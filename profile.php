<?php
session_start();

// Cek login - kompatibel dengan semua role
if (!isset($_SESSION['login']) && !isset($_SESSION['ID_Akun'])) {
    header("Location: login.php");
    exit;
}

// Ambil data session (kompatibel dengan struktur view_pemilik.php)
$id_akun = $_SESSION['ID_Akun'] ?? $_SESSION['id_akun'] ?? '';
$role = $_SESSION['role'] ?? $_SESSION['Role'] ?? '';
$username = $_SESSION['nama'] ?? $_SESSION['Username'] ?? $_SESSION['username'] ?? '';
$nama_user = $_SESSION['nama'] ?? '';

// Jika ID_Akun kosong, redirect ke login
if (empty($id_akun)) {
    header("Location: login.php");
    exit;
}

// Include config dengan path yang benar
if (file_exists('includes/config.php')) {
    include 'includes/config.php';
} elseif (file_exists('../includes/config.php')) {
    include '../includes/config.php';
} else {
    die("Config file tidak ditemukan!");
}

// Role mapping for display
$role_labels = ['pemilik' => 'Pemilik', 'karyawan' => 'Karyawan', 'customer' => 'Customer'];
$role_label = $role_labels[strtolower($role)] ?? ucfirst($role);

// Fetch Akun data
$akun = null;
if (isset($conn)) {
    $res = sqlsrv_query($conn, "SELECT * FROM Akun WHERE ID_Akun = ?", array($id_akun));
    $akun = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
}

// Fetch role-specific data
$biodata = null;
$can_edit = false;

if (isset($conn)) {
    if (strtolower($role) === 'customer') {
        $res_cust = sqlsrv_query($conn, "SELECT * FROM Customer WHERE ID_Akun = ?", array($id_akun));
        $biodata = sqlsrv_fetch_array($res_cust, SQLSRV_FETCH_ASSOC);
        $can_edit = true;
    } elseif (strtolower($role) === 'karyawan' || strtolower($role) === 'pemilik') {
        $res_kry = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Akun = ?", array($id_akun));
        $biodata = sqlsrv_fetch_array($res_kry, SQLSRV_FETCH_ASSOC);
        $can_edit = false;
    }
}

// Handle form submissions
$success_msg = '';
$error_msg = '';

// 1. Update Profile Photo
if (isset($_POST['update_photo']) && isset($_FILES['photo'])) {
    $file = $_FILES['photo'];
    $allowed = ['image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 2 * 1024 * 1024;

    if ($file['error'] === 0) {
        if (in_array($file['type'], $allowed) && $file['size'] <= $max_size) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $id_akun . '_' . time() . '.' . $ext;
            $upload_dir = 'uploads/profiles/';

            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $upload_path = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $_SESSION['Profile_Photo'] = $upload_path;
                $success_msg = 'Foto profil berhasil diperbarui!';
            } else {
                $error_msg = 'Gagal mengunggah foto. Coba lagi.';
            }
        } else {
            $error_msg = 'File harus JPG/PNG dan maksimal 2MB.';
        }
    }
}

// 2. Update Password (All roles)
if (isset($_POST['update_password'])) {
    $old_pass = trim($_POST['old_password'] ?? '');
    $new_pass = trim($_POST['new_password'] ?? '');
    $confirm_pass = trim($_POST['confirm_password'] ?? '');

    if ($old_pass !== ($akun['Kata_Sandi'] ?? '')) {
        $error_msg = 'Password lama tidak sesuai.';
    } elseif (strlen($new_pass) < 6) {
        $error_msg = 'Password baru minimal 6 karakter.';
    } elseif ($new_pass !== $confirm_pass) {
        $error_msg = 'Konfirmasi password tidak cocok.';
    } else {
        $stmt = sqlsrv_query($conn, "UPDATE Akun SET Kata_Sandi = ? WHERE ID_Akun = ?", array($new_pass, $id_akun));
        if ($stmt) {
            $success_msg = 'Password berhasil diperbarui!';
            $res = sqlsrv_query($conn, "SELECT * FROM Akun WHERE ID_Akun = ?", array($id_akun));
            $akun = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
        } else {
            $error_msg = 'Gagal memperbarui password.';
        }
    }
}

