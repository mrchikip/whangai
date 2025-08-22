<?php
// Variables para manejo de mensajes y fecha
$c1_message = '';
$c1_messageType = '';
$c1_selectedDate = date('Y-m-d');

// Procesar formulario de preparacion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $c1_message = 'Token de seguridad invalido.';
        $c1_messageType = 'danger';
    } else {
        // Obtener y validar la fecha
        if (isset($_POST['datadate']) && !empty($_POST['datadate'])) {
            $c1_selectedDate = $_POST['datadate'];

            // Validación adicional en el servidor: no permitir fechas futuras
            $selectedDateTime = strtotime($c1_selectedDate);
            $todayDateTime = strtotime(date('Y-m-d'));

            if ($selectedDateTime > $todayDateTime) {
                $c1_message = 'Error: No se puede seleccionar una fecha futura. La fecha máxima permitida es hoy.';
                $c1_messageType = 'danger';
            } else {
                try {
                    // Verificar conexion a la base de datos
                    if (!$conn2) {
                        throw new Exception('Error de conexion a la base de datos');
                    }

                    mysqli_set_charset($conn2, 'utf8');

                    // Procesar accion de preparar sales
                    if (!empty($_POST['prepare_sales'])) {
                        $deleteQuery = "DELETE FROM `sales` WHERE `InvoiceDate` >= ?";
                        $stmt = $conn2->prepare($deleteQuery);

                        if (!$stmt) {
                            throw new Exception('Error preparando la consulta de sales: ' . $conn2->error);
                        }

                        $stmt->bind_param("s", $c1_selectedDate);

                        if ($stmt->execute()) {
                            $affectedRows = $stmt->affected_rows;
                            $c1_message = "Preparacion de Sales completada exitosamente. $affectedRows registros eliminados desde la fecha $c1_selectedDate.";
                            $c1_messageType = 'success';
                        } else {
                            throw new Exception('Error ejecutando eliminacion de sales: ' . $stmt->error);
                        }

                        $stmt->close();
                    }
                    // Procesar accion de preparar credits
                    elseif (!empty($_POST['prepare_credits'])) {
                        $deleteQuery = "DELETE FROM `credits` WHERE `CreditDate` >= ?";
                        $stmt = $conn2->prepare($deleteQuery);

                        if (!$stmt) {
                            throw new Exception('Error preparando la consulta de credits: ' . $conn2->error);
                        }

                        $stmt->bind_param("s", $c1_selectedDate);

                        if ($stmt->execute()) {
                            $affectedRows = $stmt->affected_rows;
                            $c1_message = "Preparacion de Credits completada exitosamente. $affectedRows registros eliminados desde la fecha $c1_selectedDate.";
                            $c1_messageType = 'success';
                        } else {
                            throw new Exception('Error ejecutando eliminacion de credits: ' . $stmt->error);
                        }

                        $stmt->close();
                    }
                } catch (Exception $e) {
                    error_log("Error en c1.php: " . $e->getMessage());
                    $c1_message = 'Error: ' . $e->getMessage();
                    $c1_messageType = 'danger';
                }
            }
        } else {
            $c1_message = 'Debe seleccionar una fecha.';
            $c1_messageType = 'danger';
        }
    }
}
?>

<div class="col-lg-6 col-md-6 col-sm-12 mb-3">
    <div class="card h-100">
        <div class="card-body d-flex flex-column text-center">
            <!-- Icono -->
            <div class="mb-3">
                <i class="fas fa-calendar-days" style="font-size: 3rem; color: #198754;"></i>
            </div>

            <!-- Titulo centrado -->
            <h5 class="card-title text-center mb-3">1- Fecha de Actualizacion</h5>

            <!-- Descripcion centrada -->
            <p class="card-text text-center flex-grow-1 mb-4">
                Seleccion de la fecha de actualizacion para la carga de
                informacion y vinculos para limpiar y preparar las tablas de
                ventas y creditos
            </p>

            <!-- Formulario con selector de fecha y botones -->
            <form method="POST" id="preparationForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <!-- Selector de fecha -->
                <div class="mb-3">
                    <label for="datadate" class="form-label">
                        <i class="fas fa-calendar-alt me-2"></i>Escoja La Fecha
                        De Los Datos (Mes/Dia/Ano)
                    </label>
                    <input class="form-control" type="date" id="datadate" name="datadate"
                        value="<?php echo htmlspecialchars($c1_selectedDate, ENT_QUOTES, 'UTF-8'); ?>"
                        max="<?php echo date('Y-m-d'); ?>" required>
                    <div class="form-text">
                        <i class="fas fa-info-circle text-info me-1"></i>
                        Se eliminaran todos los registros desde esta fecha en
                        adelante. Fecha máxima: <?php echo date('d/m/Y'); ?>
                    </div>
                </div>

                <!-- Mensajes de resultado -->
                <?php if ($c1_message): ?>
                <div class="alert alert-<?php echo $c1_messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($c1_message, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Botones de accion -->
                <div class="mt-auto">
                    <div class="d-grid gap-2">
                        <button type="button" id="prepareSalesBtn" class="btn btn-success btn-lg w-100"
                            onclick="confirmPreparationAction('sales', 'Prepare Sales', 'Limpiar y preparar la tabla de ventas eliminando registros desde la fecha seleccionada')">
                            <i class="fas fa-sack-dollar me-2"></i>
                            Prepare Sales
                        </button>

                        <button type="button" id="prepareCreditsBtn" class="btn btn-success btn-lg w-100"
                            onclick="confirmPreparationAction('credits', 'Prepare Credits', 'Limpiar y preparar la tabla de creditos eliminando registros desde la fecha seleccionada')">
                            <i class="fas fa-hand-holding-dollar me-2"></i>
                            Prepare Credits
                        </button>
                    </div>
                </div>

                <!-- Botones ocultos para envio real del formulario -->
                <input type="hidden" id="hiddenSalesBtn" name="prepare_sales" value="">
                <input type="hidden" id="hiddenCreditsBtn" name="prepare_credits" value="">
            </form>
        </div>
    </div>
