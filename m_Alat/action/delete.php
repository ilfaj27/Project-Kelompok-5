<?php
session_start();
include '../../includes/config.php';
include 'helper.php';

if (isset($_GET['delete_id'])) {
    // HAPUS intval() karena ID_Alat berupa String (AL0001)
    $delete_id = $_GET['delete_id']; 
    $nama_user = $_SESSION['nama'] ?? 'Karyawan';
    
    $result = safeQuery($conn, "UPDATE Alat SET Is_Deleted=1, Deleted_By=?, Deleted_Date=GETDATE() WHERE ID_Alat=?", [$nama_user, $delete_id]);
    
    if ($result !== false) {
        header("Location: ../index.php?status=success&msg=" . urlencode("Alat berhasil dihapus!"));
    } else {
        header("Location: ../index.php?status=error&msg=" . urlencode('Gagal menghapus alat.'));
    }
} else {
    header("Location: ../index.php");
}
exit();
?>