<?php

session_start();

// ============================================
// CONFIGURACIÓN DE BASE DE DATOS
// ============================================

// Database local
//$conn = mysqli_connect(
//    'localhost',
//    'root',
//    '',
//    'matua'
//    );

// Database remote tareas
$conn = mysqli_connect(
    '44.211.14.38',
    'lcksgrol8_mrchikip',
    'Sandia3415',
    'lcksgrol8_raihana'
);

// Database remote puawai
$conn2 = mysqli_connect(
    '44.211.14.38',
    'lcksgrol8_cks',
    'Sandia3415',
    'lcksgrol8_puawai'
);

// ============================================
// CONFIGURACIÓN DE CRYPTOLENS.IO
// ============================================

// Configuración de Cryptolens
define('CRYPTOLENS_BASE_URL', 'https://api.cryptolens.io/api/');
define('CRYPTOLENS_ACCESS_TOKEN', 'WyIxMDg1MjYwMjgiLCJwSEpiV2tTM3lsQmRwZ01YS0FzdEtPb0FTeDVzM0EvaW9BQW44bW9ZIl0='); // Reemplaza con tu token real
define('CRYPTOLENS_PRODUCT_ID', '30099'); // Reemplaza con tu Product ID real
define('CRYPTOLENS_RSA_PUBLIC_KEY', 'w9LgkUPjtFJgb8Ls1IojqgFXj6Omk82XmdCE3UPb1idHrVfJ90HtFhFxSq+kKk10JZmBKIFYOXiBycdrnBwQj0VcbMw6pXxYOIpE3PCpeyi7P1Z9bYDgdRkJ0a3z9jPoAOwQAf64UZnI+fYufUu9LfCMKCA4A86yedEb9x82H+ubjsnEuQwVJEhoVtQQ7xvUgiD8jZWVduzwT3CkoqYHr6GECvExnBCXQtD8LCKfaoN1MoNH1iBxSDi8qXP7xR3pjPpwk+0J/gAIrNlI5xAIw7oeX4FbMlDuuQhFjkcY492+sNLBjyv/TVVHDYXFK05HEJa/usUieyRXf0H184ONxQ==AQAB'); // Reemplaza con tu clave pública RSA real

// Configuraciones adicionales de Cryptolens
define('CRYPTOLENS_CACHE_TIME', 86400); // 24 horas para re-verificación
define('CRYPTOLENS_GRACE_PERIOD', 7200); // 2 horas de período de gracia
define('CRYPTOLENS_ENABLE_MACHINE_BINDING', true);

// ============================================
// FUNCIONES DE CRYPTOLENS
// ============================================

/**
 * Genera un código único de máquina basado en características del servidor
 */
function getMachineCode()
{
    $identifiers = [
        $_SERVER['SERVER_NAME'] ?? '',
        $_SERVER['HTTP_HOST'] ?? '',
        $_SERVER['SERVER_ADDR'] ?? '',
        php_uname('n'), // nombre del host
        getcwd() // directorio actual
    ];

    return hash('sha256', implode('|', $identifiers));
}

/**
 * Verifica una licencia con Cryptolens.io
 */
