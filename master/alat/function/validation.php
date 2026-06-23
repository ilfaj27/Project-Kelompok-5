<?php
/**
 * ============================================================
 * ALAT - VALIDATION FUNCTIONS
 * ============================================================
 * File ini berisi semua validasi input untuk CRUD alat
 */

require_once __DIR__ . '/helpers.php';

// ============================================================
// VALIDASI INPUT DATA
// ============================================================

/**
 * Validasi input data alat (untuk create & update)
 * @param array $data - Data input yang perlu divalidasi
 *     - nama_alat: string
 *     - stok: string (bisa punya comma)
 *     - harga_alat: string
 *     - id_alat: int (optional, untuk update)
 * @param mixed $conn - Connection object (untuk check duplicate)
 * @return array - Array errors (kosong jika valid)
 *
 * Rules:
 * - Nama alat: required, max 25 karakter, tidak boleh duplicate
 * - Stok: required, angka, min 1, max 9999
 * - Harga: required, angka, min 0
 * - Foto: required (untuk create), optional (untuk update)
 */
function validateAlatInput($data, $conn) {
    $errors = [];
    
    // ---- VALIDASI NAMA ALAT ----
    $nama_alat = trim($data['nama_alat'] ?? '');
    
    if ($nama_alat === '') {
        $errors[] = 'Nama alat wajib diisi.';
    } elseif (strlen($nama_alat) > 25) {
        $errors[] = 'Nama alat maksimal 25 karakter.';
    } else {
        // Check duplicate
        $id_alat = isset($data['id_alat']) ? intval($data['id_alat']) : 0;
        $sql_check = "SELECT ID_Alat FROM Alat WHERE LOWER(Nama_Alat) = LOWER(?) AND ID_Alat <> ? AND Is_Deleted = 0";
        $q_check = safeQuery($conn, $sql_check, [$nama_alat, $id_alat]);
        if ($q_check && safeFetch($q_check)) {
            $errors[] = 'Nama alat sudah terdaftar.';
        }
    }
    
    // ---- VALIDASI STOK ----
    $stok_raw = preg_replace('/[^0-9]/', '', trim($data['stok'] ?? ''));
    
    if ($stok_raw === '' || !is_numeric($stok_raw)) {
        $errors[] = 'Stok harus berupa angka.';
    } else {
        $stok_num = intval($stok_raw);
        if ($stok_num <= 0) {
            $errors[] = 'Stok tidak boleh 0 atau kurang dari 0.';
        } elseif ($stok_num > 9999) {
            $errors[] = 'Stok maksimal 9999.';
        }
    }
    
    // ---- VALIDASI HARGA ----
    $harga_raw = preg_replace('/[^0-9.]/', '', trim($data['harga_alat'] ?? ''));
    
    if ($harga_raw === '' || !is_numeric($harga_raw)) {
        $errors[] = 'Harga harus berupa angka.';
    } else {
        $harga_num = floatval($harga_raw);
        if ($harga_num < 0) {
            $errors[] = 'Harga tidak boleh negatif.';
        }
    }
    
    // ---- VALIDASI FOTO ----
    // Foto wajib jika create (id_alat == 0)
    // Foto optional jika update, tapi jika upload harus valid
    $id_alat = isset($data['id_alat']) ? intval($data['id_alat']) : 0;
    $is_edit_mode = $id_alat > 0;
    
    if (isset($_FILES['photo_alat']) && $_FILES['photo_alat']['error'] === UPLOAD_ERR_OK) {
        // Ada file baru, validasi
        $file = $_FILES['photo_alat'];
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        
        if (!in_array($file_ext, $allowed_ext)) {
            $errors[] = 'Format foto tidak didukung. Gunakan: JPG, PNG, WEBP, GIF.';
        }
        
        if ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'Ukuran foto maksimal 5 MB.';
        }
    } else if (!$is_edit_mode) {
        // Create mode tanpa file = error
        $errors[] = 'Foto alat wajib diupload.';
    }
    
    return $errors;
}

/**
 * Prepare data untuk insert/update
 * @param array $data - Raw input data
 * @return array - Processed data [nama, stok, harga]
 */
function prepareAlatData($data) {
    $prepared = [];
    $prepared['nama_alat'] = trim($data['nama_alat'] ?? '');
    $prepared['stok'] = intval(preg_replace('/[^0-9]/', '', $data['stok'] ?? '0'));
    $prepared['harga_alat'] = number_format(floatval(preg_replace('/[^0-9.]/', '', $data['harga_alat'] ?? '0')), 2, '.', '');
    
    return $prepared;
}

?>