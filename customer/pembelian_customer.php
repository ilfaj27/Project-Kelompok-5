<?php
ob_start();
session_start();
date_default_timezone_set("Asia/Jakarta");
include '../includes/config.php';

// Check if customer is logged in
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    echo "<script>alert('Akses Ditolak! Silakan login terlebih dahulu.'); window.location='../login/login.php';</script>";
    exit();
}

$id_customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';
$nama_customer = 'Pelanggan';
$photo_profile = '';
$has_member = false;
$member_tipe = '';

if (!empty($id_customer)) {
    $cek_customer = sqlsrv_query($conn, "SELECT Nama_Customer, Photo_Profile, Is_Deleted, Status FROM Customer WHERE ID_Customer = ?", array($id_customer));
    if ($cek_customer) {
        $row_cust = sqlsrv_fetch_array($cek_customer, SQLSRV_FETCH_ASSOC);
        if ($row_cust) {
            if ($row_cust['Is_Deleted'] == 1 || $row_cust['Status'] == 0) {
                $_SESSION = array(); session_destroy();
                header("Location: ../login/login.php?status=error&msg=Akun Anda telah dihapus atau dinonaktifkan.");
                exit();
            }
            $nama_customer = $row_cust['Nama_Customer'];
            $photo_profile = $row_cust['Photo_Profile'];
        }
    }

    // Check member status
    $member_check = sqlsrv_query($conn, "SELECT TOP 1 T.Nama_Tipe FROM Langganan L INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe WHERE L.ID_Customer = ? AND L.Status = 1 AND GETDATE() BETWEEN L.Tanggal_Mulai AND L.Tanggal_Selesai", array($id_customer));
    if ($member_check) {
        $member_row = sqlsrv_fetch_array($member_check, SQLSRV_FETCH_ASSOC);
        if ($member_row) {
            $has_member = true;
            $member_tipe = $member_row['Nama_Tipe'];
        }
    }
}

function safeQuery($conn, $sql, $params = []) {
    $stmt = empty($params) ? sqlsrv_query($conn, $sql) : sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("[BELI_ALAT-ERROR] SQL Error: " . print_r(sqlsrv_errors(SQLSRV_ERR_ALL), true));
        return false;
    }
    return $stmt;
}

function safeFetch($stmt) {
    if ($stmt === false || $stmt === null) return false;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function getPhotoUrl($photo_path) {
    if (empty($photo_path)) return '';
    $path = str_replace('../', '', $photo_path);
    $path = ltrim($path, '/');
    return '../' . $path;
}

function rupiahFormat($n) {
    return 'Rp ' . number_format($n, 0, ',', '.');
}

// ==== PROCESS ADD TO CART ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $id_alat = intval($_POST['id_alat'] ?? 0);
    $jumlah = intval($_POST['jumlah'] ?? 1);

    if ($id_alat <= 0 || $jumlah <= 0) {
        header("Location: pembelian_customer.php?status=error&msg=" . urlencode("Data tidak valid."));
        exit();
    }

    // Check stock availability
    $check_stock = safeQuery($conn, "SELECT Nama_Alat, Stok, Harga_Alat, Photo_Alat FROM Alat WHERE ID_Alat = ? AND Status = 1 AND Is_Deleted = 0", [$id_alat]);
    $alat_data = safeFetch($check_stock);

    if (!$alat_data) {
        header("Location: pembelian_customer.php?status=error&msg=" . urlencode("Alat tidak ditemukan."));
        exit();
    }

    if ($alat_data['Stok'] < $jumlah) {
        header("Location: pembelian_customer.php?status=error&msg=" . urlencode("Stok tidak mencukupi. Tersedia: " . $alat_data['Stok'] . " pcs."));
        exit();
    }

    // Initialize cart if not exists
    if (!isset($_SESSION['cart_alat'])) {
        $_SESSION['cart_alat'] = [];
    }

    // Add or update cart item
    $found = false;
    foreach ($_SESSION['cart_alat'] as &$item) {
        if ($item['id_alat'] == $id_alat) {
            $new_jumlah = $item['jumlah'] + $jumlah;
            if ($new_jumlah > $alat_data['Stok']) {
                header("Location: pembelian_customer.php?status=error&msg=" . urlencode("Total jumlah melebihi stok tersedia."));
                exit();
            }
            $item['jumlah'] = $new_jumlah;
            $item['subtotal'] = $new_jumlah * $alat_data['Harga_Alat'];
            $found = true;
            break;
        }
    }
    unset($item);

    if (!$found) {
        $_SESSION['cart_alat'][] = [
            'id_alat' => $id_alat,
            'nama_alat' => $alat_data['Nama_Alat'],
            'harga' => $alat_data['Harga_Alat'],
            'jumlah' => $jumlah,
            'subtotal' => $jumlah * $alat_data['Harga_Alat'],
            'photo' => $alat_data['Photo_Alat']
        ];
    }

    header("Location: pembelian_customer.php?status=success&msg=" . urlencode($alat_data['Nama_Alat'] . " ditambahkan ke keranjang."));
    exit();
}

// ==== PROCESS UPDATE CART ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_cart'])) {
    $cart_index = intval($_POST['cart_index'] ?? -1);
    $new_jumlah = intval($_POST['new_jumlah'] ?? 1);

    if ($cart_index >= 0 && isset($_SESSION['cart_alat'][$cart_index])) {
        $id_alat = $_SESSION['cart_alat'][$cart_index]['id_alat'];
        $check_stock = safeQuery($conn, "SELECT Stok, Harga_Alat FROM Alat WHERE ID_Alat = ? AND Status = 1 AND Is_Deleted = 0", [$id_alat]);
        $alat_data = safeFetch($check_stock);

        if ($alat_data && $new_jumlah > 0 && $new_jumlah <= $alat_data['Stok']) {
            $_SESSION['cart_alat'][$cart_index]['jumlah'] = $new_jumlah;
            $_SESSION['cart_alat'][$cart_index]['subtotal'] = $new_jumlah * $alat_data['Harga_Alat'];
        } else if ($new_jumlah <= 0) {
            array_splice($_SESSION['cart_alat'], $cart_index, 1);
        } else {
            header("Location: pembelian_customer.php?status=error&msg=" . urlencode("Stok tidak mencukupi."));
            exit();
        }
    }

    header("Location: pembelian_customer.php?tab=cart");
    exit();
}

