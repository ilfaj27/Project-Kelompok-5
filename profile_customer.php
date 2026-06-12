<?php
session_start();

// DEBUG SEMENTARA: lihat isi session SEBELUM cek_akses() dipanggil
if (isset($_GET['debug'])) {
    echo "<pre style='background:#111;color:#0f0;padding:20px;font-size:14px;'>";
    echo "Isi \$_SESSION sebelum cek_akses():

";
    print_r($_SESSION);
    echo "</pre>";
    exit();
}

// Pakai helper yang sama dengan halaman lain (booking.php dll) supaya
// logic cek login & role konsisten di semua halaman.
include 'includes/auth_helper.php';
cek_akses('customer');

// Cek ID_Customer (sebelumnya ID_Akun, sekarang tabel Akun sudah dihapus
// dan digantikan oleh tabel Customer dengan primary key ID_Customer)
$ID_Customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? $_SESSION['ID_Akun'] ?? '';
if (empty($ID_Customer)) {
    header("Location: login.php");
    exit();
}

// Include config
if (file_exists('includes/config.php')) {
    include 'includes/config.php';
} elseif (file_exists('../includes/config.php')) {
    include '../includes/config.php';
} else {
    die("Config file tidak ditemukan!");
}

$swal_status = '';
$swal_msg = '';

// ============================================================================
// UPDATE BIODATA — HANYA FIELD YANG DIPERBOLEHKAN UNTUK DIEDIT CUSTOMER
// Yang BISA diedit: Nama_Customer, Jenis_Kelamin, Tanggal_Lahir, Tempat_Lahir,
//                   Alamat, No_Telepon, Email
// Yang TIDAK BISA diedit: ID_Customer (PK), Username, Status, Kata_Sandi
// ============================================================================
if (isset($_POST['update_biodata'])) {
    $nama = trim($_POST['nama_customer'] ?? '');
    $jk = intval($_POST['jenis_kelamin'] ?? 0); // 0 = Laki-laki, 1 = Perempuan
    $alamat = trim($_POST['alamat'] ?? '');
    $telepon = trim($_POST['no_telepon'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $tgl_lahir = $_POST['tanggal_lahir'] ?? '';
    $tmp_lahir = $_POST['tempat_lahir'] ?? '';

    // --- VALIDASI ---
    $username_input = trim($_POST['username'] ?? '');

    $errors = [];
    if (empty($nama) || strlen($nama) < 3 || !preg_match('/^[a-zA-Z\s]+$/', $nama)) {
        $errors[] = 'Nama minimal 3 karakter, hanya huruf dan spasi.';
    }
    if (empty($username_input) || strlen($username_input) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username_input)) {
        $errors[] = 'Username minimal 3 karakter, hanya huruf, angka, dan underscore.';
    }
    if (empty($tgl_lahir)) {
        $errors[] = 'Tanggal lahir wajib diisi.';
    } else {
        $birthDate = new DateTime($tgl_lahir);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        if ($age < 10) {
            $errors[] = 'Usia minimal 10 tahun.';
        } elseif ($age > 100) {
            $errors[] = 'Tanggal lahir tidak valid.';
        }
    }
    if (empty($tmp_lahir) || strlen($tmp_lahir) < 3 || !preg_match('/^[a-zA-Z\s]+$/', $tmp_lahir)) {
        $errors[] = 'Tempat lahir minimal 3 karakter, hanya huruf dan spasi.';
    }
    if (empty($alamat)) {
        $errors[] = 'Alamat tidak boleh kosong.';
    }
    if (empty($telepon) || !preg_match('/^[0-9]{10,14}$/', $telepon)) {
        $errors[] = 'Nomor telepon harus 10-14 digit angka.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email tidak valid.';
    }
    if ($jk !== 0 && $jk !== 1) {
        $errors[] = 'Jenis kelamin tidak valid.';
    }

    // --- CEK KEBERADAAN EMAIL & TELEPON (unik di tabel Customer) ---
    if (empty($errors)) {
        $cek_username = sqlsrv_query($conn,
            "SELECT ID_Customer FROM Customer WHERE Username = ? AND ID_Customer != ? AND Is_Deleted = 0",
            array($username_input, $ID_Customer)
        );
        if ($cek_username && sqlsrv_fetch_array($cek_username, SQLSRV_FETCH_ASSOC)) {
            $errors[] = 'Username sudah digunakan oleh customer lain.';
        }
        $cek_email = sqlsrv_query($conn,
            "SELECT ID_Customer FROM Customer WHERE Email = ? AND ID_Customer != ? AND Is_Deleted = 0",
            array($email, $ID_Customer)
        );
        if ($cek_email && sqlsrv_fetch_array($cek_email, SQLSRV_FETCH_ASSOC)) {
            $errors[] = 'Email sudah digunakan oleh customer lain.';
        }
        $cek_telp = sqlsrv_query($conn,
            "SELECT ID_Customer FROM Customer WHERE No_Telepon = ? AND ID_Customer != ? AND Is_Deleted = 0",
            array($telepon, $ID_Customer)
        );
        if ($cek_telp && sqlsrv_fetch_array($cek_telp, SQLSRV_FETCH_ASSOC)) {
            $errors[] = 'Nomor telepon sudah digunakan oleh customer lain.';
        }
    }

    if (empty($errors)) {
        // UPDATE field yang diperbolehkan — TIDAK menyentuh ID_Customer (PK) dan Status
        $modified_by = $_SESSION['nama'] ?? 'SYSTEM';
        $stmt = sqlsrv_query($conn,
            "UPDATE Customer SET
                Nama_Customer = ?,
                Username = ?,
                Jenis_Kelamin = ?,
                Tanggal_Lahir = ?,
                Tempat_Lahir = ?,
                Alamat = ?,
                No_Telepon = ?,
                Email = ?,
                Modified_By = ?,
                Modified_Date = GETDATE()
             WHERE ID_Customer = ?",
            array($nama, $username_input, $jk, $tgl_lahir, $tmp_lahir, $alamat, $telepon, $email, $modified_by, $ID_Customer)
        );
        if ($stmt) {
            while (sqlsrv_next_result($stmt)) {}
            sqlsrv_free_stmt($stmt);
            $_SESSION['nama'] = $nama;
            $_SESSION['nama_user'] = $nama;
            session_write_close();
            $swal_status = 'success';
            $swal_msg = 'Biodata berhasil diperbarui!';
        } else {
            $swal_status = 'error';
            $swal_msg = 'Gagal memperbarui biodata.';
        }
    } else {
        $swal_status = 'error';
        $swal_msg = implode("\n", $errors);
    }
}

// ============================================================================
// UPDATE PASSWORD — Terpisah dari biodata, hanya update Kata_Sandi
// ============================================================================
if (isset($_POST['update_password'])) {
    $old_pass = trim($_POST['old_password'] ?? '');
    $new_pass = trim($_POST['new_password'] ?? '');
    $confirm_pass = trim($_POST['confirm_password'] ?? '');

    $res = sqlsrv_query($conn, "SELECT Kata_Sandi FROM Customer WHERE ID_Customer = ?", array($ID_Customer));
    $custData = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);

    if ($old_pass !== ($custData['Kata_Sandi'] ?? '')) {
        $swal_status = 'error';
        $swal_msg = 'Password lama tidak sesuai.';
    } elseif (strlen($new_pass) < 6) {
        $swal_status = 'error';
        $swal_msg = 'Password baru minimal 6 karakter.';
    } elseif ($new_pass !== $confirm_pass) {
        $swal_status = 'error';
        $swal_msg = 'Konfirmasi password tidak cocok.';
    } else {
        $modified_by = $_SESSION['nama'] ?? 'SYSTEM';
        $stmt = sqlsrv_query($conn,
            "UPDATE Customer SET Kata_Sandi = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Customer = ?",
            array($new_pass, $modified_by, $ID_Customer)
        );
        if ($stmt) {
            while (sqlsrv_next_result($stmt)) {}
            sqlsrv_free_stmt($stmt);
            $swal_status = 'success';
            $swal_msg = 'Password berhasil diperbarui!';
        } else {
            $swal_status = 'error';
            $swal_msg = 'Gagal memperbarui password.';
        }
    }
}

