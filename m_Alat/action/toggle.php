<?php
session_start();
include '../../includes/config.php';
include 'helper.php';

if (isset($_GET['toggle_id'])) {
    // HAPUS intval() karena ID_Alat berupa String (AL0001)
    $toggle_id = $_GET['toggle_id']; 
    $current_status = (int)$_GET['s'];
    $s_baru = ($current_status == 1) ? 0 : 1;
    $nama_user = $_SESSION['nama'] ?? 'Karyawan';

    $result = safeQuery($conn, "UPDATE Alat SET Status=?, Modified_By=?, Modified_Date=GETDATE() WHERE ID_Alat=?", [$s_baru, $nama_user, $toggle_id]);
    
    if ($result !== false) {
        $msg = ($s_baru == 1) ? 'Alat berhasil diaktifkan!' : 'Alat berhasil dinonaktifkan!';
        header("Location: ../index.php?status=success&msg=" . urlencode($msg));
    } else {
        header("Location: ../index.php?status=error&msg=" . urlencode('Gagal mengubah status.'));
    }
} else {
    header("Location: ../index.php");
}
exit();
?>