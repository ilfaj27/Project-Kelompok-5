<?php
$serverName = "localhost"; 
$connectionOptions = array(
    "Database" => "Hoopball",
    "Uid" => "", 
    "PWD" => "",
    "TrustServerCertificate" => true
);

$conn = sqlsrv_connect($serverName, $connectionOptions);

if (!$conn) {
    die(print_r(sqlsrv_errors(), true));
}
?>