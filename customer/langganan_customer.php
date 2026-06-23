<?php
// ============================================================================
// BUFFER OUTPUT
// ============================================================================
ob_start();

session_start();
include '../includes/config.php';

// ============================================================================
// CEK AKSES
// ============================================================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../login/login.php");
    exit();
}

$id_customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';
$nama_customer = $_SESSION['nama'] ?? 'Pelanggan';

// ============================================================================
// AMBIL DATA CUSTOMER
// ============================================================================
$customer_data = null;
if (!empty($id_customer)) {
    $stmt = sqlsrv_query($conn, "SELECT * FROM Customer WHERE ID_Customer = ? AND Is_Deleted = 0", array($id_customer));
    if ($stmt) {
        $customer_data = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
    }
}

if (!$customer_data) {
    header("Location: ../login/login.php?status=error&msg=Sesi tidak valid");
    exit();
}

// ============================================================================
// CEK STATUS MEMBER AKTIF
// ============================================================================
$member_aktif = null;
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
    $member_aktif = sqlsrv_fetch_array($member_check, SQLSRV_FETCH_ASSOC);
}

$has_member = !empty($member_aktif);
$member_tipe = $has_member ? $member_aktif['Nama_Tipe'] : '';

// ============================================================================
// AMBIL DATA TIPE MEMBER (yang aktif)
// ============================================================================
$tipe_member_list = [];
$query_tipe = sqlsrv_query($conn, 
    "SELECT ID_Tipe, Nama_Tipe, Harga_Member, Potongan_Harga, Status 
     FROM Tipe_Member 
     WHERE Status = 1 AND Is_Deleted = 0 
     ORDER BY Harga_Member ASC"
);
if ($query_tipe) {
    while ($row = sqlsrv_fetch_array($query_tipe, SQLSRV_FETCH_ASSOC)) {
        $tipe_member_list[] = $row;
    }
}

// ============================================================================
// AMBIL RIWAYAT LANGGANAN CUSTOMER
// ============================================================================
$riwayat_langganan = [];
$query_riwayat = sqlsrv_query($conn, 
    "SELECT L.*, T.Nama_Tipe, T.Potongan_Harga, T.Harga_Member
     FROM Langganan L 
     INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe 
     WHERE L.ID_Customer = ? 
     ORDER BY L.Created_Date DESC",
    array($id_customer)
);
if ($query_riwayat) {
    while ($row = sqlsrv_fetch_array($query_riwayat, SQLSRV_FETCH_ASSOC)) {
        $riwayat_langganan[] = $row;
    }
}

// ============================================================================
// PROSES PEMBELIAN LANGGANAN
// ============================================================================
// ============================================================================
// PROSES PEMBELIAN LANGGANAN
// ============================================================================
$pembelian_msg = '';
$pembelian_error = '';
$show_payment_modal = false;
$payment_total = 0;
$payment_method = '';
$last_inserted_id = 0;

