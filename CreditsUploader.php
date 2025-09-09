<?php
// Incluir archivo de configuración de base de datos
include("db.php");
// Incluir autenticación Firebase
include("includes/auth.php");

// Configuración de seguridad
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB para XLSX
define('ALLOWED_EXTENSIONS', ['xlsx']);
define('UPLOAD_DIR', 'uploads/');
define('DATABASE_COLUMNS', 31); // Las columnas que van a la base de datos

// Función para generar token CSRF
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Función para validar token CSRF
function validateCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Función para sanitizar datos
function sanitizeData($data)
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Función para convertir fecha específica para credits
function convertCreditDate($dateString)
{
    if (empty($dateString)) return null;

    // Si es un número (fecha de Excel), convertir
    if (is_numeric($dateString)) {
        // Excel almacena fechas como números desde 1900-01-01
        $unix_date = ($dateString - 25569) * 86400;
        return date('Y-m-d', $unix_date);
    }

    // Si es string, intentar parsear
    $date = DateTime::createFromFormat('n/j/Y', $dateString);
    if ($date === false) {
        $date = DateTime::createFromFormat('m/d/Y', $dateString);
    }
    if ($date === false) {
        $date = DateTime::createFromFormat('n/j/Y H:i', $dateString);
    }
    if ($date === false) {
        $date = DateTime::createFromFormat('m/d/Y H:i', $dateString);
    }
    if ($date === false) {
        $date = DateTime::createFromFormat('Y-m-d', $dateString);
    }

    return $date !== false ? $date->format('Y-m-d') : null;
}

// Clase para manejar XLSX con data descriptors
class DataDescriptorXLSXReader
{
    private $strings = [];
    private $worksheetData = [];

    public function __construct($xlsxPath)
    {
        if (!$this->extractWithDataDescriptors($xlsxPath)) {
            throw new Exception('No se pudo extraer el archivo XLSX. Verifique que sea un archivo válido.');
        }
    }

    private function extractWithDataDescriptors($xlsxPath)
    {
        $fileContent = file_get_contents($xlsxPath);
        if ($fileContent === false || strlen($fileContent) < 4) {
            return false;
        }

        // Verificar firma ZIP
        if (substr($fileContent, 0, 4) !== "PK\x03\x04") {
            return false;
        }

        error_log("XLSX DataDesc: Iniciando extracción con soporte para data descriptors");

        // Buscar archivos específicos con manejo de data descriptors
        $worksheetXML = $this->extractFileWithDataDescriptor($fileContent, 'xl/worksheets/sheet1.xml');
        $sharedStringsXML = $this->extractFileWithDataDescriptor($fileContent, 'xl/sharedStrings.xml');

        if (!$worksheetXML) {
            error_log("XLSX DataDesc: No se pudo extraer worksheet XML");
            return false;
        }

        error_log("XLSX DataDesc: Worksheet XML extraído exitosamente, longitud: " . strlen($worksheetXML));

        // Procesar shared strings si existe
        if ($sharedStringsXML) {
            error_log("XLSX DataDesc: Shared strings encontrado, longitud: " . strlen($sharedStringsXML));
            $this->parseSharedStrings($sharedStringsXML);
        } else {
            error_log("XLSX DataDesc: No hay shared strings, usando valores inline");
        }

        // Procesar worksheet
        $this->parseWorksheet($worksheetXML);

        return true;
    }

