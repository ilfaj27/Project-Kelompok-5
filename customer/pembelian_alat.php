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
// AMBIL DATA ALAT YANG AKTIF & TERSEDIA
// ============================================================================
$alat_list = [];
$query_alat = sqlsrv_query($conn, 
    "SELECT ID_Alat, Nama_Alat, Stok, Harga_Alat, Photo_Alat, Status 
     FROM Alat 
     WHERE Status = 1 AND Is_Deleted = 0 AND Stok > 0
     ORDER BY Nama_Alat ASC"
);
if ($query_alat) {
    while ($row = sqlsrv_fetch_array($query_alat, SQLSRV_FETCH_ASSOC)) {
        $alat_list[] = $row;
    }
}

// ============================================================================
// AMBIL RIWAYAT PEMBELIAN CUSTOMER
// ============================================================================
$riwayat_pembelian = [];
$query_riwayat = sqlsrv_query($conn, 
    "SELECT BA.ID_Beli, BA.Tanggal_Beli, BA.Metode_Pembayaran, BA.Total_Bayar, BA.Status,
            BA.Created_Date, K.Nama_Karyawan
     FROM Beli_Alat BA
     LEFT JOIN Karyawan K ON BA.ID_Karyawan = K.ID_Karyawan
     WHERE BA.ID_Customer = ?
     ORDER BY BA.Created_Date DESC",
    array($id_customer)
);
if ($query_riwayat) {
    while ($row = sqlsrv_fetch_array($query_riwayat, SQLSRV_FETCH_ASSOC)) {
        $id_beli = $row['ID_Beli'];
        $detail_query = sqlsrv_query($conn,
            "SELECT DBA.Jumlah, DBA.SubTotal, A.Nama_Alat, A.Harga_Alat
             FROM Detail_Beli_Alat DBA
             INNER JOIN Alat A ON DBA.ID_Alat = A.ID_Alat
             WHERE DBA.ID_Beli = ?",
            array($id_beli)
        );
        $details = [];
        if ($detail_query) {
            while ($d = sqlsrv_fetch_array($detail_query, SQLSRV_FETCH_ASSOC)) {
                $details[] = $d;
            }
        }
        $row['details'] = $details;
        $riwayat_pembelian[] = $row;
    }
}

// ============================================================================
// PROSES PEMBELIAN ALAT (KERANJANG → CHECKOUT)
// ============================================================================
$pembelian_error = '';
$show_payment_modal = false;
$payment_total = 0;
$payment_method = '';
$cart_summary = [];

if (isset($_POST['checkout'])) {
    $cart = json_decode($_POST['cart_data'] ?? '[]', true);
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? '';

    if (empty($cart) || empty($metode_pembayaran)) {
        $pembelian_error = 'Keranjang kosong atau metode pembayaran belum dipilih!';
    } else {
        $total_bayar = 0;
        $valid_cart = [];

        foreach ($cart as $item) {
            $id_alat = intval($item['id_alat']);
            $jumlah = intval($item['jumlah']);

            if ($id_alat <= 0 || $jumlah <= 0) continue;

            $cek_stok = sqlsrv_query($conn, 
                "SELECT Nama_Alat, Stok, Harga_Alat FROM Alat WHERE ID_Alat = ? AND Status = 1 AND Is_Deleted = 0",
                array($id_alat)
            );
            $alat_data = sqlsrv_fetch_array($cek_stok, SQLSRV_FETCH_ASSOC);

            if (!$alat_data) {
                $pembelian_error = 'Alat tidak ditemukan!';
                break;
            }
            if ($alat_data['Stok'] < $jumlah) {
                $pembelian_error = 'Stok ' . htmlspecialchars($alat_data['Nama_Alat']) . ' tidak mencukupi! Tersedia: ' . $alat_data['Stok'];
                break;
            }

            $subtotal = $alat_data['Harga_Alat'] * $jumlah;
            $total_bayar += $subtotal;
            $valid_cart[] = [
                'id_alat' => $id_alat,
                'nama_alat' => $alat_data['Nama_Alat'],
                'jumlah' => $jumlah,
                'harga' => $alat_data['Harga_Alat'],
                'subtotal' => $subtotal
            ];
        }

        if (empty($pembelian_error) && !empty($valid_cart)) {
            $_SESSION['pending_cart'] = $valid_cart;
            $_SESSION['pending_total'] = $total_bayar;
            $_SESSION['pending_metode'] = $metode_pembayaran;

            $show_payment_modal = true;
            $payment_total = $total_bayar;
            $payment_method = $metode_pembayaran;
            $cart_summary = $valid_cart;
        }
    }
}

