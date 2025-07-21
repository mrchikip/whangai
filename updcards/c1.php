<div class="col-lg-6 col-md-6 col-sm-12 mb-3">
    <div class="card h-100">
        <div class="card-body d-flex flex-column text-center">
            <!-- Icono -->
            <div class="mb-3">
                <i class="fa-solid fa-calendar-days" style="font-size: 3rem; color: #198754;"></i>
            </div>

            <!-- Título centrado -->
            <h5 class="card-title text-center mb-3">1- Fecha de Actualización
            </h5>

            <!-- Descripción centrada -->
            <p class="card-text text-center flex-grow-1 mb-4">
                Selección de la fecha de actualización para la carga de
                información y vínculos para limpiar y preparar las tablas de
                ventas y créditos
            </p>

            <!-- Formulario con selector de fecha y botones -->
            <form method="POST" id="preparationForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <!-- Selector de fecha -->
                <div class="mb-3">
                    <label for="datadate" class="form-label">
                        <i class="fas fa-calendar-alt me-2"></i>Escoja La Fecha
                        De Los Datos (Mes/Dia/Año)
                    </label>
                    <input class="form-control" type="date" id="datadate" name="datadate"
                        value="<?php echo htmlspecialchars($selectedDate, ENT_QUOTES, 'UTF-8'); ?>" required>
                    <div class="form-text">
                        <i class="fas fa-info-circle text-info me-1"></i>
                        Se eliminarán todos los registros desde esta fecha en
                        adelante
                    </div>
                </div>

                <!-- Mensajes de resultado -->
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Botones de acción -->
                <div class="mt-auto">
                    <div class="d-grid gap-2">
                        <button type="button" id="prepareSalesBtn" class="btn btn-success btn-lg w-100"
                            onclick="confirmPreparationAction('sales', 'Prepare Sales', 'Limpiar y preparar la tabla de ventas eliminando registros desde la fecha seleccionada')">
                            <i class="fa-solid fa-sack-dollar me-2"></i>
                            Prepare Sales
                        </button>

                        <button type="button" id="prepareCreditsBtn" class="btn btn-success btn-lg w-100"
                            onclick="confirmPreparationAction('credits', 'Prepare Credits', 'Limpiar y preparar la tabla de créditos eliminando registros desde la fecha seleccionada')">
                            <i class="fa-solid fa-hand-holding-dollar me-2"></i>
                            Prepare Credits
                        </button>
                    </div>
                </div>

                <!-- Botones ocultos para envío real del formulario -->
                <input type="hidden" id="hiddenSalesBtn" name="prepare_sales">
                <input type="hidden" id="hiddenCreditsBtn" name="prepare_credits">
            </form>
        </div>
    </div>
</div>

<script>
// Función para confirmar y ejecutar acciones de preparación
function confirmPreparationAction(action, actionName, description) {
    // Validar que se haya seleccionado una fecha
    const dateInput = document.getElementById('datadate');
    if (!dateInput.value) {
        // Crear modal de error
        const errorModalHtml = `
            <div class="modal fade" id="errorModal" tabindex="-1" aria-labelledby="errorModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title" id="errorModalLabel">
                                <i class="fas fa-exclamation-triangle me-2"></i>Error de Validación
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-danger">
                                <h6 class="alert-heading">
                                    <i class="fas fa-calendar-times me-2"></i>Fecha Requerida
                                </h6>
                                <p class="mb-0">Debe seleccionar una fecha antes de continuar con la preparación.</p>
                            </div>
                            <p class="text-muted">Por favor, seleccione la fecha de los datos en el campo correspondiente.</p>
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

        // Remover modal existente si lo hay
        const existingModal = document.getElementById('errorModal');
        if (existingModal) {
            existingModal.remove();
        }

        // Agregar modal al DOM
        document.body.insertAdjacentHTML('beforeend', errorModalHtml);

        // Mostrar modal
        const modal = new bootstrap.Modal(document.getElementById('errorModal'));
        modal.show();

        // Limpiar modal cuando se cierre y enfocar el campo de fecha
        document.getElementById('errorModal').addEventListener('hidden.bs.modal', function() {
            this.remove();
            dateInput.focus();
        });

        return;
    }

    // Formatear la fecha para mostrar
    const selectedDate = new Date(dateInput.value);
    const formattedDate = selectedDate.toLocaleDateString('es-ES', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });

    // Crear modal de confirmación personalizado
    const modalHtml = `
        <div class="modal fade" id="confirmPreparationModal" tabindex="-1" aria-labelledby="confirmPreparationModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="confirmPreparationModalLabel">
                            <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Preparación
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <h6 class="alert-heading">
                                <i class="fas fa-calendar-check me-2"></i>Acción: ${actionName}
                            </h6>
                            <p class="mb-2">${description}</p>
                            <hr>
                            <p class="mb-0">
                                <strong>Fecha seleccionada:</strong> ${formattedDate}
                            </p>
                        </div>
                        <p><strong>¿Está seguro de que desea ejecutar esta preparación?</strong></p>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Advertencia:</strong> Esta acción eliminará permanentemente todos los registros desde la fecha seleccionada en adelante. Esta operación no se puede deshacer.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="button" class="btn btn-success" onclick="executePreparationAction('${action}')">
                            <i class="fas fa-check me-2"></i>Ejecutar Preparación
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remover modal existente si lo hay
    const existingModal = document.getElementById('confirmPreparationModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Agregar modal al DOM
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Mostrar modal
    const modal = new bootstrap.Modal(document.getElementById('confirmPreparationModal'));
    modal.show();

    // Limpiar modal cuando se cierre
    document.getElementById('confirmPreparationModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

// Función para ejecutar la acción de preparación
function executePreparationAction(action) {
    // Cerrar modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('confirmPreparationModal'));
    modal.hide();

    // Deshabilitar botones y mostrar loading
    const buttons = ['prepareSalesBtn', 'prepareCreditsBtn'];
    buttons.forEach(btnId => {
        const btn = document.getElementById(btnId);
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
    });

    // Ejecutar la acción correspondiente
    switch (action) {
        case 'sales':
            document.getElementById('hiddenSalesBtn').value = '1';
            break;
        case 'credits':
            document.getElementById('hiddenCreditsBtn').value = '1';
            break;
    }

    // Enviar formulario
    document.getElementById('preparationForm').submit();
}

// Restaurar botones si hay error (se ejecuta después de la carga de página)
document.addEventListener('DOMContentLoaded', function() {
    // Restaurar estado de botones
    const prepareSalesBtn = document.getElementById('prepareSalesBtn');
    const prepareCreditsBtn = document.getElementById('prepareCreditsBtn');

    if (prepareSalesBtn) {
        prepareSalesBtn.disabled = false;
        prepareSalesBtn.innerHTML = '<i class="fa-solid fa-sack-dollar me-2"></i>Prepare Sales';
    }

    if (prepareCreditsBtn) {
        prepareCreditsBtn.disabled = false;
        prepareCreditsBtn.innerHTML = '<i class="fa-solid fa-hand-holding-dollar me-2"></i>Prepare Credits';
    }
});
</script>