</div>

<script>
// Funcion para confirmar y ejecutar acciones de preparacion
function confirmPreparationAction(action, actionName, description) {
    // Validar que se haya seleccionado una fecha
    const dateInput = document.getElementById('datadate');
    if (!dateInput.value) {
        showErrorModal('Fecha Requerida', 'Debe seleccionar una fecha antes de continuar con la preparacion.');
        return;
    }

    // Validación más estricta de fechas futuras
    const selectedDate = new Date(dateInput.value + 'T00:00:00'); // Forzar timezone local
    const today = new Date();
    today.setHours(0, 0, 0, 0); // Resetear horas para comparación exacta

    if (selectedDate > today) {
        showErrorModal('Fecha Invalida',
            'No se puede seleccionar una fecha futura. La fecha máxima permitida es hoy (' + today
            .toLocaleDateString('es-ES') + ').');
        return;
    }

    // Formatear la fecha para mostrar
    const formattedDate = selectedDate.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    showConfirmationModal(action, actionName, description, formattedDate);
}

// Funcion para mostrar modal de error
function showErrorModal(title, message) {
    const errorModalHtml = `
            <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="errorModalLabel">
                                <i class="fas fa-exclamation-triangle me-2"></i>Error de Validacion
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger">
                                <h6 class="alert-heading">
                                    <i class="fas fa-calendar-times me-2"></i>${title}
                                </h6>
                                <p class="mb-0">${message}</p>
                            </div>
                            <p class="text-muted">Por favor, corrija el error antes de continuar.</p>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Entendido
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

    showModal(errorModalHtml, 'errorModal', function() {
        document.getElementById('datadate').focus();
    });
}

// Funcion para mostrar modal de confirmacion
function showConfirmationModal(action, actionName, description, formattedDate) {
    const modalHtml = `
            <div class="modal fade" id="confirmPreparationModal" tabindex="-1" aria-labelledby="confirmPreparationModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-success text-white">
                            <h5 class="modal-title" id="confirmPreparationModalLabel">
                                <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Preparacion
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-warning">
                                <h6 class="alert-heading">
                                    <i class="fas fa-calendar-check me-2"></i>Accion: ${actionName}
                                </h6>
                                <p class="mb-2">${description}</p>
                                <hr>
                                <p class="mb-0">
                                    <strong>Fecha seleccionada:</strong> ${formattedDate}
                                </p>
                            </div>
                            <p><strong>Esta seguro de que desea ejecutar esta preparacion?</strong></p>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Advertencia:</strong> Esta accion eliminara permanentemente todos los registros desde la fecha seleccionada en adelante. Esta operacion no se puede deshacer.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-2"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-success" onclick="executePreparationAction('${action}')">
                                <i class="fas fa-check me-2"></i>Ejecutar Preparacion
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

    showModal(modalHtml, 'confirmPreparationModal');
}

// Funcion utilitaria para mostrar modales
function showModal(modalHtml, modalId, onHiddenCallback = null) {
    try {
        // Remover modal existente si lo hay
        const existingModal = document.getElementById(modalId);
        if (existingModal) {
            existingModal.remove();
        }

        // Agregar modal al DOM
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById(modalId));
        modal.show();

        // Limpiar modal cuando se cierre
        document.getElementById(modalId).addEventListener('hidden.bs.modal', function() {
            this.remove();
            if (onHiddenCallback) {
                onHiddenCallback();
            }
        });
    } catch (error) {
        console.error('Error mostrando modal:', error);
        // Fallback a alert nativo
        alert('Error: Por favor recargue la pagina e intente nuevamente.');
    }
}

