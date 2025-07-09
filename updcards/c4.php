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

            // Procesar botón SVFactors
            if (isset($_POST['svfactors_action'])) {
                $queries = [
                    "UPDATE `sales` SET `svfactores` = '0'",
                    "UPDATE `sales` SET `svfactores` = '1' WHERE `ShipVia` LIKE '%Warehouse%'",
                    "UPDATE `sales` SET `svfactores` = '0' WHERE `customer` LIKE '%Ipanema%'",
                    "UPDATE `sales` SET `svfactores` = '0' WHERE `customer` LIKE '%GLOBAL ROSE.COM LLC%'"
                ];

                $totalAffected = 0;
                foreach ($queries as $query) {
                    $result = mysqli_query($conn2, $query);
                    if ($result) {
                        $totalAffected += mysqli_affected_rows($conn2);
                    } else {
                        throw new Exception('Error en SVFactors: ' . mysqli_error($conn2));
                    }
                }

                $sqlMessage = "SVFactors ejecutado exitosamente. $totalAffected registros actualizados.";
                $sqlMessageType = 'success';
            }

            // Procesar botón CTB
            elseif (isset($_POST['ctb_action'])) {
                $query = "UPDATE `sales` SET `BoxType` = 'CTB' WHERE `Customer` LIKE '%Ipanema%'";
                $result = mysqli_query($conn2, $query);

                if ($result) {
                    $affectedRows = mysqli_affected_rows($conn2);
                    $sqlMessage = "CTB ejecutado exitosamente. $affectedRows registros actualizados.";
                    $sqlMessageType = 'success';
                } else {
                    throw new Exception('Error en CTB: ' . mysqli_error($conn2));
                }
            }

            // Procesar botón Dump
            elseif (isset($_POST['dump_action'])) {
                $queries = [
                    "UPDATE `Sales` SET `Customer` = 'American Business Wholesale' WHERE `Customer` LIKE '%American Business%' AND `TotalPrice`>`TotalCost`",
                    "UPDATE `Sales` SET `Customer` = 'American Business Local' WHERE `Customer` LIKE '%American Business%' AND `TotalPrice`<=`TotalCost`"
                ];

                $totalAffected = 0;
                foreach ($queries as $query) {
                    $result = mysqli_query($conn2, $query);
                    if ($result) {
                        $totalAffected += mysqli_affected_rows($conn2);
                    } else {
                        throw new Exception('Error en Dump: ' . mysqli_error($conn2));
                    }
                }

                $sqlMessage = "Dump ejecutado exitosamente. $totalAffected registros actualizados.";
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
            <h5 class="card-title text-center mb-3">Ajustes SQL</h5>

            <!-- Descripción centrada -->
            <p class="card-text text-center flex-grow-1 mb-4">
                Ejecutar ajustes automáticos en la base de datos para actualizar
                campos específicos según reglas de negocio predefinidas
            </p>

            <!-- Formulario con botones de ajustes SQL -->
            <form method="POST" id="sqlAdjustmentsForm">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">

                <!-- Mensajes de resultado -->
                <?php if ($sqlMessage): ?>
                <div class="alert alert-<?php echo $sqlMessageType; ?> alert-dismissible fade show" role="alert">
                    <?php echo $sqlMessage; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Botones de acción -->
                <div class="mt-auto">
                    <div class="d-grid gap-2">
                        <!-- Botón SVFactors -->
                        <button type="button" id="svfactorsBtn" class="btn btn-success btn-lg w-100"
                            onclick="confirmSQLAction('svfactors', 'SVFactors', 'Actualizar valores de svfactores según reglas de Warehouse, Ipanema y Global Rose')">
                            <i class="fa-solid fa-calculator me-2"></i>
                            SVFactors
                        </button>

                        <!-- Botón CTB -->
                        <button type="button" id="ctbBtn" class="btn btn-success btn-lg w-100"
                            onclick="confirmSQLAction('ctb', 'CTB', 'Cambiar BoxType a CTB para clientes Ipanema')">
                            <i class="fa-solid fa-box me-2"></i>
                            CTB
                        </button>

                        <!-- Botón Dump -->
                        <button type="button" id="dumpBtn" class="btn btn-success btn-lg w-100"
                            onclick="confirmSQLAction('dump', 'Dump', 'Clasificar clientes American Business según margen')">
                            <i class="fa-solid fa-trash me-2"></i>
                            Dump
                        </button>
                    </div>
                </div>

                <!-- Botones ocultos para envío real del formulario -->
                <input type="hidden" id="hiddenSVFactorsBtn" name="svfactors_action">
                <input type="hidden" id="hiddenCTBBtn" name="ctb_action">
                <input type="hidden" id="hiddenDumpBtn" name="dump_action">
            </form>
        </div>
    </div>
</div>

<!-- Información técnica sobre los ajustes -->
<div class="col-12 mt-3">
    <div class="card">
        <div class="card-body">
            <h6 class="card-title">
                <i class="fas fa-info-circle me-2 text-success"></i>Detalles de los Ajustes SQL
            </h6>
            <div class="row">
                <div class="col-md-4">
                    <strong>SVFactors:</strong>
                    <ul class="mb-0 small">
                        <li>Establece svfactores = '0' para todos</li>
                        <li>Cambia a '1' si ShipVia contiene "Warehouse"</li>
                        <li>Fuerza '0' para clientes Ipanema y Global Rose</li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <strong>CTB:</strong>
                    <ul class="mb-0 small">
                        <li>Cambia BoxType a 'CTB'</li>
                        <li>Solo para clientes que contengan 'Ipanema'</li>
                        <li>Ajuste específico de tipo de caja</li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <strong>Dump:</strong>
                    <ul class="mb-0 small">
                        <li>American Business → 'Wholesale' si TotalPrice > TotalCost</li>
                        <li>American Business → 'Local' si TotalPrice ≤ TotalCost</li>
                        <li>Clasificación por margen de ganancia</li>
                    </ul>
                </div>
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
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="confirmSQLModalLabel">
                            <i class="fas fa-exclamation-triangle me-2"></i>Confirmar Ajuste SQL
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
                        <p><strong>¿Está seguro de que desea ejecutar este ajuste SQL?</strong></p>
                        <p class="text-muted small">Esta acción modificará registros en la base de datos y no se puede deshacer fácilmente.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Cancelar
                        </button>
                        <button type="button" class="btn btn-success" onclick="executeSQLAction('${action}')">
                            <i class="fas fa-check me-2"></i>Ejecutar Ajuste
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
    // Cerrar modal
    const modal = bootstrap.Modal.getInstance(document.getElementById('confirmSQLModal'));
    modal.hide();

    // Deshabilitar botones y mostrar loading
    const buttons = ['svfactorsBtn', 'ctbBtn', 'dumpBtn'];
    buttons.forEach(btnId => {
        const btn = document.getElementById(btnId);
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Procesando...';
    });

    // Ejecutar la acción correspondiente
    switch (action) {
        case 'svfactors':
            document.getElementById('hiddenSVFactorsBtn').value = '1';
            break;
        case 'ctb':
            document.getElementById('hiddenCTBBtn').value = '1';
            break;
        case 'dump':
            document.getElementById('hiddenDumpBtn').value = '1';
            break;
    }

    // Enviar formulario
    document.getElementById('sqlAdjustmentsForm').submit();
}

// Restaurar botones si hay error (se ejecuta después de la carga de página)
document.addEventListener('DOMContentLoaded', function() {
    // Restaurar estado de botones
    const svfactorsBtn = document.getElementById('svfactorsBtn');
    const ctbBtn = document.getElementById('ctbBtn');
    const dumpBtn = document.getElementById('dumpBtn');

    if (svfactorsBtn) {
        svfactorsBtn.disabled = false;
        svfactorsBtn.innerHTML = '<i class="fa-solid fa-calculator me-2"></i>SVFactors';
    }

    if (ctbBtn) {
        ctbBtn.disabled = false;
        ctbBtn.innerHTML = '<i class="fa-solid fa-box me-2"></i>CTB';
    }

    if (dumpBtn) {
        dumpBtn.disabled = false;
        dumpBtn.innerHTML = '<i class="fa-solid fa-trash me-2"></i>Dump';
    }
});
</script>