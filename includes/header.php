<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Whangai</title>
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-4Q6Gf2aSP4eDXB8Miphtr37CMZZQ5oXLH2yaXMJ2w8e2ZtHTl7GptT4jmndRuHDT" crossorigin="anonymous">
    <!-- FontAwesome 6 -->
    <script src="https://kit.fontawesome.com/4ca1d3174b.js" crossorigin="anonymous"></script>
</head>

<body>

    <nav class="navbar navbar-expand-lg bg-dark border border-primary-subtle">
        <div class="container-fluid">
            <a href="index.php" class="navbar-brand text-white">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" width="24" height="24" viewBox="0 0 512 512"
                    class="d-inline-block align-text-top me-2">
                    <path
                        d="M512 32c0 113.6-84.6 207.5-194.2 222c-7.1-53.4-30.6-101.6-65.3-139.3C290.8 46.3 364 0 448 0l32 0c17.7 0 32 14.3 32 32zM0 96C0 78.3 14.3 64 32 64l32 0c123.7 0 224 100.3 224 224l0 32 0 160c0 17.7-14.3 32-32 32s-32-14.3-32-32l0-160C100.3 320 0 219.7 0 96z" />
                </svg>
                Whangai
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarText"
                aria-controls="navbarText" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse d-grid gap-2 d-md-flex justify-content-md-end" id="navbarText">
                <span class="navbar-text">
                    <small class="text-white me-md-2">Logged in as: <span id="user-email"></span></small>
                    <!-- <div> -->
                    <a href="create.php" class="btn btn-sm btn-primary me-md-2">Back to Tasks</a>
                    <button onclick="logout()" class="btn btn-sm btn-secondary">Logout</button>
                    <!-- </div> -->
                </span>
            </div>
        </div>
    </nav>