// Funcion para ejecutar la accion de preparacion
function executePreparationAction(action) {
    try {
        // Cerrar modal
        const modal = bootstrap.Modal.getInstance(document.getElementById('confirmPreparationModal'));
        if (modal) {
            modal.hide();
        }

        // CRITICO: Limpiar TODOS los campos ocultos primero
        document.getElementById('hiddenSalesBtn').value = '';
        document.getElementById('hiddenCreditsBtn').value = '';

        // Solo deshabilitar el boton especifico, no todos
        let currentButton;
        let otherButton;

        if (action === 'sales') {
            currentButton = document.getElementById('prepareSalesBtn');
            otherButton = document.getElementById('prepareCreditsBtn');
            // Establecer valor SOLO para sales
            document.getElementById('hiddenSalesBtn').value = '1';
        } else if (action === 'credits') {
            currentButton = document.getElementById('prepareCreditsBtn');
            otherButton = document.getElementById('prepareSalesBtn');
            // Establecer valor SOLO para credits
            document.getElementById('hiddenCreditsBtn').value = '1';
        }

        // Deshabilitar solo el boton actual y mostrar loading
        if (currentButton) {
            currentButton.disabled = true;
            currentButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
        }

        // Mantener el otro boton habilitado pero visualmente deshabilitado temporalmente
        if (otherButton) {
            otherButton.style.opacity = '0.6';
            otherButton.style.pointerEvents = 'none';
        }

        // Debug: Verificar valores antes del envio
        console.log('Accion seleccionada:', action);
        console.log('prepare_sales value:', document.getElementById('hiddenSalesBtn').value);
        console.log('prepare_credits value:', document.getElementById('hiddenCreditsBtn').value);
        console.log('Fecha seleccionada:', document.getElementById('datadate').value);

        // Breve delay para asegurar que los valores se establecieron
        setTimeout(function() {
            document.getElementById('preparationForm').submit();
        }, 100);

    } catch (error) {
        console.error('Error ejecutando accion:', error);
        setButtonsLoadingState(false);
        alert('Error: ' + error.message);
    }
}

// Funcion para manejar estado de loading de botones
function setButtonsLoadingState(loading) {
    const buttons = [{
            id: 'prepareSalesBtn',
            text: 'Prepare Sales',
            icon: 'fas fa-sack-dollar'
        },
        {
            id: 'prepareCreditsBtn',
            text: 'Prepare Credits',
            icon: 'fas fa-hand-holding-dollar'
        }
    ];

    buttons.forEach(button => {
        const btn = document.getElementById(button.id);
        if (btn) {
            btn.disabled = loading;
            if (loading) {
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
            } else {
                btn.innerHTML = `<i class="${button.icon} me-2"></i>${button.text}`;
            }
        }
    });
}

// Validación adicional en tiempo real del input de fecha
function setupDateValidation() {
    const dateInput = document.getElementById('datadate');
    const today = new Date().toISOString().split('T')[0];

    // Establecer max attribute dinámicamente por si acaso
    dateInput.setAttribute('max', today);

    // Validación en tiempo real cuando el usuario cambia la fecha
    dateInput.addEventListener('change', function() {
        const selectedDate = new Date(this.value + 'T00:00:00');
        const todayDate = new Date();
        todayDate.setHours(0, 0, 0, 0);

        if (selectedDate > todayDate) {
            showErrorModal('Fecha Invalida',
                'No se puede seleccionar una fecha futura. La fecha máxima permitida es hoy (' + todayDate
                .toLocaleDateString('es-ES') + ').');
            this.value = today; // Resetear a hoy
        }
    });

    // Validación adicional en el evento input (para navegadores que no respetan max)
    dateInput.addEventListener('input', function() {
        const selectedDate = new Date(this.value + 'T00:00:00');
        const todayDate = new Date();
        todayDate.setHours(0, 0, 0, 0);

        if (selectedDate > todayDate) {
            this.value = today; // Resetear a hoy inmediatamente
        }
    });
}

// Restaurar botones cuando se carga la pagina
document.addEventListener('DOMContentLoaded', function() {
    // Configurar validación de fechas
    setupDateValidation();

    // Restaurar estado de botones
    const prepareSalesBtn = document.getElementById('prepareSalesBtn');
    const prepareCreditsBtn = document.getElementById('prepareCreditsBtn');

    if (prepareSalesBtn) {
        prepareSalesBtn.disabled = false;
        prepareSalesBtn.style.opacity = '1';
        prepareSalesBtn.style.pointerEvents = 'auto';
        prepareSalesBtn.innerHTML = '<i class="fas fa-sack-dollar me-2"></i>Prepare Sales';
    }

    if (prepareCreditsBtn) {
        prepareCreditsBtn.disabled = false;
        prepareCreditsBtn.style.opacity = '1';
        prepareCreditsBtn.style.pointerEvents = 'auto';
        prepareCreditsBtn.innerHTML = '<i class="fas fa-hand-holding-dollar me-2"></i>Prepare Credits';
    }

    // Limpiar campos ocultos al cargar
    document.getElementById('hiddenSalesBtn').value = '';
    document.getElementById('hiddenCreditsBtn').value = '';

    // Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            if (alert && alert.parentNode) {
                alert.classList.remove('show');
                setTimeout(() => {
                    if (alert.parentNode) {
                        alert.remove();
                    }
                }, 150);
            }
        }, 5000);
    });
});
</script>