// ============================================================================
// UPLOAD FOTO — Disimpan di server & session (tidak ke DB karena belum ada kolom)
// ============================================================================
if (isset($_POST['update_photo']) && isset($_FILES['photo'])) {
    $file = $_FILES['photo'];
    $allowed = ['image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 2 * 1024 * 1024;

    if ($file['error'] === 0) {
        if (in_array($file['type'], $allowed) && $file['size'] <= $max_size) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $ID_Customer . '_' . time() . '.' . $ext;
            $upload_dir = 'uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $upload_path = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                $_SESSION['Profile_Photo'] = $upload_path;
                $swal_status = 'success';
                $swal_msg = 'Foto profil berhasil diperbarui!';
            } else {
                $swal_status = 'error';
                $swal_msg = 'Gagal mengunggah foto.';
            }
        } else {
            $swal_status = 'error';
            $swal_msg = 'File harus JPG/PNG dan maksimal 2MB.';
        }
    }
}

// ============================================================================
// AMBIL DATA CUSTOMER — ID_Customer dari session, tidak bisa diubah user
// ============================================================================
$res_cust = sqlsrv_query($conn, "SELECT * FROM Customer WHERE ID_Customer = ? AND Is_Deleted = 0", array($ID_Customer));
$biodata = sqlsrv_fetch_array($res_cust, SQLSRV_FETCH_ASSOC);

$profile_photo = $_SESSION['Profile_Photo'] ?? '';
if (empty($profile_photo) || !file_exists($profile_photo)) {
    $profile_photo = '';
}

$nama = $biodata['Nama_Customer'] ?? $_SESSION['nama'] ?? 'Customer';
$username = $biodata['Username'] ?? '-';
$email = $biodata['Email'] ?? '-';
$telepon = $biodata['No_Telepon'] ?? '-';
$alamat = $biodata['Alamat'] ?? '-';
$jk = $biodata['Jenis_Kelamin'] ?? 0;
$tgl_lahir = $biodata['Tanggal_Lahir'] ?? '';
$tmp_lahir = $biodata['Tempat_Lahir'] ?? '';
$status = $biodata['Status'] ?? 1;