// ============================================================================
// PROSES KONFIRMASI PEMBAYARAN (SIMPAN KE DATABASE)
// ============================================================================
if (isset($_POST['konfirmasi_pembayaran'])) {
    $cart = $_SESSION['pending_cart'] ?? [];
    $total_bayar = $_SESSION['pending_total'] ?? 0;
    $metode_pembayaran = $_SESSION['pending_metode'] ?? '';

    if (empty($cart) || empty($metode_pembayaran)) {
        header("Location: pembelian_alat.php?status=error&msg=Data pembayaran tidak valid");
        exit();
    }

    if (sqlsrv_begin_transaction($conn) === false) {
        header("Location: pembelian_alat.php?status=error&msg=Gagal memulai transaksi");
        exit();
    }

    $sql_beli = "INSERT INTO Beli_Alat (ID_Karyawan, ID_Customer, Tanggal_Beli, Metode_Pembayaran, Total_Bayar, Status, Created_By, Created_Date)
                 OUTPUT INSERTED.ID_Beli
                 VALUES (?, ?, GETDATE(), ?, ?, 1, ?, GETDATE())";
    $params_beli = array(2, $id_customer, $metode_pembayaran, $total_bayar, $nama_customer);
    $stmt_beli = sqlsrv_query($conn, $sql_beli, $params_beli);

    if ($stmt_beli === false) {
        sqlsrv_rollback($conn);
        $errors = sqlsrv_errors();
        header("Location: pembelian_alat.php?status=error&msg=Gagal menyimpan transaksi: " . urlencode($errors[0]['message'] ?? 'Unknown error'));
        exit();
    }

    $row_id = sqlsrv_fetch_array($stmt_beli, SQLSRV_FETCH_ASSOC);
    $id_beli = $row_id['ID_Beli'] ?? 0;

    if ($id_beli == 0) {
        sqlsrv_rollback($conn);
        header("Location: pembelian_alat.php?status=error&msg=Gagal mendapatkan ID transaksi");
        exit();
    }

    $success = true;

    foreach ($cart as $item) {
        $sql_detail = "INSERT INTO Detail_Beli_Alat (ID_Alat, ID_Beli, Jumlah, SubTotal) VALUES (?, ?, ?, ?)";
        $params_detail = array($item['id_alat'], $id_beli, $item['jumlah'], $item['subtotal']);
        $stmt_detail = sqlsrv_query($conn, $sql_detail, $params_detail);

        if ($stmt_detail === false) {
            $success = false;
            break;
        }

        $sql_update_stok = "UPDATE Alat SET Stok = Stok - ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Alat = ?";
        $params_update = array($item['jumlah'], $nama_customer, $item['id_alat']);
        $stmt_update = sqlsrv_query($conn, $sql_update_stok, $params_update);

        if ($stmt_update === false) {
            $success = false;
            break;
        }
    }

    if ($success) {
        sqlsrv_commit($conn);
        unset($_SESSION['pending_cart']);
        unset($_SESSION['pending_total']);
        unset($_SESSION['pending_metode']);

        header("Location: pembelian_alat.php?status=success&msg=Pembelian alat berhasil! Total: Rp " . number_format($total_bayar, 0, ',', '.'));
        exit();
    } else {
        sqlsrv_rollback($conn);
        $errors = sqlsrv_errors();
        header("Location: pembelian_alat.php?status=error&msg=Gagal memproses detail transaksi: " . urlencode($errors[0]['message'] ?? 'Unknown error'));
        exit();
    }
}

// ============================================================================
// BATALKAN PEMBAYARAN
// ============================================================================
if (isset($_POST['batal_pembayaran'])) {
    unset($_SESSION['pending_cart']);
    unset($_SESSION['pending_total']);
    unset($_SESSION['pending_metode']);
    header("Location: pembelian_alat.php?status=info&msg=Pembayaran dibatalkan");
    exit();
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
    0 => ['label' => 'Menunggu', 'class' => 'sp-pending', 'icon' => 'fa-clock'],
    1 => ['label' => 'Berhasil', 'class' => 'sp-active', 'icon' => 'fa-check-circle']
];

$photo_profile = $customer_data['Photo_Profile'] ?? '';

