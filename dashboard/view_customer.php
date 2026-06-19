<?php
// ============================================================================
// BUFFER OUTPUT — Agar header() bisa dipanggil kapan saja tanpa error
// ============================================================================
ob_start();

session_start();
include '../includes/auth_helper.php';
include '../includes/config.php'; // Berisi koneksi $conn menggunakan sqlsrv

// ============================================================================
// HARD DELETE AKUN CUSTOMER — Soft Delete di DB, Hard Delete di Program
// ============================================================================
if (isset($_GET['hapus_akun']) && $_GET['hapus_akun'] == '1') {
    $id_customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';

    if (!empty($id_customer)) {
        $modified_by = $_SESSION['nama'] ?? 'CUSTOMER';

        $stmt = sqlsrv_query($conn, 
            "UPDATE Customer SET 
                Is_Deleted = 1, 
                Status = 0, 
                Deleted_By = ?, 
                Deleted_Date = GETDATE() 
             WHERE ID_Customer = ? AND Is_Deleted = 0", 
            array($modified_by, $id_customer)
        );

        if ($stmt) {
            $_SESSION = array();
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params['path'], $params['domain'],
                    $params['secure'], $params['httponly']
                );
            }
            session_destroy();
            setcookie('remember_me', '', time() - 3600, "/");
            ob_end_clean();
            header("Location: ../login/login.php?status=success&msg=Akun Anda telah dihapus permanen. Anda harus mendaftar ulang untuk menggunakan layanan kami.");
            exit();
        } else {
            ob_end_clean();
            header("Location: view_customer.php?status=error&msg=Gagal menghapus akun. Silakan coba lagi.");
            exit();
        }
    } else {
        ob_end_clean();
        header("Location: ../login/login.php?status=error&msg=Sesi tidak valid. Silakan login kembali.");
        exit();
    }
}

// ============================================================================
// CEK AKSES
// ============================================================================
cek_akses('customer');

// ============================================================================
// AMBIL DATA CUSTOMER DARI DATABASE SECARA DINAMIS
// ============================================================================
$id_customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';
$nama_customer = 'Pelanggan';
$photo_profile = '';

if (!empty($id_customer)) {
    $cek_deleted = sqlsrv_query($conn, 
        "SELECT Nama_Customer, Photo_Profile, Is_Deleted, Status FROM Customer WHERE ID_Customer = ?", 
        array($id_customer)
    );
    if ($cek_deleted) {
        $row_cust = sqlsrv_fetch_array($cek_deleted, SQLSRV_FETCH_ASSOC);
        if ($row_cust) {
            if ($row_cust['Is_Deleted'] == 1 || $row_cust['Status'] == 0) {
                $_SESSION = array();
                session_destroy();
                setcookie('remember_me', '', time() - 3600, "/");
                ob_end_clean();
                header("Location: ../login/login.php?status=error&msg=Akun Anda telah dihapus atau dinonaktifkan. Silakan hubungi admin atau daftar ulang.");
                exit();
            }
            $nama_customer = $row_cust['Nama_Customer'];
            $photo_profile = $row_cust['Photo_Profile'];
        }
    }
}

// ============================================================================
// CHECK MEMBER STATUS
// ============================================================================
$member_data = null;
$member_check = sqlsrv_query($conn, 
    "SELECT TOP 1 L.*, T.Nama_Tipe, T.Potongan_Harga, T.Harga_Member
     FROM Langganan L 
     INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe 
     WHERE L.ID_Customer = ? AND L.Status = 1 
     AND GETDATE() BETWEEN L.Tanggal_Mulai AND L.Tanggal_Selesai
     ORDER BY L.Tanggal_Selesai DESC", 
    array($id_customer)
);
if ($member_check) {
    $member_data = sqlsrv_fetch_array($member_check, SQLSRV_FETCH_ASSOC);
}
$has_member = !empty($member_data);
$member_tipe = $has_member ? $member_data['Nama_Tipe'] : '';

// ============================================================================
// LOAD DATA MASTER DINAMIS UNTUK LANDING PAGE
// ============================================================================

// 1. Data Lapangan (Maksimal 3 untuk Grid Lapangan Populer)
$lapangan_list = [];
$query_lapangan = sqlsrv_query($conn, "SELECT TOP 3 ID_Lapangan, Nama_Lapangan, Harga_Sewa, Photo_Lapangan FROM Lapangan WHERE Status = 1 AND Is_Deleted = 0 ORDER BY ID_Lapangan ASC");
if ($query_lapangan) {
    while ($row = sqlsrv_fetch_array($query_lapangan, SQLSRV_FETCH_ASSOC)) {
        $lapangan_list[] = $row;
    }
}

