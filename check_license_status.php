<?php
require_once 'db.php';

// Establecer header para JSON
header('Content-Type: application/json');

// Verificar si hay sesión PHP activa
$user_logged_in = isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] === true;

// Verificar estado de la licencia
$license_status = checkCurrentLicense();
$license_valid = $license_status['valid'];

// Obtener información adicional de la licencia si está disponible
$license_info = null;
if ($license_valid) {
    $license_info = getLicenseInfo();
}

// Responder con el estado
echo json_encode([
    'user_logged_in' => $user_logged_in,
    'license_valid' => $license_valid,
    'license_info' => $license_info,
    'license_error' => !$license_valid ? ($license_status['error'] ?? 'Licencia no válida') : null
]);
