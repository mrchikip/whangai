<?php
// Lógica PHP para manejar los ajustes SQL
$sqlMessage = '';
$sqlMessageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar token CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $sqlMessage = 'Token de seguridad inválido.';
        $sqlMessageType = 'danger';
    } else {
        try {
            // Verificar conexión a la base de datos
            if (!$conn2) {
                throw new Exception('Error de conexión a la base de datos');
            }

            mysqli_set_charset($conn2, 'utf8');

            // Procesar botón de Ajustes SQL
            if (!empty($_POST['sql_adjustments_action'])) {
                // Array con todas las consultas SQL a ejecutar
                $queries = [
                    "UPDATE `sales` SET `BoxType` = 'CTB' WHERE `Customer` LIKE '%Ipanema%'",
                    "UPDATE `sales` SET `svfactores` = '0'",
                    "UPDATE `sales` SET `svfactores` = '1' WHERE `ShipVia` LIKE '%Warehouse%'",
                    "UPDATE `sales` SET `svfactores` = '0' WHERE `customer` LIKE '%Ipanema%'",
                    "UPDATE `sales` SET `svfactores` = '0' WHERE `customer` LIKE '%GLOBAL ROSE.COM LLC%'",
                    "UPDATE `Sales` SET `Customer` = 'American Business Wholesale' WHERE `Customer` LIKE '%American Business%' AND `TotalPrice` > `TotalCost`",
                    "UPDATE `Sales` SET `Customer` = 'American Business Local' WHERE `Customer` LIKE '%American Business%' AND `TotalPrice` <= `TotalCost`"
                ];

                $totalAffected = 0;
                $executedQueries = 0;

                // Ejecutar cada consulta en secuencia
                foreach ($queries as $index => $query) {
                    $result = mysqli_query($conn2, $query);
                    if ($result) {
                        $affectedRows = mysqli_affected_rows($conn2);
                        $totalAffected += $affectedRows;
                        $executedQueries++;

                        // Log para debug (opcional)
                        error_log("Query " . ($index + 1) . " ejecutada: $affectedRows filas afectadas");
                    } else {
                        throw new Exception('Error en consulta ' . ($index + 1) . ': ' . mysqli_error($conn2) . ' | Query: ' . $query);
                    }
                }

                $sqlMessage = "Ajustes SQL ejecutados exitosamente. $executedQueries consultas completadas, $totalAffected registros actualizados en total.";
                $sqlMessageType = 'success';
            }
        } catch (Exception $e) {
            $sqlMessage = 'Error: ' . $e->getMessage();
            $sqlMessageType = 'danger';
            error_log("SQL Adjustments Error: " . $e->getMessage());
        }
    }
}
?>

<div class="col-lg-6 col-md-6 col-sm-12 mb-3">
    <div class="card h-100">
        <div class="card-body d-flex flex-column text-center">
            <!-- Icono -->
            <div class="mb-3">
                <i class="fa-solid fa-database" style="font-size: 3rem; color: #198754;"></i>
            </div>

            <!-- Título centrado -->
            <h5 class="card-title text-center mb-3">4- Ajustes SQL</h5>

            <!-- Descripción centrada -->
            <p class="card-text text-center flex-grow-1 mb-4">
                Ejecutar ajustes automáticos en la base de datos para actualizar
                campos específicos según reglas de negocio predefinidas (BoxType, SVFactors y Customer)
            </p>

            <!-- Formulario con botón de ajustes SQL -->
            <form method="POST" id="sqlAdjustmentsForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <!-- Mensajes de resultado -->
                <?php if ($sqlMessage): ?>
                <div class="alert alert-<?php echo $sqlMessageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $sqlMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Botón de acción -->
                <div class="mt-auto">
                    <div class="d-grid gap-2">
                        <button type="button" id="sqlAdjustmentsBtn" class="btn btn-success btn-lg w-100"
                            onclick="confirmSQLAction('sql_adjustments', 'Ajustes SQL', 'Ejecutar todas las reglas de negocio: BoxType CTB para Ipanema, actualizar SVFactors según Warehouse/Ipanema/Global Rose, y clasificar American Business por margen')">
                            <i class="fa-solid fa-database me-2"></i>
                            Ajustes SQL
                        </button>
                    </div>
                </div>

                <!-- Botón oculto para envío real del formulario -->
                <input type="hidden" id="hiddenSQLAdjustmentsBtn" name="sql_adjustments_action" value="">
            </form>
        </div>
    </div>
</div>

