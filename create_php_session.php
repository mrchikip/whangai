<?php
require_once 'db.php';

// Establecer header para JSON
header('Content-Type: application/json');

// Verificar que la petición sea POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener datos JSON del cuerpo de la petición
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// Verificar que se recibieron los datos necesarios
if (!isset($data['email']) || !isset($data['uid'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

$email = $data['email'];
$uid = $data['uid'];

try {
    // Verificar que el email tenga un formato válido
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Email inválido']);
        exit;
    }

    // Aquí puedes agregar validaciones adicionales si es necesario
    // Por ejemplo, verificar que el usuario existe en tu base de datos local
    // (esto es opcional dependiendo de tu arquitectura)

    // Crear la sesión PHP
    $_SESSION['user_logged_in'] = true;
    $_SESSION['user_email'] = $email;
    $_SESSION['user_uid'] = $uid;
    $_SESSION['login_time'] = time();

    echo json_encode([
        'success' => true,
        'message' => 'Sesión PHP creada exitosamente',
        'user_email' => $email
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error creando sesión: ' . $e->getMessage()
    ]);
}