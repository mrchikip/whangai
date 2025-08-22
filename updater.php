<?php
// Incluye la conexion a la base de datos
include("db.php");
// Incluye la proteccion de autenticacion de usuario
include("includes/auth.php");

// Funcion para generar token CSRF (necesaria para las tarjetas)
function generateCSRFToken()
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Funcion para validar token CSRF (necesaria para las tarjetas)
function validateCSRFToken($token)
{
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Incluye la cabecera HTML y recursos
include("includes/header.php");
?>

<!-- Elemento para mostrar mensaje de carga durante verificacion de autenticacion -->
<div id="loading-message" style="display: none;">
    <div class="auth-loading-content">
        <h3>Verificando autenticacion...</h3>
        <p>Por favor espere...</p>
    </div>
</div>

<!-- Contenido principal protegido -->
<div id="main-content" style="display: none;">
    <div class="container p-4">
        <div class="row">
            <div class="col-md-4">
                <?php
                // Muestra mensaje de alerta si existe en sesion
                if (isset($_SESSION['message'])) { ?>
                    <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
                        <?= htmlspecialchars($_SESSION['message'], ENT_QUOTES, 'UTF-8') ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                        </button>
                    </div>
                <?php
                    unset($_SESSION['message'], $_SESSION['message_type']);
                } ?>

                <!-- Rejilla principal de tarjetas -->
                <div class="container mt-4">
                    <div class="row justify-content-center align-items-stretch">
                        <div class="col-12">
                            <div class="card shadow" style="width: 80vw; margin: 0 auto;">
                                <div class="card-body">
                                    <div class="row justify-content-center align-items-stretch">
                                        <!-- Tarjeta 1: Fecha de Actualizacion -->
                                        <?php include("updcards/c1.php"); ?>

                                        <!-- Tarjeta 2: Carga de Informacion -->
                                        <?php include("updcards/c2.php"); ?>

                                        <!-- Tarjeta 3: Carga de Creditos -->
                                        <?php include("updcards/c3.php"); ?>

                                        <!-- Tarjeta 4: Ajustes SQL -->
                                        <?php include("updcards/c4.php"); ?>

                                        <!-- Tarjeta 5: En Construccion -->
                                        <?php include("updcards/c5.php"); ?>

                                        <!-- Tarjeta 6: En Construccion -->
                                        <?php include("updcards/c6.php"); ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Boton de regreso -->
                <div class="container mt-4">
                    <div class="row justify-content-center align-items-stretch">
                        <div class="col-12">
                            <div class="card shadow" style="width: 80vw; margin: 0 auto;">
                                <div class="card-body">
                                    <div class="row justify-content-center align-items-stretch">
                                        <button type="button" class="btn btn-success btn-lg w-100"
                                            onclick="window.location.href='Landing.php'">
                                            <i class="fas fa-circle-arrow-left me-2"></i>
                                            Regresar
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

<script>
    // Al cargar updater.php, proteger la pagina
    document.addEventListener('DOMContentLoaded', function() {
        protectPage();

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

<?php
// Incluye el pie de pagina 
include("includes/footer.php");
?>