function verifyLicense($licenseKey)
{
    $url = CRYPTOLENS_BASE_URL . 'key/Activate';

    $postData = [
        'token' => CRYPTOLENS_ACCESS_TOKEN,
        'ProductId' => CRYPTOLENS_PRODUCT_ID,
        'Key' => $licenseKey,
        'Sign' => true,
        'MachineCode' => getMachineCode()
    ];

    $options = [
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\n",
            'method' => 'POST',
            'content' => http_build_query($postData),
            'timeout' => 10 // timeout de 10 segundos
        ]
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    if ($result === FALSE) {
        return ['valid' => false, 'error' => 'Error de conexión con el servidor de licencias'];
    }

    $response = json_decode($result, true);

    if (!$response || !isset($response['result'])) {
        return ['valid' => false, 'error' => 'Respuesta inválida del servidor'];
    }

    if ($response['result'] == 0) {
        // Licencia válida - verificar detalles adicionales
        $licenseData = $response['license'];

        // Verificar si la licencia ha expirado
        if (isset($licenseData['Expires']) && $licenseData['Expires'] != null) {
            $expiryDate = new DateTime($licenseData['Expires']);
            $now = new DateTime();
            if ($now > $expiryDate) {
                return ['valid' => false, 'error' => 'La licencia ha expirado'];
            }
        }

        // Verificar si la licencia está bloqueada
        if ($licenseData['Blocked']) {
            return ['valid' => false, 'error' => 'La licencia ha sido bloqueada'];
        }

        return [
            'valid' => true,
            'data' => $licenseData,
            'expires' => isset($licenseData['Expires']) ? $licenseData['Expires'] : null,
            'customer' => isset($licenseData['Customer']) ? $licenseData['Customer'] : null
        ];
    } else {
        $errorMessages = [
            1 => 'Clave de licencia no encontrada',
            2 => 'Clave de licencia bloqueada',
            3 => 'Clave de licencia expirada',
            4 => 'Límite de activaciones alcanzado',
            5 => 'Producto no encontrado'
        ];

        $errorMessage = isset($errorMessages[$response['result']])
            ? $errorMessages[$response['result']]
            : 'Error de validación de licencia (Código: ' . $response['result'] . ')';

        return ['valid' => false, 'error' => $errorMessage];
    }
}

/**
 * Verifica si la licencia actual es válida
 */
function checkCurrentLicense()
{
    // Verificar licencia existente en sesión
    if (isset($_SESSION['license_verified']) && $_SESSION['license_verified']) {
        // Re-verificar la licencia periódicamente
        if (
            !isset($_SESSION['last_license_check']) ||
            (time() - $_SESSION['last_license_check']) > CRYPTOLENS_CACHE_TIME
        ) {

            $verification = verifyLicense($_SESSION['license_key']);

            if ($verification['valid']) {
                $_SESSION['last_license_check'] = time();
                $_SESSION['license_expires'] = $verification['expires'];
                $_SESSION['license_customer'] = $verification['customer'];
                return ['valid' => true, 'data' => $verification];
            } else {
                // Limpiar sesión si la licencia ya no es válida
                clearLicenseSession();
                return ['valid' => false, 'error' => $verification['error']];
            }
        } else {
            return ['valid' => true, 'cached' => true];
        }
    }

    return ['valid' => false, 'error' => 'No hay licencia activa'];
}

/**
 * Establece una licencia válida en la sesión
 */
function setLicenseSession($licenseKey, $licenseData)
{
    $_SESSION['license_key'] = $licenseKey;
    $_SESSION['license_verified'] = true;
    $_SESSION['license_expires'] = $licenseData['expires'];
    $_SESSION['license_customer'] = $licenseData['customer'];
    $_SESSION['last_license_check'] = time();
}

/**
 * Limpia la información de licencia de la sesión
 */
function clearLicenseSession()
{
    unset($_SESSION['license_key']);
    unset($_SESSION['license_verified']);
    unset($_SESSION['license_expires']);
    unset($_SESSION['license_customer']);
    unset($_SESSION['last_license_check']);
}

// ============================================
// FUNCIONES AUXILIARES
// ============================================

/**
 * Obtiene información de la licencia actual
 */
function getLicenseInfo()
{
    if (!isset($_SESSION['license_verified']) || !$_SESSION['license_verified']) {
        return null;
    }

    return [
        'expires' => $_SESSION['license_expires'] ?? null,
        'customer' => $_SESSION['license_customer'] ?? null,
        'last_check' => $_SESSION['last_license_check'] ?? null
    ];
}

/**
 * Verifica si la licencia expira pronto (dentro de los próximos 7 días)
 */
function isLicenseExpiringSoon()
{
    $licenseInfo = getLicenseInfo();
    if (!$licenseInfo || !$licenseInfo['expires']) {
        return false;
    }

    $expiryDate = new DateTime($licenseInfo['expires']);
    $now = new DateTime();
    $interval = $now->diff($expiryDate);

    return ($interval->days <= 7 && $expiryDate > $now);
}