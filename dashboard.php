<?php
session_start();
include 'includes/config.php';

if (!isset($_SESSION['login'])) {
    header("Location: login.php");
    exit();
}

// Redirect berdasarkan role
$role = $_SESSION['role'] ?? '';

switch ($role) {
    case 'pemilik':
        header("Location: view_pemilik.php");
        break;
    case 'karyawan':
        header("Location: view_admin.php");
        break;
    case 'customer':
        header("Location: view_customer.php"); // Customer ke landing page
        break;
    default:
        header("Location: login.php");
}
exit();
?>