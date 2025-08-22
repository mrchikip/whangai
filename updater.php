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
            <div class="col-md-12">
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

                <div class="container mt-4">
                    <div class="row justify-content-center">
                        <div class="col-12">
                            <div class="card shadow">
                                <div class="card-body">
                                    <!-- Título mejorado -->
                                    <div class="text-center mb-4">
                                        <h1 class="h2 fw-bold text-success mb-3">Actualización de Base de Datos</h1>
                                        <p class="text-muted">Selecciona la herramienta que deseas utilizar</p>
                                    </div>

                                    <div class="row justify-content-center align-items-stretch g-4">
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

                                    <!-- Botón de regreso -->
                                    <div class="row justify-content-center mt-4">
                                        <div class="col-lg-6 col-md-8 col-sm-12">
                                            <button type="button" class="btn btn-success btn-lg w-100"
                                                onclick="window.location.href='Landing.php'">
                                                <i class="fas fa-circle-arrow-left me-2"></i>
                                                Regresar al Panel Principal
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Información adicional -->
                                    <div class="text-center mt-4">
                                        <div class="alert alert-info" role="alert">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>Consejo:</strong> Utiliza estas herramientas para mantener
                                            tu base de datos siempre actualizada y optimizada.
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

        // Aplicar efectos de hover mejorado a las cards (igual que en landing)
        const cards = document.querySelectorAll('.card.h-100');
        cards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                if (!this.querySelector('button[disabled]')) {
                    this.style.transform = 'translateY(-4px) scale(1.02)';
                    this.style.transition = 'all 0.3s ease';
                }
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0) scale(1)';
            });
        });
    });
</script>

<?php
// Incluye el pie de pagina 
include("includes/footer.php");
?>