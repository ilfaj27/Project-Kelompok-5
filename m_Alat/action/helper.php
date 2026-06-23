<?php
// Fungsi eksekusi query aman
function safeQuery($conn, $sql, $params = []) {
    $stmt = empty($params) ? sqlsrv_query($conn, $sql) : sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("[ALAT-ERROR] Query: " . $sql);
        return false;
    }
    return $stmt;
}

function safeFetch($stmt) {
    if ($stmt === false || $stmt === null) return false;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function getLastSqlError($conn) {
    $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
    if (!empty($errors) && isset($errors[0]['message'])) return $errors[0]['message'];
    return 'Unknown database error';
}

function getPhotoUrl($photo_path) {
    if (empty($photo_path)) return '';
    $path = str_replace('../', '', $photo_path);
    $path = ltrim($path, '/');
    return '../' . $path; // Kembali 1 folder dari m_Alat/index.php
}

// Perhatikan path upload kita naik 2 tingkat (../../) karena dipanggil dari dalam folder action/
function processPhotoUpload($file, $edit_data = null) {
    $upload_dir = '../../asset/image/'; 
    if (!isset($file) || empty($file['name'])) {
        if ($edit_data && !empty($edit_data['Photo_Alat'])) return $edit_data['Photo_Alat'];
        return false;
    }
    if (!is_dir($upload_dir)) @mkdir($upload_dir, 0755, true);
    
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($file_ext, $allowed_ext) || $file['size'] > 5 * 1024 * 1024) {
        return ($edit_data ? $edit_data['Photo_Alat'] : '');
    }
    
    $new_file_name = 'alat_' . time() . '_' . uniqid() . '.' . $file_ext;
    if (move_uploaded_file($file['tmp_name'], $upload_dir . $new_file_name)) {
        return 'asset/image/' . $new_file_name; // Disimpan ke DB tanpa ../../
    }
    return ($edit_data && !empty($edit_data['Photo_Alat'])) ? $edit_data['Photo_Alat'] : '';
}
?>