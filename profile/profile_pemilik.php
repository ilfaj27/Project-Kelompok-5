<?php
session_start();

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

if (!isset($_SESSION['role'])) {
    header("Location: ../login/login.php");
    exit();
}

$role = $_SESSION['role'];
$nama = $_SESSION['nama'] ?? '';
$id_karyawan = $_SESSION['id_karyawan'] ?? $_SESSION['id_akun'] ?? '';

$dashboard_url = '../dashboard/view_pemilik.php';

// ── CARI DATA KARYAWAN ──
$user_data = null;

if (!empty($id_karyawan)) {
    $query = "SELECT * FROM Karyawan WHERE ID_Karyawan = ?";
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

// Kalau masih tidak ketemu, tampilkan error yang informatif
if (!$user_data) {
    $debug_info = "ID_Karyawan: " . htmlspecialchars($id_karyawan) . " | Nama_Karyawan: " . htmlspecialchars($nama);
    error_log("[PROFILE ERROR] Data karyawan tidak ditemukan. " . $debug_info);

    echo '<!DOCTYPE html>
<html lang="id">
<head><meta charset="UTF-8"><title>Error Profil</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { font-family: "Barlow", sans-serif; background: #F3F4F6; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .error-box { background: #fff; border-radius: 20px; padding: 48px; text-align: center; max-width: 480px; box-shadow: 0 20px 60px rgba(0,0,0,.1); border: 1px solid #E5E7EB; }
        .error-icon { width: 80px; height: 80px; background: #FEF2F2; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 24px; }
        .error-icon i { font-size: 36px; color: #EF4444; }
        .error-title { font-size: 22px; font-weight: 800; color: #111827; margin-bottom: 8px; }
        .error-desc { font-size: 14px; color: #6B7280; margin-bottom: 24px; line-height: 1.6; }
        .error-debug { background: #F3F4F6; border-radius: 10px; padding: 12px 16px; font-size: 12px; color: #6B7280; font-family: monospace; margin-bottom: 24px; text-align: left; }
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: #FF4500; color: #fff; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 14px; transition: .2s; }
        .btn-back:hover { background: #E03E00; transform: translateY(-1px); }
        .btn-refresh { display: inline-flex; align-items: center; gap: 8px; background: #F3F4F6; color: #374151; padding: 12px 24px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 14px; border: 1px solid #E5E7EB; margin-left: 8px; transition: .2s; }
        .btn-refresh:hover { background: #E5E7EB; }
    </style>
    </head>
    <body>
    <div class="error-box">
        <div class="error-icon"><i class="fa-solid fa-user-xmark"></i></div>
        <div class="error-title">Data Profil Tidak Ditemukan</div>
        <div class="error-desc">Sistem tidak dapat menemukan data karyawan Anda di database.<br><br>
        <strong>Coba:</strong><br>
        1. Keluar dan Masuk ulang<br>
        2. Periksa apakah data karyawan ada di database<br>
        3. Hubungi administrator</div>
        <div class="error-debug"><strong>Debug Info:</strong><br>' . htmlspecialchars($debug_info) . '</div>
        <div>
            <a href="' . $dashboard_url . '" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Kembali ke Dashboard</a>
            <a href="../login/logout.php" class="btn-refresh"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
        </div>
    </div>
    </body>
    </html>';
    exit();
}

// Update session
$_SESSION['id_karyawan'] = $user_data['ID_Karyawan'];
$_SESSION['nama'] = $user_data['Nama_Karyawan'];
$_SESSION['jabatan'] = $user_data['Jabatan'];

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

if (isset($_POST['change_password'])) {
    $old = $_POST['old_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    // Mendapatkan nama file saat ini untuk redirect dinamis
    $current_file = basename($_SERVER['PHP_SELF']);

    // 1. Validasi kecocokan password lama
    if (!password_verify($old, trim($user_data['Kata_Sandi']))) {
        $_SESSION['swal_status'] = 'error';
        $_SESSION['swal_title'] = 'Gagal!';
        $_SESSION['swal_msg'] = 'Kata sandi lama salah!';
        $_SESSION['pass_error_field'] = 'old_password';

        header("Location: " . $current_file);
        exit();
    }
    // 2. Validasi apakah sandi baru sama dengan sandi lama
    elseif (password_verify($new, $user_data['Kata_Sandi'])) {
        $_SESSION['swal_status'] = 'error';
        $_SESSION['swal_title'] = 'Gagal!';
        $_SESSION['swal_msg'] = 'Kata sandi baru tidak boleh sama dengan kata sandi lama!';
        $_SESSION['pass_error_field'] = 'new_password';

        header("Location: " . $current_file);
        exit();
    }
    // 3. Validasi panjang karakter sandi baru
    elseif (strlen($new) < 8) {
        $_SESSION['swal_status'] = 'error';
        $_SESSION['swal_title'] = 'Gagal!';
        $_SESSION['swal_msg'] = 'Kata sandi baru minimal 8 karakter!';
        $_SESSION['pass_error_field'] = 'new_password';

        header("Location: " . $current_file);
        exit();
    }
    // 4. Validasi kecocokan konfirmasi sandi
    elseif ($new !== $confirm) {
        $_SESSION['swal_status'] = 'error';
        $_SESSION['swal_title'] = 'Gagal!';
        $_SESSION['swal_msg'] = 'Konfirmasi tidak cocok.';
        $_SESSION['pass_error_field'] = 'confirm_password';

        header("Location: " . $current_file);
        exit();
    } else {
        // Proses update password menggunakan Argon2id
        $hashed_new = password_hash($new, PASSWORD_ARGON2ID);

        // Catatan: Pastikan kolom Modified_By dan Modified_Date ada di tabel Anda. 
        // Jika tidak ada, sesuaikan query UPDATE ini.
        $upd = sqlsrv_query($conn, "UPDATE Karyawan SET Kata_Sandi = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Karyawan = ?", array($hashed_new, $nama, $user_data['ID_Karyawan']));

        if ($upd) {
            $_SESSION['swal_status'] = 'success';
            $_SESSION['swal_title'] = 'Berhasil!';
            $_SESSION['swal_msg'] = 'Kata sandi berhasil diubah!';
            header("Location: " . $current_file);
            exit();
        } else {
            $_SESSION['swal_status'] = 'error';
            $_SESSION['swal_title'] = 'Gagal!';
            $_SESSION['swal_msg'] = 'Gagal mengubah kata sandi di database!';
            header("Location: " . $current_file);
            exit();
        }
    }
}

// Photo upload - PERBAIKAN: simpan path relatif dari root project
$photo_msg = '';
if (isset($_POST['upload_photo']) && isset($_FILES['profile_photo'])) {
    $file = $_FILES['profile_photo'];
    if ($file['error'] === 0) {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($ext, $allowed)) {
            $filename = 'uploads/profiles/' . $user_data['ID_Karyawan'] . '_' . time() . '.' . $ext;
            if (!is_dir('uploads/profiles')) mkdir('uploads/profiles', 0777, true);
            if (move_uploaded_file($file['tmp_name'], $filename)) {
                $upd = sqlsrv_query($conn, "UPDATE Karyawan SET Photo_Profile = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Karyawan = ?", array($filename, $nama, $user_data['ID_Karyawan']));
                if ($upd) {
                    $_SESSION['Photo_Profile'] = $filename;
                    $photo_msg = 'success';
                    // Refresh data
                    $stmt = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($user_data['ID_Karyawan']));
                    $user_data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                    // Update session
                    $_SESSION['Photo_Profile'] = $filename;
                    $_SESSION['nama'] = $user_data['Nama_Karyawan'];
                } else {
                    $photo_msg = 'Gagal menyimpan ke database!';
                }
            } else {
                $photo_msg = 'Gagal memindahkan file!';
            }
        } else {
            $photo_msg = 'Format file tidak didukung! (jpg, jpeg, png, gif)';
        }
    } else {
        $photo_msg = 'Error upload: ' . $file['error'];
    }
}


// ── SESUDAH (BENAR) ──
$profile_photo = $user_data['Photo_Profile'] ?? '';
$photo_path = '';
$photo_base64 = '';

function findPhotoFile($photo_val) {
    if (empty($photo_val)) return '';
    if (strpos($photo_val, 'data:image') === 0) return $photo_val;

    $paths = [];
    $paths[] = $photo_val;
    $paths[] = '../' . $photo_val;
    $paths[] = '../../' . $photo_val;

    $doc_root = $_SERVER['DOCUMENT_ROOT'] ?? '';
    $script_dir = dirname($_SERVER['SCRIPT_FILENAME'] ?? '');

    $paths[] = $script_dir . '/' . $photo_val;
    $paths[] = dirname($script_dir) . '/' . $photo_val;
    $paths[] = dirname(dirname($script_dir)) . '/' . $photo_val;
    $paths[] = $doc_root . '/' . $photo_val;
    $paths[] = $doc_root . '/Project-Kelompok-5/' . $photo_val;
    $paths[] = $doc_root . '/Project-Kelompok-5/uploads/profiles/' . basename($photo_val);

    foreach ($paths as $p) {
        if (file_exists($p) && is_file($p)) {
            return $p;
        }
    }
    return '';
}

$found_path = findPhotoFile($profile_photo);

if (!empty($found_path)) {
    $photo_path = $found_path;
    $mime = mime_content_type($found_path) ?: 'image/jpeg';
    $data = file_get_contents($found_path);
    if ($data !== false) {
        $photo_base64 = 'data:' . $mime . ';base64,' . base64_encode($data);
    }
}

if (!empty($photo_path)) {
    $_SESSION['Photo_Profile'] = basename($profile_photo);
}

$sidebar_photo = $photo_base64;
$profile_photo = $photo_base64;

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
<meta charset="UTF-8">
<title>Profil Saya | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10);
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --muted: #6B7280; --bg: #F3F4F6;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar { background: var(--card-bg); height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
.topbar-left { display: flex; flex-direction: column; }
.topbar-title { font-family: 'Barlow Condensed'; font-size: 26px; font-weight: 900; color: var(--text); }
.topbar-breadcrumb { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.topbar-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); cursor: pointer; font-size: 14px; text-decoration: none; transition: .2s; }
.topbar-btn:hover { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); }
.dropdown-wrap { position: relative; }
.topbar-user { display: flex; align-items: center; gap: 10px; background: var(--bg); border: 1px solid var(--border); padding: 6px 14px 6px 8px; border-radius: 12px; cursor: pointer; transition: .2s; }
.topbar-user:hover { border-color: var(--orange); }
.t-avatar { width: 32px; height: 32px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; overflow: hidden; }
.t-avatar img { width: 100%; height: 100%; object-fit: cover; }
.t-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.1; text-transform: uppercase; }
.t-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.t-chevron { color: var(--muted); font-size: 10px; margin-left: 4px; }
.dropdown-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #fff; min-width: 200px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 15px 40px rgba(0,0,0,.12); overflow: hidden; padding: 8px 0; z-index: 999; }
.dropdown-wrap:hover .dropdown-menu { display: block; }
.dropdown-wrap.active .dropdown-menu { display: block; }
.dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-item i { font-size: 14px; width: 18px; text-align: center; }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

#clock-display { display: flex; align-items: center; gap: 16px; }
.clock-time { font-family: 'Barlow Condensed'; font-size: 26px; font-weight: 900; color: var(--orange); display: flex; align-items: center; gap: 6px; line-height: 1; }
.clock-colon { color: var(--orange); opacity: .5; animation: blink 1s infinite; }
@keyframes blink { 0%, 100% { opacity: .5; } 50% { opacity: 1; } }
.clock-divider { width: 1.5px; height: 28px; background-color: var(--border); }
.clock-date { font-family: 'Barlow'; font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }

.content { padding: 32px 40px; flex: 1; }
.page-header { margin-bottom: 28px; }
.page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
.page-title { font-family: 'Barlow Condensed'; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }

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

.profile-card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); padding: 28px; text-align: center; display: flex; flex-direction: column; justify-content: center; }
.profile-photo-wrap { width: 120px; height: 120px; border-radius: 50%; background: var(--orange-lt); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px; position: relative; overflow: hidden; border: 3px solid var(--orange); margin-left: auto; margin-right: auto; }
.profile-photo-wrap img { width: 100%; height: 100%; object-fit: cover; }
.profile-photo-wrap i { font-size: 48px; color: var(--orange); }
.profile-name { font-family: 'Barlow Condensed'; font-size: 22px; font-weight: 900; color: var(--text); text-transform: uppercase; }
.profile-role { font-size: 12px; color: var(--orange); font-weight: 800; text-transform: uppercase; margin-top: 4px; }
.profile-id { font-size: 11px; color: var(--muted); font-weight: 700; margin-top: 6px; font-family: 'Barlow Condensed'; }
.profile-status { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 800; margin-top: 12px; margin-left: auto; margin-right: auto; }
.status-active { background: var(--green-lt); color: var(--green); }
.status-inactive { background: var(--red-lt); color: var(--red); }

.photo-upload { margin-top: 20px; }
.photo-upload input[type="file"] { display: none; }
.btn-upload { display: inline-flex; align-items: center; gap: 8px; background: var(--bg); border: 1.5px solid var(--border); color: var(--text-md); padding: 10px 18px; border-radius: 10px; font-size: 12px; font-weight: 800; cursor: pointer; transition: .2s; }
.btn-upload:hover { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); }

.info-card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); padding: 28px; }
.info-card-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; margin-bottom: 20px; }
.info-card-title i { color: var(--orange); }
.info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media(max-width: 768px) { .info-grid { grid-template-columns: 1fr; } }
.info-item { padding: 14px 16px; background: var(--bg); border-radius: 12px; border: 1px solid var(--border-lt); }
.info-label { font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
.info-label i { color: var(--orange); font-size: 11px; }
.info-value { font-size: 14px; font-weight: 700; color: var(--text); }
.info-value-mono { font-family: 'Barlow Condensed'; font-size: 15px; font-weight: 800; color: var(--orange); }
.info-full { grid-column: span 2; }
@media(max-width: 768px) { .info-full { grid-column: span 1; } }

.password-card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); padding: 28px; align-self: start; }
.password-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; margin-bottom: 20px; }
.password-title i { color: var(--orange); }
.form-group { margin-bottom: 16px; }
.form-label { font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 6px; }
.form-input { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 14px; font-family: 'Barlow'; transition: .2s; background: #FAFAFA; }
.form-input:focus { outline: none; border-color: var(--orange); background: #fff; box-shadow: 0 0 0 3px rgba(255,69,0,.08); }
.btn-save { background: var(--orange); color: #fff; border: none; padding: 12px 24px; border-radius: 10px; font-weight: 800; font-size: 13px; cursor: pointer; transition: .2s; text-transform: uppercase; }
.btn-save:hover { background: var(--orange-dk); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(255,69,0,.25); }

.msg-success { background: var(--green-lt); color: var(--green); padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 700; margin-bottom: 16px; border: 1px solid rgba(16,185,129,.2); }
.msg-error { background: var(--red-lt); color: var(--red); padding: 12px 16px; border-radius: 10px; font-size: 13px; font-weight: 700; margin-bottom: 16px; border: 1px solid rgba(239,68,68,.2); }

html, body {
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


.topbar-user { background-color: #FFFFFF !important; }

.topbar-btn:hover, .topbar-user:hover {
    background-color: #E5E7EB !important;
    border-color: #D1D5DB !important;
    color: #4B5563 !important;
}

.topbar-btn:active, .topbar-user:active {
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
                    <?php if ($photo_path && file_exists($photo_path)): ?>
                        <img src="<?= $photo_path ?>" alt="Profile">
                    <?php else: ?>
                        <span style="font-size:48px; font-weight:900; color:var(--orange);"><?= strtoupper(substr($user_data['Nama_Karyawan'] ?? $nama, 0, 1)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="profile-name"><?= strtoupper(htmlspecialchars($user_data['Nama_Karyawan'] ?? $nama)) ?></div>
                <div class="profile-role"><?= strtoupper(htmlspecialchars($map_jabatan[$user_data['Jabatan']] ?? 'Karyawan')) ?></div>
                <div class="profile-id" style="font-size:10px; color: var(--muted); opacity: 0.7;">NIK: <?= htmlspecialchars($user_data['NIK'] ?? '-') ?></div>
                <div class="profile-status <?= ($user_data['Status'] == 1) ? 'status-active' : 'status-inactive' ?>">
                    <i class="fa-solid <?= ($user_data['Status'] == 1) ? 'fa-circle-check' : 'fa-circle-xmark' ?>"></i>
                    <?= ($user_data['Status'] == 1) ? 'AKTIF' : 'NONAKTIF' ?>
                </div>
                <form method="POST" enctype="multipart/form-data" class="photo-upload">
                    <input type="file" name="profile_photo" id="profile_photo" accept="image/*" onchange="this.form.submit()">
                    <input type="hidden" name="upload_photo" value="1">
                    <label for="profile_photo" class="btn-upload">
                        <i class="fa-solid fa-camera"></i> Ganti Foto
                    </label>
                </form>
                <?php if ($photo_msg === 'success'): ?>
                    <div class="msg-success" style="margin-top:12px;"><i class="fa-solid fa-check-circle"></i> Foto berhasil diperbarui!</div>
                <?php elseif ($photo_msg): ?>
                    <div class="msg-error" style="margin-top:12px;"><i class="fa-solid fa-circle-exclamation"></i> <?= $photo_msg ?></div>
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
                        <div class="info-value"><?= $map_jk[$user_data['Jenis_Kelamin']] ?? 'Tidak diketahui' ?></div>
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
    <div class="info-card-title" style="margin-bottom: 20px;"><i class="fa-solid fa-shield-halved"></i> Informasi Masuk</div>
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; flex-grow: 1; align-content: space-between;">
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

            <!-- 4. KANAN BAWAH: GANTI PASSWORD -->
            <div class="password-card">
                <div class="password-title"><i class="fa-solid fa-lock"></i> Ganti Kata Sandi</div>
                <?php if ($pass_msg === 'success'): ?>
                    <div class="msg-success"><i class="fa-solid fa-check-circle"></i> Kata sandi berhasil diubah!</div>
                <?php elseif ($pass_msg): ?>
                    <div class="msg-error"><i class="fa-solid fa-circle-exclamation"></i> <?= $pass_msg ?></div>
                <?php endif; ?>
                <form method="POST">
                    <div class="info-grid">
                        <div class="form-group">
                            <label class="form-label">Kata Sandi Lama</label>
                            <input type="password" name="old_password" class="form-input" required placeholder="Masukkan kata sandi lama">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Kata Sandi Baru</label>
                            <input type="password" name="new_password" class="form-input" required minlength="8" placeholder="Minimal 8 karakter">
                        </div>
                        <div class="form-group" style="grid-column: span 2;">
                            <label class="form-label">Konfirmasi Kata Sandi Baru</label>
                            <input type="password" name="confirm_password" class="form-input" required placeholder="Ulangi kata sandi baru">
                        </div>
                    </div>
                    <button type="submit" name="change_password" class="btn-save" style="margin-top: 8px;">
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
    document.getElementById('h').innerText = h;
    document.getElementById('m').innerText = m;
    document.getElementById('s').innerText = s;
    const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    document.getElementById('full-date').innerText = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
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
            /* Pemicu Pop-Up ketika validasi kata sandi lama salah di database */
            Swal.fire({
                icon: 'error',
                title: 'Gagal!',
                text: <?= json_encode($pass_msg) ?>,
                confirmButtonColor: '#FF4500'
            });
        <?php endif; ?>

        // 1. Fungsi Dinamis Toggle Tampilkan/Sembunyikan Password
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

            // 2. Fungsi Validasi Real-time
            function validateOldPass() {
                if (!oldPass) return true;
                // Hanya cek field input pertama / spasi dipangkas
                if (oldPass.value.trim() === '') {
                    oldPass.classList.add('error-border');
                    oldPassError.textContent = 'Kata Sandi lama wajib diisi.';
                    oldPassError.classList.add('show');
                    return false;
                } else {
                    // Ketika syarat input disesuaikan (berhenti merah/terlepas show text)
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

            // Pasang Event Listener Input
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

            // 3. Validasi saat Form dikirim (Submit)
            if (formPassword) {
                formPassword.addEventListener('submit', function (e) {
                    let isPassFormValid = true;

                    // Memaksa jalannya pemeriksaan ke seluruh komponen kotak field.
                    if (!validateOldPass()) isPassFormValid = false;
                    if (!validateNewPass()) isPassFormValid = false;
                    if (!validateConfirm()) isPassFormValid = false;

                    if (!isPassFormValid) {
                        // Jangan melompat jika input terhambat status di javascript atas tadi!
                        e.preventDefault();
                    }
                });
            }
        });
    </script>
</body>

</html>