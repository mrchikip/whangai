<?php

include("db.php");

if (isset($_POST['save_task'])) {
    $tittle = $_POST['tittle'];
    $description = $_POST['description'];

    $query = "INSERT INTO tasks(tittle, description) VALUES ('$tittle', '$description')";
    $result = mysqli_query($conn, $query);
    if (!$result) {
        die("Query Failed");
    }

    $_SESSION['message'] = 'Task Saved Successfully';
    $_SESSION['message_type'] = 'success';

    header("Location: index.php");
}
