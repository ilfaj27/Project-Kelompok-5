<?php
/**
 * ============================================================
 * ALAT - ACTION: UPDATE
 * ============================================================
 * File ini handle UPDATE data alat yang sudah ada
 * Dipanggil dari: form edit alat di index.php
 * 
 * POST Parameters:
 * - save_alat: flag (untuk check apakah form submit)
 * - id_alat: int (ID alat yang di-edit)
 * - nama_alat: string
 * - stok: int
 * - harga_alat: decimal
 * - photo_alat: file upload (foto - optional)
 * - edit_photo_path: string (path foto lama)
 */

require_once __DIR__ . '/../function/helpers.php';
require_once __DIR__ . '/../function/validation.php';
require_once __DIR__ . '/../function/read.php';

// ============================================================
// HANDLE UPDATE REQUEST
// ============================================================

function handleUpdateAlat($conn, $post_data, $files, $user_info) {
    // Check apakah form di-submit
    if (!isset($post_data['save_alat'])) {
        return [
            'success' => false,
            'message' => 'Invalid request'
        ];
    }
    
    $id_alat = intval($post_data['id_alat'] ?? 0);
    if ($id_alat <= 0) {
        return [
            'success' => false,
            'message' => 'ID alat tidak valid'
        ];
    }
    
    // Ambil data lama
    $old_data = getEditData($conn, $id_alat);
    if (!$old_data) {
        return [
            'success' => false,
            'message' => 'Alat tidak ditemukan'
        ];
    }
    
    // Validasi input
    $errors = validateAlatInput($post_data, $conn);
    
    if (!empty($errors)) {
        return [
            'success' => false,
            'message' => implode(' | ', $errors)
        ];
    }
    
    // Prepare data
    $prepared = prepareAlatData($post_data);
    
    // Handle foto
    $edit_photo_path = trim($post_data['edit_photo_path'] ?? '');
    $edit_data_for_photo = !empty($edit_photo_path) ? ['Photo_Alat' => $edit_photo_path] : null;
    $photo_alat = processPhotoUpload($files['photo_alat'] ?? null, $edit_data_for_photo);
    
    // Jika tidak ada foto sama sekali (new atau old)
    if (empty($photo_alat)) {
        $photo_alat = $old_data['Photo_Alat'] ?? 'asset/image/default.png';
    }
    
    // Update database
    $sql = "UPDATE Alat 
            SET Nama_Alat = ?,
                Stok = ?,
                Harga_Alat = ?,
                Photo_Alat = ?,
                Modified_By = ?,
                Modified_Date = GETDATE()
            WHERE ID_Alat = ?";
    
    $params = [
        $prepared['nama_alat'],
        $prepared['stok'],
        $prepared['harga_alat'],
        $photo_alat,
        $user_info['nama'],
        $id_alat
    ];
    
    $result = safeQuery($conn, $sql, $params);
    
    if ($result !== false) {
        return [
            'success' => true,
            'message' => 'Data alat berhasil diperbarui!',
            'redirect' => 'index.php'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Gagal memperbarui data: ' . getLastSqlError($conn)
        ];
    }
}

?>