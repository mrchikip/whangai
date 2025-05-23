<?php

include("db.php");

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
    header("Location: index.php");
}

?>

<?php include("includes/header.php") ?>

<div class="container p-4">
    <div class="row">
        <div class="col-md-4 mx-auto">
            <div class="card card-body">
                <form action="edit.php?id=<?php echo $_GET['id']; ?>" method="POST">
                    <div class="form-group">
                        <input type="text" name="tittle" value="<?php echo $tittle; ?>" class="form-control"
                            placeholder="Update Tittle">
                    </div>
                    <div class="form-group">
                        <textarea name="description" rows="2" class="form-control" placeholder="Update Description">
                        <?php echo $description; ?></textarea>
                    </div>
                    <button type="submit" name="update" class="btn btn-success">
                        Update Task
                    </button>
            </div>
        </div>
    </div>
</div>

<?php include("includes/footer.php") ?>