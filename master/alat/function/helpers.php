<?php
/**
 * ============================================================
 * ALAT - HELPER FUNCTIONS
 * ============================================================
 * File ini berisi function-function utility untuk master alat:
 * - safeQuery: Execute query dengan error handling
 * - safeFetch: Fetch array result dengan safety check
 * - getLastSqlError: Ambil pesan error dari DB
 * - getPhotoUrl: Normalkan path foto
 * - getUploadDirectory: Setup upload directory
 * - processPhotoUpload: Handle upload & validasi foto
 * - rupiah: Format angka ke Rp
 */

// ============================================================
// 1. DATABASE QUERY HELPERS
// ============================================================

/**
 * Execute query dengan error handling
 * @param mixed $conn - Connection object
 * @param string $sql - SQL query
 * @param array $params - Parameters untuk prepared statement
 * @return mixed - Statement object atau FALSE
 */
function safeQuery($conn, $sql, $params = []) {
    $stmt = empty($params) ? sqlsrv_query($conn, $sql) : sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
        error_log("[ALAT-ERROR] SQL Error: " . print_r($errors, true));
        error_log("[ALAT-ERROR] Query: " . $sql);
        return false;
    }
    return $stmt;
}

/**
 * Fetch single row dari statement dengan safety check
 * @param mixed $stmt - Statement object
 * @return array|false - Array hasil atau FALSE jika no data
 */
function safeFetch($stmt) {
    if ($stmt === false || $stmt === null) return false;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

/**
 * Ambil pesan error terakhir dari database
 * @param mixed $conn - Connection object
 * @return string - Error message
 */
function getLastSqlError($conn) {
    $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
    if (!empty($errors) && isset($errors[0]['message'])) {
        return $errors[0]['message'];
    }
    return 'Unknown database error';
}

// ============================================================
// 2. FILE UPLOAD HELPERS
// ============================================================

/**
 * Normalize path foto (handle relative & absolute paths)
 * @param string $photo_path - Path foto
 * @return string - Normalized path
 */
function getPhotoUrl($photo_path) {
    if (empty($photo_path)) return '';
    $path = str_replace('../../', '', $photo_path);
    $path = ltrim($path, '/');
    return '../../' . $path;
}

/**
 * Setup & validasi upload directory
 * @return string - Path ke upload directory
 */
function getUploadDirectory() {
    $upload_dir = '../../asset/image/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    return $upload_dir;
}

/**
 * Process upload foto dengan validasi
 * @param array $file - $_FILES['photo_alat']
 * @param array|null $edit_data - Data lama (jika edit mode)
 * @return string|false - Path foto atau FALSE jika gagal
 *
 * Rules:
 * - Extension: jpg, jpeg, png, webp, gif
 * - Max size: 5 MB
 * - Naming: alat_[timestamp]_[uniqid].[ext]
 */
function processPhotoUpload($file, $edit_data = null) {
    $upload_dir = getUploadDirectory();
    
    // Jika tidak ada file baru dan edit mode
    if (!isset($file) || empty($file['name'])) {
        if ($edit_data && !empty($edit_data['Photo_Alat'])) {
            return $edit_data['Photo_Alat'];
        }
        return false;
    }
    
    // Validasi direktori
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    
    // Validasi extension
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($file_ext, $allowed_ext)) {
        return ($edit_data ? $edit_data['Photo_Alat'] : '');
    }
    
    // Validasi ukuran (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        return ($edit_data ? $edit_data['Photo_Alat'] : '');
    }
    
    // Generate nama file unik
    $new_file_name = 'alat_' . time() . '_' . uniqid() . '.' . $file_ext;
    $target_path = $upload_dir . $new_file_name;
    
    // Upload file
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return 'asset/image/' . $new_file_name;
    }
    
    return ($edit_data && !empty($edit_data['Photo_Alat'])) ? $edit_data['Photo_Alat'] : '';
}

// ============================================================
// 3. FORMATTING HELPERS
// ============================================================

/**
 * Format angka ke format Rupiah
 * @param float|int $n - Angka
 * @return string - Formatted string (Rp 1.234.567)
 */
function rupiah($n) {
    return 'Rp ' . number_format($n, 0, ',', '.');
}

/**
 * Format harga dengan validasi
 * @param string $harga_raw - Input harga (bisa punya karakter non-angka)
 * @return string - Harga numerik (format: 123456.78)
 */
function formatHarga($harga_raw) {
    $harga = floatval(preg_replace('/[^0-9.]/', '', $harga_raw));
    return number_format($harga, 2, '.', '');
}

// ============================================================
// 4. DATA COUNTING HELPERS
// ============================================================

/**
 * Hitung total record dari query dengan kondisi
 * @param mixed $conn - Connection object
 * @param string $condition - WHERE clause condition (tanpa "WHERE")
 * @param array $params - Parameters untuk prepared statement
 * @return int - Total record
 */
function countData($conn, $condition = '1=1', $params = []) {
    $sql = "SELECT COUNT(*) as t FROM Alat WHERE $condition";
    $result = safeQuery($conn, $sql, $params);
    if ($result) {
        $row = safeFetch($result);
        return $row['t'] ?? 0;
    }
    return 0;
}

?>