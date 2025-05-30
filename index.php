<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="estilos.css" />
    <title>Login Whangai</title>
    <?php include("includes/auth.php"); ?>
</head>

<body>
    <div class="login">
        <img src="img/idle/1.png" id="monster" alt="" />
        <form class="formulario" id="login-form">
            <label>Usuario</label>
            <input type="email" id="input-usuario" placeholder="Email" autocomplete="off" required />
            <label>Password</label>
            <input type="password" id="input-clave" placeholder="*******" required />
            <button type="submit">Login</button>
            <p id="mensaje-estado"></p>
        </form>
    </div>
    <script src="javascript.js"></script>

    <!-- Script específico para la página de login -->
    <script>
    // Verificar si ya está logueado cuando la página carga
    document.addEventListener('DOMContentLoaded', function() {
        checkIfAlreadyLoggedIn();
    });
    </script>
</body>

</html>