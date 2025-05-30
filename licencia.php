<?php
require_once 'db.php';

$message = '';
$messageType = '';

// Procesar el formulario de activación
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_license'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $licenseKey = trim($_POST['license_key']);

    // Validar campos
    if (empty($name) || empty($email) || empty($licenseKey)) {
        $message = 'Todos los campos son obligatorios.';
        $messageType = 'danger';
    } else {
        // Verificar la licencia
        $verification = verifyLicense($licenseKey);

        if ($verification['valid']) {
            // Establecer la licencia en la sesión
            setLicenseSession($licenseKey, $verification);

            $message = 'Licencia activada correctamente. Bienvenido, ' . htmlspecialchars($name) . '!';
            $messageType = 'success';

            // Opcional: Redirigir después de activar
            // header('Location: index.php');
            // exit;
        } else {
            $message = 'Error al activar la licencia: ' . $verification['error'];
            $messageType = 'danger';
        }
    }
}

// Si ya hay una licencia activa, mostrar información
$currentLicense = checkCurrentLicense();
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activación de Licencia - Whangai</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="icon" href="favicon.svg" type="image/svg+xml">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-key me-2"></i>
                            Activación de Licencia - Whangai
                        </h4>
                    </div>
                    <div class="card-body">

                        <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php endif; ?>

                        <?php if ($currentLicense['valid']): ?>
                        <!-- Mostrar información de licencia activa -->
                        <div class="alert alert-success">
                            <h5><i class="fas fa-check-circle me-2"></i>Licencia Activa</h5>
                            <p class="mb-1"><strong>Estado:</strong> Válida y activa</p>
                            <?php
                                $licenseInfo = getLicenseInfo();
                                if ($licenseInfo['expires']):
                                ?>
                            <p class="mb-1"><strong>Expira:</strong>
                                <?php echo date('d/m/Y H:i', strtotime($licenseInfo['expires'])); ?></p>
                            <?php endif; ?>
                            <?php if ($licenseInfo['customer']): ?>
                            <p class="mb-1"><strong>Cliente:</strong>
                                <?php echo htmlspecialchars($licenseInfo['customer']['Name'] ?? 'N/A'); ?></p>
                            <?php endif; ?>
                            <p class="mb-0"><strong>Machine ID:</strong>
                                <code><?php echo substr(getMachineCode(), 0, 16); ?>...</code></p>
                        </div>

                        <div class="d-grid gap-2">
                            <a href="index.php" class="btn btn-success btn-lg">
                                <i class="fas fa-home me-2"></i>Ir al Panel Principal
                            </a>
                            <form method="POST" class="d-inline">
                                <button type="submit" name="deactivate_license"
                                    class="btn btn-outline-danger btn-sm w-100">
                                    <i class="fas fa-sign-out-alt me-2"></i>Desactivar Licencia
                                </button>
                            </form>
                        </div>
                        <?php else: ?>
                        <!-- Formulario de activación -->
                        <p class="text-muted mb-4">Complete el siguiente formulario para activar su licencia de
                            software.</p>

                        <div class="card card-body bg-light">
                            <form method="POST">
                                <div class="form-group mb-3">
                                    <label for="name" class="form-label">
                                        <i class="fas fa-user me-1"></i>Nombre Completo
                                    </label>
                                    <input type="text" name="name" id="name" class="form-control"
                                        placeholder="Ingrese su nombre completo"
                                        value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>"
                                        required>
                                </div>

                                <div class="form-group mb-3">
                                    <label for="email" class="form-label">
                                        <i class="fas fa-envelope me-1"></i>Correo Electrónico
                                    </label>
                                    <input type="email" name="email" id="email" class="form-control"
                                        placeholder="correo@ejemplo.com"
                                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                                        required>
                                </div>

                                <div class="form-group mb-4">
                                    <label for="license_key" class="form-label">
                                        <i class="fas fa-key me-1"></i>Clave de Licencia
                                    </label>
                                    <input type="text" name="license_key" id="license_key" class="form-control"
                                        placeholder="XXXX-XXXX-XXXX-XXXX" style="font-family: monospace;"
                                        value="<?php echo isset($_POST['license_key']) ? htmlspecialchars($_POST['license_key']) : ''; ?>"
                                        required>
                                    <div class="form-text">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Ingrese la clave de licencia proporcionada por el desarrollador
                                    </div>
                                </div>

                                <div class="d-grid">
                                    <button type="submit" name="activate_license" class="btn btn-primary btn-lg">
                                        <i class="fas fa-shield-alt me-2"></i>Activar Licencia
                                    </button>
                                </div>
                            </form>
                        </div>

                        <div class="mt-4 text-center">
                            <small class="text-muted">
                                <i class="fas fa-desktop me-1"></i>
                                Machine ID: <code><?php echo substr(getMachineCode(), 0, 8); ?>...</code>
                            </small>
                        </div>

                        <hr>

                        <div class="text-center">
                            <h6 class="text-primary">¿Necesita ayuda?</h6>
                            <small class="text-muted">
                                Si tiene problemas con la activación, contacte al soporte técnico con su Machine ID.
                            </small>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.6/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>

<?php
// Procesar desactivación de licencia
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_license'])) {
    clearLicenseSession();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>