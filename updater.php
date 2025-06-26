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
            <div class="col-md-4">
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
                                                    <h5 class="card-title text-center mb-3">Fecha de Actualización
                                                    </h5>

                                                    <!-- Descripción centrada -->
                                                    <p class="card-text text-center flex-grow-1 mb-4">
                                                        Seleccion de la fecha de actualización para la carga de
                                                        información y vinculos para limpiar y preparar las tablas de
                                                        ventas y creditos
                                                    </p>

                                                    <!-- Botón centrado en la parte inferior -->
                                                    <div class="mt-auto">
                                                        <button type="button" class="btn btn-success btn-lg w-100"
                                                            onclick="window.location.href='Uploader.php'">
                                                            <i class="fa-solid fa-calendar-days"></i> Prepare Sales
                                                        </button>
                                                        <button type="button" class="btn btn-success btn-lg w-100"
                                                            onclick="window.location.href='Uploader.php'">
                                                            <i class="fa-solid fa-calendar-days"></i> Prepare Credits
                                                        </button>
                                                    </div>
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
// Al cargar create.php, proteger la página
document.addEventListener('DOMContentLoaded', function() {
    protectPage();
});
</script>

<?php
// Incluye el pie de página 
include("includes/footer.php");
?>