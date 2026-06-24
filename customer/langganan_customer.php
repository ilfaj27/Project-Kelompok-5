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
$pembelian_msg = '';
$pembelian_error = '';

if (isset($_POST['beli_langganan'])) {
    $id_tipe = $_POST['id_tipe'] ?? '';
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? '';
    
    if (empty($id_tipe) || empty($metode_pembayaran)) {
        $pembelian_error = 'Pilih tipe member dan metode pembayaran!';
    } else {
        $stmt_tipe = sqlsrv_query($conn, 
            "SELECT * FROM Tipe_Member WHERE ID_Tipe = ? AND Status = 1 AND Is_Deleted = 0", 
            array($id_tipe)
        );
        $tipe_data = sqlsrv_fetch_array($stmt_tipe, SQLSRV_FETCH_ASSOC);
        
        if (!$tipe_data) {
            $pembelian_error = 'Tipe member tidak valid!';
        } else {
            $cek_aktif = sqlsrv_query($conn,
                "SELECT COUNT(*) as total FROM Langganan 
                 WHERE ID_Customer = ? AND Status = 1 
                 AND GETDATE() BETWEEN Tanggal_Mulai AND Tanggal_Selesai",
                array($id_customer)
            );
            $row_aktif = sqlsrv_fetch_array($cek_aktif, SQLSRV_FETCH_ASSOC);
            
            if ($row_aktif['total'] > 0) {
                $pembelian_error = 'Anda masih memiliki langganan member aktif.';
            } else {
                $cek_pending = sqlsrv_query($conn,
                    "SELECT COUNT(*) as total FROM Langganan 
                     WHERE ID_Customer = ? AND Status = 0",
                    array($id_customer)
                );
                $row_pending = sqlsrv_fetch_array($cek_pending, SQLSRV_FETCH_ASSOC);
                
                if ($row_pending['total'] > 0) {
                    $pembelian_error = 'Anda memiliki pendaftaran yang menunggu konfirmasi.';
                } else {
                    $tanggal_mulai = date('Y-m-d');
                    $tanggal_selesai = date('Y-m-d', strtotime('+30 days'));
                    $total_bayar = $tipe_data['Harga_Member'];
                    
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
                        $id_row = sqlsrv_fetch_array($stmt_insert, SQLSRV_FETCH_ASSOC);
                        $last_id = $id_row['ID_Langganan'] ?? 0;
                        header("Location: langganan_customer.php?status=success&msg=Pendaftaran member berhasil! ID: LANG-$last_id. Menunggu konfirmasi admin.");
                        exit();
                    } else {
                        $errors = sqlsrv_errors();
                        $pembelian_error = 'Gagal mendaftar. Error: ' . ($errors[0]['message'] ?? 'Unknown');
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Barlow+Condensed:wght@700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #FF5200;
            --primary-hover: #E04800;
            --primary-light: rgba(255,82,0,0.1);
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
            --orange: #FF5A1F;
            --orange-hover: #E0440E;
            --orange-lt: rgba(255,90,31,0.06);
            --orange-glow: rgba(255,90,31,0.15);
            --border: #E2E8F0;
            --border-lt: #F1F5F9;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --muted: #94A3B8;
            --bg: #F8FAFC;
            --transition-smooth: all 0.3s cubic-bezier(0.4,0,0.2,1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--light-bg);color:#111;overflow-x:hidden}

        ::-webkit-scrollbar{display:none}
        html,body{-ms-overflow-style:none;scrollbar-width:none}
        ::selection{background:rgba(255,82,0,0.3);color:#1C1C1E}

        /* ═══ ANIMATIONS ═══ */
        @keyframes fadeInUp{from{opacity:0;transform:translateY(40px)}to{opacity:1;transform:translateY(0)}}
        @keyframes fadeInDown{from{opacity:0;transform:translateY(-30px)}to{opacity:1;transform:translateY(0)}}
        @keyframes fadeIn{from{opacity:0}to{opacity:1}}
        @keyframes scaleIn{from{opacity:0;transform:scale(0.8)}to{opacity:1;transform:scale(1)}}
        @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
        @keyframes pulse{0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(52,199,89,.4)}50%{transform:scale(1.05);box-shadow:0 0 0 15px rgba(52,199,89,0)}}
        @keyframes shimmer{0%{background-position:-200% 0}100%{background-position:200% 0}}
        @keyframes slideInModal{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
        @keyframes fadeInModal{from{opacity:0}to{opacity:1}}
        @keyframes countUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        @keyframes drawLine{from{width:0}to{width:60px}}

        /* ═══ REVEAL ═══ */
        .reveal{opacity:0;transform:translateY(40px);transition:all 0.8s cubic-bezier(0.16,1,0.3,1)}
        .reveal.active{opacity:1;transform:translateY(0)}
        .reveal-stagger .stagger-item{opacity:0;transform:translateY(30px);transition:all 0.6s cubic-bezier(0.16,1,0.3,1)}
        .reveal-stagger.active .stagger-item{opacity:1;transform:translateY(0)}
        .reveal-stagger.active .stagger-item:nth-child(1){transition-delay:0s}
        .reveal-stagger.active .stagger-item:nth-child(2){transition-delay:.1s}
        .reveal-stagger.active .stagger-item:nth-child(3){transition-delay:.2s}

        /* ═══ SCROLL PROGRESS ═══ */
        .scroll-progress{position:fixed;top:0;left:0;height:3px;background:linear-gradient(90deg,var(--primary),#FF8C42);z-index:9999;transform-origin:left;transform:scaleX(0);transition:transform 0.1s ease-out}

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
            animation: fadeInDown .6s ease-out forwards;
        }
        .nav-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            gap: 10px;
            transition: transform .3s ease;
        }
        .nav-logo:hover { transform: scale(1.05); }
        .nav-logo img {
            height: 70px;
            width: auto;
            transition: transform .5s cubic-bezier(.34,1.56,.64,1);
        }
        .nav-logo:hover img { transform: rotate(5deg) scale(1.1); }
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
            transition: var(--transition-smooth);
            position: relative;
            overflow: hidden;
        }
        .nav-links a::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: var(--transition-smooth);
            transform: translateX(-50%);
        }
        .nav-links a:hover { color: #1C1C1E; transform: translateY(-2px); }
        .nav-links a:hover::before { width: 60%; }
        .nav-links a.active { color: var(--primary); font-weight: 600; }
        .nav-links a.active::before { width: 60%; }

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
            transition: var(--transition-smooth);
        }
        .nav-user:hover { background: #E5E5EA; border-color: var(--primary); transform: scale(1.02); box-shadow: 0 4px 12px rgba(255,82,0,.15); }
        .nav-user img.user-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
            transition: transform .3s ease;
        }
        .nav-user:hover img.user-avatar { transform: scale(1.15); }
        .nav-user i.user-icon {
            font-size: 16px;
            color: var(--primary);
            transition: transform .3s ease;
        }
        .nav-user:hover i.user-icon { transform: scale(1.2); }
        .nav-user i.arrow { 
            font-size: 11px; 
            color: #8E8E93; 
            transition: .3s cubic-bezier(.34,1.56,.64,1);
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
            transform-origin: top right;
        }
        .nav-user-container:hover .dropdown-menu {
            display: block;
            animation: fadeInUp .3s cubic-bezier(.16,1,.3,1) forwards;
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
            transition: all .25s cubic-bezier(.16,1,.3,1);
            position: relative;
            overflow: hidden;
        }
        .dropdown-menu a::after {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 3px;
            height: 100%;
            background: var(--primary);
            transform: scaleY(0);
            transition: transform .25s cubic-bezier(.16,1,.3,1);
        }
        .dropdown-menu a i { 
            font-size: 14px; 
            width: 16px; 
            text-align: center; 
            transition: transform .3s ease;
        }
        .dropdown-menu a:hover {
            background: #222227;
            color: var(--primary);
            padding-left: 28px;
        }
        .dropdown-menu a:hover::after { transform: scaleY(1); }
        .dropdown-menu a:hover i { transform: scale(1.2); }
        .dropdown-divider {
            height: 1px;
            background: #2d2d33;
            margin: 6px 0;
        }
        .dropdown-menu a.logout:hover { 
            color: #ff3b30; 
        }
        .dropdown-menu a.logout:hover::after { background: #ff3b30; }

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
            animation: pulse 2s ease-in-out infinite;
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
            animation: fadeInUp 0.8s ease-out forwards;
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
            animation: fadeInUp 0.8s ease-out 0.2s forwards;
            opacity: 0;
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

        /* ═══ PAYMENT MODAL (MATCHING BOOKING) ═══ */
        .booking-modal-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;z-index:2000;padding:20px;animation:fadeInModal .25s ease-out forwards}
        .booking-modal-overlay.active{display:flex}
        .summary-card{background:#fff;border-radius:20px;padding:30px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 20px 40px rgba(0,0,0,.15);animation:slideInModal .3s cubic-bezier(.16,1,.3,1) forwards;-ms-overflow-style:none;scrollbar-width:none}
        .summary-card::-webkit-scrollbar{display:none}
        .booking-modal-close{position:absolute;top:20px;right:20px;background:var(--border-lt);border:none;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text-secondary);transition:var(--transition-smooth);z-index:10}
        .booking-modal-close:hover{background:var(--red-lt);color:var(--red);transform:rotate(90deg)}
        .summary-title{font-family:'Barlow Condensed',sans-serif;font-size:16px;font-weight:800;letter-spacing:.5px;color:var(--muted);margin-bottom:20px;text-transform:uppercase;text-align:center}
        .summary-item-info{display:flex;gap:14px;margin-bottom:20px;padding:16px;background:var(--bg);border-radius:12px;border:1px solid var(--border)}
        .summary-icon{width:48px;height:48px;border-radius:12px;background:var(--orange-lt);color:var(--orange);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
        .summary-details{display:flex;flex-direction:column;justify-content:center}
        .summary-item-name{font-size:15px;font-weight:700;color:var(--text-primary)}
        .summary-item-sub{font-size:12px;color:var(--muted);margin-top:2px}
        .pricing-breakdown{border-top:1px solid var(--border-lt);padding:16px 0;display:flex;flex-direction:column;gap:10px}
        .price-row{display:flex;justify-content:space-between;font-size:12.5px;color:var(--text-secondary);font-weight:500}
        .price-row.total-row{margin-top:6px;font-size:14px;color:var(--text-primary);font-weight:800;align-items:center}
        .price-row.total-row .total-amount{font-size:24px;color:var(--orange);font-weight:900;animation:countUp .5s ease-out}
        .payment-section{border-top:1px solid var(--border-lt);padding:20px 0 10px}
        .payment-header{font-size:12.5px;font-weight:700;color:var(--text-primary);margin-bottom:12px;display:flex;align-items:center;gap:6px}
        .payment-header i{color:var(--muted)}
        .payment-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
        .payment-card{border:1px solid var(--border);border-radius:10px;padding:12px;cursor:pointer;display:flex;align-items:center;gap:10px;transition:var(--transition-smooth);user-select:none;position:relative;overflow:hidden}
        .payment-card::before{content:'';position:absolute;top:50%;left:50%;width:0;height:0;background:rgba(255,90,31,.1);border-radius:50%;transform:translate(-50%,-50%);transition:width .4s,height .4s}
        .payment-card:hover::before{width:200px;height:200px}
        .payment-card:hover{border-color:var(--orange);transform:translateY(-2px);box-shadow:0 4px 12px rgba(255,90,31,.1)}
        .payment-card.selected{border-color:var(--orange);background:var(--orange-lt)}
        .custom-radio{width:16px;height:16px;border-radius:50%;border:1.5px solid var(--muted);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:.2s}
        .payment-card.selected .custom-radio{border-color:var(--orange)}
        .custom-radio::after{content:'';width:8px;height:8px;border-radius:50%;background:var(--orange);display:none}
        .payment-card.selected .custom-radio::after{display:block;animation:scaleIn .2s ease-out}
        .payment-card-content{display:flex;flex-direction:column;justify-content:center}
        .payment-name{font-size:11px;font-weight:700;color:var(--text-primary);line-height:1.3}
        .payment-sub{font-size:9px;color:var(--muted);margin-top:1px;font-weight:500}
        .qris-logo{font-family:'Barlow Condensed',sans-serif;font-weight:900;font-size:14px;color:#000;letter-spacing:-.5px}
        .btn-booking{width:100%;background:var(--orange);color:#fff;border:none;border-radius:12px;padding:14px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:16px;transition:var(--transition-smooth);position:relative;overflow:hidden}
        .btn-booking::before{content:'';position:absolute;top:50%;left:50%;width:0;height:0;background:rgba(255,255,255,.2);border-radius:50%;transform:translate(-50%,-50%);transition:width .6s,height .6s}
        .btn-booking:hover::before{width:400px;height:400px}
        .btn-booking:hover:not(:disabled){background:var(--orange-hover);transform:translateY(-2px);box-shadow:0 10px 30px rgba(255,90,31,.4)}
        .btn-booking:disabled{background:var(--muted);cursor:not-allowed}
        .booking-disclaimer{display:flex;align-items:center;justify-content:center;gap:6px;font-size:11px;color:var(--muted);margin-top:10px;font-weight:500}
        .booking-disclaimer i{color:var(--green);animation:pulse 2s ease-in-out infinite}

        /* ═══ PAYMENT INSTRUCTION MODAL ═══ */
        .instruction-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 16px;
            font-weight: 800;
            letter-spacing: .5px;
            color: var(--muted);
            margin-bottom: 20px;
            text-transform: uppercase;
            text-align: center;
        }
        .switch-tabs-row {
            display: flex;
            gap: 6px;
            margin-bottom: 16px;
            background: var(--border-lt);
            padding: 4px;
            border-radius: 10px;
            border: 1px solid var(--border);
        }
        .switch-tab-btn {
            flex: 1;
            padding: 10px;
            border: none;
            border-radius: 8px;
            font-family: inherit;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition-smooth);
            background: transparent;
            color: var(--text-secondary);
        }
        .switch-tab-btn.active {
            background: #fff;
            color: var(--orange);
            box-shadow: 0 2px 6px rgba(0, 0, 0, .05);
        }
        .countdown-box {
            background: var(--orange-lt);
            border: 1px solid rgba(255, 90, 31, .15);
            border-radius: 10px;
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 20px;
        }
        .countdown-box i {
            color: var(--orange);
            animation: pulse 2s infinite;
        }
        .countdown-text {
            color: var(--orange-hover);
            font-weight: 700;
            font-size: 12px;
        }
        .total-display-box {
            background: var(--bg);
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1px solid var(--border);
            text-align: center;
        }
        .total-display-label {
            font-size: 11px;
            color: var(--text-secondary);
            font-weight: 600;
            text-transform: uppercase;
        }
        .total-display-amount {
            font-size: 24px;
            color: var(--orange);
            font-weight: 900;
            margin-top: 4px;
        }
        .instr-va-box{text-align:left}
        .instr-qris-box{display:none;align-items:center;flex-direction:column}
        .instr-qris-box.active{display:flex}
        .bank-info-card{background:linear-gradient(135deg,#f8fafc 0%,#f1f5f9 100%);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:20px;text-align:left;position:relative;overflow:hidden}
        .bank-info-card::before{content:'';position:absolute;top:-20px;right:-20px;width:80px;height:80px;background:rgba(255,90,31,.05);border-radius:50%}
        .bank-header{display:flex;align-items:center;gap:12px;margin-bottom:16px}
        .bank-icon{width:44px;height:44px;background:var(--orange);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;box-shadow:0 4px 12px rgba(255,90,31,.3)}
        .bank-title{font-size:13px;font-weight:800;color:var(--text-primary)}
        .bank-sub{font-size:11px;color:var(--muted);font-weight:500}
        .va-section-label{font-size:11.5px;font-weight:700;color:var(--text-secondary);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px}
        .va-input-row{display:flex;gap:8px}
        .va-input-wrap{flex:1;background:#fff;border:2px solid var(--border);border-radius:12px;padding:14px 16px;display:flex;align-items:center;gap:10px;transition:var(--transition-smooth)}
        .va-input-wrap:hover{border-color:var(--orange);box-shadow:0 4px 16px rgba(255,90,31,.1)}
        .va-input-wrap i{color:var(--orange);font-size:14px}
        .va-input-wrap input{border:none;background:transparent;font-weight:800;text-align:center;font-size:18px;letter-spacing:2px;color:var(--text-primary);font-family:'Plus Jakarta Sans',monospace;width:100%;outline:none}
        .btn-copy-va{border-radius:12px;font-size:13px;padding:14px 18px;display:flex;align-items:center;gap:6px;white-space:nowrap;background:var(--orange);color:#fff;border:none;font-weight:700;box-shadow:0 4px 12px rgba(255,90,31,.3);cursor:pointer;transition:var(--transition-smooth);font-family:inherit}
        .btn-copy-va:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(255,90,31,.4)}
        .steps-label{font-size:11.5px;font-weight:700;color:var(--text-secondary);margin-bottom:14px;text-transform:uppercase;letter-spacing:.5px;margin-top:20px}
        .step-item{display:flex;gap:14px;align-items:flex-start;padding:14px 16px;background:#fafafa;border-radius:12px;border:1px solid var(--border-lt);transition:var(--transition-smooth);margin-bottom:12px}
        .step-item:hover{background:#fff;border-color:var(--orange);transform:translateX(4px)}
        .step-item:last-child{margin-bottom:0}
        .step-num{width:28px;height:28px;background:var(--orange-lt);color:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0;margin-top:2px}
        .step-title{font-size:13px;font-weight:700;color:var(--text-primary);margin-bottom:2px}
        .step-desc{font-size:12px;color:var(--text-secondary);line-height:1.5}
        .qris-title{font-size:12.5px;font-weight:700;color:var(--text-primary);margin-bottom:12px}
        .qris-img-wrap{background:#fff;padding:12px;border:1px solid var(--border);border-radius:12px;width:fit-content;margin-bottom:16px;box-shadow:0 4px 12px rgba(0,0,0,.05)}
        .qris-img{display:block;width:170px;height:180px;object-fit:contain}
        .qris-steps-list{text-align:left;font-size:11.5px;color:var(--text-secondary);padding-left:20px;line-height:1.6;display:flex;flex-direction:column;gap:6px;width:100%}
        .modal-divider{border:none;height:1px;background:var(--border-lt);margin:20px 0}
        .btn-done-pay{width:100%;background:var(--orange);color:#fff;border:none;border-radius:12px;padding:14px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:var(--transition-smooth);position:relative;overflow:hidden}
        .btn-done-pay::before{content:'';position:absolute;top:50%;left:50%;width:0;height:0;background:rgba(255,255,255,.2);border-radius:50%;transform:translate(-50%,-50%);transition:width .6s,height .6s}
        .btn-done-pay:hover::before{width:400px;height:400px}
        .btn-done-pay:hover{background:var(--orange-hover);transform:translateY(-2px);box-shadow:0 10px 30px rgba(255,90,31,.4)}
    </style>
</head>
<body>

<div class="scroll-progress" id="scrollProgress"></div>

<!-- NAVBAR -->
<nav>
    <a href="view_customer.php" class="nav-logo">
        <img src="../asset/image/logo2.png" alt="HoopBall">
    </a>
    <div class="nav-links">
        <a href="view_customer.php">Beranda</a>
        <a href="booking_customer.php">Booking</a>
        <a href="pembatalan_customer.php">Pembatalan</a>
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
            <a href="#" onclick="confirmHapusAkun(event)" style="color:#ff3b30"><i class="fa-solid fa-trash-can"></i> Hapus Akun</a>
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
        <div class="section-header reveal">
            <div>
                <h2 class="section-title"><i class="fa-solid fa-crown" style="color:var(--primary)"></i> Pilih Paket Member</h2>
                <p class="section-subtitle">Pilih tipe member yang sesuai dengan kebutuhan Anda.</p>
            </div>
        </div>

        <div class="pricing-grid reveal-stagger">
            <?php 
            $icon_map = ['Silver' => 'fa-medal', 'Gold' => 'fa-trophy', 'Platinum' => 'fa-crown'];
            $class_map = ['Silver' => 'silver', 'Gold' => 'gold', 'Platinum' => 'platinum'];
            foreach ($tipe_member_list as $tipe): 
                $is_recommended = ($tipe['Nama_Tipe'] === 'Gold');
                $icon = $icon_map[$tipe['Nama_Tipe']] ?? 'fa-star';
                $cls = $class_map[$tipe['Nama_Tipe']] ?? 'silver';
            ?>
            <div class="pricing-card stagger-item <?php echo $is_recommended ? 'recommended' : ''; ?>">
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
    <section class="riwayat-section reveal">
        <div class="section-header">
            <div>
                <h2 class="section-title"><i class="fa-solid fa-clock-rotate-left" style="color:var(--primary)"></i> Riwayat Langganan</h2>
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
                <li><a href="pembatalan_customer.php">Jadwal</a></li>
                <li><a href="langganan_customer.php">Member</a></li>
                <li><a href="pembelian_alat.php">Pembelian</a></li>
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

<!-- ═══ MODAL 1: RINGKASAN PEMBAYARAN ═══ -->
<div class="booking-modal-overlay" id="checkoutModal">
    <div class="summary-card">
        <button class="booking-modal-close" onclick="tutupCheckoutModal()"><i class="fa-solid fa-xmark"></i></button>
        <h2 class="summary-title">Ringkasan Pembelian Member</h2>

        <div class="summary-item-info">
            <div class="summary-icon"><i class="fa-solid fa-crown"></i></div>
            <div class="summary-details">
                <div class="summary-item-name" id="summaryNamaTipe">-</div>
                <div class="summary-item-sub">Paket Member 30 Hari</div>
            </div>
        </div>

        <div class="pricing-breakdown">
            <div class="price-row">
                <span>Harga Paket</span>
                <span id="summaryHarga">Rp 0</span>
            </div>
            <div class="price-row total-row">
                <span>Total Pembayaran</span>
                <span class="total-amount" id="summaryTotal">Rp 0</span>
            </div>
        </div>

        <div class="payment-section">
            <div class="payment-header"><i class="fa-solid fa-wallet"></i> Metode Pembayaran</div>
            <div class="payment-grid">
                <div class="payment-card selected" data-method="Transfer Bank" onclick="pilihMetode('Transfer Bank', this)">
                    <div class="custom-radio"></div>
                    <div class="payment-card-content">
                        <span class="payment-name">Transfer Bank</span>
                        <span class="payment-sub">Virtual Account</span>
                    </div>
                </div>
                <div class="payment-card" data-method="QRIS" onclick="pilihMetode('QRIS', this)">
                    <div class="custom-radio"></div>
                    <div class="payment-card-content">
                        <span class="payment-name qris-logo">QRIS</span>
                        <span class="payment-sub">Scan & Bayar Instan</span>
                    </div>
                </div>
            </div>
        </div>

        <button class="btn-booking" id="btnLanjutBayar" onclick="lanjutKePembayaran()">
            <i class="fa-solid fa-lock"></i> Lanjutkan Pembayaran
        </button>
        <div class="booking-disclaimer"><i class="fa-solid fa-circle-check"></i> Enkripsi data aman terverifikasi</div>
    </div>
</div>

<!-- ═══ MODAL 2: INSTRUKSI PEMBAYARAN ═══ -->
<div class="booking-modal-overlay" id="paymentInstructionModal">
    <div class="summary-card" style="max-width:460px;text-align:center">
        <button class="booking-modal-close" onclick="tutupInstructionModal()"><i class="fa-solid fa-xmark"></i></button>
        <p class="instruction-title">Instruksi Pembayaran</p>
        <div class="switch-tabs-row">
            <button id="btnSwitchVA" class="switch-tab-btn active" onclick="switchPaymentMethod('Transfer Bank')">
                <i class="fa-solid fa-university" style="margin-right:4px"></i> Virtual Account
            </button>
            <button id="btnSwitchQRIS" class="switch-tab-btn" onclick="switchPaymentMethod('QRIS')">
                <i class="fa-solid fa-qrcode" style="margin-right:4px"></i> QRIS Scan
            </button>
        </div>
        <div class="countdown-box">
            <i class="fa-solid fa-clock"></i>
            <p class="countdown-text">Selesaikan pembayaran dalam <span id="paymentCountdown">15:00</span></p>
        </div>
        <div class="total-display-box">
            <div class="total-display-label">Total Tagihan</div>
            <div class="total-display-amount" id="paymentTotalAmount">Rp 0</div>
        </div>

        <div id="instruksiTransfer" class="instr-va-box">
            <div class="bank-info-card">
                <div class="bank-header">
                    <div class="bank-icon"><i class="fa-solid fa-building-columns"></i></div>
                    <div>
                        <div class="bank-title">Virtual Account</div>
                        <div class="bank-sub">Mandiri / BCA / BNI / BRI</div>
                    </div>
                </div>
                <div class="va-section-label">Nomor Virtual Account</div>
                <div class="va-input-row">
                    <div class="va-input-wrap">
                        <i class="fa-solid fa-hashtag"></i>
                        <input type="text" id="vaNumber" value="8801281234567890" readonly>
                    </div>
                    <button class="btn-copy-va" id="btnCopyVA" onclick="copyVA()">
                        <i class="fa-regular fa-copy"></i> Salin
                    </button>
                </div>
            </div>
            <div class="steps-label">Cara Pembayaran</div>
            <div class="step-item">
                <div class="step-num">1</div>
                <div>
                    <div class="step-title">Buka Aplikasi Banking</div>
                    <div class="step-desc">Pilih menu <strong style="color:var(--primary)">Transfer > Virtual Account</strong> pada M-Banking atau ATM Anda.</div>
                </div>
            </div>
            <div class="step-item">
                <div class="step-num">2</div>
                <div>
                    <div class="step-title">Masukkan Nomor VA</div>
                    <div class="step-desc">Masukkan nomor Virtual Account <strong style="color:var(--primary)">8801281234567890</strong>.</div>
                </div>
            </div>
            <div class="step-item" style="margin-bottom:0">
                <div class="step-num">3</div>
                <div>
                    <div class="step-title">Konfirmasi Pembayaran</div>
                    <div class="step-desc">Nominal akan otomatis muncul sesuai total tagihan. Konfirmasi dan selesaikan transaksi.</div>
                </div>
            </div>
        </div>

        <div id="instruksiQRIS" class="instr-qris-box">
            <div class="qris-title">Pindai Kode QRIS Resmi HoopBall</div>
            <div class="qris-img-wrap">
                <img id="qrisImage" src="" alt="QRIS Code" class="qris-img">
            </div>
            <ul class="qris-steps-list">
                <li>Buka aplikasi e-wallet (GoPay, OVO, Dana, LinkAja) atau Mobile Banking.</li>
                <li>Pilih opsi <strong>Scan / Bayar QRIS</strong>.</li>
                <li>Arahkan kamera ke kode QR, lalu selesaikan pembayaran.</li>
            </ul>
        </div>

        <hr class="modal-divider">
        <form method="POST" action="" id="formPembayaran">
            <input type="hidden" name="id_tipe" id="formIdTipe">
            <input type="hidden" name="metode_pembayaran" id="formMetode">
            <button type="submit" name="beli_langganan" class="btn-done-pay" id="btnDonePayment">
                Saya Sudah Bayar <i class="fa-solid fa-circle-check"></i>
            </button>
        </form>
    </div>
</div>

<script>
/* ─── SCROLL PROGRESS ─── */
window.addEventListener('scroll', () => {
    const st = document.documentElement.scrollTop || document.body.scrollTop;
    const sh = document.documentElement.scrollHeight - document.documentElement.clientHeight;
    document.getElementById('scrollProgress').style.transform = `scaleX(${st / sh})`;
});

/* ─── INTERSECTION OBSERVER (REVEAL ANIMATION) ─── */
const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('active'); });
}, { threshold: .1, rootMargin: '0px 0px -50px 0px' });
document.querySelectorAll('.reveal, .reveal-stagger').forEach(el => observer.observe(el));

/* ─── STATE ─── */
let selectedIdTipe = null;
let selectedNamaTipe = '';
let selectedHarga = 0;
let selectedPaymentMethod = 'Transfer Bank';
let countdownInterval;

function formatRupiah(n) {
    return 'Rp ' + Math.max(0, n).toLocaleString('id-ID');
}

/* ─── MODAL 1: CHECKOUT ─── */
function bukaModal(idTipe, namaTipe, harga) {
    selectedIdTipe = idTipe;
    selectedNamaTipe = namaTipe;
    selectedHarga = harga;

    document.getElementById('summaryNamaTipe').textContent = 'Member ' + namaTipe;
    document.getElementById('summaryHarga').textContent = formatRupiah(harga);
    document.getElementById('summaryTotal').textContent = formatRupiah(harga);

    document.getElementById('checkoutModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function tutupCheckoutModal() {
    document.getElementById('checkoutModal').style.display = 'none';
    document.body.style.overflow = '';
}

function pilihMetode(method, element) {
    document.querySelectorAll('#checkoutModal .payment-card').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    selectedPaymentMethod = method;
}

/* ─── MODAL 2: INSTRUKSI PEMBAYARAN ─── */
function lanjutKePembayaran() {
    tutupCheckoutModal();
    document.getElementById('paymentTotalAmount').textContent = formatRupiah(selectedHarga);
    document.getElementById('formIdTipe').value = selectedIdTipe;
    document.getElementById('formMetode').value = selectedPaymentMethod;
    switchPaymentMethod(selectedPaymentMethod);

    document.getElementById('paymentInstructionModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    startPaymentCountdown(15 * 60);
}

function tutupInstructionModal() {
    clearInterval(countdownInterval);
    document.getElementById('paymentInstructionModal').style.display = 'none';
    document.body.style.overflow = '';
}

function switchPaymentMethod(method) {
    selectedPaymentMethod = method;
    const va = document.getElementById('instruksiTransfer');
    const qris = document.getElementById('instruksiQRIS');
    const btnVA = document.getElementById('btnSwitchVA');
    const btnQR = document.getElementById('btnSwitchQRIS');

    if (method === 'Transfer Bank') {
        btnVA.classList.add('active');
        btnQR.classList.remove('active');
        va.style.display = 'block';
        qris.style.display = 'none';
    } else {
        btnQR.classList.add('active');
        btnVA.classList.remove('active');
        va.style.display = 'none';
        qris.style.display = 'flex';
        const total = document.getElementById('paymentTotalAmount').innerText.replace(/[^0-9]/g, '');
        document.getElementById('qrisImage').src = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent('HOOPBALL-MEMBER-' + selectedIdTipe + '-' + total)}`;
    }
}

function startPaymentCountdown(duration) {
    clearInterval(countdownInterval);
    let timer = duration;
    const display = document.getElementById('paymentCountdown');
    countdownInterval = setInterval(() => {
        const m = String(Math.floor(timer / 60)).padStart(2, '0');
        const s = String(timer % 60).padStart(2, '0');
        display.textContent = `${m}:${s}`;
        if (--timer < 0) {
            clearInterval(countdownInterval);
            display.textContent = 'Waktu Habis';
            document.getElementById('btnDonePayment').disabled = true;
        }
    }, 1000);
}

function copyVA() {
    const v = document.getElementById('vaNumber');
    v.select();
    v.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(v.value).then(() => {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil Disalin!',
            text: 'Nomor VA disalin ke papan klip.',
            confirmButtonColor: '#FF5A1F',
            confirmButtonText: 'OK'
        });
    });
}

/* ─── CLOSE MODAL ON OUTSIDE CLICK ─── */
window.addEventListener('click', function(e) {
    const cModal = document.getElementById('checkoutModal');
    const pModal = document.getElementById('paymentInstructionModal');
    if (e.target === cModal) tutupCheckoutModal();
    if (e.target === pModal) tutupInstructionModal();
});

/* ─── HARD DELETE AKUN ─── */
function confirmHapusAkun(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Hapus Akun Permanen?',
        html: '<strong style="color:#FF3B30;">PERINGATAN:</strong> Tindakan ini tidak dapat dibatalkan!<br><br>Akun Anda akan dihapus dari sistem dan Anda harus mendaftar ulang untuk menggunakan layanan kami.<br><br><span style="color:#8E8E93; font-size:12px;">Data akan dihapus secara permanen.</span>',
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

/* ─── URL PARAMETER NOTIFICATION ─── */
const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
const msg = urlParams.get('msg');

if (status && msg) {
    const isSuccess = status === 'success';
    Swal.fire({
        icon: isSuccess ? 'success' : 'error',
        title: isSuccess ? 'Berhasil!' : 'Gagal!',
        text: msg,
        confirmButtonColor: '#FF5A1F',
        confirmButtonText: 'OK'
    });
    window.history.replaceState({}, document.title, window.location.pathname);
}
</script>

</body>
</html>