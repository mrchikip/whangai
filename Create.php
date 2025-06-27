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

                <div class="card card-body">
                    <!-- Formulario para crear nueva tarea -->
                    <form action="save_task.php" method="POST">
                        <div class="form-group mb-3">
                            <input type="text" name="tittle" class="form-control" placeholder="Task Tittle" autofocus>
                        </div>
                        <div class="form-group mb-3">
                            <textarea name="description" rows="2" class="form-control"
                                placeholder="Task Description"></textarea>
                        </div>
                        <input type="submit" class="btn btn-success btn-lg w-100" name="save_task" value="Save Task">
                        <!-- Botón para ir a la página de subida de archivos -->
                        <button type="button" class="btn btn-success btn-lg w-100"
                            onclick="window.location.href='SalesUploader.php'">
                            <i class="fas fa-cloud-upload-alt me-2"></i>
                            Uploader
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-md-8">
                <!-- Tabla que muestra todas las tareas -->
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Tittle</th>
                            <th>Description</th>
                            <th>Created At</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Consulta todas las tareas de la base de datos
                        $query = "SELECT * FROM tasks";
                        $result_tasks = mysqli_query($conn, $query);
                        if (!$result_tasks) {
                            die("Query Failed: " . mysqli_error($conn));
                        }
                        // Muestra cada tarea en una fila de la tabla
                        while ($row = mysqli_fetch_array($result_tasks)) { ?>
                            <tr>
                                <td><?php echo $row['tittle'] ?></td>
                                <td><?php echo $row['description'] ?></td>
                                <td><?php echo $row['createdAt'] ?></td>
                                <td>
                                    <!-- Botón para editar la tarea -->
                                    <a href="edit.php?id=<?php echo $row['id'] ?>" class="btn btn-secondary">
                                        <i class="fas fa-marker"></i>
                                    </a>
                                    <!-- Botón para eliminar la tarea -->
                                    <a href="delete_task.php?id=<?php echo $row['id'] ?>" class="btn btn-danger">
                                        <i class="fas fa-trash-alt"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                    </tbody>
                </table>
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