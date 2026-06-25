<?php
// ============================================================================
// BUFFER OUTPUT & SESSION
// ============================================================================
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
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

// ============================================================================
// AJAX HANDLER: PROSES CHECKOUT (STATUS = 0 / MENUNGGU KONFIRMASI)
// ============================================================================
if (isset($_GET['action']) && $_GET['action'] == 'checkout' && $_SERVER['REQUEST_METHOD'] == 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);

    $cart = $input['cart_data'] ?? [];
    $metode = htmlspecialchars($input['metode_pembayaran'] ?? '');
    $total = floatval($input['total_bayar'] ?? 0);

    if (empty($cart) || empty($metode) || $total <= 0) {
        echo json_encode(['success' => false, 'message' => 'Data pesanan tidak valid.']);
        exit();
    }

    if (sqlsrv_begin_transaction($conn) === false) {
        echo json_encode(['success' => false, 'message' => 'Gagal menginisiasi transaksi database.']);
        exit();
    }

    try {
        $valid_cart = [];
        $calculated_total = 0;

        // 1. Validasi Stok
        foreach ($cart as $item) {
            $id_alat = intval($item['id_alat']);
            $jumlah = intval($item['jumlah']);

            $cek_stok = sqlsrv_query($conn, "SELECT Nama_Alat, Stok, Harga_Alat FROM Alat WHERE ID_Alat = ? AND Status = 1 AND Is_Deleted = 0", array($id_alat));
            $alat_data = sqlsrv_fetch_array($cek_stok, SQLSRV_FETCH_ASSOC);

            if (!$alat_data) {
                throw new Exception("Salah satu alat tidak ditemukan.");
            }
            if ($alat_data['Stok'] < $jumlah) {
                throw new Exception("Stok " . $alat_data['Nama_Alat'] . " tidak mencukupi.");
            }

            $subtotal = $alat_data['Harga_Alat'] * $jumlah;
            $calculated_total += $subtotal;
            $valid_cart[] = [
                'id_alat' => $id_alat,
                'jumlah' => $jumlah,
                'subtotal' => $subtotal
            ];
        }

        // 2. Insert ke Beli_Alat (Status = 0 -> Menunggu Konfirmasi Karyawan)
        $sql_beli = "INSERT INTO Beli_Alat (ID_Karyawan, ID_Customer, Tanggal_Beli, Metode_Pembayaran, Total_Bayar, Status, Created_By, Created_Date)
                     OUTPUT INSERTED.ID_Beli
                     VALUES (1, ?, GETDATE(), ?, ?, 0, ?, GETDATE())"; // Default Karyawan 1 untuk online
        $stmt_beli = sqlsrv_query($conn, $sql_beli, array($id_customer, $metode, $calculated_total, $nama_customer));

        if ($stmt_beli === false) {
            throw new Exception("Gagal membuat data pesanan utama.");
        }

        $row_id = sqlsrv_fetch_array($stmt_beli, SQLSRV_FETCH_ASSOC);
        $id_beli = $row_id['ID_Beli'];

        // 3. Insert Detail dan Potong Stok
        foreach ($valid_cart as $item) {
            $sql_detail = "INSERT INTO Detail_Beli_Alat (ID_Alat, ID_Beli, Jumlah, SubTotal) VALUES (?, ?, ?, ?)";
            $stmt_detail = sqlsrv_query($conn, $sql_detail, array($item['id_alat'], $id_beli, $item['jumlah'], $item['subtotal']));
            if ($stmt_detail === false) throw new Exception("Gagal menyimpan detail alat.");

            $sql_update_stok = "UPDATE Alat SET Stok = Stok - ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Alat = ?";
            $stmt_update = sqlsrv_query($conn, $sql_update_stok, array($item['jumlah'], $nama_customer, $item['id_alat']));
            if ($stmt_update === false) throw new Exception("Gagal memperbarui stok alat.");
        }

        sqlsrv_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Pesanan berhasil dibuat! Menunggu konfirmasi karyawan.']);
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
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

function resolvePhotoPath($photo_path) {
    if (empty($photo_path)) return '';
    if (strpos($photo_path, 'http://') === 0 || strpos($photo_path, 'https://') === 0) return $photo_path;
    if (strpos($photo_path, '../') === 0) return $photo_path;
    if (strpos($photo_path, '/') === 0) return '..' . $photo_path;
    return '../' . ltrim($photo_path, '/');
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pembelian Alat | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Barlow+Condensed:wght@700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
    /* ═══ SHARED ROOT VARIABLES ═══ */
    :root {
        --primary: #FF5200; --primary-hover: #E04800; --primary-light: rgba(255,82,0,0.1);
        --dark-bg: #0B0B0C; --card-dark: #121214; --text-gray: #8E8E93;
        --white: #FFFFFF; --light-bg: #F8F9FA;
        --green: #34C759; --green-lt: rgba(52,199,89,.10);
        --yellow: #FFCC00; --yellow-lt: rgba(255,204,0,.10);
        --red: #FF3B30; --red-lt: rgba(255,59,48,.10);
        --blue: #007AFF; --blue-lt: rgba(0,122,255,.10);
        --orange: #FF5A1F; --orange-hover: #E0440E;
        --orange-lt: rgba(255,90,31,0.06); --orange-glow: rgba(255,90,31,0.15);
        --border: #E2E8F0; --border-lt: #F1F5F9;
        --text-primary: #0F172A; --text-secondary: #475569; --muted: #94A3B8;
        --bg: #F8FAFC; --shopee-orange: #EE4D2D;
        --transition-smooth: all 0.3s cubic-bezier(0.4,0,0.2,1);
    }

    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
    body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--light-bg);color:var(--text-primary);overflow-x:hidden}
    ::-webkit-scrollbar{display:none} html,body{-ms-overflow-style:none;scrollbar-width:none}

    /* ═══ ANIMATIONS ═══ */
    @keyframes fadeInUp{from{opacity:0;transform:translateY(40px)}to{opacity:1;transform:translateY(0)}}
    @keyframes fadeInDown{from{opacity:0;transform:translateY(-30px)}to{opacity:1;transform:translateY(0)}}
    @keyframes fadeIn{from{opacity:0}to{opacity:1}}
    @keyframes scaleIn{from{opacity:0;transform:scale(0.8)}to{opacity:1;transform:scale(1)}}
    @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
    @keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.05)}}
    @keyframes shimmer{0%{background-position:-200% 0}100%{background-position:200% 0}}
    @keyframes slideInModal{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
    @keyframes fadeInModal{from{opacity:0}to{opacity:1}}
    @keyframes countUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
    @keyframes drawLine{from{width:0}to{width:60px}}

    /* ═══ NAVBAR ═══ */
    nav{background:var(--white);padding:0 80px;display:flex;justify-content:space-between;align-items:center;height:76px;position:sticky;top:0;z-index:1000;border-bottom:1px solid #E5E5EA;animation:fadeInDown .6s ease-out forwards}
    .nav-logo{display:flex;align-items:center;text-decoration:none;gap:10px;transition:transform .3s ease}
    .nav-logo:hover{transform:scale(1.05)}
    .nav-logo img{height:70px;width:auto;transition:transform .5s cubic-bezier(.34,1.56,.64,1)}
    .nav-logo:hover img{transform:rotate(5deg) scale(1.1)}
    .nav-links{display:flex;align-items:center;gap:8px}
    .nav-links a{color:#636366;text-decoration:none;font-size:14px;font-weight:500;padding:8px 16px;border-radius:20px;transition:var(--transition-smooth);position:relative;overflow:hidden}
    .nav-links a::before{content:'';position:absolute;bottom:0;left:50%;width:0;height:2px;background:var(--primary);transition:var(--transition-smooth);transform:translateX(-50%)}
    .nav-links a:hover{color:#1C1C1E;transform:translateY(-2px)}
    .nav-links a:hover::before{width:60%}
    .nav-links a.active{color:var(--primary);font-weight:600}
    .nav-links a.active::before{width:60%}

    .nav-user-container{position:relative;height:76px;display:flex;align-items:center}
    .nav-user{background:#F2F2F7;border:1px solid #E5E5EA;padding:8px 16px;border-radius:50px;color:#1C1C1E;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:10px;transition:var(--transition-smooth)}
    .nav-user:hover{background:#E5E5EA;border-color:var(--primary);transform:scale(1.02);box-shadow:0 4px 12px rgba(255,82,0,.15)}
    .nav-user img.user-avatar{width:24px;height:24px;border-radius:50%;object-fit:cover;transition:transform .3s ease}
    .nav-user:hover img.user-avatar{transform:scale(1.15)}
    .nav-user i.user-icon{font-size:16px;color:var(--primary);transition:transform .3s ease}
    .nav-user:hover i.user-icon{transform:scale(1.2)}
    .nav-user i.arrow{font-size:11px;color:#8E8E93;transition:.3s cubic-bezier(.34,1.56,.64,1)}
    .nav-user-container:hover i.arrow{transform:rotate(180deg);color:var(--primary)}
    .dropdown-menu{position:absolute;top:85%;right:0;background:#16161a;min-width:220px;border-radius:12px;border:1px solid #2d2d33;padding:8px 0;display:none;z-index:1001;transform-origin:top right}
    .nav-user-container:hover .dropdown-menu{display:block;animation:fadeInUp .3s cubic-bezier(.16,1,.3,1) forwards}
    .dropdown-menu .user-info-header{padding:12px 20px;border-bottom:1px solid #2d2d33;margin-bottom:6px}
    .dropdown-menu .user-info-header .u-name{color:var(--white);font-size:14px;font-weight:700;display:block}
    .dropdown-menu .user-info-header .u-role{color:var(--text-gray);font-size:11px;text-transform:uppercase;letter-spacing:.5px;margin-top:2px;display:block}
    .dropdown-menu a{display:flex;align-items:center;gap:12px;padding:10px 20px;color:#c5c5ca;text-decoration:none;font-size:13px;font-weight:500;transition:all .25s cubic-bezier(.16,1,.3,1);position:relative;overflow:hidden}
    .dropdown-menu a::after{content:'';position:absolute;left:0;top:0;width:3px;height:100%;background:var(--primary);transform:scaleY(0);transition:transform .25s cubic-bezier(.16,1,.3,1)}
    .dropdown-menu a i{font-size:14px;width:16px;text-align:center;transition:transform .3s ease}
    .dropdown-menu a:hover{background:#222227;color:var(--primary);padding-left:28px}
    .dropdown-menu a:hover::after{transform:scaleY(1)}
    .dropdown-menu a:hover i{transform:scale(1.2)}
    .dropdown-menu a.logout:hover{color:#ff3b30}
    .dropdown-menu a.logout:hover::after{background:#ff3b30}
    .member-badge-nav{display:inline-flex;align-items:center;gap:6px;background:var(--green-lt);border:1px solid var(--green);color:var(--green);padding:4px 12px;border-radius:50px;font-size:11px;font-weight:700;margin-left:8px;animation:pulse 2s ease-in-out infinite}

    /* ═══ HERO SECTION ═══ */
    .hero { background: linear-gradient(135deg, #0B0B0C 0%, #1a1a2e 100%); padding: 60px 80px; display: flex; align-items: center; justify-content: space-between; gap: 40px; position: relative; overflow: hidden; }
    .hero::before { content: ''; position: absolute; right: -100px; top: -100px; width: 400px; height: 400px; border-radius: 50%; background: radial-gradient(circle, rgba(255,82,0,.15) 0%, transparent 70%); }
    .hero-left { max-width: 600px; position: relative; z-index: 1; }
    .hero-badge { display: inline-flex; align-items: center; gap: 8px; background: var(--primary); color: var(--white); padding: 8px 16px; border-radius: 50px; font-size: 13px; font-weight: 700; margin-bottom: 20px; }
    .hero-title { font-size: 42px; font-weight: 800; color: var(--white); line-height: 1.2; margin-bottom: 16px; }
    .hero-title span { color: var(--primary); }
    .hero-desc { color: #A0A0A5; font-size: 16px; line-height: 1.6; margin-bottom: 24px; }

    /* ═══ KERANJANG WIDGET ═══ */
    .cart-widget { background: var(--white); border-radius: 16px; padding: 28px; border: 1px solid #E5E5EA; position: relative; z-index: 1; min-width: 340px; max-width: 380px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    .cart-widget-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid #F2F2F7; }
    .cart-widget-icon { width: 48px; height: 48px; border-radius: 14px; background: var(--orange-lt); color: var(--orange); display: flex; align-items: center; justify-content: center; font-size: 20px; }
    .cart-widget-title { font-size: 16px; font-weight: 800; color: #1C1C1E; }
    .cart-widget-subtitle { font-size: 12px; color: #8E8E93; }
    .cart-items { max-height: 200px; overflow-y: auto; margin-bottom: 16px; }
    .cart-empty { text-align: center; padding: 24px 0; color: #8E8E93; font-size: 13px; }
    .cart-empty i { font-size: 32px; margin-bottom: 8px; display: block; opacity: 0.5; }
    .cart-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #F2F2F7; }
    .cart-item-info { flex: 1; }
    .cart-item-name { font-size: 13px; font-weight: 700; color: #1C1C1E; }
    .cart-item-qty { font-size: 11px; color: #8E8E93; }
    .cart-item-price { font-size: 13px; font-weight: 800; color: var(--shopee-orange); }
    .cart-item-remove { background: none; border: none; color: var(--red); cursor: pointer; font-size: 12px; margin-left: 8px; padding: 4px; border-radius: 4px; transition: 0.2s; }
    .cart-item-remove:hover { background: var(--red-lt); }
    .cart-total { display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 2px solid #F2F2F7; margin-bottom: 16px; }
    .cart-total-label { font-size: 14px; font-weight: 600; color: #1C1C1E; }
    .cart-total-value { font-size: 20px; font-weight: 800; color: var(--shopee-orange); }

    .btn-checkout { width: 100%; background: var(--orange); color: var(--white); border: none; padding: 14px; border-radius: 12px; font-size: 14px; font-weight: 700; cursor: pointer; transition: var(--transition-smooth); display: flex; align-items: center; justify-content: center; gap: 8px; position: relative; overflow: hidden; }
    .btn-checkout::before { content:''; position:absolute; top:50%; left:50%; width:0; height:0; background:rgba(255,255,255,.2); border-radius:50%; transform:translate(-50%,-50%); transition:width .6s,height .6s; }
    .btn-checkout:hover::before { width:400px; height:400px; }
    .btn-checkout:hover:not(:disabled) { background: var(--orange-hover); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255,90,31,0.3); }
    .btn-checkout:disabled { background: var(--muted); cursor: not-allowed; }

    /* ═══ ALAT GRID ═══ */
    .main-container { padding: 60px 80px; max-width: 1440px; margin: 0 auto; }
    .section-header { margin-bottom: 28px; }
    .section-title { font-size: 24px; font-weight: 800; color: #111; }
    .section-subtitle { font-size: 14px; color: #636366; margin-top: 4px; }

    .alat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px; margin-bottom: 60px; }
    .alat-card { background: var(--white); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; transition: var(--transition-smooth); position: relative; }
    .alat-card:hover { transform: translateY(-5px); box-shadow: 0 12px 32px rgba(0,0,0,0.08); border-color: var(--orange); }
    .alat-card-photo-wrap { position: relative; width: 100%; aspect-ratio: 1 / 1; background: #F8F9FA; overflow: hidden; }
    .alat-card-photo-wrap img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s cubic-bezier(0.16,1,0.3,1); }
    .alat-card:hover .alat-card-photo-wrap img { transform: scale(1.08); }
    .alat-card-photo-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); }
    .alat-card-photo-placeholder i { font-size: 48px; color: var(--primary); opacity: 0.5; }
    .alat-card-stok-badge { position: absolute; bottom: 12px; left: 12px; background: rgba(255,255,255,0.9); color: var(--text-primary); padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 800; border: 1px solid var(--border); }

    .alat-card-info { padding: 20px; }
    .alat-card-name { font-size: 15px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; min-height: 40px; }
    .alat-card-price { font-size: 20px; font-weight: 800; color: var(--orange); margin-bottom: 16px; }

    .alat-card-actions { display: flex; gap: 8px; align-items: center; }
    .qty-input { width: 60px; padding: 10px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 14px; font-weight: 700; text-align: center; font-family: inherit; outline: none; transition: .2s; }
    .qty-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-glow); }

    .btn-add-cart { flex: 1; background: var(--orange-lt); color: var(--orange); border: 1px solid var(--orange); padding: 10px 14px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; transition: var(--transition-smooth); display: flex; align-items: center; justify-content: center; gap: 6px; position: relative; overflow: hidden; }
    .btn-add-cart:hover { background: var(--orange); color: #fff; }

    /* ═══ MODAL OVERLAY & CARD ═══ */
    .booking-modal-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,.6); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:2000; padding:20px; animation:fadeInModal .25s ease-out forwards; }
    .summary-card { background:#fff; border-radius:20px; padding:30px; width:100%; max-width:500px; max-height:90vh; overflow-y:auto; position:relative; box-shadow:0 20px 40px rgba(0,0,0,.15); animation:slideInModal .3s cubic-bezier(.16,1,.3,1) forwards; }
    .summary-card::-webkit-scrollbar { display:none; }
    .booking-modal-close { position:absolute; top:20px; right:20px; background:var(--border-lt); border:none; width:32px; height:32px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; color:var(--text-secondary); transition:var(--transition-smooth); z-index:10; }
    .booking-modal-close:hover { background:var(--red-lt); color:var(--red); transform:rotate(90deg); }

    .summary-title { font-family:'Barlow Condensed',sans-serif; font-size:16px; font-weight:800; letter-spacing:.5px; color:var(--muted); margin-bottom:20px; text-transform:uppercase; text-align:center; }

    /* Checkout Items List */
    .checkout-items-list { background: var(--bg); border-radius: 12px; padding: 16px; margin-bottom: 20px; max-height: 200px; overflow-y: auto; border: 1px solid var(--border); }
    .checkout-item-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px solid var(--border-lt); transition: var(--transition-smooth); }
    .checkout-item-row:hover { transform: translateX(4px); }
    .checkout-item-row:last-child { border-bottom: none; }
    .checkout-item-title { font-size: 13px; font-weight: 700; color: var(--text-primary); }
    .checkout-item-sub { font-size: 11px; color: var(--muted); margin-top: 2px; }
    .checkout-item-price { font-size: 13px; font-weight: 800; color: var(--text-primary); }

    .pricing-breakdown { border-top:1px solid var(--border-lt); padding:16px 0; display:flex; flex-direction:column; gap:10px; }
    .price-row { display:flex; justify-content:space-between; font-size:12.5px; color:var(--text-secondary); font-weight:500; transition:var(--transition-smooth); }
    .price-row:hover { transform:translateX(5px); }
    .price-row.total-row { margin-top:6px; font-size:14px; color:var(--text-primary); font-weight:800; align-items:center; }
    .price-row.total-row .total-amount { font-size:24px; color:var(--orange); font-weight:900; animation:countUp .5s ease-out; }

    /* Payment Methods */
    .payment-section { border-top:1px solid var(--border-lt); padding:20px 0 10px; }
    .payment-header { font-size:12.5px; font-weight:700; color:var(--text-primary); margin-bottom:12px; display:flex; align-items:center; gap:6px; }
    .payment-header i { color:var(--muted); }
    .payment-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
    .payment-card { border:1px solid var(--border); border-radius:10px; padding:12px; cursor:pointer; display:flex; align-items:center; gap:10px; transition:var(--transition-smooth); user-select:none; position:relative; overflow:hidden; }
    .payment-card::before { content:''; position:absolute; top:50%; left:50%; width:0; height:0; background:rgba(255,90,31,.1); border-radius:50%; transform:translate(-50%,-50%); transition:width .4s,height .4s; }
    .payment-card:hover::before { width:200px; height:200px; }
    .payment-card:hover { border-color:var(--orange); transform:translateY(-2px); box-shadow:0 4px 12px rgba(255,90,31,.1); }
    .payment-card.selected { border-color:var(--orange); background:var(--orange-lt); }
    .custom-radio { width:16px; height:16px; border-radius:50%; border:1.5px solid var(--muted); display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:.2s; }
    .payment-card.selected .custom-radio { border-color:var(--orange); }
    .custom-radio::after { content:''; width:8px; height:8px; border-radius:50%; background:var(--orange); display:none; }
    .payment-card.selected .custom-radio::after { display:block; animation:scaleIn .2s ease-out; }
    .payment-card-content { display:flex; flex-direction:column; justify-content:center; }
    .payment-name { font-size:11px; font-weight:700; color:var(--text-primary); line-height:1.3; }
    .payment-sub { font-size:9px; color:var(--muted); margin-top:1px; font-weight:500; }
    .qris-logo { font-family:'Barlow Condensed',sans-serif; font-weight:900; font-size:14px; color:#000; letter-spacing:-.5px; }

    .btn-booking { width:100%; background:var(--orange); color:#fff; border:none; border-radius:12px; padding:14px; font-family:inherit; font-size:14px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; margin-top:16px; transition:var(--transition-smooth); position:relative; overflow:hidden; }
    .btn-booking::before { content:''; position:absolute; top:50%; left:50%; width:0; height:0; background:rgba(255,255,255,.2); border-radius:50%; transform:translate(-50%,-50%); transition:width .6s,height .6s; }
    .btn-booking:hover::before { width:400px; height:400px; }
    .btn-booking:hover:not(:disabled) { background:var(--orange-hover); transform:translateY(-2px); box-shadow:0 10px 30px rgba(255,90,31,.4); }
    .btn-booking:disabled { background: var(--muted); cursor: not-allowed; }
    .booking-disclaimer { display:flex; align-items:center; justify-content:center; gap:6px; font-size:11px; color:var(--muted); margin-top:10px; font-weight:500; }
    .booking-disclaimer i { color:var(--green); animation:pulse 2s ease-in-out infinite; }

    /* Payment Instructions */
    .instruction-title { font-family:'Barlow Condensed',sans-serif; font-size:16px; font-weight:800; letter-spacing:.5px; color:var(--muted); margin-bottom:20px; text-transform:uppercase; text-align:center; }
    .switch-tabs-row { display:flex; gap:6px; margin-bottom:16px; background:var(--border-lt); padding:4px; border-radius:10px; border:1px solid var(--border); }
    .switch-tab-btn { flex:1; padding:10px; border:none; border-radius:8px; font-family:inherit; font-size:12px; font-weight:700; cursor:pointer; transition:var(--transition-smooth); background:transparent; color:var(--text-secondary); }
    .switch-tab-btn.active { background:#fff; color:var(--orange); box-shadow:0 2px 6px rgba(0,0,0,.05); }

    .countdown-box { background:var(--orange-lt); border:1px solid rgba(255,90,31,.15); border-radius:10px; padding:12px 16px; display:flex; align-items:center; justify-content:center; gap:12px; margin-bottom:20px; }
    .countdown-box i { color:var(--orange); animation:pulse 2s infinite; }
    .countdown-text { color:var(--orange-hover); font-weight:700; font-size:12px; }

    .total-display-box { background:var(--bg); padding:14px 18px; border-radius:12px; margin-bottom:20px; border:1px solid var(--border); text-align:center; }
    .total-display-label { font-size:11px; color:var(--text-secondary); font-weight:600; text-transform:uppercase; }
    .total-display-amount { font-size:24px; color:var(--orange); font-weight:900; margin-top:4px; }

    .instr-va-box { text-align:left; }
    .instr-qris-box { display:none; align-items:center; flex-direction:column; }
    .instr-qris-box.active { display:flex; }

    .bank-info-card { background:linear-gradient(135deg,#f8fafc 0%,#f1f5f9 100%); border:1px solid var(--border); border-radius:14px; padding:20px; margin-bottom:20px; text-align:left; position:relative; overflow:hidden; }
    .bank-info-card::before { content:''; position:absolute; top:-20px; right:-20px; width:80px; height:80px; background:rgba(255,82,0,.05); border-radius:50%; }
    .bank-header { display:flex; align-items:center; gap:12px; margin-bottom:16px; }
    .bank-icon { width:44px; height:44px; background:var(--primary); border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:18px; box-shadow:0 4px 12px rgba(255,82,0,.3); }
    .bank-title { font-size:13px; font-weight:800; color:var(--text-primary); }
    .bank-sub { font-size:11px; color:var(--muted); font-weight:500; }

    .va-section-label { font-size:11.5px; font-weight:700; color:var(--text-secondary); margin-bottom:10px; text-transform:uppercase; letter-spacing:.5px; }
    .va-input-row { display:flex; gap:8px; }
    .va-input-wrap { flex:1; background:#fff; border:2px solid var(--border); border-radius:12px; padding:14px 16px; display:flex; align-items:center; gap:10px; transition:var(--transition-smooth); }
    .va-input-wrap:hover { border-color:var(--orange); box-shadow:0 4px 16px rgba(255,82,0,.1); }
    .va-input-wrap i { color:var(--orange); font-size:14px; }
    .va-input-wrap input { border:none; background:transparent; font-weight:800; text-align:center; font-size:18px; letter-spacing:2px; color:var(--text-primary); font-family:'Plus Jakarta Sans',monospace; width:100%; outline:none; }
    .btn-copy-va { border-radius:12px; font-size:13px; padding:14px 18px; display:flex; align-items:center; gap:6px; white-space:nowrap; background:var(--primary); color:#fff; border:none; font-weight:700; box-shadow:0 4px 12px rgba(255,82,0,.3); cursor:pointer; transition:var(--transition-smooth); font-family:inherit; }
    .btn-copy-va:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(255,82,0,.4); }

    .steps-label { font-size:11.5px; font-weight:700; color:var(--text-secondary); margin-bottom:14px; text-transform:uppercase; letter-spacing:.5px; margin-top:20px; }
    .step-item { display:flex; gap:14px; align-items:flex-start; padding:14px 16px; background:#fafafa; border-radius:12px; border:1px solid var(--border-lt); transition:var(--transition-smooth); margin-bottom:12px; }
    .step-item:hover { background:#fff; border-color:var(--orange); transform:translateX(4px); }
    .step-item:last-child { margin-bottom:0; }
    .step-num { width:28px; height:28px; background:var(--orange-lt); color:var(--primary); border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:800; flex-shrink:0; margin-top:2px; }
    .step-title { font-size:13px; font-weight:700; color:var(--text-primary); margin-bottom:2px; }
    .step-desc { font-size:12px; color:var(--text-secondary); line-height:1.5; }

    .qris-title { font-size:12.5px; font-weight:700; color:var(--text-primary); margin-bottom:12px; }
    .qris-img-wrap { background:#fff; padding:12px; border:1px solid var(--border); border-radius:12px; width:fit-content; margin-bottom:16px; box-shadow:0 4px 12px rgba(0,0,0,.05); }
    .qris-img { display:block; width:170px; height:180px; object-fit:contain; }
    .qris-steps-list { text-align:left; font-size:11.5px; color:var(--text-secondary); padding-left:20px; line-height:1.6; display:flex; flex-direction:column; gap:6px; width:100%; }

    .modal-divider { border:none; height:1px; background:var(--border-lt); margin:20px 0; }
    .btn-done-pay { width:100%; background:var(--orange); color:#fff; border:none; border-radius:12px; padding:14px; font-family:inherit; font-size:14px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; transition:var(--transition-smooth); position:relative; overflow:hidden; }
    .btn-done-pay::before { content:''; position:absolute; top:50%; left:50%; width:0; height:0; background:rgba(255,255,255,.2); border-radius:50%; transform:translate(-50%,-50%); transition:width .6s,height .6s; }
    .btn-done-pay:hover::before { width:400px; height:400px; }
    .btn-done-pay:hover:not(:disabled) { background:var(--orange-hover); transform:translateY(-2px); box-shadow:0 10px 30px rgba(255,90,31,.4); }
    .btn-done-pay:disabled { background: var(--muted); cursor: not-allowed; }

    /* ---- FOOTER ---- */
    footer { background:var(--dark-bg); color:#8E8E93; padding:80px 80px 40px; border-top:1px solid #1C1C1E; position:relative; overflow:hidden; }
    footer::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,var(--primary),transparent); animation:shimmer 3s linear infinite; background-size:200% 100%; }
    .footer-grid { display:grid; grid-template-columns:1.5fr 1fr 1fr 1.2fr; gap:40px; margin-bottom:60px; }
    .footer-logo { display:flex; align-items:center; gap:10px; margin-bottom:16px; transition:transform .3s ease; }
    .footer-logo:hover { transform:scale(1.05); }
    .footer-logo img { height:70px; transition:transform .5s ease; }
    .footer-logo:hover img { transform:rotate(5deg); }
    .footer-logo span { color:var(--white); font-size:20px; font-weight:800; }
    .footer-desc { font-size:13px; line-height:1.6; margin-bottom:24px; }
    .social-links { display:flex; gap:12px; }
    .social-btn { width:36px; height:36px; border-radius:50%; background:#1C1C1E; color:var(--white); display:flex; align-items:center; justify-content:center; text-decoration:none; transition:all .3s cubic-bezier(.34,1.56,.64,1); }
    .social-btn:hover { background:var(--primary); transform:translateY(-3px) scale(1.1); box-shadow:0 8px 20px rgba(255,82,0,.3); }
    .footer-col h4 { color:var(--white); font-size:15px; font-weight:700; margin-bottom:20px; position:relative; display:inline-block; }
    .footer-col h4::after { content:''; position:absolute; bottom:-4px; left:0; width:30px; height:2px; background:var(--primary); transition:width .3s ease; }
    .footer-col:hover h4::after { width:100%; }
    .footer-col ul { list-style:none; }
    .footer-col ul li { margin-bottom:12px; }
    .footer-col ul li a { color:#8E8E93; text-decoration:none; font-size:13px; transition:all .3s ease; display:inline-block; position:relative; }
    .footer-col ul li a:hover { color:var(--white); transform:translateX(5px); }
    .contact-item { display:flex; gap:12px; font-size:13px; line-height:1.5; margin-bottom:16px; transition:var(--transition-smooth); padding:4px; border-radius:6px; }
    .contact-item:hover { background:rgba(255,82,0,.05); transform:translateX(5px); }
    .contact-item i { color:var(--primary); font-size:14px; margin-top:3px; transition:transform .3s ease; }
    .contact-item:hover i { transform:scale(1.2); }
    .footer-bottom { border-top:1px solid #1C1C1E; padding-top:30px; text-align:center; font-size:13px; }

    .swal-toast { border-radius: 12px !important; font-family: 'Plus Jakarta Sans', sans-serif !important; }

    @media(max-width: 1100px) {
        .hero { flex-direction: column; padding: 40px; }
        .cart-widget { min-width: auto; width: 100%; max-width: none; }
        .main-container { padding: 40px; }
        nav { padding: 0 40px; }
        .alat-grid { grid-template-columns: repeat(2, 1fr); }
        .footer-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media(max-width: 768px) {
        .nav-links { display: none; }
        .main-container { padding: 20px; }
        nav { padding: 0 20px; height: auto; flex-direction: column; gap: 15px; padding: 15px 20px; }
        .hero { padding: 30px 20px; }
        .hero-title { font-size: 28px; }
        .alat-grid { grid-template-columns: 1fr; }
        .footer-grid { grid-template-columns: 1fr; }
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
        <a href="booking_customer.php">Booking</a>
        <a href="pembatalan_customer.php">Pembatalan</a>
        <a href="langganan_customer.php">Member</a>
        <a href="pembelian_alat.php" class="active">Pembelian</a>
    </div>

    <div class="nav-user-container">
        <div class="nav-user">
            <?php if (!empty($photo_profile) && file_exists(resolvePhotoPath($photo_profile))): ?>
                <img src="<?php echo htmlspecialchars(resolvePhotoPath($photo_profile)); ?>" alt="Avatar" class="user-avatar">
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
            <div style="height:1px; background:#2d2d33; margin:6px 0;"></div>
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
            Lanjut Pembayaran <i class="fa-solid fa-arrow-right"></i>
        </button>
    </div>
</section>

<!-- MAIN CONTENT -->
<main class="main-container">
    <section>
        <div class="section-header">
            <h2 class="section-title"><i class="fa-solid fa-basketball" style="color:var(--primary)"></i> Daftar Alat</h2>
            <p class="section-subtitle">Pilih perlengkapan basket yang Anda butuhkan.</p>
        </div>

        <div class="alat-grid" id="alatGrid">
            <?php foreach ($alat_list as $alat): 
                $photo_url = resolvePhotoPath($alat['Photo_Alat']);
            ?>
            <div class="alat-card" data-id="<?php echo $alat['ID_Alat']; ?>">
                <div class="alat-card-photo-wrap">
                    <?php if (!empty($photo_url) && @file_exists($photo_url)): ?>
                        <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="<?php echo htmlspecialchars($alat['Nama_Alat']); ?>">
                    <?php else: ?>
                        <div class="alat-card-photo-placeholder">
                            <i class="fa-solid fa-toolbox"></i>
                        </div>
                    <?php endif; ?>
                    <span class="alat-card-stok-badge">
                        Tersedia: <?php echo intval($alat['Stok']); ?>
                    </span>
                </div>
                <div class="alat-card-info">
                    <div class="alat-card-name"><?php echo htmlspecialchars($alat['Nama_Alat']); ?></div>
                    <div class="alat-card-price"><?php echo 'Rp ' . number_format($alat['Harga_Alat'], 0, ',', '.'); ?></div>
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
</main>

<!-- FOOTER -->
<footer>
    <div class="footer-grid">
        <div>
            <div class="footer-logo"><img src="../asset/image/logo.png" alt="HoopBall"></div>
            <p class="footer-desc">HoopBall adalah platform penyewaan lapangan basket online yang mudah, cepat, dan terpercaya.</p>
            <div class="social-links">
                <a href="#" class="social-btn"><i class="fa-brands fa-instagram"></i></a>
                <a href="#" class="social-btn"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" class="social-btn"><i class="fa-brands fa-youtube"></i></a>
            </div>
        </div>
        <div class="footer-col">
            <h4>Navigasi</h4>
            <ul>
                <li><a href="view_customer.php">Beranda</a></li>
                <li><a href="booking_customer.php">Booking</a></li>
                <li><a href="langganan_customer.php">Member</a></li>
                <li><a href="pembelian_alat.php">Pembelian</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Informasi</h4>
            <ul>
                <li><a href="#">Cara Pemesanan</a></li>
                <li><a href="#">Syarat & Ketentuan</a></li>
                <li><a href="#">FAQ</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Hubungi Kami</h4>
            <div class="contact-item"><i class="fa-solid fa-location-dot"></i>Jl. Olahraga No. 10, Jakarta Selatan</div>
            <div class="contact-item"><i class="fa-solid fa-phone"></i>+62 812-3456-7890</div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; 2025 HoopBall. All rights reserved.</p>
    </div>
</footer>

<!-- MODAL 1: RINGKASAN CHECKOUT -->
<div class="booking-modal-overlay" id="checkoutModal">
    <div class="summary-card">
        <button class="booking-modal-close" onclick="closeCheckoutModal()"><i class="fa-solid fa-xmark"></i></button>
        <h2 class="summary-title">Ringkasan Pesanan</h2>

        <div class="checkout-items-list" id="checkoutItemsList">
            <!-- Items di-render via JS -->
        </div>

        <div class="pricing-breakdown">
            <div class="price-row total-row">
                <span>Total Pembayaran</span>
                <span class="total-amount" id="checkoutTotalAmount">Rp 0</span>
            </div>
        </div>

        <div class="payment-section">
            <div class="payment-header"><i class="fa-solid fa-wallet"></i> Metode Pembayaran</div>
            <div class="payment-grid">
                <div class="payment-card selected" data-method="Transfer Bank" onclick="selectPaymentMethod('Transfer Bank', this)">
                    <div class="custom-radio"></div>
                    <div class="payment-card-content">
                        <span class="payment-name">Transfer Bank</span>
                        <span class="payment-sub">Virtual Account</span>
                    </div>
                </div>
                <div class="payment-card" data-method="QRIS" onclick="selectPaymentMethod('QRIS', this)">
                    <div class="custom-radio"></div>
                    <div class="payment-card-content">
                        <span class="payment-name qris-logo">QRIS</span>
                        <span class="payment-sub">Scan & Bayar Instan</span>
                    </div>
                </div>
            </div>
        </div>

        <button class="btn-booking" id="btnProceedPayment" onclick="proceedToPayment()">
            <i class="fa-solid fa-lock"></i> Lanjutkan Pembayaran
        </button>
        <div class="booking-disclaimer"><i class="fa-solid fa-circle-check"></i> Enkripsi data aman terverifikasi</div>
    </div>
</div>

<!-- MODAL 2: INSTRUKSI PEMBAYARAN -->
<div class="booking-modal-overlay" id="paymentInstructionModal">
    <div class="summary-card" style="max-width:460px;text-align:center">
        <button class="booking-modal-close" onclick="closeInstructionModal()"><i class="fa-solid fa-xmark"></i></button>
        <p class="instruction-title">Instruksi Pembayaran</p>
        <div class="switch-tabs-row">
            <button id="btnSwitchVA" class="switch-tab-btn active" onclick="showPaymentMethodInstructions('Transfer Bank')">
                <i class="fa-solid fa-university" style="margin-right:4px"></i> Virtual Account
            </button>
            <button id="btnSwitchQRIS" class="switch-tab-btn" onclick="showPaymentMethodInstructions('QRIS')">
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
                    <div><div class="bank-title">Virtual Account</div><div class="bank-sub">Mandiri / BCA / BNI / BRI</div></div>
                </div>
                <div class="va-section-label">Nomor Virtual Account</div>
                <div class="va-input-row">
                    <div class="va-input-wrap"><i class="fa-solid fa-hashtag"></i><input type="text" id="vaNumber" value="8801281234567890" readonly></div>
                    <button class="btn-copy-va" id="btnCopyVA" onclick="copyVA()"><i class="fa-regular fa-copy"></i> Salin</button>
                </div>
            </div>
            <div class="steps-label">Cara Pembayaran</div>
            <div class="step-item"><div class="step-num">1</div><div><div class="step-title">Buka Aplikasi Banking</div><div class="step-desc">Pilih menu <strong style="color:var(--primary)">Transfer &gt; Virtual Account</strong> pada M-Banking atau ATM Anda.</div></div></div>
            <div class="step-item"><div class="step-num">2</div><div><div class="step-title">Masukkan Nomor VA</div><div class="step-desc">Masukkan nomor Virtual Account <strong style="color:var(--primary)">8801281234567890</strong>.</div></div></div>
            <div class="step-item" style="margin-bottom:0"><div class="step-num">3</div><div><div class="step-title">Konfirmasi Pembayaran</div><div class="step-desc">Nominal akan otomatis muncul sesuai total tagihan. Konfirmasi dan selesaikan transaksi.</div></div></div>
        </div>

        <div id="instruksiQRIS" class="instr-qris-box">
            <div class="qris-title">Pindai Kode QRIS Resmi HoopBall</div>
            <div class="qris-img-wrap"><img id="qrisImage" src="" alt="QRIS Code" class="qris-img"></div>
            <ul class="qris-steps-list">
                <li>Buka aplikasi e-wallet (GoPay, OVO, Dana, LinkAja) atau Mobile Banking.</li>
                <li>Pilih opsi <strong>Scan / Bayar QRIS</strong>.</li>
                <li>Arahkan kamera ke kode QR, lalu selesaikan pembayaran.</li>
            </ul>
        </div>

        <hr class="modal-divider">
        <button class="btn-done-pay" id="btnDonePayment">
            Saya Sudah Bayar <i class="fa-solid fa-circle-check"></i>
        </button>
    </div>
</div></div>
</div>

<script>
// ============================================================================
// STATE & KERANJANG BELANJA
// ============================================================================
let cart = [];
let checkoutTotalValue = 0;
let selectedPaymentMethod = 'Transfer Bank';
let countdownInterval;

function formatRupiah(angka) {
    return 'Rp ' + angka.toLocaleString('id-ID');
}

function addToCart(idAlat, namaAlat, harga, maxStok) {
    const qtyInput = document.getElementById('qty_' + idAlat);
    const qty = parseInt(qtyInput.value) || 1;

    if (qty <= 0 || qty > maxStok) {
        Swal.fire({ icon: 'warning', title: 'Jumlah Tidak Valid', text: 'Jumlah maksimal: ' + maxStok, confirmButtonColor: '#FF5200' });
        return;
    }

    const existingIndex = cart.findIndex(item => item.id_alat === idAlat);

    if (existingIndex >= 0) {
        const newQty = cart[existingIndex].jumlah + qty;
        if (newQty > maxStok) {
            Swal.fire({ icon: 'warning', title: 'Stok Terbatas', text: 'Total barang di keranjang melebihi stok (' + maxStok + ')', confirmButtonColor: '#FF5200' });
            return;
        }
        cart[existingIndex].jumlah = newQty;
        cart[existingIndex].subtotal = newQty * harga;
    } else {
        cart.push({ id_alat: idAlat, nama_alat: namaAlat, harga: harga, jumlah: qty, subtotal: qty * harga });
    }

    updateCartUI();

    Swal.fire({
        icon: 'success', title: 'Ditambahkan!', text: namaAlat + ' (' + qty + 'x)',
        timer: 1500, showConfirmButton: false, toast: true, position: 'top-end',
        background: '#ffffff', color: '#1c1c1e', iconColor: '#34C759', customClass: { popup: 'swal-toast' }
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
        cartItems.innerHTML = `<div class="cart-empty"><i class="fa-solid fa-cart-plus"></i>Keranjang kosong</div>`;
        cartCount.textContent = '0 item';
        cartTotal.textContent = 'Rp 0';
        btnCheckout.disabled = true;
        checkoutTotalValue = 0;
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
                <button class="cart-item-remove" onclick="removeFromCart(${index})" title="Hapus"><i class="fa-solid fa-trash"></i></button>
            </div>
        `;
    });

    checkoutTotalValue = total;
    cartItems.innerHTML = html;
    const totalItems = cart.reduce((sum, item) => sum + item.jumlah, 0);
    cartCount.textContent = totalItems + ' item' + (totalItems > 1 ? 's' : '');
    cartTotal.textContent = formatRupiah(total);
    btnCheckout.disabled = false;
}

// ============================================================================
// MODAL 1: CHECKOUT SUMMARY
// ============================================================================
function openCheckoutModal() {
    if (cart.length === 0) return;

    let html = '';
    cart.forEach(item => {
        html += `
        <div class="checkout-item-row">
            <div>
                <div class="checkout-item-title">${item.nama_alat}</div>
                <div class="checkout-item-sub">${item.jumlah}x @ ${formatRupiah(item.harga)}</div>
            </div>
            <div class="checkout-item-price">${formatRupiah(item.subtotal)}</div>
        </div>`;
    });

    document.getElementById('checkoutItemsList').innerHTML = html;
    document.getElementById('checkoutTotalAmount').textContent = formatRupiah(checkoutTotalValue);

    document.getElementById('checkoutModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function closeCheckoutModal() {
    document.getElementById('checkoutModal').style.display = 'none';
    document.body.style.overflow = '';
}

function selectPaymentMethod(method, element) {
    document.querySelectorAll('.payment-card').forEach(el => el.classList.remove('selected'));
    element.classList.add('selected');
    selectedPaymentMethod = method;
}

// ============================================================================
// MODAL 2: INSTRUKSI PEMBAYARAN
// ============================================================================
function proceedToPayment() {
    closeCheckoutModal();
    document.getElementById('paymentTotalAmount').textContent = formatRupiah(checkoutTotalValue);
    showPaymentMethodInstructions(selectedPaymentMethod);

    document.getElementById('paymentInstructionModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    startPaymentCountdown(15 * 60);
}

function closeInstructionModal() {
    document.getElementById('paymentInstructionModal').style.display = 'none';
    document.body.style.overflow = '';
    clearInterval(countdownInterval);
}

function showPaymentMethodInstructions(method) {
    selectedPaymentMethod = method;
    const va = document.getElementById('instruksiTransfer');
    const qris = document.getElementById('instruksiQRIS');
    const btnVA = document.getElementById('btnSwitchVA');
    const btnQR = document.getElementById('btnSwitchQRIS');
    if(method === 'Transfer Bank'){
        btnVA.classList.add('active'); btnQR.classList.remove('active');
        va.style.display = 'block'; qris.style.display = 'none';
    } else {
        btnQR.classList.add('active'); btnVA.classList.remove('active');
        va.style.display = 'none'; qris.style.display = 'flex';
        const total = document.getElementById('paymentTotalAmount').innerText.replace(/[^0-9]/g, '');
        document.getElementById('qrisImage').src = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent('HOOPBALL-PAYMENT-ALAT-'+total)}`;
    }
}

function startPaymentCountdown(duration) {
    clearInterval(countdownInterval);
    let timer = duration;
    const display = document.getElementById('paymentCountdown');
    const btn = document.getElementById('btnDonePayment');
    btn.disabled = false;
    btn.innerHTML = 'Saya Sudah Bayar <i class="fa-solid fa-circle-check"></i>';

    countdownInterval = setInterval(() => {
        const m = String(Math.floor(timer / 60)).padStart(2, '0');
        const s = String(timer % 60).padStart(2, '0');
        display.textContent = `${m}:${s}`;

        if (--timer < 0) {
            clearInterval(countdownInterval);
            display.textContent = 'Waktu Habis';
            btn.disabled = true;
            btn.innerHTML = 'Waktu Habis <i class="fa-solid fa-clock"></i>';
        }
    }, 1000);
}

function copyVA() {
    const v = document.getElementById('vaNumber');
    v.select(); v.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(v.value).then(() => {
        Swal.fire({ icon: 'success', title: 'Berhasil Disalin!', text: 'Nomor VA disalin ke papan klip.', confirmButtonColor: '#FF5200', confirmButtonText: 'OK' });
    });
}

// AJAX SUBMIT FINAL PAYMENT
document.getElementById('btnDonePayment').addEventListener('click', function() {
    if (cart.length === 0) return;

    const btn = document.getElementById('btnDonePayment');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';
    btn.disabled = true;

    const payload = {
        cart_data: cart,
        metode_pembayaran: selectedPaymentMethod,
        total_bayar: checkoutTotalValue
    };

    fetch('pembelian_alat.php?action=checkout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(response => response.json())
    .then(data => {
        closeInstructionModal();
        if (data.success) {
            Swal.fire({
                icon: 'success', title: 'Pembayaran Diterima!',
                text: 'Terima kasih, pembayaran pesanan alat Anda sedang menunggu konfirmasi admin.',
                confirmButtonColor: '#FF5200', confirmButtonText: 'Selesai'
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire({ icon: 'error', title: 'Gagal', text: data.message, confirmButtonColor: '#FF5200', confirmButtonText: 'Coba Lagi' });
            btn.innerHTML = 'Coba Lagi <i class="fa-solid fa-rotate-right"></i>';
            btn.disabled = false;
        }
    })
    .catch(error => {
        Swal.fire({ icon: 'error', title: 'Koneksi Terputus', text: 'Gagal menghubungi server. Periksa koneksi internet Anda.', confirmButtonColor: '#FF5200', confirmButtonText: 'Coba Lagi' });
        btn.innerHTML = 'Coba Lagi <i class="fa-solid fa-rotate-right"></i>';
        btn.disabled = false;
    });
});

// Close modals when clicking outside
window.addEventListener('click', function(e) {
    const cModal = document.getElementById('checkoutModal');
    const pModal = document.getElementById('paymentInstructionModal');
    if (e.target === cModal) closeCheckoutModal();
    if (e.target === pModal) closeInstructionModal();
});
</script>

</body>
</html>