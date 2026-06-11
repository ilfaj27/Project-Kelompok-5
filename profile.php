<?php
session_start();

// Cek login - kompatibel dengan semua role
if (!isset($_SESSION['login']) && !isset($_SESSION['ID_Akun'])) {
    header("Location: login.php");
    exit;
}

// Ambil data session
$id_akun = $_SESSION['ID_Akun'] ?? $_SESSION['id_akun'] ?? '';
$role = $_SESSION['role'] ?? $_SESSION['Role'] ?? '';
$username = $_SESSION['nama'] ?? $_SESSION['Username'] ?? $_SESSION['username'] ?? '';
$nama_user = $_SESSION['nama'] ?? '';

if (empty($id_akun)) {
    header("Location: login.php");
    exit;
}

// Include config
if (file_exists('includes/config.php')) {
    include 'includes/config.php';
} elseif (file_exists('../includes/config.php')) {
    include '../includes/config.php';
} else {
    die("Config file tidak ditemukan!");
}

// Role mapping
$role_labels = ['pemilik' => 'Pemilik', 'karyawan' => 'Karyawan', 'customer' => 'Customer'];
$role_label = $role_labels[strtolower($role)] ?? ucfirst($role);

$is_pemilik = strtolower($role) === 'pemilik';
$is_karyawan = strtolower($role) === 'karyawan';
$is_customer = strtolower($role) === 'customer';

// --- TAMBAHKAN QUERY INI UNTUK PENDING COUNT SINKRON ---
$total_pending = 0;
if (isset($conn)) {
    $q_pending = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Booking WHERE Status=1"); // Status 1 = pending
    if ($q_pending !== false) {
        $total_pending = sqlsrv_fetch_array($q_pending, SQLSRV_FETCH_ASSOC)['t'] ?? 0;
    }
}

// Fetch Karyawan data directly (no separate Akun table)
$karyawan_data = null;
if (isset($conn)) {
    $res = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Akun = ? OR ID_Karyawan = ?", array($id_akun, $id_akun));
    $karyawan_data = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
}

// Fetch biodata
$biodata = null;
$can_edit = false;

if (isset($conn)) {
    if ($is_customer) {
        $res_cust = sqlsrv_query($conn, "SELECT * FROM Customer WHERE ID_Akun = ?", array($id_akun));
        $biodata = sqlsrv_fetch_array($res_cust, SQLSRV_FETCH_ASSOC);
        $can_edit = true;
    } elseif ($is_karyawan || $is_pemilik) {
        $res_kry = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Akun = ?", array($id_akun));
        $biodata = sqlsrv_fetch_array($res_kry, SQLSRV_FETCH_ASSOC);
        $can_edit = $is_pemilik;
    }
}

// ── HANDLE FORM SUBMISSIONS ──
$flash_msg = '';
$flash_type = '';

// 1. UPDATE PHOTO (All roles)
if (isset($_POST['update_photo']) && isset($_FILES['photo'])) {
    $file = $_FILES['photo'];
    $allowed = ['image/jpeg', 'image/png', 'image/jpg', 'image/JPEG', 'image/JPG', 'image/PNG'];
    $max_size = 2 * 1024 * 1024; // 2MB

    // Debug: cek error upload
    if ($file['error'] !== 0) {
        $upload_errors = [
            1 => 'File terlalu besar (max 2MB).',
            2 => 'File terlalu besar (max 2MB).',
            3 => 'File hanya terupload sebagian.',
            4 => 'Tidak ada file yang dipilih.',
            6 => 'Folder temporary tidak tersedia.',
            7 => 'Gagal menulis file ke disk.',
            8 => 'Upload dihentikan oleh extension.'
        ];
        $error_msg = $upload_errors[$file['error']] ?? 'Error upload: ' . $file['error'];
        header("Location: profile.php?status=error&msg=" . urlencode($error_msg));
        exit();
    }

    // Cek MIME type dengan getimagesize untuk lebih akurat
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $allowed_mimes = ['image/jpeg', 'image/png', 'image/jpg'];

    if (!in_array($mime_type, $allowed_mimes)) {
        header("Location: profile.php?status=error&msg=" . urlencode('File harus JPG atau PNG. Type: ' . $mime_type));
        exit();
    }

    if ($file['size'] > $max_size) {
        header("Location: profile.php?status=error&msg=" . urlencode('File terlalu besar. Max 2MB. Size: ' . round($file['size']/1024/1024, 2) . 'MB'));
        exit();
    }

    // Generate nama file unik
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $id_akun . '_' . time() . '.' . strtolower($ext);
    $upload_dir = 'uploads/profiles/';

    // Buat folder jika belum ada
    if (!is_dir($upload_dir)) {
        if (!mkdir($upload_dir, 0755, true)) {
            header("Location: profile.php?status=error&msg=" . urlencode('Gagal membuat folder upload.'));
            exit();
        }
    }

    $upload_path = $upload_dir . $filename;

    // Cek apakah file berhasil dipindahkan
    if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
        header("Location: profile.php?status=error&msg=" . urlencode('Gagal memindahkan file. Cek permission folder.'));
        exit();
    }

    // Update database (Karyawan table)
    $sql = "UPDATE Karyawan SET Profile_Photo = ? WHERE ID_Akun = ? OR ID_Karyawan = ?";
    $stmt = sqlsrv_query($conn, $sql, array($upload_path, $id_akun, $id_akun));

    if ($stmt) {
        // Consume all results
        while (sqlsrv_next_result($stmt)) {
            sqlsrv_free_stmt($stmt);
        }
        $_SESSION['Profile_Photo'] = $upload_path;
        header("Location: profile.php?status=success&msg=" . urlencode('Foto profil berhasil diperbarui!'));
        exit();
    } else {
        // Hapus file jika database gagal
        if (file_exists($upload_path)) {
            unlink($upload_path);
        }
        $errors = sqlsrv_errors();
        header("Location: profile.php?status=error&msg=" . urlencode('Gagal update database: ' . ($errors[0]['message'] ?? 'Unknown')));
        exit();
    }
}