// Cek member aktif untuk badge
$member_aktif = null;
$member_check = sqlsrv_query($conn, 
    "SELECT TOP 1 L.*, T.Nama_Tipe 
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
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pembelian Alat | HoopBall</title>
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
            --shopee-orange: #EE4D2D;
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

        /* ---- KERANJANG WIDGET ---- */
        .cart-widget {
            background: var(--white);
            border-radius: 16px;
            padding: 28px;
            border: 1px solid #E5E5EA;
            position: relative;
            z-index: 1;
            min-width: 340px;
            max-width: 380px;
        }
        .cart-widget-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 16px;
            border-bottom: 2px solid #F2F2F7;
        }
        .cart-widget-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            background: var(--orange-lt);
            color: var(--orange);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        .cart-widget-title {
            font-size: 16px;
            font-weight: 800;
            color: #1C1C1E;
        }
        .cart-widget-subtitle {
            font-size: 12px;
            color: #8E8E93;
        }
        .cart-items {
            max-height: 200px;
            overflow-y: auto;
            margin-bottom: 16px;
        }
        .cart-items::-webkit-scrollbar { width: 4px; }
        .cart-items::-webkit-scrollbar-thumb { background: #E5E5EA; border-radius: 4px; }
        .cart-empty {
            text-align: center;
            padding: 24px 0;
            color: #8E8E93;
            font-size: 13px;
        }
        .cart-empty i {
            font-size: 32px;
            margin-bottom: 8px;
            display: block;
            opacity: 0.5;
        }
        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #F2F2F7;
        }
        .cart-item:last-child { border-bottom: none; }
        .cart-item-info { flex: 1; }
        .cart-item-name {
            font-size: 13px;
            font-weight: 700;
            color: #1C1C1E;
        }
        .cart-item-qty {
            font-size: 11px;
            color: #8E8E93;
        }
        .cart-item-price {
            font-size: 13px;
            font-weight: 800;
            color: var(--shopee-orange);
        }
        .cart-item-remove {
            background: none;
            border: none;
            color: var(--red);
            cursor: pointer;
            font-size: 12px;
            margin-left: 8px;
            padding: 4px;
            border-radius: 4px;
            transition: 0.2s;
        }
        .cart-item-remove:hover { background: var(--red-lt); }
        .cart-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 2px solid #F2F2F7;
            margin-bottom: 16px;
        }
        .cart-total-label {
            font-size: 14px;
            font-weight: 600;
            color: #1C1C1E;
        }
        .cart-total-value {
            font-size: 20px;
            font-weight: 800;
            color: var(--shopee-orange);
        }
        .btn-checkout {
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
        .btn-checkout:hover { background: var(--primary-hover); }
        .btn-checkout:disabled {
            background: #C7C7CC;
            cursor: not-allowed;
        }

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

        /* ---- ALAT GRID ---- */
        .alat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 24px;
            margin-bottom: 60px;
        }
        .alat-card {
            background: var(--white);
            border-radius: 16px;
            border: 1px solid #E5E5EA;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        .alat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 32px rgba(0,0,0,0.08);
            border-color: var(--primary);
        }
        .alat-card-photo-wrap {
            position: relative;
            width: 100%;
            aspect-ratio: 1 / 1;
            background: #F8F9FA;
            overflow: hidden;
        }
        .alat-card-photo-wrap img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .alat-card-photo-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%);
        }
        .alat-card-photo-placeholder i {
            font-size: 48px;
            color: var(--primary);
            opacity: 0.5;
        }
        .alat-card-stok-badge {
            position: absolute;
            top: 12px;
            right: 12px;
            background: rgba(0,0,0,0.7);
            color: #fff;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            backdrop-filter: blur(4px);
        }
        .alat-card-info {
            padding: 20px;
        }
        .alat-card-name {
            font-size: 15px;
            font-weight: 700;
            color: #1C1C1E;
            margin-bottom: 8px;
            min-height: 40px;
        }
        .alat-card-price {
            font-size: 22px;
            font-weight: 800;
            color: var(--shopee-orange);
            margin-bottom: 16px;
        }
        .alat-card-actions {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        .qty-input {
            width: 60px;
            padding: 10px;
            border: 1.5px solid #E5E5EA;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            text-align: center;
            font-family: inherit;
            outline: none;
        }
        .qty-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px var(--orange-glow);
        }
        .btn-add-cart {
            flex: 1;
            background: var(--primary);
            color: var(--white);
            border: none;
            padding: 10px 14px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        .btn-add-cart:hover { background: var(--primary-hover); }
        .btn-add-cart:disabled {
            background: #C7C7CC;
            cursor: not-allowed;
        }

        /* ---- RIWAYAT PEMBELIAN ---- */
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

        .detail-alat-list {
            margin-top: 8px;
        }
        .detail-alat-item {
            font-size: 12px;
            color: #636366;
            padding: 2px 0;
        }
        .detail-alat-item i {
            color: var(--primary);
            font-size: 10px;
            margin-right: 4px;
        }

        /* ---- MODAL ---- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 2000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            animation: fadeInModal 0.25s ease-out forwards;
        }
        .modal-overlay.active {
            display: flex;
        }
        @keyframes fadeInModal {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .modal-box {
            background: #fff;
            border-radius: 20px;
            width: 100%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            padding: 32px;
            position: relative;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            animation: slideInModal 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes slideInModal {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .modal-close {
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
        .modal-close:hover {
            background: var(--red-lt);
            color: var(--red);
        }
        .modal-header {
            margin-bottom: 24px;
        }
        .modal-header h3 {
            font-size: 20px;
            font-weight: 800;
            color: #1C1C1E;
        }
        .modal-header p {
            font-size: 13px;
            color: #8E8E93;
            margin-top: 4px;
        }
        .summary-card {
            background: #F8F9FA;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            font-size: 14px;
        }
        .summary-row.total {
            border-top: 2px solid #E5E5EA;
            padding-top: 12px;
            margin-top: 8px;
            font-weight: 800;
            font-size: 18px;
            color: var(--shopee-orange);
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
            color: #fff;
            border: none;
            border-radius: 12px;
            padding: 16px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 16px;
            transition: background 0.2s ease;
        }
        .btn-bayar:hover {
            background: var(--primary-hover);
        }
        .btn-batal {
            width: 100%;
            background: transparent;
            color: #8E8E93;
            border: 1.5px solid #E5E5EA;
            border-radius: 12px;
            padding: 14px;
            font-family: inherit;
            font-size: 14px;
            font-weight: 700;
            cursor: pointer;
            margin-top: 10px;
            transition: all 0.2s ease;
        }
        .btn-batal:hover {
            border-color: var(--red);
            color: var(--red);
            background: var(--red-lt);
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

        /* ---- FOOTER ---- */
        footer { 
            background:var(--dark-bg); 
            color:#8E8E93; 
            padding:80px 80px 40px; 
            border-top:1px solid #1C1C1E; 
            position:relative; 
            overflow:hidden; 
        }
        footer::before { 
            content:''; 
            position:absolute; 
            top:0; 
            left:0; 
            right:0; 
            height:1px; 
            background:linear-gradient(90deg,transparent,var(--primary),transparent); 
            animation:shimmer 3s linear infinite; 
            background-size:200% 100%; 
        }
        .footer-grid { 
            display:grid; 
            grid-template-columns:1.5fr 1fr 1fr 1.2fr; 
            gap:40px; 
            margin-bottom:60px; 
        }
        .footer-logo { 
            display:flex; 
            align-items:center; 
            gap:10px; 
            margin-bottom:16px; 
            transition:transform 0.3s ease; 
        }
        .footer-logo:hover { 
            transform:scale(1.05); 
        }
        .footer-logo img { 
            height:70px; 
            transition:transform 0.5s ease; 
        }
        .footer-logo:hover img { 
            transform:rotate(5deg); 
        }
        .footer-logo span { 
            color:var(--white); 
            font-size:20px; 
            font-weight:800; 
        }
        .footer-desc { 
            font-size:13px; 
            line-height:1.6; 
            margin-bottom:24px; 
        }
        .social-links { 
            display:flex; 
            gap:12px; 
        }
        .social-btn { 
            width:36px; 
            height:36px; 
            border-radius:50%; 
            background:#1C1C1E; 
            color:var(--white); 
            display:flex; 
            align-items:center; 
            justify-content:center; 
            text-decoration:none; 
            transition:all 0.3s cubic-bezier(0.34,1.56,0.64,1); 
        }
        .social-btn:hover { 
            background:var(--primary); 
            transform:translateY(-3px) scale(1.1); 
            box-shadow:0 8px 20px rgba(255,82,0,0.3); 
        }
        .social-btn:active { 
            transform:scale(0.95); 
        }
        .footer-col h4 { 
            color:var(--white); 
            font-size:15px; 
            font-weight:700; 
            margin-bottom:20px; 
            position:relative; 
            display:inline-block; 
        }
        .footer-col h4::after { 
            content:''; 
            position:absolute; 
            bottom:-4px; 
            left:0; 
            width:30px; 
            height:2px; 
            background:var(--primary); 
            transition:width 0.3s ease; 
        }
        .footer-col:hover h4::after { 
            width:100%; 
        }
        .footer-col ul { 
            list-style:none; 
        }
        .footer-col ul li { 
            margin-bottom:12px; 
        }
        .footer-col ul li a { 
            color:#8E8E93; 
            text-decoration:none; 
            font-size:13px; 
            transition:all 0.3s ease; 
            display:inline-block; 
            position:relative; 
        }
        .footer-col ul li a::after { 
            content:''; 
            position:absolute; 
            bottom:-2px; 
            left:0; 
            width:0; 
            height:1px; 
            background:var(--primary); 
            transition:width 0.3s ease; 
        }
        .footer-col ul li a:hover { 
            color:var(--white); 
            transform:translateX(5px); 
        }
        .footer-col ul li a:hover::after { 
            width:100%; 
        }
        .contact-item { 
            display:flex; 
            gap:12px; 
            font-size:13px; 
            line-height:1.5; 
            margin-bottom:16px; 
            transition:all 0.3s ease; 
            padding:4px; 
            border-radius:6px; 
        }
        .contact-item:hover { 
            background:rgba(255,82,0,0.05); 
            transform:translateX(5px); 
        }
        .contact-item i { 
            color:var(--primary); 
            font-size:14px; 
            margin-top:3px; 
            transition:transform 0.3s ease; 
        }
        .contact-item:hover i { 
            transform:scale(1.2); 
        }
        .footer-bottom { 
            border-top:1px solid #1C1C1E; 
            padding-top:30px; 
            text-align:center; 
            font-size:13px; 
            position:relative; 
        }

        .swal-toast { 
            border-radius: 12px !important; 
            font-family: 'Plus Jakarta Sans', sans-serif !important; 
        }

        /* ---- RESPONSIVE ---- */
        @media(max-width: 1100px) {
            .hero { flex-direction: column; padding: 40px; }
            .cart-widget { min-width: auto; width: 100%; max-width: none; }
            .main-container { padding: 40px; }
            nav { padding: 0 40px; }
            .alat-grid { grid-template-columns: repeat(2, 1fr); }
        }
        @media(max-width: 768px) {
            .nav-links { display: none; }
            .main-container { padding: 20px; }
            nav { padding: 0 20px; }
            .hero { padding: 30px 20px; }
            .hero-title { font-size: 28px; }
            .alat-grid { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr; }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes shimmer {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
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
        <a href="#">Jadwal</a>
        <a href="langganan_customer.php">Member</a>
        <a href="pembelian_alat.php" class="active">Pembelian</a>
        <a href="#">Tentang</a>
        <a href="#">Kontak</a>
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
            <a href="pembelian_alat.php"><i class="fa-solid fa-cart-shopping"></i> Pembelian Alat</a>
            <div class="dropdown-divider"></div>
            <a href="../login/logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
        </div>
    </div>
</nav>

<!-- HERO SECTION -->
<section class="hero">
    <div class="hero-left">
        <div class="hero-badge">
            <i class="fa-solid fa-cart-shopping"></i> PEMBELIAN ALAT
        </div>
        <h1 class="hero-title">Perlengkapan Basket<br><span>Berkualitas!</span></h1>
        <p class="hero-desc">Beli perlengkapan basket terbaik untuk meningkatkan performa permainan Anda. Dari bola, sepatu, hingga jersey tersedia lengkap.</p>
    </div>

    <!-- KERANJANG WIDGET -->
    <div class="cart-widget">
        <div class="cart-widget-header">
            <div class="cart-widget-icon">
                <i class="fa-solid fa-basket-shopping"></i>
            </div>
            <div>
                <div class="cart-widget-title">Keranjang Belanja</div>
                <div class="cart-widget-subtitle" id="cartCount">0 item</div>
            </div>
        </div>
        <div class="cart-items" id="cartItems">
            <div class="cart-empty">
                <i class="fa-solid fa-cart-plus"></i>
                Keranjang masih kosong
            </div>
        </div>
        <div class="cart-total">
            <span class="cart-total-label">Total</span>
            <span class="cart-total-value" id="cartTotal">Rp 0</span>
        </div>
        <button class="btn-checkout" id="btnCheckout" onclick="openCheckoutModal()" disabled>
            <i class="fa-solid fa-credit-card"></i> Checkout
        </button>
    </div>
</section>

<!-- MAIN CONTENT -->
<main class="main-container">

    <!-- DAFTAR ALAT -->
    <section>
        <div class="section-header">
            <div>
                <h2 class="section-title">Daftar Alat</h2>
                <p class="section-subtitle">Pilih perlengkapan basket yang Anda butuhkan.</p>
            </div>
        </div>

        <div class="alat-grid" id="alatGrid">
            <?php foreach ($alat_list as $alat): 
                $photo_path = $alat['Photo_Alat'] ?? '';
                $photo_url = '';
                if (!empty($photo_path)) {
                    if (strpos($photo_path, '../') === 0) {
                        $photo_url = $photo_path;
                    } elseif (strpos($photo_path, 'asset/') === 0 || strpos($photo_path, 'uploads/') === 0) {
                        $photo_url = '../' . $photo_path;
                    } else {
                        $photo_url = '../asset/image/' . $photo_path;
                    }
                }
            ?>
            <div class="alat-card" data-id="<?php echo $alat['ID_Alat']; ?>">
                <div class="alat-card-photo-wrap">
                    <?php if (!empty($photo_url) && @file_exists(str_replace('../', '', $photo_url))): ?>
                        <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="<?php echo htmlspecialchars($alat['Nama_Alat']); ?>">
                    <?php else: ?>
                        <div class="alat-card-photo-placeholder">
                            <i class="fa-solid fa-toolbox"></i>
                        </div>
                    <?php endif; ?>
                    <span class="alat-card-stok-badge">
                        <i class="fa-solid fa-box"></i> Stok: <?php echo intval($alat['Stok']); ?>
                    </span>
                </div>
                <div class="alat-card-info">
                    <div class="alat-card-name"><?php echo htmlspecialchars($alat['Nama_Alat']); ?></div>
                    <div class="alat-card-price"><?php echo rupiahFormat($alat['Harga_Alat']); ?></div>
                    <div class="alat-card-actions">
                        <input type="number" class="qty-input" id="qty_<?php echo $alat['ID_Alat']; ?>" 
                               value="1" min="1" max="<?php echo intval($alat['Stok']); ?>">
                        <button class="btn-add-cart" onclick="addToCart(<?php echo $alat['ID_Alat']; ?>, '<?php echo htmlspecialchars($alat['Nama_Alat'], ENT_QUOTES); ?>', <?php echo $alat['Harga_Alat']; ?>, <?php echo intval($alat['Stok']); ?>)">
                            <i class="fa-solid fa-plus"></i> Tambah
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($alat_list)): ?>
            <div style="grid-column: 1 / -1; text-align: center; padding: 60px 20px; color: var(--muted);">
                <i class="fa-solid fa-toolbox" style="font-size: 48px; margin-bottom: 16px; opacity: 0.3; display: block;"></i>
                <div style="font-size: 16px; font-weight: 700;">Belum ada alat tersedia</div>
                <p style="font-size: 13px; margin-top: 8px;">Silakan cek kembali nanti</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- RIWAYAT PEMBELIAN -->
    <?php if (!empty($riwayat_pembelian)): ?>
    <section class="riwayat-section">
        <div class="section-header">
            <div>
                <h2 class="section-title">Riwayat Pembelian</h2>
                <p class="section-subtitle">Daftar pembelian alat yang pernah Anda lakukan.</p>
            </div>
        </div>
        <div class="riwayat-card">
            <table class="riwayat-table">
                <thead>
                    <tr>
                        <th>ID Beli</th>
                        <th>Tanggal</th>
                        <th>Detail Alat</th>
                        <th>Total Bayar</th>
                        <th>Metode</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($riwayat_pembelian as $rp): 
                        $status = $status_labels[$rp['Status']] ?? $status_labels[0];
                    ?>
                    <tr>
                        <td><strong>#<?php echo $rp['ID_Beli']; ?></strong></td>
                        <td><?php echo formatTanggal($rp['Tanggal_Beli']); ?></td>
                        <td>
                            <?php foreach ($rp['details'] as $detail): ?>
                            <div class="detail-alat-item">
                                <i class="fa-solid fa-basketball"></i>
                                <?php echo htmlspecialchars($detail['Nama_Alat']); ?> 
                                (<?php echo $detail['Jumlah']; ?>x)
                            </div>
                            <?php endforeach; ?>
                        </td>
                        <td><strong style="color: var(--shopee-orange);"><?php echo rupiahFormat($rp['Total_Bayar']); ?></strong></td>
                        <td><?php echo htmlspecialchars($rp['Metode_Pembayaran']); ?></td>
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
                <li><a href="jadwal_customer.php">Jadwal</a></li>
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

<!-- MODAL CHECKOUT (PILIH METODE PEMBAYARAN) -->
<div class="modal-overlay" id="checkoutModal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeCheckoutModal()" type="button"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header">
            <h3><i class="fa-solid fa-credit-card" style="color: var(--primary); margin-right: 8px;"></i>Checkout</h3>
            <p>Pilih metode pembayaran untuk melanjutkan</p>
        </div>

        <div class="summary-card">
            <div id="checkoutSummary"></div>
            <div class="summary-row total">
                <span>Total Bayar</span>
                <span id="checkoutTotal">Rp 0</span>
            </div>
        </div>

        <div class="metode-list">
            <div class="metode-item" onclick="selectMetode(this, 'Transfer Bank')">
                <i class="fa-solid fa-building-columns"></i>
                <span>Transfer Bank</span>
            </div>
            <div class="metode-item" onclick="selectMetode(this, 'QRIS')">
                <i class="fa-solid fa-qrcode"></i>
                <span>QRIS</span>
            </div>
            <div class="metode-item" onclick="selectMetode(this, 'E-Wallet')">
                <i class="fa-solid fa-wallet"></i>
                <span>E-Wallet (OVO/Dana/GoPay)</span>
            </div>
        </div>

        <form method="POST" id="formCheckout">
            <input type="hidden" name="cart_data" id="cartDataInput">
            <input type="hidden" name="metode_pembayaran" id="metodePembayaranInput">
            <input type="hidden" name="checkout" value="1">
            <button type="submit" class="btn-bayar" id="btnProsesCheckout" disabled>
                <i class="fa-solid fa-lock"></i> Proses Pembayaran
            </button>
        </form>
        <button type="button" class="btn-batal" onclick="closeCheckoutModal()">Batal</button>
    </div>
</div>

<!-- MODAL KONFIRMASI PEMBAYARAN -->
<?php if ($show_payment_modal): ?>
<div class="modal-overlay active" id="paymentModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-circle-check" style="color: var(--green); margin-right: 8px;"></i>Konfirmasi Pembayaran</h3>
            <p>Silakan transfer sesuai nominal di bawah ini</p>
        </div>

        <div class="summary-card">
            <?php foreach ($cart_summary as $item): ?>
            <div class="summary-row">
                <span><?php echo htmlspecialchars($item['nama_alat']); ?> (<?php echo $item['jumlah']; ?>x)</span>
                <span><?php echo rupiahFormat($item['subtotal']); ?></span>
            </div>
            <?php endforeach; ?>
            <div class="summary-row">
                <span>Metode Pembayaran</span>
                <span><?php echo htmlspecialchars($payment_method); ?></span>
            </div>
            <div class="summary-row total">
                <span>Total Bayar</span>
                <span><?php echo rupiahFormat($payment_total); ?></span>
            </div>
        </div>

        <?php if ($payment_method === 'Transfer Bank'): ?>
        <div class="alert-banner">
            <i class="fa-solid fa-building-columns"></i>
            <div class="alert-banner-text">
                <strong>Bank BCA</strong><br>
                No. Rekening: 123-456-7890<br>
                a.n. HoopBall Indonesia<br>
                Transfer sesuai nominal di atas.
            </div>
        </div>
        <?php elseif ($payment_method === 'QRIS'): ?>
        <div class="alert-banner">
            <i class="fa-solid fa-qrcode"></i>
            <div class="alert-banner-text">
                <strong>Scan QRIS</strong><br>
                Silakan scan kode QRIS di lokasi atau hubungi admin untuk kode QRIS.
            </div>
        </div>
        <?php else: ?>
        <div class="alert-banner">
            <i class="fa-solid fa-wallet"></i>
            <div class="alert-banner-text">
                <strong>E-Wallet</strong><br>
                Silakan hubungi admin untuk pembayaran via E-Wallet (OVO/Dana/GoPay).
            </div>
        </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="konfirmasi_pembayaran" value="1">
            <button type="submit" class="btn-bayar">
                <i class="fa-solid fa-check"></i> Saya Sudah Membayar
            </button>
        </form>
        <form method="POST">
            <input type="hidden" name="batal_pembayaran" value="1">
            <button type="submit" class="btn-batal">Batalkan Pembelian</button>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
// ============================================================================
// KERANJANG BELANJA
// ============================================================================
let cart = [];

function addToCart(idAlat, namaAlat, harga, maxStok) {
    const qtyInput = document.getElementById('qty_' + idAlat);
    const qty = parseInt(qtyInput.value) || 1;

    if (qty <= 0 || qty > maxStok) {
        Swal.fire({
            icon: 'warning',
            title: 'Jumlah Tidak Valid',
            text: 'Jumlah harus antara 1 dan ' + maxStok,
            confirmButtonColor: '#FF5200'
        });
        return;
    }

    // Cek apakah item sudah ada di keranjang
    const existingIndex = cart.findIndex(item => item.id_alat === idAlat);

    if (existingIndex >= 0) {
        const newQty = cart[existingIndex].jumlah + qty;
        if (newQty > maxStok) {
            Swal.fire({
                icon: 'warning',
                title: 'Stok Tidak Mencukupi',
                text: 'Total jumlah di keranjang melebihi stok tersedia (' + maxStok + ')',
                confirmButtonColor: '#FF5200'
            });
            return;
        }
        cart[existingIndex].jumlah = newQty;
        cart[existingIndex].subtotal = newQty * harga;
    } else {
        cart.push({
            id_alat: idAlat,
            nama_alat: namaAlat,
            harga: harga,
            jumlah: qty,
            subtotal: qty * harga
        });
    }

    updateCartUI();

    // Animasi feedback
    Swal.fire({
        icon: 'success',
        title: 'Ditambahkan!',
        text: namaAlat + ' (' + qty + 'x) ditambahkan ke keranjang',
        timer: 1500,
        showConfirmButton: false,
        toast: true,
        position: 'top-end',
        background: '#ffffff',
        color: '#1c1c1e',
        iconColor: '#34C759',
        customClass: { popup: 'swal-toast' }
    });
}

function removeFromCart(index) {
    cart.splice(index, 1);
    updateCartUI();
}

function updateCartUI() {
    const cartItems = document.getElementById('cartItems');
    const cartCount = document.getElementById('cartCount');
    const cartTotal = document.getElementById('cartTotal');
    const btnCheckout = document.getElementById('btnCheckout');

    if (cart.length === 0) {
        cartItems.innerHTML = `
            <div class="cart-empty">
                <i class="fa-solid fa-cart-plus"></i>
                Keranjang masih kosong
            </div>
        `;
        cartCount.textContent = '0 item';
        cartTotal.textContent = 'Rp 0';
        btnCheckout.disabled = true;
        return;
    }

    let total = 0;
    let html = '';

    cart.forEach((item, index) => {
        total += item.subtotal;
        html += `
            <div class="cart-item">
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.nama_alat}</div>
                    <div class="cart-item-qty">${item.jumlah}x @ Rp ${item.harga.toLocaleString('id-ID')}</div>
                </div>
                <div class="cart-item-price">Rp ${item.subtotal.toLocaleString('id-ID')}</div>
                <button class="cart-item-remove" onclick="removeFromCart(${index})" title="Hapus">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>
        `;
    });

    cartItems.innerHTML = html;

    const totalItems = cart.reduce((sum, item) => sum + item.jumlah, 0);
    cartCount.textContent = totalItems + ' item' + (totalItems > 1 ? 's' : '');
    cartTotal.textContent = 'Rp ' + total.toLocaleString('id-ID');
    btnCheckout.disabled = false;
}

function formatRupiah(angka) {
    return 'Rp ' + angka.toLocaleString('id-ID');
}

// ============================================================================
// MODAL CHECKOUT
// ============================================================================
function openCheckoutModal() {
    if (cart.length === 0) {
        Swal.fire({
            icon: 'warning',
            title: 'Keranjang Kosong',
            text: 'Silakan tambahkan alat ke keranjang terlebih dahulu',
            confirmButtonColor: '#FF5200'
        });
        return;
    }

    // Generate summary HTML
    let summaryHtml = '';
    let total = 0;

    cart.forEach(item => {
        total += item.subtotal;
        summaryHtml += `
            <div class="summary-row">
                <span>${item.nama_alat} (${item.jumlah}x)</span>
                <span>${formatRupiah(item.subtotal)}</span>
            </div>
        `;
    });

    document.getElementById('checkoutSummary').innerHTML = summaryHtml;
    document.getElementById('checkoutTotal').textContent = formatRupiah(total);

    // Reset metode selection
    document.querySelectorAll('.metode-item').forEach(el => el.classList.remove('selected'));
    document.getElementById('metodePembayaranInput').value = '';
    document.getElementById('btnProsesCheckout').disabled = true;

    document.getElementById('checkoutModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeCheckoutModal() {
    document.getElementById('checkoutModal').classList.remove('active');
    document.body.style.overflow = '';
}

function selectMetode(element, metode) {
    document.querySelectorAll('.metode-item').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    document.getElementById('metodePembayaranInput').value = metode;
    document.getElementById('btnProsesCheckout').disabled = false;
}

// Close modal on overlay click
document.getElementById('checkoutModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeCheckoutModal();
    }
});

// ============================================================================
// FORM SUBMIT
// ============================================================================
document.getElementById('formCheckout').addEventListener('submit', function(e) {
    if (cart.length === 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Keranjang Kosong',
            text: 'Silakan tambahkan alat ke keranjang terlebih dahulu',
            confirmButtonColor: '#FF5200'
        });
        return;
    }

    document.getElementById('cartDataInput').value = JSON.stringify(cart);
});

// ============================================================================
// NOTIFIKASI URL PARAMETER
// ============================================================================
const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
const msg = urlParams.get('msg');

if (status && msg) {
    const isSuccess = status === 'success';
    Swal.fire({
        icon: isSuccess ? 'success' : (status === 'info' ? 'info' : 'error'),
        title: isSuccess ? 'Berhasil!' : (status === 'info' ? 'Info' : 'Gagal!'),
        text: msg,
        timer: 5000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end',
        timerProgressBar: true,
        showCloseButton: true,
        background: '#ffffff',
        color: '#1c1c1e',
        iconColor: isSuccess ? '#34C759' : (status === 'info' ? '#007AFF' : '#FF3B30'),
        customClass: { popup: 'swal-toast' }
    });
    window.history.replaceState({}, document.title, window.location.pathname);
}

// ============================================================================
// ERROR HANDLING
// ============================================================================
<?php if (!empty($pembelian_error)): ?>
Swal.fire({
    icon: 'error',
    title: 'Gagal!',
    text: '<?php echo htmlspecialchars($pembelian_error); ?>',
    confirmButtonColor: '#FF5200'
});
<?php endif; ?>
</script>

</body>
</html>