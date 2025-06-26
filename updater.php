<?php
// Incluye la conexión a la base de datos
include("db.php");
// Incluye la protección de autenticación de usuario
include("includes/auth.php");

// Configuración de seguridad
define('MAX_FILE_SIZE', 4 * 1024 * 1024); // 4MB
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

// Variables para manejo de mensajes y fecha
$message = '';
$messageType = '';
$ddate = '';
$selectedDate = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $message = 'Token de seguridad inválido.';
        $messageType = 'danger';
    } else {
        // Obtener y validar la fecha
        $selectedDate = $_POST['datadate'] ?? '';

        if (empty($selectedDate)) {
            $message = 'Debe seleccionar una fecha.';
            $messageType = 'danger';
        } else {
            // Convertir la fecha de YYYY-MM-DD a YYYY/MM/DD
            $ddate = str_replace('-', '/', $selectedDate);

            try {
                // Verificar conexión a la base de datos
                if (!$conn2) {
                    throw new Exception('Error de conexión a la base de datos');
                }

                // Verificar qué botón fue presionado
                if (isset($_POST['prepare_sales'])) {
                    // Ejecutar DELETE para sales
                    $deleteQuery = "DELETE FROM `sales` WHERE `InvoiceDate` >= ?";
                    $stmt = $conn2->prepare($deleteQuery);

                    if (!$stmt) {
                        throw new Exception('Error preparando la consulta de sales: ' . $conn2->error);
                    }

                    $stmt->bind_param("s", $ddate);

                    if ($stmt->execute()) {
                        $affectedRows = $stmt->affected_rows;
                        $message = "Preparación de Sales completada exitosamente. $affectedRows registros eliminados desde la fecha $ddate.";
                        $messageType = 'success';
                    } else {
                        throw new Exception('Error ejecutando eliminación de sales: ' . $stmt->error);
                    }

                    $stmt->close();
                } elseif (isset($_POST['prepare_credits'])) {
                    // Ejecutar DELETE para credits
                    $deleteQuery = "DELETE FROM `credits` WHERE `CreditDate` >= ?";
                    $stmt = $conn2->prepare($deleteQuery);

                    if (!$stmt) {
                        throw new Exception('Error preparando la consulta de credits: ' . $conn2->error);
                    }

                    $stmt->bind_param("s", $ddate);

                    if ($stmt->execute()) {
                        $affectedRows = $stmt->affected_rows;
                        $message = "Preparación de Credits completada exitosamente. $affectedRows registros eliminados desde la fecha $ddate.";
                        $messageType = 'success';
                    } else {
                        throw new Exception('Error ejecutando eliminación de credits: ' . $stmt->error);
                    }

                    $stmt->close();
                }
            } catch (Exception $e) {
                error_log("Error en preparación de datos: " . $e->getMessage());
                $message = 'Error: ' . $e->getMessage();
                $messageType = 'danger';
            }
        }
    }
}

// Incluye la cabecera HTML y recursos
include("includes/header.php");
?>

<!-- Elemento para mostrar mensaje de carga durante verificación de autenticación -->
<div id="loading-message" style="display: none;">
    <div class="auth-loading-content">
        <h3>Verificando autenticación...</h3>
        <p>Por favor espere...</p>
    </div>
</div>