// 2. UPDATE BIODATA (Pemilik & Customer only)
if (isset($_POST['update_biodata']) && $can_edit && $biodata) {
    if ($is_customer) {
        $nama = trim($_POST['nama_customer'] ?? '');
        $jk = intval($_POST['jenis_kelamin'] ?? 1);
        $alamat = trim($_POST['alamat'] ?? '');
        $telepon = trim($_POST['no_telepon'] ?? '');

        if (empty($nama) || empty($alamat) || empty($telepon)) {
            header("Location: profile.php?status=error&msg=" . urlencode('Semua field wajib diisi.'));
            exit();
        }

        // Customer: UPDATE dengan kolom Alamat
        $sql = "UPDATE Customer SET Nama_Customer = ?, Jenis_Kelamin = ?, Alamat = ?, No_Telepon = ? WHERE ID_Akun = ?";
        $params = array($nama, $jk, $alamat, $telepon, $id_akun);

    } elseif ($is_pemilik) {
        $nama = trim($_POST['nama_karyawan'] ?? '');
        $jk = intval($_POST['jenis_kelamin'] ?? 1);
        $telepon = trim($_POST['no_telepon'] ?? '');
        $jabatan = trim($_POST['jabatan'] ?? '');

        if (empty($nama) || empty($telepon)) {
            header("Location: profile.php?status=error&msg=" . urlencode('Nama dan telepon wajib diisi.'));
            exit();
        }

        // Karyawan: UPDATE TANPA kolom Alamat (karena tidak ada di tabel!)
        $sql = "UPDATE Karyawan SET Nama_Karyawan = ?, Jenis_Kelamin = ?, No_Telepon = ?, Jabatan = ? WHERE ID_Akun = ?";
        $params = array($nama, $jk, $telepon, $jabatan, $id_akun);
    }

    // Execute dengan transaction
    if (isset($sql) && isset($params)) {
        $stmt = sqlsrv_query($conn, $sql, $params);

        if ($stmt) {
            // Consume all results
            while (sqlsrv_next_result($stmt)) {
                sqlsrv_free_stmt($stmt);
            }

            // Update session
            $_SESSION['nama'] = $nama;
            $_SESSION['nama_user'] = $nama;
            session_write_close();

            header("Location: profile.php?status=success&msg=" . urlencode('Biodata berhasil diperbarui!'));
            exit();
        } else {
            $errors = sqlsrv_errors();
            $error_msg = 'Gagal memperbarui: ' . ($errors[0]['message'] ?? 'Unknown error');
            header("Location: profile.php?status=error&msg=" . urlencode($error_msg));
            exit();
        }
    }
}

