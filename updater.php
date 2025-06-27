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
            // La fecha ya viene en formato YYYY-MM-DD del input date, mantenerla así
            $ddate = $selectedDate;

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
                                        <?php
                                        include("updcards/c1.php");
                                        ?>

                                        <!-- Segunda tarjeta -->
                                        <?php
                                        include("updcards/c2.php");
                                        ?>

                                        <!-- Tercera tarjeta -->
                                        <?php
                                        include("updcards/c3.php");
                                        ?>

                                        <!-- Cuarta tarjeta -->
                                        <?php
                                        include("updcards/c4.php");
                                        ?>

                                        <!-- Quinta tarjeta -->
                                        <?php
                                        include("updcards/c5.php");
                                        ?>

                                        <!-- Sexta tarjeta -->
                                        <?php
                                        include("updcards/c6.php");
                                        ?>
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
    // Función para verificar cantidad de registros y confirmar eliminación
    function checkAndConfirmDelete(action) {
        const dateInput = document.getElementById('datadate');
        const selectedDate = dateInput.value;

        if (!selectedDate) {
            alert('Por favor seleccione una fecha antes de continuar.');
            dateInput.focus();
            return false;
        }

        // Convertir fecha a formato YYYY-MM-DD (ya viene en este formato del input date)
        const ddate = selectedDate;

        // Mostrar loading en el botón correspondiente
        const button = action === 'sales' ? document.getElementById('prepareSalesBtn') : document.getElementById(
            'prepareCreditsBtn');
        const originalText = button.innerHTML;
        button.disabled = true;
        button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Verificando...';

        // Hacer petición AJAX para contar registros (usando archivo corregido)
        fetch('check_records.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    action: action,
                    date: ddate,
                    csrf_token: document.querySelector('input[name="csrf_token"]').value
                })
            })
            .then(response => {
                // Debug: mostrar el status de la respuesta
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);

                // Intentar obtener el texto de la respuesta para debugging
                return response.text().then(text => {
                    console.log('Response text:', text);

                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}, response: ${text}`);
                    }

                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        throw new Error(`JSON parse error: ${e.message}, response: ${text}`);
                    }
                });
            })
            .then(data => {
                console.log('Response data:', data); // Debug completo

                // Mostrar información de debug si está disponible
                if (data.debug) {
                    console.log('Debug info:', data.debug);
                }

                // Restaurar botón
                button.disabled = false;
                button.innerHTML = originalText;

                if (data.success) {
                    const recordCount = data.count;
                    const formattedDate = new Date(selectedDate).toLocaleDateString('es-ES');
                    const actionText = action === 'sales' ? 'Ventas' : 'Créditos';

                    let confirmMessage;
                    if (recordCount === 0) {
                        confirmMessage =
                            `No se encontraron registros de ${actionText} desde la fecha ${formattedDate}.\n\n¿Desea continuar de todas formas?`;
                    } else {
                        confirmMessage =
                            `¿Está seguro de eliminar ${recordCount} registros de ${actionText} desde ${formattedDate}?\n\nEsta acción no se puede deshacer.`;
                    }

                    if (confirm(confirmMessage)) {
                        // Usuario confirmó, proceder con la eliminación
                        submitForm(action);
                    }
                } else {
                    // Mostrar error con información de debug si está disponible
                    let errorMsg = 'Error al verificar registros: ' + (data.message || 'Error desconocido');

                    if (data.debug) {
                        errorMsg += '\n\nInfo de debug:\n';
                        errorMsg += `Paso: ${data.debug.step || 'desconocido'}\n`;
                        if (data.debug.error) {
                            errorMsg += `Error: ${data.debug.error}\n`;
                        }
                        if (data.debug.session_status) {
                            errorMsg += `Sesión: ${data.debug.session_status}\n`;
                        }
                        if (data.debug.user_logged_in) {
                            errorMsg += `Usuario logueado: ${data.debug.user_logged_in}\n`;
                        }
                    }

                    alert(errorMsg);
                }
            })
            .catch(error => {
                // Restaurar botón en caso de error
                button.disabled = false;
                button.innerHTML = originalText;

                console.error('Fetch error details:', error);

                // Mensaje más específico según el tipo de error
                let errorMessage = 'Error de conexión al verificar registros.\n\n';
                errorMessage += `Detalles técnicos: ${error.message}\n\n`;

                if (error.name === 'TypeError') {
                    errorMessage += 'Verifique que el archivo debug_check_records.php existe.';
                } else if (error.message.includes('HTTP error')) {
                    errorMessage += `Código de error HTTP detectado.`;
                } else if (error.message.includes('JSON parse')) {
                    errorMessage += 'La respuesta del servidor no es JSON válido.';
                }

                errorMessage += '\n\nRevise la consola del navegador (F12) para más detalles.';

                alert(errorMessage);
            });
    }

    // Función para enviar el formulario después de la confirmación
    function submitForm(action) {
        const form = document.getElementById('preparationForm');

        // Limpiar campos ocultos previos
        document.getElementById('hiddenSalesBtn').disabled = true;
        document.getElementById('hiddenCreditsBtn').disabled = true;

        // Habilitar solo el campo correspondiente
        if (action === 'sales') {
            document.getElementById('hiddenSalesBtn').disabled = false;
        } else {
            document.getElementById('hiddenCreditsBtn').disabled = false;
        }

        // Enviar formulario
        form.submit();
    }

    // Función original de confirmación (ahora no se usa, pero se mantiene por compatibilidad)
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