// Jenis_Kelamin sesuai CHECK (0,1) pada tabel Customer: 0 = Laki-laki, 1 = Perempuan
function jk_label($jk) {
    return $jk == 0 ? 'Laki-laki' : ($jk == 1 ? 'Perempuan' : '-');
}

function format_date_input($date) {
    if (empty($date)) return '';
    if (is_object($date) && method_exists($date, 'format')) {
        return $date->format('Y-m-d');
    }
    return $date;
}

function format_date_display($date) {
    if (empty($date)) return '-';
    if (is_object($date) && method_exists($date, 'format')) {
        return $date->format('d F Y');
    }
    return $date;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya | Hoop Arena</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --orange: #FF6B00;
            --orange-light: #FF8C2A;
            --blue: #0061FF;
            --blue-dark: #0047CC;
            --black: #0A0A0A;
            --dark: #111118;
            --dark-card: #1A1A24;
            --dark-border: #2A2A35;
            --white: #FFFFFF;
            --gray-100: #F8F9FA;
            --gray-200: #E9ECEF;
            --gray-500: #888;
            --gray-600: #666;
            --green: #16A34A;
            --green-bg: #F0FDF4;
            --green-border: #DCFCE7;
            --red: #DC2626;
            --red-bg: #FEF2F2;
            --red-border: #FEE2E2;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Barlow', sans-serif; background: var(--black); color: var(--white); overflow-x: hidden; min-height: 100vh; }

        /* NAVBAR */
        nav {
            background: var(--black);
            padding: 0 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 68px;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 2px solid #1a1a1a;
        }
        .nav-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            height: 68px;
            padding: 8px 0;
        }
        .nav-logo-img {
            height: 100%;
            width: auto;
            max-width: 180px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
            transition: transform 0.3s ease;
        }
        .nav-logo:hover .nav-logo-img {
            transform: scale(1.05);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 0;
        }
        .nav-links a {
            color: #888;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0 18px;
            height: 68px;
            display: flex;
            align-items: center;
            border-bottom: 2px solid transparent;
            transition: 0.2s;
        }
        .nav-links a:hover { color: #fff; }
        .nav-links a.active { color: #fff; border-bottom-color: var(--orange); }

        /* USER DROPDOWN */
        .nav-user-container {
            position: relative;
            height: 68px;
            display: flex;
            align-items: center;
        }
        .nav-user {
            color: #fff;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 10px;
            transition: 0.2s;
        }
        .nav-user:hover { color: var(--orange); }
        .nav-user i.arrow { font-size: 11px; color: #888; transition: 0.3s; }
        .nav-user-container:hover i.arrow { transform: rotate(180deg); color: var(--orange); }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: #151515;
            min-width: 200px;
            border-radius: 12px;
            border: 1px solid #333;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            padding: 10px 0;
            display: none;
            z-index: 1001;
            transform: translateY(10px);
            transition: 0.3s;
        }
        .nav-user-container:hover .dropdown-menu {
            display: block;
            transform: translateY(0);
        }
        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: #bbb;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s;
        }
        .dropdown-menu a i { font-size: 14px; width: 16px; text-align: center; }
        .dropdown-menu a:hover { background: #222; color: var(--orange); }
        .dropdown-menu a.active { color: var(--orange); background: rgba(255,107,0,0.1); }
        .dropdown-divider { height: 1px; background: #333; margin: 8px 0; }
        .dropdown-menu a.logout:hover { color: var(--red); }

        /* PROFILE HERO */
        .profile-hero {
            background: linear-gradient(135deg, #1a1a24 0%, #0f0f16 100%);
            border-bottom: 3px solid var(--orange);
            padding: 50px 60px;
            display: flex;
            align-items: center;
            gap: 40px;
            position: relative;
            overflow: hidden;
        }
        .profile-hero::before {
            content: '';
            position: absolute;
            right: -100px;
            top: -100px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,107,0,0.12) 0%, transparent 70%);
        }

        .photo-section { position: relative; z-index: 1; flex-shrink: 0; }
        .photo-wrapper {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            border: 4px solid var(--orange);
            padding: 4px;
            position: relative;
            cursor: pointer;
            transition: all .3s ease;
            background: var(--dark);
        }
        .photo-wrapper:hover { transform: scale(1.05); box-shadow: 0 0 40px rgba(255,107,0,0.3); }
        .photo-wrapper:hover .photo-overlay { opacity: 1; }
        .photo-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
            background: linear-gradient(135deg, var(--orange), #ff8c2a);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 56px;
            font-weight: 800;
            font-family: 'Barlow Condensed', sans-serif;
        }
        .photo-overlay {
            position: absolute;
            inset: 4px;
            border-radius: 50%;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: .3s;
        }
        .photo-overlay i { color: #fff; font-size: 28px; }
        .photo-input { display: none; }
        .photo-label { cursor: pointer; }
        .photo-badge {
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--orange);
            color: #fff;
            font-size: 10px;
            font-weight: 800;
            padding: 5px 14px;
            border-radius: 20px;
            white-space: nowrap;
            z-index: 2;
            letter-spacing: 0.5px;
        }

        .hero-info { position: relative; z-index: 1; flex: 1; }
        .hero-label {
            color: var(--orange);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 8px;
            display: block;
        }
        .hero-name {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 42px;
            font-weight: 900;
            color: #fff;
            text-transform: uppercase;
            letter-spacing: 1px;
            line-height: 1.1;
        }
        .hero-role {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(255,107,0,0.15);
            border: 1px solid rgba(255,107,0,0.3);
            color: var(--orange);
            font-size: 12px;
            font-weight: 800;
            padding: 6px 16px;
            border-radius: 20px;
            text-transform: uppercase;
            margin-top: 12px;
            letter-spacing: 1px;
        }
        .hero-meta {
            display: flex;
            gap: 24px;
            margin-top: 16px;
            flex-wrap: wrap;
        }
        .hero-meta-item {
            font-size: 13px;
            color: #888;
            font-weight: 600;
        }
        .hero-meta-item span { color: var(--orange); font-weight: 800; }

        /* MAIN CONTENT */
        .main { padding: 50px 60px; max-width: 1200px; margin: 0 auto; }

        /* Section Title */
        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .section-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 26px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: #fff;
        }
        .section-title::after {
            content: '';
            display: block;
            width: 40px;
            height: 3px;
            background: var(--orange);
            border-radius: 2px;
        }

        /* CARDS */
        .profile-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        @media(max-width: 900px) { .profile-grid { grid-template-columns: 1fr; } }

        .p-card {
            background: var(--dark-card);
            border-radius: 16px;
            border: 1px solid var(--dark-border);
            overflow: hidden;
            transition: all .3s ease;
        }
        .p-card:hover { border-color: rgba(255,107,0,0.3); }
        .p-card-wide { grid-column: 1 / -1; }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--dark-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .card-title {
            font-size: 15px;
            font-weight: 800;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .card-title i { color: var(--orange); font-size: 16px; }
        .card-badge {
            background: rgba(255,107,0,0.15);
            color: var(--orange);
            font-size: 11px;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .card-badge.green {
            background: rgba(22,163,74,0.15);
            color: var(--green);
        }
        .card-badge.red {
            background: rgba(220,38,38,0.15);
            color: var(--red);
        }
        .card-body { padding: 24px; }

        /* FORM */
        .form-group { margin-bottom: 20px; }
        .form-group:last-child { margin-bottom: 0; }
        .form-label {
            display: block;
            font-size: 11px;
            font-weight: 800;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }
        .form-label .required { color: var(--red); margin-left: 2px; }
        .form-input {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid var(--dark-border);
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Barlow', sans-serif;
            color: #fff;
            outline: none;
            transition: all .2s;
            background: var(--black);
        }
        .form-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px rgba(255,107,0,0.1); }
        .form-input::placeholder { color: #555; }
        select.form-input {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23888' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 16px center;
            padding-right: 40px;
        }
        textarea.form-input { resize: vertical; min-height: 80px; }

        /* Read-only display */
        .form-display {
            width: 100%;
            padding: 14px 16px;
            border: 1.5px solid var(--dark-border);
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Barlow', sans-serif;
            color: #888;
            background: var(--black);
            cursor: not-allowed;
            user-select: none;
        }
        .form-display .lock-icon {
            color: var(--orange);
            margin-right: 8px;
            font-size: 12px;
        }

        /* Validasi */
        .form-input.error { border-color: var(--red) !important; box-shadow: 0 0 0 3px rgba(220,38,38,0.1) !important; }
        .form-input.valid { border-color: var(--green) !important; box-shadow: 0 0 0 3px rgba(22,163,74,0.1) !important; }
        .error-msg {
            font-size: 12px;
            color: var(--red);
            margin-top: 6px;
            font-weight: 600;
            display: none;
        }
        .error-msg.show { display: block; }

        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
        .form-row-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
        @media(max-width: 900px) { .form-row-3 { grid-template-columns: 1fr 1fr; } }
        @media(max-width: 600px) { .form-row, .form-row-3 { grid-template-columns: 1fr; } }

        .btn-save {
            width: 100%;
            padding: 16px;
            border: none;
            background: var(--orange);
            color: #fff;
            font-weight: 800;
            font-size: 14px;
            border-radius: 10px;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all .2s;
            font-family: 'Barlow Condensed', sans-serif;
        }
        .btn-save:hover { background: var(--orange-light); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(255,107,0,0.3); }
        .btn-save:active { transform: translateY(0); }

        /* INFO ROWS */
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
            border-bottom: 1px solid var(--dark-border);
        }
        .info-row:last-child { border-bottom: none; }
        .info-key {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 700;
            color: var(--gray-500);
        }
        .info-key i { color: var(--orange); font-size: 14px; width: 20px; text-align: center; }
        .info-val { font-size: 14px; font-weight: 700; color: #fff; text-align: right; }
        .info-val.highlight { color: var(--orange); }
        .info-val.muted { color: #666; font-style: italic; }
        .info-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
        }
        .badge-active { background: rgba(22,163,74,0.15); color: var(--green); }
        .badge-inactive { background: rgba(220,38,38,0.15); color: var(--red); }
        .badge-locked {
            background: rgba(136,136,136,0.15);
            color: #888;
            font-size: 10px;
            padding: 2px 8px;
        }

        /* Password */
        .password-mask { display: flex; align-items: center; gap: 8px; }
        .password-dots { font-size: 18px; letter-spacing: 3px; color: var(--gray-500); }
        .btn-toggle-pass { background: none; border: none; color: var(--gray-500); cursor: pointer; font-size: 14px; padding: 4px; transition: .2s; }
        .btn-toggle-pass:hover { color: var(--orange); }

        /* Footer */
        footer {
            background: var(--black);
            padding: 30px 60px;
            text-align: center;
            border-top: 1px solid #1a1a1a;
            margin-top: 60px;
        }
        footer p { color: #444; font-size: 13px; font-weight: 500; }

        /* Swal Dark */
        .swal2-popup.swal-dark { background: #151515 !important; color: #fff !important; border: 1px solid #333 !important; }
        .swal2-popup.swal-dark .swal2-title { color: #fff !important; }

        /* Input Date Dark Theme */
        input[type="date"].form-input {
            color-scheme: dark;
            font-family: 'Barlow', sans-serif;
        }
        input[type="date"].form-input::-webkit-calendar-picker-indicator {
            filter: invert(0.6);
            cursor: pointer;
        }

        /* Tooltip for locked fields */
        .locked-hint {
            font-size: 11px;
            color: #666;
            margin-top: 4px;
            font-style: italic;
        }
</style>
</head>
<body>

<!-- NAVBAR -->
<nav>
    <a href="view_customer.php" class="nav-logo">
        <img src="logo.png" alt="HoopBall" class="nav-logo-img">
    </a>
    <div class="nav-links">
        <a href="view_customer.php">Beranda</a>
        <a href="lapangan.php">Lapangan</a>
        <a href="jadwal.php">Jadwal</a>
        <a href="booking.php">Booking</a>
        <a href="promo.php">Promo</a>
    </div>
    <div class="nav-user-container">
        <div class="nav-user">
            <i class="fa-regular fa-circle-user"></i>
            <i class="fa-solid fa-chevron-down arrow"></i>
        </div>
        <div class="dropdown-menu">
            <a href="profile_customer.php" class="active"><i class="fa-solid fa-user"></i> Profil Saya</a>
            <a href="riwayat.php"><i class="fa-solid fa-calendar-check"></i> Riwayat Booking</a>
            <div class="dropdown-divider"></div>
            <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
        </div>
    </div>
</nav>

<!-- PROFILE HERO -->
<div class="profile-hero">
    <div class="photo-section">
        <form method="POST" enctype="multipart/form-data" id="photoForm">
            <label class="photo-label">
                <div class="photo-wrapper">
                    <?php if ($profile_photo): ?>
                        <img src="<?= $profile_photo ?>" alt="Profile" class="photo-img">
                    <?php else: ?>
                        <div class="photo-img"><?= strtoupper(substr($nama, 0, 1)) ?></div>
                    <?php endif; ?>
                    <div class="photo-overlay"><i class="fa-solid fa-camera"></i></div>
                </div>
                <span class="photo-badge"><i class="fa-solid fa-camera" style="font-size:9px;"></i> GANTI FOTO</span>
                <input type="file" name="photo" class="photo-input" accept="image/jpeg,image/png,image/jpg" onchange="document.getElementById('photoForm').submit();">
            </label>
            <input type="hidden" name="update_photo" value="1">
        </form>
    </div>
    <div class="hero-info">
        <span class="hero-label">Profil Customer</span>
        <div class="hero-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
        <div class="hero-role"><i class="fa-solid fa-shield-halved"></i> CUSTOMER</div>
        <div class="hero-meta">
            <div class="hero-meta-item">ID Customer: <span><?= htmlspecialchars($ID_Customer) ?></span></div>
            <div class="hero-meta-item">Email: <span><?= htmlspecialchars($email) ?></span></div>
        </div>
    </div>
</div>

<!-- MAIN CONTENT -->
<main class="main">
    <div class="profile-grid">
        <!-- BIODATA — HANYA FIELD YANG BOLEH DIEDIT -->
        <div class="p-card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-address-card"></i> Biodata Diri</div>
                <span class="card-badge green"><i class="fa-solid fa-pen-to-square" style="font-size:10px;"></i> Bisa Diedit</span>
            </div>
            <div class="card-body">
                <form method="POST" id="formBiodata">
                    <!-- ID Customer — TIDAK BOLEH DIEDIT (Read-only display) -->
                    <div class="form-group">
                        <label class="form-label">ID Customer <span class="badge-locked"><i class="fa-solid fa-lock"></i> TIDAK BISA DIEDIT</span></label>
                        <div class="form-display">
                            <i class="fa-solid fa-lock lock-icon"></i><?= htmlspecialchars($ID_Customer) ?>
                        </div>
                        <div class="locked-hint">ID Customer adalah primary key dan tidak dapat diubah.</div>
                    </div>

                    <!-- Username — BISA DIEDIT -->
                    <div class="form-group">
                        <label class="form-label">Username <span class="required">*</span></label>
                        <input type="text" name="username" id="username" class="form-input"
                               value="<?= htmlspecialchars($username) ?>"
                               placeholder="Masukkan username" autocomplete="off" maxlength="20">
                        <div class="error-msg" id="usernameError">Username minimal 3 karakter, hanya huruf, angka, dan underscore.</div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                            <input type="text" name="nama_customer" id="nama_customer" class="form-input"
                                   value="<?= htmlspecialchars($biodata['Nama_Customer'] ?? '') ?>"
                                   placeholder="Masukkan nama lengkap" autocomplete="off">
                            <div class="error-msg" id="namaError">Nama minimal 3 karakter, hanya huruf dan spasi.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Jenis Kelamin <span class="required">*</span></label>
                            <select name="jenis_kelamin" id="jenis_kelamin" class="form-input">
                                <option value="0" <?= ($jk == 0) ? 'selected' : '' ?>>Laki-laki</option>
                                <option value="1" <?= ($jk == 1) ? 'selected' : '' ?>>Perempuan</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Tanggal Lahir <span class="required">*</span></label>
                            <input type="date" name="tanggal_lahir" id="tanggal_lahir" class="form-input"
                                   value="<?= format_date_input($tgl_lahir) ?>"
                                   placeholder="Pilih tanggal lahir">
                            <div class="error-msg" id="tglLahirError">Tanggal lahir wajib diisi, usia minimal 10 tahun.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tempat Lahir <span class="required">*</span></label>
                            <input type="text" name="tempat_lahir" id="tempat_lahir" class="form-input"
                                   value="<?= htmlspecialchars($tmp_lahir) ?>"
                                   placeholder="Contoh: Jakarta, Bekasi" autocomplete="off">
                            <div class="error-msg" id="tmpLahirError">Tempat lahir minimal 3 karakter, hanya huruf dan spasi.</div>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email <span class="required">*</span></label>
                        <input type="email" name="email" id="email" class="form-input"
                               value="<?= htmlspecialchars($email) ?>"
                               placeholder="Contoh: email@domain.com" autocomplete="off">
                        <div class="error-msg" id="emailError">Email tidak valid.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Alamat Lengkap <span class="required">*</span></label>
                        <textarea name="alamat" id="alamat" class="form-input" placeholder="Masukkan alamat lengkap"><?= htmlspecialchars($alamat) ?></textarea>
                        <div class="error-msg" id="alamatError">Alamat tidak boleh kosong.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nomor Telepon <span class="required">*</span></label>
                        <input type="tel" name="no_telepon" id="no_telepon" class="form-input"
                               value="<?= htmlspecialchars($telepon) ?>"
                               maxlength="14" placeholder="Contoh: 08123456789">
                        <div class="error-msg" id="teleponError">Nomor telepon harus 10-14 digit angka.</div>
                    </div>

                    <!-- Status — TIDAK BOLEH DIEDIT (Read-only display) -->
                    <div class="form-group">
                        <label class="form-label">Status Akun <span class="badge-locked"><i class="fa-solid fa-lock"></i> TIDAK BISA DIEDIT</span></label>
                        <div class="form-display" style="display:flex; align-items:center; gap:8px;">
                            <?php if ($status == 1): ?>
                                <span class="info-badge badge-active"><i class="fa-solid fa-check-circle"></i> Aktif</span>
                            <?php else: ?>
                                <span class="info-badge badge-inactive"><i class="fa-solid fa-circle-xmark"></i> Non-Aktif</span>
                            <?php endif; ?>
                            <span style="color:#666; font-size:12px;">— Dikontrol oleh sistem</span>
                        </div>
                        <div class="locked-hint">Status akun dikelola oleh admin dan tidak dapat diubah sendiri.</div>
                    </div>

                    <button type="submit" name="update_biodata" class="btn-save">
                        <i class="fa-solid fa-floppy-disk"></i> SIMPAN PERUBAHAN
                    </button>
                </form>
            </div>
        </div>

        <!-- INFORMASI AKUN — READ ONLY -->
        <div class="p-card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-shield-halved"></i> Informasi Akun</div>
                <span class="card-badge"><i class="fa-solid fa-circle-check" style="font-size:10px;"></i> Detail</span>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-fingerprint"></i> ID Customer</span>
                    <span class="info-val muted"><?= htmlspecialchars($ID_Customer) ?> <span class="badge-locked"><i class="fa-solid fa-lock"></i></span></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-user-tag"></i> Username</span>
                    <span class="info-val"><?= htmlspecialchars($username) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-envelope"></i> Email</span>
                    <span class="info-val"><?= htmlspecialchars($email) ?></span>
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
                    <span class="info-key"><i class="fa-solid fa-cake-candles"></i> Tanggal Lahir</span>
                    <span class="info-val"><?= format_date_display($tgl_lahir) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-location-dot"></i> Tempat Lahir</span>
                    <span class="info-val"><?= htmlspecialchars($tmp_lahir) ?: '-' ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-venus-mars"></i> Jenis Kelamin</span>
                    <span class="info-val"><?= jk_label($jk) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-user-shield"></i> Role</span>
                    <span class="info-val"><span class="info-badge badge-active"><i class="fa-solid fa-check-circle"></i> Customer</span></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-circle-check"></i> Status</span>
                    <span class="info-val">
                        <?php if ($status == 1): ?>
                            <span class="info-badge badge-active"><i class="fa-solid fa-check-circle"></i> Aktif</span>
                        <?php else: ?>
                            <span class="info-badge badge-inactive"><i class="fa-solid fa-circle-xmark"></i> Non-Aktif</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- PASSWORD — Terpisah, hanya update Kata_Sandi -->
        <div class="p-card p-card-wide">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-lock"></i> Keamanan — Ubah Password</div>
            </div>
            <div class="card-body">
                <form method="POST" id="formPassword">
                    <div class="form-row-3">
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Password Lama <span class="required">*</span></label>
                            <input type="password" name="old_password" id="old_password" class="form-input" placeholder="Password saat ini">
                            <div class="error-msg" id="oldPassError">Password lama wajib diisi.</div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Password Baru <span class="required">*</span></label>
                            <input type="password" name="new_password" id="new_password" class="form-input" placeholder="Minimal 6 karakter">
                            <div class="error-msg" id="newPassError">Password baru minimal 6 karakter.</div>
                        </div>
                        <div class="form-group" style="margin-bottom:0;">
                            <label class="form-label">Konfirmasi <span class="required">*</span></label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="Ulangi password">
                            <div class="error-msg" id="confirmPassError">Konfirmasi tidak cocok.</div>
                        </div>
                    </div>
                    <div style="margin-top: 20px;">
                        <button type="submit" name="update_password" class="btn-save" style="max-width: 220px;">
                            <i class="fa-solid fa-key"></i> PERBARUI PASSWORD
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<footer>
    <p>© 2024 Hoop Arena. All rights reserved.</p>
</footer>

<script>
// SweetAlert
<?php if ($swal_status && $swal_msg): ?>
Swal.fire({
    icon: '<?= $swal_status ?>',
    title: '<?= $swal_status === 'success' ? 'Berhasil!' : 'Gagal!' ?>',
    text: '<?= addslashes($swal_msg) ?>',
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true,
    background: '#151515',
    color: '#fff',
    iconColor: '<?= $swal_status === 'success' ? '#16A34A' : '#DC2626' ?>',
    customClass: { popup: 'swal-dark' }
});
<?php endif; ?>

// Toggle Password
let passVisible = false;
const realPass = '<?= addslashes($biodata['Kata_Sandi'] ?? '') ?>';
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

// Validasi Realtime
const namaInput = document.getElementById('nama_customer');
const usernameInput = document.getElementById('username');
const tglLahirInput = document.getElementById('tanggal_lahir');
const tmpLahirInput = document.getElementById('tempat_lahir');
const teleponInput = document.getElementById('no_telepon');
const alamatInput = document.getElementById('alamat');
const emailInput = document.getElementById('email');

if (namaInput) {
    namaInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
        validateNama();
    });
    namaInput.addEventListener('blur', validateNama);
}

// Username validation
if (usernameInput) {
    usernameInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^a-zA-Z0-9_]/g, '');
        validateUsername();
    });
    usernameInput.addEventListener('blur', validateUsername);
}

