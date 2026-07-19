<?php
require_once '../login/auth_check.php';
$path_prefix = "../"; 

// 1. Baca semua data session ke variabel lokal terlebih dahulu
$swal_status = $_SESSION['swal_status'] ?? '';
$swal_title = $_SESSION['swal_title'] ?? '';
$swal_msg = $_SESSION['swal_msg'] ?? '';
$pass_error_field = $_SESSION['pass_error_field'] ?? '';

// 2. Gunakan variabel lokal untuk ganti password agar tidak kosong
$pass_msg = $swal_msg;

// 3. Baru hapus memori session setelah nilainya aman disimpan di variabel lokal
if (isset($_SESSION['swal_status'])) {
    unset($_SESSION['swal_status']);
    unset($_SESSION['swal_title']);
    unset($_SESSION['swal_msg']);
}
if (isset($_SESSION['pass_error_field'])) {
    unset($_SESSION['pass_error_field']);
}

include '../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pemilik') {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard/dashboard.php';</script>";
    exit();
}

// ========================================================
// ⚠️ PANGGIL SENSOR AUTO LOGOUT IDLE (DENGAN PENGAMAN AJAX) ⚠️
// ========================================================
// Cek apakah request berupa AJAX POST ganti password atau upload foto
$is_profile_ajax = ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['change_password']) || isset($_POST['upload_photo'])));

if (!$is_profile_ajax) {
    require_once '../login/auto_logout.php';
}
// ========================================================

$role = $_SESSION['role'];
$nama = $_SESSION['nama'] ?? '';
$id_karyawan = $_SESSION['id_karyawan'] ?? $_SESSION['id_akun'] ?? '';

$dashboard_url = '../dashboard/view_pemilik.php';

// ── CARI DATA KARYAWAN ──
$user_data = null;

if (!empty($id_karyawan)) {
    $query = "EXEC sp_Karyawan_GetByID ?";
    $stmt = sqlsrv_query($conn, $query, array($id_karyawan));
    if ($stmt && sqlsrv_has_rows($stmt)) {
        $user_data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    }
}

if (!$user_data && !empty($nama)) {
    $query = "SELECT TOP 1 * FROM Karyawan WHERE Nama_Karyawan = ?";
    $stmt = sqlsrv_query($conn, $query, array($nama));
    if ($stmt && sqlsrv_has_rows($stmt)) {
        $user_data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    }
}

if ($user_data) {
    $_SESSION['id_karyawan'] = $user_data['ID_Karyawan'] ?? '';
    $_SESSION['nama'] = $user_data['Nama_Karyawan'] ?? '';
    $_SESSION['jabatan'] = $user_data['Jabatan'] ?? '';
}

function fmtDate($date)
{
    if (!$date)
        return '-';
    if (is_object($date) && method_exists($date, 'format'))
        return $date->format('d M Y');
    return $date;
}

$map_jk = [0 => 'Perempuan', 1 => 'Laki-laki', 'P' => 'Perempuan', 'L' => 'Laki-laki'];
$map_jabatan = [1 => 'Karyawan', 2 => 'Manajer'];

