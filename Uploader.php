<?php
// Incluir archivo de configuración de base de datos
include("db.php");
// Incluir autenticación Firebase
include("includes/auth.php");

// Configuración de seguridad
define('MAX_FILE_SIZE', 3 * 1024 * 1024); // 3MB
define('ALLOWED_EXTENSIONS', ['csv']);
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

// Función para convertir fecha
function convertDate($dateString)
{
    if (empty($dateString)) return null;

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
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $message = 'Error al subir el archivo.';
            $messageType = 'danger';
        } else {
            $file = $_FILES['csv_file'];

            // Validar tamaño
            if ($file['size'] > MAX_FILE_SIZE) {
                $message = 'El archivo es demasiado grande. Máximo 3MB.';
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

                        // Preparar la consulta de inserción
                        $insertQuery = "INSERT INTO sales (
                            DataOrigin, InvoiceNumber, PrebookNumber, PrebookCreatedOn, InvoiceDate,
                            PONumber, SONumber, FarmShipDate, AWB, TruckDate, TruckMonth,
                            FarmName, Brand, FarmCode, Origin, ShipVia, Location, Customer,
                            PriceList, City, State, ZipCode, CustomerType, Salesperson,
                            InvoiceBoxes, PrebookBoxes, POBoxes, UnitsBox, BoxType, TotalUnits,
                            UnitType, BunchesBox, UnitsBunch, QtyFullBoxes, Product, Category,
                            Variety, Color, Grade, Country, SalesType, SalesUnitPrice,
                            TotalPrice, UnitCost, TotalCost, HandlingCostUnit, AWBFreightCostUnit,
                            DutiesCostUnit, LandedCostUnit, GPM, TotalHandling, TotalAWBFreight,
                            TotalLandedCost, OrderType, CustomerCode, ProductLegacyCode,
                            ProductVBN, CarrierCode, SalespersonCode, Cubes, Aging, svfactores
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

                        $stmt = $conn2->prepare($insertQuery);

                        if (!$stmt) {
                            throw new Exception('Error preparando la consulta: ' . $conn->error);
                        }

                        // Leer el archivo CSV línea por línea
                        $handle = fopen($file['tmp_name'], 'r');
                        if (!$handle) {
                            throw new Exception('No se pudo abrir el archivo CSV.');
                        }

                        $lineNumber = 0;
                        $insertedRows = 0;
                        $errors = [];

                        while (($data = fgetcsv($handle, 1000, ',', '"')) !== FALSE) {
                            $lineNumber++;

                            // Saltar la primera línea (encabezados)
                            if ($lineNumber === 1) {
                                continue;
                            }

                            // Verificar que tenemos el número correcto de columnas
                            if (count($data) !== 62) {
                                $errors[] = "Línea $lineNumber: Número incorrecto de columnas (" . count($data) . " encontradas, 62 esperadas)";
                                continue;
                            }

                            // Sanitizar y procesar los datos
                            $cleanData = array_map('sanitizeData', $data);

                            // Convertir fechas
                            $cleanData[3] = convertDate($cleanData[3]); // PrebookCreatedOn
                            $cleanData[4] = convertDate($cleanData[4]); // InvoiceDate
                            $cleanData[7] = convertDate($cleanData[7]); // FarmShipDate
                            $cleanData[9] = convertDate($cleanData[9]); // TruckDate

                            // Convertir valores numéricos y manejar valores vacíos
                            for ($i = 0; $i < count($cleanData); $i++) {
                                if ($cleanData[$i] === '') {
                                    $cleanData[$i] = null;
                                }
                            }

                            // Bind parameters (todos como string inicialmente, MySQL hará las conversiones)
                            $stmt->bind_param(str_repeat('s', 62), ...array_values($cleanData));

                            if ($stmt->execute()) {
                                $insertedRows++;
                            } else {
                                $errors[] = "Línea $lineNumber: Error al insertar - " . $stmt->error;
                            }
                        }

                        fclose($handle);
                        $stmt->close();
                        // No cerramos $conn2 ya que viene de db.php

                        if ($insertedRows > 0) {
                            $message = "Proceso completado exitosamente. $insertedRows filas insertadas.";
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
                    <div class="card-header bg-primary text-white">
                        <h3 class="card-title mb-0">
                            <i class="fas fa-upload me-2"></i>
                            Subir Archivo CSV - Puawai Sales Data
                        </h3>
                    </div>
                    <div class="card-body">
                        <!-- Instrucciones -->
                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Instrucciones:</h6>
                            <ul class="mb-0">
                                <li>Selecciona un archivo CSV con los datos de ventas de Puawai</li>
                                <li>El archivo debe tener exactamente 62 columnas</li>
                                <li>La primera fila debe contener los encabezados</li>
                                <li>Tamaño máximo: 3MB</li>
                                <li>Formatos de fecha soportados: m/d/Y o n/j/Y (con o sin hora)</li>
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
                                    <i class="fas fa-file-csv me-2"></i>Seleccionar archivo CSV:
                                </label>
                                <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv"
                                    required>
                                <div class="form-text">
                                    <i class="fas fa-exclamation-triangle text-warning me-1"></i>
                                    Archivo CSV requerido (máximo 3MB)
                                </div>
                            </div>

                            <!-- Barra de progreso -->
                            <div class="progress mb-3" id="progressContainer" style="display: none;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" id="progressBar"
                                    role="progressbar" style="width: 0%"></div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                    <i class="fas fa-cloud-upload-alt me-2"></i>
                                    Subir y Procesar CSV
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('uploadForm').addEventListener('submit', function() {
        const submitBtn = document.getElementById('submitBtn');
        const progressContainer = document.getElementById('progressContainer');

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
        progressContainer.style.display = 'block';

        // Simular progreso (ya que no podemos obtener el progreso real del servidor)
        let progress = 0;
        const interval = setInterval(function() {
            progress += Math.random() * 15;
            if (progress > 90) progress = 90;
            document.getElementById('progressBar').style.width = progress + '%';
        }, 200);

        // Limpiar el intervalo cuando el formulario se envíe
        setTimeout(function() {
            clearInterval(interval);
        }, 1000);
    });

    // Validación del archivo en el cliente
    document.getElementById('csv_file').addEventListener('change', function() {
        const file = this.files[0];
        if (file) {
            if (file.size > <?php echo MAX_FILE_SIZE; ?>) {
                alert('El archivo es demasiado grande. Máximo 3MB permitido.');
                this.value = '';
            }
            if (!file.name.toLowerCase().endsWith('.csv')) {
                alert('Solo se permiten archivos CSV.');
                this.value = '';
            }
        }
    });
</script>

<?php
// Incluir footer
include("includes/footer.php");
?>