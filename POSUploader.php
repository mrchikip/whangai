<?php
// Incluir archivo de configuración de base de datos
include("db.php");
// Incluir autenticación Firebase
include("includes/auth.php");

// Configuración de seguridad
define('MAX_FILE_SIZE', 16 * 1024 * 1024); // 16MB
define('ALLOWED_EXTENSIONS', ['csv']);
define('UPLOAD_DIR', 'uploads/');
define('EXPECTED_COLUMNS', 30); // Archivo POS: 30 columnas

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
    $s = trim((string)$data);
    $decoded = html_entity_decode($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($decoded !== $s) {
        $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return $decoded;
}

// Función para convertir fecha
function convertDate($dateString)
{
    if (empty($dateString)) return null;

    $dateString = trim($dateString);

    $formats = [
        'm/d/Y H:i:s',
        'n/j/Y H:i:s',
        'm/d/Y',
        'n/j/Y',
        'm/d/Y H:i',
        'n/j/Y H:i',
        'Y-m-d',
        'Y-m-d H:i:s',
    ];

    foreach ($formats as $format) {
        $date = DateTime::createFromFormat($format, $dateString);
        if ($date !== false) {
            return $date->format('Y-m-d');
        }
    }

    return null;
}

// Limpiar números monetarios y decimales
function normalizeNumeric($value)
{
    if ($value === null) return null;

    $value = trim((string)$value);
    if ($value === '') return null;

    // Quitar símbolos monetarios, espacios y separadores de miles con coma
    $value = str_replace(['$', ',', "\t", "\n", "\r", ' '], '', $value);

    return $value === '' ? null : $value;
}

// Convertir posibles booleanos a 1/0/null
function normalizeBoolean($value)
{
    $value = trim((string)$value);
    if ($value === '') return null;

    $upper = mb_strtoupper($value, 'UTF-8');
    if (in_array($upper, ['1', 'YES', 'Y', 'TRUE', 'SI', 'SÍ'])) {
        return '1';
    }
    if (in_array($upper, ['0', 'NO', 'N', 'FALSE'])) {
        return '0';
    }

    return null;
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
                    try {
                        if (!$conn2) {
                            throw new Exception('Error de conexión a la base de datos');
                        }

                        mysqli_set_charset($conn2, 'utf8');

                        // Preparar la consulta de inserción (sin id_pos, que es AUTO_INCREMENT)
                        $insertQuery = "INSERT INTO pos (
                            purchase_order, location, farm_name, farm_ship_date,
                            ship_via, origin_code, po_status, grower_status,
                            prebook_code, standing_order_code, is_double,
                            customer_name, vbn_code, product_name, product_code,
                            boxes, box_type, unit_type, bunches_per_box, units_per_bunch,
                            fbe, unit_cost, units, total_units, total_cost,
                            comments, prebook_created_date, po_created_date,
                            vendor_invoice_status, mark_code
                        ) VALUES (" . str_repeat('?,', EXPECTED_COLUMNS - 1) . "?)";

                        $stmt = $conn2->prepare($insertQuery);

                        if (!$stmt) {
                            throw new Exception('Error preparando la consulta: ' . $conn2->error);
                        }

                        $handle = fopen($file['tmp_name'], 'r');
                        if (!$handle) {
                            throw new Exception('No se pudo abrir el archivo CSV.');
                        }

                        $lineNumber = 0;
                        $insertedRows = 0;
                        $errors = [];
                        $headersFound = false;

                        while (($data = fgetcsv($handle, 0, ',', '"')) !== false) {
                            $lineNumber++;

                            // Saltar las primeras 5 filas de metadata
                            if ($lineNumber <= 5) {
                                error_log("CSV POS: Saltando fila $lineNumber (metadata): " . implode(',', array_slice($data, 0, 3)));
                                continue;
                            }

                            // Fila 6: encabezados
                            if ($lineNumber === 6) {
                                $headersFound = true;
                                error_log("CSV POS: Encabezados encontrados en fila 6: " . implode(',', array_slice($data, 0, 5)));
                                error_log("CSV POS: Total de columnas en encabezados: " . count($data));
                                continue;
                            }

                            // Procesar desde la fila 7
                            if (!$headersFound) {
                                $errors[] = "Línea $lineNumber: No se encontraron encabezados válidos";
                                continue;
                            }

                            if (count($data) !== EXPECTED_COLUMNS) {
                                $errors[] = "Línea $lineNumber: Número incorrecto de columnas (" . count($data) . " encontradas, " . EXPECTED_COLUMNS . " esperadas)";
                                continue;
                            }

                            $hasData = false;
                            foreach ($data as $cell) {
                                if (!empty(trim((string)$cell))) {
                                    $hasData = true;
                                    break;
                                }
                            }

                            if (!$hasData) {
                                continue;
                            }

                            $cleanData = array_map('sanitizeData', $data);

                            // Convertir fechas
                            $cleanData[3] = convertDate($cleanData[3]);   // farm_ship_date
                            $cleanData[26] = convertDate($cleanData[26]); // prebook_created_date
                            $cleanData[27] = convertDate($cleanData[27]); // po_created_date

                            // Normalizar booleano
                            $cleanData[10] = normalizeBoolean($cleanData[10]); // is_double

                            // Normalizar numéricos
                            $numericIndexes = [15, 18, 19, 20, 21, 22, 23, 24];
                            foreach ($numericIndexes as $index) {
                                $cleanData[$index] = normalizeNumeric($cleanData[$index]);
                            }

                            // Convertir vacíos a null
                            for ($i = 0; $i < count($cleanData); $i++) {
                                if ($cleanData[$i] === '') {
                                    $cleanData[$i] = null;
                                }
                            }

                            // purchase_order es requerido
                            if (empty($cleanData[0])) {
                                $errors[] = "Línea $lineNumber: Purchase Order es requerido";
                                continue;
                            }

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
                            $message = "Proceso completado exitosamente. $insertedRows filas POS insertadas.";
                            $messageType = 'success';

                            if (!empty($errors)) {
                                $message .= " Se encontraron " . count($errors) . " errores.";
                            }
                        } else {
                            $message = 'No se pudieron insertar filas. Revisa el formato del archivo.';
                            $messageType = 'danger';
                        }

                        if (!empty($errors)) {
                            $message .= '<br><br><strong>Errores encontrados:</strong><br>';
                            $message .= implode('<br>', array_slice($errors, 0, 10));
                            if (count($errors) > 10) {
                                $message .= '<br>... y ' . (count($errors) - 10) . ' errores más.';
                            }
                        }
                    } catch (Exception $e) {
                        $message = 'Error: ' . $e->getMessage();
                        $messageType = 'danger';
                        error_log('CSV POS Error: ' . $e->getMessage());
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
                            Subir Archivo CSV - Puawai POS Data
                        </h3>
                    </div>
                    <div class="card-body">

                        <div class="alert alert-info">
                            <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Instrucciones:</h6>
                            <ul class="mb-0">
                                <li>Selecciona un archivo CSV con los datos POS de Puawai</li>
                                <li><strong>Estructura esperada:</strong> 5 filas de metadata + 1 fila de encabezados + datos</li>
                                <li>El archivo debe tener exactamente <strong>30 columnas</strong></li>
                                <li><strong>Encabezados:</strong> Deben estar en la fila 6</li>
                                <li><strong>Datos:</strong> Se procesan desde la fila 7 en adelante</li>
                                <li>Tamaño máximo: 16MB</li>
                                <li>Formatos de fecha soportados: m/d/Y, n/j/Y, Y-m-d (con o sin hora)</li>
                            </ul>
                        </div>

                        <?php if ($message): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                            <div class="mb-3">
                                <label for="csv_file" class="form-label">
                                    <i class="fas fa-file-csv me-2"></i>Seleccionar archivo CSV POS:
                                </label>
                                <input type="file" name="csv_file" id="csv_file" class="form-control" accept=".csv" required>
                                <div class="form-text">
                                    <i class="fas fa-check-circle text-success me-1"></i>
                                    Archivo CSV POS para 30 columnas (máximo 16MB)
                                </div>
                            </div>

                            <div class="progress mb-3" id="progressContainer" style="display: none;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" id="progressBar" role="progressbar" style="width: 0%"></div>
                            </div>

                            <div class="mt-auto">
                                <div class="d-grid gap-2">
                                    <button type="submit" class="btn btn-success btn-lg" id="submitBtn">
                                        <i class="fas fa-cloud-upload-alt me-2"></i>
                                        Subir y Procesar POS CSV
                                    </button>

                                    <button type="button" class="btn btn-success btn-lg w-100" onclick="window.location.href='updater.php'">
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
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando CSV POS...';
    progressContainer.style.display = 'block';

    let progress = 0;
    const interval = setInterval(function() {
        progress += Math.random() * 20;
        if (progress > 90) progress = 90;
        document.getElementById('progressBar').style.width = progress + '%';
    }, 150);

    setTimeout(function() {
        clearInterval(interval);
    }, 800);
});

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

        console.log('✅ Archivo CSV POS seleccionado:', file.name);
        console.log('📊 Tamaño:', (file.size / 1024 / 1024).toFixed(2), 'MB');

        const formText = document.querySelector('.form-text');
        formText.innerHTML = '<i class="fas fa-check-circle text-success me-1"></i>Archivo CSV POS válido - 30 columnas';
        formText.className = 'form-text text-success';
    }
});
</script>

<?php
// Incluir footer
include("includes/footer.php");
?>
