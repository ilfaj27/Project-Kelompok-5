<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role'])) {
    header("Location: ../login/login.php");
    exit();
}

$role = $_SESSION['role'];
$nama = $_SESSION['nama'] ?? '';
$id_karyawan = $_SESSION['id_karyawan'] ?? $_SESSION['id_akun'] ?? '';

$dashboard_url = ($role === 'pemilik') ? '../dashboard/view_pemilik.php' : '../dashboard/view_admin.php';

// CARI DATA KARYAWAN
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

if (!$user_data) {
    $debug_info = "ID_Karyawan: " . htmlspecialchars($id_karyawan) . " | Nama_Karyawan: " . htmlspecialchars($nama);
    error_log("[PROFILE ERROR] Data karyawan tidak ditemukan. " . $debug_info);
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Error</title></head><body style="font-family:sans-serif;text-align:center;padding:50px;"><h2>Data Profil Tidak Ditemukan</h2><p>Silakan logout dan login ulang.</p></body></html>';
    exit();
}

// Update session
$_SESSION['id_karyawan'] = $user_data['ID_Karyawan'];
$_SESSION['nama'] = $user_data['Nama_Karyawan'];
$_SESSION['jabatan'] = $user_data['Jabatan'];

function fmtDate($date) {
    if (!$date) return '-';
    if (is_object($date) && method_exists($date, 'format')) return $date->format('d M Y');
    return $date;
}

$map_jk = [0 => 'Perempuan', 1 => 'Laki-laki'];
// FIX: Map Jabatan number to text
$map_jabatan = [1 => 'Karyawan', 2 => 'Manajer'];

// Password change
$pass_msg = '';
if (isset($_POST['change_password'])) {
    $old = $_POST['old_password'];
    $new = $_POST['new_password'];
    $confirm = $_POST['confirm_password'];

    if ($old !== $user_data['Kata_Sandi']) {
        $pass_msg = 'Kata sandi lama salah!';
    } elseif (strlen($new) < 8) {
        $pass_msg = 'Kata sandi baru minimal 8 karakter!';
    } elseif ($new !== $confirm) {
        $pass_msg = 'Kata sandi baru dan konfirmasi tidak cocok!';
    } else {
        $upd = sqlsrv_query($conn, "UPDATE Karyawan SET Kata_Sandi = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Karyawan = ?", array($new, $nama, $user_data['ID_Karyawan']));
        if ($upd) {
            $pass_msg = 'success';
            $stmt = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($user_data['ID_Karyawan']));
            $user_data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        } else {
            $pass_msg = 'Gagal mengubah kata sandi!';
        }
    }
}

// Photo upload - FIX: use Photo_Profile column
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
                // FIX: Photo_Profile not Profile_Photo
                $upd = sqlsrv_query($conn, "UPDATE Karyawan SET Photo_Profile = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Karyawan = ?", array($filename, $nama, $user_data['ID_Karyawan']));
                if ($upd) {
                    $_SESSION['Photo_Profile'] = $filename;
                    $photo_msg = 'success';
                    $stmt = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan = ?", array($user_data['ID_Karyawan']));
                    $user_data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
                }
            }
        } else {
            $photo_msg = 'Format file tidak didukung!';
        }
    }
}

// FIX: Use Photo_Profile column
// FIX: Sesuaikan path foto untuk folder profile/
$profile_photo = $user_data['Photo_Profile'] ?? '';
$photo_path = '';

if (!empty($profile_photo)) {
    if (strpos($profile_photo, '../') === 0) {
        $photo_path = $profile_photo;
    } elseif (strpos($profile_photo, 'uploads/') === 0) {
        $photo_path = '../' . $profile_photo;  // Naik 1 level dari profile/ ke root
    } else {
        $photo_path = '../uploads/profiles/' . $profile_photo;
    }
    if (!file_exists($photo_path)) {
        $photo_path = '';
    }
}

// FIX: Sidebar photo juga perlu disesuaikan
$sidebar_photo = $_SESSION['Photo_Profile'] ?? '';
if (!empty($sidebar_photo)) {
    if (strpos($sidebar_photo, '../') === 0) {
        // sudah benar
    } elseif (strpos($sidebar_photo, 'uploads/') === 0) {
        $sidebar_photo = '../' . $sidebar_photo;
    } else {
        $sidebar_photo = '../uploads/profiles/' . $sidebar_photo;
    }
    if (!file_exists($sidebar_photo)) {
        $sidebar_photo = '';
    }
}

$last_pwd_change_raw = $user_data['Modified_Date'] ?? null;
$last_pwd_change_formatted = '-';
if ($last_pwd_change_raw) {
    if (is_object($last_pwd_change_raw) && method_exists($last_pwd_change_raw, 'format')) {
        $last_pwd_change_formatted = $last_pwd_change_raw->format('d M Y');
    } else {
        $last_pwd_change_formatted = date('d M Y', strtotime($last_pwd_change_raw));
    }
}

