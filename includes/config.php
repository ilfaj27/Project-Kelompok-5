<?php
$serverName = "localhost"; 
$connectionOptions = [
    "Database" => "hoopball",
    "Uid" => "sa", 
    "PWD" => "F@nsdolalynomor1",
    "TrustServerCertificate" => true
];

$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}
?>


//Ini punya kalian kalo kalian engga bisa jalanin program karna ada notif enga bisa jalan

<!-- <?php
$serverName = "."; 
$connectionOptions = [
    "Database" => "hoopball",
    "Uid" => "", 
    "PWD" => "",
    "TrustServerCertificate" => true
];

$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}
?> -->