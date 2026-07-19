<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Memeriksa status login
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    
    // Mendeteksi nama folder proyek Anda secara otomatis (misal: /HoopBall)
    $project_dir = str_replace($_SERVER['DOCUMENT_ROOT'], '', str_replace('\\', '/', dirname(__DIR__)));
    
    // Mengarahkan ke halaman login menggunakan path yang aman
    header("Location: " . $project_dir . "/login/login.php");
    exit();
}