<!-- Contenido principal protegido -->
<div id="main-content" style="display: none;">
    <div class="container p-4">
        <div class="row">
            <div class="col-md-4">
                <?php
                // Muestra mensaje de alerta si existe en sesión
                if (isset($_SESSION['message'])) { ?>
                <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
                    <?= $_SESSION['message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                    </button>
                </div>
                <?php
                    unset($_SESSION['message'], $_SESSION['message_type']);
                } ?>

                <div class="container mt-4">
                    <div class="row justify-content-center align-items-stretch">
                        <div class="col-12">
                            <div class="card shadow" style="width: 80vw; margin: 0 auto;">
                                <div class="card-body">
                                    <div class="row justify-content-center align-items-stretch">
                                        <!-- Primera tarjeta -->
                                        <div class="col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <div class="card h-100">
                                                <div class="card-body d-flex flex-column text-center">
                                                    <!-- Icono -->
                                                    <div class="mb-3">
                                                        <i class="fa-solid fa-calendar-days"
                                                            style="font-size: 3rem; color: #198754;"></i>
                                                    </div>

                                                    <!-- Título centrado -->
                                                    <h5 class="card-title text-center mb-3">Fecha de Actualización</h5>

                                                    <!-- Descripción centrada -->
                                                    <p class="card-text text-center flex-grow-1 mb-4">
                                                        Selección de la fecha de actualización para la carga de
                                                        información y vínculos para limpiar y preparar las tablas de
                                                        ventas y créditos
                                                    </p>

                                                    <!-- Formulario con selector de fecha y botones -->
                                                    <form method="POST" id="preparationForm">
                                                        <input type="hidden" name="csrf_token"
                                                            value="<?php echo generateCSRFToken(); ?>">

                                                        <!-- Selector de fecha -->
                                                        <div class="mb-3">
                                                            <label for="datadate" class="form-label">
                                                                <i class="fas fa-calendar-alt me-2"></i>Escoja La Fecha
                                                                De Los Datos
                                                            </label>
                                                            <input class="form-control" type="date" id="datadate"
                                                                name="datadate"
                                                                value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>"
                                                                required>
                                                            <div class="form-text">
                                                                <i class="fas fa-info-circle text-info me-1"></i>
                                                                Se eliminarán todos los registros desde esta fecha en
                                                                adelante
                                                            </div>
                                                        </div>

                                                        <!-- Mensajes de resultado -->
                                                        <?php if ($message): ?>
                                                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show"
                                                            role="alert">
                                                            <?php echo $message; ?>
                                                            <button type="button" class="btn-close"
                                                                data-bs-dismiss="alert"></button>
                                                        </div>
                                                        <?php endif; ?>

                                                        <!-- Botones de acción -->
                                                        <div class="mt-auto">
                                                            <div class="d-grid gap-2">
                                                                <button type="submit" name="prepare_sales"
                                                                    class="btn btn-success btn-lg w-100"
                                                                    onclick="return confirmAction('Sales', document.getElementById('datadate').value)">
                                                                    <i class="fa-solid fa-sack-dollar me-2"></i>
                                                                    Prepare Sales
                                                                </button>

                                                                <button type="submit" name="prepare_credits"
                                                                    class="btn btn-success btn-lg w-100"
                                                                    onclick="return confirmAction('Credits', document.getElementById('datadate').value)">
                                                                    <i class="fa-solid fa-hand-holding-dollar me-2"></i>
                                                                    Prepare Credits
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Segunda tarjeta -->
                                        <div class="col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <div class="card h-100">
                                                <div class="card-body d-flex flex-column text-center">
                                                    <!-- Icono -->
                                                    <div class="mb-3">
                                                        <i class="fa-solid fa-dolly"
                                                            style="font-size: 3rem; color: #198754;"></i>
                                                    </div>

                                                    <!-- Título centrado -->
                                                    <h5 class="card-title text-center mb-3">Requerimientos y Solicitudes
                                                    </h5>

                                                    <!-- Descripción centrada -->
                                                    <p class="card-text text-center flex-grow-1 mb-4">
                                                        Acceso al módulo para solicitudes de servicio, cambios, o
                                                        requerimientos
                                                    </p>

                                                    <!-- Botón centrado en la parte inferior -->
                                                    <div class="mt-auto">
                                                        <button type="button" class="btn btn-success btn-lg w-100"
                                                            onclick="window.location.href='#'">
                                                            <i class="fa-solid fa-dolly me-2"></i>
                                                            Request
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Tercera tarjeta -->
                                        <div class="col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <div class="card h-100">
                                                <div class="card-body d-flex flex-column text-center">
                                                    <!-- Icono -->
                                                    <div class="mb-3">
                                                        <i class="fa-solid fa-dolly"
                                                            style="font-size: 3rem; color: #198754;"></i>
                                                    </div>

                                                    <!-- Título centrado -->
                                                    <h5 class="card-title text-center mb-3">Requerimientos y Solicitudes
                                                    </h5>

                                                    <!-- Descripción centrada -->
                                                    <p class="card-text text-center flex-grow-1 mb-4">
                                                        Acceso al módulo para solicitudes de servicio, cambios, o
                                                        requerimientos
                                                    </p>

                                                    <!-- Botón centrado en la parte inferior -->
                                                    <div class="mt-auto">
                                                        <button type="button" class="btn btn-success btn-lg w-100"
                                                            onclick="window.location.href='#'">
                                                            <i class="fa-solid fa-dolly me-2"></i>
                                                            Request
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Cuarta tarjeta -->
                                        <div class="col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <div class="card h-100">
                                                <div class="card-body d-flex flex-column text-center">
                                                    <!-- Icono -->
                                                    <div class="mb-3">
                                                        <i class="fa-solid fa-dolly"
                                                            style="font-size: 3rem; color: #198754;"></i>
                                                    </div>

                                                    <!-- Título centrado -->
                                                    <h5 class="card-title text-center mb-3">Requerimientos y Solicitudes
                                                    </h5>

                                                    <!-- Descripción centrada -->
                                                    <p class="card-text text-center flex-grow-1 mb-4">
                                                        Acceso al módulo para solicitudes de servicio, cambios, o
                                                        requerimientos
                                                    </p>

                                                    <!-- Botón centrado en la parte inferior -->
                                                    <div class="mt-auto">
                                                        <button type="button" class="btn btn-success btn-lg w-100"
                                                            onclick="window.location.href='#'">
                                                            <i class="fa-solid fa-dolly me-2"></i>
                                                            Request
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Quinta tarjeta -->
                                        <div class="col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <div class="card h-100">
                                                <div class="card-body d-flex flex-column text-center">
                                                    <!-- Icono -->
                                                    <div class="mb-3">
                                                        <i class="fa-solid fa-dolly"
                                                            style="font-size: 3rem; color: #198754;"></i>
                                                    </div>

                                                    <!-- Título centrado -->
                                                    <h5 class="card-title text-center mb-3">Requerimientos y Solicitudes
                                                    </h5>

                                                    <!-- Descripción centrada -->
                                                    <p class="card-text text-center flex-grow-1 mb-4">
                                                        Acceso al módulo para solicitudes de servicio, cambios, o
                                                        requerimientos
                                                    </p>

                                                    <!-- Botón centrado en la parte inferior -->
                                                    <div class="mt-auto">
                                                        <button type="button" class="btn btn-success btn-lg w-100"
                                                            onclick="window.location.href='#'">
                                                            <i class="fa-solid fa-dolly me-2"></i>
                                                            Request
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Sexta tarjeta -->
                                        <div class="col-lg-6 col-md-6 col-sm-12 mb-3">
                                            <div class="card h-100">
                                                <div class="card-body d-flex flex-column text-center">
                                                    <!-- Icono -->
                                                    <div class="mb-3">
                                                        <i class="fa-solid fa-dolly"
                                                            style="font-size: 3rem; color: #198754;"></i>
                                                    </div>

                                                    <!-- Título centrado -->
                                                    <h5 class="card-title text-center mb-3">Requerimientos y Solicitudes
                                                    </h5>

                                                    <!-- Descripción centrada -->
                                                    <p class="card-text text-center flex-grow-1 mb-4">
                                                        Acceso al módulo para solicitudes de servicio, cambios, o
                                                        requerimientos
                                                    </p>

                                                    <!-- Botón centrado en la parte inferior -->
                                                    <div class="mt-auto">
                                                        <button type="button" class="btn btn-success btn-lg w-100"
                                                            onclick="window.location.href='#'">
                                                            <i class="fa-solid fa-dolly me-2"></i>
                                                            Request
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Función de confirmación antes de ejecutar la eliminación
function confirmAction(action, selectedDate) {
    if (!selectedDate) {
        alert('Por favor seleccione una fecha antes de continuar.');
        return false;
    }

    const formattedDate = new Date(selectedDate).toLocaleDateString('es-ES');
    const message =
        `¿Está seguro de que desea eliminar todos los registros de ${action} desde el ${formattedDate} en adelante?\n\nEsta acción no se puede deshacer.`;

    return confirm(message);
}

// Validación del formulario
document.getElementById('preparationForm').addEventListener('submit', function(e) {
    const dateInput = document.getElementById('datadate');

    if (!dateInput.value) {
        e.preventDefault();
        alert('Debe seleccionar una fecha.');
        dateInput.focus();
        return false;
    }

    // Verificar que la fecha no sea futura
    const selectedDate = new Date(dateInput.value);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    if (selectedDate > today) {
        if (!confirm('Ha seleccionado una fecha futura. ¿Está seguro de continuar?')) {
            e.preventDefault();
            return false;
        }
    }
});

// Al cargar updater.php, proteger la página
document.addEventListener('DOMContentLoaded', function() {
    protectPage();

    // Establecer fecha máxima como hoy
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('datadate').max = today;
});
</script>

<?php
// Incluye el pie de página 
include("includes/footer.php");
?>