    private function extractFileWithDataDescriptor($zipContent, $filename)
    {
        $contentLength = strlen($zipContent);
        $pos = 0;

        // Buscar todas las firmas de archivos locales
        while (($pos = strpos($zipContent, "PK\x03\x04", $pos)) !== false) {

            if ($pos + 30 > $contentLength) {
                break;
            }

            // Leer header local (30 bytes)
            $header = substr($zipContent, $pos, 30);
            $headerData = unpack('Vsig/vver/vflag/vmethod/vtime/vdate/Vcrc/Vcompsize/Vuncompsize/vnamelen/vextralen', $header);

            $nameLen = $headerData['namelen'];
            $extraLen = $headerData['extralen'];
            $compSize = $headerData['compsize'];
            $uncompSize = $headerData['uncompsize'];
            $method = $headerData['method'];
            $flags = $headerData['flag'];

            if ($pos + 30 + $nameLen > $contentLength) {
                $pos++;
                continue;
            }

            // Leer nombre del archivo
            $currentFilename = substr($zipContent, $pos + 30, $nameLen);

            // Si es el archivo que buscamos
            if ($currentFilename === $filename) {
                error_log("XLSX DataDesc: Encontrado archivo '$filename' en posición $pos");

                $dataPos = $pos + 30 + $nameLen + $extraLen;

                // Verificar si usa data descriptor (bit 3 de flags)
                $hasDataDescriptor = ($flags & 0x08) !== 0;

                if ($hasDataDescriptor) {
                    error_log("XLSX DataDesc: Archivo '$filename' usa data descriptor");

                    // Buscar el data descriptor después de los datos
                    $descriptorPos = $this->findDataDescriptor($zipContent, $dataPos);

                    if ($descriptorPos === false) {
                        error_log("XLSX DataDesc: No se encontró data descriptor para '$filename'");
                        $pos++;
                        continue;
                    }

                    // Leer tamaños del data descriptor
                    $descriptor = substr($zipContent, $descriptorPos, 16);
                    $descData = unpack('Vsig/Vcrc/Vcompsize/Vuncompsize', $descriptor);

                    $compSize = $descData['compsize'];
                    $uncompSize = $descData['uncompsize'];

                    error_log("XLSX DataDesc: Tamaños del data descriptor - Comprimido: $compSize, Descomprimido: $uncompSize");
                } else {
                    error_log("XLSX DataDesc: Archivo '$filename' usa tamaños del header");
                }

                if ($dataPos + $compSize > $contentLength) {
                    error_log("XLSX DataDesc: Datos del archivo fuera de rango");
                    $pos++;
                    continue;
                }

                $compressedData = substr($zipContent, $dataPos, $compSize);

                // Descomprimir si es necesario
                if ($method == 8) { // Deflate
                    $data = @gzinflate($compressedData);
                    if ($data === false) {
                        error_log("XLSX DataDesc: Error al descomprimir archivo '$filename'");
                        $pos++;
                        continue;
                    }
                } else {
                    $data = $compressedData;
                }

                error_log("XLSX DataDesc: Archivo '$filename' extraído exitosamente, tamaño descomprimido: " . strlen($data));
                return $data;
            }

            $pos++;
        }

        return false;
    }

    private function findDataDescriptor($zipContent, $startPos)
    {
        $contentLength = strlen($zipContent);

        // Buscar signature del data descriptor PK\x07\x08 después de startPos
        for ($i = $startPos; $i < $contentLength - 4; $i++) {
            if (substr($zipContent, $i, 4) === "PK\x07\x08") {
                return $i;
            }
        }

        return false;
    }

    private function parseSharedStrings($xml)
    {
        if (preg_match_all('/<si[^>]*>.*?<t[^>]*>(.*?)<\/t>.*?<\/si>/s', $xml, $matches)) {
            foreach ($matches[1] as $string) {
                $this->strings[] = html_entity_decode($string, ENT_XML1, 'UTF-8');
            }
        }
        error_log("XLSX DataDesc: Procesados " . count($this->strings) . " shared strings");
    }