if (isset($_POST['beli_langganan'])) {
    $id_tipe = $_POST['id_tipe'] ?? '';
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? '';
    
    // Validasi
    if (empty($id_tipe) || empty($metode_pembayaran)) {
        $pembelian_error = 'Pilih tipe member dan metode pembayaran!';
    } else {
        // Ambil data tipe member
        $stmt_tipe = sqlsrv_query($conn, 
            "SELECT * FROM Tipe_Member WHERE ID_Tipe = ? AND Status = 1 AND Is_Deleted = 0", 
            array($id_tipe)
        );
        $tipe_data = sqlsrv_fetch_array($stmt_tipe, SQLSRV_FETCH_ASSOC);
        
        if (!$tipe_data) {
            $pembelian_error = 'Tipe member tidak valid!';
        } else {
            // Cek apakah sudah ada langganan aktif
            $cek_aktif = sqlsrv_query($conn,
                "SELECT COUNT(*) as total FROM Langganan 
                 WHERE ID_Customer = ? AND Status = 1 
                 AND GETDATE() BETWEEN Tanggal_Mulai AND Tanggal_Selesai",
                array($id_customer)
            );
            $row_aktif = sqlsrv_fetch_array($cek_aktif, SQLSRV_FETCH_ASSOC);
            
            if ($row_aktif['total'] > 0) {
                $pembelian_error = 'Anda masih memiliki langganan member aktif. Tidak dapat mendaftar lagi.';
            } else {
                // Cek juga apakah ada yang menunggu konfirmasi
                $cek_pending = sqlsrv_query($conn,
                    "SELECT COUNT(*) as total FROM Langganan 
                     WHERE ID_Customer = ? AND Status = 0",
                    array($id_customer)
                );
                $row_pending = sqlsrv_fetch_array($cek_pending, SQLSRV_FETCH_ASSOC);
                
                if ($row_pending['total'] > 0) {
                    $pembelian_error = 'Anda memiliki pendaftaran member yang sedang menunggu konfirmasi. Silakan tunggu verifikasi dari admin.';
                } else {
                    // Hitung tanggal
                    $tanggal_mulai = date('Y-m-d');
                    $tanggal_selesai = date('Y-m-d', strtotime('+30 days'));
                    $total_bayar = $tipe_data['Harga_Member'];
                    
                    // Simpan ke database - Status 0 = Menunggu Konfirmasi
                    // FIX: Gunakan OUTPUT INSERTED.ID_Langganan untuk mendapatkan ID
                    $stmt_insert = sqlsrv_query($conn,
                        "INSERT INTO Langganan 
                         (ID_Customer, ID_Karyawan, ID_Tipe, Tanggal_Mulai, Tanggal_Selesai, 
                          Total_Bayar, Metode_Pembayaran, Status, Created_By, Created_Date)
                         OUTPUT INSERTED.ID_Langganan
                         VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?, GETDATE())",
                        array($id_customer, 2, $id_tipe, $tanggal_mulai, $tanggal_selesai, 
                              $total_bayar, $metode_pembayaran, $nama_customer)
                    );
                    
                    if ($stmt_insert) {
                        // Get the last inserted ID
                        $id_row = sqlsrv_fetch_array($stmt_insert, SQLSRV_FETCH_ASSOC);
                        $last_inserted_id = $id_row['ID_Langganan'] ?? 0;
                        
                        // Set flags to show payment modal instead of redirect
                        $show_payment_modal = true;
                        $payment_total = $total_bayar;
                        $payment_method = $metode_pembayaran;
                    } else {
                        $errors = sqlsrv_errors();
                        $pembelian_error = 'Gagal mendaftar member. Error: ' . ($errors[0]['message'] ?? 'Unknown error');
                    }
                }
            }
        }
    }
}

// ============================================================================
// URL PARAMETER NOTIFICATION
// ============================================================================
$notif_status = $_GET['status'] ?? '';
$notif_msg = $_GET['msg'] ?? '';

function rupiahFormat($n) { 
    return 'Rp ' . number_format($n, 0, ',', '.'); 
}

function formatTanggal($tanggal) {
    if (empty($tanggal)) return '-';
    if (is_object($tanggal) && method_exists($tanggal, 'format')) {
        return $tanggal->format('d M Y');
    }
    return date('d M Y', strtotime($tanggal));
}

$status_labels = [
    0 => ['label' => 'Menunggu Konfirmasi', 'class' => 'sp-pending', 'icon' => 'fa-clock'],
    1 => ['label' => 'Aktif', 'class' => 'sp-active', 'icon' => 'fa-check-circle'],
    2 => ['label' => 'Berakhir', 'class' => 'sp-inactive', 'icon' => 'fa-flag-checkered'],
    3 => ['label' => 'Ditolak', 'class' => 'sp-inactive', 'icon' => 'fa-ban']
];

