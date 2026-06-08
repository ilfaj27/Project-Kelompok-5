<?php
session_start();
include 'includes/config.php';

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

if ($role == 'customer') {
    include 'view_customer.php';
} else {
    include 'view_admin.php'; // Digunakan oleh Karyawan Dan Pemilikk
}
?>