// ==== PROCESS REMOVE FROM CART ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_cart'])) {
    $cart_index = intval($_POST['cart_index'] ?? -1);
    if ($cart_index >= 0 && isset($_SESSION['cart_alat'][$cart_index])) {
        $nama = $_SESSION['cart_alat'][$cart_index]['nama_alat'];
        array_splice($_SESSION['cart_alat'], $cart_index, 1);
        header("Location: pembelian_customer.php?tab=cart&status=success&msg=" . urlencode($nama . " dihapus dari keranjang."));
        exit();
    }
}

// ==== PROCESS CHECKOUT ====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout'])) {
    if (empty($_SESSION['cart_alat'])) {
        header("Location: pembelian_customer.php?status=error&msg=" . urlencode("Keranjang belanja kosong."));
        exit();
    }

    $metode_pembayaran = trim($_POST['metode_pembayaran'] ?? '');
    if (empty($metode_pembayaran)) {
        header("Location: pembelian_customer.php?tab=cart&status=error&msg=" . urlencode("Pilih metode pembayaran."));
        exit();
    }

    // Validate stock again before checkout
    $total_bayar = 0;
    foreach ($_SESSION['cart_alat'] as $item) {
        $check_stock = safeQuery($conn, "SELECT Stok FROM Alat WHERE ID_Alat = ? AND Status = 1 AND Is_Deleted = 0", [$item['id_alat']]);
        $stock_data = safeFetch($check_stock);
        if (!$stock_data || $stock_data['Stok'] < $item['jumlah']) {
            header("Location: pembelian_customer.php?tab=cart&status=error&msg=" . urlencode("Stok " . $item['nama_alat'] . " tidak mencukupi. Silakan perbarui keranjang."));
            exit();
        }
        $total_bayar += $item['subtotal'];
    }

    // Get karyawan ID (default to first active karyawan)
    $karyawan_query = safeQuery($conn, "SELECT TOP 1 ID_Karyawan FROM Karyawan WHERE Status = 1 AND Is_Deleted = 0 ORDER BY ID_Karyawan ASC");
    $karyawan_data = safeFetch($karyawan_query);
    $id_karyawan = $karyawan_data ? $karyawan_data['ID_Karyawan'] : 2;

    // Insert Beli_Alat (Status: 0 = Menunggu Konfirmasi, 1 = Berhasil)
    $sql_insert = "INSERT INTO Beli_Alat (ID_Karyawan, ID_Customer, Tanggal_Beli, Metode_Pembayaran, Total_Bayar, Status, Created_By, Created_Date) OUTPUT INSERTED.ID_Beli VALUES (?, ?, GETDATE(), ?, ?, 0, ?, GETDATE())";
    $stmt_insert = safeQuery($conn, $sql_insert, [$id_karyawan, $id_customer, $metode_pembayaran, $total_bayar, $id_customer]);

    if ($stmt_insert) {
        $row = safeFetch($stmt_insert);
        $id_beli = $row['ID_Beli'];

        // Insert Detail_Beli_Alat
        foreach ($_SESSION['cart_alat'] as $item) {
            safeQuery($conn, "INSERT INTO Detail_Beli_Alat (ID_Alat, ID_Beli, Jumlah, SubTotal) VALUES (?, ?, ?, ?)", 
                [$item['id_alat'], $id_beli, $item['jumlah'], $item['subtotal']]);
        }

        // Clear cart
        $_SESSION['cart_alat'] = [];

        header("Location: pembelian_customer.php?status=success&msg=" . urlencode("Pembelian berhasil! Status: Menunggu Konfirmasi. ID Transaksi: BELI-" . $id_beli));
        exit();
    } else {
        header("Location: pembelian_customer.php?tab=cart&status=error&msg=" . urlencode("Gagal memproses pembelian."));
        exit();
    }
}

// ==== CLEAR CART ====
if (isset($_GET['clear_cart']) && $_GET['clear_cart'] == '1') {
    $_SESSION['cart_alat'] = [];
    header("Location: pembelian_customer.php?status=success&msg=" . urlencode("Keranjang dikosongkan."));
    exit();
}

// ==== FETCH DATA ====
$alat_list = [];
$query_alat = safeQuery($conn, "SELECT ID_Alat, Nama_Alat, Stok, Harga_Alat, Photo_Alat, Status FROM Alat WHERE Is_Deleted = 0 AND Status = 1 ORDER BY Nama_Alat ASC");
if ($query_alat) {
    while ($row = sqlsrv_fetch_array($query_alat, SQLSRV_FETCH_ASSOC)) {
        $alat_list[] = $row;
    }
}

// Fetch purchase history
$riwayat_list = [];
$query_riwayat = safeQuery($conn, "SELECT B.ID_Beli, B.Tanggal_Beli, B.Metode_Pembayaran, B.Total_Bayar, B.Status, K.Nama_Karyawan FROM Beli_Alat B INNER JOIN Karyawan K ON B.ID_Karyawan = K.ID_Karyawan WHERE B.ID_Customer = ? ORDER BY B.Created_Date DESC", [$id_customer]);
if ($query_riwayat) {
    while ($row = sqlsrv_fetch_array($query_riwayat, SQLSRV_FETCH_ASSOC)) {
        $riwayat_list[] = $row;
    }
}

