<?php
include("db.php");
include("includes/auth.php");

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $query = "SELECT * FROM tasks WHERE id = $id";
    $result = mysqli_query($conn, $query);
    if (mysqli_num_rows($result) == 1) {
        $row = mysqli_fetch_array($result);
        $tittle = $row['tittle'];
        $description = $row['description'];
    }
}

if (isset($_POST['update'])) {
    $id = $_GET['id'];
    $tittle = $_POST['tittle'];
    $description = $_POST['description'];
    $query = "UPDATE tasks set tittle = '$tittle', description = '$description' WHERE id = $id";
    mysqli_query($conn, $query);
    $_SESSION['message'] = 'Task Updated Successfully';
    $_SESSION['message_type'] = 'warning';
    header("Location: create.php");
}
?>

<?php include("includes/header.php") ?>

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
            <div class="col-md-4 mx-auto">
                <!-- Mostrar información del usuario y navegación -->


                <div class="card card-body">
                    <h5 class="card-title">Edit Task</h5>
                    <form action="edit.php?id=<?php echo $_GET['id']; ?>" method="POST">
                        <div class="form-group mb-3">
                            <input type="text" name="tittle" value="<?php echo $tittle; ?>" class="form-control"
                                placeholder="Update Tittle">
                        </div>
                        <div class="form-group mb-3">
                            <textarea name="description" rows="2" class="form-control"
                                placeholder="Update Description"><?php echo $description; ?></textarea>
                        </div>
                        <button type="submit" name="update" class="btn btn-success">
                            Update Task
                        </button>
                        <a href="create.php" class="btn btn-secondary ms-2">Cancel</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include("includes/footer.php") ?>