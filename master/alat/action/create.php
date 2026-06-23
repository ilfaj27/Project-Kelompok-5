<?php
/**
 * ============================================================
 * ALAT - ACTION: CREATE
 * ============================================================
 * File ini handle INSERT data alat baru
 * Dipanggil dari: form tambah alat di index.php
 * 
 * POST Parameters:
 * - save_alat: flag (untuk check apakah form submit)
 * - nama_alat: string
 * - stok: int
 * - harga_alat: decimal
 * - photo_alat: file upload (foto)
 */

require_once __DIR__ . '/../function/helpers.php';
require_once __DIR__ . '/../function/validation.php';

// ============================================================
// HANDLE CREATE REQUEST
// ============================================================

function handleCreateAlat($conn, $post_data, $files, $user_info) {
    // Check apakah form di-submit
    if (!isset($post_data['save_alat'])) {
        return [
            'success' => false,
            'message' => 'Invalid request'
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
    
    // Upload foto
    $photo_alat = processPhotoUpload($files['photo_alat'] ?? null, null);
    
    if ($photo_alat === false) {
        return [
            'success' => false,
            'message' => 'Foto alat wajib diupload.'
        ];
    }
    
    // Insert ke database
    $sql = "INSERT INTO Alat 
            (Nama_Alat, Stok, Harga_Alat, Photo_Alat, Status, Is_Deleted, Created_By, Created_Date)
            VALUES (?, ?, ?, ?, 1, 0, ?, GETDATE())";
    
    $params = [
        $prepared['nama_alat'],
        $prepared['stok'],
        $prepared['harga_alat'],
        $photo_alat,
        $user_info['nama']
    ];
    
    $result = safeQuery($conn, $sql, $params);
    
    if ($result !== false) {
        return [
            'success' => true,
            'message' => 'Alat baru berhasil ditambahkan!',
            'redirect' => 'index.php'
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Gagal menyimpan data: ' . getLastSqlError($conn)
        ];
    }
}

?>