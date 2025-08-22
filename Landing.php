<?php
// Incluye la conexión a la base de datos
include("db.php");
// Incluye la protección de autenticación de usuario
include("includes/auth.php");
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
            <div class="col-md-12">
                <?php
                // Muestra mensaje de alerta si existe en sesión
                if (isset($_SESSION['message'])) { ?>
                <div class="alert alert-<?= $_SESSION['message_type'] ?> alert-dismissible fade show" role="alert">
                    <?= $_SESSION['message'] ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close">
                    </button>
                </div>
                <?php session_unset();
                } ?>

                <div class="container mt-4">
                    <div class="row justify-content-center">
                        <div class="col-12">
                            <div class="card shadow">
                                <div class="card-body">
                                    <!-- Título mejorado -->
                                    <div class="text-center mb-4">
                                        <h1 class="h2 fw-bold text-success mb-3">Panel de Control</h1>
                                        <p class="text-muted">Selecciona el módulo que deseas utilizar</p>
                                    </div>

                                    <div class="row justify-content-center align-items-stretch g-4">
                                        <!-- Primera tarjeta -->
                                        <div class="col-lg-6 col-md-6 col-sm-12">
                                            <div class="card h-100">
                                                <div class="card-body d-flex flex-column text-center">
                                                    <!-- Icono -->
                                                    <div class="mb-3">
                                                        <i class="fas fa-cloud-upload-alt"
                                                            style="font-size: 3.5rem; color: #198754;"></i>
                                                    </div>

                                                    <!-- Título centrado -->
                                                    <h5 class="card-title text-center mb-3">Actualizar Base de Datos
                                                    </h5>

                                                    <!-- Descripción centrada -->
                                                    <p class="card-text text-center flex-grow-1 mb-4">
                                                        Acceso al módulo para carga de información actualizada a la base
                                                        de datos. Mantén tu información siempre actualizada.
                                                    </p>

                                                    <!-- Botón centrado en la parte inferior -->
                                                    <div class="mt-auto">
                                                        <button type="button" class="btn btn-success btn-lg w-100"
                                                            onclick="window.location.href='updater.php'">
                                                            <i class="fas fa-cloud-upload-alt me-2"></i>
                                                            Actualizar
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Segunda tarjeta -->
                                        <div class="col-lg-6 col-md-6 col-sm-12">
                                            <div class="card h-100">
                                                <div class="card-body d-flex flex-column text-center">
                                                    <!-- Icono -->
                                                    <div class="mb-3">
                                                        <i class="fa-solid fa-dolly"
                                                            style="font-size: 3.5rem; color: #198754;"></i>
                                                    </div>

                                                    <!-- Título centrado -->
                                                    <h5 class="card-title text-center mb-3">Requerimientos y Solicitudes
                                                    </h5>

                                                    <!-- Descripción centrada -->
                                                    <p class="card-text text-center flex-grow-1 mb-4">
                                                        Acceso al módulo para solicitudes de servicio, cambios, o
                                                        requerimientos. Gestiona todas tus peticiones en un lugar.
                                                    </p>

                                                    <!-- Botón centrado en la parte inferior -->
                                                    <div class="mt-auto">
                                                        <button type="button" class="btn btn-success btn-lg w-100"
                                                            onclick="window.location.href='#'" disabled>
                                                            <i class="fa-solid fa-dolly me-2"></i>
                                                            Próximamente
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Tercera tarjeta -->
                                        <div class="col-lg-6 col-md-6 col-sm-12">
                                            <div class="card h-100">
                                                <div class="card-body d-flex flex-column text-center">
                                                    <!-- Icono -->
                                                    <div class="mb-3">
                                                        <i class="fas fa-chart-bar"
                                                            style="font-size: 3.5rem; color: #198754;"></i>
                                                    </div>

                                                    <!-- Título centrado -->
                                                    <h5 class="card-title text-center mb-3">Reportes y Analytics</h5>

                                                    <!-- Descripción centrada -->
                                                    <p class="card-text text-center flex-grow-1 mb-4">
                                                        Visualiza estadísticas y genera reportes detallados.
                                                        Toma decisiones basadas en datos actualizados.
                                                    </p>

                                                    <!-- Botón centrado en la parte inferior -->
                                                    <div class="mt-auto">
                                                        <button type="button" class="btn btn-success btn-lg w-100"
                                                            onclick="window.location.href='#'" disabled>
                                                            <i class="fas fa-chart-bar me-2"></i>
                                                            Próximamente
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Cuarta tarjeta -->
                                        <div class="col-lg-6 col-md-6 col-sm-12">
                                            <div class="card h-100">
                                                <div class="card-body d-flex flex-column text-center">
                                                    <!-- Icono -->
                                                    <div class="mb-3">
                                                        <i class="fas fa-cog"
                                                            style="font-size: 3.5rem; color: #198754;"></i>
                                                    </div>

                                                    <!-- Título centrado -->
                                                    <h5 class="card-title text-center mb-3">Configuración</h5>

                                                    <!-- Descripción centrada -->
                                                    <p class="card-text text-center flex-grow-1 mb-4">
                                                        Personaliza la aplicación según tus necesidades.
                                                        Configura parámetros y preferencias del sistema.
                                                    </p>

                                                    <!-- Botón centrado en la parte inferior -->
                                                    <div class="mt-auto">
                                                        <button type="button" class="btn btn-success btn-lg w-100"
                                                            onclick="window.location.href='#'" disabled>
                                                            <i class="fas fa-cog me-2"></i>
                                                            Próximamente
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Información adicional -->
                                    <div class="text-center mt-4">
                                        <div class="alert alert-info" role="alert">
                                            <i class="fas fa-info-circle me-2"></i>
                                            <strong>Consejo:</strong> Utiliza el módulo de actualización para mantener
                                            tu base de datos siempre actualizada.
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
// Al cargar la página, proteger y aplicar efectos
document.addEventListener('DOMContentLoaded', function() {
    protectPage();

    // Aplicar efectos de hover mejorado a las cards
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
// Incluye el pie de página 
include("includes/footer.php");
?>