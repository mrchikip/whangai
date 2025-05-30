<?php
require_once 'db.php';

// Limpiar todas las variables de sesión
$_SESSION = array();

// Destruir la sesión
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

// Responder con éxito
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Sesión cerrada']);