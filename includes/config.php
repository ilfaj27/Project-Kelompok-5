<?php
$serverName = "localhost"; 
$connectionOptions = [
    "Database" => "Hoopball",
    "Uid" => "sa", 
    "PWD" => "F@nsdolalynomor1",
    "TrustServerCertificate" => true
];

$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}
?>