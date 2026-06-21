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

        // Build item names string for notification
        $item_names = [];
        foreach ($_SESSION['cart_alat'] as $item) {
            $item_names[] = $item['nama_alat'];
        }
        $item_names_str = implode(', ', $item_names);

        // Clear cart
        $_SESSION['cart_alat'] = [];

        header("Location: pembelian_customer.php?status=success&msg=" . urlencode("Pembelian berhasil! Status: Menunggu Konfirmasi. Alat: " . $item_names_str));
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
:root { --primary:#FF5200; --primary-hover:#E04800; --dark-bg:#0B0B0C; --card-dark:#121214; --text-gray:#8E8E93; --border-color:#222225; --white:#FFFFFF; --light-bg:#F8F9FA; --green:#34C759; --green-lt:rgba(52,199,89,.10); --yellow:#FFCC00; --yellow-lt:rgba(255,204,0,.10); --red:#FF3B30; --red-lt:rgba(255,59,48,.10); --blue:#007AFF; --blue-lt:rgba(0,122,255,.10); --orange:#FF4500; --orange-lt:rgba(255,69,0,.10); --shopee-orange:#EE4D2D; --text-primary:#0F172A; --text-secondary:#475569; --muted:#94A3B8; --bg:#F8FAFC; --border:#E2E8F0; --border-lt:#F1F5F9; --transition-smooth:all 0.3s cubic-bezier(0.4, 0, 0.2, 1); --purple:#AF52DE; --purple-lt:rgba(175,82,222,.10); --orange-glow:rgba(255, 90, 31, 0.15); }
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


/* MODAL PEMBAYARAN */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); z-index: 2000; align-items: center; justify-content: center; backdrop-filter: blur(4px); animation: fadeInModal 0.25s ease-out forwards; }
.modal-overlay.active { display: flex; }
@keyframes fadeInModal { from { opacity: 0; } to { opacity: 1; } }
.modal-box { background: var(--white); border-radius: 20px; width: 480px; max-width: 90%; max-height: 90vh; overflow-y: auto; padding: 32px; animation: slideInModal 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; position: relative; }
@keyframes slideInModal { from { transform: translateY(30px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
.modal-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; }
.modal-header h3 { font-size: 20px; font-weight: 800; color: #1C1C1E; }
.modal-close { width: 36px; height: 36px; border-radius: 50%; background: #F2F2F7; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #8E8E93; font-size: 14px; transition: all 0.3s ease; }
.modal-close:hover { background: var(--red-lt); color: var(--red); transform: rotate(90deg); }
.modal-summary { background: #F8F9FA; border-radius: 12px; padding: 20px; margin-bottom: 24px; }
.modal-summary-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; font-size: 14px; transition: all 0.3s ease; }
.modal-summary-row:hover { transform: translateX(5px); }
.modal-summary-row.total { border-top: 2px solid #E5E5EA; padding-top: 12px; margin-top: 8px; font-weight: 800; font-size: 18px; color: var(--primary); }

.payment-section { border-top: 1px solid var(--border-lt); padding: 20px 0 10px; margin-top: 16px; }
.payment-header { font-size: 12.5px; font-weight: 700; color: var(--text-primary); margin-bottom: 12px; display: flex; align-items: center; gap: 6px; }
.payment-header i { color: var(--muted); }
.payment-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
.payment-card { border: 1px solid var(--border); border-radius: 10px; padding: 12px; cursor: pointer; display: flex; align-items: center; gap: 10px; transition: all 0.3s cubic-bezier(0.16,1,0.3,1); user-select: none; position: relative; overflow: hidden; }
.payment-card::before { content: ''; position: absolute; top: 50%; left: 50%; width: 0; height: 0; background: rgba(255,90,31,0.1); border-radius: 50%; transform: translate(-50%,-50%); transition: width 0.4s, height 0.4s; }
.payment-card:hover::before { width: 200px; height: 200px; }
.payment-card:hover { border-color: var(--primary); transform: translateY(-2px); box-shadow: 0 4px 12px rgba(255,90,31,0.1); }
.payment-card.selected { border-color: var(--primary); background: var(--orange-lt); }
.custom-radio { width: 16px; height: 16px; border-radius: 50%; border: 1.5px solid var(--muted); display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: 0.2s; }
.payment-card.selected .custom-radio { border-color: var(--primary); }
.custom-radio::after { content: ''; width: 8px; height: 8px; border-radius: 50%; background: var(--primary); display: none; }
.payment-card.selected .custom-radio::after { display: block; animation: scaleIn 0.2s ease-out; }
.payment-card-content { display: flex; flex-direction: column; justify-content: center; }
.payment-name { font-size: 11px; font-weight: 700; color: var(--text-primary); line-height: 1.3; }
.payment-sub { font-size: 9px; color: var(--muted); margin-top: 1px; font-weight: 500; }
.qris-logo { font-family: 'Barlow Condensed', sans-serif; font-weight: 900; font-size: 14px; color: #000; letter-spacing: -0.5px; }

.btn-bayar { width: 100%; background: var(--primary); color: var(--white); border: none; padding: 16px; border-radius: 12px; font-size: 15px; font-weight: 800; cursor: pointer; transition: all 0.3s cubic-bezier(0.16,1,0.3,1); display: flex; align-items: center; justify-content: center; gap: 8px; position: relative; overflow: hidden; margin-top: 16px; }
.btn-bayar::before { content: ''; position: absolute; top: 50%; left: 50%; width: 0; height: 0; background: rgba(255,255,255,0.2); border-radius: 50%; transform: translate(-50%,-50%); transition: width 0.6s, height 0.6s; }
.btn-bayar:hover::before { width: 400px; height: 400px; }
.btn-bayar:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 10px 30px rgba(255,82,0,0.4); }
.btn-bayar:disabled { background: var(--muted); cursor: not-allowed; transform: none; box-shadow: none; }

.booking-disclaimer { display: flex; align-items: center; justify-content: center; gap: 6px; font-size: 11px; color: var(--muted); margin-top: 10px; font-weight: 500; }
.booking-disclaimer i { color: var(--green); animation: pulse 2s ease-in-out infinite; }

/* INSTRUKSI PEMBAYARAN MODAL */
.payment-instruction-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 2000; padding: 20px; animation: fadeInModal 0.25s ease-out forwards; }
.payment-instruction-overlay.active { display: flex; }
.instruction-card { background: #fff; border-radius: 20px !important; border: none !important; padding: 30px !important; width: 100%; max-width: 460px; max-height: 90vh; overflow-y: auto; position: relative; box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15) !important; animation: slideInModal 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; text-align: center; }
.instruction-close { position: absolute; top: 20px; right: 20px; background: var(--border-lt); border: none; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-secondary); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); z-index: 10; }
.instruction-close:hover { background: var(--red-lt); color: var(--red); transform: rotate(90deg); }
.switch-tabs { display: flex; gap: 6px; margin-bottom: 16px; background: var(--border-lt); padding: 4px; border-radius: 10px; border: 1px solid var(--border); }
.switch-tab { flex: 1; padding: 10px; border: none; border-radius: 8px; font-family: inherit; font-size: 12px; font-weight: 700; cursor: pointer; transition: var(--transition-smooth); background: transparent; color: var(--text-secondary); }
.switch-tab.active { background: #fff; color: var(--primary); box-shadow: 0 2px 6px rgba(0,0,0,0.05); }
.countdown-box { background: var(--orange-lt); border: 1px solid rgba(255, 90, 31, 0.15); border-radius: 10px; padding: 12px 16px; display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 20px; animation: fadeInUp 0.5s ease-out; }
.countdown-box i { color: var(--orange); animation: pulse 2s ease-in-out infinite; }
.countdown-text { color: var(--primary-hover); font-weight: 700; font-size: 12px; }
.total-box { background: var(--bg); padding: 14px 18px; border-radius: 12px; margin-bottom: 20px; border: 1px solid var(--border); animation: fadeInUp 0.5s ease-out; }
.total-label { font-size: 11px; color: var(--text-secondary); font-weight: 600; text-transform: uppercase; }
.total-amount { font-size: 24px; color: var(--primary); font-weight: 900; margin-top: 4px; }
.va-box { text-align: left; display: none; }
.va-box.active { display: block; }
.va-label { font-size: 12.5px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; }
.va-input-group { display: flex; gap: 8px; margin-bottom: 16px; }
.va-input { flex: 1; padding: 10px 14px; font-weight: 800; text-align: center; font-size: 15px; letter-spacing: 1px; color: var(--text-primary); border: 1px solid var(--border); border-radius: 10px; background: #fff; font-family: inherit; }
.btn-copy { padding: 10px 14px; border-radius: 10px; font-size: 12px; border: 1px solid var(--border); background: #fff; cursor: pointer; font-family: inherit; font-weight: 600; color: var(--text-secondary); transition: all 0.3s ease; }
.btn-copy:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
.va-steps { text-align: left; font-size: 11.5px; color: var(--text-secondary); padding-left: 20px; line-height: 1.6; display: flex; flex-direction: column; gap: 6px; }
.qris-box { display: none; align-items: center; flex-direction: column; }
.qris-box.active { display: flex; }
.qris-label { font-size: 12.5px; font-weight: 700; color: var(--text-primary); margin-bottom: 12px; }
.qris-image-wrapper { background: #fff; padding: 12px; border: 1px solid var(--border); border-radius: 12px; width: fit-content; margin-bottom: 16px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); animation: fadeInUp 0.5s ease-out; }
.qris-image { display: block; width: 170px; height: 180px; object-fit: contain; }
.qris-steps { text-align: left; font-size: 11.5px; color: var(--text-secondary); padding-left: 20px; line-height: 1.6; display: flex; flex-direction: column; gap: 6px; width: 100%; }
.btn-done { width: 100%; background: var(--primary); color: #fff; border: none; border-radius: 12px; padding: 14px; font-family: inherit; font-size: 14px; font-weight: 700; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 16px; transition: all 0.3s cubic-bezier(0.16,1,0.3,1); position: relative; overflow: hidden; }
.btn-done::before { content: ''; position: absolute; top: 50%; left: 50%; width: 0; height: 0; background: rgba(255,255,255,0.2); border-radius: 50%; transform: translate(-50%,-50%); transition: width 0.6s, height 0.6s; }
.btn-done:hover::before { width: 400px; height: 400px; }
.btn-done:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 10px 30px rgba(255,82,0,0.4); }

</style>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&display=swap" rel="stylesheet">
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
            <a href="pembelian_customer.php?clear_cart=1" class="btn-clear-cart" onclick="confirmClearCart(); return false;">
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

                <form method="POST" action="pembelian_customer.php" id="checkoutForm" style="display:none;">
                    <input type="hidden" name="checkout" value="1">
                    <input type="hidden" name="metode_pembayaran" id="inputMetode" value="Transfer Bank">
                </form>
                <button type="button" class="btn-checkout" id="btnCheckout" onclick="bukaModalPembayaran()">
                    <i class="fa-solid fa-credit-card"></i> Bayar Sekarang
                </button>
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

    <!-- MODAL PEMBAYARAN -->
<div class="modal-overlay" id="paymentModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3><i class="fa-solid fa-basket-shopping" style="color: var(--primary); margin-right: 8px;"></i>Ringkasan Pembelian Alat</h3>
            <button class="modal-close" onclick="tutupModalPembayaran()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-summary">
            <div class="modal-summary-row">
                <span>Total Item</span>
                <span id="modalTotalItem" style="font-weight: 700;"><?php echo $cart_count; ?> item</span>
            </div>
            <div class="modal-summary-row">
                <span>Total Pembayaran</span>
                <span id="modalTotalBayar" style="font-weight: 700; color: var(--primary);"><?php echo rupiahFormat($cart_total); ?></span>
            </div>
            <div class="modal-summary-row total">
                <span>Total Tagihan</span>
                <span id="modalTotalTagihan"><?php echo rupiahFormat($cart_total); ?></span>
            </div>
        </div>

        <div class="payment-section">
            <div class="payment-header">
                <i class="fa-solid fa-wallet"></i> Metode Pembayaran
            </div>
            <div class="payment-grid">
                <div class="payment-card selected" data-method="Transfer Bank" onclick="pilihMetode(this)">
                    <div class="custom-radio"></div>
                    <div class="payment-card-content">
                        <span class="payment-name">Transfer Bank</span>
                        <span class="payment-sub">Virtual Account</span>
                    </div>
                </div>
                <div class="payment-card" data-method="QRIS" onclick="pilihMetode(this)">
                    <div class="custom-radio"></div>
                    <div class="payment-card-content">
                        <span class="payment-name qris-logo">QRIS</span>
                        <span class="payment-sub">Scan & Bayar Instan</span>
                    </div>
                </div>
            </div>
        </div>

        <button type="button" class="btn-bayar" id="btnBayar" onclick="prosesBayar()">
            <i class="fa-solid fa-lock"></i> Bayar Sekarang
        </button>

        <div class="booking-disclaimer">
            <i class="fa-solid fa-circle-check"></i> Enkripsi data aman terverifikasi
        </div>
    </div>
</div>

<!-- MODAL INSTRUKSI PEMBAYARAN -->
<div class="payment-instruction-overlay" id="instructionModal">
    <div class="instruction-card">
        <button class="instruction-close" onclick="tutupInstructionModal()">
            <i class="fa-solid fa-xmark"></i>
        </button>

        <h2 style="font-family: 'Barlow Condensed', sans-serif; font-size: 16px; font-weight: 800; letter-spacing: 0.5px; color: var(--muted); margin-bottom: 20px; text-transform: uppercase; text-align: center;">Instruksi Pembayaran</h2>

        <div class="switch-tabs">
            <button id="btnSwitchVA" class="switch-tab active" onclick="switchPaymentMethod('Transfer Bank')">
                <i class="fa-solid fa-university" style="margin-right: 4px;"></i> Virtual Account
            </button>
            <button id="btnSwitchQRIS" class="switch-tab" onclick="switchPaymentMethod('QRIS')">
                <i class="fa-solid fa-qrcode" style="margin-right: 4px;"></i> QRIS Scan
            </button>
        </div>

        <div class="countdown-box">
            <i class="fa-solid fa-clock"></i>
            <p class="countdown-text">
                Selesaikan pembayaran dalam <span id="paymentCountdown">15:00</span>
            </p>
        </div>

        <div class="total-box">
            <div class="total-label">Total Tagihan</div>
            <div class="total-amount" id="instructionTotal"><?php echo rupiahFormat($cart_total); ?></div>
        </div>

        <div id="instruksiTransfer" class="va-box active">
            <!-- Bank Info Card -->
            <div style="background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border: 1px solid var(--border); border-radius: 14px; padding: 20px; margin-bottom: 20px; text-align: left; position: relative; overflow: hidden;">
                <div style="position: absolute; top: -20px; right: -20px; width: 80px; height: 80px; background: rgba(255,82,0,0.05); border-radius: 50%;"></div>

                <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 16px;">
                    <div style="width: 44px; height: 44px; background: var(--primary); border-radius: 12px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; box-shadow: 0 4px 12px rgba(255,82,0,0.3);">
                        <i class="fa-solid fa-building-columns"></i>
                    </div>
                    <div>
                        <div style="font-size: 13px; font-weight: 800; color: var(--text-primary);">Virtual Account</div>
                        <div style="font-size: 11px; color: var(--muted); font-weight: 500;">Mandiri / BCA / BNI / BRI</div>
                    </div>
                </div>

                <div style="font-size: 11.5px; font-weight: 700; color: var(--text-secondary); margin-bottom: 10px; text-transform: uppercase; letter-spacing: 0.5px;">Nomor Virtual Account</div>

                <div style="display: flex; gap: 8px; margin-bottom: 0;">
                    <div style="flex: 1; background: #fff; border: 2px solid var(--border); border-radius: 12px; padding: 14px 16px; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); transition: all 0.3s ease;" onmouseover="this.style.borderColor='var(--primary)';this.style.boxShadow='0 4px 16px rgba(255,82,0,0.1)'" onmouseout="this.style.borderColor='var(--border)';this.style.boxShadow='0 2px 8px rgba(0,0,0,0.04)'">
                        <i class="fa-solid fa-hashtag" style="color: var(--primary); font-size: 14px;"></i>
                        <input type="text" id="vaNumber" value="8801281234567890" style="border: none; background: transparent; font-weight: 800; text-align: center; font-size: 18px; letter-spacing: 2px; color: var(--text-primary); font-family: 'Plus Jakarta Sans', monospace; width: 100%; outline: none;" readonly>
                    </div>
                    <button class="btn-copy" id="btnCopyVA" onclick="salinVA()" style="border-radius: 12px; font-size: 13px; padding: 14px 18px; display: flex; align-items: center; gap: 6px; white-space: nowrap; background: var(--primary); color: #fff; border: none; font-weight: 700; box-shadow: 0 4px 12px rgba(255,82,0,0.3); transition: all 0.3s ease;" onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 6px 20px rgba(255,82,0,0.4)'" onmouseout="this.style.transform='translateY(0)';this.style.boxShadow='0 4px 12px rgba(255,82,0,0.3)'">
                        <i class="fa-regular fa-copy"></i> Salin
                    </button>
                </div>
            </div>

            <!-- Steps -->
            <div style="text-align: left;">
                <div style="font-size: 11.5px; font-weight: 700; color: var(--text-secondary); margin-bottom: 14px; text-transform: uppercase; letter-spacing: 0.5px;">Cara Pembayaran</div>

                <div style="display: flex; flex-direction: column; gap: 12px;">
                    <div style="display: flex; gap: 14px; align-items: flex-start; padding: 14px 16px; background: #fafafa; border-radius: 12px; border: 1px solid var(--border-lt); transition: all 0.3s ease;" onmouseover="this.style.background='#fff';this.style.borderColor='var(--primary)';this.style.transform='translateX(4px)'" onmouseout="this.style.background='#fafafa';this.style.borderColor='var(--border-lt)';this.style.transform='translateX(0)'">
                        <div style="width: 28px; height: 28px; background: var(--orange-lt); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; flex-shrink: 0; margin-top: 2px;">1</div>
                        <div>
                            <div style="font-size: 13px; font-weight: 700; color: var(--text-primary); margin-bottom: 2px;">Buka Aplikasi Banking</div>
                            <div style="font-size: 12px; color: var(--text-secondary); line-height: 1.5;">Pilih menu <strong style="color: var(--primary);">Transfer > Virtual Account</strong> pada aplikasi M-Banking atau ATM Anda.</div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 14px; align-items: flex-start; padding: 14px 16px; background: #fafafa; border-radius: 12px; border: 1px solid var(--border-lt); transition: all 0.3s ease;" onmouseover="this.style.background='#fff';this.style.borderColor='var(--primary)';this.style.transform='translateX(4px)'" onmouseout="this.style.background='#fafafa';this.style.borderColor='var(--border-lt)';this.style.transform='translateX(0)'">
                        <div style="width: 28px; height: 28px; background: var(--orange-lt); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; flex-shrink: 0; margin-top: 2px;">2</div>
                        <div>
                            <div style="font-size: 13px; font-weight: 700; color: var(--text-primary); margin-bottom: 2px;">Masukkan Nomor VA</div>
                            <div style="font-size: 12px; color: var(--text-secondary); line-height: 1.5;">Masukkan nomor Virtual Account <strong style="color: var(--primary);">8801281234567890</strong> di atas.</div>
                        </div>
                    </div>

                    <div style="display: flex; gap: 14px; align-items: flex-start; padding: 14px 16px; background: #fafafa; border-radius: 12px; border: 1px solid var(--border-lt); transition: all 0.3s ease;" onmouseover="this.style.background='#fff';this.style.borderColor='var(--primary)';this.style.transform='translateX(4px)'" onmouseout="this.style.background='#fafafa';this.style.borderColor='var(--border-lt)';this.style.transform='translateX(0)'">
                        <div style="width: 28px; height: 28px; background: var(--orange-lt); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 12px; font-weight: 800; flex-shrink: 0; margin-top: 2px;">3</div>
                        <div>
                            <div style="font-size: 13px; font-weight: 700; color: var(--text-primary); margin-bottom: 2px;">Konfirmasi Pembayaran</div>
                            <div style="font-size: 12px; color: var(--text-secondary); line-height: 1.5;">Nominal pembayaran akan otomatis muncul sesuai total tagihan. Konfirmasi dan selesaikan pembayaran.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="instruksiQRIS" class="qris-box">
            <div class="qris-label">Pindai Kode QRIS Resmi HoopBall</div>
            <div class="qris-image-wrapper">
                <img id="qrisImage" src="" alt="QRIS Code" class="qris-image">
            </div>
            <ul class="qris-steps">
                <li>Buka aplikasi e-wallet Anda (GoPay, OVO, Dana, LinkAja) atau Mobile Banking.</li>
                <li>Pilih opsi <strong>Scan / Bayar QRIS</strong>.</li>
                <li>Arahkan kamera smartphone ke kode QR di atas, lalu selesaikan pembayaran.</li>
            </ul>
        </div>

        <hr style="border: none; height: 1px; background: var(--border-lt); margin: 20px 0;">

        <button class="btn-done" id="btnDonePayment" onclick="selesaiBayar()">
            Saya Sudah Bayar <i class="fa-solid fa-circle-check"></i>
        </button>
    </div>
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
    Swal.fire({
        icon: 'warning',
        title: 'Kosongkan Keranjang?',
        text: 'Semua item di keranjang akan dihapus. Lanjutkan?',
        showCancelButton: true,
        confirmButtonColor: '#FF5200',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Kosongkan',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'pembelian_customer.php?clear_cart=1';
        }
    });
    return false;
}

function confirmRemove(form) {
    Swal.fire({
        icon: 'warning',
        title: 'Hapus Item?',
        text: 'Item ini akan dihapus dari keranjang. Lanjutkan?',
        showCancelButton: true,
        confirmButtonColor: '#FF3B30',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
    return false;
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
        confirmButtonColor: '#FF5200',
        confirmButtonText: 'OK'
    });
    window.history.replaceState({}, document.title, window.location.pathname);
}

// ==================== MODAL PEMBAYARAN ====================
let selectedMetode = 'Transfer Bank';
let countdownInterval;

function bukaModalPembayaran() {
    document.getElementById('paymentModal').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function tutupModalPembayaran() {
    document.getElementById('paymentModal').classList.remove('active');
    document.body.style.overflow = '';
}

function tutupInstructionModal() {
    document.getElementById('instructionModal').classList.remove('active');
    document.body.style.overflow = '';
    clearInterval(countdownInterval);
}

function pilihMetode(el) {
    document.querySelectorAll('.payment-card').forEach(p => p.classList.remove('selected'));
    el.classList.add('selected');
    selectedMetode = el.getAttribute('data-method');
    document.getElementById('inputMetode').value = selectedMetode;
}

function prosesBayar() {
    tutupModalPembayaran();
    document.getElementById('instructionModal').classList.add('active');
    document.body.style.overflow = 'hidden';

    showPaymentMethodInstructions(selectedMetode);
    startPaymentCountdown(15 * 60);
}

function showPaymentMethodInstructions(method) {
    selectedMetode = method;
    const btnSwitchVA = document.getElementById('btnSwitchVA');
    const btnSwitchQRIS = document.getElementById('btnSwitchQRIS');

    if (method === 'Transfer Bank') {
        btnSwitchVA.classList.add('active');
        btnSwitchQRIS.classList.remove('active');
        document.getElementById('instruksiTransfer').classList.add('active');
        document.getElementById('instruksiQRIS').classList.remove('active');
    } else {
        btnSwitchQRIS.classList.add('active');
        btnSwitchVA.classList.remove('active');
        document.getElementById('instruksiTransfer').classList.remove('active');
        document.getElementById('instruksiQRIS').classList.add('active');

        const qrPayload = 'HOOPBALL-ALAT-' + Date.now();
        document.getElementById('qrisImage').src = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' + encodeURIComponent(qrPayload);
    }
}

function switchPaymentMethod(method) {
    showPaymentMethodInstructions(method);
}

function startPaymentCountdown(duration) {
    let timer = duration, minutes, seconds;
    const display = document.getElementById('paymentCountdown');
    clearInterval(countdownInterval);

    countdownInterval = setInterval(function () {
        minutes = parseInt(timer / 60, 10);
        seconds = parseInt(timer % 60, 10);
        minutes = minutes < 10 ? "0" + minutes : minutes;
        seconds = seconds < 10 ? "0" + seconds : seconds;
        display.textContent = minutes + ":" + seconds;
        if (--timer < 0) {
            clearInterval(countdownInterval);
            display.textContent = "Waktu Habis";
            document.getElementById('btnDonePayment').disabled = true;
        }
    }, 1000);
}

function salinVA() {
    const vaInput = document.getElementById('vaNumber');
    vaInput.select();
    vaInput.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(vaInput.value).then(() => {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil Disalin!',
            text: 'Nomor Virtual Account disalin ke papan klip.',
            confirmButtonColor: '#FF5200',
            confirmButtonText: 'OK',
            timer: 2000
        });
    });
}

function selesaiBayar() {
    clearInterval(countdownInterval);
    tutupInstructionModal();
    document.getElementById('checkoutForm').submit();
}

window.addEventListener('click', function(e) {
    if (e.target === document.getElementById('paymentModal')) {
        tutupModalPembayaran();
    }
    if (e.target === document.getElementById('instructionModal')) {
        tutupInstructionModal();
    }
});</script>

</body>
</html>