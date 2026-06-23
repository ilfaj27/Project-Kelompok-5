<?php
/**
 * ============================================================
 * ALAT - ACTION: DELETE
 * ============================================================
 * File ini handle DELETE (soft delete) data alat
 * Note: Menggunakan SOFT DELETE (Is_Deleted = 1, bukan hard delete)
 * 
 * GET Parameters:
 * - delete_id: int (ID alat yang akan dihapus)
 */

require_once __DIR__ . '/../function/helpers.php';
require_once __DIR__ . '/../function/read.php';

// ============================================================
// HANDLE DELETE REQUEST
// ============================================================

function handleDeleteAlat($conn, $delete_id, $user_info) {
    $delete_id = intval($delete_id);
    
    if ($delete_id <= 0) {
        return [
            'success' => false,
            'message' => 'ID alat tidak valid'
        ];
    }
    
    // Ambil nama alat untuk feedback message
    $alat_data = getDetailData($conn, $delete_id);
    if (!$alat_data) {
        return [
            'success' => false,
            'message' => 'Alat tidak ditemukan'
        ];
    }
    
    $nama_alat_deleted = $alat_data['Nama_Alat'] ?? 'Alat';
    
    // Soft delete (set Is_Deleted = 1)
    $sql = "UPDATE Alat 
            SET Is_Deleted = 1,
                Deleted_By = ?,
                Deleted_Date = GETDATE()
            WHERE ID_Alat = ?";
    
    $params = [
        $user_info['nama'],
        $delete_id
    ];
    
    $result = safeQuery($conn, $sql, $params);
    
    if ($result !== false) {
        return [
            'success' => true,
            'message' => "Alat \"$nama_alat_deleted\" berhasil dihapus!",
            'redirect' => 'index.php'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Gagal menghapus alat: ' . getLastSqlError($conn)
        ];
    }
}

// ============================================================
// HANDLE TOGGLE STATUS (Active/Inactive)
// ============================================================

/**
 * Toggle status alat (active/inactive)
 * @param mixed $conn - Connection
 * @param int $id_alat - ID alat
 * @param int $current_status - Status saat ini (0 atau 1)
 * @param array $user_info - User info [nama, id, role]
 * @return array - Result [success, message]
 */
function handleToggleStatus($conn, $id_alat, $current_status, $user_info) {
    $id_alat = intval($id_alat);
    $current_status = intval($current_status);
    
    if ($id_alat <= 0) {
        return [
            'success' => false,
            'message' => 'ID alat tidak valid'
        ];
    }
    
    // Cek alat ada atau tidak
    $alat_data = getDetailData($conn, $id_alat);
    if (!$alat_data) {
        return [
            'success' => false,
            'message' => 'Alat tidak ditemukan'
        ];
    }
    
    // Toggle status (0 → 1, 1 → 0)
    $new_status = ($current_status == 1) ? 0 : 1;
    
    $sql = "UPDATE Alat 
            SET Status = ?,
                Modified_By = ?,
                Modified_Date = GETDATE()
            WHERE ID_Alat = ?";
    
    $params = [
        $new_status,
        $user_info['nama'],
        $id_alat
    ];
    
    $result = safeQuery($conn, $sql, $params);
    
    if ($result !== false) {
        $msg = ($new_status == 1) ? 'Alat berhasil diaktifkan!' : 'Alat berhasil dinonaktifkan!';
        return [
            'success' => true,
            'message' => $msg,
            'redirect' => 'index.php'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Gagal mengubah status alat: ' . getLastSqlError($conn)
        ];
    }
}

?>