$status_beli = [0 => ['label'=>'Menunggu Konfirmasi','class'=>'sp-pending','icon'=>'fa-clock'], 1 => ['label'=>'Berhasil','class'=>'sp-active','icon'=>'fa-check-circle']];

$cart_count = isset($_SESSION['cart_alat']) ? count($_SESSION['cart_alat']) : 0;
$cart_total = 0;
if (isset($_SESSION['cart_alat'])) {
    foreach ($_SESSION['cart_alat'] as $item) {
        $cart_total += $item['subtotal'];
    }
}

$active_tab = $_GET['tab'] ?? 'alat';
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
:root { --primary:#FF5200; --primary-hover:#E04800; --dark-bg:#0B0B0C; --card-dark:#121214; --text-gray:#8E8E93; --border-color:#222225; --white:#FFFFFF; --light-bg:#F8F9FA; --green:#34C759; --green-lt:rgba(52,199,89,.10); --yellow:#FFCC00; --yellow-lt:rgba(255,204,0,.10); --red:#FF3B30; --red-lt:rgba(255,59,48,.10); --blue:#007AFF; --blue-lt:rgba(0,122,255,.10); --orange:#FF4500; --orange-lt:rgba(255,69,0,.10); --shopee-orange:#EE4D2D; }
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--white); color:#111; overflow-x:hidden; }

