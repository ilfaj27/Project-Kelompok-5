<?php
// auth_helper.php

function cek_akses($role_yang_diizinkan) {
    // Tambahkan baris ini: Jika input bukan array (cuma string), ubah jadi array otomatis
    if (!is_array($role_yang_diizinkan)) {
        $role_yang_diizinkan = [$role_yang_diizinkan];
    }

    // Pastikan session sudah dimulai
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Jika tidak ada session role (belum login)
    if (!isset($_SESSION['role'])) {
        header("Location: login.php"); // Sesuaikan path jika file ada di dalam folder
        exit();
    }

    // Jika role user tidak ada dalam daftar yang diizinkan
    if (!in_array($_SESSION['role'], $role_yang_diizinkan)) {
        echo "<script>
                alert('Akses Ditolak! Anda tidak punya izin untuk mengakses halaman ini.'); 
                window.location='dashboard.php';
              </script>";
        exit();
    }
}

// Fungsi enkripsi password
function enkripsi_pass($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}
?>