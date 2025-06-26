<?php

/**
 * check_records.php - Verificar cantidad de registros antes de eliminar
 * Versión corregida para sistema dual Firebase + PHP
 */

// Solo incluir db.php (NO auth.php para evitar HTML output)
include("db.php");

// Establecer header para JSON al inicio
header('Content-Type: application/json');

// Verificar que sea una petición AJAX POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Para sistema dual Firebase+PHP, usar verificación alternativa
// En lugar de verificar $_SESSION['user_logged_in'], verificamos solo CSRF
if (!isset($_SESSION['csrf_token'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión no válida']);
    exit;
}

// Obtener datos JSON del body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validar datos requeridos
if (!isset($data['action']) || !isset($data['date']) || !isset($data['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

// Validar token CSRF
if (!hash_equals($_SESSION['csrf_token'], $data['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Token de seguridad inválido']);
    exit;
}

// Sanitizar datos
$action = strtolower(trim($data['action']));
$date = trim($data['date']);

// Validar acción
if (!in_array($action, ['sales', 'credits'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
    exit;
}

// Validar formato de fecha
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Formato de fecha inválido']);
    exit;
}

try {
    // Verificar conexión a la base de datos
    if (!$conn2) {
        throw new Exception('Error de conexión a la base de datos');
    }

    $count = 0;

    if ($action === 'sales') {
        // Contar registros en tabla sales
        $countQuery = "SELECT COUNT(*) as total FROM `sales` WHERE `InvoiceDate` >= ?";
        $stmt = $conn2->prepare($countQuery);

        if (!$stmt) {
            throw new Exception('Error preparando consulta de sales: ' . $conn2->error);
        }

        $stmt->bind_param("s", $date);

        if (!$stmt->execute()) {
            throw new Exception('Error ejecutando consulta de sales: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = (int)$row['total'];
        $stmt->close();
    } elseif ($action === 'credits') {
        // Contar registros en tabla credits
        $countQuery = "SELECT COUNT(*) as total FROM `credits` WHERE `CreditDate` >= ?";
        $stmt = $conn2->prepare($countQuery);

        if (!$stmt) {
            throw new Exception('Error preparando consulta de credits: ' . $conn2->error);
        }

        $stmt->bind_param("s", $date);

        if (!$stmt->execute()) {
            throw new Exception('Error ejecutando consulta de credits: ' . $stmt->error);
        }

        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $count = (int)$row['total'];
        $stmt->close();
    }

    // Log de auditoría (opcional)
    error_log("AJAX verificación: $count registros de $action desde $date");

    // Respuesta exitosa
    echo json_encode([
        'success' => true,
        'count' => $count,
        'action' => $action,
        'date' => $date,
        'message' => "Se encontraron $count registros"
    ]);
} catch (Exception $e) {
    error_log("Error en check_records.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al verificar registros: ' . $e->getMessage()
    ]);
}
