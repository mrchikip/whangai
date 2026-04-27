<?php
echo "PHP: " . PHP_VERSION . "<br>";
echo "PDO drivers: " . implode(", ", PDO::getAvailableDrivers()) . "<br>";
echo "Extension sqlsrv: " . (extension_loaded('sqlsrv') ? 'SI' : 'NO') . "<br>";
echo "Extension pdo_sqlsrv: " . (extension_loaded('pdo_sqlsrv') ? 'SI' : 'NO') . "<br>";