// 3. UPDATE PASSWORD (Pemilik & Customer only)
if (isset($_POST['update_password']) && !$is_karyawan) {
    $old_pass = trim($_POST['old_password'] ?? '');
    $new_pass = trim($_POST['new_password'] ?? '');
    $confirm_pass = trim($_POST['confirm_password'] ?? '');

    // Check password from Karyawan table
    $pass_check = null;
    if (isset($conn)) {
        $res = sqlsrv_query($conn, "SELECT Kata_Sandi FROM Karyawan WHERE ID_Akun = ? OR ID_Karyawan = ?", array($id_akun, $id_akun));
        $pass_check = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);
    }

    if ($old_pass !== ($pass_check['Kata_Sandi'] ?? '')) {
        header("Location: profile.php?status=error&msg=" . urlencode('Password lama tidak sesuai.'));
        exit();
    } elseif (strlen($new_pass) < 6) {
        header("Location: profile.php?status=error&msg=" . urlencode('Password baru minimal 6 karakter.'));
        exit();
    } elseif ($new_pass !== $confirm_pass) {
        header("Location: profile.php?status=error&msg=" . urlencode('Konfirmasi password tidak cocok.'));
        exit();
    } else {
        $sql = "UPDATE Karyawan SET Kata_Sandi = ? WHERE ID_Akun = ? OR ID_Karyawan = ?";
        $stmt = sqlsrv_query($conn, $sql, array($new_pass, $id_akun, $id_akun));

        if ($stmt) {
            while (sqlsrv_next_result($stmt)) {
                sqlsrv_free_stmt($stmt);
            }
            header("Location: profile.php?status=success&msg=" . urlencode('Password berhasil diperbarui!'));
            exit();
        } else {
            header("Location: profile.php?status=error&msg=" . urlencode('Gagal memperbarui password.'));
            exit();
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
.sidebar { width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 28px 18px; border-right: 1px solid rgba(255,255,255,.04); z-index: 200; overflow-y: auto; scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none;  /* IE and Edge */ }
.sidebar::-webkit-scrollbar { width: 4px; display: none;}
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
.main {  /* Trik tumpang-tindih 1px ke kiri untuk melenyapkan celah vertikal */
    margin-left: calc(var(--sidebar-w) - 1px); 
    flex: 1;
    display: flex;
    flex-direction: column;
    min-height: 100vh; }
.topbar { background: var(--card-bg); height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 0 rgba(0,0,0,.04); }
.topbar-left { display: flex; flex-direction: column; }
.topbar-title { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); letter-spacing: -.5px; line-height: 1; }
.topbar-breadcrumb { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.topbar-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); cursor: pointer; font-size: 14px; text-decoration: none; transition: .2s; position: relative; }
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
.dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-item i { font-size: 14px; width: 18px; text-align: center; }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

/* ═══ CONTENT ═══ */
.content { padding: 32px 40px; flex: 1; }
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 24px; }
.page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }

/* ═══ PROFILE HERO ═══ */
.profile-hero { background: linear-gradient(135deg, #1F2937 0%, #111827 100%); border-radius: 20px; padding: 40px; display: flex; align-items: center; gap: 30px; margin-bottom: 28px; position: relative; overflow: hidden; border: 1px solid #374151; }
.profile-hero::before { content: ''; position: absolute; right: -50px; top: -50px; width: 250px; height: 250px; border-radius: 50%; background: radial-gradient(circle, rgba(255,69,0,.15) 0%, transparent 70%); }
.profile-hero::after { content: ''; position: absolute; right: 100px; bottom: -60px; width: 180px; height: 180px; border-radius: 50%; background: radial-gradient(circle, rgba(255,69,0,.08) 0%, transparent 70%); }

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

/* ═══ GRID LAYOUT ═══ */
.profile-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 22px; }
@media(max-width: 1100px) { .profile-grid { grid-template-columns: 1fr; } }

/* ═══ CARDS ═══ */
.p-card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; transition: all .2s ease; }
.p-card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.06); }
.p-card-wide { grid-column: 1 / -1; }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 10px; }
.card-title i { color: var(--orange); font-size: 16px; }
.card-badge { background: var(--orange-lt); color: var(--orange); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; text-transform: uppercase; }
.card-body { padding: 24px; }