function validateUsername() {
    if (!usernameInput) return true;
    const val = usernameInput.value.trim();
    const error = document.getElementById('usernameError');
    if (val.length < 3 || !/^[a-zA-Z0-9_]+$/.test(val)) {
        usernameInput.classList.add('error'); usernameInput.classList.remove('valid'); error.classList.add('show'); return false;
    } else {
        usernameInput.classList.remove('error'); usernameInput.classList.add('valid'); error.classList.remove('show'); return true;
    }
}

// Filter Tempat Lahir - hanya huruf dan spasi
if (tmpLahirInput) {
    tmpLahirInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
        validateTmpLahir();
    });
    tmpLahirInput.addEventListener('blur', validateTmpLahir);
}

// Email validation
if (emailInput) {
    emailInput.addEventListener('input', validateEmail);
    emailInput.addEventListener('blur', validateEmail);
}

function validateEmail() {
    if (!emailInput) return true;
    const val = emailInput.value.trim();
    const error = document.getElementById('emailError');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (val === '' || !emailRegex.test(val)) {
        emailInput.classList.add('error'); 
        emailInput.classList.remove('valid'); 
        error.classList.add('show'); 
        return false;
    } else {
        emailInput.classList.remove('error'); 
        emailInput.classList.add('valid'); 
        error.classList.remove('show'); 
        return true;
    }
}