$photo_profile = $customer_data['Photo_Profile'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Langganan Member | HoopBall</title>
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
            --purple: #AF52DE;
            --purple-lt: rgba(175,82,222,.10);
            --orange: #FF9500;
            --orange-lt: rgba(255,149,0,.10);
            --orange-glow: rgba(255, 90, 31, 0.15);
            --border: #E2E8F0;
            --border-lt: #F1F5F9;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --muted: #94A3B8;
            --bg: #F8FAFC;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--light-bg); 
            color: #111; 
            overflow-x: hidden; 
        }

        /* ---- NAVBAR ---- */
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

        /* ---- MEMBER BADGE ---- */
        .member-badge-nav {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--green-lt);
            border: 1px solid var(--green);
            color: var(--green);
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 700;
            margin-left: 8px;
        }

        /* ---- HERO SECTION ---- */
        .hero {
            background: linear-gradient(135deg, #0B0B0C 0%, #1a1a2e 100%);
            padding: 60px 80px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 40px;
            position: relative;
            overflow: hidden;
        }
        .hero::before {
            content: '';
            position: absolute;
            right: -100px;
            top: -100px;
            width: 400px;
            height: 400px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(255,82,0,.15) 0%, transparent 70%);
        }
        .hero-left {
            max-width: 600px;
            position: relative;
            z-index: 1;
        }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--primary);
            color: var(--white);
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 20px;
        }
        .hero-title {
            font-size: 42px;
            font-weight: 800;
            color: var(--white);
            line-height: 1.2;
            margin-bottom: 16px;
        }
        .hero-title span {
            color: var(--primary);
        }
        .hero-desc {
            color: #A0A0A5;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        /* ---- MEMBER STATUS CARD ---- */
        .member-status-card {
            background: var(--white);
            border-radius: 16px;
            padding: 28px;
            border: 1px solid #E5E5EA;
            position: relative;
            z-index: 1;
            min-width: 340px;
        }
        .member-status-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
            padding-bottom: 20px;
            border-bottom: 1px solid #F2F2F7;
        }
        .member-status-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .member-status-icon.active {
            background: var(--green-lt);
            color: var(--green);
        }
        .member-status-icon.inactive {
            background: var(--red-lt);
            color: var(--red);
        }
        .member-status-icon.pending {
            background: var(--yellow-lt);
            color: #D97706;
        }
        .member-status-text h3 {
            font-size: 18px;
            font-weight: 800;
            color: #1C1C1E;
        }
        .member-status-text p {
            font-size: 13px;
            color: #8E8E93;
            margin-top: 2px;
        }
        .member-detail-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #F2F2F7;
        }
        .member-detail-row:last-child {
            border-bottom: none;
        }
        .member-detail-label {
            font-size: 13px;
            color: #8E8E93;
            font-weight: 500;
        }
        .member-detail-value {
            font-size: 14px;
            font-weight: 700;
            color: #1C1C1E;
        }
        .member-detail-value.green { color: var(--green); }
        .member-detail-value.primary { color: var(--primary); }
        .member-detail-value.yellow { color: #D97706; }

        /* ---- MAIN CONTAINER ---- */
        .main-container {
            padding: 60px 80px;
            max-width: 1440px;
            margin: 0 auto;
        }

        /* ---- SECTION HEADER ---- */
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-bottom: 28px;
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

        /* ---- TIPE MEMBER CARDS ---- */
        .pricing-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 60px;
        }
        .pricing-card {
            background: var(--white);
            border: 2px solid #E5E5EA;
            border-radius: 16px;
            padding: 32px;
            position: relative;
            transition: all 0.3s ease;
        }
        .pricing-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.08);
        }
        .pricing-card.recommended {
            border-color: var(--primary);
            box-shadow: 0 4px 20px rgba(255,82,0,.1);
        }
        .pricing-card.recommended::before {
            content: 'POPULER';
            position: absolute;
            top: -12px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--primary);
            color: var(--white);
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 1px;
        }
        .pricing-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            margin-bottom: 20px;
        }
        .pricing-icon.silver { background: var(--blue-lt); color: var(--blue); }
        .pricing-icon.gold { background: var(--orange-lt); color: var(--orange); }
        .pricing-icon.platinum { background: var(--purple-lt); color: var(--purple); }
        
        .pricing-name {
            font-size: 22px;
            font-weight: 800;
            color: #1C1C1E;
            margin-bottom: 4px;
        }
        .pricing-desc {
            font-size: 13px;
            color: #8E8E93;
            margin-bottom: 20px;
        }
        .pricing-price {
            font-size: 36px;
            font-weight: 800;
            color: var(--primary);
            margin-bottom: 4px;
        }
        .pricing-price span {
            font-size: 14px;
            color: #8E8E93;
            font-weight: 500;
        }
        .pricing-potongan {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--green-lt);
            color: var(--green);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 24px;
        }
        .pricing-features {
            list-style: none;
            margin-bottom: 24px;
        }
        .pricing-features li {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 0;
            font-size: 14px;
            color: #1C1C1E;
            border-bottom: 1px solid #F2F2F7;
        }
        .pricing-features li:last-child {
            border-bottom: none;
        }
        .pricing-features li i {
            color: var(--green);
            font-size: 14px;
        }
        .btn-pilih {
            width: 100%;
            background: var(--primary);
            color: var(--white);
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-pilih:hover {
            background: var(--primary-hover);
        }
        .btn-pilih:disabled {
            background: #C7C7CC;
            cursor: not-allowed;
        }

        /* ---- MODAL PEMBAYARAN ---- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-box {
            background: var(--white);
            border-radius: 20px;
            width: 480px;
            max-width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            padding: 32px;
            animation: slideUp 0.3s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .modal-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }
        .modal-header h3 {
            font-size: 20px;
            font-weight: 800;
            color: #1C1C1E;
        }
        .modal-close {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #F2F2F7;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: #8E8E93;
            font-size: 14px;
            transition: 0.2s;
        }
        .modal-close:hover {
            background: #E5E5EA;
            color: #1C1C1E;
        }
        .modal-summary {
            background: #F8F9FA;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .modal-summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            font-size: 14px;
        }
        .modal-summary-row.total {
            border-top: 2px solid #E5E5EA;
            padding-top: 12px;
            margin-top: 8px;
            font-weight: 800;
            font-size: 18px;
            color: var(--primary);
        }
        .metode-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 24px;
        }
        .metode-item {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            border: 2px solid #E5E5EA;
            border-radius: 12px;
            cursor: pointer;
            transition: 0.2s;
        }
        .metode-item:hover {
            border-color: var(--primary);
        }
        .metode-item.selected {
            border-color: var(--primary);
            background: var(--orange-lt);
        }
        .metode-item i {
            font-size: 24px;
            color: var(--primary);
            width: 32px;
            text-align: center;
        }
        .metode-item span {
            font-size: 14px;
            font-weight: 700;
            color: #1C1C1E;
        }
        .btn-bayar {
            width: 100%;
            background: var(--primary);
            color: var(--white);
            border: none;
            padding: 16px;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-bayar:hover {
            background: var(--primary-hover);
        }

        /* ---- RIWAYAT LANGGANAN ---- */
        .riwayat-section {
            margin-bottom: 60px;
        }
        .riwayat-card {
            background: var(--white);
            border: 1px solid #E5E5EA;
            border-radius: 16px;
            overflow: hidden;
        }
        .riwayat-table {
            width: 100%;
            border-collapse: collapse;
        }
        .riwayat-table th {
            padding: 14px 20px;
            font-size: 11px;
            font-weight: 800;
            color: #8E8E93;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #F2F2F7;
            text-align: left;
            background: #FAFAFA;
        }
        .riwayat-table td {
            padding: 16px 20px;
            font-size: 14px;
            border-bottom: 1px solid #F2F2F7;
            vertical-align: middle;
        }
        .riwayat-table tr:last-child td {
            border-bottom: none;
        }
        .riwayat-table tbody tr:hover {
            background: #FAFAFA;
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
        .sp-pending { background: var(--yellow-lt); color: #D97706; }
        .sp-inactive { background: var(--red-lt); color: var(--red); }
        .sp-success { background: var(--blue-lt); color: var(--blue); }

        /* ============ FOOTER ============ */
footer { background:var(--dark-bg); color:#8E8E93; padding:80px 80px 40px; border-top:1px solid #1C1C1E; position:relative; overflow:hidden; }
footer::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,var(--primary),transparent); animation:shimmer 3s linear infinite; background-size:200% 100%; }
.footer-grid { display:grid; grid-template-columns:1.5fr 1fr 1fr 1.2fr; gap:40px; margin-bottom:60px; }
.footer-logo { display:flex; align-items:center; gap:10px; margin-bottom:16px; transition:transform 0.3s ease; }
.footer-logo:hover { transform:scale(1.05); }
.footer-logo img { height:70px; transition:transform 0.5s ease; }
.footer-logo:hover img { transform:rotate(5deg); }
.footer-logo span { color:var(--white); font-size:20px; font-weight:800; }
.footer-desc { font-size:13px; line-height:1.6; margin-bottom:24px; }
.social-links { display:flex; gap:12px; }
.social-btn { width:36px; height:36px; border-radius:50%; background:#1C1C1E; color:var(--white); display:flex; align-items:center; justify-content:center; text-decoration:none; transition:all 0.3s cubic-bezier(0.34,1.56,0.64,1); }
.social-btn:hover { background:var(--primary); transform:translateY(-3px) scale(1.1); box-shadow:0 8px 20px rgba(255,82,0,0.3); }
.social-btn:active { transform:scale(0.95); }
.footer-col h4 { color:var(--white); font-size:15px; font-weight:700; margin-bottom:20px; position:relative; display:inline-block; }
.footer-col h4::after { content:''; position:absolute; bottom:-4px; left:0; width:30px; height:2px; background:var(--primary); transition:width 0.3s ease; }
.footer-col:hover h4::after { width:100%; }
.footer-col ul { list-style:none; }
.footer-col ul li { margin-bottom:12px; }
.footer-col ul li a { color:#8E8E93; text-decoration:none; font-size:13px; transition:all 0.3s ease; display:inline-block; position:relative; }
.footer-col ul li a::after { content:''; position:absolute; bottom:-2px; left:0; width:0; height:1px; background:var(--primary); transition:width 0.3s ease; }
.footer-col ul li a:hover { color:var(--white); transform:translateX(5px); }
.footer-col ul li a:hover::after { width:100%; }
.contact-item { display:flex; gap:12px; font-size:13px; line-height:1.5; margin-bottom:16px; transition:all 0.3s ease; padding:4px; border-radius:6px; }
.contact-item:hover { background:rgba(255,82,0,0.05); transform:translateX(5px); }
.contact-item i { color:var(--primary); font-size:14px; margin-top:3px; transition:transform 0.3s ease; }
.contact-item:hover i { transform:scale(1.2); }
.footer-bottom { border-top:1px solid #1C1C1E; padding-top:30px; text-align:center; font-size:13px; position:relative; }

.swal-toast { border-radius:12px !important; font-family:'Plus Jakarta Sans',sans-serif !important; }

/* ---- RESPONSIVE ---- */
        @media(max-width: 1100px) {
            .pricing-grid { grid-template-columns: 1fr; }
            .hero { flex-direction: column; padding: 40px; }
            .member-status-card { min-width: auto; width: 100%; }
            .main-container { padding: 40px; }
            nav { padding: 0 40px; }
        }
        @media(max-width: 768px) {
            .nav-links { display: none; }
            .main-container { padding: 20px; }
            nav { padding: 0 20px; }
            .hero { padding: 30px 20px; }
            .hero-title { font-size: 28px; }
        }

        .swal-toast { border-radius: 12px !important; font-family: 'Plus Jakarta Sans', sans-serif !important; }

        /* ---- PAYMENT INSTRUCTION MODAL (FROM BOOKING) ---- */
        .booking-modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
            padding: 20px;
            animation: fadeInModal 0.25s ease-out forwards;
        }
        .booking-modal-overlay.active {
            display: flex;
        }
        @keyframes fadeInModal {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .summary-card {
            background: #fff;
            border-radius: 20px !important;
            border: none !important;
            padding: 30px !important;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15) !important;
            animation: slideInModal 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes slideInModal {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .booking-modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            background: var(--border-lt);
            border: none;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            color: var(--text-secondary);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 10;
        }
        .booking-modal-close:hover {
            background: var(--red-lt);
            color: var(--red);
        }
        .btn-toast-action {
            background: var(--orange);
            color: #fff;
            border: none;
            border-radius: 8px;
            padding: 8px 14px;
            font-family: inherit;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s ease;
            white-space: nowrap;
        }
        .btn-toast-action:hover {
            background: var(--orange-hover);
        }
        .divider {
            height: 1px;
            background: var(--border-lt);
            margin: 25px 0;
        }
        .btn-booking {
            width: 100%;
            background: var(--orange);
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 14px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
            transition: background 0.2s ease;
        }
        .btn-booking:hover:not(:disabled) {
            background: var(--orange-hover);
        }
        .btn-booking:disabled {
            background: var(--muted);
            cursor: not-allowed;
        }
        .alert-banner {
            background: #EFF6FF;
            border: 1px solid rgba(0, 122, 255, 0.15);
            border-radius: 10px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 16px;
        }
        .alert-banner i {
            color: var(--blue);
            font-size: 16px;
        }
        .alert-banner-text {
            font-size: 11.5px;
            color: #1E40AF;
            line-height: 1.5;
        }
        .form-control {
            width: 100%;
            padding: 11px 40px 11px 40px; 
            border: 1px solid var(--border);
            border-radius: 10px;
            font-family: inherit;
            font-size: 13px;
            color: var(--text-primary);
            background-color: #fff;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394A3B8' stroke-width='2.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19.5 8.25l-7.5 7.5-7.5-7.5'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 14px;
            outline: none;
            appearance: none;
        }
        .form-control:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px var(--orange-glow);
        }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav>
    <a href="view_customer.php" class="nav-logo">
        <img src="../asset/image/logo2.png" alt="HoopBall">
    </a>
    <div class="nav-links">
        <a href="view_customer.php">Beranda</a>
        <a href="booking_customer.php">Booking</a>
        <a href="jadwal_customer.php">Jadwal</a>
        <a href="langganan_customer.php" class="active">Member</a>
        <a href="pembelian_alat.php">Pembelian</a>
        <a href="tentang_customer.php">Tentang</a>
        <a href="kontak_customer.php">Kontak</a>
    </div>

    <div class="nav-user-container">
        <div class="nav-user">
            <?php if (!empty($photo_profile) && file_exists($photo_profile)): ?>
                <img src="<?php echo htmlspecialchars($photo_profile); ?>" alt="Avatar" class="user-avatar">
            <?php else: ?>
                <i class="fa-solid fa-circle-user user-icon"></i>
            <?php endif; ?>
            <span><?php echo htmlspecialchars($nama_customer); ?></span>
            <?php if ($has_member): ?>
                <span class="member-badge-nav"><i class="fa-solid fa-crown"></i> <?php echo htmlspecialchars($member_tipe); ?></span>
            <?php endif; ?>
            <i class="fa-solid fa-chevron-down arrow"></i>
        </div>
        <div class="dropdown-menu">
            <div class="user-info-header">
                <span class="u-name"><?php echo htmlspecialchars($nama_customer); ?></span>
                <span class="u-role">Customer <?php echo $has_member ? '• Member ' . htmlspecialchars($member_tipe) : ''; ?></span>
            </div>
            <a href="../profile/profile_customer.php"><i class="fa-solid fa-user"></i> Profil Saya</a>
            <a href="booking_customer.php"><i class="fa-solid fa-calendar-check"></i> Riwayat Booking</a>
            <a href="langganan_customer.php"><i class="fa-solid fa-crown"></i> Langganan Member</a>
            <div class="dropdown-divider"></div>
            <a href="../login/logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
        </div>
    </div>
</nav>

<!-- HERO SECTION -->
<section class="hero">
    <div class="hero-left">
        <div class="hero-badge">
            <i class="fa-solid fa-crown"></i> LANGGANAN MEMBER
        </div>
        <h1 class="hero-title">Jadi Member,<br><span>Main Makin Hemat!</span></h1>
        <p class="hero-desc">Dapatkan potongan harga khusus, prioritas jadwal, dan promo eksklusif dengan berlangganan member HoopBall.</p>
    </div>

    <!-- MEMBER STATUS CARD -->
    <div class="member-status-card">
        <div class="member-status-header">
            <div class="member-status-icon <?php echo $has_member ? 'active' : ($member_aktif ? 'pending' : 'inactive'); ?>">
                <i class="fa-solid <?php echo $has_member ? 'fa-crown' : ($member_aktif ? 'fa-clock' : 'fa-user'); ?>"></i>
            </div>
            <div class="member-status-text">
                <h3>
                    <?php 
                    if ($has_member) {
                        echo 'Member ' . htmlspecialchars($member_tipe) . ' Aktif';
                    } elseif ($member_aktif) {
                        echo 'Menunggu Konfirmasi';
                    } else {
                        echo 'Belum Berlangganan';
                    }
                    ?>
                </h3>
                <p>
                    <?php 
                    if ($has_member) {
                        echo 'Nikmati keuntungan member Anda';
                    } elseif ($member_aktif) {
                        echo 'Pendaftaran member sedang diproses oleh admin';
                    } else {
                        echo 'Daftar sekarang untuk mendapatkan keuntungan';
                    }
                    ?>
                </p>
            </div>
        </div>
        
        <?php if ($has_member): ?>
        <div class="member-detail-row">
            <span class="member-detail-label">Tipe Member</span>
            <span class="member-detail-value primary"><?php echo htmlspecialchars($member_tipe); ?></span>
        </div>
        <div class="member-detail-row">
            <span class="member-detail-label">Potongan Harga</span>
            <span class="member-detail-value green"><?php echo rupiahFormat($member_aktif['Potongan_Harga']); ?> /booking</span>
        </div>
        <div class="member-detail-row">
            <span class="member-detail-label">Tanggal Mulai</span>
            <span class="member-detail-value"><?php echo formatTanggal($member_aktif['Tanggal_Mulai']); ?></span>
        </div>
        <div class="member-detail-row">
            <span class="member-detail-label">Berlaku Sampai</span>
            <span class="member-detail-value"><?php echo formatTanggal($member_aktif['Tanggal_Selesai']); ?></span>
        </div>
        <div class="member-detail-row">
            <span class="member-detail-label">Sisa Hari</span>
            <span class="member-detail-value green">
               <?php 
                    $tgl_selesai = $member_aktif['Tanggal_Selesai'];
                    if (is_object($tgl_selesai) && method_exists($tgl_selesai, 'format')) {
                        $timestamp_selesai = $tgl_selesai->getTimestamp();
                    } else {
                        $timestamp_selesai = strtotime($tgl_selesai);
                    }
                    $sisa = ceil(($timestamp_selesai - time()) / 86400);
                    echo $sisa > 0 ? $sisa . ' hari' : 'Hari ini berakhir';
                ?>
            </span>
        </div>
        <?php elseif ($member_aktif): ?>
        <div class="member-detail-row">
            <span class="member-detail-label">Tipe Member</span>
            <span class="member-detail-value primary"><?php echo htmlspecialchars($member_aktif['Nama_Tipe']); ?></span>
        </div>
        <div class="member-detail-row">
            <span class="member-detail-label">Total Bayar</span>
            <span class="member-detail-value"><?php echo rupiahFormat($member_aktif['Total_Bayar']); ?></span>
        </div>
        <div class="member-detail-row">
            <span class="member-detail-label">Tanggal Daftar</span>
            <span class="member-detail-value"><?php echo formatTanggal($member_aktif['Created_Date']); ?></span>
        </div>
        <div class="member-detail-row">
            <span class="member-detail-label">Status</span>
            <span class="member-detail-value yellow">Menunggu Konfirmasi Admin</span>
        </div>
        <?php else: ?>
        <div class="member-detail-row">
            <span class="member-detail-label">Status</span>
            <span class="member-detail-value" style="color: var(--red);">Non-Member</span>
        </div>
        <div class="member-detail-row">
            <span class="member-detail-label">Potongan Harga</span>
            <span class="member-detail-value">-</span>
        </div>
        <div class="member-detail-row">
            <span class="member-detail-label">Prioritas Jadwal</span>
            <span class="member-detail-value">-</span>
        </div>
        <p style="font-size: 13px; color: #8E8E93; margin-top: 16px; text-align: center; line-height: 1.5;">
            Pilih paket member di bawah untuk mulai berlangganan
        </p>
        <?php endif; ?>
    </div>
</section>

<!-- MAIN CONTENT -->
<main class="main-container">

    <!-- PILIH PAKET MEMBER -->
    <section>
        <div class="section-header">
            <div>
                <h2 class="section-title">Pilih Paket Member</h2>
                <p class="section-subtitle">Pilih tipe member yang sesuai dengan kebutuhan Anda.</p>
            </div>
        </div>

        <div class="pricing-grid">
            <?php 
            $icon_map = ['Silver' => 'fa-medal', 'Gold' => 'fa-trophy', 'Platinum' => 'fa-crown'];
            $class_map = ['Silver' => 'silver', 'Gold' => 'gold', 'Platinum' => 'platinum'];
            $idx = 0;
            foreach ($tipe_member_list as $tipe): 
                $is_recommended = ($tipe['Nama_Tipe'] === 'Gold');
                $icon = $icon_map[$tipe['Nama_Tipe']] ?? 'fa-star';
                $cls = $class_map[$tipe['Nama_Tipe']] ?? 'silver';
            ?>
            <div class="pricing-card <?php echo $is_recommended ? 'recommended' : ''; ?>">
                <div class="pricing-icon <?php echo $cls; ?>">
                    <i class="fa-solid <?php echo $icon; ?>"></i>
                </div>
                <h3 class="pricing-name"><?php echo htmlspecialchars($tipe['Nama_Tipe']); ?></h3>
                <p class="pricing-desc">Paket member <?php echo htmlspecialchars($tipe['Nama_Tipe']); ?> 30 hari</p>
                <div class="pricing-price">
                    <?php echo rupiahFormat($tipe['Harga_Member']); ?>
                    <span>/ 30 hari</span>
                </div>
                <div class="pricing-potongan">
                    <i class="fa-solid fa-tag"></i> Hemat <?php echo rupiahFormat($tipe['Potongan_Harga']); ?> per booking
                </div>
                <ul class="pricing-features">
                    <li><i class="fa-solid fa-check"></i> Potongan <?php echo rupiahFormat($tipe['Potongan_Harga']); ?> per booking</li>
                    <li><i class="fa-solid fa-check"></i> Masa aktif 30 hari</li>
                    <li><i class="fa-solid fa-check"></i> Prioritas jadwal</li>
                    <li><i class="fa-solid fa-check"></i> Promo eksklusif member</li>
                    <?php if ($tipe['Nama_Tipe'] === 'Platinum'): ?>
                    <li><i class="fa-solid fa-check"></i> Diskon pembelian alat 5%</li>
                    <?php endif; ?>
                </ul>
                <button class="btn-pilih" 
                        onclick="bukaModal(<?php echo $tipe['ID_Tipe']; ?>, '<?php echo htmlspecialchars($tipe['Nama_Tipe']); ?>', <?php echo $tipe['Harga_Member']; ?>)"
                        <?php echo ($has_member || $member_aktif) ? 'disabled' : ''; ?>>
                    <?php 
                    if ($has_member) {
                        echo 'Sudah Aktif';
                    } elseif ($member_aktif) {
                        echo 'Menunggu Konfirmasi';
                    } else {
                        echo '<i class="fa-solid fa-crown"></i> Pilih Paket Ini';
                    }
                    ?>
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- RIWAYAT LANGGANAN -->
    <?php if (!empty($riwayat_langganan)): ?>
    <section class="riwayat-section">
        <div class="section-header">
            <div>
                <h2 class="section-title">Riwayat Langganan</h2>
                <p class="section-subtitle">Daftar langganan member yang pernah Anda lakukan.</p>
            </div>
        </div>
        <div class="riwayat-card">
            <table class="riwayat-table">
                <thead>
                    <tr>
                        <th>Tipe Member</th>
                        <th>Tanggal Mulai</th>
                        <th>Tanggal Selesai</th>
                        <th>Total Bayar</th>
                        <th>Metode</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riwayat_langganan as $rl): 
                        $status = $status_labels[$rl['Status']] ?? $status_labels[0];
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($rl['Nama_Tipe']); ?></strong>
                            <div style="font-size: 12px; color: #8E8E93; margin-top: 2px;">
                                Potongan <?php echo rupiahFormat($rl['Potongan_Harga']); ?>
                            </div>
                        </td>
                        <td><?php echo formatTanggal($rl['Tanggal_Mulai']); ?></td>
                        <td><?php echo formatTanggal($rl['Tanggal_Selesai']); ?></td>
                        <td><strong style="color: var(--primary);"><?php echo rupiahFormat($rl['Total_Bayar']); ?></strong></td>
                        <td><?php echo htmlspecialchars($rl['Metode_Pembayaran']); ?></td>
                        <td>
                            <span class="status-pill <?php echo $status['class']; ?>">
                                <i class="fa-solid <?php echo $status['icon']; ?>"></i> <?php echo $status['label']; ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
    <?php endif; ?>

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
                <li><a href="#">Jadwal</a></li>
                <li><a href="../customer/langganan_customer.php">Member</a></li>
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