<?php

session_start();

// db.php - Conexion a Azure SQL (SQL Server) usando PDO_SQLSRV
// Recomendado: guardar credenciales en variables de entorno (no en el codigo)

// Ejemplos de valores:
// AZURE_SQL_SERVER = "tcp:TU-SERVER.database.windows.net,1433"
// AZURE_SQL_DB     = "TU_DB"
// AZURE_SQL_USER   = "TU_USUARIO"
// AZURE_SQL_PASS   = "TU_PASSWORD"

//$server   = getenv('AZURE_SQL_SERVER') ?: "tcp:euvalleyspring.database.windows.net,1433";
//$database = getenv('AZURE_SQL_DB')     ?: "Valley_Spring";
//$user     = getenv('AZURE_SQL_USER')   ?: "U_RW_Spring";
//$pass     = getenv('AZURE_SQL_PASS')   ?: "tgPG283]wL=F";

// Encrypt recomendado para Azure SQL
//$dsn = "sqlsrv:Server=$server;Database=$database;Encrypt=yes;TrustServerCertificate=no";

//try {
//    $pdo = new PDO($dsn, $user, $pass, [
//        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
//        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

        // Opcional (si tu driver lo soporta): fuerza UTF-8 desde el driver
        // PDO::SQLSRV_ATTR_ENCODING => PDO::SQLSRV_ENCODING_UTF8,

        // Recomendado: preparados reales (no emulados)
//        PDO::ATTR_EMULATE_PREPARES => false,
//    ]);
//} catch (PDOException $e) {
    // En prod, no muestres detalles del error; loguealo
//    die("Error de conexion a Azure SQL: " . $e->getMessage());
//}


//-----------------------------------------------------------------------------------------------------


// Codigo MYSQL
//session_start();

// ============================================
// CONFIGURACIÓN DE BASE DE DATOS
// ============================================

// Database local
$conn2 = mysqli_connect(
    'localhost',
    'root',
    '',
    'puawai'
);

// Database remote tareas
// $conn = mysqli_connect(
//     '44.211.14.38',
//     'lcksgrol8_mrchikip',
//     'Sandia3415',
//     'lcksgrol8_raihana'
// );

// Database cks remote puawai
//$conn2 = mysqli_connect(
//    '44.211.14.38',
//    'lcksgrol8_cks',
//    'Sandia3415',
//    'lcksgrol8_puawai'
//);

// Database remote puawai
// $conn2 = mysqli_connect(
//     '184.154.139.180',
//     'superadminvs_cks',
//     'Sandia3415',
//     'superadminvs_puawai'
// );