// 3. Update Customer Biodata (Customer only)
if (isset($_POST['update_biodata']) && strtolower($role) === 'customer' && $biodata) {
    $nama = trim($_POST['nama_customer'] ?? '');
    $jk = intval($_POST['jenis_kelamin'] ?? 1);
    $alamat = trim($_POST['alamat'] ?? '');
    $telepon = trim($_POST['no_telepon'] ?? '');

    if (empty($nama) || empty($alamat) || empty($telepon)) {
        $error_msg = 'Semua field wajib diisi.';
    } else {
        $stmt = sqlsrv_query($conn, 
            "UPDATE Customer SET Nama_Customer = ?, Jenis_Kelamin = ?, Alamat = ?, No_Telepon = ? WHERE ID_Akun = ?",
            array($nama, $jk, $alamat, $telepon, $id_akun)
        );
        if ($stmt) {
            $success_msg = 'Biodata berhasil diperbarui!';
            $res_cust = sqlsrv_query($conn, "SELECT * FROM Customer WHERE ID_Akun = ?", array($id_akun));
            $biodata = sqlsrv_fetch_array($res_cust, SQLSRV_FETCH_ASSOC);
        } else {
            $error_msg = 'Gagal memperbarui biodata.';
        }
    }
}

// Get profile photo
$profile_photo = $_SESSION['Profile_Photo'] ?? '';
if (empty($profile_photo) || !file_exists($profile_photo)) {
    $profile_photo = '';
}

// Helper functions
function jk_label($jk) {
    return $jk == 1 ? 'Laki-laki' : ($jk == 2 ? 'Perempuan' : '-');
}
function jk_icon($jk) {
    return $jk == 1 ? 'fa-mars' : ($jk == 2 ? 'fa-venus' : 'fa-user');
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profil Saya | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10); --green-dk: #059669;
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --purple: #8B5CF6; --purple-lt: rgba(139,92,246,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10); --red-dk: #DC2626;
    --yellow: #F59E0B; --yellow-lt: rgba(245,158,11,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --text-md: #374151; --muted: #6B7280; --bg: #F3F4F6;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