// FIX: Get role text
$jabatan_text = $map_jabatan[$user_data['Jabatan']] ?? 'Karyawan';
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
    --text: #111827; --text-md: #374151; --muted: #6B7280; --bg: #F3F4F6;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

.sidebar { width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 28px 18px; z-index: 200; overflow-y: auto; scrollbar-width: none; }
.sidebar::-webkit-scrollbar { display: none; }
.sb-brand { display: flex; align-items: center; gap: 12px; padding: 0 8px; margin-bottom: 36px; text-decoration: none; }
.sb-icon { width: 40px; height: 40px; background: var(--orange); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; box-shadow: 0 4px 14px rgba(255,69,0,.4); }
.sb-brand-name { font-family: 'Barlow Condensed'; font-size: 20px; font-weight: 900; color: #fff; letter-spacing: 1px; }
.sb-brand-sub { font-size: 9px; color: #4B5563; font-weight: 700; text-transform: uppercase; }
.sb-section-label { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #374151; letter-spacing: .8px; padding: 0 10px; margin: 22px 0 8px; }
.sb-link { display: flex; align-items: center; gap: 12px; color: #6B7280; text-decoration: none; padding: 10px 12px; border-radius: 10px; margin-bottom: 2px; font-size: 13px; font-weight: 600; transition: all .2s; }
.sb-link .sb-icon-wrap { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; background: rgba(255,255,255,.04); transition: .2s; flex-shrink: 0; }
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

.profile-grid { display: grid; grid-template-columns: 320px 1fr; gap: 24px; align-items: stretch; }
@media(max-width: 1100px) { .profile-grid { grid-template-columns: 1fr; } }

.profile-card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); padding: 28px; text-align: center; display: flex; flex-direction: column; justify-content: center; }
.profile-photo-wrap { width: 120px; height: 120px; border-radius: 50%; background: var(--orange-lt); display: inline-flex; align-items: center; justify-content: center; margin-bottom: 16px; position: relative; overflow: hidden; border: 3px solid var(--orange); margin-left: auto; margin-right: auto; }
.profile-photo-wrap img { width: 100%; height: 100%; object-fit: cover; }
.profile-photo-wrap i { font-size: 48px; color: var(--orange); }
.profile-name { font-family: 'Barlow Condensed'; font-size: 22px; font-weight: 900; color: var(--text); text-transform: uppercase; }
.profile-role { font-size: 12px; color: var(--orange); font-weight: 800; text-transform: uppercase; margin-top: 4px; }
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

html, body { scrollbar-width: none; -ms-overflow-style: none; }
html::-webkit-scrollbar, body::-webkit-scrollbar { display: none; }

@media(max-width: 768px) {
    .sidebar { width: 0; overflow: hidden; padding: 0; }
    .main { margin-left: 0; }
    .content { padding: 20px; }
    .topbar { padding: 0 20px; }
}
</style>
</head>
<body>

<aside class="sidebar">
    <a href="<?= ($role === 'pemilik') ? '../dashboard/view_pemilik.php' : '../dashboard/view_admin.php' ?>" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div><div class="sb-brand-name">HOOP BALL</div><div class="sb-brand-sub">Management System</div></div>
    </a>

<?php if ($role === 'pemilik') { ?>
    <div class="sb-section-label">Manajemen</div>
    <nav>
        <a href="../dashboard/view_pemilik.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>Dashboard
        </a>
        <a href="../master/karyawan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-user-tie"></i></div>Kelola Karyawan
        </a>
        <a href="../laporan/omzet.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-chart-line"></i></div>Laporan & Omzet
        </a>
    </nav>
<?php } else { ?>
    <div class="sb-section-label">Operasional</div>
    <nav>
        <a href="../dashboard/view_admin.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>Dashboard
        </a>
        <a href="../master/customer.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>Kelola Customer
        </a>
        <a href="../master/lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>Kelola Lapangan
        </a>
        <a href="../master/fasilitas_lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-list-check"></i></div>Kelola Fasilitas
        </a>
        <a href="../master/jadwal.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-days"></i></div>Kelola Jadwal
        </a>
        <a href="../master/promo.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div>Kelola Promo
        </a>
        <a href="../master/tipe_member.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-card"></i></div>Kelola Tipe Member
        </a>
        <a href="../master/alat.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div>Kelola Alat
        </a>
    </nav>
    <div class="sb-section-label">Transaksi</div>
    <nav>
        <a href="../transaksi/booking.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>Kelola Booking
        </a>
        <a href="../transaksi/langganan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-crown"></i></div>Kelola Langganan
        </a>
        <a href="../transaksi/pembelian.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-cart-shopping"></i></div>Kelola Pembelian Alat
        </a>
        <a href="../transaksi/pembatalan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-ban"></i></div>Kelola Pembatalan
        </a>
    </nav>
<?php } ?>

    <div class="sb-section-label">Akun</div>
    <a href="profile.php" class="sb-link active">
        <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>Profil Saya
    </a>

    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
                <?php if ($sidebar_photo) { ?>
                    <img src="<?= $sidebar_photo ?>" alt="Profile">
                <?php } else { ?>
                    <i class="fa-solid fa-user"></i>
                <?php } ?>
            </div>
            <div>
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                <div class="sb-user-role"><?= ($role === 'pemilik') ? 'MANAJER' : 'KARYAWAN' ?></div>
            </div>
            <a href="../login/logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Profil Saya</div>
            <div class="topbar-breadcrumb">Akun / Profil</div>
        </div>
        <div class="topbar-right">
            <div id="clock-display">
                <div class="clock-time"><span id="h">00</span><span class="clock-colon">:</span><span id="m">00</span><span class="clock-colon">:</span><span id="s">00</span></div>
                <div class="clock-divider"></div>
                <div class="clock-date" id="full-date">MEMUAT...</div>
            </div>
            <div class="dropdown-wrap">
                <div class="topbar-user">
                    <div class="t-avatar">
                        <?php if ($photo_path): ?><img src="<?= $photo_path ?>" alt="Profile"><?php else: ?><i class="fa-solid fa-user"></i><?php endif; ?>
                    </div>
                    <div><div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="t-role"><?= ($role === 'pemilik') ? 'MANAJER' : 'KARYAWAN' ?></div></div>
                    <i class="fa-solid fa-chevron-down t-chevron"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="profile.php" class="dd-item"><i class="fa-solid fa-id-badge"></i> Profil Saya</a>
                    <hr class="dd-divider">
                    <a href="../login/logout.php" class="dd-item" style="color:var(--red);"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
                </div>
            </div>
        </div>
    </header>

    <div class="content">
        <div class="page-header">
            <div class="page-title-tag"></div>
            <div class="page-title">Informasi Profil</div>
        </div>

        <div class="profile-grid">
            <!-- KIRI ATAS: FOTO PROFIL -->
            <div class="profile-card">
                <div class="profile-photo-wrap">
                    <?php if ($photo_path): ?>
                        <img src="<?= $photo_path ?>" alt="Profile">
                    <?php else: ?>
                        <i class="fa-solid fa-user-tie"></i>
                    <?php endif; ?>
                </div>
                <div class="profile-name"><?= strtoupper(htmlspecialchars($user_data['Nama_Karyawan'] ?? $nama)) ?></div>
                <!-- FIX: Show role text instead of number -->
                <div class="profile-role"><?= strtoupper(htmlspecialchars($jabatan_text)) ?></div>
                <!-- FIX: ID_Karyawan hidden, not displayed -->
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

            <!-- KANAN ATAS: DATA PRIBADI -->
            <div class="info-card">
                <div class="info-card-title"><i class="fa-solid fa-id-card"></i> Data Pribadi</div>
                <div class="info-grid">
                    <!-- FIX: ID_Karyawan hidden from view -->
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
                    <div class="info-item">
                        <div class="info-label"><i class="fa-solid fa-briefcase"></i> Jabatan</div>
                        <!-- FIX: Show role text -->
                        <div class="info-value"><?= htmlspecialchars($jabatan_text) ?></div>
                    </div>
                    <div class="info-item info-full">
                        <div class="info-label"><i class="fa-solid fa-map-location-dot"></i> Alamat Lengkap</div>
                        <div class="info-value"><?= htmlspecialchars($user_data['Alamat'] ?? '-') ?></div>
                    </div>
                </div>
            </div>

            <!-- KIRI BAWAH: INFORMASI LOGIN -->
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

            <!-- KANAN BAWAH: GANTI PASSWORD -->
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
document.addEventListener('DOMContentLoaded', function () {
    const userDropdown = document.querySelector('.dropdown-wrap');
    if (userDropdown) {
        userDropdown.addEventListener('click', function (e) {
            e.stopPropagation();
            this.classList.toggle('active');
        });
    }
    document.addEventListener('click', function () {
        if (userDropdown) userDropdown.classList.remove('active');
    });
});

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

<?php if ($pass_msg === 'success' || $photo_msg === 'success'): ?>
Swal.fire({
    icon: 'success',
    title: 'Berhasil!',
    text: '<?= ($pass_msg === 'success') ? 'Kata sandi berhasil diubah!' : 'Foto profil berhasil diperbarui!' ?>',
    timer: 2000,
    showConfirmButton: false,
    iconColor: '#FF4500'
});
<?php endif; ?>
</script>
</body>
</html>