function validateTglLahir() {
    if (!tglLahirInput) return true;
    const val = tglLahirInput.value.trim();
    const error = document.getElementById('tglLahirError');
    if (val === '') {
        tglLahirInput.classList.add('error'); tglLahirInput.classList.remove('valid'); error.classList.add('show'); return false;
    }
    const birthDate = new Date(val);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) age--;

    if (age < 10) {
        error.textContent = 'Usia minimal 10 tahun.';
        tglLahirInput.classList.add('error'); tglLahirInput.classList.remove('valid'); error.classList.add('show'); return false;
    } else if (age > 100) {
        error.textContent = 'Tanggal lahir tidak valid.';
        tglLahirInput.classList.add('error'); tglLahirInput.classList.remove('valid'); error.classList.add('show'); return false;
    } else {
        tglLahirInput.classList.remove('error'); tglLahirInput.classList.add('valid'); error.classList.remove('show'); return true;
    }
}

function validateTmpLahir() {
    if (!tmpLahirInput) return true;
    const val = tmpLahirInput.value.trim();
    const error = document.getElementById('tmpLahirError');
    if (val === '' || val.length < 3 || !/^[a-zA-Z\s]+$/.test(val)) {
        tmpLahirInput.classList.add('error'); tmpLahirInput.classList.remove('valid'); error.classList.add('show'); return false;
    } else {
        tmpLahirInput.classList.remove('error'); tmpLahirInput.classList.add('valid'); error.classList.remove('show'); return true;
    }
}
function validateNama() {
    if (!namaInput) return true;
    const val = namaInput.value.trim();
    const error = document.getElementById('namaError');
    if (val.length < 3 || !/^[a-zA-Z\s]+$/.test(val)) {
        namaInput.classList.add('error'); namaInput.classList.remove('valid'); error.classList.add('show'); return false;
    } else {
        namaInput.classList.remove('error'); namaInput.classList.add('valid'); error.classList.remove('show'); return true;
    }
}

