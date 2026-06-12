<?php
session_start();
include '../../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'karyawan') {
    exit('Akses ditolak');
}

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $user = $_SESSION['nama'] ?? 'Karyawan';
    
    // Proses Soft Delete
    $sql = "UPDATE Alat SET Is_Deleted = 1, Deleted_By = ?, Deleted_Date = GETDATE() WHERE ID_Alat = ?";
    $stmt = sqlsrv_query($conn, $sql, array($user, $id));

    if ($stmt) {
        header("Location: ../index.php?status=success&msg=Data berhasil disembunyikan (soft delete)!");
    } else {
        header("Location: ../index.php?status=error&msg=Gagal menghapus data.");
    }
    exit();
} else {
    header("Location: ../index.php");
}
?>