/* ═══ FORM ELEMENTS ═══ */
.form-group { margin-bottom: 20px; }
.form-group:last-child { margin-bottom: 0; }
.form-label { display: block; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
.form-label .required { color: var(--red); margin-left: 2px; font-size: 14px; font-weight: 900; }
.form-input { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 14px; font-family: 'Barlow', sans-serif; color: var(--text); outline: none; transition: all .2s; background: #fff; }
.form-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.form-input.error { border-color: var(--red) !important; box-shadow: 0 0 0 3px var(--red-lt) !important; background-color: #fef2f2 !important; }
.form-input.error::placeholder { color: var(--red); }
.form-input.valid { border-color: var(--green) !important; box-shadow: 0 0 0 3px var(--green-lt) !important; }
.error-msg { color: var(--red); font-size: 12px; font-weight: 600; margin-top: 4px; display: none; }
.error-msg.show { display: block; }
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

/* ═══ INFO ROWS (Read-only) ═══ */
.info-row { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid var(--border-lt); }
.info-row:last-child { border-bottom: none; }
.info-key { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 700; color: var(--muted); }
.info-key i { color: var(--orange); font-size: 14px; width: 20px; text-align: center; }
.info-val { font-size: 14px; font-weight: 700; color: var(--text); text-align: right; }
.info-val.highlight { color: var(--orange); }
.info-badge { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 700; }
.badge-active { background: var(--green-lt); color: var(--green); }
.badge-inactive { background: var(--red-lt); color: var(--red); }

/* ═══ PASSWORD MASK ═══ */
.password-mask { display: flex; align-items: center; gap: 8px; }
.password-dots { font-size: 18px; letter-spacing: 3px; color: var(--muted); }
.btn-toggle-pass { background: none; border: none; color: var(--muted); cursor: pointer; font-size: 14px; padding: 4px; transition: .2s; }
.btn-toggle-pass:hover { color: var(--orange); }

/* ═══ VIEW ONLY MESSAGE ═══ */
.view-only-msg { background: var(--blue-lt); border: 1px solid rgba(59,130,246,.2); border-radius: 10px; padding: 12px 16px; font-size: 13px; color: var(--blue); font-weight: 600; display: flex; align-items: center; gap: 8px; margin-bottom: 16px; }
.view-only-msg i { font-size: 14px; }

/* ═══ PASSWORD CARD GRID ═══ */
.password-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items: end; }
@media(max-width: 768px) { .password-form-grid { grid-template-columns: 1fr; } }
.password-btn-wrap { margin-top: 20px; }
.password-btn-wrap .btn-save { max-width: 240px; }

/* ═══ ROLE-SPECIFIC STYLES ═══ */
.role-pemilik .hero-role { background: rgba(255,69,0,.2); border-color: rgba(255,69,0,.4); }
.role-karyawan .profile-hero { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-color: #334155; }
.role-karyawan .hero-role { background: rgba(59,130,246,.15); border-color: rgba(59,130,246,.3); color: #60a5fa; }
.role-karyawan .photo-wrapper { border-color: rgba(59,130,246,.3); }
.role-karyawan .photo-wrapper:hover { border-color: #60a5fa; }
.role-customer .hero-role { background: rgba(16,185,129,.15); border-color: rgba(16,185,129,.3); color: var(--green); }

#clock-display { 
    display: flex; 
    align-items: center; 
    gap: 16px; 
}

.clock-time { 
    font-family: 'Barlow Condensed', sans-serif; 
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
    0%, 100% { opacity: .5; } 
    50% { opacity: 1; } 
}

.clock-divider { 
    width: 1.5px; 
    height: 28px; 
    background-color: var(--border); 
}

.clock-date { 
    font-family: 'Barlow', sans-serif; 
    font-size: 13px; 
    font-weight: 700; 
    color: var(--muted); 
    text-transform: uppercase; 
    letter-spacing: 0.5px; 
}

html {
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE and Edge */
}

html::-webkit-scrollbar {
    display: none; /* Chrome, Safari, Opera */
}

/* ═══ RESPONSIVE ═══ */
@media(max-width: 768px) {
    .sidebar { width: 0; overflow: hidden; padding: 0; }
    .main { margin-left: 0; }
    .content { padding: 20px; }
    .topbar { padding: 0 20px; }
    .profile-hero { flex-direction: column; text-align: center; padding: 30px 20px; }
    .hero-info { width: 100%; }
}
</style>
</head>
<body class="role-<?= strtolower($role) ?>">

<!-- ═══ SIDEBAR PROFIL SINKRON ═══ -->
<aside class="sidebar">
    <a href="<?= (strtolower($role) === 'customer') ? 'view_customer.php' : ((strtolower($role) === 'karyawan') ? 'view_admin.php' : 'view_pemilik.php') ?>" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">MANAGEMENT SYSTEM</div>
        </div>
    </a>

    <?php if ($is_pemilik): ?>
    <div class="sb-section-label">Manajemen</div>
    <nav>
        <a href="view_pemilik.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div> Dashboard</a>
        <a href="master/karyawan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-user-tie"></i></div> Kelola Karyawan</a>
        <a href="master/alat.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-truck-fast"></i></div> Kelola Alat</a>
        <a href="laporan/omzet.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-chart-line"></i></div> Laporan & Omzet</a>
    </nav>
    <div class="sb-section-label">Akun</div>
    <a href="profile.php" class="sb-link active"><div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div> Profil Saya</a>

    <?php elseif ($is_karyawan): ?>
    <!-- MENU KARYAWAN SINKRON (SAMA DENGAN VIEW_ADMIN) -->
    <div class="sb-section-label">Menu Utama</div>
    <nav>
        <a href="view_admin.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div> Dashboard
        </a>
        <a href="booking.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div> Booking
        </a>
        <a href="master/lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div> Lapangan
        </a>
        <a href="master/customer.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div> Customer
        </a>
        <a href="master/promo.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tag"></i></div> Promo
        </a>
    </nav>
    <div class="sb-section-label">Akun</div>
    <nav>
        <a href="profile.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div> Profil Saya
        </a>
        <a href="riwayat.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-clock-rotate-left"></i></div> Riwayat
        </a>
    </nav>

    <?php else: ?>
    <div class="sb-section-label">Menu</div>
    <nav>
        <a href="view_customer.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div> Beranda</a>
        <a href="lapangan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div> Lapangan</a>
        <a href="jadwal.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-calendar"></i></div> Jadwal</a>
        <a href="booking.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div> Booking</a>
        <a href="promo.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-tag"></i></div> Promo</a>
    </nav>
    <div class="sb-section-label">Akun</div>
    <a href="profile.php" class="sb-link active"><div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div> Profil Saya</a>
    <?php endif; ?>

    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
                <?php if ($profile_photo): ?><img src="<?= $profile_photo ?>" alt="Profile"><?php else: ?><i class="fa-solid fa-user"></i><?php endif; ?>
            </div>
            <div><div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama_user ?: $username)) ?></div><div class="sb-user-role"><?= strtoupper($role_label) ?></div></div>
            <a href="logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- ═══ MAIN & TOPBAR ═══ -->
<main class="main">
<!-- ═══ TOPBAR PROFIL SINKRON DENGAN VIEW_ADMIN ═══ -->
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Profil Saya</div>
            <div class="topbar-breadcrumb">Akun / Profil</div>
        </div>
        <div class="topbar-right">
            <!-- Jam Digital Live Persis Seperti di Gambar -->
            <div id="clock-display">
                <div class="clock-time">
                    <span id="h">00</span><span class="clock-colon">:</span><span id="m">00</span><span class="clock-colon">:</span><span id="s">00</span>
                </div>
                <div class="clock-divider"></div>
                <div class="clock-date" id="full-date">MEMUAT...</div>
            </div>
            
            <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
            
            <a href="#" class="topbar-btn">
                <i class="fa-solid fa-bell"></i>
                <!-- Notifikasi Dinamis dari database -->
                <?php if(isset($total_pending) && $total_pending > 0): ?><span class="notif-dot"></span><?php endif; ?>
            </a>
            
            <div class="dropdown-wrap">
                <div class="topbar-user">
                    <div class="t-avatar">
                        <?php if ($profile_photo): ?><img src="<?= $profile_photo ?>" alt="Profile"><?php else: ?><i class="fa-solid fa-user"></i><?php endif; ?>
                    </div>
                    <div><div class="t-name"><?= strtoupper(htmlspecialchars($nama_user ?: $username)) ?></div><div class="t-role"><?= strtoupper($role_label) ?></div></div>
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
                <?php if (!$is_karyawan): ?>
                <div class="hero-id">ID Akun: <span><?= $id_akun ?></span> &nbsp;|&nbsp; Username: <span><?= htmlspecialchars($username) ?></span></div>
                <?php else: ?>
                <div class="hero-id">Username: <span><?= htmlspecialchars($username) ?></span></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="profile-grid">
            <!-- CARD 1: BIODATA DIRI -->
            <div class="p-card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-address-card"></i> Biodata Diri</div>
                    <?php if ($can_edit): ?>
                        <span class="card-badge"><i class="fa-solid fa-pen-to-square" style="font-size:10px;"></i> Bisa Diedit</span>
                    <?php elseif ($is_karyawan): ?>
                        <span class="card-badge" style="background:var(--blue-lt);color:var(--blue);"><i class="fa-solid fa-eye" style="font-size:10px;"></i> Hanya Lihat</span>
                    <?php else: ?>
                        <span class="card-badge" style="background:var(--border-lt);color:var(--muted);"><i class="fa-solid fa-eye" style="font-size:10px;"></i> Hanya Lihat</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if ($is_karyawan): ?>
                    <div class="view-only-msg"><i class="fa-solid fa-circle-info"></i> Anda hanya dapat melihat data. Hubungi Manajer untuk perubahan data.</div>
                    <?php endif; ?>

                    <?php if ($can_edit && $biodata): ?>
                    <!-- EDITABLE FORM -->
                    <form method="POST" id="formBiodata" onsubmit="return validateBiodata(this)">
                        <?php if ($is_customer): ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                                <input type="text" name="nama_customer" id="nama_customer" class="form-input" value="<?= htmlspecialchars($biodata['Nama_Customer'] ?? '') ?>" placeholder="Masukkan nama lengkap">
                                <div class="error-msg">Nama wajib diisi, minimal 3 huruf</div>
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
                            <textarea name="alamat" id="alamat" class="form-input" placeholder="Masukkan alamat lengkap"><?= htmlspecialchars($biodata['Alamat'] ?? '') ?></textarea>
                            <div class="error-msg">Alamat wajib diisi</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nomor Telepon <span class="required">*</span></label>
                            <input type="tel" name="no_telepon" id="no_telepon" class="form-input" value="<?= htmlspecialchars($biodata['No_Telepon'] ?? '') ?>" maxlength="14" placeholder="Contoh: 08123456789">
                            <div class="error-msg">Nomor telepon wajib diisi, 10-14 digit</div>
                        </div>
                        <?php elseif ($is_pemilik): ?>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                                <input type="text" name="nama_karyawan" id="nama_karyawan" class="form-input" value="<?= htmlspecialchars($biodata['Nama_Karyawan'] ?? '') ?>" placeholder="Masukkan nama lengkap">
                                <div class="error-msg">Nama wajib diisi, minimal 3 huruf</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Jenis Kelamin <span class="required">*</span></label>
                                <select name="jenis_kelamin" class="form-input" required>
                                    <option value="1" <?= ($biodata['Jenis_Kelamin'] ?? 1) == 1 ? 'selected' : '' ?>>Laki-laki</option>
                                    <option value="2" <?= ($biodata['Jenis_Kelamin'] ?? 1) == 2 ? 'selected' : '' ?>>Perempuan</option>
                                </select>
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Nomor Telepon <span class="required">*</span></label>
                                <input type="tel" name="no_telepon" id="no_telepon" class="form-input" value="<?= htmlspecialchars($biodata['No_Telepon'] ?? '') ?>" maxlength="14" placeholder="Contoh: 08123456789">
                                <div class="error-msg">Nomor telepon wajib diisi, 10-14 digit</div>
                            </div>
                            <div class="form-group">
                                <label class="form-label">Jabatan</label>
                                <input type="text" id="jabatan" class="form-input" value="Manajer" readonly style="background:var(--border-lt); cursor:not-allowed; opacity:0.8;">
                                <input type="hidden" name="jabatan" value="Manajer">
                            </div>
                        </div>
                        <?php endif; ?>
                        <button type="submit" name="update_biodata" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan</button>
                    </form>
                    <?php elseif ($biodata): ?>
                    <!-- READ-ONLY VIEW -->
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-user"></i> Nama Lengkap</span>
                        <span class="info-val"><?= htmlspecialchars($biodata['Nama_Customer'] ?? $biodata['Nama_Karyawan'] ?? '-') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid <?= jk_icon($biodata['Jenis_Kelamin'] ?? 0) ?>"></i> Jenis Kelamin</span>
                        <span class="info-val"><?= jk_label($biodata['Jenis_Kelamin'] ?? 0) ?></span>
                    </div>
                    <?php if ($is_customer || isset($biodata['Alamat'])): ?>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-location-dot"></i> Alamat</span>
                        <span class="info-val" style="max-width: 60%; text-align: right;"><?= htmlspecialchars($biodata['Alamat'] ?? '-') ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-phone"></i> Nomor Telepon</span>
                        <span class="info-val highlight"><?= htmlspecialchars($biodata['No_Telepon'] ?? '-') ?></span>
                    </div>
                    <?php if (isset($biodata['Jabatan'])): ?>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-briefcase"></i> Jabatan</span>
                        <span class="info-val"><?= is_numeric($biodata['Jabatan']) ? (['','Manajer','Supervisor','Kasir','Staf','Operator'][$biodata['Jabatan'] ?? 0] ?? '-') : htmlspecialchars($biodata['Jabatan']) ?></span>
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

            <!-- CARD 2: INFORMASI AKUN -->
            <div class="p-card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-shield-halved"></i> Informasi Akun</div>
                    <span class="card-badge">Aktif</span>
                </div>
                <div class="card-body">
                    <?php if (!$is_karyawan): ?>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-fingerprint"></i> ID Akun</span>
                        <span class="info-val highlight"><?= $id_akun ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-user-tag"></i> Username</span>
                        <span class="info-val"><?= htmlspecialchars($username) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-envelope"></i> Email</span>
                        <span class="info-val"><?= htmlspecialchars($karyawan_data['Email'] ?? $biodata['Email'] ?? '-') ?></span>
                    </div>
                    <?php if (!$is_karyawan): ?>
                    <div class="info-row">
                        <span class="info-key"><i class="fa-solid fa-key"></i> Password</span>
                        <span class="info-val">
                            <span class="password-mask">
                                <span class="password-dots" id="passDots">••••••••</span>
                                <button type="button" class="btn-toggle-pass" onclick="togglePass()" id="toggleBtn"><i class="fa-solid fa-eye"></i></button>
                            </span>
                        </span>
                    </div>
                    <?php endif; ?>
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

            <?php if (!$is_karyawan): ?>
            <!-- CARD 3: UBAH PASSWORD -->
            <div class="p-card p-card-wide">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-lock"></i> Keamanan — Ubah Password</div>
                </div>
                <div class="card-body">
                    <form method="POST" id="formPassword" onsubmit="return validatePassword(this)">
                        <div class="form-group">
                            <label class="form-label">Password Lama <span class="required">*</span></label>
                            <input type="password" name="old_password" id="old_password" class="form-input" placeholder="Masukkan password lama">
                            <div class="error-msg">Password lama wajib diisi</div>
                        </div>
                        <div class="password-form-grid">
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label">Password Baru <span class="required">*</span></label>
                                <input type="password" name="new_password" id="new_password" class="form-input" placeholder="Minimal 6 karakter">
                                <div class="error-msg">Password baru wajib diisi, minimal 6 karakter</div>
                            </div>
                            <div class="form-group" style="margin-bottom:0;">
                                <label class="form-label">Konfirmasi Password <span class="required">*</span></label>
                                <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="Ulangi password baru">
                                <div class="error-msg">Konfirmasi password wajib diisi</div>
                            </div>
                        </div>
                        <div class="password-btn-wrap">
                            <button type="submit" name="update_password" class="btn-save"><i class="fa-solid fa-key"></i> Perbarui Password</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</main>

<script>
// Toggle password visibility
let passVisible = false;
const realPass = '<?= addslashes($karyawan_data['Kata_Sandi'] ?? $biodata['Kata_Sandi'] ?? '') ?>';
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

// Validasi Real-time
function validateField(field, condition, errorMsg) {
    const errorEl = field.parentElement.querySelector('.error-msg');
    if (!condition) {
        field.classList.add('error');
        field.classList.remove('valid');
        if (errorEl) { errorEl.textContent = errorMsg; errorEl.classList.add('show'); }
        return false;
    } else {
        field.classList.remove('error');
        field.classList.add('valid');
        if (errorEl) errorEl.classList.remove('show');
        return true;
    }
}

function clearError(field) {
    field.classList.remove('error');
    const errorEl = field.parentElement.querySelector('.error-msg');
    if (errorEl) errorEl.classList.remove('show');
}

// Validasi Biodata
function validateBiodata(form) {
    let valid = true;

    const nama = form.querySelector('#nama_customer, #nama_karyawan');
    const telp = form.querySelector('#no_telepon');
    const alamat = form.querySelector('#alamat');

    // Validasi Nama - wajib diisi, minimal 3 huruf
    if (nama) {
        const val = nama.value.trim();
        if (val === '') {
            validateField(nama, false, 'Nama wajib diisi!');
            valid = false;
        } else if (val.length < 3) {
            validateField(nama, false, 'Nama minimal 3 karakter!');
            valid = false;
        } else if (!/^[a-zA-Z ]+$/.test(val)) {
            validateField(nama, false, 'Nama hanya boleh huruf dan spasi!');
            valid = false;
        } else {
            validateField(nama, true, '');
        }
    }

    // Validasi Telepon - wajib diisi, hanya angka, 10-14 digit
    if (telp) {
        const val = telp.value.trim();
        if (val === '') {
            validateField(telp, false, 'Nomor telepon wajib diisi!');
            valid = false;
        } else if (!/^[0-9]+$/.test(val)) {
            validateField(telp, false, 'Hanya boleh angka!');
            valid = false;
        } else if (val.length < 10 || val.length > 14) {
            validateField(telp, false, 'Nomor telepon 10-14 digit!');
            valid = false;
        } else {
            validateField(telp, true, '');
        }
    }

    // Validasi Alamat - wajib diisi
    if (alamat) {
        const val = alamat.value.trim();
        if (val === '') {
            validateField(alamat, false, 'Alamat wajib diisi!');
            valid = false;
        } else {
            validateField(alamat, true, '');
        }
    }

    return valid;
}

// Real-time validation setup
document.addEventListener('DOMContentLoaded', function() {
    // Telepon: hanya angka
    const telpInputs = document.querySelectorAll('input[name="no_telepon"]');
    telpInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            // Hapus semua karakter non-angka
            this.value = this.value.replace(/[^0-9]/g, '');
            // Max 14 digit
            if (this.value.length > 14) this.value = this.value.slice(0, 14);
            // Clear error saat user mengetik
            clearError(this);
        });
        input.addEventListener('blur', function() {
            const valid = /^[0-9]{10,14}$/.test(this.value.trim());
            validateField(this, valid, 'Nomor telepon harus 10-14 digit angka!');
        });
    });

    // Nama: hanya huruf dan spasi
    const namaInputs = document.querySelectorAll('#nama_customer, #nama_karyawan');
    namaInputs.forEach(input => {
        input.addEventListener('input', function(e) {
            // Hapus angka dan karakter spesial
            this.value = this.value.replace(/[^a-zA-Z ]/g, '');
            clearError(this);
        });
        input.addEventListener('blur', function() {
            const valid = this.value.trim().length >= 3 && /^[a-zA-Z ]+$/.test(this.value.trim());
            validateField(this, valid, 'Nama minimal 3 karakter, hanya huruf!');
        });
    });

    // Alamat: tidak boleh kosong
    const alamatInputs = document.querySelectorAll('#alamat');
    alamatInputs.forEach(input => {
        input.addEventListener('input', function() {
            clearError(this);
        });
        input.addEventListener('blur', function() {
            validateField(this, this.value.trim().length > 0, 'Alamat tidak boleh kosong!');
        });
    });
});

