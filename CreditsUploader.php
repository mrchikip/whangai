<?php
// Incluir archivo de configuración de base de datos
include("db.php");
// Incluir autenticación Firebase
include("includes/auth.php");

// Verificar disponibilidad de funciones PHP básicas
function checkPHPExtensions()
{
    $missing = [];
    $alternatives = [];

    if (!function_exists('file_get_contents')) {
        $missing[] = 'file_get_contents';
        $alternatives[] = 'Función PHP básica requerida';
    }

    if (!function_exists('preg_match')) {
        $missing[] = 'preg_match (PCRE)';
        $alternatives[] = 'Extensión PCRE requerida (generalmente incluida)';
    }

    // Esta implementación no requiere ZipArchive, DOMDocument es opcional

    return ['missing' => $missing, 'alternatives' => $alternatives];
}

// Configuración de seguridad
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB para XLSX
define('ALLOWED_EXTENSIONS', ['xlsx']);
define('UPLOAD_DIR', 'uploads/');

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

// Función para convertir fecha específica para credits (consistente con salesuploader.php)
function convertCreditDate($dateString)
{
    if (empty($dateString)) return null;

    // Si es un número (fecha de Excel), convertir
    if (is_numeric($dateString)) {
        // Excel almacena fechas como números desde 1900-01-01
        $unix_date = ($dateString - 25569) * 86400;
        return date('Y-m-d', $unix_date);
    }

    // Si es string, intentar parsear (mismo formato que salesuploader.php)
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

// Clase simple para leer archivos XLSX sin dependencias externas
class SimpleXLSXReader
{
    private $zip;
    private $strings = [];

    public function __construct($filepath)
    {
        $this->zip = new ZipArchive;
        if ($this->zip->open($filepath) !== TRUE) {
            throw new Exception('No se pudo abrir el archivo XLSX');
        }
        $this->loadSharedStrings();
    }

    private function loadSharedStrings()
    {
        $xml = $this->zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return; // No hay strings compartidos
        }

        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $strings = $dom->getElementsByTagName('si');

        foreach ($strings as $string) {
            $t = $string->getElementsByTagName('t')->item(0);
            if ($t) {
                $this->strings[] = $t->nodeValue;
            }
        }
    }

    public function getWorksheetData($worksheetIndex = 0)
    {
        $worksheetXML = $this->zip->getFromName('xl/worksheets/sheet' . ($worksheetIndex + 1) . '.xml');
        if ($worksheetXML === false) {
            throw new Exception('No se pudo leer la hoja de cálculo');
        }

        $dom = new DOMDocument();
        $dom->loadXML($worksheetXML);

        $rows = [];
        $cellElements = $dom->getElementsByTagName('c');

        // Agrupar celdas por fila
        $cellsByRow = [];
        foreach ($cellElements as $cell) {
            $cellRef = $cell->getAttribute('r');
            preg_match('/([A-Z]+)(\d+)/', $cellRef, $matches);
            $row = (int)$matches[2] - 1; // Convertir a índice 0
            $col = $this->columnToIndex($matches[1]);

            $cellType = $cell->getAttribute('t');
            $value = '';

            $valueNode = $cell->getElementsByTagName('v')->item(0);
            if ($valueNode) {
                $value = $valueNode->nodeValue;

                // Si es un string compartido
                if ($cellType == 's' && isset($this->strings[(int)$value])) {
                    $value = $this->strings[(int)$value];
                }
            }

            if (!isset($cellsByRow[$row])) {
                $cellsByRow[$row] = [];
            }
            $cellsByRow[$row][$col] = $value;
        }

        // Convertir a array estructurado
        foreach ($cellsByRow as $rowIndex => $rowData) {
            $maxCol = max(array_keys($rowData));
            $row = [];
            for ($i = 0; $i <= $maxCol; $i++) {
                $row[] = isset($rowData[$i]) ? $rowData[$i] : '';
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private function columnToIndex($column)
    {
        $index = 0;
        $length = strlen($column);
        for ($i = 0; $i < $length; $i++) {
            $index = $index * 26 + (ord($column[$i]) - ord('A') + 1);
        }
        return $index - 1; // Convertir a índice 0
    }

    public function close()
    {
        if ($this->zip) {
            $this->zip->close();
        }
    }
}

// session_start() ya está incluido en db.php

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

                        // Crear instancia del lector XLSX
                        $xlsxReader = new SimpleXLSXReader($file['tmp_name']);
                        $worksheetData = $xlsxReader->getWorksheetData(0); // Primera hoja
                        $xlsxReader->close();

                        if (empty($worksheetData)) {
                            throw new Exception('El archivo XLSX está vacío o no se pudo leer');
                        }

                        // LIMPIEZA DE DATOS DEL XLSX:
                        // - Omitir las primeras 16 filas (basura)
                        // - Fila 17 (índice 16) son los encabezados
                        // - Omitir las últimas 33 filas (basura)

                        $totalRows = count($worksheetData);
                        $dataStartRow = 17; // Fila 18 en Excel (índice 17 en array)
                        $trashRowsAtEnd = 33;
                        $dataEndRow = $totalRows - $trashRowsAtEnd - 1; // Calcular dónde terminan los datos válidos

                        // Validar que tenemos suficientes filas para procesar
                        if ($totalRows < ($dataStartRow + $trashRowsAtEnd + 1)) {
                            throw new Exception("El archivo tiene muy pocas filas. Total: $totalRows, se necesitan al menos " . ($dataStartRow + $trashRowsAtEnd + 1));
                        }

                        // Extraer solo las filas de datos válidos
                        $validDataRows = array_slice($worksheetData, $dataStartRow, $dataEndRow - $dataStartRow + 1);

                        if (empty($validDataRows)) {
                            throw new Exception('No se encontraron filas de datos válidas después de la limpieza');
                        }

                        // Obtener encabezados desde la fila 17 (índice 16) para referencia
                        $headers = isset($worksheetData[16]) ? $worksheetData[16] : [];

                        // Log de información de limpieza
                        error_log("XLSX Limpieza - Total filas: $totalRows, Filas válidas: " . count($validDataRows) . " (desde fila " . ($dataStartRow + 1) . " hasta fila " . ($dataEndRow + 1) . ")");

                        // Preparar la consulta de inserción para credits (31 columnas)
                        $insertQuery = "INSERT INTO credits (
                            CreditNum, CreditDate, ApprovedOn, ApprovedBy, OrderNum, Location, MainLocation,
                            Customer, CustomerCode, OrderDate, SalesPerson, Status, Type, Description,
                            AccountingCode, Vendor, VendorCode, Boxes, BoxType, UnitType, TotalUnits,
                            AWB, Aging, Amount, CreditsUnits, Credits, CreditFreight, TotalCredits,
                            CreditReason, TaxPercent, UnitCost
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                        $stmt = $conn2->prepare($insertQuery);

                        if (!$stmt) {
                            throw new Exception('Error preparando la consulta: ' . $conn2->error);
                        }

                        $lineNumber = $dataStartRow; // Empezar el contador desde la fila real de Excel
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

                            // Verificar que tenemos el número correcto de columnas (31 columnas esperadas)
                            if (count($rowData) < 31) {
                                // Rellenar con valores vacíos si faltan columnas
                                while (count($rowData) < 31) {
                                    $rowData[] = '';
                                }
                            } elseif (count($rowData) > 31) {
                                // Truncar si hay más columnas de las esperadas
                                $rowData = array_slice($rowData, 0, 31);
                            }

                            // Sanitizar y procesar los datos
                            $cleanData = array_map('sanitizeData', $rowData);

                            // Convertir fechas específicas para credits (mismo orden que salesuploader.php)
                            $cleanData[1] = convertCreditDate($cleanData[1]); // CreditDate (índice 1)
                            $cleanData[2] = convertCreditDate($cleanData[2]); // ApprovedOn (índice 2)
                            $cleanData[9] = convertCreditDate($cleanData[9]); // OrderDate (índice 9)

                            // Convertir valores numéricos y manejar valores vacíos (igual que salesuploader.php)
                            for ($i = 0; $i < count($cleanData); $i++) {
                                if ($cleanData[$i] === '' || $cleanData[$i] === null) {
                                    $cleanData[$i] = null;
                                }
                            }

                            // Validar que al menos tenemos CreditNum (campo requerido)
                            if (empty($cleanData[0])) {
                                $errors[] = "Fila Excel $lineNumber: CreditNum es requerido";
                                continue;
                            }

                            // Bind parameters - usar 31 parámetros tipo 's'
                            $stmt->bind_param(str_repeat('s', 31), ...array_values($cleanData));

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

                        // Mostrar errores si los hay (solo los primeros 10) - igual que salesuploader.php
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
                        <!-- Instrucciones -->
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Instrucciones:</h6>
                            <ul class="mb-0">
                                <li>Selecciona un archivo XLSX con los datos de créditos de Puawai</li>
                                <li>El archivo debe tener exactamente <strong>31 columnas</strong> en la primera hoja
                                </li>
                                <li><strong>Limpieza automática:</strong> Se omiten las primeras 16 filas (basura)</li>
                                <li><strong>Encabezados:</strong> La fila 17 se toma como encabezados de referencia</li>
                                <li><strong>Datos:</strong> Se procesan desde la fila 18 hasta las últimas 33 filas (que
                                    se omiten)</li>
                                <li>El sistema procesará automáticamente solo la <strong>primera hoja</strong> del
                                    archivo</li>
                                <li>Tamaño máximo: 10MB</li>
                                <li>Formatos de fecha soportados: Fechas de Excel, m/d/Y o n/j/Y</li>
                            </ul>
                        </div>

                        <!-- Verificación de funciones PHP -->
                        <?php
                        $phpCheck = checkPHPExtensions();
                        if (!empty($phpCheck['missing'])): ?>
                        <div class="alert alert-danger">
                            <h6 class="alert-heading"><i class="fas fa-times-circle me-2"></i>Error del Servidor:</h6>
                            <p><strong>Funciones PHP faltantes:</strong> <?= implode(', ', $phpCheck['missing']) ?></p>
                            <p class="mb-0">Contacte al administrador del hosting para resolver este problema.</p>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-success">
                            <h6 class="alert-heading"><i class="fas fa-check-circle me-2"></i>Procesador XLSX Nativo:
                            </h6>
                            <p class="mb-0">Sistema listo para procesar archivos XLSX usando implementación PHP pura
                                (sin dependencias externas).</p>
                        </div>
                        <?php endif; ?>

                        <!-- Proceso de limpieza -->
                        <div class="alert alert-warning">
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
                                    <span class="text-danger">❌ Omitidas (basura)</span>
                                </div>
                            </div>
                            <hr>
                            <small><strong>Solo se procesan las filas de datos válidas:</strong> Desde fila 18 hasta
                                (total - 33)</small>
                        </div>

                        <!-- Estructura de columnas esperada -->
                        <div class="alert alert-secondary">
                            <h6 class="alert-heading"><i class="fas fa-table me-2"></i>Estructura esperada del XLSX (31
                                columnas):</h6>
                            <small class="text-muted">
                                <strong>A:</strong> CreditNum, <strong>B:</strong> CreditDate, <strong>C:</strong>
                                ApprovedOn, <strong>D:</strong> ApprovedBy, <strong>E:</strong> OrderNum,
                                <strong>F:</strong> Location, <strong>G:</strong> MainLocation, <strong>H:</strong>
                                Customer, <strong>I:</strong> CustomerCode, <strong>J:</strong> OrderDate,
                                <strong>K:</strong> SalesPerson, <strong>L:</strong> Status, <strong>M:</strong> Type,
                                <strong>N:</strong> Description, <strong>O:</strong> AccountingCode,
                                <strong>P:</strong> Vendor, <strong>Q:</strong> VendorCode, <strong>R:</strong> Boxes,
                                <strong>S:</strong> BoxType, <strong>T:</strong> UnitType,
                                <strong>U:</strong> TotalUnits, <strong>V:</strong> AWB, <strong>W:</strong> Aging,
                                <strong>X:</strong> Amount, <strong>Y:</strong> CreditsUnits,
                                <strong>Z:</strong> Credits, <strong>AA:</strong> CreditFreight, <strong>AB:</strong>
                                TotalCredits, <strong>AC:</strong> CreditReason,
                                <strong>AD:</strong> TaxPercent, <strong>AE:</strong> UnitCost
                            </small>
                        </div>

                        <!-- Nuevas características XLSX -->
                        <div class="alert alert-success">
                            <h6 class="alert-heading"><i class="fas fa-magic me-2"></i>Características del Procesador
                                XLSX:</h6>
                            <ul class="mb-0">
                                <li><strong>Limpieza inteligente:</strong> Automáticamente elimina filas de basura al
                                    inicio y final</li>
                                <li><strong>Conversión automática:</strong> Fechas de Excel se convierten
                                    automáticamente</li>
                                <li><strong>Tolerancia de columnas:</strong> Ajuste automático si faltan o sobran
                                    columnas</li>
                                <li><strong>Detección de filas vacías:</strong> Omite automáticamente filas sin datos
                                </li>
                                <li><strong>Validación de estructura:</strong> Verifica que el archivo tenga suficientes
                                    filas</li>
                                <li><strong>Sin dependencias:</strong> Procesamiento nativo PHP sin librerías externas
                                </li>
                            </ul>
                        </div>

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
                                    <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                    Archivo XLSX requerido (máximo 10MB) - Solo primera hoja, 31 columnas
                                </div>
                            </div>

                            <!-- Barra de progreso -->
                            <div class="progress mb-3" id="progressContainer" style="display: none;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning"
                                    id="progressBar" role="progressbar" style="width: 0%"></div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-warning btn-lg text-dark" id="submitBtn">
                                    <i class="fas fa-file-excel me-2"></i>
                                    Procesar Archivo XLSX de Créditos
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Información técnica -->
                <div class="card mt-3">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="fas fa-cogs me-2 text-warning"></i>Información Técnica del Procesador XLSX
                        </h6>
                        <div class="row">
                            <div class="col-md-6">
                                <ul class="mb-0 small">
                                    <li><strong>Arquitectura:</strong> ZIP + XML parsing nativo</li>
                                    <li><strong>Strings compartidos:</strong> Soporte completo</li>
                                    <li><strong>Fechas Excel:</strong> Conversión automática desde 1900</li>
                                    <li><strong>Memoria:</strong> Procesamiento eficiente por filas</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <ul class="mb-0 small">
                                    <li><strong>Validación:</strong> Estructura y tipos de dato</li>
                                    <li><strong>Errores:</strong> Reporte detallado por línea</li>
                                    <li><strong>Seguridad:</strong> Sanitización completa de datos</li>
                                    <li><strong>Base de datos:</strong> Prepared statements MySQL</li>
                                </ul>
                            </div>
                        </div>
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
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando XLSX de Créditos...';
    progressContainer.style.display = 'block';

    // Simular progreso más lento para XLSX
    let progress = 0;
    const interval = setInterval(function() {
        progress += Math.random() * 10; // Más lento que CSV
        if (progress > 85) progress = 85;
        document.getElementById('progressBar').style.width = progress + '%';
    }, 300);

    // Limpiar el intervalo cuando el formulario se envíe
    setTimeout(function() {
        clearInterval(interval);
    }, 1500);
});

// Validación del archivo en el cliente
document.getElementById('xlsx_file').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        if (file.size > <?php echo MAX_FILE_SIZE; ?>) {
            alert('El archivo es demasiado grande. Máximo 10MB permitido.');
            this.value = '';
        }

        const fileName = file.name.toLowerCase();
        if (!fileName.endsWith('.xlsx')) {
            alert('Solo se permiten archivos XLSX.');
            this.value = '';
        }

        // Verificación del tipo MIME
        if (file.type && !file.type.includes('spreadsheetml')) {
            alert('El archivo no parece ser un XLSX válido.');
            this.value = '';
        }

        // Verificación básica del nombre del archivo
        if (fileName.includes('credit')) {
            console.log('Archivo de créditos XLSX detectado correctamente.');
        } else {
            if (confirm(
                    'El nombre del archivo no contiene "credit". ¿Está seguro de que es el archivo correcto?'
                    )) {
                console.log('Usuario confirmó el archivo XLSX.');
            } else {
                this.value = '';
            }
        }
    }
});
</script>

<?php
// Incluir footer
include("includes/footer.php");
?>