    private function parseWorksheet($xml)
    {
        error_log("XLSX DataDesc: Iniciando parsing de worksheet, longitud XML: " . strlen($xml));

        // Primer paso: extraer todas las celdas con un regex muy amplio
        $allCellsPattern = '/<c[^>]*r="([A-Z]+)(\d+)"[^>]*>(.*?)<\/c>/s';

        if (!preg_match_all($allCellsPattern, $xml, $allMatches, PREG_SET_ORDER)) {
            throw new Exception('No se encontraron celdas en la hoja de trabajo');
        }

        error_log("XLSX DataDesc: Encontradas " . count($allMatches) . " celdas en total");

        $cells = [];

        foreach ($allMatches as $match) {
            $column = $match[1];
            $row = (int)$match[2] - 1; // Convertir a índice 0
            $colIndex = $this->columnToIndex($column);
            $cellContent = $match[3];

            $value = '';

            // Múltiples estrategias para extraer el valor de la celda

            // 1. Buscar valor directo en <v>
            if (preg_match('/<v[^>]*>(.*?)<\/v>/', $cellContent, $vMatch)) {
                $rawValue = $vMatch[1];

                // Verificar si es un shared string (buscar atributo t="s")
                if (preg_match('/t="s"/', $cellContent)) {
                    $stringIndex = (int)$rawValue;
                    if (isset($this->strings[$stringIndex])) {
                        $value = $this->strings[$stringIndex];
                        error_log("XLSX DataDesc: Celda $column$row usa shared string [$stringIndex]: '$value'");
                    } else {
                        error_log("XLSX DataDesc: WARNING - Shared string [$stringIndex] no encontrado para celda $column$row");
                        $value = $rawValue;
                    }
                } else {
                    // Valor directo (número, fecha, etc.)
                    $value = $rawValue;
                }
            }
            // 2. Buscar inline string en <is><t>
            else if (preg_match('/<is[^>]*>.*?<t[^>]*>(.*?)<\/t>.*?<\/is>/', $cellContent, $isMatch)) {
                $value = html_entity_decode($isMatch[1], ENT_XML1, 'UTF-8');
                error_log("XLSX DataDesc: Celda $column$row usa inline string: '$value'");
            }
            // 3. Buscar string directo en <t>
            else if (preg_match('/<t[^>]*>(.*?)<\/t>/', $cellContent, $tMatch)) {
                $value = html_entity_decode($tMatch[1], ENT_XML1, 'UTF-8');
                error_log("XLSX DataDesc: Celda $column$row usa string directo: '$value'");
            }
            // 4. Celda vacía o sin valor
            else {
                $value = '';
            }

            if (!empty($value) || $row == 16) { // Log especial para fila de headers
                error_log("XLSX DataDesc: Celda $column" . ($row + 1) . " (índice $row,$colIndex) = '$value'");
            }

            $cells["$row-$colIndex"] = $value;
        }

        // Convertir a array estructurado
        $maxRow = 0;
        $maxCol = 0;

        foreach ($cells as $key => $value) {
            list($row, $col) = explode('-', $key);
            $maxRow = max($maxRow, (int)$row);
            $maxCol = max($maxCol, (int)$col);
        }

        for ($row = 0; $row <= $maxRow; $row++) {
            $rowData = [];
            for ($col = 0; $col <= $maxCol; $col++) {
                $key = "$row-$col";
                $rowData[] = isset($cells[$key]) ? $cells[$key] : '';
            }
            $this->worksheetData[] = $rowData;
        }

        error_log("XLSX DataDesc: Procesadas " . count($this->worksheetData) . " filas, " . ($maxCol + 1) . " columnas máximo");

        // Log especial para la fila de headers (fila 17, índice 16)
        if (isset($this->worksheetData[16])) {
            error_log("XLSX DataDesc: Headers en fila 17: " . print_r($this->worksheetData[16], true));
        }
    }

