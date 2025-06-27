<div class="col-lg-6 col-md-6 col-sm-12 mb-3">
    <div class="card h-100">
        <div class="card-body d-flex flex-column text-center">
            <!-- Icono -->
            <div class="mb-3">
                <i class="fa-solid fa-calendar-days"
                    style="font-size: 3rem; color: #198754;"></i>
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
                <input type="hidden" name="csrf_token"
                    value="<?php echo generateCSRFToken(); ?>">

                <!-- Selector de fecha -->
                <div class="mb-3">
                    <label for="datadate" class="form-label">
                        <i class="fas fa-calendar-alt me-2"></i>Escoja La Fecha
                        De Los Datos (Mes/Dia/Año)
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
                        <button type="button" id="prepareSalesBtn"
                            class="btn btn-success btn-lg w-100"
                            onclick="checkAndConfirmDelete('sales')">
                            <i class="fa-solid fa-sack-dollar me-2"></i>
                            Prepare Sales
                        </button>

                        <button type="button" id="prepareCreditsBtn"
                            class="btn btn-success btn-lg w-100"
                            onclick="checkAndConfirmDelete('credits')">
                            <i class="fa-solid fa-hand-holding-dollar me-2"></i>
                            Prepare Credits
                        </button>
                    </div>
                </div>

                <!-- Botones ocultos para envío real del formulario -->
                <input type="hidden" id="hiddenSalesBtn" name="prepare_sales">
                <input type="hidden" id="hiddenCreditsBtn"
                    name="prepare_credits">
            </form>
        </div>
    </div>
</div>