if (teleponInput) {
    teleponInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        validateTelepon();
    });
    teleponInput.addEventListener('blur', validateTelepon);
}
function validateTelepon() {
    if (!teleponInput) return true;
    const val = teleponInput.value.trim();
    const error = document.getElementById('teleponError');
    if (!/^[0-9]{10,14}$/.test(val)) {
        teleponInput.classList.add('error'); teleponInput.classList.remove('valid'); error.classList.add('show'); return false;
    } else {
        teleponInput.classList.remove('error'); teleponInput.classList.add('valid'); error.classList.remove('show'); return true;
    }
}

if (alamatInput) {
    alamatInput.addEventListener('input', validateAlamat);
    alamatInput.addEventListener('blur', validateAlamat);
}
function validateAlamat() {
    if (!alamatInput) return true;
    const val = alamatInput.value.trim();
    const error = document.getElementById('alamatError');
    if (val === '') {
        alamatInput.classList.add('error'); alamatInput.classList.remove('valid'); error.classList.add('show'); return false;
    } else {
        alamatInput.classList.remove('error'); alamatInput.classList.add('valid'); error.classList.remove('show'); return true;
    }
}

// Form Submit Validation
const formBiodata = document.getElementById('formBiodata');
if (formBiodata) {
    formBiodata.addEventListener('submit', function(e) {
        let valid = true;
        if (!validateNama()) valid = false;
        if (!validateUsername()) valid = false;
        if (!validateTglLahir()) valid = false;
        if (!validateTmpLahir()) valid = false;
        if (!validateAlamat()) valid = false;
        if (!validateTelepon()) valid = false;
        if (!validateEmail()) valid = false;
        if (!valid) {
            e.preventDefault();
            Swal.fire({
                icon: 'error', title: 'Validasi Gagal', text: 'Mohon periksa kembali data yang diisi',
                background: '#151515', color: '#fff', confirmButtonColor: '#FF6B00',
                customClass: { popup: 'swal-dark' }
            });
        }
    });
}