<!-- Modal de carga durante ejecución SQL -->
<div class="modal fade" id="sqlLoadingModal" tabindex="-1" aria-labelledby="sqlLoadingModalLabel" aria-hidden="true"
    data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="sqlLoadingModalLabel">
                    <i class="fas fa-database me-2"></i>Ejecutando Ajustes SQL
                </h5>
            </div>
            <div class="modal-body text-center">
                <div class="mb-3">
                    <div class="spinner-border text-success" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
                <h5 class="text-success mb-3">Se están ejecutando los ajustes solicitados</h5>
                <p class="mb-3">Este proceso puede tardar un poco, por favor espere...</p>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>¡IMPORTANTE!</strong> NO recargue la página ni cierre el navegador durante este proceso.
                </div>
                <div class="progress mb-3">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" role="progressbar"
                        style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100">
                        Procesando...
                    </div>
                </div>
                <small class="text-muted">
                    <i class="fas fa-clock me-1"></i>
                    Tiempo estimado: 30-60 segundos
                </small>
            </div>
        </div>
    </div>
</div>

<script>
// Función para confirmar y ejecutar acciones SQL
function confirmSQLAction(action, actionName, description) {
    // Crear modal de confirmación personalizado
    const modalHtml = `
        <div class="modal fade" id="confirmSQLModal" tabindex="-1" aria-labelledby="confirmSQLModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="confirmSQLModalLabel">
                            <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Ajustes SQL
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-success">
                            <h6 class="alert-heading">
                                <i class="fas fa-database me-2"></i>Acción: ${actionName}
                            </h6>
                            <p class="mb-0">${description}</p>
                        </div>
                        <div class="alert alert-info">
                            <h6 class="alert-heading">
                                <i class="fas fa-list me-2"></i>Se ejecutarán las siguientes operaciones:
                            </h6>
                            <ol class="mb-0 small">
                                <li>Actualizar BoxType = 'CTB' para clientes Ipanema</li>
                                <li>Resetear svfactores = '0' para todos los registros</li>
                                <li>Establecer svfactores = '1' donde ShipVia contenga "Warehouse"</li>
                                <li>Forzar svfactores = '0' para clientes Ipanema</li>
                                <li>Forzar svfactores = '0' para clientes Global Rose</li>
                                <li>Clasificar American Business como "Wholesale" (TotalPrice > TotalCost)</li>
                                <li>Clasificar American Business como "Local" (TotalPrice ≤ TotalCost)</li>
                            </ol>
                        </div>
                        <p><strong>¿Está seguro de que desea ejecutar todos estos ajustes SQL?</strong></p>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Advertencia:</strong> Esta acción modificará múltiples registros en la base de datos y no se puede deshacer fácilmente.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="button" class="btn btn-success" onclick="executeSQLAction('${action}')">
                            <i class="fas fa-check me-2"></i>Ejecutar Ajustes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;

    // Remover modal existente si lo hay
    const existingModal = document.getElementById('confirmSQLModal');
    if (existingModal) {
        existingModal.remove();
    }

    // Agregar modal al DOM
    document.body.insertAdjacentHTML('beforeend', modalHtml);

    // Mostrar modal
    const modal = new bootstrap.Modal(document.getElementById('confirmSQLModal'));
    modal.show();

    // Limpiar modal cuando se cierre
    document.getElementById('confirmSQLModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

// Función para ejecutar la acción SQL
function executeSQLAction(action) {
    // Cerrar modal de confirmación
    const confirmModal = bootstrap.Modal.getInstance(document.getElementById('confirmSQLModal'));
    if (confirmModal) {
        confirmModal.hide();
    }

    // Mostrar modal de carga inmediatamente y guardarlo en sessionStorage
    const loadingModal = new bootstrap.Modal(document.getElementById('sqlLoadingModal'));
    loadingModal.show();

    // Marcar que estamos ejecutando SQL para mostrar el modal después de la recarga
    sessionStorage.setItem('sqlExecuting', 'true');
    sessionStorage.setItem('sqlStartTime', Date.now().toString());

    // Deshabilitar botón y mostrar loading
    const button = document.getElementById('sqlAdjustmentsBtn');
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Ejecutando ajustes...';

    // Ocultar todo el contenido principal y mostrar pantalla de carga
    document.getElementById('main-content').style.display = 'none';
    document.getElementById('loading-message').style.display = 'block';

    // Actualizar el mensaje de carga
    const loadingContent = document.querySelector('.auth-loading-content');
    if (loadingContent) {
        loadingContent.innerHTML = `
            <div class="text-center">
                <div class="spinner-border text-success mb-3" style="width: 3rem; height: 3rem;" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <h3 class="text-success">Ejecutando Ajustes SQL</h3>
                <p>Se están ejecutando los ajustes solicitados...</p>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    <strong>¡IMPORTANTE!</strong> NO recargue la página ni cierre el navegador.
                </div>
                <div class="progress mt-3">
                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                         role="progressbar" style="width: 100%">
                        Procesando consultas SQL...
                    </div>
                </div>
                <small class="text-muted mt-2 d-block">
                    <i class="fas fa-clock me-1"></i>
                    Este proceso puede tardar varios minutos
                </small>
            </div>
        `;
    }

    // Limpiar campo oculto y establecer valor
    document.getElementById('hiddenSQLAdjustmentsBtn').value = '';
    document.getElementById('hiddenSQLAdjustmentsBtn').value = '1';

    // Debug
    console.log('Ejecutando ajustes SQL...');
    console.log('sql_adjustments_action value:', document.getElementById('hiddenSQLAdjustmentsBtn').value);

    // Enviar formulario después de configurar todo
    setTimeout(function() {
        document.getElementById('sqlAdjustmentsForm').submit();
    }, 100);
}

// Restaurar botón después de carga de página
document.addEventListener('DOMContentLoaded', function() {
    // Verificar si estamos regresando de una ejecución SQL
    const sqlExecuting = sessionStorage.getItem('sqlExecuting');

    if (sqlExecuting === 'true') {
        // Mostrar pantalla de carga mientras se procesa
        document.getElementById('main-content').style.display = 'none';
        document.getElementById('loading-message').style.display = 'block';

        // Actualizar el mensaje de carga
        const loadingContent = document.querySelector('.auth-loading-content');
        if (loadingContent) {
            const startTime = parseInt(sessionStorage.getItem('sqlStartTime') || '0');
            const elapsed = Math.floor((Date.now() - startTime) / 1000);

            loadingContent.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-success mb-3" style="width: 3rem; height: 3rem;" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <h3 class="text-success">Ejecutando Ajustes SQL</h3>
                    <p>Se están ejecutando los ajustes solicitados...</p>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>¡IMPORTANTE!</strong> NO recargue la página ni cierre el navegador.
                    </div>
                    <div class="progress mt-3">
                        <div class="progress-bar progress-bar-striped progress-bar-animated bg-success" 
                             role="progressbar" style="width: 100%">
                            Procesando consultas SQL...
                        </div>
                    </div>
                    <small class="text-muted mt-2 d-block">
                        <i class="fas fa-clock me-1"></i>
                        Tiempo transcurrido: ${elapsed} segundos
                    </small>
                </div>
            `;
        }

        // Verificar cada segundo si el proceso terminó
        const checkCompletion = setInterval(function() {
            // Si hay un mensaje de éxito o error en la página, el proceso terminó
            const alerts = document.querySelectorAll('.alert-success, .alert-danger');

            if (alerts.length > 0) {
                // Limpiar sessionStorage
                sessionStorage.removeItem('sqlExecuting');
                sessionStorage.removeItem('sqlStartTime');

                // Mostrar el contenido principal
                document.getElementById('loading-message').style.display = 'none';
                document.getElementById('main-content').style.display = 'block';

                clearInterval(checkCompletion);
            }
        }, 1000);

        // Fallback: después de 5 minutos, mostrar contenido de todos modos
        setTimeout(function() {
            sessionStorage.removeItem('sqlExecuting');
            sessionStorage.removeItem('sqlStartTime');
            document.getElementById('loading-message').style.display = 'none';
            document.getElementById('main-content').style.display = 'block';
            clearInterval(checkCompletion);
        }, 300000); // 5 minutos

    } else {
        // Funcionamiento normal
        document.getElementById('loading-message').style.display = 'none';
        document.getElementById('main-content').style.display = 'block';
    }

    // Restaurar estado del botón
    const sqlAdjustmentsBtn = document.getElementById('sqlAdjustmentsBtn');

    if (sqlAdjustmentsBtn) {
        sqlAdjustmentsBtn.disabled = false;
        sqlAdjustmentsBtn.innerHTML = '<i class="fa-solid fa-database me-2"></i>Ajustes SQL';
    }

    // Limpiar campo oculto al cargar
    if (document.getElementById('hiddenSQLAdjustmentsBtn')) {
        document.getElementById('hiddenSQLAdjustmentsBtn').value = '';
    }

    // Ocultar modal de carga si la página se recargó después del proceso
    const loadingModal = document.getElementById('sqlLoadingModal');
    if (loadingModal) {
        const modalInstance = bootstrap.Modal.getInstance(loadingModal);
        if (modalInstance) {
            modalInstance.hide();
        }
    }

    // Auto-dismiss alerts after 8 seconds
    const alerts = document.querySelectorAll('.alert:not(.alert-warning)');
    alerts.forEach(alert => {
        if (alert.classList.contains('alert-success') || alert.classList.contains('alert-danger')) {
            setTimeout(() => {
                if (alert && alert.parentNode) {
                    alert.classList.remove('show');
                    setTimeout(() => {
                        if (alert.parentNode) {
                            alert.remove();
                        }
                    }, 150);
                }
            }, 8000);
        }
    });
});
</script>