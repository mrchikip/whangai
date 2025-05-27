<?php

session_start();

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

$conn2 = mysqli_connect(
    '44.211.14.38',
    'lcksgrol8_cks',
    'Sandia3415',
    'lcksgrol8_puawai'
);