    private function columnToIndex($column)
    {
        $index = 0;
        $length = strlen($column);
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($column[$i]) - ord('A') + 1);
        }
        return $index - 1;
    }

    public function getWorksheetData()
    {
        return $this->worksheetData;
    }
}

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token de seguridad inválido.';
        $messageType = 'danger';
    } else {
        // Validar archivo subido
        if (!isset($_FILES['xlsx_file']) || $_FILES['xlsx_file']['error'] !== UPLOAD_ERR_OK) {
            $message = 'Error al subir el archivo.';
            $messageType = 'danger';
        } else {
            $file = $_FILES['xlsx_file'];

            // Validar tamaño
            if ($file['size'] > MAX_FILE_SIZE) {
                $message = 'El archivo es demasiado grande. Máximo 10MB.';
                $messageType = 'danger';
            } else {
                // Validar extensión
                $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($fileExtension, ALLOWED_EXTENSIONS)) {
                    $message = 'Solo se permiten archivos XLSX.';
                    $messageType = 'danger';
                } else {
                    // Procesar el archivo XLSX
                    try {
                        // Usar la conexión existente de db.php
                        if (!$conn2) {
                            throw new Exception('Error de conexión a la base de datos');
                        }

                        mysqli_set_charset($conn2, 'utf8');

                        // Crear instancia del lector XLSX con data descriptors y mapeo
                        error_log("XLSX DataDesc: Iniciando procesamiento con data descriptors y mapeo de campos");
                        $xlsxReader = new DataDescriptorXLSXReader($file['tmp_name']);
                        $worksheetData = $xlsxReader->getWorksheetData();

                        if (empty($worksheetData)) {
                            throw new Exception('El archivo XLSX está vacío o no se pudo leer');
                        }

                        // ANÁLISIS DEL ARCHIVO
                        $totalRows = count($worksheetData);
                        $firstRowColumns = isset($worksheetData[0]) ? count($worksheetData[0]) : 0;

                        error_log("XLSX DataDesc - Total filas: $totalRows, Columnas primera fila: $firstRowColumns");

                        // LÓGICA DE LIMPIEZA (igual que antes)
                        $headerRowIndex = 16; // Fila 17 en Excel (índice 16)
                        $dataStartRow = 17;   // Fila 18 en Excel (índice 17)
                        $trashRowsAtEnd = 33; // Últimas 33 filas (basura/comentarios)

                        // Verificar que el archivo tenga la estructura mínima esperada
                        if ($totalRows <= $headerRowIndex) {
                            throw new Exception("El archivo tiene muy pocas filas. Se necesitan al menos " . ($headerRowIndex + 1) . " filas, pero solo hay $totalRows");
                        }

                        if ($totalRows < ($dataStartRow + $trashRowsAtEnd + 1)) {
                            throw new Exception("El archivo tiene muy pocas filas. Total: $totalRows, se necesitan al menos " . ($dataStartRow + $trashRowsAtEnd + 1));
                        }

                        // Obtener encabezados para mapeo
                        $headers = isset($worksheetData[$headerRowIndex]) ? $worksheetData[$headerRowIndex] : [];
                        $headerCount = count($headers);

                        error_log("XLSX Headers - Encontrados: $headerCount encabezados");
                        error_log("XLSX Headers - Todos los encabezados: " . print_r($headers, true));

                        // MAPEO DE CAMPOS XLSX -> BASE DE DATOS (mejorado y robusto)
                        $fieldMapping = [
                            'Credit #' => 'CreditNum',
                            'Credit Date' => 'CreditDate',
                            'Approved On' => 'ApprovedOn',
                            'Approved By' => 'ApprovedBy',
                            'Order #' => 'OrderNum',
                            'Location' => 'Location',
                            'Main Location' => 'MainLocation',
                            'Customer' => 'Customer',
                            'Customer Code' => 'CustomerCode',
                            'Order Date' => 'OrderDate',
                            'Sales Person' => 'SalesPerson',
                            'Status' => 'Status',
                            'Type' => 'Type',
                            'Description' => 'Description',
                            'Accounting Code' => 'AccountingCode',
                            'Vendor' => 'Vendor',
                            'Vendor Code' => 'VendorCode',
                            'Boxes' => 'Boxes',
                            'Box Type' => 'BoxType',
                            'Unit Type' => 'UnitType',
                            'Total Units' => 'TotalUnits',
                            'AWB' => 'AWB',
                            'Aging' => 'Aging',
                            'Amount' => 'Amount',
                            'Credits Units' => 'CreditsUnits',
                            'Credits' => 'Credits',
                            'Credit Freight' => 'CreditFreight',
                            'Total Credits' => 'TotalCredits',
                            'Credit Reason' => 'CreditReason',
                            'Tax Percent' => 'TaxPercent',
                            'Unit Cost' => 'UnitCost'
                        ];

                        // Crear mapeo de índices de columnas (más robusto)
                        $columnIndexes = [];
                        foreach ($headers as $index => $header) {
                            // Limpiar header de espacios, saltos de línea y caracteres especiales
                            $cleanHeader = trim(str_replace(["\r", "\n", "\t"], '', $header));

                            // Log para debugging
                            error_log("XLSX Header $index: '" . $cleanHeader . "' (longitud: " . strlen($cleanHeader) . ")");

                            if (isset($fieldMapping[$cleanHeader])) {
                                $dbField = $fieldMapping[$cleanHeader];
                                $columnIndexes[$dbField] = $index;
                                error_log("XLSX Mapeo exitoso: '$cleanHeader' (índice $index) -> '$dbField'");
                            } else {
                                error_log("XLSX Header no mapeado: '$cleanHeader'");
                            }
                        }

                        // Log del mapeo completo para debugging
                        error_log("XLSX Columnas mapeadas: " . print_r($columnIndexes, true));

                        // Verificar campos requeridos
                        $requiredFields = ['CreditNum', 'Customer', 'Amount'];
                        $missingFields = [];
                        foreach ($requiredFields as $field) {
                            if (!isset($columnIndexes[$field])) {
                                $missingFields[] = $field;
                                error_log("XLSX Campo requerido faltante: $field");
                            } else {
                                error_log("XLSX Campo requerido encontrado: $field en índice " . $columnIndexes[$field]);
                            }
                        }

                        if (!empty($missingFields)) {
                            $availableHeaders = array_keys($fieldMapping);
                            throw new Exception("Campos requeridos no encontrados en el archivo: " . implode(', ', $missingFields) .
                                ". Headers disponibles en el archivo: " . implode(', ', array_slice($headers, 0, 10)));
                        }

                        error_log("XLSX Mapeo completado exitosamente - " . count($columnIndexes) . " campos mapeados");

                        // Calcular rango de datos válidos (MANTENER lógica original)
                        $dataEndRow = $totalRows - $trashRowsAtEnd - 1;

                        if ($dataEndRow < $dataStartRow) {
                            throw new Exception("No hay suficientes filas de datos después de omitir basura al inicio y final");
                        }

                        $validDataRows = array_slice($worksheetData, $dataStartRow, $dataEndRow - $dataStartRow + 1);

                        error_log("XLSX Procesamiento - Filas de datos válidas: " . count($validDataRows) . " (desde fila " . ($dataStartRow + 1) . " hasta fila " . ($dataEndRow + 1) . ") - Últimas 33 filas omitidas (basura/comentarios)");

                        if (empty($validDataRows)) {
                            throw new Exception('No se encontraron filas de datos válidas después de la limpieza');
                        }

                        // Preparar la consulta de inserción para credits (31 columnas en BD)
                        $insertQuery = "INSERT INTO credits (
                            CreditNum, CreditDate, ApprovedOn, ApprovedBy, OrderNum, Location, MainLocation,
                            Customer, CustomerCode, OrderDate, SalesPerson, Status, Type, Description,
                            AccountingCode, Vendor, VendorCode, Boxes, BoxType, UnitType, TotalUnits,
                            AWB, Aging, Amount, CreditsUnits, Credits, CreditFreight, TotalCredits,
                            CreditReason, TaxPercent, UnitCost
                        ) VALUES (" . str_repeat('?,', DATABASE_COLUMNS - 1) . "?)";

                        $stmt = $conn2->prepare($insertQuery);

                        if (!$stmt) {
                            throw new Exception('Error preparando la consulta: ' . $conn2->error);
                        }

                        $lineNumber = $dataStartRow; // Empezar el contador desde la fila real
                        $insertedRows = 0;
                        $errors = [];

                        foreach ($validDataRows as $rowData) {
                            $lineNumber++;

                            // Verificar que no sea una fila completamente vacía
                            $hasData = false;
                            foreach ($rowData as $cell) {
                                if (!empty(trim($cell))) {
                                    $hasData = true;
                                    break;
                                }
                            }

                            if (!$hasData) {
                                continue; // Saltar filas completamente vacías
                            }

                            // MAPEAR DATOS USANDO LOS ÍNDICES DE COLUMNAS
                            $mappedData = [];

                            // Orden de campos para la base de datos (31 campos)
                            $dbFields = [
                                'CreditNum',
                                'CreditDate',
                                'ApprovedOn',
                                'ApprovedBy',
                                'OrderNum',
                                'Location',
                                'MainLocation',
                                'Customer',
                                'CustomerCode',
                                'OrderDate',
                                'SalesPerson',
                                'Status',
                                'Type',
                                'Description',
                                'AccountingCode',
                                'Vendor',
                                'VendorCode',
                                'Boxes',
                                'BoxType',
                                'UnitType',
                                'TotalUnits',
                                'AWB',
                                'Aging',
                                'Amount',
                                'CreditsUnits',
                                'Credits',
                                'CreditFreight',
                                'TotalCredits',
                                'CreditReason',
                                'TaxPercent',
                                'UnitCost'
                            ];

                            foreach ($dbFields as $dbField) {
                                if (isset($columnIndexes[$dbField]) && isset($rowData[$columnIndexes[$dbField]])) {
                                    $value = trim($rowData[$columnIndexes[$dbField]]);
                                    $mappedData[] = $value;
                                } else {
                                    $mappedData[] = ''; // Campo no encontrado o vacío
                                }
                            }

                            // Sanitizar y procesar los datos mapeados
                            $cleanData = array_map('sanitizeData', $mappedData);

                            // Convertir fechas específicas usando nombres de campos
                            $creditDateIndex = array_search('CreditDate', $dbFields);
                            $approvedOnIndex = array_search('ApprovedOn', $dbFields);
                            $orderDateIndex = array_search('OrderDate', $dbFields);

                            if ($creditDateIndex !== false) {
                                $cleanData[$creditDateIndex] = convertCreditDate($cleanData[$creditDateIndex]);
                            }
                            if ($approvedOnIndex !== false) {
                                $cleanData[$approvedOnIndex] = convertCreditDate($cleanData[$approvedOnIndex]);
                            }
                            if ($orderDateIndex !== false) {
                                $cleanData[$orderDateIndex] = convertCreditDate($cleanData[$orderDateIndex]);
                            }

                            // Convertir valores numéricos y manejar valores vacíos
                            for ($i = 0; $i < count($cleanData); $i++) {
                                if ($cleanData[$i] === '' || $cleanData[$i] === null) {
                                    $cleanData[$i] = null;
                                }
                            }

                            // Validar que al menos tenemos CreditNum (usando mapeo)
                            $creditNumIndex = array_search('CreditNum', $dbFields);
                            if ($creditNumIndex === false || empty($cleanData[$creditNumIndex])) {
                                $errors[] = "Fila Excel $lineNumber: CreditNum es requerido";
                                continue;
                            }

                            // Bind parameters
                            $stmt->bind_param(str_repeat('s', DATABASE_COLUMNS), ...array_values($cleanData));

                            if ($stmt->execute()) {
                                $insertedRows++;
                            } else {
                                $errors[] = "Fila Excel $lineNumber: Error al insertar - " . $stmt->error;
                            }
                        }

                        $stmt->close();

                        if ($insertedRows > 0) {
                            $message = "Proceso completado exitosamente. $insertedRows filas de créditos insertadas desde archivo XLSX.";
                            $messageType = 'success';

                            if (!empty($errors)) {
                                $message .= " Se encontraron " . count($errors) . " errores.";
                            }
                        } else {
                            $message = "No se pudieron insertar filas. Revisa el formato del archivo XLSX.";
                            $messageType = 'danger';
                        }

                        // Mostrar errores si los hay (solo los primeros 10)
                        if (!empty($errors)) {
                            $message .= "<br><br><strong>Errores encontrados:</strong><br>";
                            $message .= implode("<br>", array_slice($errors, 0, 10));
                            if (count($errors) > 10) {
                                $message .= "<br>... y " . (count($errors) - 10) . " errores más.";
                            }
                        }
                    } catch (Exception $e) {
                        $message = 'Error: ' . $e->getMessage();
                        $messageType = 'danger';
                        error_log("XLSX DataDesc Error: " . $e->getMessage());
                    }
                }
            }
        }
    }
}