// 2. Data Jadwal Tersedia (Hanya yang Status = 1 / Tersedia)
$jadwal_list = [];
$query_jadwal = sqlsrv_query($conn, "
    SELECT TOP 5 J.ID_Jadwal, J.Tanggal, J.Jam_Mulai, J.Jam_Selesai, J.Status, L.ID_Lapangan, L.Nama_Lapangan, L.Harga_Sewa, L.Photo_Lapangan 
    FROM Jadwal J 
    INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan 
    WHERE J.Is_Deleted = 0 AND L.Is_Deleted = 0 AND L.Status = 1 AND J.Status = 1
    ORDER BY J.Tanggal ASC, J.Jam_Mulai ASC
");
if ($query_jadwal) {
    while ($row = sqlsrv_fetch_array($query_jadwal, SQLSRV_FETCH_ASSOC)) {
        $jadwal_list[] = $row;
    }
}

// 3. Data Tipe Member untuk Banner Promosi
$tipe_member_list = [];
$query_tipe = sqlsrv_query($conn, "SELECT TOP 3 ID_Tipe, Nama_Tipe, Harga_Member, Potongan_Harga FROM Tipe_Member WHERE Status = 1 AND Is_Deleted = 0 ORDER BY Harga_Member ASC");
if ($query_tipe) {
    while ($row = sqlsrv_fetch_array($query_tipe, SQLSRV_FETCH_ASSOC)) {
        $tipe_member_list[] = $row;
    }
}

// 4. Riwayat Booking Customer (Top 3)
$riwayat_list = [];
$query_riwayat = sqlsrv_query($conn, "
    SELECT TOP 3 B.ID_Booking, B.Tanggal_Booking, B.Metode_Pembayaran, B.Total_Bayar, B.Status,
           L.Nama_Lapangan, J.Tanggal, J.Jam_Mulai, J.Jam_Selesai
    FROM Booking B
    INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
    INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
    WHERE B.ID_Customer = ?
    ORDER BY B.Created_Date DESC
", array($id_customer));
if ($query_riwayat) {
    while ($row = sqlsrv_fetch_array($query_riwayat, SQLSRV_FETCH_ASSOC)) {
        $riwayat_list[] = $row;
    }
}

$status_labels = [
    0 => ['label' => 'Menunggu', 'class' => 'sp-pending', 'icon' => 'fa-clock'],
    1 => ['label' => 'Berhasil', 'class' => 'sp-active', 'icon' => 'fa-check-circle'],
    2 => ['label' => 'Selesai', 'class' => 'sp-success', 'icon' => 'fa-flag-checkered'],
    3 => ['label' => 'Dibatalkan', 'class' => 'sp-inactive', 'icon' => 'fa-ban']
];

function formatTanggal($tanggal) {
    if (empty($tanggal)) return '-';
    if (is_object($tanggal) && method_exists($tanggal, 'format')) {
        return $tanggal->format('d M Y');
    }
    return date('d M Y', strtotime($tanggal));
}
function formatJam($jam) {
    if (empty($jam)) return '-';
    if (is_object($jam) && method_exists($jam, 'format')) {
        return $jam->format('H:i');
    }
    return substr($jam, 0, 5);
}
function rupiahFormat($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HoopBall | Sewa Lapangan Basket Online</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #FF5200;
            --primary-hover: #E04800;
            --dark-bg: #0B0B0C;
            --card-dark: #121214;
            --text-gray: #8E8E93;
            --border-color: #222225;
            --white: #FFFFFF;
            --light-bg: #F8F9FA;
            --green: #34C759;
            --green-lt: rgba(52,199,89,.10);
            --yellow: #FFCC00;
            --yellow-lt: rgba(255,204,0,.10);
            --red: #FF3B30;
            --red-lt: rgba(255,59,48,.10);
            --blue: #007AFF;
            --blue-lt: rgba(0,122,255,.10);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--white); 
            color: #111; 
            overflow-x: hidden; 
        }

        /* ---- NAVBAR (PUTIH) ---- */
        nav {
            background: var(--white);
            padding: 0 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 76px;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid #E5E5EA;
        }
        .nav-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            gap: 10px;
        }
        .nav-logo img {
            height: 70px;
            width: auto;
        }
        .nav-logo span {
            color: #1C1C1E;
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nav-links a {
            color: #636366;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.2s ease;
        }
        .nav-links a:hover { color: #1C1C1E; }
        .nav-links a.active { color: var(--primary); font-weight: 600; }

        /* ---- USER DROPDOWN ---- */
        .nav-user-container {
            position: relative;
            height: 76px;
            display: flex;
            align-items: center;
        }
        .nav-user {
            background: #F2F2F7;
            border: 1px solid #E5E5EA;
            padding: 8px 16px;
            border-radius: 50px;
            color: #1C1C1E;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.2s;
        }
        .nav-user:hover { background: #E5E5EA; border-color: var(--primary); }
        .nav-user img.user-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
        }
        .nav-user i.user-icon {
            font-size: 16px;
            color: var(--primary);
        }
        .nav-user i.arrow { 
            font-size: 11px; 
            color: #8E8E93; 
            transition: 0.3s; 
        }
        .nav-user-container:hover i.arrow { 
            transform: rotate(180deg); 
            color: var(--primary); 
        }
        .dropdown-menu {
            position: absolute;
            top: 85%;
            right: 0;
            background: #16161a;
            min-width: 220px;
            border-radius: 12px;
            border: 1px solid #2d2d33;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            padding: 8px 0;
            display: none; 
            z-index: 1001;
            animation: fadeIn 0.2s ease-out;
        }
        .nav-user-container:hover .dropdown-menu {
            display: block;
        }
        .dropdown-menu .user-info-header {
            padding: 12px 20px;
            border-bottom: 1px solid #2d2d33;
            margin-bottom: 6px;
        }
        .dropdown-menu .user-info-header span {
            display: block;
        }
        .dropdown-menu .user-info-header .u-name {
            color: var(--white);
            font-size: 14px;
            font-weight: 700;
        }
        .dropdown-menu .user-info-header .u-role {
            color: var(--text-gray);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px;
            color: #c5c5ca;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: 0.2s;
        }
        .dropdown-menu a i { 
            font-size: 14px; 
            width: 16px; 
            text-align: center; 
        }
        .dropdown-menu a:hover {
            background: #222227;
            color: var(--primary);
        }
        .dropdown-divider {
            height: 1px;
            background: #2d2d33;
            margin: 6px 0;
        }
        .dropdown-menu a.logout:hover { 
            color: #ff3b30; 
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ---- HERO SECTION ---- */
        .hero {
            background-color: var(--dark-bg);
            background-image: linear-gradient(180deg, rgba(11,11,12,0.6) 0%, rgba(11,11,12,0.9) 100%), url('https://images.unsplash.com/photo-1546519638-68e109498ffc?q=80&w=2000');
            background-size: cover;
            background-position: center;
            min-height: 600px;
            padding: 60px 80px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
        }
        .hero-left {
            max-width: 620px;
        }
        .hero-title {
            font-size: 54px;
            font-weight: 800;
            color: var(--white);
            line-height: 1.15;
            margin-bottom: 20px;
        }
        .hero-title span {
            color: var(--primary);
        }
        .hero-desc {
            color: #A0A0A5;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 36px;
        }
        .hero-btns {
            display: flex;
            gap: 16px;
        }
        .btn-primary {
            background: var(--primary);
            color: var(--white);
            border: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-primary:hover { 
            background: var(--primary-hover); 
        }
        .btn-outline {
            background: transparent;
            color: var(--white);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 14px 28px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 15px;
            text-decoration: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-outline:hover { 
            background: rgba(255, 255, 255, 0.05); 
            border-color: var(--white);
        }

        /* MEMBER BADGE */
        .member-badge-hero {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--green-lt);
            border: 1px solid var(--green);
            color: var(--green);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .member-badge-hero i { font-size: 14px; }

        /* WIDGET CARI LAPANGAN */
        .search-widget {
            background: rgba(18, 18, 20, 0.85);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            width: 440px;
            padding: 28px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.4);
        }
        .widget-title {
            color: var(--white);
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 24px;
        }
        .form-group {
            margin-bottom: 16px;
            position: relative;
        }
        .form-label {
            display: block;
            color: #A0A0A5;
            font-size: 12px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        .input-wrapper {
            position: relative;
        }
        .input-wrapper i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: #636366;
            font-size: 14px;
        }
        .form-select, .form-input {
            width: 100%;
            background: #1C1C1E;
            border: 1px solid #2C2C2E;
            border-radius: 8px;
            padding: 12px 14px 12px 40px;
            color: var(--white);
            font-size: 14px;
            font-family: inherit;
            outline: none;
            appearance: none;
            transition: 0.2s;
        }
        .form-select:focus, .form-input:focus {
            border-color: var(--primary);
        }
        .form-select {
            background-image: url("data:image/svg+xml;utf8,<svg fill='none' height='24' stroke='%238E8E93' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' viewBox='0 0 24 24' width='24' xmlns='http://www.w3.org/2000/svg'><polyline points='6 9 12 15 18 9'/></svg>");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 16px;
        }
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .btn-widget {
            background: var(--primary);
            color: var(--white);
            border: none;
            width: 100%;
            padding: 14px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            margin-top: 10px;
            transition: 0.2s;
        }
        .btn-widget:hover { 
            background: var(--primary-hover); 
        }

        /* ---- ROW FITUR ---- */
        .features-row {
            padding: 40px 80px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            background: var(--white);
            border-bottom: 1px solid #F2F2F7;
        }
        .feature-card {
            display: flex;
            gap: 16px;
            align-items: flex-start;
        }
        .feature-icon-circle {
            background: #FFF0E6;
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .feature-icon-circle i {
            color: var(--primary);
            font-size: 20px;
        }
        .feature-text h4 {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 6px;
            color: #1C1C1E;
        }
        .feature-text p {
            font-size: 13px;
            color: #636366;
            line-height: 1.5;
        }

        /* ---- MAIN CONTAINER ---- */
        .main-container {
            padding: 60px 80px;
            max-width: 1440px;
            margin: 0 auto;
        }

        /* ---- LAPANGAN POPULER ---- */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 24px;
        }
        .section-title {
            font-size: 24px;
            font-weight: 800;
            color: #111;
        }
        .section-subtitle {
            font-size: 14px;
            color: #636366;
            margin-top: 4px;
        }
        .section-action {
            color: var(--primary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        .section-action:hover {
            color: var(--primary-hover);
        }

        .court-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 60px;
        }
        .court-card-new {
            background: var(--white);
            border: 1px solid #E5E5EA;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
            transition: all 0.25s ease;
        }
        .court-card-new:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.08);
        }
        .court-img-container {
            height: 220px;
            position: relative;
            background: #f0f0f0;
        }
        .court-img-new {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .heart-btn {
            position: absolute;
            top: 14px;
            right: 14px;
            background: rgba(255, 255, 255, 0.9);
            border: none;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #636366;
            font-size: 16px;
            transition: 0.2s;
        }
        .heart-btn:hover {
            color: #FF2D55;
            background: var(--white);
        }
        .court-body-new {
            padding: 20px;
        }
        .court-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 6px;
        }
        .court-name-new {
            font-size: 18px;
            font-weight: 700;
            color: #1C1C1E;
        }
        .court-rating {
            font-size: 13px;
            font-weight: 600;
            color: #1C1C1E;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .court-rating i {
            color: #FFCC00;
        }
        .court-rating span {
            color: #8E8E93;
            font-weight: 400;
        }
        .court-location {
            font-size: 13px;
            color: #636366;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 16px;
        }
        .court-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-top: 1px solid #E5E5EA;
            padding-top: 16px;
        }
        .court-price-box {
            font-size: 13px;
            color: #8E8E93;
        }
        .court-price-box strong {
            font-size: 18px;
            color: var(--primary);
            font-weight: 800;
        }
        .btn-detail {
            background: var(--white);
            color: #1C1C1E;
            border: 1px solid #D1D1D6;
            padding: 10px 18px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-detail:hover {
            background: #F2F2F7;
            border-color: #AEAEB2;
        }

        /* ---- JADWAL TERSEDIA ---- */
        .schedule-box {
            border: 1px solid #E5E5EA;
            border-radius: 16px;
            padding: 28px;
            background: var(--white);
            margin-bottom: 60px;
        }
        .schedule-layout {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 40px;
            margin-top: 20px;
        }
        .schedule-left {
            border-right: 1px solid #E5E5EA;
            padding-right: 40px;
        }
        .time-slot-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 10px;
            margin-top: 16px;
        }
        .time-slot {
            border: 1px solid #E5E5EA;
            border-radius: 8px;
            padding: 14px 8px;
            text-align: center;
            cursor: pointer;
            transition: 0.2s;
        }
        .time-slot.available {
            background: #F2FDF5;
            border-color: #D1F2D9;
        }
        .time-slot.available .status-lbl {
            color: #34C759;
        }
        .time-slot.selected {
            background: #FFF2EB;
            border-color: #FFD2BC;
            box-shadow: 0 0 0 2px var(--primary);
        }
        .time-slot.selected .status-lbl {
            color: var(--primary);
        }
        .time-slot.booked {
            background: #FFF5F5;
            border-color: #FFD1D1;
            cursor: not-allowed;
            opacity: 0.6;
        }
        .time-slot.booked .status-lbl {
            color: #FF3B30;
        }
        .time-slot .time-lbl {
            display: block;
            font-size: 14px;
            font-weight: 700;
            color: #1C1C1E;
            margin-bottom: 4px;
        }
        .time-slot .status-lbl {
            display: block;
            font-size: 11px;
            font-weight: 600;
        }

        .schedule-info-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            color: #636366;
            margin-top: 20px;
        }
        .schedule-info-row i {
            color: #8E8E93;
        }

        /* RINGKASAN JADWAL */
        .selected-summary-card {
            background: #F8F9FA;
            border-radius: 12px;
            padding: 24px;
        }
        .summary-title {
            font-size: 15px;
            font-weight: 700;
            color: #1C1C1E;
            margin-bottom: 16px;
        }
        .summary-item {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 16px;
            font-size: 14px;
            color: #1C1C1E;
        }
        .summary-item i {
            color: #636366;
            margin-top: 3px;
            font-size: 14px;
            width: 16px;
        }
        .summary-item span {
            color: #8E8E93;
            font-size: 12px;
            display: block;
            margin-bottom: 2px;
        }
        .btn-booking-submit {
            background: var(--primary);
            color: var(--white);
            border: none;
            width: 100%;
            padding: 14px;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 8px;
            transition: 0.2s;
        }
        .btn-booking-submit:hover {
            background: var(--primary-hover);
        }

        /* ---- RIWAYAT BOOKING MINI ---- */
        .riwayat-section {
            margin-bottom: 60px;
        }
        .riwayat-card {
            border: 1px solid #E5E5EA;
            border-radius: 16px;
            padding: 24px;
            background: var(--white);
        }
        .riwayat-item {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 16px 0;
            border-bottom: 1px solid #F2F2F7;
        }
        .riwayat-item:last-child {
            border-bottom: none;
        }
        .riwayat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: var(--orange-lt);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary);
            font-size: 20px;
            flex-shrink: 0;
        }
        .riwayat-info {
            flex: 1;
        }
        .riwayat-info h4 {
            font-size: 15px;
            font-weight: 700;
            color: #1C1C1E;
            margin-bottom: 4px;
        }
        .riwayat-info p {
            font-size: 13px;
            color: #636366;
        }
        .riwayat-price {
            font-size: 16px;
            font-weight: 800;
            color: var(--primary);
        }
        .status-pill {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .3px;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .sp-active { background: var(--green-lt); color: var(--green); }
        .sp-success { background: var(--blue-lt); color: var(--blue); }
        .sp-pending { background: var(--yellow-lt); color: #D97706; }
        .sp-inactive { background: var(--red-lt); color: var(--red); }

        /* ---- BANNER MEMBER ---- */
        .member-promo-banner {
            background: #0B0B0C;
            border-radius: 16px;
            padding: 40px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
            margin-bottom: 60px;
            color: var(--white);
            overflow: hidden;
            position: relative;
        }
        .member-banner-left {
            display: flex;
            align-items: center;
            gap: 30px;
            max-width: 55%;
            position: relative;
            z-index: 2;
        }
        .member-img-card {
            background: #1C1C1E;
            padding: 16px;
            border-radius: 12px;
            border: 1px solid #2C2C2E;
            width: 140px;
            text-align: center;
        }
        .member-img-card img {
            width: 100%;
            height: auto;
        }
        .member-banner-desc h3 {
            font-size: 24px;
            font-weight: 800;
            margin-bottom: 8px;
        }
        .member-banner-desc h3 span {
            color: var(--primary);
        }
        .member-banner-desc p {
            color: #A0A0A5;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 16px;
        }
        .btn-join-member {
            background: var(--primary);
            color: var(--white);
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-join-member:hover {
            background: var(--primary-hover);
        }

        .member-banner-right {
            display: flex;
            gap: 20px;
            position: relative;
            z-index: 2;
        }
        .member-benefit-item {
            background: #121214;
            border: 1px solid #1C1C1E;
            padding: 20px;
            border-radius: 12px;
            text-align: center;
            width: 150px;
        }
        .benefit-icon-circle {
            background: #FFF0E6;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
        }
        .benefit-icon-circle i {
            color: var(--primary);
            font-size: 18px;
        }
        .member-benefit-item h5 {
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .member-benefit-item p {
            font-size: 10px;
            color: #8E8E93;
            line-height: 1.4;
        }

        /* ---- FASILITAS UNGGULAN ---- */
        .facilities-section {
            margin-bottom: 60px;
        }
        .fac-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            margin-top: 24px;
        }
        .fac-card {
            border: 1px solid #E5E5EA;
            border-radius: 12px;
            padding: 24px;
            display: flex;
            align-items: flex-start;
            gap: 16px;
        }
        .fac-icon {
            color: var(--primary);
            font-size: 24px;
            margin-top: 2px;
        }
        .fac-info h4 {
            font-size: 15px;
            font-weight: 700;
            color: #1C1C1E;
            margin-bottom: 6px;
        }
        .fac-info p {
            font-size: 13px;
            color: #636366;
            line-height: 1.5;
        }

        /* ---- CALL TO ACTION BANNER ---- */
        .cta-banner {
            background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('https://images.unsplash.com/photo-1544919982-b61976f0ba43?q=80&w=1500');
            background-size: cover;
            background-position: center;
            border-radius: 16px;
            padding: 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--white);
            margin-bottom: 60px;
        }
        .cta-left {
            max-width: 60%;
        }
        .cta-title {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 12px;
        }
        .cta-desc {
            font-size: 15px;
            color: #D1D1D6;
            line-height: 1.6;
        }
        .btn-cta {
            background: var(--primary);
            color: var(--white);
            border: none;
            padding: 16px 32px;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.2s;
        }
        .btn-cta:hover {
            background: var(--primary-hover);
        }

        /* ---- FOOTER ---- */
        footer {
            background: var(--dark-bg);
            color: #8E8E93;
            padding: 80px 80px 40px;
            border-top: 1px solid #1C1C1E;
        }
        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1.2fr;
            gap: 40px;
            margin-bottom: 60px;
        }
        .footer-logo {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
        }
        .footer-logo img {
            height: 70px;
        }
        .footer-logo span {
            color: var(--white);
            font-size: 20px;
            font-weight: 800;
        }
        .footer-desc {
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 24px;
        }
        .social-links {
            display: flex;
            gap: 12px;
        }
        .social-btn {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #1C1C1E;
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: 0.2s;
        }
        .social-btn:hover {
            background: var(--primary);
        }
        .footer-col h4 {
            color: var(--white);
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .footer-col ul {
            list-style: none;
        }
        .footer-col ul li {
            margin-bottom: 12px;
        }
        .footer-col ul li a {
            color: #8E8E93;
            text-decoration: none;
            font-size: 13px;
            transition: 0.2s;
        }
        .footer-col ul li a:hover {
            color: var(--white);
        }
        .contact-item {
            display: flex;
            gap: 12px;
            font-size: 13px;
            line-height: 1.5;
            margin-bottom: 16px;
        }
        .contact-item i {
            color: var(--primary);
            font-size: 14px;
            margin-top: 3px;
        }
        .footer-bottom {
            border-top: 1px solid #1C1C1E;
            padding-top: 30px;
            text-align: center;
            font-size: 13px;
        }

        .swal-toast { border-radius: 12px !important; font-family: 'Plus Jakarta Sans', sans-serif !important; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav>
    <a href="view_customer.php" class="nav-logo">
        <img src="../asset/image/logo2.png" alt="HoopBall">
    </a>
    <div class="nav-links">
        <a href="view_customer.php" class="active">Beranda</a>
        <a href="booking_customer.php">Booking</a>
        <a href="#">Lapangan</a>
        <a href="#">Member</a>
        <a href="#">Pembelian</a>
        <a href="#">Tentang</a>
        <a href="#">Kontak</a>
    </div>

    <!-- User Dropdown -->
    <div class="nav-user-container">
        <div class="nav-user">
            <?php if (!empty($photo_profile) && file_exists($photo_profile)): ?>
                <img src="<?php echo htmlspecialchars($photo_profile); ?>" alt="Avatar" class="user-avatar">
            <?php else: ?>
                <i class="fa-solid fa-circle-user user-icon"></i>
            <?php endif; ?>
            <span><?php echo htmlspecialchars($nama_customer); ?></span>
            <i class="fa-solid fa-chevron-down arrow"></i>
        </div>
        <div class="dropdown-menu">
            <div class="user-info-header">
                <span class="u-name"><?php echo htmlspecialchars($nama_customer); ?></span>
                <span class="u-role">Customer</span>
            </div>
            <a href="../profile/profile_customer.php"><i class="fa-solid fa-user"></i> Profil Saya</a>
            <a href="booking_customer.php"><i class="fa-solid fa-calendar-check"></i> Riwayat Booking</a>
            <a href="#"><i class="fa-solid fa-gear"></i> Pengaturan</a>
            <div class="dropdown-divider"></div>
            <a href="#" onclick="confirmHapusAkun(event)" style="color: #ff3b30;"><i class="fa-solid fa-trash-can"></i> Hapus Akun</a>
            <a href="../login/logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
        </div>
    </div>
</nav>

<!-- HERO SECTION -->
<header class="hero">
    <div class="hero-left">
        <?php if ($has_member): ?>
        <div class="member-badge-hero">
            <i class="fa-solid fa-crown"></i> Member <?php echo htmlspecialchars($member_tipe); ?> Aktif
        </div>
        <?php endif; ?>
        <h1 class="hero-title">Sewa Lapangan<br>Basket Jadi<br><span>Lebih Mudah</span></h1>
        <p class="hero-desc">Booking lapangan favoritmu secara online dengan cepat, cek jadwal real-time, dan nikmati promo khusus member.</p>
        <div class="hero-btns">
            <a href="booking_customer.php" class="btn-primary">Booking Sekarang <i class="fa-solid fa-arrow-right"></i></a>
            <a href="#jadwal-section" class="btn-outline" onclick="document.getElementById('jadwal-section').scrollIntoView({behavior:'smooth'}); return false;"><i class="fa-solid fa-calendar-days"></i> Lihat Jadwal</a>
        </div>
    </div>

    <!-- WIDGET CARI LAPANGAN -->
    <div class="search-widget">
        <h3 class="widget-title"><i class="fa-solid fa-magnifying-glass"></i> Cari & Booking</h3>

        <div class="form-group">
            <label class="form-label">Pilih Lapangan</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-basketball"></i>
                <select class="form-select" id="heroLapangan" onchange="filterJadwal()">
                    <option value="">Semua Lapangan</option>
                    <?php
                    $q_all_lap = sqlsrv_query($conn, "SELECT ID_Lapangan, Nama_Lapangan FROM Lapangan WHERE Status = 1 AND Is_Deleted = 0 ORDER BY Nama_Lapangan ASC");
                    if ($q_all_lap) {
                        while ($lap = sqlsrv_fetch_array($q_all_lap, SQLSRV_FETCH_ASSOC)) {
                            echo '<option value="'.htmlspecialchars($lap['ID_Lapangan']).'">'.htmlspecialchars($lap['Nama_Lapangan']).'</option>';
                        }
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-row-2">
            <div class="form-group">
                <label class="form-label">Tanggal</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-calendar-days"></i>
                    <input type="date" class="form-input" id="heroTanggal" value="<?php echo date('Y-m-d'); ?>" onchange="filterJadwal()">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Jam</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-clock"></i>
                    <input type="time" class="form-input" id="heroJam" value="18:00" onchange="filterJadwal()">
                </div>
            </div>
        </div>

        <a href="booking_customer.php" class="btn-widget" style="text-decoration: none; display: inline-flex; justify-content: center;">
            <i class="fa-solid fa-calendar-check"></i> Lanjutkan Booking
        </a>
    </div>
</header>

<!-- FITUR ROW -->
<section class="features-row">
    <div class="feature-card">
        <div class="feature-icon-circle"><i class="fa-solid fa-calendar-check"></i></div>
        <div class="feature-text">
            <h4>Booking Online</h4>
            <p>Pesan lapangan kapan saja di mana saja dengan mudah dan cepat.</p>
        </div>
    </div>
    <div class="feature-card">
        <div class="feature-icon-circle"><i class="fa-solid fa-clock"></i></div>
        <div class="feature-text">
            <h4>Jadwal Real-Time</h4>
            <p>Cek ketersediaan lapangan secara real-time dan akurat setiap saat.</p>
        </div>
    </div>
    <div class="feature-card">
        <div class="feature-icon-circle"><i class="fa-solid fa-tags"></i></div>
        <div class="feature-text">
            <h4>Promo Member</h4>
            <p>Dapatkan promo menarik dan diskon eksklusif untuk member setia.</p>
        </div>
    </div>
    <div class="feature-card">
        <div class="feature-icon-circle"><i class="fa-solid fa-award"></i></div>
        <div class="feature-text">
            <h4>Lapangan Berkualitas</h4>
            <p>Lapangan terawat, nyaman, dan standar profesional untuk pengalaman terbaik.</p>
        </div>
    </div>
</section>

<!-- MAIN CONTENT -->
<main class="main-container">

    <!-- RIWAYAT BOOKING TERBARU -->
    <?php if (!empty($riwayat_list)): ?>
    <section class="riwayat-section">
        <div class="section-header">
            <div>
                <h2 class="section-title">Booking Terbaru Saya</h2>
                <p class="section-subtitle">Status booking lapangan yang baru saja Anda lakukan.</p>
            </div>
            <a href="booking_customer.php" class="section-action">Lihat Semua <i class="fa-solid fa-chevron-right"></i></a>
        </div>
        <div class="riwayat-card">
            <?php foreach ($riwayat_list as $rb): 
                $status = $status_labels[$rb['Status']] ?? $status_labels[0];
            ?>
            <div class="riwayat-item">
                <div class="riwayat-icon"><i class="fa-solid fa-basketball"></i></div>
                <div class="riwayat-info">
                    <h4><?php echo htmlspecialchars($rb['Nama_Lapangan']); ?></h4>
                    <p><?php echo formatTanggal($rb['Tanggal']); ?> | <?php echo formatJam($rb['Jam_Mulai']); ?> - <?php echo formatJam($rb['Jam_Selesai']); ?> | <?php echo $rb['Metode_Pembayaran']; ?></p>
                </div>
                <div style="text-align: right;">
                    <div class="riwayat-price"><?php echo rupiahFormat($rb['Total_Bayar']); ?></div>
                    <span class="status-pill <?php echo $status['class']; ?>" style="margin-top: 4px;">
                        <i class="fa-solid <?php echo $status['icon']; ?>"></i> <?php echo $status['label']; ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- LAPANGAN POPULER -->
    <section>
        <div class="section-header">
            <div>
                <h2 class="section-title">Lapangan Populer</h2>
                <p class="section-subtitle">Temukan pilihan lapangan basket terbaik yang sering disewa.</p>
            </div>
            <a href="booking_customer.php" class="section-action">Lihat Semua Lapangan <i class="fa-solid fa-chevron-right"></i></a>
        </div>

        <div class="court-grid-3">
            <?php 
            if (!empty($lapangan_list)):
                $fallback_images = [
                    "https://images.unsplash.com/photo-1546519638-68e109498ffc?q=80&w=800",
                    "https://images.unsplash.com/photo-1504450758481-7338eba7524a?q=80&w=800",
                    "https://images.unsplash.com/photo-1505666287802-931dc83948e9?q=80&w=800"
                ];
                $idx = 0;
                foreach ($lapangan_list as $lapangan): 
                    $img = (!empty($lapangan['Photo_Lapangan']) && file_exists($lapangan['Photo_Lapangan'])) 
                           ? htmlspecialchars($lapangan['Photo_Lapangan']) 
                           : $fallback_images[$idx % 3];
                    $idx++;
            ?>
                <div class="court-card-new">
                    <div class="court-img-container">
                        <img class="court-img-new" src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($lapangan['Nama_Lapangan']); ?>">
                    </div>
                    <div class="court-body-new">
                        <div class="court-meta">
                            <h3 class="court-name-new"><?php echo htmlspecialchars($lapangan['Nama_Lapangan']); ?></h3>
                        </div>
                        <div class="court-footer">
                            <div class="court-price-box"><strong><?php echo rupiahFormat($lapangan['Harga_Sewa']); ?></strong> / jam</div>
                            <a href="booking_customer.php" class="btn-detail">Booking</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: span 3; text-align: center; padding: 40px; color: #8E8E93;">
                    <i class="fa-solid fa-circle-info" style="font-size: 32px; margin-bottom: 12px; color: var(--primary);"></i>
                    <p>Tidak ada data lapangan yang aktif saat ini.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- JADWAL TERSEDIA -->
    <section class="schedule-box" id="jadwal-section">
        <div class="section-header" style="margin-bottom: 0;">
            <div>
                <h2 class="section-title">Jadwal Tersedia</h2>
                <p class="section-subtitle">Pilih jadwal dan lanjutkan ke halaman booking.</p>
            </div>
            <a href="booking_customer.php" class="section-action">Lihat Semua Jadwal <i class="fa-solid fa-chevron-right"></i></a>
        </div>

        <div class="schedule-layout">
            <div class="schedule-left">
                <div class="time-slot-grid" id="jadwalGrid">
                    <?php 
                    if (!empty($jadwal_list)):
                        foreach ($jadwal_list as $jadwal):
                            $jam_mulai = $jadwal['Jam_Mulai'] instanceof DateTime ? $jadwal['Jam_Mulai']->format('H:i') : substr($jadwal['Jam_Mulai'], 0, 5);
                            $jam_selesai = $jadwal['Jam_Selesai'] instanceof DateTime ? $jadwal['Jam_Selesai']->format('H:i') : substr($jadwal['Jam_Selesai'], 0, 5);
                            $tanggal_str = $jadwal['Tanggal'] instanceof DateTime ? $jadwal['Tanggal']->format('Y-m-d') : $jadwal['Tanggal'];
                    ?>
                        <a href="booking_customer.php?jadwal=<?php echo $jadwal['ID_Jadwal']; ?>" 
                           class="time-slot available" 
                           style="text-decoration: none; color: inherit;"
                           data-lapangan="<?php echo htmlspecialchars($jadwal['Nama_Lapangan']); ?>"
                           data-tanggal="<?php echo $tanggal_str; ?>"
                           data-jam="<?php echo $jam_mulai; ?> - <?php echo $jam_selesai; ?>">
                            <span class="time-lbl"><?php echo $jam_mulai; ?></span>
                            <span class="status-lbl"><?php echo htmlspecialchars($jadwal['Nama_Lapangan']); ?></span>
                            <span style="font-size: 10px; color: #8E8E93; display: block; margin-top: 2px;"><?php echo formatTanggal($jadwal['Tanggal']); ?></span>
                        </a>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: span 5; text-align: center; padding: 20px; color: #8E8E93;">
                            <i class="fa-solid fa-calendar-xmark" style="font-size: 24px; margin-bottom: 8px; color: var(--primary);"></i>
                            <div>Tidak ada jadwal tersedia saat ini.</div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="schedule-info-row">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Klik jadwal untuk langsung menuju halaman booking.</span>
                </div>
            </div>

            <div class="schedule-right">
                <div class="selected-summary-card">
                    <h4 class="summary-title"><i class="fa-solid fa-lightbulb" style="color: var(--primary);"></i> Cara Booking</h4>
                    <div class="summary-item">
                        <i class="fa-solid fa-1" style="color: var(--primary);"></i>
                        <div><span>Langkah 1</span><strong>Pilih jadwal lapangan yang tersedia</strong></div>
                    </div>
                    <div class="summary-item">
                        <i class="fa-solid fa-2" style="color: var(--primary);"></i>
                        <div><span>Langkah 2</span><strong>Pilih metode pembayaran (Transfer/QRIS)</strong></div>
                    </div>
                    <div class="summary-item">
                        <i class="fa-solid fa-3" style="color: var(--primary);"></i>
                        <div><span>Langkah 3</span><strong>Lakukan pembayaran dan tunggu konfirmasi</strong></div>
                    </div>
                    <a href="booking_customer.php" class="btn-booking-submit" style="text-decoration: none;">
                        <i class="fa-solid fa-calendar-check"></i> Booking Sekarang
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- BANNER PROMO MEMBER -->
    <section class="member-promo-banner">
        <div class="member-banner-left">
            <div class="member-img-card">
                <img src="https://www.pngall.com/wp-content/uploads/2/Basketball-Player-PNG-Transparent-HD-Photo.png" alt="Card Graphic" style="max-height: 100px; object-fit: contain;">
                <div style="color: #fff; font-size: 10px; font-weight: 700; margin-top: 8px; letter-spacing: 1px;">HOOPBALL MEMBER</div>
            </div>
            <div class="member-banner-desc">
                <h3>Jadi Member,<br><span>Main Makin Hemat!</span></h3>
                <p>
                    Tipe member tersedia: 
                    <strong>
                    <?php 
                        $types = [];
                        foreach ($tipe_member_list as $tm) {
                            $types[] = htmlspecialchars($tm['Nama_Tipe']);
                        }
                        echo implode(', ', $types);
                    ?>
                    </strong>.
                    Nikmati potongan harga dan prioritas jadwal.
                </p>
                <a href="#" class="btn-join-member" style="text-decoration: none; display: inline-block;">Gabung Member</a>
            </div>
        </div>

        <div class="member-banner-right">
            <div class="member-benefit-item">
                <div class="benefit-icon-circle"><i class="fa-solid fa-percent"></i></div>
                <h5>Potongan Harga</h5>
                <p>Hingga <?php echo !empty($tipe_member_list) ? rupiahFormat($tipe_member_list[count($tipe_member_list)-1]['Potongan_Harga']) : 'Rp 35.000'; ?> per booking.</p>
            </div>
            <div class="member-benefit-item">
                <div class="benefit-icon-circle"><i class="fa-solid fa-calendar-check"></i></div>
                <h5>Prioritas Jadwal</h5>
                <p>Akses lebih awal untuk jadwal prime time.</p>
            </div>
            <div class="member-benefit-item">
                <div class="benefit-icon-circle"><i class="fa-solid fa-gift"></i></div>
                <h5>Promo Eksklusif</h5>
                <p>Dapatkan promo dan penawaran spesial khusus member.</p>
            </div>
        </div>
    </section>

    <!-- FASILITAS UNGGULAN -->
    <section class="facilities-section">
        <div class="section-header">
            <div>
                <h2 class="section-title">Fasilitas Unggulan</h2>
                <p class="section-subtitle">Nikmati fasilitas pendukung terbaik untuk pengalaman bermain yang lebih nyaman.</p>
            </div>
        </div>

        <div class="fac-grid">
            <div class="fac-card">
                <i class="fa-solid fa-door-open fac-icon"></i>
                <div class="fac-info">
                    <h4>Ruang Ganti</h4>
                    <p>Area ganti yang bersih, steril, dan nyaman.</p>
                </div>
            </div>
            <div class="fac-card">
                <i class="fa-solid fa-shower fac-icon"></i>
                <div class="fac-info">
                    <h4>Shower Mandi</h4>
                    <p>Fasilitas mandi air hangat setelah selesai bermain.</p>
                </div>
            </div>
            <div class="fac-card">
                <i class="fa-solid fa-square-parking fac-icon"></i>
                <div class="fac-info">
                    <h4>Parkir Luas</h4>
                    <p>Area parkir terpadu yang aman untuk motor dan mobil.</p>
                </div>
            </div>
            <div class="fac-card">
                <i class="fa-solid fa-wifi fac-icon"></i>
                <div class="fac-info">
                    <h4>WiFi & Lounge</h4>
                    <p>Tunggu giliran bermain dengan nyaman di ruang tunggu.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA BANNER -->
    <section class="cta-banner">
        <div class="cta-left">
            <h2 class="cta-title">Siap Main Bareng?</h2>
            <p class="cta-desc">Booking lapangan favoritmu sekarang dan rasakan pengalaman bermain terbaik bersama teman-temanmu di HoopBall!</p>
        </div>
        <a href="booking_customer.php" class="btn-cta" style="text-decoration: none;">
            Booking Sekarang <i class="fa-solid fa-arrow-right"></i>
        </a>
    </section>

</main>

<!-- FOOTER -->
<footer>
    <div class="footer-grid">
        <div>
            <div class="footer-logo">
                <img src="../asset/image/logo.png" alt="HoopBall">
            </div>
            <p class="footer-desc">HoopBall adalah platform penyewaan lapangan basket online yang mudah, cepat, dan terpercaya.</p>
            <div class="social-links">
                <a href="#" class="social-btn"><i class="fa-brands fa-instagram"></i></a>
                <a href="#" class="social-btn"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" class="social-btn"><i class="fa-brands fa-tiktok"></i></a>
                <a href="#" class="social-btn"><i class="fa-brands fa-youtube"></i></a>
            </div>
        </div>

        <div class="footer-col">
            <h4>Navigasi</h4>
            <ul>
                <li><a href="view_customer.php">Beranda</a></li>
                <li><a href="booking_customer.php">Booking</a></li>
                <li><a href="#">Lapangan</a></li>
                <li><a href="#">Member</a></li>
                <li><a href="#">Pembelian</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Informasi</h4>
            <ul>
                <li><a href="#">Cara Booking</a></li>
                <li><a href="#">Syarat & Ketentuan</a></li>
                <li><a href="#">Kebijakan Privasi</a></li>
                <li><a href="#">FAQ</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Hubungi Kami</h4>
            <div class="contact-item">
                <i class="fa-solid fa-location-dot"></i>
                Jl. Olahraga No. 10, Kebayoran Baru, Jakarta Selatan 12190
            </div>
            <div class="contact-item">
                <i class="fa-solid fa-phone"></i>
                +62 812-3456-7890
            </div>
            <div class="contact-item">
                <i class="fa-solid fa-envelope"></i>
                info@hoopball.id
            </div>
            <div class="contact-item">
                <i class="fa-solid fa-clock"></i>
                Setiap hari 07:00 - 23:00 WIB
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; 2025 HoopBall. All rights reserved.</p>
    </div>
</footer>

<script>
// ============================================================================
// FILTER JADWAL (Hero Widget)
// ============================================================================
function filterJadwal() {
    // This is a visual filter - in real implementation, 
    // it would filter the displayed jadwal or redirect to booking page with params
    const lapangan = document.getElementById('heroLapangan').value;
    const tanggal = document.getElementById('heroTanggal').value;
    const jam = document.getElementById('heroJam').value;

    // Store in session/localStorage for booking page to pick up
    localStorage.setItem('booking_filter_lapangan', lapangan);
    localStorage.setItem('booking_filter_tanggal', tanggal);
    localStorage.setItem('booking_filter_jam', jam);
}

// ============================================================================
// HARD DELETE AKUN CONFIRMATION
// ============================================================================
function confirmHapusAkun(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Hapus Akun Permanen?',
        html: '<strong style="color:#FF3B30;">PERINGATAN:</strong> Tindakan ini tidak dapat dibatalkan!<br><br>' +
              'Akun Anda akan dihapus dari sistem dan Anda harus mendaftar ulang untuk menggunakan layanan kami.<br><br>' +
              '<span style="color:#8E8E93; font-size:12px;">Data akan dihapus secara permanen.</span>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#FF3B30',
        cancelButtonColor: '#8E8E93',
        confirmButtonText: 'Ya, Hapus Akun Saya!',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (result.isConfirmed) {
            let timerInterval;
            Swal.fire({
                title: 'Menghapus Akun...',
                html: 'Mohon tunggu, akun Anda sedang diproses.<br><b></b>',
                timer: 2000,
                timerProgressBar: true,
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                    const timer = Swal.getHtmlContainer().querySelector('b');
                    timerInterval = setInterval(() => {
                        timer.textContent = Math.ceil(Swal.getTimerLeft() / 1000) + ' detik';
                    }, 100);
                },
                willClose: () => {
                    clearInterval(timerInterval);
                }
            }).then(() => {
                window.location.href = '?hapus_akun=1';
            });
        }
    });
}

// ============================================================================
// URL PARAMETER NOTIFICATION (TOAST STYLE)
// ============================================================================
const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
const msg = urlParams.get('msg');

if (status && msg) {
    const isSuccess = status === 'success';
    Swal.fire({
        icon: isSuccess ? 'success' : 'error',
        title: isSuccess ? 'Berhasil!' : 'Gagal!',
        text: msg,
        timer: 5000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end',
        timerProgressBar: true,
        showCloseButton: true,
        background: '#ffffff',
        color: '#1c1c1e',
        iconColor: isSuccess ? '#34C759' : '#FF3B30',
        customClass: { popup: 'swal-toast' }
    });
    window.history.replaceState({}, document.title, window.location.pathname);
}
</script>

</body>
</html>