// Validasi Password
function validatePassword(form) {
    const oldPass = form.querySelector('#old_password');
    const newPass = form.querySelector('#new_password');
    const confirmPass = form.querySelector('#confirm_password');
    let valid = true;

    // Validasi password lama - wajib diisi
    if (oldPass && oldPass.value.trim() === '') {
        validateField(oldPass, false, 'Password lama wajib diisi!');
        oldPass.focus(); valid = false;
    } else if (oldPass) {
        validateField(oldPass, true, '');
    }

    // Validasi password baru - wajib diisi, minimal 6
    if (newPass) {
        if (newPass.value.trim() === '') {
            validateField(newPass, false, 'Password baru wajib diisi!');
            newPass.focus(); valid = false;
        } else if (newPass.value.length < 6) {
            validateField(newPass, false, 'Password minimal 6 karakter!');
            newPass.focus(); valid = false;
        } else {
            validateField(newPass, true, '');
        }
    }

    // Validasi konfirmasi - wajib diisi, harus sama dengan password baru
    if (confirmPass) {
        if (confirmPass.value.trim() === '') {
            validateField(confirmPass, false, 'Konfirmasi password wajib diisi!');
            confirmPass.focus(); valid = false;
        } else if (newPass && confirmPass.value !== newPass.value) {
            validateField(confirmPass, false, 'Konfirmasi tidak cocok!');
            confirmPass.focus(); valid = false;
        } else {
            validateField(confirmPass, true, '');
        }
    }

    return valid;
}

// SweetAlert Toast Notification
const urlParams = new URLSearchParams(window.location.search);
const statusParam = urlParams.get('status');
const msgParam = urlParams.get('msg');

// TAMBAHKAN FUNGSI JAM DIGITAL INI DI DALAM TAG SCRIPT PALING BAWAH
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
    document.getElementById('full-date').innerText = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
}
setInterval(updateClock, 1000);
updateClock();

if (statusParam && msgParam) {
    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.addEventListener('mouseenter', Swal.stopTimer);
            toast.addEventListener('mouseleave', Swal.resumeTimer);
        }
    });

    Toast.fire({
        icon: statusParam,
        title: decodeURIComponent(msgParam),
        background: statusParam === 'success' ? '#f0fdf4' : '#fef2f2',
        color: statusParam === 'success' ? '#166534' : '#991b1b'
    });

    window.history.replaceState({}, '', window.location.pathname);
}
</script>
</body>
</html>