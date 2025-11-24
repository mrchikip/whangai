<?php
// Incluir archivo de configuración de base de datos
include("db.php");
// Incluir autenticación Firebase
include("includes/auth.php");

// Configuración de seguridad
define('MAX_FILE_SIZE', 16 * 1024 * 1024); // 16MB
define('ALLOWED_EXTENSIONS', ['csv']);
define('UPLOAD_DIR', 'uploads/');
define('EXPECTED_COLUMNS', 60); // Corregido: el archivo tiene 60 columnas, no 62

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
    // Corregido: no escapar a HTML al guardar en BD; decodificar entidades HTML del CSV
    $s = trim($data);
    // Primer pase
    $decoded = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    // Segundo pase para casos doblemente escapados (p. ej., &amp;apos;)
    if ($decoded !== $s) {
        $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return $decoded;
}

// Función para convertir fecha
function convertDate($dateString)
{
    if (empty($dateString)) return null;

    // Si contiene fecha y hora (formato como "05/12/2025 15:31:36")
    $date = DateTime::createFromFormat('m/d/Y H:i:s', $dateString);
    if ($date !== false) {
        return $date->format('Y-m-d');
    }

    // Formatos de solo fecha
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

    return $date !== false ? $date->format('Y-m-d') : null;
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
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $message = 'Error al subir el archivo.';
            $messageType = 'danger';
        } else {
            $file = $_FILES['csv_file'];

            // Validar tamaño
            if ($file['size'] > MAX_FILE_SIZE) {
                $message = 'El archivo es demasiado grande. Máximo 16MB.';
                $messageType = 'danger';
            } else {
                // Validar extensión
                $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($fileExtension, ALLOWED_EXTENSIONS)) {
                    $message = 'Solo se permiten archivos CSV.';
                    $messageType = 'danger';
                } else {
                    // Procesar el archivo CSV
                    try {
                        // Usar la conexión existente de db.php
                        if (!$conn2) {
                            throw new Exception('Error de conexión a la base de datos');
                        }

                        mysqli_set_charset($conn2, 'utf8');

                        // Preparar la consulta de inserción (60 columnas - SIN IdSaleTrx que es AUTO_INCREMENT)
                        $insertQuery = "INSERT INTO sales (
                            InvoiceNumber, PrebookNumber, PrebookCreatedOn, InvoiceDate,
                            PONumber, SONumber, FarmShipDate, AWB, TruckDate, TruckMonth,
                            FarmName, Brand, FarmCode, Origin, ShipVia, Location, Customer,
                            PriceList, City, State, ZipCode, CustomerType, Salesperson,
                            InvoiceBoxes, PrebookBoxes, POBoxes, UnitsBox, BoxType, TotalUnits,
                            UnitType, BunchesBox, UnitsBunch, QtyFullBoxes, Product, Category,
                            Variety, Color, Grade, Country, SalesType, SalesUnitPrice,
                            TotalPrice, UnitCost, TotalCost, HandlingCostUnit, AWBFreightCostUnit,
                            DutiesCostUnit, LandedCostUnit, GPM, TotalHandling, TotalAWBFreight,
                            TotalLandedCost, OrderType, CustomerCode, ProductLegacyCode,
                            ProductVBN, CarrierCode, SalespersonCode, Cubes, Aging
                        ) VALUES (" . str_repeat('?,', EXPECTED_COLUMNS - 1) . "?)";

                        $stmt = $conn2->prepare($insertQuery);

                        if (!$stmt) {
                            throw new Exception('Error preparando la consulta: ' . $conn2->error);
                        }

                        // Leer el archivo CSV línea por línea
                        $handle = fopen($file['tmp_name'], 'r');
                        if (!$handle) {
                            throw new Exception('No se pudo abrir el archivo CSV.');
                        }

                        $lineNumber = 0;
                        $insertedRows = 0;
                        $errors = [];
                        $headersFound = false;

                        while (($data = fgetcsv($handle, 1000, ',', '"')) !== FALSE) {
                            $lineNumber++;

                            // SALTAR LAS PRIMERAS 5 FILAS DE METADATA
                            if ($lineNumber <= 5) {
                                error_log("CSV Sales: Saltando fila $lineNumber (metadata): " . implode(',', array_slice($data, 0, 3)));
                                continue;
                            }

                            // FILA 6 SON LOS ENCABEZADOS REALES
                            if ($lineNumber === 6) {
                                $headersFound = true;
                                error_log("CSV Sales: Encabezados encontrados en fila 6: " . implode(',', array_slice($data, 0, 5)));
                                error_log("CSV Sales: Total de columnas en encabezados: " . count($data));
                                continue;
                            }

                            // PROCESAR DATOS DESDE LA FILA 7
                            if (!$headersFound) {
                                $errors[] = "Línea $lineNumber: No se encontraron encabezados válidos";
                                continue;
                            }

                            // Verificar que tenemos el número correcto de columnas
                            if (count($data) !== EXPECTED_COLUMNS) {
                                $errors[] = "Línea $lineNumber: Número incorrecto de columnas (" . count($data) . " encontradas, " . EXPECTED_COLUMNS . " esperadas)";
                                continue;
                            }

                            // Verificar que no sea una fila completamente vacía
                            $hasData = false;
                            foreach ($data as $cell) {
                                if (!empty(trim($cell))) {
                                    $hasData = true;
                                    break;
                                }
                            }

                            if (!$hasData) {
                                continue; // Saltar filas completamente vacías
                            }

                            // Sanitizar y procesar los datos (usar todas las 60 columnas)
                            $cleanData = array_map('sanitizeData', $data);

                            // Convertir fechas específicas (índices basados en el orden real del CSV)
                            $cleanData[2] = convertDate($cleanData[2]); // PrebookCreatedOn (índice 2)
                            $cleanData[3] = convertDate($cleanData[3]); // InvoiceDate (índice 3)
                            $cleanData[6] = convertDate($cleanData[6]); // FarmShipDate (índice 6)
                            $cleanData[8] = convertDate($cleanData[8]); // TruckDate (índice 8)

                            // Convertir valores numéricos y manejar valores vacíos
                            for ($i = 0; $i < count($cleanData); $i++) {
                                if ($cleanData[$i] === '') {
                                    $cleanData[$i] = null;
                                }
                            }

                            // Validar que al menos tenemos InvoiceNumber (primera columna)
                            if (empty($cleanData[0])) {
                                $errors[] = "Línea $lineNumber: InvoiceNumber es requerido";
                                continue;
                            }

                            // Bind parameters - usar EXPECTED_COLUMNS parámetros tipo 's'
                            $stmt->bind_param(str_repeat('s', EXPECTED_COLUMNS), ...array_values($cleanData));

                            if ($stmt->execute()) {
                                $insertedRows++;
                            } else {
                                $errors[] = "Línea $lineNumber: Error al insertar - " . $stmt->error;
                            }
                        }

                        fclose($handle);
                        $stmt->close();

                        if ($insertedRows > 0) {
                            $message = "Proceso completado exitosamente. $insertedRows filas de ventas insertadas.";
                            $messageType = 'success';

                            if (!empty($errors)) {
                                $message .= " Se encontraron " . count($errors) . " errores.";
                            }
                        } else {
                            $message = "No se pudieron insertar filas. Revisa el formato del archivo.";
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
                        error_log("CSV Sales Error: " . $e->getMessage());
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
                            <i class="fas fa-upload me-2"></i>
                            Subir Archivo CSV - Puawai Sales Data
                        </h3>
                    </div>
                    <div class="card-body">

                        <!-- Instrucciones actualizadas -->
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Instrucciones:</h6>
                            <ul class="mb-0">
                                <li>Selecciona un archivo CSV con los datos de ventas de Puawai</li>
                                <li><strong>Estructura esperada:</strong> 5 filas de metadata + 1 fila de encabezados +
                                    datos</li>
                                <li>El archivo debe tener exactamente <strong>60 columnas</strong></li>
                                <li><strong>Encabezados:</strong> Deben estar en la fila 6</li>
                                <li><strong>Datos:</strong> Se procesan desde la fila 7 en adelante</li>
                                <li>Tamaño máximo: 16MB</li>
                                <li>Formatos de fecha soportados: m/d/Y, n/j/Y (con o sin hora)</li>
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
                                <label for="csv_file" class="form-label">
                                    <i class="fas fa-file-csv me-2"></i>Seleccionar archivo CSV de Ventas:
                                </label>
                                <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv"
                                    required>
                                <div class="form-text">
                                    <i class="fas fa-check-circle text-success me-1"></i>
                                    Archivo CSV corregido para 60 columnas (máximo 16MB)
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
                                        <i class="fas fa-cloud-upload-alt me-2"></i>
                                        Subir y Procesar Ventas CSV
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

document.getElementById('uploadForm').addEventListener('submit', function() {
    const submitBtn = document.getElementById('submitBtn');
    const progressContainer = document.getElementById('progressContainer');

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando CSV Corregido...';
    progressContainer.style.display = 'block';

    // Progreso más rápido para CSV
    let progress = 0;
    const interval = setInterval(function() {
        progress += Math.random() * 20;
        if (progress > 90) progress = 90;
        document.getElementById('progressBar').style.width = progress + '%';
    }, 150);

    // Limpiar el intervalo cuando el formulario se envíe
    setTimeout(function() {
        clearInterval(interval);
    }, 800);
});

// Validación del archivo en el cliente
document.getElementById('csv_file').addEventListener('change', function() {
    const file = this.files[0];
    if (file) {
        if (file.size > <?php echo MAX_FILE_SIZE; ?>) {
            alert('El archivo es demasiado grande. Máximo 16MB permitido.');
            this.value = '';
            return;
        }
        if (!file.name.toLowerCase().endsWith('.csv')) {
            alert('Solo se permiten archivos CSV.');
            this.value = '';
            return;
        }

        console.log('✅ Archivo CSV seleccionado:', file.name);
        console.log('📊 Tamaño:', (file.size / 1024 / 1024).toFixed(2), 'MB');

        // Mostrar información positiva
        const formText = document.querySelector('.form-text');
        formText.innerHTML =
            '<i class="fas fa-check-circle text-success me-1"></i>Archivo CSV válido - Versión corregida para 60 columnas';
        formText.className = 'form-text text-success';
    }
});
</script>

<?php
// Incluir footer
include("includes/footer.php");
?>