// ── PROSES GANTI PASSWORD (AJAX) ──
if (isset($_POST['change_password'])) {
    $old = $_POST['old_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    header('Content-Type: application/json');

    // 1. Validasi kecocokan password lama
    if (!password_verify($old, trim($user_data['Kata_Sandi']))) {
        echo json_encode([
            'status' => 'error',
            'title' => 'Gagal!',
            'message' => 'Kata sandi lama salah!',
            'error_field' => 'old_password'
        ]);
        exit();
    }
    // 2. Validasi apakah sandi baru sama dengan sandi lama
    elseif (password_verify($new, $user_data['Kata_Sandi'])) {
        echo json_encode([
            'status' => 'error',
            'title' => 'Gagal!',
            'message' => 'Kata sandi baru tidak boleh sama dengan kata sandi lama!',
            'error_field' => 'new_password'
        ]);
        exit();
    }

    // TAMBAHKAN VALIDASI BARU INI: Validasi apakah sandi baru mengandung spasi
    elseif (strpos($new, ' ') !== false) {
        echo json_encode([
            'status' => 'error',
            'title' => 'Gagal!',
            'message' => 'Kata sandi baru tidak boleh mengandung spasi!',
            'error_field' => 'new_password'
        ]);
        exit();
    }

    // 3. Validasi panjang karakter sandi baru
    elseif (strlen($new) < 8) {
        echo json_encode([
            'status' => 'error',
            'title' => 'Gagal!',
            'message' => 'Kata sandi baru minimal 8 karakter!',
            'error_field' => 'new_password'
        ]);
        exit();
    }
    // 4. Validasi kecocokan konfirmasi sandi
    elseif ($new !== $confirm) {
        echo json_encode([
            'status' => 'error',
            'title' => 'Gagal!',
            'message' => 'Konfirmasi tidak cocok.',
            'error_field' => 'confirm_password'
        ]);
        exit();
    } else {
        $hashed_new = password_hash($new, PASSWORD_ARGON2ID);
        $upd = sqlsrv_query($conn, "EXEC sp_Karyawan_ChangePassword @ID_Karyawan = ?, @Kata_Sandi = ?, @Modified_By = ?", array($user_data['ID_Karyawan'], $hashed_new, $nama));

        if ($upd) {
            echo json_encode([
                'status' => 'success',
                'title' => 'Berhasil!',
                'message' => 'Kata sandi berhasil diubah!'
            ]);
            exit();
        } else {
            echo json_encode([
                'status' => 'error',
                'title' => 'Gagal!',
                'message' => 'Gagal mengubah kata sandi di database!'
            ]);
            exit();
        }
    }
}

// Definisikan nilai awal secara global agar tidak memicu Undefined Variable di HTML
$photo_msg = '';
if (isset($_POST['upload_photo']) && isset($_FILES['profile_photo'])) {
    header('Content-Type: application/json');
    $file = $_FILES['profile_photo'];
    if ($file['error'] === 0) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($ext, $allowed)) {
            $upload_dir = 'uploads/profiles/'; // DIUBAH: Tanpa ../ karena sejajar
            $id_karyawan_clean = trim($user_data['ID_Karyawan']);
            $db_filename = 'uploads/profiles/' . $id_karyawan_clean . '_' . time() . '.' . $ext;
            $target_file = $upload_dir . basename($db_filename);

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            if (move_uploaded_file($file['tmp_name'], $target_file)) {
                $upd = sqlsrv_query($conn, "EXEC sp_Karyawan_UpdatePhoto @ID_Karyawan = ?, @Photo_Profile = ?, @Modified_By = ?", array($user_data['ID_Karyawan'], $db_filename, $nama));
                if ($upd) {
                    $_SESSION['Photo_Profile'] = $db_filename;
                    echo json_encode([
                        'status' => 'success',
                        'message' => 'Foto profil berhasil diperbarui!',
                        'new_photo_path' => $db_filename // DIUBAH: Tanpa ../
                    ]);
                    exit();
                } else {
                    echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan ke database!']);
                    exit();
                }
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Gagal memindahkan file!']);
                exit();
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Format file tidak didukung! (jpg, jpeg, png, gif)']);
            exit();
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error upload: ' . $file['error']]);
        exit();
    }
}

// ── SESUDAH (BENAR) ──
// ── PENYESUAIAN PATH FOTO (BERSIH & AMAN) ──
$profile_photo_db = $user_data['Photo_Profile'] ?? '';
$photo_path = '';

if (!empty($profile_photo_db)) {
    // Bersihkan sisa-sisa karakter "../" lama yang mungkin pernah tersimpan di database
    $clean_db_path = ltrim(str_replace('../', '', $profile_photo_db), '/');
    $photo_path = $clean_db_path; // Langsung gunakan path tanpa ../
}

// Gunakan __DIR__ karena folder uploads berada di dalam folder yang sama dengan file script ini
$absolute_check_path = __DIR__ . '/' . $photo_path;
$is_photo_exist = !empty($photo_path) && file_exists($absolute_check_path);

$sidebar_photo = $photo_path;
$_SESSION['Photo_Profile'] = $photo_path;

// ── VARIABEL UNTUK TOPBAR.PHP ──
$topbar_title = 'Profil Saya';
$topbar_breadcrumb = 'Akun / Profil';

// ── VARIABEL UNTUK SIDEBAR.PHP ──
$sidebar_folder = 'profile';
$current_page = 'profile';

$last_pwd_change_raw = $user_data['Modified_Date'] ?? null;
$last_pwd_change_formatted = '-';
if ($last_pwd_change_raw) {
    if (is_object($last_pwd_change_raw) && method_exists($last_pwd_change_raw, 'format')) {
        $last_pwd_change_formatted = $last_pwd_change_raw->format('d M Y');
    } else {
        $last_pwd_change_formatted = date('d M Y', strtotime($last_pwd_change_raw));
    }
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
   <?php include '../includes/favicon.php'; ?>
    <title>Profil Saya | HoopBall</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../asset/css/responsive_tipe_member.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --orange: #FF4500;
            --orange-lt: rgba(255, 69, 0, .10);
            --orange-dk: #E03E00;
            --green: #10B981;
            --green-lt: rgba(16, 185, 129, .10);
            --blue: #3B82F6;
            --blue-lt: rgba(59, 130, 246, .10);
            --red: #EF4444;
            --red-lt: rgba(239, 68, 68, .10);
            --sidebar: #0D1117;
            --sidebar-w: 260px;
            --topbar-h: 70px;
            --card-bg: #FFFFFF;
            --border: #E5E7EB;
            --border-lt: #F3F4F6;
            --text: #111827;
            --muted: #6B7280;
            --bg: #F3F4F6;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Barlow', sans-serif;
            background: var(--bg);
            display: flex;
            min-height: 100vh;
            color: var(--text);
        }

        .main {
            margin-left: var(--sidebar-w);
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .topbar {
            background: var(--card-bg);
            height: var(--topbar-h);
            padding: 0 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 1px solid var(--border);
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .topbar-left {
            display: flex;
            flex-direction: column;
        }

        .topbar-title {
            font-family: 'Barlow Condensed';
            font-size: 26px;
            font-weight: 900;
            color: var(--text);
        }

        .topbar-breadcrumb {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
            margin-top: 2px;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .topbar-btn {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: var(--bg);
            border: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            transition: .2s;
        }

        .topbar-btn:hover {
            border-color: var(--orange);
            color: var(--orange);
            background: var(--orange-lt);
        }

        .dropdown-wrap {
            position: relative;
        }

        .topbar-user {
            display: flex;
            align-items: center;
            gap: 10px;
            background: var(--bg);
            border: 1px solid var(--border);
            padding: 6px 14px 6px 8px;
            border-radius: 12px;
            cursor: pointer;
            transition: .2s;
        }

        .topbar-user:hover {
            border-color: var(--orange);
        }

        .t-avatar {
            width: 32px;
            height: 32px;
            background: var(--orange);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 13px;
            overflow: hidden;
        }

        .t-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .t-name {
            font-size: 13px;
            font-weight: 800;
            color: var(--text);
            line-height: 1.1;
            text-transform: uppercase;
        }

        .t-role {
            font-size: 10px;
            color: var(--orange);
            font-weight: 700;
            text-transform: uppercase;
        }

        .t-chevron {
            color: var(--muted);
            font-size: 10px;
            margin-left: 4px;
        }

        .dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 8px);
            background: #fff;
            min-width: 200px;
            border-radius: 12px;
            border: 1px solid var(--border);
            box-shadow: 0 15px 40px rgba(0, 0, 0, .12);
            overflow: hidden;
            padding: 8px 0;
            z-index: 999;
        }

        .dropdown-wrap:hover .dropdown-menu {
            display: block;
        }

        .dropdown-wrap.active .dropdown-menu {
            display: block;
        }

        .dd-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 16px;
            color: #444;
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: .15s;
        }

        .dd-item:hover {
            background: #FFF7ED;
            color: var(--orange);
        }

        .dd-item i {
            font-size: 14px;
            width: 18px;
            text-align: center;
        }

        .dd-divider {
            border: none;
            border-top: 1px solid #F3F4F6;
            margin: 4px 0;
        }

        #clock-display {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .clock-time {
            font-family: 'Barlow Condensed';
            font-size: 26px;
            font-weight: 900;
            color: var(--orange);
            display: flex;
            align-items: center;
            gap: 6px;
            line-height: 1;
        }

        .clock-colon {
            color: var(--orange);
            opacity: .5;
            animation: blink 1s infinite;
        }

        @keyframes blink {

            0%,
            100% {
                opacity: .5;
            }

            50% {
                opacity: 1;
            }
        }

        .clock-divider {
            width: 1.5px;
            height: 28px;
            background-color: var(--border);
        }

        .clock-date {
            font-family: 'Barlow';
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .content {
            padding: 32px 40px;
            flex: 1;
        }

        .page-header {
            margin-bottom: 28px;
        }

        .page-title-tag {
            width: 36px;
            height: 4px;
            background: var(--orange);
            border-radius: 2px;
            margin-bottom: 8px;
        }

        .page-title {
            font-family: 'Barlow Condensed';
            font-size: 30px;
            font-weight: 900;
            color: var(--text);
            text-transform: uppercase;
        }

        .profile-grid {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 24px;
            align-items: stretch;
        }

        @media(max-width: 1100px) {
            .profile-grid {
                grid-template-columns: 1fr;
            }
        }

        .profile-card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 28px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .profile-photo-wrap {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--orange-lt);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
            position: relative;
            overflow: hidden;
            border: 3px solid var(--orange);
            margin-left: auto;
            margin-right: auto;
        }

        .profile-photo-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-photo-wrap i {
            font-size: 48px;
            color: var(--orange);
        }

        .profile-name {
            font-family: 'Barlow Condensed';
            font-size: 22px;
            font-weight: 900;
            color: var(--text);
            text-transform: uppercase;
        }

        .profile-role {
            font-size: 12px;
            color: var(--orange);
            font-weight: 800;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .profile-id {
            font-size: 11px;
            color: var(--muted);
            font-weight: 700;
            margin-top: 6px;
            font-family: 'Barlow Condensed';
        }

        .profile-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 800;
            margin-top: 12px;
            margin-left: auto;
            margin-right: auto;
        }

        .status-active {
            background: var(--green-lt);
            color: var(--green);
        }

        .status-inactive {
            background: var(--red-lt);
            color: var(--red);
        }

        .photo-upload {
            margin-top: 20px;
        }

        .photo-upload input[type="file"] {
            display: none;
        }

        .btn-upload {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--bg);
            border: 1.5px solid var(--border);
            color: var(--text-md);
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 800;
            cursor: pointer;
            transition: .2s;
        }

        .btn-upload:hover {
            border-color: var(--orange);
            color: var(--orange);
            background: var(--orange-lt);
        }

        .info-card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 28px;
        }

        .info-card-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .info-card-title i {
            color: var(--orange);
        }

        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media(max-width: 768px) {
            .info-grid {
                grid-template-columns: 1fr;
            }
        }

        .info-item {
            padding: 14px 16px;
            background: var(--bg);
            border-radius: 12px;
            border: 1px solid var(--border-lt);
        }

        .info-label {
            font-size: 10px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .info-label i {
            color: var(--orange);
            font-size: 11px;
        }

        .info-value {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
        }

        .info-value-mono {
            font-family: 'Barlow Condensed';
            font-size: 15px;
            font-weight: 800;
            color: var(--orange);
        }

        .info-full {
            grid-column: span 2;
        }

        @media(max-width: 768px) {
            .info-full {
                grid-column: span 1;
            }
        }

        .password-card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 28px;
            align-self: start;
        }

        .password-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 20px;
        }

        .password-title i {
            color: var(--orange);
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            font-size: 11px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .5px;
            display: block;
            margin-bottom: 6px;
        }

        .form-input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Barlow';
            transition: .2s;
            background: #FAFAFA;
        }

        .form-input:focus {
            outline: none;
            border-color: var(--orange);
            background: #fff;
            box-shadow: 0 0 0 3px rgba(255, 69, 0, .08);
        }

        .btn-save {
            background: var(--orange);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 13px;
            cursor: pointer;
            transition: .2s;
            text-transform: uppercase;
        }

        .btn-save:hover {
            background: var(--orange-dk);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(255, 69, 0, .25);
        }

        .msg-success {
            background: var(--green-lt);
            color: var(--green);
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 16px;
            border: 1px solid rgba(16, 185, 129, .2);
        }

        .msg-error {
            background: var(--red-lt);
            color: var(--red);
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 16px;
            border: 1px solid rgba(239, 68, 68, .2);
        }

        html,
        body {
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        html::-webkit-scrollbar,
        body::-webkit-scrollbar {
            display: none;
        }

        @media(max-width: 991px) {
            .bottom-row-grid {
                grid-template-columns: 1fr;
            }
        }

        @media(max-width: 768px) {
            .sidebar {
                width: 0;
                overflow: hidden;
                padding: 0;
            }

            .main {
                margin-left: 0;
            }

            .content {
                padding: 20px;
            }

            .topbar {
                padding: 0 20px;
            }
        }


        .topbar-user {
            background-color: #FFFFFF !important;
        }

        .topbar-btn:hover,
        .topbar-user:hover {
            background-color: #E5E7EB !important;
            border-color: #D1D5DB !important;
            color: #4B5563 !important;
        }

        .topbar-btn:active,
        .topbar-user:active {
            background-color: #D1D5DB !important;
            border-color: #9CA3AF !important;
            color: #1F2937 !important;
        }

        /* ============================================
   MATIKAN SEMUA ANIMASI SWEETALERT2 
   ============================================ */
        .swal2-popup {
            animation: none !important;
            transition: none !important;
        }

        .swal2-icon {
            animation: none !important;
        }

        .swal2-icon.swal2-success .swal2-success-ring,
        .swal2-icon.swal2-success [class^="swal2-success-line"],
        .swal2-icon.swal2-error [class^="swal2-x-mark-line"],
        .swal2-icon.swal2-warning {
            animation: none !important;
        }

        /* cegah body/html digeser oleh kompensasi scrollbar SweetAlert */
        html.swal2-shown,
        body.swal2-shown,
        body.swal2-height-auto {
            padding-right: 0 !important;
        }

        /* Menampilkan pesan error */
        .error-message.show {
            display: block;
        }

        /* --- KODE CSS INI YANG HILANG SEBELUMNYA --- */
        /* Form Ganti Password Override (Font Plus Jakarta Sans) */
        .password-card {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
        }

        .password-card .password-title {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            font-size: 18px !important;
            font-weight: 800 !important;
            letter-spacing: normal !important;
        }

        .password-card .form-label {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            text-transform: none !important;
            /* Menonaktifkan huruf kapital paksa */
            font-size: 12px !important;
            font-weight: 700 !important;
            letter-spacing: normal !important;
            color: #1C1C1E !important;
        }

        .password-card .form-input {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            font-size: 13px !important;
        }

        /* Memastikan Teks Pesan Kesalahan Berwarna Merah dan Menggunakan Font yang Benar */
        .password-card .error-message {
            font-family: 'Plus Jakarta Sans', sans-serif !important;
            font-size: 11px !important;
            font-weight: 600 !important;
            margin-top: 6px;
            color: #ff3b30 !important;
            /* Memaksa teks menjadi merah */
            display: none;
            /* <--- TAMBAHKAN BARIS INI UNTUK MENYEMBUNYIKAN SEBELUM VALIdASI */
        }

        /* Memastikan teks merah muncul hanya saat class .show ditambahkan */
        .password-card .error-message.show {
            display: block !important;
        }

        /* Memastikan Garis Tepi Input Berwarna Merah Saat Terjadi Kesalahan */
        .form-input.error-border {
            border-color: #ff3b30 !important;
            outline-color: #ff3b30 !important;
            background-color: #FFF5F5 !important;
            /* Menambahkan latar belakang merah sangat tipis seperti di form Customer */
        }

        /* ------------------------------------------- */
    </style>
</head>

<body>

    <?php
    $current_page = 'profile';
    $sidebar_folder = 'profile';
    include '../includes/sidebar.php';
    ?>

    <main class="main">
        <?php
        // ============================================================
// SET TOPBAR VARIABLES & INCLUDE UNIFIED TOPBAR
// ============================================================
        $topbar_title = 'Profil Saya';
        $topbar_breadcrumb = 'Akun / Profil';
        include '../includes/topbar.php';
        ?>

        <div class="content">
            <div class="page-header">
                <div class="page-title-tag"></div>
                <div class="page-title">Informasi Profil</div>
            </div>

            <div class="profile-grid">
                <!-- 1. KIRI ATAS: FOTO PROFIL -->
                <div class="profile-card">
                    <div class="profile-photo-wrap">
                        <?php if ($photo_path && $is_photo_exist): ?>
                            <img src="<?= $photo_path ?>" alt="Profile">
                        <?php else: ?>
                            <span
                                style="font-size:48px; font-weight:900; color:var(--orange);"><?= strtoupper(substr($user_data['Nama_Karyawan'] ?? $nama, 0, 1)) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="profile-name"><?= strtoupper(htmlspecialchars($user_data['Nama_Karyawan'] ?? $nama)) ?>
                    </div>
                    <div class="profile-role">
                        <?= strtoupper(htmlspecialchars($map_jabatan[$user_data['Jabatan']] ?? 'Karyawan')) ?>
                    </div>
                    <div class="profile-id" style="font-size:10px; color: var(--muted); opacity: 0.7;">NIK:
                        <?= htmlspecialchars($user_data['NIK'] ?? '-') ?>
                    </div>
                    <div
                        class="profile-status <?= ($user_data['Status'] == 1) ? 'status-active' : 'status-inactive' ?>">
                        <i
                            class="fa-solid <?= ($user_data['Status'] == 1) ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                        <?= ($user_data['Status'] == 1) ? 'AKTIF' : 'NONAKTIF' ?>
                    </div>
                    <form method="POST" enctype="multipart/form-data" class="photo-upload" id="photoUploadForm">
                        <input type="file" name="profile_photo" id="profile_photo" accept="image/*">
                        <input type="hidden" name="upload_photo" value="1">
                        <label for="profile_photo" class="btn-upload">
                            <i class="fa-solid fa-camera"></i> Ganti Foto
                        </label>
                    </form>
                    <?php if ($photo_msg === 'success'): ?>
                        <div class="msg-success" style="margin-top:12px;"><i class="fa-solid fa-check-circle"></i> Foto
                            berhasil diperbarui!</div>
                    <?php elseif ($photo_msg): ?>
                        <div class="msg-error" style="margin-top:12px;"><i class="fa-solid fa-circle-exclamation"></i>
                            <?= $photo_msg ?></div>
                    <?php endif; ?>
                </div>

                <!-- 2. KANAN ATAS: DATA PRIBADI -->
                <div class="info-card">
                    <div class="info-card-title"><i class="fa-solid fa-id-card"></i> Data Pribadi</div>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label"><i class="fa-solid fa-id-card"></i> NIK</div>
                            <div class="info-value-mono"><?= htmlspecialchars($user_data['NIK'] ?? '-') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fa-solid fa-user"></i> Nama Lengkap</div>
                            <div class="info-value"><?= htmlspecialchars($user_data['Nama_Karyawan'] ?? '-') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fa-solid fa-venus-mars"></i> Jenis Kelamin</div>
                            <div class="info-value"><?= $map_jk[$user_data['Jenis_Kelamin']] ?? 'Tidak diketahui' ?>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fa-solid fa-calendar-day"></i> Tanggal Lahir</div>
                            <div class="info-value"><?= fmtDate($user_data['Tanggal_Lahir'] ?? null) ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fa-solid fa-location-dot"></i> Tempat Lahir</div>
                            <div class="info-value"><?= htmlspecialchars($user_data['Tempat_Lahir'] ?? '-') ?></div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fa-solid fa-phone"></i> No. Telepon</div>
                            <div class="info-value"><?= htmlspecialchars($user_data['No_Telepon'] ?? '-') ?></div>
                        </div>
                        <div class="info-item info-full">
                            <div class="info-label"><i class="fa-solid fa-map-location-dot"></i> Alamat Lengkap</div>
                            <div class="info-value"><?= htmlspecialchars($user_data['Alamat'] ?? '-') ?></div>
                        </div>
                    </div>
                </div>

                <!-- 3. KIRI BAWAH: INFORMASI LOGIN -->
                <div class="info-card login-info-card" style="display: flex; flex-direction: column;">
                    <div class="info-card-title" style="margin-bottom: 20px;"><i class="fa-solid fa-shield-halved"></i>
                        Informasi Masuk</div>
                    <div
                        style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; flex-grow: 1; align-content: space-between;">
                        <div class="info-item" style="grid-column: span 2;">
                            <div class="info-label"><i class="fa-solid fa-user-gear"></i> Nama Pengguna</div>
                            <div class="info-value"><?= htmlspecialchars($user_data['Username'] ?? '-') ?></div>
                        </div>
                        <div class="info-item" style="grid-column: span 2;">
                            <div class="info-label"><i class="fa-solid fa-envelope"></i> Email</div>
                            <div class="info-value"><?= htmlspecialchars($user_data['Email'] ?? '-') ?></div>
                        </div>
                        <div class="info-item" style="grid-column: span 2;">
                            <div class="info-label"><i class="fa-solid fa-key"></i> Terakhir Ganti Kata Sandi</div>
                            <div class="info-value"><?= $last_pwd_change_formatted ?></div>
                        </div>
                    </div>
                </div>

                <div class="password-card" id="password-form-card">
                    <div class="password-title"><i class="fa-solid fa-lock"></i> Ganti Kata Sandi</div>

                    <!-- Notifikasi Umum (Hanya muncul jika ada error) -->
                    <?php if ($swal_status === 'error' && empty($pass_error_field)): ?>
                        <div class="msg-error"><i class="fa-solid fa-circle-exclamation"></i>
                            <?= htmlspecialchars($pass_msg) ?></div>
                    <?php endif; ?>

                    <form method="POST" id="formPassword">
                        <div class="info-grid">
                            <!-- Kata Sandi Lama -->
                            <div class="form-group" style="grid-column: span 2;">
                                <label class="form-label">Kata Sandi Lama <span class="required"
                                        style="color: red;">*</span></label>
                                <div style="position: relative;">
                                    <input type="password" name="old_password" id="old_password"
                                        class="form-input <?= ($pass_error_field === 'old_password') ? 'error-border' : '' ?>"
                                        placeholder="Sandi saat ini" style="padding-right: 40px;">
                                    <i class="fa-solid fa-eye" id="toggleOldPass"
                                        onclick="toggleProfilePassword('old_password', 'toggleOldPass')"
                                        style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #8E8E93; font-size: 14px;"></i>
                                </div>
                                <div class="error-message <?= ($pass_error_field === 'old_password') ? 'show' : '' ?>"
                                    id="oldPassError">
                                    <?= ($pass_error_field === 'old_password') ? htmlspecialchars($pass_msg) : 'Kata Sandi lama wajib diisi.' ?>
                                </div>
                            </div>

                            <!-- Kata Sandi Baru -->
                            <div class="form-group">
                                <label class="form-label">Kata Sandi Baru <span class="required"
                                        style="color: red;">*</span></label>
                                <div style="position: relative;">
                                    <input type="password" name="new_password" id="new_password"
                                        class="form-input <?= ($pass_error_field === 'new_password') ? 'error-border' : '' ?>"
                                        placeholder="Minimal 8 karakter" style="padding-right: 40px;">
                                    <i class="fa-solid fa-eye" id="toggleNewPass"
                                        onclick="toggleProfilePassword('new_password', 'toggleNewPass')"
                                        style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #8E8E93; font-size: 14px;"></i>
                                </div>
                                <div class="error-message <?= ($pass_error_field === 'new_password') ? 'show' : '' ?>"
                                    id="newPassError">
                                    <?= ($pass_error_field === 'new_password') ? htmlspecialchars($pass_msg) : 'Kata Sandi baru minimal 8 karakter.' ?>
                                </div>
                            </div>

                            <!-- Konfirmasi Kata Sandi Baru -->
                            <div class="form-group">
                                <label class="form-label">Konfirmasi Sandi Baru <span class="required"
                                        style="color: red;">*</span></label>
                                <div style="position: relative;">
                                    <input type="password" name="confirm_password" id="confirm_password"
                                        class="form-input <?= ($pass_error_field === 'confirm_password') ? 'error-border' : '' ?>"
                                        placeholder="Ulangi sandi baru" style="padding-right: 40px;">
                                    <i class="fa-solid fa-eye" id="toggleConfirmPass"
                                        onclick="toggleProfilePassword('confirm_password', 'toggleConfirmPass')"
                                        style="position: absolute; right: 14px; top: 50%; transform: translateY(-50%); cursor: pointer; color: #8E8E93; font-size: 14px;"></i>
                                </div>
                                <div class="error-message <?= ($pass_error_field === 'confirm_password') ? 'show' : '' ?>"
                                    id="confirmPassError">
                                    <?= ($pass_error_field === 'confirm_password') ? htmlspecialchars($pass_msg) : 'Konfirmasi tidak cocok.' ?>
                                </div>
                            </div>
                        </div>
                        <button type="submit" name="change_password" class="btn-save" style="margin-top: 16px;">
                            <i class="fa-solid fa-key"></i> Ubah Kata Sandi
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        function updateClock() {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');

            if (document.getElementById('h')) {
                document.getElementById('h').innerText = h;
                document.getElementById('m').innerText = m;
                document.getElementById('s').innerText = s;
                const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
                const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
                document.getElementById('full-date').innerText = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
            }
        }

        function sesuaikanTinggiInformasiLogin() {
            const cardPassword = document.querySelector('.password-card');
            const cardLogin = document.querySelector('.login-info-card');
            if (cardPassword && cardLogin) {
                cardLogin.style.height = cardPassword.offsetHeight + 'px';
            }
        }

        window.addEventListener('load', sesuaikanTinggiInformasiLogin);
        window.addEventListener('resize', sesuaikanTinggiInformasiLogin);

        updateClock();
        setInterval(updateClock, 1000);

        <?php if ($swal_status === 'success' || $photo_msg === 'success'): ?>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: '<?= ($swal_status === 'success') ? htmlspecialchars($pass_msg) : 'Foto profil berhasil diperbarui!' ?>',
                timer: 2000,
                showConfirmButton: false,
                iconColor: '#10B981'
            });

            window.Swal = Swal.mixin({
                scrollbarPadding: false
            });
        <?php elseif ($swal_status === 'error'): ?>
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: <?= json_encode($pass_msg) ?>,
                confirmButtonColor: '#FF4500'
            });
        <?php endif; ?>

        function toggleProfilePassword(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input && icon) {
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        }

        document.addEventListener('DOMContentLoaded', function () {
            const formPassword = document.getElementById('formPassword');
            const oldPass = document.getElementById('old_password');
            const newPass = document.getElementById('new_password');
            const confirmPass = document.getElementById('confirm_password');

            const oldPassError = document.getElementById('oldPassError');
            const newPassError = document.getElementById('newPassError');
            const confirmPassError = document.getElementById('confirmPassError');

            // 1. Fungsi Validasi Real-time
            function validateOldPass() {
                if (!oldPass) return true;
                if (oldPass.value.trim() === '') {
                    oldPass.classList.add('error-border');
                    oldPassError.textContent = 'Kata Sandi lama wajib diisi.';
                    oldPassError.classList.add('show');
                    return false;
                } else {
                    oldPass.classList.remove('error-border');
                    oldPassError.classList.remove('show');
                    return true;
                }
            }

            function validateNewPass() {
                if (!newPass) return true;
                if (newPass.value.trim() === '') {
                    newPass.classList.add('error-border');
                    newPassError.textContent = 'Kata Sandi baru wajib diisi.';
                    newPassError.classList.add('show');
                    return false;
                } else if (newPass.value.length < 8) {
                    newPass.classList.add('error-border');
                    newPassError.textContent = 'Kata Sandi baru minimal 8 karakter.';
                    newPassError.classList.add('show');
                    return false;
                } else if (/\s/.test(newPass.value)) { // DIUBAH: Mendeteksi spasi kosong, tab, atau enter
                    newPass.classList.add('error-border');
                    newPassError.textContent = 'Kata Sandi baru tidak boleh mengandung spasi.';
                    newPassError.classList.add('show');
                    return false;
                } else {
                    newPass.classList.remove('error-border');
                    newPassError.classList.remove('show');
                    return true;
                }
            }

            function validateConfirm() {
                if (!confirmPass || !newPass) return true;
                if (confirmPass.value.trim() === '') {
                    confirmPass.classList.add('error-border');
                    confirmPassError.textContent = 'Konfirmasi sandi baru wajib diisi.';
                    confirmPassError.classList.add('show');
                    return false;
                } else if (confirmPass.value !== newPass.value) {
                    confirmPass.classList.add('error-border');
                    confirmPassError.textContent = 'Konfirmasi tidak cocok.';
                    confirmPassError.classList.add('show');
                    return false;
                } else {
                    confirmPass.classList.remove('error-border');
                    confirmPassError.classList.remove('show');
                    return true;
                }
            }

            if (oldPass) {
                oldPass.addEventListener('input', validateOldPass);
                oldPass.addEventListener('blur', validateOldPass);
            }
            if (newPass) {
                newPass.addEventListener('input', validateNewPass);
                newPass.addEventListener('blur', validateNewPass);
            }
            if (confirmPass) {
                confirmPass.addEventListener('input', validateConfirm);
                confirmPass.addEventListener('blur', validateConfirm);
            }

            // 2. Ganti Password via AJAX (Tanpa Refresh)
            if (formPassword) {
                formPassword.addEventListener('submit', function (e) {
                    e.preventDefault();

                    let isPassFormValid = true;
                    if (!validateOldPass()) isPassFormValid = false;
                    if (!validateNewPass()) isPassFormValid = false;
                    if (!validateConfirm()) isPassFormValid = false;

                    if (isPassFormValid) {
                        const formData = new FormData(formPassword);
                        formData.append('change_password', '1');

                        fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        })
                            .then(response => response.json())
                            .then(data => {
                                if (data.status === 'success') {
                                    Swal.fire({
                                        icon: 'success',
                                        title: data.title,
                                        text: data.message,
                                        timer: 2000,
                                        showConfirmButton: false,
                                        iconColor: '#10B981'
                                    });
                                    formPassword.reset();

                                    document.querySelectorAll('.form-input').forEach(input => input.classList.remove('error-border'));
                                    document.querySelectorAll('.error-message').forEach(err => err.classList.remove('show'));
                                } else {
                                    Swal.fire({
                                        icon: 'error',
                                        title: data.title,
                                        text: data.message,
                                        confirmButtonColor: '#FF4500'
                                    });

                                    if (data.error_field) {
                                        const errorInput = document.getElementById(data.error_field);
                                        if (errorInput) {
                                            errorInput.classList.add('error-border');
                                            let targetErrorId = '';
                                            if (data.error_field === 'old_password') targetErrorId = 'oldPassError';
                                            else if (data.error_field === 'new_password') targetErrorId = 'newPassError';
                                            else if (data.error_field === 'confirm_password') targetErrorId = 'confirmPassError';

                                            const targetErrorEl = document.getElementById(targetErrorId);
                                            if (targetErrorEl) {
                                                targetErrorEl.textContent = data.message;
                                                targetErrorEl.classList.add('show');
                                            }
                                        }
                                    }
                                }
                            })
                            .catch(error => {
                                console.error('Error:', error);
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error!',
                                    text: 'Terjadi kegagalan memproses data.',
                                    confirmButtonColor: '#FF4500'
                                });
                            });
                    }
                });
            }

            // 3. Upload Foto via AJAX (Tanpa Refresh)
            const photoInput = document.getElementById('profile_photo');
            const photoForm = document.getElementById('photoUploadForm');

            if (photoInput && photoForm) {
                photoInput.addEventListener('change', function () {
                    const formData = new FormData(photoForm);

                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                        .then(response => response.json())
                        .then(data => {
                            if (data.status === 'success') {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Berhasil!',
                                    text: data.message,
                                    timer: 2000,
                                    showConfirmButton: false,
                                    iconColor: '#10B981'
                                });

                                const photoWrap = document.querySelector('.profile-photo-wrap');
                                if (photoWrap) {
                                    const cacheBusterPath = data.new_photo_path + '?t=' + new Date().getTime();
                                    const existingImg = photoWrap.querySelector('img');

                                    if (existingImg) {
                                        existingImg.src = cacheBusterPath;
                                    } else {
                                        photoWrap.innerHTML = `<img src="${cacheBusterPath}" alt="Profile">`;
                                    }
                                }

                                const topbarImg = document.querySelectorAll('.t-avatar img');
                                topbarImg.forEach(img => {
                                    img.src = data.new_photo_path + '?t=' + new Date().getTime();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Gagal!',
                                    text: data.message,
                                    confirmButtonColor: '#FF4500'
                                });
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: 'Gagal mengunggah foto.',
                                confirmButtonColor: '#FF4500'
                            });
                        });
                });
            }
        });
    </script>
    <?php if (function_exists('tampilkan_sensor_auto_logout')) tampilkan_sensor_auto_logout(); ?>
</body>

</html>