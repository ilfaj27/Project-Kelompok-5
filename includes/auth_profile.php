<?php

$role = $_SESSION['role'];
$nama = $_SESSION['nama'] ?? 'USER';

// FIX: Ambil foto profil dari database dengan kolom yang benar
$profile_photo = '';
$id_karyawan_session = $_SESSION['id_karyawan'] ?? $_SESSION['id_akun'] ?? '';

if (!empty($id_karyawan_session)) {
    $stmt_photo = sqlsrv_query($conn, "EXEC dbo.sp_GetKaryawanPhoto ?", array($id_karyawan_session));
    if ($stmt_photo !== false) {
        $row_photo = sqlsrv_fetch_array($stmt_photo, SQLSRV_FETCH_ASSOC);
        if ($row_photo && !empty($row_photo['Photo_Profile'])) {
            $profile_photo = $row_photo['Photo_Profile'];
            $_SESSION['Photo_Profile'] = $profile_photo;
        }
    }
}

// Fallback ke session jika query gagal
if (empty($profile_photo)) {
    $profile_photo = $_SESSION['Photo_Profile'] ?? '';
}

// Cek file exists dan sesuaikan path (customer.php ada di folder master/)
$sidebar_photo = '';
if (!empty($profile_photo)) {
    if (strpos($profile_photo, '../') === 0) {
        $sidebar_photo = $profile_photo;
    } elseif (strpos($profile_photo, 'uploads/') === 0) {
        $sidebar_photo = '../' . $profile_photo;
    } else {
        $sidebar_photo = '../uploads/profiles/' . $profile_photo;
    }
    // Cek file exists, jika tidak ada kosongkan
    if (!file_exists($sidebar_photo)) {
        $sidebar_photo = '';
    }
}

?>