/* ═══ SIDEBAR ═══ */
.sidebar { width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 28px 18px; border-right: 1px solid rgba(255,255,255,.04); z-index: 200; overflow-y: auto; }
.sidebar::-webkit-scrollbar { width: 4px; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 4px; }
.sb-brand { display: flex; align-items: center; gap: 12px; padding: 0 8px; margin-bottom: 36px; text-decoration: none; }
.sb-icon { width: 40px; height: 40px; background: var(--orange); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; flex-shrink: 0; box-shadow: 0 4px 14px rgba(255,69,0,.4); }
.sb-brand-name { font-family: 'Barlow Condensed', sans-serif; font-size: 20px; font-weight: 900; color: #fff; letter-spacing: 1px; }
.sb-brand-sub { font-size: 9px; color: #4B5563; font-weight: 700; text-transform: uppercase; }
.sb-section-label { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #374151; letter-spacing: .8px; padding: 0 10px; margin: 22px 0 8px; }
.sb-link { display: flex; align-items: center; gap: 12px; color: #6B7280; text-decoration: none; padding: 10px 12px; border-radius: 10px; margin-bottom: 2px; font-size: 13px; font-weight: 600; transition: all .2s ease; position: relative; }
.sb-link .sb-icon-wrap { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: .2s; flex-shrink: 0; background: rgba(255,255,255,.04); }
.sb-link:hover { color: #E5E7EB; background: rgba(255,255,255,.04); }
.sb-link:hover .sb-icon-wrap { background: rgba(255,255,255,.08); }
.sb-link.active { color: #fff; background: var(--orange-lt); }
.sb-link.active .sb-icon-wrap { background: var(--orange); color: #fff; }
.sb-bottom { margin-top: auto; padding-top: 20px; }
.sb-user { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.04); border-radius: 12px; padding: 12px; border: 1px solid rgba(255,255,255,.06); }
.sb-avatar { width: 36px; height: 36px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; overflow: hidden; }
.sb-avatar img { width: 100%; height: 100%; object-fit: cover; }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; line-height: 1.1; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
.sb-logout:hover { color: var(--red); background: rgba(239,68,68,.1); }

/* ═══ MAIN & TOPBAR ═══ */
.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar { background: var(--card-bg); height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 0 rgba(0,0,0,.04); }
.topbar-left { display: flex; flex-direction: column; }
.topbar-title { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); letter-spacing: -.5px; line-height: 1; }
.topbar-breadcrumb { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.topbar-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); cursor: pointer; font-size: 14px; text-decoration: none; transition: .2s; position: relative; }
.topbar-btn:hover { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); }
.notif-dot { position: absolute; top: 7px; right: 7px; width: 7px; height: 7px; background: var(--orange); border-radius: 50%; border: 2px solid #fff; }
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
.dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-item i { font-size: 14px; width: 18px; text-align: center; }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

/* ═══ CONTENT & PROFILE ═══ */
.content { padding: 32px 40px; flex: 1; }
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 24px; }
.page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }

/* PROFILE HERO */
.profile-hero { background: linear-gradient(135deg, #1F2937 0%, #111827 100%); border-radius: 20px; padding: 40px; display: flex; align-items: center; gap: 30px; margin-bottom: 28px; position: relative; overflow: hidden; border: 1px solid #374151; }
.profile-hero::before { content: ''; position: absolute; right: -50px; top: -50px; width: 250px; height: 250px; border-radius: 50%; background: radial-gradient(circle, rgba(255,69,0,.15) 0%, transparent 70%); }
.profile-hero::after { content: ''; position: absolute; right: 100px; bottom: -60px; width: 180px; height: 180px; border-radius: 50%; background: radial-gradient(circle, rgba(255,69,0,.08) 0%, transparent 70%); }

/* PHOTO UPLOAD */
.photo-section { position: relative; z-index: 1; flex-shrink: 0; }
.photo-wrapper { width: 120px; height: 120px; border-radius: 50%; border: 4px solid rgba(255,69,0,.3); padding: 4px; position: relative; cursor: pointer; transition: all .3s ease; }
.photo-wrapper:hover { border-color: var(--orange); transform: scale(1.02); }
.photo-wrapper:hover .photo-overlay { opacity: 1; }
.photo-img { width: 100%; height: 100%; border-radius: 50%; object-fit: cover; background: linear-gradient(135deg, var(--orange), #ff7043); display: flex; align-items: center; justify-content: center; color: #fff; font-size: 48px; font-weight: 800; }
.photo-overlay { position: absolute; inset: 4px; border-radius: 50%; background: rgba(0,0,0,.6); display: flex; align-items: center; justify-content: center; opacity: 0; transition: .3s; }
.photo-overlay i { color: #fff; font-size: 24px; }
.photo-input { display: none; }
.photo-label { cursor: pointer; }
.photo-badge { position: absolute; bottom: -5px; left: 50%; transform: translateX(-50%); background: var(--orange); color: #fff; font-size: 10px; font-weight: 800; padding: 4px 12px; border-radius: 20px; white-space: nowrap; z-index: 2; }

.hero-info { position: relative; z-index: 1; flex: 1; }
.hero-name { font-family: 'Barlow Condensed', sans-serif; font-size: 32px; font-weight: 900; color: #fff; letter-spacing: .5px; text-transform: uppercase; }
.hero-role { display: inline-flex; align-items: center; gap: 6px; background: rgba(255,69,0,.15); border: 1px solid rgba(255,69,0,.3); color: var(--orange); font-size: 12px; font-weight: 800; padding: 5px 14px; border-radius: 20px; text-transform: uppercase; margin-top: 8px; letter-spacing: .5px; }
.hero-id { font-size: 13px; color: #6B7280; margin-top: 10px; font-weight: 600; }
.hero-id span { color: var(--orange); font-weight: 800; }

/* GRID LAYOUT */
.profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; }
@media(max-width: 1100px) { .profile-grid { grid-template-columns: 1fr; } }

/* CARDS */
.p-card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; transition: all .2s ease; }
.p-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.06); }
.p-card-wide { grid-column: 1 / -1; }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 10px; }
.card-title i { color: var(--orange); font-size: 16px; }
.card-badge { background: var(--orange-lt); color: var(--orange); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; }
.card-body { padding: 24px; }

/* FORM ELEMENTS */
.form-group { margin-bottom: 20px; }
.form-group:last-child { margin-bottom: 0; }
.form-label { display: block; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
.form-label .required { color: var(--red); margin-left: 2px; font-size: 14px; font-weight: 900; }
.form-input { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 14px; font-family: 'Barlow', sans-serif; color: var(--text); outline: none; transition: all .2s; background: #fff; }
.form-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.form-input:disabled, .form-input[readonly] { background: var(--border-lt); color: var(--muted); cursor: not-allowed; }
.form-input::placeholder { color: #9CA3AF; }
select.form-input { cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 40px; }
textarea.form-input { resize: vertical; min-height: 80px; }

.form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
@media(max-width: 600px) { .form-row { grid-template-columns: 1fr; } }

.btn-save { width: 100%; padding: 14px; border: none; background: var(--orange); color: #fff; font-weight: 800; font-size: 14px; border-radius: 10px; cursor: pointer; text-transform: uppercase; letter-spacing: .5px; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all .2s; }
.btn-save:hover { background: var(--orange-dk); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(255,69,0,.3); }
.btn-save:active { transform: translateY(0); }
.btn-save i { font-size: 16px; }

/* INFO ROWS (Read-only) */
.info-row { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid var(--border-lt); }
.info-row:last-child { border-bottom: none; }
.info-key { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 700; color: var(--muted); }
.info-key i { color: var(--orange); font-size: 14px; width: 20px; text-align: center; }
.info-val { font-size: 14px; font-weight: 700; color: var(--text); text-align: right; }
.info-val.highlight { color: var(--orange); }
.info-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
.badge-active { background: var(--green-lt); color: var(--green); }
.badge-inactive { background: var(--red-lt); color: var(--red); }

/* PASSWORD MASK */
.password-mask { display: flex; align-items: center; gap: 8px; }
.password-dots { font-size: 18px; letter-spacing: 3px; color: var(--muted); }
.btn-toggle-pass { background: none; border: none; color: var(--muted); cursor: pointer; font-size: 14px; padding: 4px; transition: .2s; }
.btn-toggle-pass:hover { color: var(--orange); }

/* ALERTS */
.alert { padding: 14px 18px; border-radius: 12px; font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 10px; margin-bottom: 20px; }
.alert-success { background: var(--green-lt); border: 1px solid rgba(16,185,129,.2); color: var(--green-dk); }
.alert-error { background: var(--red-lt); border: 1px solid rgba(239,68,68,.2); color: var(--red-dk); }
.alert i { font-size: 16px; }

/* EDIT INDICATOR */
.edit-indicator { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; color: var(--green); background: var(--green-lt); padding: 4px 10px; border-radius: 20px; margin-left: 10px; }
.read-indicator { display: inline-flex; align-items: center; gap: 6px; font-size: 11px; font-weight: 700; color: var(--muted); background: var(--border-lt); padding: 4px 10px; border-radius: 20px; margin-left: 10px; }

/* PHOTO FORM */
.photo-form { display: flex; align-items: center; gap: 16px; margin-top: 12px; }
.btn-upload { padding: 10px 20px; background: var(--text); color: #fff; border: none; border-radius: 10px; font-size: 13px; font-weight: 800; cursor: pointer; transition: .2s; display: inline-flex; align-items: center; gap: 8px; }
.btn-upload:hover { background: var(--orange); }
.file-info { font-size: 11px; color: var(--muted); font-weight: 600; }
</style>
</head>
<body>

<!-- ═══════════════════════════════════════════
   SIDEBAR - DYNAMIC BASED ON ROLE
   ═══════════════════════════════════════════ -->
<aside class="sidebar">
    <a href="<?= (strtolower($role) === 'customer') ? 'view_customer.php' : ((strtolower($role) === 'karyawan') ? 'view_admin.php' : 'view_pemilik.php') ?>" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">Management System</div>
        </div>
    </a>

    <?php if (strtolower($role) === 'pemilik'): ?>
    <!-- PEMILIK SIDEBAR -->
    <div class="sb-section-label">Manajemen</div>
    <nav>
        <a href="view_pemilik.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div> Dashboard
        </a>
        <a href="master/akun.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-user-shield"></i></div> Kelola Akun
        </a>
        <a href="master/karyawan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-user-tie"></i></div> Kelola Karyawan
        </a>
        <a href="master/alat.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-truck-fast"></i></div> Kelola Alat
        </a>
        <a href="laporan/omzet.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-chart-line"></i></div> Laporan & Omzet
        </a>
    </nav>
    <div class="sb-section-label">Akun</div>
    <a href="profile.php" class="sb-link active">
        <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div> Profil Saya
    </a>

    <?php elseif (strtolower($role) === 'karyawan'): ?>
    <!-- KARYAWAN SIDEBAR -->
    <div class="sb-section-label">Menu Utama</div>
    <nav>
        <a href="view_admin.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div> Dashboard
        </a>
        <a href="booking.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div> Booking
        </a>
        <a href="lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-map-marker-alt"></i></div> Lapangan
        </a>
        <a href="customer.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div> Customer
        </a>
        <a href="promo.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tag"></i></div> Promo
        </a>
    </nav>
    <div class="sb-section-label">Layanan</div>
    <a href="promo_diskon.php" class="sb-link">
        <div class="sb-icon-wrap"><i class="fa-solid fa-percent"></i></div> Promo & Diskon
    </a>
    <a href="stok_alat.php" class="sb-link">
        <div class="sb-icon-wrap"><i class="fa-solid fa-boxes"></i></div> Stok Alat
    </a>
    <div class="sb-section-label">Akun</div>
    <a href="profile.php" class="sb-link active">
        <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div> Profil Saya
    </a>

    <?php else: ?>
    <!-- CUSTOMER SIDEBAR -->
    <div class="sb-section-label">Menu</div>
    <nav>
        <a href="view_customer.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div> Beranda
        </a>
        <a href="lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-map-marker-alt"></i></div> Lapangan
        </a>
        <a href="jadwal.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar"></i></div> Jadwal
        </a>
        <a href="booking.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div> Booking
        </a>
        <a href="promo.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tag"></i></div> Promo
        </a>
    </nav>
    <div class="sb-section-label">Akun</div>
    <a href="profile.php" class="sb-link active">
        <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div> Profil Saya
    </a>
    <?php endif; ?>

    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
                <?php if ($profile_photo): ?>
                    <img src="<?= $profile_photo ?>" alt="Profile">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
            <div>
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama_user ?: $username)) ?></div>
                <div class="sb-user-role"><?= strtoupper($role_label) ?></div>
            </div>
            <a href="logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- ═══════════════════════════════════════════
   MAIN & TOPBAR
   ═══════════════════════════════════════════ -->
<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Profil Saya</div>
            <div class="topbar-breadcrumb">Akun / Profil</div>
        </div>
        <div class="topbar-right">
            <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a href="#" class="topbar-btn"><i class="fa-solid fa-bell"></i><?php if(false): ?><span class="notif-dot"></span><?php endif; ?></a>
            <div class="dropdown-wrap">
                <div class="topbar-user">
                    <div class="t-avatar">
                        <?php if ($profile_photo): ?>
                            <img src="<?= $profile_photo ?>" alt="Profile">
                        <?php else: ?>
                            <i class="fa-solid fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="t-name"><?= strtoupper(htmlspecialchars($nama_user ?: $username)) ?></div>
                        <div class="t-role"><?= strtoupper($role_label) ?></div>
                    </div>
                    <i class="fa-solid fa-chevron-down t-chevron"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="profile.php" class="dd-item"><i class="fa-solid fa-id-badge"></i> Profil Saya</a>
                    <hr class="dd-divider">
                    <a href="logout.php" class="dd-item" style="color:var(--red);"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
                </div>
            </div>
        </div>
    </header>

    <div class="content">
        <!-- ALERTS -->
        <?php if ($success_msg): ?>
        <div class="alert alert-success"><i class="fa-solid fa-circle-check"></i> <?= $success_msg ?></div>
        <?php endif; ?>
        <?php if ($error_msg): ?>
        <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?= $error_msg ?></div>
        <?php endif; ?>

        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <div class="page-title-tag"></div>
                <div class="page-title">Profil Saya</div>
            </div>
        </div>

        <!-- PROFILE HERO -->
        <div class="profile-hero">
            <div class="photo-section">
                <form method="POST" enctype="multipart/form-data" id="photoForm">
                    <label class="photo-label">
                        <div class="photo-wrapper">
                            <?php if ($profile_photo): ?>
                                <img src="<?= $profile_photo ?>" alt="Profile" class="photo-img">
                            <?php else: ?>
                                <div class="photo-img"><?= strtoupper(substr($username, 0, 1)) ?></div>
                            <?php endif; ?>
                            <div class="photo-overlay"><i class="fa-solid fa-camera"></i></div>
                        </div>
                        <span class="photo-badge"><i class="fa-solid fa-camera" style="font-size:9px;"></i> Ganti Foto</span>
                        <input type="file" name="photo" class="photo-input" accept="image/jpeg,image/png,image/jpg" onchange="document.getElementById('photoForm').submit();">
                    </label>
                    <input type="hidden" name="update_photo" value="1">
                </form>
            </div>
            <div class="hero-info">
                <div class="hero-name"><?= strtoupper(htmlspecialchars($biodata ? ($biodata['Nama_Customer'] ?? $biodata['Nama_Karyawan'] ?? $username) : $username)) ?></div>
                <div class="hero-role"><i class="fa-solid fa-shield-halved"></i> <?= strtoupper($role_label) ?></div>
                <div class="hero-id">ID Akun: <span><?= $id_akun ?></span> &nbsp;|&nbsp; Username: <span><?= htmlspecialchars($username) ?></span></div>
            </div>
        </div>

        <div class="profile-grid">
            <!-- ═══════════════════════════════════════════
               CARD 1: BIODATA DIRI
               ═══════════════════════════════════════════ -->
            <div class="p-card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-address-card"></i> Biodata Diri</div>
                    <?php if ($can_edit): ?>
                        <span class="edit-indicator"><i class="fa-solid fa-pen-to-square"></i> Bisa Diedit</span>
                    <?php else: ?>
                        <span class="read-indicator"><i class="fa-solid fa-eye"></i> Hanya Lihat</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($can_edit && $biodata): ?>
                    <!-- CUSTOMER EDITABLE FORM -->
                    <form method="POST" id="formBiodata" onsubmit="return validateBiodata(this)">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                                <input type="text" name="nama_customer" id="nama_customer" class="form-input" value="<?= htmlspecialchars($biodata['Nama_Customer'] ?? '') ?>" required minlength="3" pattern="[a-zA-Z\s]+" placeholder="Masukkan nama lengkap">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Jenis Kelamin <span class="required">*</span></label>
                                <select name="jenis_kelamin" class="form-input" required>
                                    <option value="1" <?= ($biodata['Jenis_Kelamin'] ?? 1) == 1 ? 'selected' : '' ?>>Laki-laki</option>
                                    <option value="2" <?= ($biodata['Jenis_Kelamin'] ?? 1) == 2 ? 'selected' : '' ?>>Perempuan</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Alamat Lengkap <span class="required">*</span></label>
                            <textarea name="alamat" id="alamat" class="form-input" required placeholder="Masukkan alamat lengkap"><?= htmlspecialchars($biodata['Alamat'] ?? '') ?></textarea>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nomor Telepon <span class="required">*</span></label>
                            <input type="tel" name="no_telepon" id="no_telepon" class="form-input" value="<?= htmlspecialchars($biodata['No_Telepon'] ?? '') ?>" required pattern="[0-9]{10,14}" maxlength="14" placeholder="Contoh: 08123456789">
                        </div>
                        <button type="submit" name="update_biodata" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan</button>
                    </form>
                    <?php elseif ($biodata): ?>
                    <!-- KARYAWAN/PEMILIK READ-ONLY -->
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-user"></i> Nama Lengkap</span>
                        <span class="info-val"><?= htmlspecialchars($biodata['Nama_Customer'] ?? $biodata['Nama_Karyawan'] ?? '-') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid <?= jk_icon($biodata['Jenis_Kelamin'] ?? 0) ?>"></i> Jenis Kelamin</span>
                        <span class="info-val"><?= jk_label($biodata['Jenis_Kelamin'] ?? 0) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-location-dot"></i> Alamat</span>
                        <span class="info-val" style="max-width: 60%; text-align: right;"><?= htmlspecialchars($biodata['Alamat'] ?? '-') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-phone"></i> Nomor Telepon</span>
                        <span class="info-val highlight"><?= htmlspecialchars($biodata['No_Telepon'] ?? '-') ?></span>
                    </div>
                    <?php if (isset($biodata['Jabatan'])): ?>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-briefcase"></i> Jabatan</span>
                        <span class="info-val"><?= ['','Manajer','Supervisor','Kasir','Staf','Operator'][$biodata['Jabatan'] ?? 0] ?? '-' ?></span>
                    </div>
                    <?php endif; ?>
                    <?php else: ?>
                    <div style="text-align: center; padding: 30px; color: var(--muted);">
                        <i class="fa-solid fa-inbox" style="font-size: 32px; margin-bottom: 10px; opacity: .5; display: block;"></i>
                        <div style="font-size: 13px; font-weight: 700;">Data biodata tidak tersedia</div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════
               CARD 2: INFORMASI AKUN
               ═══════════════════════════════════════════ -->
            <div class="p-card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-shield-halved"></i> Informasi Akun</div>
                    <span class="card-badge">Aktif</span>
                </div>
                <div class="card-body">
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-fingerprint"></i> ID Akun</span>
                        <span class="info-val highlight"><?= $id_akun ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-user-tag"></i> Username</span>
                        <span class="info-val"><?= htmlspecialchars($username) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-envelope"></i> Email</span>
                        <span class="info-val"><?= htmlspecialchars($akun['Email'] ?? '-') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-key"></i> Password</span>
                        <span class="info-val">
                            <span class="password-mask">
                                <span class="password-dots" id="passDots">••••••••</span>
                                <button type="button" class="btn-toggle-pass" onclick="togglePass()" id="toggleBtn"><i class="fa-solid fa-eye"></i></button>
                            </span>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-user-shield"></i> Role</span>
                        <span class="info-val"><span class="info-badge badge-active"><i class="fa-solid fa-check-circle"></i> <?= $role_label ?></span></span>
                    </div>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-circle-check"></i> Status</span>
                        <span class="info-val"><span class="info-badge badge-active"><i class="fa-solid fa-check-circle"></i> Aktif</span></span>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════
               CARD 3: UBAH PASSWORD (ALL ROLES)
               ═══════════════════════════════════════════ -->
            <div class="p-card p-card-wide">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-lock"></i> Keamanan - Ubah Password</div>
                </div>
                <div class="card-body">
                    <form method="POST" id="formPassword" onsubmit="return validatePassword(this)">
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Password Lama <span class="required">*</span></label>
                                <input type="password" name="old_password" id="old_password" class="form-input" required placeholder="Masukkan password saat ini">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Password Baru <span class="required">*</span></label>
                                <input type="password" name="new_password" id="new_password" class="form-input" required placeholder="Minimal 6 karakter" minlength="6">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Konfirmasi Password <span class="required">*</span></label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-input" required placeholder="Ulangi password baru">
                            </div>
                        </div>
                        <button type="submit" name="update_password" class="btn-save" style="max-width: 300px;"><i class="fa-solid fa-key"></i> Perbarui Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
// Toggle password visibility
let passVisible = false;
const realPass = '<?= addslashes($akun['Kata_Sandi'] ?? '') ?>';
function togglePass() {
    const dots = document.getElementById('passDots');
    const btn = document.getElementById('toggleBtn');
    passVisible = !passVisible;
    if (passVisible) {
        dots.textContent = realPass;
        dots.style.letterSpacing = 'normal';
        dots.style.fontSize = '14px';
        btn.innerHTML = '<i class="fa-solid fa-eye-slash"></i>';
    } else {
        dots.textContent = '••••••••';
        dots.style.letterSpacing = '3px';
        dots.style.fontSize = '18px';
        btn.innerHTML = '<i class="fa-solid fa-eye"></i>';
    }
}

// Validasi Biodata
function validateBiodata(form) {
    const nama = form.querySelector('#nama_customer');
    const telp = form.querySelector('#no_telepon');
    const alamat = form.querySelector('#alamat');
    let valid = true;

    if (nama.value.length < 3 || !/^[a-zA-Z\s]+$/.test(nama.value)) {
        alert('Nama minimal 3 karakter, hanya huruf dan spasi!');
        nama.focus();
        valid = false;
    }
    if (!/^[0-9]{10,14}$/.test(telp.value)) {
        alert('Nomor telepon harus 10-14 digit angka!');
        telp.focus();
        valid = false;
    }
    if (alamat.value.trim() === '') {
        alert('Alamat tidak boleh kosong!');
        alamat.focus();
        valid = false;
    }
    return valid;
}

// Validasi Password
function validatePassword(form) {
    const newPass = form.querySelector('#new_password');
    const confirmPass = form.querySelector('#confirm_password');

    if (newPass.value.length < 6) {
        alert('Password baru minimal 6 karakter!');
        newPass.focus();
        return false;
    }
    if (newPass.value !== confirmPass.value) {
        alert('Konfirmasi password tidak cocok!');
        confirmPass.focus();
        return false;
    }
    return true;
}

// SweetAlert for messages
const urlParams = new URLSearchParams(window.location.search);
if (urlParams.get('status')) {
    Swal.fire({
        icon: urlParams.get('status'),
        title: urlParams.get('msg'),
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true,
        toast: true,
        position: 'top-end'
    });
    window.history.replaceState({}, '', window.location.pathname);
}
</script>
</body>
</html>