// Password Validation
const oldPass = document.getElementById('old_password');
const newPass = document.getElementById('new_password');
const confirmPass = document.getElementById('confirm_password');

function validateOldPass() {
    if (!oldPass) return true;
    const error = document.getElementById('oldPassError');
    if (!oldPass.value) { oldPass.classList.add('error'); error.classList.add('show'); return false; }
    else { oldPass.classList.remove('error'); error.classList.remove('show'); return true; }
}
function validateNewPass() {
    if (!newPass) return true;
    const val = newPass.value;
    const error = document.getElementById('newPassError');
    if (val.length < 6) { newPass.classList.add('error'); error.classList.add('show'); return false; }
    else { newPass.classList.remove('error'); error.classList.remove('show'); return true; }
}
function validateConfirm() {
    if (!confirmPass || !newPass) return true;
    const error = document.getElementById('confirmPassError');
    if (confirmPass.value !== newPass.value || !confirmPass.value) { confirmPass.classList.add('error'); error.classList.add('show'); return false; }
    else { confirmPass.classList.remove('error'); error.classList.remove('show'); return true; }
}

if (oldPass) oldPass.addEventListener('blur', validateOldPass);
if (newPass) { newPass.addEventListener('input', validateNewPass); newPass.addEventListener('blur', validateNewPass); }
if (confirmPass) { confirmPass.addEventListener('input', validateConfirm); confirmPass.addEventListener('blur', validateConfirm); }

const formPassword = document.getElementById('formPassword');
if (formPassword) {
    formPassword.addEventListener('submit', function(e) {
        let valid = true;
        if (!validateOldPass()) valid = false;
        if (!validateNewPass()) valid = false;
        if (!validateConfirm()) valid = false;
        if (!valid) {
            e.preventDefault();
            Swal.fire({
                icon: 'error', title: 'Validasi Gagal', text: 'Mohon periksa kembali password yang diisi',
                background: '#151515', color: '#fff', confirmButtonColor: '#FF6B00',
                customClass: { popup: 'swal-dark' }
            });
        }
    });
}
</script>
</body>
</html>