/* Animations */
@keyframes fadeInUp { from{opacity:0;transform:translateY(40px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeInDown { from{opacity:0;transform:translateY(-30px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }
@keyframes scaleIn { from{opacity:0;transform:scale(0.8)} to{opacity:1;transform:scale(1)} }
@keyframes slideInUp { from{opacity:0;transform:translateY(60px) scale(0.95)} to{opacity:1;transform:translateY(0) scale(1)} }
@keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
@keyframes pulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.05)} }
@keyframes shimmer { 0%{background-position:-200% 0} 100%{background-position:200% 0} }
@keyframes bounceIn { 0%{opacity:0;transform:scale(0.3)} 50%{opacity:1;transform:scale(1.05)} 70%{transform:scale(0.9)} 100%{transform:scale(1)} }

/* Navbar */
nav { background:var(--white); padding:0 80px; display:flex; justify-content:space-between; align-items:center; height:76px; position:sticky; top:0; z-index:1000; border-bottom:1px solid #E5E5EA; animation:fadeInDown 0.6s ease-out forwards; }
.nav-logo { display:flex; align-items:center; text-decoration:none; gap:10px; transition:transform 0.3s ease; }
.nav-logo:hover { transform:scale(1.05); }
.nav-logo img { height:70px; width:auto; transition:transform 0.5s cubic-bezier(0.34,1.56,0.64,1); }
.nav-logo:hover img { transform:rotate(5deg) scale(1.1); }
.nav-logo span { color:#1C1C1E; font-size:20px; font-weight:800; letter-spacing:-0.5px; }
.nav-links { display:flex; align-items:center; gap:8px; }
.nav-links a { color:#636366; text-decoration:none; font-size:14px; font-weight:500; padding:8px 16px; border-radius:20px; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); position:relative; overflow:hidden; }
.nav-links a::before { content:''; position:absolute; bottom:0; left:50%; width:0; height:2px; background:var(--primary); transition:all 0.3s cubic-bezier(0.16,1,0.3,1); transform:translateX(-50%); }
.nav-links a:hover { color:#1C1C1E; transform:translateY(-2px); }
.nav-links a:hover::before { width:60%; }
.nav-links a.active { color:var(--primary); font-weight:600; }
.nav-links a.active::before { width:60%; }

.nav-user-container { position:relative; height:76px; display:flex; align-items:center; }
.nav-user { background:#F2F2F7; border:1px solid #E5E5EA; padding:8px 16px; border-radius:50px; color:#1C1C1E; font-size:14px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:10px; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); }
.nav-user:hover { background:#E5E5EA; border-color:var(--primary); transform:scale(1.02); box-shadow:0 4px 12px rgba(255,82,0,0.15); }
.nav-user img.user-avatar { width:24px; height:24px; border-radius:50%; object-fit:cover; transition:transform 0.3s ease; }
.nav-user:hover img.user-avatar { transform:scale(1.15); }
.nav-user i.user-icon { font-size:16px; color:var(--primary); transition:transform 0.3s ease; }
.nav-user:hover i.user-icon { transform:scale(1.2); }
.nav-user i.arrow { font-size:11px; color:#8E8E93; transition:0.3s cubic-bezier(0.34,1.56,0.64,1); }
.nav-user-container:hover i.arrow { transform:rotate(180deg); color:var(--primary); }
.dropdown-menu { position:absolute; top:85%; right:0; background:#16161a; min-width:220px; border-radius:12px; border:1px solid #2d2d33; box-shadow:0 10px 30px rgba(0,0,0,0.5); padding:8px 0; display:none; z-index:1001; transform-origin:top right; }
.nav-user-container:hover .dropdown-menu { display:block; animation:fadeInUp 0.3s cubic-bezier(0.16,1,0.3,1) forwards; }
.dropdown-menu .user-info-header { padding:12px 20px; border-bottom:1px solid #2d2d33; margin-bottom:6px; }
.dropdown-menu .user-info-header .u-name { color:var(--white); font-size:14px; font-weight:700; }
.dropdown-menu .user-info-header .u-role { color:var(--text-gray); font-size:11px; text-transform:uppercase; letter-spacing:0.5px; margin-top:2px; }
.dropdown-menu a { display:flex; align-items:center; gap:12px; padding:10px 20px; color:#c5c5ca; text-decoration:none; font-size:13px; font-weight:500; transition:all 0.25s cubic-bezier(0.16,1,0.3,1); position:relative; overflow:hidden; }
.dropdown-menu a::after { content:''; position:absolute; left:0; top:0; width:3px; height:100%; background:var(--primary); transform:scaleY(0); transition:transform 0.25s cubic-bezier(0.16,1,0.3,1); }
.dropdown-menu a i { font-size:14px; width:16px; text-align:center; transition:transform 0.3s ease; }
.dropdown-menu a:hover { background:#222227; color:var(--primary); padding-left:28px; }
.dropdown-menu a:hover::after { transform:scaleY(1); }
.dropdown-menu a:hover i { transform:scale(1.2); }
.dropdown-divider { height:1px; background:#2d2d33; margin:6px 0; }
.dropdown-menu a.logout:hover { color:#ff3b30; }
.dropdown-menu a.logout:hover::after { background:#ff3b30; }

.member-badge-nav { background:var(--green-lt); color:var(--green); padding:3px 10px; border-radius:20px; font-size:10px; font-weight:700; display:inline-flex; align-items:center; gap:4px; }

/* Hero */
.hero { background-color:var(--dark-bg); background-image:linear-gradient(180deg,rgba(11,11,12,0.6) 0%,rgba(11,11,12,0.9) 100%),url('https://images.unsplash.com/photo-1546519638-68e109498ffc?q=80&w=2000'); background-size:cover; background-position:center; min-height:300px; padding:60px 80px; display:flex; align-items:center; justify-content:space-between; gap:40px; position:relative; overflow:hidden; }
.hero::before { content:''; position:absolute; top:0; left:0; right:0; bottom:0; background:radial-gradient(circle at 20% 50%,rgba(255,82,0,0.08) 0%,transparent 50%),radial-gradient(circle at 80% 20%,rgba(255,82,0,0.05) 0%,transparent 40%); pointer-events:none; }
.hero-left { max-width:620px; position:relative; z-index:2; }
.hero-title { font-size:42px; font-weight:800; color:var(--white); line-height:1.15; margin-bottom:16px; animation:fadeInUp 1s cubic-bezier(0.16,1,0.3,1) 0.3s forwards; opacity:0; }
.hero-title span { color:var(--primary); }
.hero-desc { color:#A0A0A5; font-size:16px; line-height:1.6; margin-bottom:24px; animation:fadeInUp 1s cubic-bezier(0.16,1,0.3,1) 0.5s forwards; opacity:0; }

/* Tab Navigation */
.tab-nav { display:flex; gap:0; border-bottom:1px solid #E5E5EA; background:var(--white); padding:0 80px; }
.tab-btn { padding:16px 28px; background:none; border:none; border-bottom:3px solid transparent; font-family:'Plus Jakarta Sans',sans-serif; font-size:14px; font-weight:700; color:#636366; cursor:pointer; transition:all 0.3s ease; position:relative; }
.tab-btn:hover { color:var(--primary); }
.tab-btn.active { color:var(--primary); border-bottom-color:var(--primary); }
.tab-btn i { margin-right:8px; }
.tab-badge { background:var(--red); color:var(--white); font-size:10px; font-weight:800; padding:2px 8px; border-radius:10px; margin-left:6px; }

/* Main Container */
.main-container { padding:40px 80px; max-width:1440px; margin:0 auto; min-height:500px; }

/* Section Header */
.section-header { display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:24px; }
.section-title { font-size:24px; font-weight:800; color:#111; position:relative; display:inline-block; }
.section-title::after { content:''; position:absolute; bottom:-4px; left:0; width:40px; height:3px; background:var(--primary); border-radius:2px; transition:width 0.4s cubic-bezier(0.16,1,0.3,1); }
.section-header:hover .section-title::after { width:100%; }
.section-subtitle { font-size:14px; color:#636366; margin-top:4px; }

/* Alat Grid */
.alat-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px, 1fr)); gap:24px; }
.alat-card { background:var(--white); border:1px solid #E5E5EA; border-radius:16px; overflow:hidden; transition:all 0.4s cubic-bezier(0.16,1,0.3,1); position:relative; }
.alat-card:hover { transform:translateY(-8px); box-shadow:0 20px 40px rgba(0,0,0,0.12); border-color:rgba(255,82,0,0.2); }
.alat-card:hover .alat-img { transform:scale(1.08); }
.alat-img-wrap { height:200px; position:relative; background:#f0f0f0; overflow:hidden; }
.alat-img { width:100%; height:100%; object-fit:cover; transition:transform 0.6s cubic-bezier(0.16,1,0.3,1); }
.alat-img-placeholder { width:100%; height:100%; display:flex; align-items:center; justify-content:center; background:linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); }
.alat-img-placeholder i { font-size:48px; color:var(--primary); opacity:0.5; }
.alat-stok-badge { position:absolute; top:12px; right:12px; background:rgba(0,0,0,0.7); color:var(--white); padding:6px 12px; border-radius:20px; font-size:11px; font-weight:700; backdrop-filter:blur(4px); }
.alat-body { padding:20px; }
.alat-name { font-size:16px; font-weight:700; color:#1C1C1E; margin-bottom:8px; }
.alat-price { font-size:22px; font-weight:800; color:var(--shopee-orange); margin-bottom:12px; }
.alat-stok-info { font-size:12px; color:#636366; margin-bottom:16px; display:flex; align-items:center; gap:6px; }
.alat-stok-info i { color:var(--primary); }
.alat-actions { display:flex; gap:8px; }
.btn-add-cart { flex:1; background:var(--primary); color:var(--white); border:none; padding:12px; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:6px; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); }
.btn-add-cart:hover { background:var(--primary-hover); transform:translateY(-2px); box-shadow:0 8px 20px rgba(255,82,0,0.3); }
.btn-add-cart:active { transform:translateY(0) scale(0.98); }
.jumlah-input { width:60px; padding:10px; border:1.5px solid #E5E5EA; border-radius:8px; font-size:14px; font-weight:700; text-align:center; font-family:inherit; outline:none; transition:all 0.2s; }
.jumlah-input:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(255,82,0,0.15); }

/* Cart Section */
.cart-container { max-width:900px; margin:0 auto; }
.cart-item { display:flex; align-items:center; gap:16px; padding:20px; border:1px solid #E5E5EA; border-radius:12px; margin-bottom:12px; background:var(--white); transition:all 0.3s ease; }
.cart-item:hover { box-shadow:0 8px 20px rgba(0,0,0,0.06); border-color:rgba(255,82,0,0.15); }
.cart-img { width:80px; height:80px; border-radius:10px; object-fit:cover; background:#f0f0f0; flex-shrink:0; }
.cart-img-placeholder { width:80px; height:80px; border-radius:10px; background:linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); display:flex; align-items:center; justify-content:center; flex-shrink:0; }
.cart-img-placeholder i { font-size:28px; color:var(--primary); opacity:0.5; }
.cart-info { flex:1; }
.cart-name { font-size:15px; font-weight:700; color:#1C1C1E; margin-bottom:4px; }
.cart-price-unit { font-size:13px; color:#636366; margin-bottom:8px; }
.cart-qty-control { display:flex; align-items:center; gap:8px; }
.btn-qty { width:32px; height:32px; border:1.5px solid #E5E5EA; background:var(--white); border-radius:8px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:14px; font-weight:700; color:#1C1C1E; transition:all 0.2s; }
.btn-qty:hover { border-color:var(--primary); color:var(--primary); }
.qty-display { font-size:14px; font-weight:700; color:#1C1C1E; min-width:30px; text-align:center; }
.cart-subtotal { font-size:16px; font-weight:800; color:var(--shopee-orange); min-width:120px; text-align:right; }
.btn-remove { width:36px; height:36px; border:none; background:var(--red-lt); color:var(--red); border-radius:8px; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:14px; transition:all 0.2s; }
.btn-remove:hover { background:var(--red); color:var(--white); transform:scale(1.1); }

.cart-summary { background:var(--light-bg); border-radius:16px; padding:28px; margin-top:24px; }
.summary-row { display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid #E5E5EA; }
.summary-row:last-child { border-bottom:none; }
.summary-label { font-size:14px; color:#636366; font-weight:600; }
.summary-value { font-size:16px; font-weight:700; color:#1C1C1E; }
.summary-total { font-size:24px; font-weight:800; color:var(--shopee-orange); }

.metode-pembayaran { margin-top:20px; }
.metode-label { font-size:14px; font-weight:700; color:#1C1C1E; margin-bottom:12px; display:block; }
.metode-options { display:flex; gap:12px; flex-wrap:wrap; }
.metode-option { flex:1; min-width:140px; }
.metode-option input { display:none; }
.metode-option label { display:flex; flex-direction:column; align-items:center; gap:8px; padding:16px; border:2px solid #E5E5EA; border-radius:12px; cursor:pointer; transition:all 0.3s ease; background:var(--white); }
.metode-option label i { font-size:24px; color:#636366; transition:all 0.3s ease; }
.metode-option label span { font-size:13px; font-weight:700; color:#636366; }
.metode-option input:checked + label { border-color:var(--primary); background:var(--orange-lt); }
.metode-option input:checked + label i { color:var(--primary); }
.metode-option input:checked + label span { color:var(--primary); }
.metode-option label:hover { border-color:var(--primary); }

.btn-checkout { width:100%; background:var(--primary); color:var(--white); border:none; padding:16px; border-radius:12px; font-weight:800; font-size:15px; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; margin-top:20px; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); }
.btn-checkout:hover { background:var(--primary-hover); transform:translateY(-2px); box-shadow:0 10px 30px rgba(255,82,0,0.3); }
.btn-checkout:disabled { background:#ccc; cursor:not-allowed; transform:none; box-shadow:none; }
.btn-clear-cart { display:inline-flex; align-items:center; gap:6px; color:var(--red); font-size:13px; font-weight:700; text-decoration:none; padding:8px 16px; border-radius:8px; transition:all 0.2s; margin-bottom:16px; }
.btn-clear-cart:hover { background:var(--red-lt); }

.empty-cart { text-align:center; padding:80px 20px; color:#636366; }
.empty-cart i { font-size:64px; margin-bottom:20px; opacity:0.3; display:block; color:var(--primary); }
.empty-cart div { font-size:18px; font-weight:700; color:#1C1C1E; margin-bottom:8px; }
.empty-cart p { font-size:14px; }

/* Riwayat */
.riwayat-card { border:1px solid #E5E5EA; border-radius:16px; padding:24px; background:var(--white); }
.riwayat-item { display:flex; align-items:center; gap:16px; padding:16px 0; border-bottom:1px solid #F2F2F7; transition:all 0.3s ease; }
.riwayat-item:hover { background:#FFF8F5; transform:translateX(8px); border-bottom-color:transparent; border-radius:8px; padding-left:12px; padding-right:12px; }
.riwayat-item:last-child { border-bottom:none; }
.riwayat-icon { width:48px; height:48px; border-radius:12px; background:#FFF0E6; display:flex; align-items:center; justify-content:center; color:var(--primary); font-size:20px; flex-shrink:0; }
.riwayat-info { flex:1; }
.riwayat-info h4 { font-size:15px; font-weight:700; color:#1C1C1E; margin-bottom:4px; }
.riwayat-info p { font-size:13px; color:#636366; }
.riwayat-price { font-size:16px; font-weight:800; color:var(--shopee-orange); }
.status-pill { padding:5px 12px; border-radius:20px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:0.3px; display:inline-flex; align-items:center; gap:5px; }
.sp-active { background:var(--green-lt); color:var(--green); }
.sp-pending { background:var(--yellow-lt); color:#D97706; }

/* Footer */
footer { background:var(--dark-bg); color:#8E8E93; padding:60px 80px 30px; border-top:1px solid #1C1C1E; margin-top:60px; }
.footer-grid { display:grid; grid-template-columns:1.5fr 1fr 1fr 1.2fr; gap:40px; margin-bottom:40px; }
.footer-logo { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.footer-logo img { height:50px; }
.footer-logo span { color:var(--white); font-size:18px; font-weight:800; }
.footer-desc { font-size:13px; line-height:1.6; margin-bottom:20px; }
.social-links { display:flex; gap:10px; }
.social-btn { width:36px; height:36px; border-radius:50%; background:#1C1C1E; color:var(--white); display:flex; align-items:center; justify-content:center; text-decoration:none; transition:all 0.3s ease; }
.social-btn:hover { background:var(--primary); transform:translateY(-3px); }
.footer-col h4 { color:var(--white); font-size:15px; font-weight:700; margin-bottom:16px; }
.footer-col ul { list-style:none; }
.footer-col ul li { margin-bottom:10px; }
.footer-col ul li a { color:#8E8E93; text-decoration:none; font-size:13px; transition:all 0.3s ease; }
.footer-col ul li a:hover { color:var(--primary); }
.contact-item { display:flex; gap:10px; font-size:13px; line-height:1.5; margin-bottom:12px; }
.contact-item i { color:var(--primary); font-size:14px; margin-top:2px; }
.footer-bottom { border-top:1px solid #1C1C1E; padding-top:20px; text-align:center; font-size:13px; }

.swal-toast { border-radius:12px !important; font-family:'Plus Jakarta Sans',sans-serif !important; }

@media (max-width:1200px) {
  .hero { padding:40px 40px; }
  .tab-nav { padding:0 40px; }
  .main-container { padding:30px 40px; }
  .footer-grid { grid-template-columns:repeat(2,1fr); }
  nav { padding:0 40px; }
}
@media (max-width:768px) {
  .hero-title { font-size:28px; }
  .alat-grid { grid-template-columns:1fr; }
  .tab-nav { padding:0 20px; overflow-x:auto; }
  .tab-btn { padding:12px 16px; white-space:nowrap; }
  .main-container { padding:20px 20px; }
  .cart-item { flex-wrap:wrap; }
  .footer-grid { grid-template-columns:1fr; }
  nav { padding:0 20px; }
  .nav-links { display:none; }
  .metode-options { flex-direction:column; }
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
        <a href="pembelian_customer.php" class="active">Pembelian</a>
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
            <div class="dropdown-divider"></div>
            <a href="../login/logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
        </div>
    </div>
</nav>

<!-- HERO -->
<header class="hero">
    <div class="hero-left">
        <h1 class="hero-title">Beli Alat Basket<br><span>Langsung Online</span></h1>
        <p class="hero-desc">Pilih alat basket berkualitas, tambahkan ke keranjang, dan bayar dengan mudah. Stok terjamin!</p>
    </div>
</header>

<!-- TAB NAVIGATION -->
<div class="tab-nav">
    <button class="tab-btn <?php echo $active_tab == 'alat' ? 'active' : ''; ?>" onclick="switchTab('alat')">
        <i class="fa-solid fa-toolbox"></i> Daftar Alat
    </button>
    <button class="tab-btn <?php echo $active_tab == 'cart' ? 'active' : ''; ?>" onclick="switchTab('cart')">
        <i class="fa-solid fa-cart-shopping"></i> Keranjang
        <?php if ($cart_count > 0): ?><span class="tab-badge"><?php echo $cart_count; ?></span><?php endif; ?>
    </button>
    <button class="tab-btn <?php echo $active_tab == 'riwayat' ? 'active' : ''; ?>" onclick="switchTab('riwayat')">
        <i class="fa-solid fa-clock-rotate-left"></i> Riwayat Pembelian
    </button>
</div>

<!-- MAIN CONTENT -->
<main class="main-container">

    <!-- TAB: DAFTAR ALAT -->
    <div id="tab-alat" class="tab-content" style="<?php echo $active_tab == 'alat' ? '' : 'display:none;'; ?>">
        <div class="section-header">
            <div>
                <h2 class="section-title">Alat Basket Tersedia</h2>
                <p class="section-subtitle">Pilih alat basket berkualitas yang Anda butuhkan.</p>
            </div>
        </div>

        <div class="alat-grid">
            <?php if (!empty($alat_list)): ?>
                <?php foreach ($alat_list as $alat): 
                    $photo_url = getPhotoUrl($alat['Photo_Alat'] ?? '');
                ?>
                <div class="alat-card">
                    <div class="alat-img-wrap">
                        <?php if (!empty($photo_url)): ?>
                            <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="<?php echo htmlspecialchars($alat['Nama_Alat']); ?>" class="alat-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                            <div class="alat-img-placeholder" style="display:none;"><i class="fa-solid fa-toolbox"></i></div>
                        <?php else: ?>
                            <div class="alat-img-placeholder"><i class="fa-solid fa-toolbox"></i></div>
                        <?php endif; ?>
                        <span class="alat-stok-badge"><i class="fa-solid fa-boxes-stacked"></i> <?php echo intval($alat['Stok']); ?> tersedia</span>
                    </div>
                    <div class="alat-body">
                        <div class="alat-name"><?php echo htmlspecialchars($alat['Nama_Alat']); ?></div>
                        <div class="alat-price"><?php echo rupiahFormat($alat['Harga_Alat']); ?></div>
                        <div class="alat-stok-info"><i class="fa-solid fa-circle-check" style="color:var(--green);"></i> Stok tersedia: <?php echo intval($alat['Stok']); ?> pcs</div>
                        <form method="POST" action="pembelian_customer.php" class="alat-actions" onsubmit="return validateAddToCart(this, <?php echo intval($alat['Stok']); ?>)">
                            <input type="hidden" name="add_to_cart" value="1">
                            <input type="hidden" name="id_alat" value="<?php echo intval($alat['ID_Alat']); ?>">
                            <input type="number" name="jumlah" value="1" min="1" max="<?php echo intval($alat['Stok']); ?>" class="jumlah-input" required>
                            <button type="submit" class="btn-add-cart">
                                <i class="fa-solid fa-cart-plus"></i> Tambah
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column:1/-1; text-align:center; padding:60px 20px; color:#636366;">
                    <i class="fa-solid fa-toolbox" style="font-size:48px; margin-bottom:16px; opacity:0.3; color:var(--primary);"></i>
                    <div style="font-size:18px; font-weight:700; color:#1C1C1E; margin-bottom:8px;">Belum ada alat tersedia</div>
                    <p>Silakan kembali lagi nanti.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB: KERANJANG -->
    <div id="tab-cart" class="tab-content" style="<?php echo $active_tab == 'cart' ? '' : 'display:none;'; ?>">
        <div class="section-header">
            <div>
                <h2 class="section-title">Keranjang Belanja</h2>
                <p class="section-subtitle">Review dan checkout alat yang ingin Anda beli.</p>
            </div>
        </div>

        <?php if (!empty($_SESSION['cart_alat'])): ?>
        <div class="cart-container">
            <a href="pembelian_customer.php?clear_cart=1" class="btn-clear-cart" onclick="return confirmClearCart()">
                <i class="fa-solid fa-trash-can"></i> Kosongkan Keranjang
            </a>

            <?php foreach ($_SESSION['cart_alat'] as $index => $item): 
                $photo_url = getPhotoUrl($item['photo'] ?? '');
            ?>
            <div class="cart-item">
                <?php if (!empty($photo_url)): ?>
                    <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="<?php echo htmlspecialchars($item['nama_alat']); ?>" class="cart-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                    <div class="cart-img-placeholder" style="display:none;"><i class="fa-solid fa-toolbox"></i></div>
                <?php else: ?>
                    <div class="cart-img-placeholder"><i class="fa-solid fa-toolbox"></i></div>
                <?php endif; ?>

                <div class="cart-info">
                    <div class="cart-name"><?php echo htmlspecialchars($item['nama_alat']); ?></div>
                    <div class="cart-price-unit"><?php echo rupiahFormat($item['harga']); ?> / pcs</div>
                    <form method="POST" action="pembelian_customer.php" class="cart-qty-control">
                        <input type="hidden" name="update_cart" value="1">
                        <input type="hidden" name="cart_index" value="<?php echo $index; ?>">
                        <button type="submit" name="new_jumlah" value="<?php echo max(0, $item['jumlah'] - 1); ?>" class="btn-qty">-</button>
                        <span class="qty-display"><?php echo $item['jumlah']; ?></span>
                        <button type="submit" name="new_jumlah" value="<?php echo $item['jumlah'] + 1; ?>" class="btn-qty">+</button>
                    </form>
                </div>

                <div class="cart-subtotal"><?php echo rupiahFormat($item['subtotal']); ?></div>

                <form method="POST" action="pembelian_customer.php" onsubmit="return confirmRemove(this)">
                    <input type="hidden" name="remove_cart" value="1">
                    <input type="hidden" name="cart_index" value="<?php echo $index; ?>">
                    <button type="submit" class="btn-remove" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>
                </form>
            </div>
            <?php endforeach; ?>

            <div class="cart-summary">
                <div class="summary-row">
                    <span class="summary-label">Total Item</span>
                    <span class="summary-value"><?php echo $cart_count; ?> item</span>
                </div>
                <div class="summary-row">
                    <span class="summary-label">Total Pembayaran</span>
                    <span class="summary-total"><?php echo rupiahFormat($cart_total); ?></span>
                </div>

                <form method="POST" action="pembelian_customer.php" id="checkoutForm">
                    <input type="hidden" name="checkout" value="1">
                    <div class="metode-pembayaran">
                        <span class="metode-label"><i class="fa-solid fa-wallet" style="margin-right:6px; color:var(--primary);"></i> Pilih Metode Pembayaran</span>
                        <div class="metode-options">
                            <div class="metode-option">
                                <input type="radio" name="metode_pembayaran" id="qris" value="QRIS" required>
                                <label for="qris">
                                    <i class="fa-solid fa-qrcode"></i>
                                    <span>QRIS</span>
                                </label>
                            </div>
                            <div class="metode-option">
                                <input type="radio" name="metode_pembayaran" id="transfer" value="Transfer Bank" required>
                                <label for="transfer">
                                    <i class="fa-solid fa-building-columns"></i>
                                    <span>Transfer Bank</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn-checkout" id="btnCheckout">
                        <i class="fa-solid fa-credit-card"></i> Bayar Sekarang
                    </button>
                </form>
            </div>
        </div>
        <?php else: ?>
        <div class="empty-cart">
            <i class="fa-solid fa-cart-shopping"></i>
            <div>Keranjang Belanja Kosong</div>
            <p>Tambahkan alat basket ke keranjang untuk melanjutkan pembelian.</p>
        </div>
        <?php endif; ?>
    </div>

    <!-- TAB: RIWAYAT PEMBELIAN -->
    <div id="tab-riwayat" class="tab-content" style="<?php echo $active_tab == 'riwayat' ? '' : 'display:none;'; ?>">
        <div class="section-header">
            <div>
                <h2 class="section-title">Riwayat Pembelian</h2>
                <p class="section-subtitle">Daftar transaksi pembelian alat basket Anda.</p>
            </div>
        </div>

        <?php if (!empty($riwayat_list)): ?>
        <div class="riwayat-card">
            <?php foreach ($riwayat_list as $rb): 
                $status = $status_beli[$rb['Status']] ?? $status_beli[0];
                $tgl_beli = $rb['Tanggal_Beli'] instanceof DateTime ? $rb['Tanggal_Beli']->format('d M Y') : date('d M Y', strtotime($rb['Tanggal_Beli']));
            ?>
            <div class="riwayat-item">
                <div class="riwayat-icon"><i class="fa-solid fa-toolbox"></i></div>
                <div class="riwayat-info">
                    <h4>Beli Alat #<?php echo $rb['ID_Beli']; ?></h4>
                    <p><?php echo $tgl_beli; ?> | <?php echo htmlspecialchars($rb['Metode_Pembayaran']); ?> | Karyawan: <?php echo htmlspecialchars($rb['Nama_Karyawan']); ?></p>
                </div>
                <div style="text-align:right;">
                    <div class="riwayat-price"><?php echo rupiahFormat($rb['Total_Bayar']); ?></div>
                    <span class="status-pill <?php echo $status['class']; ?>" style="margin-top:4px;">
                        <i class="fa-solid <?php echo $status['icon']; ?>"></i> <?php echo $status['label']; ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="empty-cart">
            <i class="fa-solid fa-clock-rotate-left"></i>
            <div>Belum Ada Riwayat</div>
            <p>Anda belum melakukan pembelian alat basket.</p>
        </div>
        <?php endif; ?>
    </div>

</main>

<!-- FOOTER -->
<footer>
    <div class="footer-grid">
        <div>
            <div class="footer-logo">
                <img src="../asset/image/logo.png" alt="HoopBall">
                <span>HoopBall</span>
            </div>
            <p class="footer-desc">Platform penyewaan lapangan basket dan pembelian alat basket online terpercaya.</p>
            <div class="social-links">
                <a href="#" class="social-btn"><i class="fa-brands fa-instagram"></i></a>
                <a href="#" class="social-btn"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" class="social-btn"><i class="fa-brands fa-tiktok"></i></a>
            </div>
        </div>
        <div class="footer-col">
            <h4>Navigasi</h4>
            <ul>
                <li><a href="view_customer.php">Beranda</a></li>
                <li><a href="booking_customer.php">Booking</a></li>
                <li><a href="pembelian_customer.php">Pembelian Alat</a></li>
                <li><a href="langganan_customer.php">Member</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Informasi</h4>
            <ul>
                <li><a href="#">Cara Beli</a></li>
                <li><a href="#">Syarat & Ketentuan</a></li>
                <li><a href="#">Kebijakan Privasi</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Hubungi Kami</h4>
            <div class="contact-item"><i class="fa-solid fa-phone"></i> +62 812-3456-7890</div>
            <div class="contact-item"><i class="fa-solid fa-envelope"></i> info@hoopball.id</div>
            <div class="contact-item"><i class="fa-solid fa-clock"></i> Setiap hari 07:00 - 23:00 WIB</div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; 2025 HoopBall. All rights reserved.</p>
    </div>
</footer>

<script>
function switchTab(tab) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById('tab-' + tab).style.display = 'block';
    event.target.closest('.tab-btn').classList.add('active');

    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('tab', tab);
    window.history.replaceState({}, '', url);
}

function validateAddToCart(form, maxStok) {
    const jumlahInput = form.querySelector('input[name="jumlah"]');
    const jumlah = parseInt(jumlahInput.value);
    if (jumlah <= 0 || jumlah > maxStok) {
        Swal.fire({
            icon: 'error',
            title: 'Jumlah Tidak Valid',
            text: 'Jumlah harus antara 1 dan ' + maxStok + ' pcs.',
            confirmButtonColor: '#FF5200'
        });
        return false;
    }
    return true;
}

function confirmClearCart() {
    return confirm('Yakin ingin mengosongkan keranjang?');
}

function confirmRemove(form) {
    return confirm('Yakin ingin menghapus item ini dari keranjang?');
}

// URL Parameter Notification (Toast Style)
const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
const msg = urlParams.get('msg');

if (status && msg) {
    const isSuccess = status === 'success';
    Swal.fire({
        icon: isSuccess ? 'success' : 'error',
        title: isSuccess ? 'Berhasil!' : 'Gagal!',
        text: msg,
        timer: 4000,
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

// Checkout confirmation
document.getElementById('checkoutForm')?.addEventListener('submit', function(e) {
    const metode = document.querySelector('input[name="metode_pembayaran"]:checked');
    if (!metode) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Pilih Metode Pembayaran',
            text: 'Silakan pilih metode pembayaran terlebih dahulu.',
            confirmButtonColor: '#FF5200'
        });
        return false;
    }

    e.preventDefault();
    Swal.fire({
        title: 'Konfirmasi Pembayaran',
        html: 'Anda akan melakukan pembayaran sebesar <strong style="color:#FF5200;"><?php echo rupiahFormat($cart_total); ?></strong> menggunakan <strong>' + metode.value + '</strong>.<br><br>Status pembelian akan menjadi <strong>Menunggu Konfirmasi</strong> sampai karyawan memverifikasi pembayaran.',
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#FF5200',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Bayar Sekarang!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            const btn = document.getElementById('btnCheckout');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';
            this.submit();
        }
    });
});
</script>

</body>
</html>