// Incluir header
include("includes/header.php");
?>

<!-- Mensaje de carga para autenticación -->
<div id="loading-message" style="display: none;">
    <div class="d-flex justify-content-center align-items-center vh-100">
        <div class="text-center">
            <div class="spinner-border text-primary mb-3" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <h4>Verificando autenticación...</h4>
            <p class="text-muted">Por favor espera mientras verificamos tus credenciales.</p>
        </div>
    </div>
</div>

<div id="main-content" style="display: none;">
    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow">
                    <div class="card-header bg-success text-white">
                        <h3 class="card-title mb-0">
                            <i class="fas fa-file-excel me-2"></i>
                            Subir Archivo XLSX - Puawai Credits Data
                        </h3>
                    </div>
                    <div class="card-body">
                        <!-- Alerta sobre data descriptors y mapeo -->
                        <!-- <div class="alert alert-success">
                            <h6 class="alert-heading"><i class="fas fa-check-circle me-2"></i>Problema Resuelto - Data
                                Descriptors + Mapeo de Campos:</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="mb-0 small">
                                        <li>✅ <strong>Data descriptors:</strong> Soporte para flags 0x0808</li>
                                        <li>✅ <strong>Mapeo automático:</strong> "Credit #" → "CreditNum"</li>
                                        <li>✅ <strong>31 campos mapeados:</strong> XLSX → Base de datos</li>
                                        <li>✅ <strong>Validación completa:</strong> Verifica campos requeridos</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="mb-0 small">
                                        <li>✅ <strong>Tu archivo específico:</strong> Optimizado para tu estructura</li>
                                        <li>✅ <strong>Logs detallados:</strong> Muestra mapeo de cada campo</li>
                                        <li>✅ <strong>Sin dependencias:</strong> Solo PHP básico</li>
                                        <li>✅ <strong>Robusto:</strong> Maneja nombres de columnas diferentes</li>
                                    </ul>
                                </div>
                            </div>
                        </div> -->

                        <!-- Mapeo de campos -->
                        <!-- <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fas fa-exchange-alt me-2"></i>Mapeo Automático de
                                Campos:</h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Ejemplos de mapeo XLSX → BD:</strong>
                                    <ul class="mb-0 small">
                                        <li><code>"Credit #"</code> → <code>CreditNum</code></li>
                                        <li><code>"Credit Date"</code> → <code>CreditDate</code></li>
                                        <li><code>"Order #"</code> → <code>OrderNum</code></li>
                                        <li><code>"Sales Person"</code> → <code>SalesPerson</code></li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <strong>Campos requeridos:</strong>
                                    <ul class="mb-0 small">
                                        <li><code>Credit #</code> (requerido)</li>
                                        <li><code>Customer</code> (requerido)</li>
                                        <li><code>Amount</code> (requerido)</li>
                                        <li>28 campos adicionales (opcionales)</li>
                                    </ul>
                                </div>
                            </div>
                        </div> -->

                        <!-- Instrucciones -->
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fas fa-upload me-2"></i>Instrucciones:</h6>
                            <ul class="mb-0">
                                <li>Selecciona tu archivo XLSX directamente del sistema</li>
                                <li><strong>Mapeo automático:</strong> Los nombres de columnas se mapean automáticamente
                                    (ej: "Credit #" → CreditNum)</li>
                                <li><strong>Campos requeridos:</strong> Credit #, Customer, Amount deben estar presentes
                                </li>
                                <li><strong>Limpieza automática:</strong> Se omiten las primeras 16 filas (basura)</li>
                                <li><strong>Encabezados:</strong> La fila 17 se lee para mapear nombres de columnas</li>
                                <li><strong>Datos:</strong> Se procesan desde la fila 18 hasta las últimas 33 filas (que
                                    se omiten porque contienen comentarios)</li>
                                <li>Tamaño máximo: 10MB</li>
                                <li><strong>Compatible:</strong> Con archivos XLSX que usan data descriptors y nombres
                                    de columnas diferentes</li>
                            </ul>
                        </div>

                        <!-- Proceso de limpieza -->
                        <!-- <div class="alert alert-secondary">
                            <h6 class="alert-heading"><i class="fas fa-broom me-2"></i>Proceso de Limpieza Automática:
                            </h6>
                            <div class="row">
                                <div class="col-md-4">
                                    <strong>Filas 1-16:</strong><br>
                                    <span class="text-danger">❌ Omitidas (basura)</span>
                                </div>
                                <div class="col-md-4">
                                    <strong>Fila 17:</strong><br>
                                    <span class="text-info">📋 Encabezados</span>
                                </div>
                                <div class="col-md-4">
                                    <strong>Últimas 33 filas:</strong><br>
                                    <span class="text-danger">❌ Omitidas (comentarios)</span>
                                </div>
                            </div>
                            <hr>
                            <small><strong>Solo se procesan las filas de datos válidas:</strong> Desde fila 18 hasta
                                (total - 33)<br>
                                <strong>Importante:</strong> Las últimas 33 filas contienen 1 fila en blanco + 32 filas
                                de comentarios que NO deben procesarse</small>
                        </div> -->

                        <!-- Mensajes de resultado -->
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                                <?php echo $message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <!-- Formulario de subida -->
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                            <div class="mb-3">
                                <label for="xlsx_file" class="form-label">
                                    <i class="fas fa-file-excel me-2"></i>Seleccionar archivo XLSX de Créditos:
                                </label>
                                <input type="file" name="xlsx_file" id="xlsx_file" class="form-control"
                                    accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                                    required>
                                <div class="form-text">
                                    <i class="fas fa-magic text-success me-1"></i>
                                    Archivo XLSX con mapeo automático de campos (máximo 10MB) - Problema resuelto
                                </div>
                            </div>

                            <!-- Barra de progreso -->
                            <div class="progress mb-3" id="progressContainer" style="display: none;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"
                                    id="progressBar" role="progressbar" style="width: 0%"></div>
                            </div>

                            <div class="mt-auto">
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                                        <i class="fas fa-file-excel me-2"></i>
                                        Procesar Archivo Creditos XLSX
                                    </button>

                                    <button type="button" class="btn btn-success btn-lg w-100"
                                        onclick="window.location.href='updater.php'">
                                        <i class="fa-solid fa-circle-arrow-left"></i>
                                        Regresar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Proteger la página
    document.addEventListener('DOMContentLoaded', function() {
        protectPage();
    });

    // Manejo del formulario de upload
    document.getElementById('uploadForm').addEventListener('submit', function() {
        const submitBtn = document.getElementById('submitBtn');
        const progressContainer = document.getElementById('progressContainer');

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando XLSX con Mapeo de Campos...';
        progressContainer.style.display = 'block';

        // Progreso optimista para la versión con mapeo
        let progress = 0;
        const interval = setInterval(function() {
            progress += Math.random() * 18;
            if (progress > 92) progress = 92;
            document.getElementById('progressBar').style.width = progress + '%';
        }, 180);

        // Limpiar el intervalo
        setTimeout(function() {
            clearInterval(interval);
        }, 1200);
    });

    // Validación del archivo en el cliente
    document.getElementById('xlsx_file').addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            if (file.size > <?php echo MAX_FILE_SIZE; ?>) {
                alert('El archivo es demasiado grande. Máximo 10MB permitido.');
                this.value = '';
                return;
            }

            const fileName = file.name.toLowerCase();
            if (!fileName.endsWith('.xlsx')) {
                alert('Solo se permiten archivos XLSX.');
                this.value = '';
                return;
            }

            console.log('✅ Archivo XLSX seleccionado para parser con mapeo de campos:', fileName);
            console.log('📊 Tamaño:', (file.size / 1024 / 1024).toFixed(2), 'MB');

            // Mostrar información positiva
            const formText = document.querySelector('.form-text');
            formText.innerHTML =
                '<i class="fas fa-check-circle text-success me-1"></i>Archivo XLSX listo - Parser con mapeo de campos activado';
            formText.className = 'form-text text-success';
        }
    });
</script>

<?php
// Incluir footer
include("includes/footer.php");
?>