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

// Database cks remote puawai
//$conn2 = mysqli_connect(
//    '44.211.14.38',
//    'lcksgrol8_cks',
//    'Sandia3415',
//    'lcksgrol8_puawai'
//);

// Database remote puawai
$conn2 = mysqli_connect(
    '184.154.139.180',
    'superadminvs_cks',
    'Sandia3415',
    'superadminvs_puawai'
);
