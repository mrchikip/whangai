<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Whangai</title>
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <!-- FontAwesome 6 -->
    <script src="https://kit.fontawesome.com/4ca1d3174b.js" crossorigin="anonymous"></script>
</head>

<body>

    <nav class="navbar navbar-expand-lg bg-body-tertiary">
        <div class="container-fluid">
            <a href="index.php" class="navbar-brand">Whangai</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarText"
                aria-controls="navbarText" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse d-grid gap-2 d-md-flex justify-content-md-end" id="navbarText">
                <span class="navbar-text">
                    <small class="text-muted me-md-2">Logged in as: <span id="user-email"></span></small>
                    <!-- <div> -->
                    <a href="create.php" class="btn btn-sm btn-outline-primary me-md-2">Back to Tasks</a>
                    <button onclick="logout()" class="btn btn-sm btn-outline-secondary">Logout</button>
                    <!-- </div> -->
                </span>
            </div>
        </div>
    </nav>