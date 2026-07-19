<?php
// ============================================================================
// BUFFER OUTPUT & SESSION
// ============================================================================
ob_start();
require_once '../login/auth_check.php';
$path_prefix = "../";
include '../includes/config.php';

// ============================================================================
// CEK AKSES
// ============================================================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../login/login.php");
    exit();
}

$id_customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';
$nama_customer = 'Pelanggan'; // default, di-override dari DB di bawah

// ============================================================================
// ⚠️ PANGGIL SENSOR AUTO LOGOUT IDLE DI SINI ⚠️
// ============================================================================
// Gunakan awalan '../' karena file ini sedang dipanggil dari dalam folder customer
require_once '../login/auto_logout.php';
// ============================================================================

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

// Ambil nama asli dari DB (kolom Nama_Customer) — konsisten dengan halaman Member.
$nama_customer = $customer_data['Nama_Customer'] ?? 'Pelanggan';
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

    // NOTE: request sekarang multipart/form-data (bukan JSON body lagi),
    // karena harus ikut kirim file bukti pembayaran.
    $cart = json_decode($_POST['cart_data'] ?? '[]', true) ?: [];
    $metode = htmlspecialchars($_POST['metode_pembayaran'] ?? '');
    $total = floatval($_POST['total_bayar'] ?? 0);

    if (empty($cart) || empty($metode) || $total <= 0) {
        echo json_encode(['success' => false, 'message' => 'Data pesanan tidak valid.']);
        exit();
    }

    // ------------------------------------------------------------------
    // VALIDASI WAJIB: Bukti Pembayaran (foto/image)
    // ------------------------------------------------------------------
    if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK || empty($_FILES['bukti_pembayaran']['name'])) {
        echo json_encode(['success' => false, 'message' => 'Bukti pembayaran wajib diupload (foto/screenshot transfer).']);
        exit();
    }

    $bukti_file = $_FILES['bukti_pembayaran'];
    $bukti_ext = strtolower(pathinfo($bukti_file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
    if (!in_array($bukti_ext, $allowed_ext)) {
        echo json_encode(['success' => false, 'message' => 'Bukti pembayaran harus berupa foto (JPG, PNG, atau WEBP).']);
        exit();
    }
    if ($bukti_file['size'] > 5 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Ukuran foto bukti pembayaran maksimal 5MB.']);
        exit();
    }

    $bukti_upload_dir = '../asset/image/bukti_pembayaran/';
    if (!is_dir($bukti_upload_dir)) {
        @mkdir($bukti_upload_dir, 0755, true);
    }
    $bukti_filename = 'bukti_' . time() . '_' . uniqid() . '.' . $bukti_ext;
    if (!move_uploaded_file($bukti_file['tmp_name'], $bukti_upload_dir . $bukti_filename)) {
        echo json_encode(['success' => false, 'message' => 'Gagal menyimpan foto bukti pembayaran. Coba lagi.']);
        exit();
    }
    $bukti_pembayaran_path = 'asset/image/bukti_pembayaran/' . $bukti_filename;

    if (sqlsrv_begin_transaction($conn) === false) {
        echo json_encode(['success' => false, 'message' => 'Gagal menginisiasi transaksi database.']);
        exit();
    }

    try {
        // Kelompokkan item cart per ID_Alat (karena PK Detail_Beli_Alat = ID_Alat + ID_Beli,
        // satu alat dengan beberapa ukuran harus digabung jadi 1 baris detail).
        // $grouped[id_alat] = ['jumlah'=>N, 'sizes'=>['S'=>2,'M'=>1], 'subtotal'=>..]
        $grouped = [];
        $calculated_total = 0;

        foreach ($cart as $item) {
            $id_alat = intval($item['id_alat']);
            $jumlah  = intval($item['jumlah']);
            $ukuran  = trim($item['ukuran'] ?? 'All Size');
            if ($ukuran === '') $ukuran = 'All Size';
            if ($jumlah <= 0) continue;

            if (!isset($grouped[$id_alat])) {
                $grouped[$id_alat] = ['jumlah' => 0, 'sizes' => [], 'subtotal' => 0];
            }
            $grouped[$id_alat]['jumlah'] += $jumlah;
            $grouped[$id_alat]['sizes'][$ukuran] = ($grouped[$id_alat]['sizes'][$ukuran] ?? 0) + $jumlah;
        }

        // 1. Validasi stok (total per alat + stok per ukuran kalau ada)
        foreach ($grouped as $id_alat => $g) {
            $cek = sqlsrv_query($conn, "SELECT Nama_Alat, Stok, Harga_Jual FROM Alat WHERE ID_Alat = ? AND Status = 1 AND Is_Deleted = 0", array($id_alat));
            $alat_data = sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC);
            if (!$alat_data) throw new Exception("Salah satu alat tidak ditemukan.");
            if ($alat_data['Stok'] < $g['jumlah']) {
                throw new Exception("Stok " . $alat_data['Nama_Alat'] . " tidak mencukupi.");
            }

            // Validasi stok per ukuran (jika ukuran bukan 'All Size')
            foreach ($g['sizes'] as $uk => $qty) {
                if ($uk === 'All Size') continue;
                $cek_size = sqlsrv_query($conn, "SELECT Stok FROM Alat_Size WHERE ID_Alat = ? AND Ukuran = ?", array($id_alat, $uk));
                $size_row = $cek_size ? sqlsrv_fetch_array($cek_size, SQLSRV_FETCH_ASSOC) : null;
                if ($size_row && $size_row['Stok'] < $qty) {
                    throw new Exception("Stok " . $alat_data['Nama_Alat'] . " ukuran " . $uk . " tidak mencukupi (tersisa " . intval($size_row['Stok']) . ").");
                }
            }

            $subtotal = $alat_data['Harga_Jual'] * $g['jumlah'];
            $grouped[$id_alat]['subtotal'] = $subtotal;
            $calculated_total += $subtotal;
        }

        // 2. Insert ke Beli_Alat (Status = 0 -> Menunggu Konfirmasi Karyawan)
        $sql_beli = "INSERT INTO Beli_Alat (ID_Karyawan, ID_Customer, Tanggal_Beli, Metode_Pembayaran, Total_Bayar, Bukti_Pembayaran, Status, Created_By, Created_Date)
                     OUTPUT INSERTED.ID_Beli
                     VALUES (1, ?, GETDATE(), ?, ?, ?, 0, ?, GETDATE())"; // Default Karyawan 1 untuk online
        $stmt_beli = sqlsrv_query($conn, $sql_beli, array($id_customer, $metode, $calculated_total, $bukti_pembayaran_path, $nama_customer));
        if ($stmt_beli === false) throw new Exception("Gagal membuat data pesanan utama.");

        $row_id = sqlsrv_fetch_array($stmt_beli, SQLSRV_FETCH_ASSOC);
        $id_beli = $row_id['ID_Beli'];

        // 3. Insert detail (1 baris per alat) + update stok per ukuran (Alat_Size)
        foreach ($grouped as $id_alat => $g) {
            // Rangkai label ukuran, contoh: "S x2, M x1" atau "All Size"
            $size_parts = [];
            foreach ($g['sizes'] as $uk => $qty) $size_parts[] = $uk . ' x' . $qty;
            $ukuran_label = implode(', ', $size_parts);
            if (strlen($ukuran_label) > 15) {
                // Kolom hanya VARCHAR(15): kalau kepanjangan, simpan ringkas
                $ukuran_label = (count($g['sizes']) === 1) ? array_key_first($g['sizes']) : 'Multi';
            }

            $sql_detail = "INSERT INTO Detail_Beli_Alat (ID_Alat, ID_Beli, Jumlah, SubTotal, Ukuran) VALUES (?, ?, ?, ?, ?)";
            $stmt_detail = sqlsrv_query($conn, $sql_detail, array($id_alat, $id_beli, $g['jumlah'], $g['subtotal'], $ukuran_label));
            if ($stmt_detail === false) throw new Exception("Gagal menyimpan detail alat.");

            // CATATAN TIM: Stok Alat & Alat_Size TIDAK dipotong manual di sini.
            // Trigger trg_DetailBeliAlat_AutoUpdateStok (Transaksi_PembelianAlat_SP_TRG.sql)
            // otomatis motong Alat.Stok & Alat_Size.Stok begitu baris di atas ke-insert.
            // JANGAN tambahkan UPDATE Stok manual lagi di sini, nanti stok kepotong 2x.
        }

        sqlsrv_commit($conn);
        echo json_encode(['success' => true, 'message' => 'Pesanan berhasil dibuat! Menunggu konfirmasi karyawan.']);
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        // Hapus file bukti pembayaran yang sudah keburu keupload, biar gak jadi file nyampah
        if (!empty($bukti_pembayaran_path) && file_exists('../' . $bukti_pembayaran_path)) {
            @unlink('../' . $bukti_pembayaran_path);
        }
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

// ============================================================================
// AMBIL DATA ALAT YANG AKTIF & TERSEDIA
// ============================================================================
$alat_list = [];
$query_alat = sqlsrv_query($conn, 
    "SELECT ID_Alat, Nama_Alat, Kategori, Stok, Harga_Jual, Photo_Alat, Status 
     FROM Alat 
     WHERE Status = 1 AND Is_Deleted = 0 AND Stok > 0
     ORDER BY Nama_Alat ASC"
);
if ($query_alat) {
    while ($row = sqlsrv_fetch_array($query_alat, SQLSRV_FETCH_ASSOC)) {
        $alat_list[] = $row;
    }
}

// Ambil stok per ukuran untuk semua alat (buat pilihan size di kartu)
// Hasil: $sizes_by_alat[ID_Alat] = [ ['Ukuran'=>'S','Stok'=>5], ... ]
$sizes_by_alat = [];
$query_sizes = sqlsrv_query($conn,
    "SELECT s.ID_Alat, s.Ukuran, s.Stok
     FROM Alat_Size s
     INNER JOIN Alat a ON s.ID_Alat = a.ID_Alat
     WHERE a.Status = 1 AND a.Is_Deleted = 0 AND s.Stok > 0
     ORDER BY s.ID_Alat, s.ID_Alat_Size"
);
if ($query_sizes) {
    while ($row = sqlsrv_fetch_array($query_sizes, SQLSRV_FETCH_ASSOC)) {
        $sizes_by_alat[$row['ID_Alat']][] = ['Ukuran' => $row['Ukuran'], 'Stok' => intval($row['Stok'])];
    }
}


// ============================================================================
// AMBIL RIWAYAT TRANSAKSI PEMBELIAN ALAT CUSTOMER
// ============================================================================
$riwayat_beli = [];
$query_riwayat = sqlsrv_query($conn,
    "SELECT b.ID_Beli, b.Tanggal_Beli, b.Metode_Pembayaran, b.Total_Bayar, b.Bukti_Pembayaran, b.Status,
            k.Nama_Karyawan
     FROM Beli_Alat b
     LEFT JOIN Karyawan k ON b.ID_Karyawan = k.ID_Karyawan
     WHERE b.ID_Customer = ?
     ORDER BY b.Tanggal_Beli DESC",
    array($id_customer)
);
if ($query_riwayat) {
    while ($row = sqlsrv_fetch_array($query_riwayat, SQLSRV_FETCH_ASSOC)) {
        // Format tanggal
        if (isset($row['Tanggal_Beli']) && $row['Tanggal_Beli'] instanceof DateTime) {
            $row['Tanggal_Beli'] = $row['Tanggal_Beli']->format('Y-m-d H:i:s');
        }
        // Ambil detail item per transaksi
        $detail_items = [];
        $q_detail = sqlsrv_query($conn,
            "SELECT d.Jumlah, d.SubTotal, d.Ukuran, a.Nama_Alat, a.Photo_Alat
             FROM Detail_Beli_Alat d
             INNER JOIN Alat a ON d.ID_Alat = a.ID_Alat
             WHERE d.ID_Beli = ?",
            array($row['ID_Beli'])
        );
        if ($q_detail) {
            while ($d = sqlsrv_fetch_array($q_detail, SQLSRV_FETCH_ASSOC)) {
                $detail_items[] = $d;
            }
        }
        $row['detail_items'] = $detail_items;
        $riwayat_beli[] = $row;
    }
}

function getStatusLabel($status) {
    switch ($status) {
        case 0: return ['Menunggu Konfirmasi', 'var(--yellow)', 'var(--yellow-lt)', 'fa-clock'];
        case 1: return ['Dikonfirmasi', 'var(--green)', 'var(--green-lt)', 'fa-check-circle'];
        case 2: return ['Selesai', 'var(--blue)', 'var(--blue-lt)', 'fa-flag-checkered'];
        case 3: return ['Dibatalkan', 'var(--red)', 'var(--red-lt)', 'fa-times-circle'];
        default: return ['Tidak Diketahui', 'var(--muted)', '#F1F5F9', 'fa-question-circle'];
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
<?php include '../includes/favicon.php'; ?>
<title>Pembelian Alat | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Barlow+Condensed:wght@700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../asset/css/navbar_footer.css">
<link rel="stylesheet" href="../asset/css/responsive_pembelian.css">
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
    body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--light-bg);color:var(--text-primary);overflow-x:hidden;animation: fadeIn 0.5s ease-out}
    ::-webkit-scrollbar{display:none} html,body{-ms-overflow-style:none;scrollbar-width:none}
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
    @keyframes gradientShift { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
    @keyframes glowPulse { 0%, 100% { box-shadow: 0 0 20px rgba(255,82,0,0.3); } 50% { box-shadow: 0 0 40px rgba(255,82,0,0.6); } }
    @keyframes ballFloat1 { 0%,100%{transform:translate(0,0) rotate(0deg)} 25%{transform:translate(10px,-20px) rotate(5deg)} 50%{transform:translate(-5px,10px) rotate(-3deg)} 75%{transform:translate(15px,5px) rotate(2deg)} }
    @keyframes ballFloat2 { 0%,100%{transform:translate(0,0) rotate(0deg)} 25%{transform:translate(-15px,10px) rotate(-5deg)} 50%{transform:translate(10px,-15px) rotate(3deg)} 75%{transform:translate(-10px,-5px) rotate(-2deg)} }
    @keyframes ballFloat3 { 0%,100%{transform:translate(0,0) rotate(0deg)} 25%{transform:translate(20px,5px) rotate(3deg)} 50%{transform:translate(-15px,-20px) rotate(-5deg)} 75%{transform:translate(5px,15px) rotate(2deg)} }
    @keyframes heroGradient { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }
    @keyframes cardEnter { from { opacity: 0; transform: translateY(30px) scale(0.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes iconBounce { 0%, 100% { transform: scale(1); } 50% { transform: scale(1.15); } }
    @keyframes borderGlow { 0%, 100% { border-color: rgba(255,82,0,0.3); } 50% { border-color: rgba(255,82,0,0.8); } }
    @keyframes numberCount { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes slideInLeft { from { opacity: 0; transform: translateX(-30px); } to { opacity: 1; transform: translateX(0); } }
    @keyframes slideInRight { from { opacity: 0; transform: translateX(30px); } to { opacity: 1; transform: translateX(0); } }
    @keyframes rotateIn { from { opacity: 0; transform: rotate(-10deg) scale(0.9); } to { opacity: 1; transform: rotate(0deg) scale(1); } }
    @keyframes loaderBounce { from { transform: translateY(0); } to { transform: translateY(-20px); } }

    /* ═══ REVEAL ═══ */
    .reveal{opacity:0;transform:translateY(40px);transition:all 0.8s cubic-bezier(0.16,1,0.3,1)}
    .reveal.active{opacity:1;transform:translateY(0)}
    .reveal-stagger .stagger-item{opacity:0;transform:translateY(30px);transition:all 0.6s cubic-bezier(0.16,1,0.3,1)}
    .reveal-stagger.active .stagger-item{opacity:1;transform:translateY(0)}
    .reveal-stagger.active .stagger-item:nth-child(1){transition-delay:0s}
    .reveal-stagger.active .stagger-item:nth-child(2){transition-delay:.1s}
    .reveal-stagger.active .stagger-item:nth-child(3){transition-delay:.2s}
    .reveal-stagger.active .stagger-item:nth-child(4){transition-delay:.3s}
    .reveal-stagger.active .stagger-item:nth-child(5){transition-delay:.4s}
    .reveal-stagger.active .stagger-item:nth-child(6){transition-delay:.5s}

    /* ═══ SCROLL PROGRESS ═══ */
    .scroll-progress{position:fixed;top:0;left:0;height:3px;background:linear-gradient(90deg,var(--primary),#FF8C42);z-index:9999;transform-origin:left;transform:scaleX(0);transition:transform 0.1s ease-out}

    /* ═══ PAGE LOADER ═══ */
    .page-loader { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: #0B0B0C; display: flex; align-items: center; justify-content: center; gap: 8px; z-index: 99999; transition: opacity 0.5s ease, visibility 0.5s ease; }
    .page-loader.hidden { opacity: 0; visibility: hidden; pointer-events: none; }
    .loader-ball { width: 12px; height: 12px; border-radius: 50%; background: var(--primary); animation: loaderBounce 0.6s ease-in-out infinite alternate; }
    .loader-ball:nth-child(2) { animation-delay: 0.2s; }
    .loader-ball:nth-child(3) { animation-delay: 0.4s; }


    /* ═══ HERO SECTION ═══ */
    .hero { background: linear-gradient(135deg, #0B0B0C 0%, #1a1a2e 50%, #0d0d1a 100%); background-size: 200% 200%; animation: heroGradient 15s ease infinite; padding: 60px 80px; display: flex; align-items: center; justify-content: space-between; gap: 40px; position: relative; overflow: hidden; min-height: 400px; }
    .hero::before { content: ''; position: absolute; right: -100px; top: -100px; width: 400px; height: 400px; border-radius: 50%; background: radial-gradient(circle, rgba(255,82,0,.15) 0%, transparent 70%); }
    .hero::after { content: ''; position: absolute; bottom: -50px; left: -50px; width: 200px; height: 200px; border-radius: 50%; background: radial-gradient(circle, rgba(255,82,0,0.08) 0%, transparent 70%); pointer-events: none; animation: ballFloat3 15s ease-in-out infinite; }
    .hero-left { max-width: 600px; position: relative; z-index: 1; animation: fadeInUp 0.8s ease-out forwards; }
    .hero-left .hero-badge { animation: slideInLeft 0.6s ease-out 0.2s both; }
    .hero-left .hero-title { animation: fadeInUp 0.8s ease-out 0.4s both; }
    .hero-left .hero-desc { animation: fadeInUp 0.8s ease-out 0.6s both; }
    .hero-badge { display: inline-flex; align-items: center; gap: 8px; background: var(--primary); color: var(--white); padding: 8px 16px; border-radius: 50px; font-size: 13px; font-weight: 700; margin-bottom: 20px; transition: all 0.3s ease; cursor: default; }
    .hero-badge:hover { transform: scale(1.05); box-shadow: 0 4px 15px rgba(255,82,0,0.4); }
    .hero-badge i { animation: iconBounce 2s ease-in-out infinite; }
    .hero-title { font-size: 42px; font-weight: 800; color: var(--white); line-height: 1.2; margin-bottom: 16px; text-shadow: 0 2px 10px rgba(0,0,0,0.3); }
    .hero-title span { color: var(--primary); display: inline-block; transition: transform 0.3s ease; }
    .hero-left:hover .hero-title span { transform: scale(1.02); }
    .hero-desc { color: #A0A0A5; font-size: 16px; line-height: 1.6; margin-bottom: 24px; transition: color 0.3s ease; }
    .hero-left:hover .hero-desc { color: #C0C0C5; }

    /* ═══ FLOATING BALLS ═══ */
    .floating-ball { position: absolute; border-radius: 50%; background: radial-gradient(circle at 30% 30%, rgba(255,82,0,0.4), rgba(255,82,0,0.1)); filter: blur(1px); pointer-events: none; z-index: 0; }
    .ball-1 { width: 80px; height: 80px; top: 10%; right: 15%; animation: ballFloat1 8s ease-in-out infinite; background: radial-gradient(circle at 30% 30%, rgba(255,82,0,0.35), rgba(255,82,0,0.05)); }
    .ball-2 { width: 50px; height: 50px; top: 60%; right: 25%; animation: ballFloat2 10s ease-in-out infinite; background: radial-gradient(circle at 30% 30%, rgba(255,140,66,0.3), rgba(255,140,66,0.05)); }
    .ball-3 { width: 120px; height: 120px; bottom: 10%; right: 5%; animation: ballFloat3 12s ease-in-out infinite; background: radial-gradient(circle at 30% 30%, rgba(255,82,0,0.2), rgba(255,82,0,0.02)); }
    .ball-4 { width: 40px; height: 40px; top: 30%; right: 40%; animation: ballFloat1 7s ease-in-out infinite reverse; background: radial-gradient(circle at 30% 30%, rgba(255,200,100,0.3), rgba(255,200,100,0.05)); }
    .ball-5 { width: 60px; height: 60px; bottom: 30%; right: 30%; animation: ballFloat2 9s ease-in-out infinite reverse; background: radial-gradient(circle at 30% 30%, rgba(255,82,0,0.25), rgba(255,82,0,0.03)); }
    .hero-glow { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); width: 600px; height: 600px; background: radial-gradient(circle, rgba(255,82,0,0.08) 0%, transparent 70%); pointer-events: none; z-index: 0; animation: glowPulse 4s ease-in-out infinite; }

    /* ═══ KERANJANG WIDGET ═══ */
    .cart-widget { background: var(--white); border-radius: 16px; padding: 28px; border: 1px solid #E5E5EA; position: relative; z-index: 1; min-width: 340px; max-width: 380px; box-shadow: 0 10px 30px rgba(0,0,0,0.05); animation: fadeInUp 0.8s ease-out 0.2s forwards, cardEnter 0.6s ease-out 0.2s both; opacity: 0; transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); }
    .cart-widget:hover { transform: translateY(-6px) scale(1.02); box-shadow: 0 20px 40px rgba(0,0,0,0.15); border-color: rgba(255,82,0,0.3); }
    .cart-widget-header { display: flex; align-items: center; gap: 12px; margin-bottom: 20px; padding-bottom: 16px; border-bottom: 2px solid #F2F2F7; }
    .cart-widget-icon { width: 48px; height: 48px; border-radius: 14px; background: var(--orange-lt); color: var(--orange); display: flex; align-items: center; justify-content: center; font-size: 20px; animation: scaleIn 0.5s ease-out 0.4s both; }
    .cart-widget-title { font-size: 16px; font-weight: 800; color: #1C1C1E; animation: fadeInUp 0.5s ease-out 0.5s both; }
    .cart-widget-subtitle { font-size: 12px; color: #8E8E93; animation: fadeInUp 0.5s ease-out 0.6s both; }
    .cart-items { max-height: 200px; overflow-y: auto; margin-bottom: 16px; }
    .cart-empty { text-align: center; padding: 24px 0; color: #8E8E93; font-size: 13px; }
    .cart-empty i { font-size: 32px; margin-bottom: 8px; display: block; opacity: 0.5; }
    .cart-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid #F2F2F7; transition: var(--transition-smooth); }
    .cart-item:hover { background: rgba(255,82,0,0.02); transform: translateX(4px); }
    .cart-item-info { flex: 1; }
    .cart-item-name { font-size: 13px; font-weight: 700; color: #1C1C1E; }
    .cart-item-size { display: inline-block; background: var(--orange-lt); color: var(--orange); font-size: 10px; font-weight: 800; padding: 1px 7px; border-radius: 10px; margin-left: 4px; vertical-align: middle; }
    .cart-item-qty { font-size: 11px; color: #8E8E93; }
    .cart-item-price { font-size: 13px; font-weight: 800; color: var(--shopee-orange); }
    .cart-item-remove { background: none; border: none; color: var(--red); cursor: pointer; font-size: 12px; margin-left: 8px; padding: 4px; border-radius: 4px; transition: 0.2s; }
    .cart-item-remove:hover { background: var(--red-lt); transform: scale(1.1); }
    .cart-total { display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 2px solid #F2F2F7; margin-bottom: 16px; }
    .cart-total-label { font-size: 14px; font-weight: 600; color: #1C1C1E; }
    .cart-total-value { font-size: 20px; font-weight: 800; color: var(--shopee-orange); }

    .btn-checkout { width: 100%; background: var(--orange); color: var(--white); border: none; padding: 14px; border-radius: 12px; font-size: 14px; font-weight: 700; cursor: pointer; transition: var(--transition-smooth); display: flex; align-items: center; justify-content: center; gap: 8px; position: relative; overflow: hidden; }
    .btn-checkout::before { content:''; position:absolute; top:50%; left:50%; width:0; height:0; background:rgba(255,255,255,.2); border-radius:50%; transform:translate(-50%,-50%); transition:width .6s,height .6s; }
    .btn-checkout:hover::before { width:400px; height:400px; }
    .btn-checkout:hover:not(:disabled) { background: var(--orange-hover); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255,90,31,0.3); }
    .btn-checkout:disabled { background: var(--muted); cursor: not-allowed; }
    .btn-checkout i { transition: transform 0.3s ease; }
    .btn-checkout:hover i { transform: translateX(3px); }

    /* ═══ MAIN CONTAINER ═══ */
    .main-container { padding: 60px 80px; max-width: 1440px; margin: 0 auto; position: relative; }
    .main-container::before { content: ''; position: absolute; top: 0; left: 50%; transform: translateX(-50%); width: 80%; height: 1px; background: linear-gradient(90deg, transparent, rgba(255,82,0,0.1), transparent); }

    /* ═══ SECTION HEADER ═══ */
    .section-header { margin-bottom: 28px; animation: fadeInUp 0.6s ease-out both; }
    .section-header:hover .section-title i { transform: scale(1.2) rotate(10deg); }
    .section-title { font-size: 24px; font-weight: 800; color: #111; transition: color 0.3s ease; }
    .section-title i { display: inline-block; transition: transform 0.3s ease; }
    .section-subtitle { font-size: 14px; color: #636366; margin-top: 4px; }

    /* ═══ ALAT GRID ═══ */
    .alat-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 24px; margin-bottom: 60px; perspective: 1000px; }
    .alat-card { background: var(--white); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); position: relative; opacity: 0; transform: translateY(30px) scale(0.95); }
    .alat-card.visible { opacity: 1; transform: translateY(0) scale(1); }
    .alat-card:hover { transform: translateY(-8px) scale(1.02); box-shadow: 0 12px 32px rgba(0,0,0,0.08); border-color: var(--orange); }
    .alat-card:hover .alat-card-photo-wrap img { transform: scale(1.08); }
    .alat-card:hover .alat-card-name { color: var(--primary); }
    .alat-card:hover .alat-card-price { transform: scale(1.05); text-shadow: 0 2px 10px rgba(255,82,0,0.2); }
    .alat-card-photo-wrap { position: relative; width: 100%; aspect-ratio: 1 / 1; background: #F8F9FA; overflow: hidden; }
    .alat-card-photo-wrap img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.6s cubic-bezier(0.16,1,0.3,1); }
    .alat-card-photo-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); }
    .alat-card-photo-placeholder i { font-size: 48px; color: var(--primary); opacity: 0.5; transition: transform 0.3s ease; }
    .alat-card:hover .alat-card-photo-placeholder i { transform: scale(1.1); }
    .alat-card-stok-badge { position: absolute; bottom: 12px; left: 12px; background: rgba(255,255,255,0.9); color: var(--text-primary); padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 800; border: 1px solid var(--border); }

    .alat-card-info { padding: 20px; }
    .alat-card-name { font-size: 15px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px; min-height: 40px; transition: color 0.3s ease; }
    .alat-card-price { font-size: 20px; font-weight: 800; color: var(--orange); margin-bottom: 16px; transition: all 0.3s ease; }

    .alat-card-actions { display: flex; gap: 8px; align-items: center; }

    /* ═══ QUANTITY STEPPER (− / value / +) ═══ */
    .qty-stepper { display: inline-flex; align-items: center; border: 1.5px solid var(--border); border-radius: 10px; overflow: hidden; background: #fff; flex-shrink: 0; }
    .qty-stepper:focus-within { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-glow); }
    .qty-btn { width: 34px; height: 40px; border: none; background: var(--orange-lt); color: var(--orange); font-size: 12px; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.18s ease; }
    .qty-btn:hover:not(:disabled) { background: var(--orange); color: #fff; }
    .qty-btn:active:not(:disabled) { transform: scale(0.9); }
    .qty-btn:disabled { opacity: 0.35; cursor: not-allowed; }
    .qty-input { width: 42px; height: 40px; border: none; border-left: 1.5px solid var(--border); border-right: 1.5px solid var(--border); font-size: 14px; font-weight: 800; text-align: center; font-family: inherit; outline: none; color: var(--text-primary); background: #fff; -moz-appearance: textfield; }
    .qty-input::-webkit-outer-spin-button, .qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }

    /* ═══ SIZE CHIPS ═══ */
    .alat-card-size-group { margin-bottom: 14px; }
    .alat-card-size-label { font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .4px; margin-bottom: 8px; }
    .alat-card-sizes { display: flex; flex-wrap: wrap; gap: 6px; }
    .size-chip { min-width: 40px; padding: 7px 10px; border: 1.5px solid var(--border); border-radius: 8px; background: #fff; font-size: 12px; font-weight: 700; color: var(--text-secondary); cursor: pointer; transition: all 0.2s ease; font-family: inherit; }
    .size-chip:hover:not(:disabled) { border-color: var(--orange); color: var(--orange); transform: translateY(-1px); }
    .size-chip.selected { background: var(--orange); border-color: var(--orange); color: #fff; box-shadow: 0 4px 12px rgba(255,90,31,0.28); }
    .size-chip:disabled { opacity: 0.4; cursor: not-allowed; text-decoration: line-through; }

    /* ═══ CATEGORY BADGE ON CARD ═══ */
    .alat-card-kategori-badge { position: absolute; top: 12px; left: 12px; background: rgba(15,23,42,0.72); color: #fff; padding: 4px 10px; border-radius: 20px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; backdrop-filter: blur(4px); }

    /* ═══ SEARCH BAR ═══ */
    .section-header { display: flex; align-items: center; justify-content: space-between; gap: 20px; flex-wrap: wrap; }
    .alat-search-wrap { position: relative; flex: 1; min-width: 260px; max-width: 420px; }
    .alat-search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 14px; pointer-events: none; }
    .alat-search-input { width: 100%; padding: 13px 44px 13px 42px; border: 1.5px solid var(--border); border-radius: 12px; font-size: 14px; font-family: inherit; font-weight: 500; outline: none; transition: all 0.2s ease; background: #fff; color: var(--text-primary); }
    .alat-search-input:focus { border-color: var(--orange); box-shadow: 0 0 0 4px var(--orange-glow); }
    .alat-search-input::placeholder { color: var(--muted); font-weight: 400; }
    .alat-search-clear { position: absolute; right: 10px; top: 50%; transform: translateY(-50%); width: 28px; height: 28px; border: none; background: var(--border-lt); color: var(--muted); border-radius: 50%; cursor: pointer; display: none; align-items: center; justify-content: center; transition: all 0.2s ease; }
    .alat-search-clear:hover { background: var(--red-lt); color: var(--red); }
    .alat-search-clear.show { display: flex; }
    .alat-no-result { display: none; text-align: center; padding: 60px 20px; color: var(--muted); }
    .alat-no-result.show { display: block; }
    .alat-no-result i { font-size: 44px; opacity: 0.3; margin-bottom: 14px; display: block; }
    .alat-no-result div { font-size: 16px; font-weight: 700; color: var(--text-primary); }
    .alat-no-result p { font-size: 13px; margin-top: 6px; }

    .btn-add-cart { flex: 1; background: var(--orange-lt); color: var(--orange); border: 1px solid var(--orange); padding: 10px 14px; border-radius: 10px; font-size: 13px; font-weight: 700; cursor: pointer; transition: var(--transition-smooth); display: flex; align-items: center; justify-content: center; gap: 6px; position: relative; overflow: hidden; }
    .btn-add-cart::before { content: ''; position: absolute; top: 50%; left: 50%; width: 0; height: 0; background: rgba(255,255,255,0.2); border-radius: 50%; transform: translate(-50%, -50%); transition: width 0.6s, height 0.6s; }
    .btn-add-cart:hover::before { width: 300px; height: 300px; }
    .btn-add-cart:hover { background: var(--orange); color: #fff; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255,90,31,0.3); }
    .btn-add-cart:active { transform: translateY(0) scale(0.98); }

    /* ═══ MODAL OVERLAY & CARD ═══ */
    .booking-modal-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,.6); backdrop-filter:blur(4px); display:none; align-items:center; justify-content:center; z-index:2000; padding:20px; animation:fadeInModal .25s ease-out forwards; }
    .booking-modal-overlay.active { display: flex; }
    .summary-card { background:#fff; border-radius:20px; padding:30px; width:100%; max-width:500px; max-height:90vh; overflow-y:auto; position:relative; box-shadow:0 20px 40px rgba(0,0,0,.15); animation:slideInModal .3s cubic-bezier(.16,1,.3,1) forwards; -ms-overflow-style:none; scrollbar-width:none; transition: transform 0.3s ease; }
    .summary-card:hover { transform: translateY(-2px); }
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
    .payment-card.selected { border-color:var(--orange); background:var(--orange-lt); animation: scaleIn 0.3s ease-out; }
    .payment-card:hover .payment-name { color: var(--orange); transition: color 0.3s ease; }
    .custom-radio { width:16px; height:16px; border-radius:50%; border:1.5px solid var(--muted); display:flex; align-items:center; justify-content:center; flex-shrink:0; transition:.2s; }
    .payment-card.selected .custom-radio { border-color:var(--orange); }
    .custom-radio::after { content:''; width:8px; height:8px; border-radius:50%; background:var(--orange); display:none; }
    .payment-card.selected .custom-radio::after { display:block; animation:scaleIn .2s ease-out; }
    .payment-card-content { display:flex; flex-direction:column; justify-content:center; }
    .payment-name { font-size:11px; font-weight:700; color:var(--text-primary); line-height:1.3; }
    .payment-sub { font-size:9px; color:var(--muted); margin-top:1px; font-weight:500; }

    /* ---- BUKTI PEMBAYARAN UPLOAD ---- */
    .bukti-upload-section { text-align:left; margin: 6px 0 16px; }
    .bukti-upload-label { font-size:12.5px; font-weight:700; color:var(--text-primary); display:flex; align-items:center; gap:6px; margin-bottom:8px; }
    .bukti-upload-label i { color: var(--orange); }
    .bukti-required { font-size:10px; font-weight:800; color: var(--red, #EF4444); }
    .bukti-upload-box { display:flex; align-items:center; justify-content:center; flex-direction:column; border:2px dashed var(--border); border-radius:12px; padding:20px 14px; cursor:pointer; transition: var(--transition-smooth); min-height:110px; background: var(--bg, #FAFAFA); }
    .bukti-upload-box:hover { border-color: var(--orange); background: var(--orange-lt); }
    .bukti-upload-box.has-file { border-style:solid; border-color: var(--green, #10B981); padding:8px; }
    .bukti-upload-box i { font-size:22px; color: var(--muted); margin-bottom:6px; }
    .bukti-upload-text { font-size:12px; font-weight:700; color: var(--text-primary); }
    .bukti-upload-hint { font-size:10.5px; color: var(--muted); margin-top:2px; }
    #buktiUploadPreview { max-width:100%; max-height:160px; border-radius:8px; object-fit:contain; }
    .bukti-upload-error { font-size:11px; color: var(--red, #EF4444); font-weight:600; margin-top:6px; min-height:14px; }
    .qris-logo { font-family:'Barlow Condensed',sans-serif; font-weight:900; font-size:14px; color:#000; letter-spacing:-.5px; }

    .btn-booking { width:100%; background:var(--orange); color:#fff; border:none; border-radius:12px; padding:14px; font-family:inherit; font-size:14px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; margin-top:16px; transition:var(--transition-smooth); position:relative; overflow:hidden; }
    .btn-booking::before { content:''; position:absolute; top:50%; left:50%; width:0; height:0; background:rgba(255,255,255,.2); border-radius:50%; transform:translate(-50%,-50%); transition:width .6s,height .6s; }
    .btn-booking:hover::before { width:400px; height:400px; }
    .btn-booking:hover:not(:disabled) { background:var(--orange-hover); transform:translateY(-2px); box-shadow:0 10px 30px rgba(255,90,31,.4); }
    .btn-booking:disabled { background: var(--muted); cursor: not-allowed; }
    .btn-booking i { transition: transform 0.3s ease; }
    .btn-booking:hover i { transform: translateX(3px); }
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
    .bank-info-card::before { content:''; position:absolute; top:-20px; right:-20px; width:80px; height:80px; background:rgba(255,90,31,.05); border-radius:50%; }
    .bank-header { display:flex; align-items:center; gap:12px; margin-bottom:16px; }
    .bank-icon { width:44px; height:44px; background:var(--orange); border-radius:12px; display:flex; align-items:center; justify-content:center; color:#fff; font-size:18px; box-shadow:0 4px 12px rgba(255,90,31,.3); }
    .bank-title { font-size:13px; font-weight:800; color:var(--text-primary); }
    .bank-sub { font-size:11px; color:var(--muted); font-weight:500; }

    .va-section-label { font-size:11.5px; font-weight:700; color:var(--text-secondary); margin-bottom:10px; text-transform:uppercase; letter-spacing:.5px; }
    .va-input-row { display:flex; gap:8px; }
    .va-input-wrap { flex:1; background:#fff; border:2px solid var(--border); border-radius:12px; padding:14px 16px; display:flex; align-items:center; gap:10px; transition:var(--transition-smooth); }
    .va-input-wrap:hover { border-color:var(--orange); box-shadow:0 4px 16px rgba(255,90,31,.1); }
    .va-input-wrap i { color:var(--orange); font-size:14px; }
    .va-input-wrap input { border:none; background:transparent; font-weight:800; text-align:center; font-size:18px; letter-spacing:2px; color:var(--text-primary); font-family:'Plus Jakarta Sans',monospace; width:100%; outline:none; }
    .btn-copy-va { border-radius:12px; font-size:13px; padding:14px 18px; display:flex; align-items:center; gap:6px; white-space:nowrap; background:var(--orange); color:#fff; border:none; font-weight:700; box-shadow:0 4px 12px rgba(255,90,31,.3); cursor:pointer; transition:var(--transition-smooth); font-family:inherit; }
    .btn-copy-va:hover { transform:translateY(-2px); box-shadow:0 6px 20px rgba(255,90,31,.4); }

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
    .btn-done-pay i { transition: transform 0.3s ease; }
    .btn-done-pay:hover i { transform: scale(1.2); }

    
    /* ═══ NAVIGATION TABS ═══ */
    .page-nav-tabs { display: flex; gap: 0; margin-bottom: 32px; background: var(--white); border-radius: 14px; padding: 6px; border: 1px solid var(--border); box-shadow: 0 2px 8px rgba(0,0,0,0.04); animation: fadeInUp 0.6s ease-out both; }
    .page-nav-tab { flex: 1; padding: 14px 20px; border: none; border-radius: 10px; font-family: inherit; font-size: 14px; font-weight: 700; cursor: pointer; transition: var(--transition-smooth); background: transparent; color: var(--text-secondary); display: flex; align-items: center; justify-content: center; gap: 8px; position: relative; overflow: hidden; }
    .page-nav-tab::before { content: ''; position: absolute; top: 50%; left: 50%; width: 0; height: 0; background: rgba(255,90,31,0.1); border-radius: 50%; transform: translate(-50%,-50%); transition: width 0.5s, height 0.5s; }
    .page-nav-tab:hover::before { width: 200px; height: 200px; }
    .page-nav-tab:hover { color: var(--orange); }
    .page-nav-tab.active { background: var(--orange); color: var(--white); box-shadow: 0 4px 16px rgba(255,90,31,0.3); }
    .page-nav-tab.active:hover { color: var(--white); }
    .page-nav-tab i { font-size: 15px; transition: transform 0.3s ease; }
    .page-nav-tab:hover i { transform: scale(1.15); }
    .page-nav-tab .tab-badge { position: absolute; top: 6px; right: 10px; background: var(--red); color: var(--white); font-size: 9px; font-weight: 800; padding: 1px 6px; border-radius: 10px; min-width: 16px; text-align: center; }
    .page-nav-tab.active .tab-badge { background: var(--white); color: var(--orange); }

    /* ═══ RIWAYAT SECTION ═══ */
    .riwayat-section { display: none; animation: fadeInUp 0.5s ease-out; }
    .riwayat-section.active { display: block; }

    /* ═══ RIWAYAT CARD ═══ */
    .riwayat-list { display: flex; flex-direction: column; gap: 16px; }
    .riwayat-card { background: var(--white); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1); opacity: 0; transform: translateY(30px) scale(0.97); }
    .riwayat-card.visible { opacity: 1; transform: translateY(0) scale(1); }
    .riwayat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.08); border-color: var(--orange); }
    .riwayat-card-header { display: flex; align-items: center; justify-content: space-between; padding: 18px 24px; background: linear-gradient(135deg, #fafafa 0%, #f8f9fa 100%); border-bottom: 1px solid var(--border-lt); }
    .riwayat-card-header-left { display: flex; align-items: center; gap: 14px; }
    .riwayat-id-badge { background: var(--orange-lt); color: var(--orange); padding: 6px 14px; border-radius: 10px; font-size: 12px; font-weight: 800; font-family: 'Barlow Condensed', sans-serif; letter-spacing: 0.5px; }
    .riwayat-date { font-size: 13px; color: var(--muted); font-weight: 500; display: flex; align-items: center; gap: 6px; }
    .riwayat-date i { color: var(--orange); font-size: 12px; }
    .riwayat-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 20px; font-size: 12px; font-weight: 700; transition: all 0.3s ease; }
    .riwayat-status-badge i { font-size: 11px; }
    .riwayat-status-badge:hover { transform: scale(1.05); }

    .riwayat-card-body { padding: 20px 24px; }
    .riwayat-items-list { display: flex; flex-direction: column; gap: 12px; }
    .riwayat-item-row { display: flex; align-items: center; gap: 14px; padding: 12px; background: var(--bg); border-radius: 12px; border: 1px solid var(--border-lt); transition: all 0.3s ease; }
    .riwayat-item-row:hover { background: var(--orange-lt); border-color: rgba(255,90,31,0.2); transform: translateX(4px); }
    .riwayat-item-img { width: 56px; height: 56px; border-radius: 10px; object-fit: cover; border: 1px solid var(--border); background: #fff; flex-shrink: 0; }
    .riwayat-item-img-placeholder { width: 56px; height: 56px; border-radius: 10px; background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 1px solid var(--border); }
    .riwayat-item-img-placeholder i { font-size: 20px; color: var(--primary); opacity: 0.5; }
    .riwayat-item-info { flex: 1; min-width: 0; }
    .riwayat-item-name { font-size: 14px; font-weight: 700; color: var(--text-primary); margin-bottom: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .riwayat-item-meta { font-size: 12px; color: var(--muted); font-weight: 500; }
    .riwayat-item-meta .size-tag { background: var(--orange-lt); color: var(--orange); padding: 1px 8px; border-radius: 8px; font-size: 10px; font-weight: 800; margin-left: 4px; }
    .riwayat-item-subtotal { font-size: 14px; font-weight: 800; color: var(--text-primary); white-space: nowrap; }

    .riwayat-card-footer { display: flex; align-items: center; justify-content: space-between; padding: 16px 24px; background: linear-gradient(135deg, #fafafa 0%, #f8f9fa 100%); border-top: 1px solid var(--border-lt); }
    .riwayat-metode { display: flex; align-items: center; gap: 8px; font-size: 12px; color: var(--text-secondary); font-weight: 600; }
    .riwayat-metode i { color: var(--orange); }
    .riwayat-total-section { text-align: right; }
    .riwayat-total-label { font-size: 11px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; }
    .riwayat-total-value { font-size: 20px; font-weight: 900; color: var(--orange); margin-top: 2px; }

    /* ═══ EMPTY RIWAYAT STATE ═══ */
    .riwayat-empty { text-align: center; padding: 80px 20px; animation: fadeInUp 0.6s ease-out; }
    .riwayat-empty-icon { width: 100px; height: 100px; background: var(--orange-lt); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 24px; animation: float 4s ease-in-out infinite; }
    .riwayat-empty-icon i { font-size: 40px; color: var(--orange); }
    .riwayat-empty-title { font-size: 20px; font-weight: 800; color: var(--text-primary); margin-bottom: 8px; }
    .riwayat-empty-desc { font-size: 14px; color: var(--muted); max-width: 360px; margin: 0 auto 24px; line-height: 1.6; }
    .riwayat-empty-btn { display: inline-flex; align-items: center; gap: 8px; background: var(--orange); color: var(--white); padding: 14px 28px; border-radius: 12px; font-size: 14px; font-weight: 700; text-decoration: none; transition: var(--transition-smooth); border: none; cursor: pointer; }
    .riwayat-empty-btn:hover { background: var(--orange-hover); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255,90,31,0.3); }

    /* ═══ BUKTI PEMBAYARAN THUMBNAIL ═══ */
    .bukti-thumbnail { width: 48px; height: 48px; border-radius: 8px; object-fit: cover; border: 1px solid var(--border); cursor: pointer; transition: all 0.3s ease; }
    .bukti-thumbnail:hover { transform: scale(1.1); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
    .bukti-placeholder { width: 48px; height: 48px; border-radius: 8px; background: var(--border-lt); display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 18px; }

    /* ═══ BUKTI PEMBAYARAN MODAL ═══ */
    .bukti-modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.85); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 3000; padding: 20px; animation: fadeInModal 0.3s ease-out; }
    .bukti-modal-overlay.active { display: flex; }
    .bukti-modal-content { background: #fff; border-radius: 20px; padding: 24px; max-width: 600px; width: 100%; max-height: 90vh; overflow: hidden; position: relative; box-shadow: 0 20px 60px rgba(0,0,0,0.3); animation: slideInModal 0.4s cubic-bezier(0.16,1,0.3,1); }
    .bukti-modal-close { position: absolute; top: 16px; right: 16px; background: var(--border-lt); border: none; width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-secondary); transition: var(--transition-smooth); z-index: 10; }
    .bukti-modal-close:hover { background: var(--red-lt); color: var(--red); transform: rotate(90deg); }
    .bukti-modal-title { font-size: 18px; font-weight: 800; color: var(--text-primary); margin-bottom: 16px; text-align: center; }
    .bukti-modal-img { width: 100%; max-height: 70vh; object-fit: contain; border-radius: 12px; }

    /* ═══ RESPONSIVE RIWAYAT ═══ */
    @media (max-width: 768px) {
        .riwayat-card-header { flex-direction: column; align-items: flex-start; gap: 10px; padding: 14px 16px; }
        .riwayat-card-body { padding: 14px 16px; }
        .riwayat-card-footer { flex-direction: column; align-items: flex-start; gap: 10px; padding: 14px 16px; }
        .riwayat-total-section { text-align: left; width: 100%; }
        .riwayat-item-row { gap: 10px; }
        .riwayat-item-img, .riwayat-item-img-placeholder { width: 44px; height: 44px; }
        .page-nav-tab { font-size: 12px; padding: 10px 12px; }
        .page-nav-tab i { display: none; }
    }

    /* ============================================
   MATIKAN SEMUA ANIMASI SWEETALERT2 
   ============================================ */
        .swal2-popup {
            animation: none !important;
            transition: none !important;
        }

        .swal2-icon {
            animation: none !important;
        }

        .swal2-icon.swal2-success .swal2-success-ring,
        .swal2-icon.swal2-success [class^="swal2-success-line"],
        .swal2-icon.swal2-error [class^="swal2-x-mark-line"],
        .swal2-icon.swal2-warning {
            animation: none !important;
        }

        /* cegah body/html digeser oleh kompensasi scrollbar SweetAlert */
        html.swal2-shown,
        body.swal2-shown,
        body.swal2-height-auto {
            padding-right: 0 !important;
   }
</style>
</head>
<body>

<div class="page-loader" id="pageLoader">
    <div class="loader-ball"></div>
    <div class="loader-ball"></div>
    <div class="loader-ball"></div>
</div>

<div class="scroll-progress" id="scrollProgress"></div>

<!-- NAVBAR (pakai komponen bersama, sama seperti halaman customer lainnya) -->
<?php $path_prefix = '../'; include '../includes/navbar.php'; ?>

<!-- HERO SECTION -->
<section class="hero">
    <!-- Floating Balls Animation -->
    <div class="floating-ball ball-1"></div>
    <div class="floating-ball ball-2"></div>
    <div class="floating-ball ball-3"></div>
    <div class="floating-ball ball-4"></div>
    <div class="floating-ball ball-5"></div>
    <div class="hero-glow"></div>

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

        <!-- Navigation Tabs -->
        <div class="page-nav-tabs reveal">
            <button class="page-nav-tab active" id="tabBeli" onclick="switchTab('beli')">
                <i class="fa-solid fa-basket-shopping"></i> Beli Alat
            </button>
            <button class="page-nav-tab" id="tabRiwayat" onclick="switchTab('riwayat')">
                <i class="fa-solid fa-receipt"></i> Riwayat Pembelian
                <?php if (!empty($riwayat_beli)): ?>
                <span class="tab-badge"><?php echo count($riwayat_beli); ?></span>
                <?php endif; ?>
            </button>
        </div>

    <section id="beliSection" style="display:block;">
        <div class="section-header reveal">
            <div>
                <h2 class="section-title"><i class="fa-solid fa-basketball" style="color:var(--primary)"></i> Daftar Alat</h2>
                <p class="section-subtitle">Pilih perlengkapan basket yang Anda butuhkan.</p>
            </div>
            <div class="alat-search-wrap">
                <i class="fa-solid fa-magnifying-glass alat-search-icon"></i>
                <input type="text" id="alatSearch" class="alat-search-input" placeholder="Cari alat, misalnya 'jersey' atau 'bola'..." autocomplete="off" oninput="filterAlat()">
                <button type="button" id="alatSearchClear" class="alat-search-clear" onclick="clearAlatSearch()" title="Bersihkan"><i class="fa-solid fa-xmark"></i></button>
            </div>
        </div>

        <div class="alat-no-result" id="alatNoResult">
            <i class="fa-solid fa-magnifying-glass"></i>
            <div>Tidak ada alat yang cocok</div>
            <p>Coba kata kunci lain, ya.</p>
        </div>

        <div class="alat-grid reveal-stagger" id="alatGrid">
            <?php foreach ($alat_list as $alat): 
                $photo_url = resolvePhotoPath($alat['Photo_Alat']);
                $alat_sizes = $sizes_by_alat[$alat['ID_Alat']] ?? [];
                $kategori = $alat['Kategori'] ?? 'Lainnya';
                // Alat dianggap "punya varian ukuran" kalau ukurannya bukan cuma 'All Size'
                $has_real_sizes = false;
                foreach ($alat_sizes as $sz) { if ($sz['Ukuran'] !== 'All Size') { $has_real_sizes = true; break; } }
            ?>
            <div class="alat-card stagger-item"
                 data-id="<?php echo $alat['ID_Alat']; ?>"
                 data-name="<?php echo htmlspecialchars(strtolower($alat['Nama_Alat']), ENT_QUOTES); ?>"
                 data-kategori="<?php echo htmlspecialchars(strtolower($kategori), ENT_QUOTES); ?>">
                <div class="alat-card-photo-wrap">
                    <?php if (!empty($photo_url) && @file_exists($photo_url)): ?>
                        <img src="<?php echo htmlspecialchars($photo_url); ?>" alt="<?php echo htmlspecialchars($alat['Nama_Alat']); ?>">
                    <?php else: ?>
                        <div class="alat-card-photo-placeholder">
                            <i class="fa-solid fa-toolbox"></i>
                        </div>
                    <?php endif; ?>
                    <span class="alat-card-kategori-badge"><?php echo htmlspecialchars($kategori); ?></span>
                    <span class="alat-card-stok-badge">
                        Tersedia: <?php echo intval($alat['Stok']); ?>
                    </span>
                </div>
                <div class="alat-card-info">
                    <div class="alat-card-name"><?php echo htmlspecialchars($alat['Nama_Alat']); ?></div>
                    <div class="alat-card-price"><?php echo 'Rp ' . number_format($alat['Harga_Jual'], 0, ',', '.'); ?></div>

                    <?php if ($has_real_sizes): ?>
                    <div class="alat-card-size-group">
                        <div class="alat-card-size-label">Pilih Ukuran</div>
                        <div class="alat-card-sizes" id="sizes_<?php echo $alat['ID_Alat']; ?>">
                            <?php foreach ($alat_sizes as $sz): ?>
                                <button type="button" class="size-chip"
                                        data-size="<?php echo htmlspecialchars($sz['Ukuran'], ENT_QUOTES); ?>"
                                        data-stok="<?php echo $sz['Stok']; ?>"
                                        onclick="selectSize(<?php echo $alat['ID_Alat']; ?>, this)">
                                    <?php echo htmlspecialchars($sz['Ukuran']); ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="alat-card-actions">
                        <div class="qty-stepper" data-id="<?php echo $alat['ID_Alat']; ?>">
                            <button type="button" class="qty-btn qty-minus" onclick="stepQty(<?php echo $alat['ID_Alat']; ?>, -1)" aria-label="Kurangi">
                                <i class="fa-solid fa-minus"></i>
                            </button>
                            <input type="text" inputmode="numeric" class="qty-input" id="qty_<?php echo $alat['ID_Alat']; ?>"
                                   value="1" readonly
                                   data-max="<?php echo intval($alat['Stok']); ?>">
                            <button type="button" class="qty-btn qty-plus" onclick="stepQty(<?php echo $alat['ID_Alat']; ?>, 1)" aria-label="Tambah">
                                <i class="fa-solid fa-plus"></i>
                            </button>
                        </div>
                        <button class="btn-add-cart"
                                onclick="addToCart(<?php echo $alat['ID_Alat']; ?>, '<?php echo htmlspecialchars($alat['Nama_Alat'], ENT_QUOTES); ?>', <?php echo $alat['Harga_Jual']; ?>, <?php echo intval($alat['Stok']); ?>, <?php echo $has_real_sizes ? 'true' : 'false'; ?>)">
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

    <!-- ════════════════════════════════════════════════════════════
         RIWAYAT TRANSAKSI PEMBELIAN ALAT
         ════════════════════════════════════════════════════════════ -->
    <section class="riwayat-section" id="riwayatSection">
        <div class="section-header reveal">
            <div>
                <h2 class="section-title"><i class="fa-solid fa-receipt" style="color:var(--primary)"></i> Riwayat Pembelian Alat</h2>
                <p class="section-subtitle">Lihat status dan detail transaksi pembelian alat Anda.</p>
            </div>
        </div>

        <?php if (empty($riwayat_beli)): ?>
        <div class="riwayat-empty reveal">
            <div class="riwayat-empty-icon">
                <i class="fa-solid fa-basket-shopping"></i>
            </div>
            <div class="riwayat-empty-title">Belum Ada Pembelian</div>
            <p class="riwayat-empty-desc">Anda belum melakukan pembelian alat. Yuk, jelajahi koleksi perlengkapan basket kami dan mulai berbelanja!</p>
            <button class="riwayat-empty-btn" onclick="switchTab('beli')">
                <i class="fa-solid fa-basketball"></i> Beli Alat Sekarang
            </button>
        </div>
        <?php else: ?>
        <div class="riwayat-list reveal-stagger" id="riwayatList">
            <?php foreach ($riwayat_beli as $index => $trx):
                $status_info = getStatusLabel($trx['Status'] ?? 0);
                $status_text = $status_info[0];
                $status_color = $status_info[1];
                $status_bg = $status_info[2];
                $status_icon = $status_info[3];
                $total_items = array_sum(array_column($trx['detail_items'], 'Jumlah'));
            ?>
            <div class="riwayat-card stagger-item" data-index="<?php echo $index; ?>">
                <div class="riwayat-card-header">
                    <div class="riwayat-card-header-left">
                        <div class="riwayat-id-badge">#TRX-<?php echo str_pad($trx['ID_Beli'], 4, '0', STR_PAD_LEFT); ?></div>
                        <div class="riwayat-date">
                            <i class="fa-regular fa-calendar"></i>
                            <?php echo date('d M Y, H:i', strtotime($trx['Tanggal_Beli'])); ?>
                        </div>
                    </div>
                    <div class="riwayat-status-badge" style="background:<?php echo $status_bg; ?>; color:<?php echo $status_color; ?>;">
                        <i class="fa-solid <?php echo $status_icon; ?>"></i>
                        <?php echo $status_text; ?>
                    </div>
                </div>
                <div class="riwayat-card-body">
                    <div class="riwayat-items-list">
                        <?php foreach ($trx['detail_items'] as $item):
                            $item_photo = resolvePhotoPath($item['Photo_Alat'] ?? '');
                        ?>
                        <div class="riwayat-item-row">
                            <?php if (!empty($item_photo) && @file_exists($item_photo)): ?>
                                <img src="<?php echo htmlspecialchars($item_photo); ?>" alt="<?php echo htmlspecialchars($item['Nama_Alat']); ?>" class="riwayat-item-img">
                            <?php else: ?>
                                <div class="riwayat-item-img-placeholder">
                                    <i class="fa-solid fa-toolbox"></i>
                                </div>
                            <?php endif; ?>
                            <div class="riwayat-item-info">
                                <div class="riwayat-item-name"><?php echo htmlspecialchars($item['Nama_Alat']); ?></div>
                                <div class="riwayat-item-meta">
                                    <?php echo intval($item['Jumlah']); ?>x @ Rp <?php echo number_format($item['SubTotal'] / max(1, intval($item['Jumlah'])), 0, ',', '.'); ?>
                                    <?php if (!empty($item['Ukuran']) && $item['Ukuran'] !== 'All Size'): ?>
                                        <span class="size-tag"><?php echo htmlspecialchars($item['Ukuran']); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="riwayat-item-subtotal">Rp <?php echo number_format($item['SubTotal'], 0, ',', '.'); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="riwayat-card-footer">
                    <div style="display: flex; align-items: center; gap: 16px;">
                        <div class="riwayat-metode">
                            <i class="fa-solid fa-wallet"></i>
                            <?php echo htmlspecialchars($trx['Metode_Pembayaran'] ?? 'Transfer Bank'); ?>
                        </div>
                        <?php if (!empty($trx['Bukti_Pembayaran'])):
                            $bukti_path = resolvePhotoPath($trx['Bukti_Pembayaran']);
                            if (!empty($bukti_path) && @file_exists($bukti_path)):
                        ?>
                        <img src="<?php echo htmlspecialchars($bukti_path); ?>" alt="Bukti Pembayaran" class="bukti-thumbnail" onclick="openBuktiModal('<?php echo htmlspecialchars($bukti_path); ?>')" title="Klik untuk memperbesar">
                        <?php else: ?>
                        <div class="bukti-placeholder" title="Bukti tidak ditemukan"><i class="fa-solid fa-image"></i></div>
                        <?php endif; else: ?>
                        <div class="bukti-placeholder" title="Belum ada bukti"><i class="fa-solid fa-image"></i></div>
                        <?php endif; ?>
                    </div>
                    <div class="riwayat-total-section">
                        <div class="riwayat-total-label">Total Pembayaran (<?php echo $total_items; ?> item)</div>
                        <div class="riwayat-total-value">Rp <?php echo number_format($trx['Total_Bayar'], 0, ',', '.'); ?></div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

</main>

<!-- FOOTER -->

<!-- BUKTI PEMBAYARAN MODAL -->
<div class="bukti-modal-overlay" id="buktiModal">
    <div class="bukti-modal-content">
        <button class="bukti-modal-close" onclick="closeBuktiModal()"><i class="fa-solid fa-xmark"></i></button>
        <div class="bukti-modal-title"><i class="fa-solid fa-receipt" style="color:var(--orange);margin-right:8px;"></i>Bukti Pembayaran</div>
        <img src="" alt="Bukti Pembayaran" class="bukti-modal-img" id="buktiModalImg">
    </div>
</div>

<?php include '../includes/footer.php'; ?>

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
            <div class="step-item"><div class="step-num">1</div><div><div class="step-title">Buka Aplikasi Banking</div><div class="step-desc">Pilih menu <strong style="color:var(--primary)">Transfer > Virtual Account</strong> pada M-Banking atau ATM Anda.</div></div></div>
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

        <div class="bukti-upload-section">
            <div class="bukti-upload-label"><i class="fa-solid fa-camera"></i> Upload Bukti Pembayaran <span class="bukti-required">*Wajib</span></div>
            <label for="buktiPembayaranInput" class="bukti-upload-box" id="buktiUploadBox">
                <div id="buktiUploadPlaceholder">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <div class="bukti-upload-text">Klik untuk pilih foto/screenshot bukti transfer</div>
                    <div class="bukti-upload-hint">JPG, PNG, atau WEBP. Maks 5MB.</div>
                </div>
                <img id="buktiUploadPreview" style="display:none;">
            </label>
            <input type="file" id="buktiPembayaranInput" accept="image/jpeg,image/jpg,image/png,image/webp" style="display:none" onchange="handleBuktiPembayaranSelect(this)">
            <div class="bukti-upload-error" id="buktiUploadError"></div>
        </div>

        <button class="btn-done-pay" id="btnDonePayment">
            Saya Sudah Bayar <i class="fa-solid fa-circle-check"></i>
        </button>
    </div>
</div>

<script>
// ============================================================================
// STATE & KERANJANG BELANJA
// ============================================================================
let cart = [];
let checkoutTotalValue = 0;
let selectedPaymentMethod = 'Transfer Bank';
let countdownInterval;
let buktiPembayaranFile = null;
const selectedSizes = {}; // { idAlat: {size, stok} }

function formatRupiah(angka) {
    return 'Rp ' + angka.toLocaleString('id-ID');
}

// ═══ SEARCH / FILTER ═══
function filterAlat() {
    const q = document.getElementById('alatSearch').value.trim().toLowerCase();
    const clearBtn = document.getElementById('alatSearchClear');
    clearBtn.classList.toggle('show', q.length > 0);

    let visible = 0;
    document.querySelectorAll('.alat-card').forEach(card => {
        const name = card.getAttribute('data-name') || '';
        const kat = card.getAttribute('data-kategori') || '';
        const match = q === '' || name.includes(q) || kat.includes(q);
        card.style.display = match ? '' : 'none';
        if (match) visible++;
    });
    document.getElementById('alatNoResult').classList.toggle('show', visible === 0);
}

function clearAlatSearch() {
    const input = document.getElementById('alatSearch');
    input.value = '';
    input.focus();
    filterAlat();
}

// ═══ SIZE SELECTION ═══
function selectSize(idAlat, btn) {
    const stok = parseInt(btn.getAttribute('data-stok')) || 0;
    if (stok <= 0) return;
    const group = document.getElementById('sizes_' + idAlat);
    group.querySelectorAll('.size-chip').forEach(c => c.classList.remove('selected'));
    btn.classList.add('selected');
    selectedSizes[idAlat] = { size: btn.getAttribute('data-size'), stok: stok };

    // Batasi qty ke stok ukuran terpilih
    const qtyInput = document.getElementById('qty_' + idAlat);
    if (qtyInput) {
        qtyInput.setAttribute('data-max', stok);
        if (parseInt(qtyInput.value) > stok) qtyInput.value = stok;
        refreshStepper(idAlat);
    }
}

// ═══ QUANTITY STEPPER ═══
function stepQty(idAlat, delta) {
    const input = document.getElementById('qty_' + idAlat);
    const max = parseInt(input.getAttribute('data-max')) || 1;
    let val = (parseInt(input.value) || 1) + delta;
    if (val < 1) val = 1;
    if (val > max) val = max;
    input.value = val;
    refreshStepper(idAlat);
}

function refreshStepper(idAlat) {
    const input = document.getElementById('qty_' + idAlat);
    const max = parseInt(input.getAttribute('data-max')) || 1;
    const val = parseInt(input.value) || 1;
    const stepper = document.querySelector('.qty-stepper[data-id="' + idAlat + '"]');
    if (!stepper) return;
    stepper.querySelector('.qty-minus').disabled = (val <= 1);
    stepper.querySelector('.qty-plus').disabled = (val >= max);
}

function addToCart(idAlat, namaAlat, harga, maxStok, needsSize) {
    const qtyInput = document.getElementById('qty_' + idAlat);
    const qty = parseInt(qtyInput.value) || 1;

    // Wajib pilih ukuran kalau alat punya varian ukuran
    let ukuran = 'All Size';
    if (needsSize) {
        if (!selectedSizes[idAlat]) {
            Swal.fire({ icon: 'warning', title: 'Pilih Ukuran Dulu', text: 'Silakan pilih ukuran untuk ' + namaAlat + '.', confirmButtonColor: '#FF5200' });
            return;
        }
        ukuran = selectedSizes[idAlat].size;
        maxStok = selectedSizes[idAlat].stok; // batas = stok ukuran itu
    }

    if (qty <= 0 || qty > maxStok) {
        Swal.fire({ icon: 'warning', title: 'Jumlah Tidak Valid', text: 'Jumlah maksimal: ' + maxStok, confirmButtonColor: '#FF5200' });
        return;
    }

    // Item unik berdasarkan alat + ukuran
    const existingIndex = cart.findIndex(item => item.id_alat === idAlat && item.ukuran === ukuran);

    if (existingIndex >= 0) {
        const newQty = cart[existingIndex].jumlah + qty;
        if (newQty > maxStok) {
            Swal.fire({ icon: 'warning', title: 'Stok Terbatas', text: 'Total di keranjang melebihi stok ukuran ' + ukuran + ' (' + maxStok + ')', confirmButtonColor: '#FF5200' });
            return;
        }
        cart[existingIndex].jumlah = newQty;
        cart[existingIndex].subtotal = newQty * harga;
    } else {
        cart.push({ id_alat: idAlat, nama_alat: namaAlat, harga: harga, jumlah: qty, subtotal: qty * harga, ukuran: ukuran });
    }

    updateCartUI();

    const sizeLabel = (ukuran !== 'All Size') ? ' • Ukuran ' + ukuran : '';
    Swal.fire({
        icon: 'success', title: 'Ditambahkan!', text: namaAlat + ' (' + qty + 'x)' + sizeLabel,
        confirmButtonColor: '#FF5200', confirmButtonText: 'OK', timer: 1400, timerProgressBar: true
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
        const sizeBadge = (item.ukuran && item.ukuran !== 'All Size')
            ? `<span class="cart-item-size">Ukuran ${item.ukuran}</span>` : '';
        html += `
            <div class="cart-item">
                <div class="cart-item-info">
                    <div class="cart-item-name">${item.nama_alat} ${sizeBadge}</div>
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
        const sizeTxt = (item.ukuran && item.ukuran !== 'All Size') ? ' • Ukuran ' + item.ukuran : '';
        html += `
        <div class="checkout-item-row">
            <div>
                <div class="checkout-item-title">${item.nama_alat}</div>
                <div class="checkout-item-sub">${item.jumlah}x @ ${formatRupiah(item.harga)}${sizeTxt}</div>
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
    resetBuktiPembayaranUpload();

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

// ============================================================================
// UPLOAD BUKTI PEMBAYARAN (WAJIB)
// ============================================================================
function resetBuktiPembayaranUpload() {
    buktiPembayaranFile = null;
    document.getElementById('buktiPembayaranInput').value = '';
    document.getElementById('buktiUploadBox').classList.remove('has-file');
    document.getElementById('buktiUploadPlaceholder').style.display = 'flex';
    document.getElementById('buktiUploadPlaceholder').style.flexDirection = 'column';
    document.getElementById('buktiUploadPlaceholder').style.alignItems = 'center';
    document.getElementById('buktiUploadPreview').style.display = 'none';
    document.getElementById('buktiUploadError').textContent = '';
}

function handleBuktiPembayaranSelect(input) {
    const errorEl = document.getElementById('buktiUploadError');
    errorEl.textContent = '';
    const file = input.files && input.files[0];
    if (!file) return;

    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
    if (!allowedTypes.includes(file.type)) {
        errorEl.textContent = 'File harus berupa foto (JPG, PNG, atau WEBP).';
        input.value = '';
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        errorEl.textContent = 'Ukuran foto maksimal 5MB.';
        input.value = '';
        return;
    }

    buktiPembayaranFile = file;
    document.getElementById('buktiUploadBox').classList.add('has-file');
    document.getElementById('buktiUploadPlaceholder').style.display = 'none';
    const preview = document.getElementById('buktiUploadPreview');
    preview.src = URL.createObjectURL(file);
    preview.style.display = 'block';
}

// AJAX SUBMIT FINAL PAYMENT
document.getElementById('btnDonePayment').addEventListener('click', function() {
    if (cart.length === 0) return;

    if (!buktiPembayaranFile) {
        document.getElementById('buktiUploadError').textContent = 'Wajib upload bukti pembayaran sebelum melanjutkan.';
        document.getElementById('buktiUploadBox').scrollIntoView({ behavior: 'smooth', block: 'center' });
        return;
    }

    const btn = document.getElementById('btnDonePayment');
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';
    btn.disabled = true;

    const formData = new FormData();
    formData.append('cart_data', JSON.stringify(cart));
    formData.append('metode_pembayaran', selectedPaymentMethod);
    formData.append('total_bayar', checkoutTotalValue);
    formData.append('bukti_pembayaran', buktiPembayaranFile);

    fetch('pembelian_alat.php?action=checkout', {
        method: 'POST',
        body: formData
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

// Enhanced card animations with IntersectionObserver
const cardObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry, index) => {
        if (entry.isIntersecting) {
            setTimeout(() => {
                entry.target.classList.add('visible');
            }, index * 100);
            cardObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.alat-card').forEach(card => {
    cardObserver.observe(card);
});

// Safety net: if IntersectionObserver doesn't fire (edge cases), reveal all cards after 1.2s
setTimeout(() => {
    document.querySelectorAll('.alat-card:not(.visible)').forEach(c => c.classList.add('visible'));
}, 1200);

// Add hover tilt effect to ALL alat cards (independent of scroll-reveal state)
document.querySelectorAll('.alat-card').forEach(card => {
    // Saat mouse masuk: matikan transition + delay bawaan stagger,
    // supaya tilt langsung mengikuti cursor (bukan cuma pas cursor berhenti).
    card.addEventListener('mouseenter', () => {
        card.style.transition = 'none';
    });
    card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const rotateX = (y - rect.height / 2) / 50;
        const rotateY = (rect.width / 2 - x) / 50;
        card.style.transition = 'none';
        card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-8px) scale(1.02)`;
    });
    // Saat mouse keluar: pasang transition tanpa delay biar spring-back mulus,
    // lalu reset transform.
    card.addEventListener('mouseleave', () => {
        card.style.transition = 'transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1)';
        card.style.transform = '';
    });
});

// Init quantity steppers (disable/enable +/- correctly on load)
document.querySelectorAll('.qty-stepper').forEach(stepper => {
    refreshStepper(stepper.getAttribute('data-id'));
});

// Add parallax effect to floating balls on mouse move
document.querySelector('.hero').addEventListener('mousemove', (e) => {
    const balls = document.querySelectorAll('.floating-ball');
    const x = (e.clientX / window.innerWidth - 0.5) * 20;
    const y = (e.clientY / window.innerHeight - 0.5) * 20;
    balls.forEach((ball, i) => {
        const speed = (i + 1) * 0.5;
        ball.style.transform = `translate(${x * speed}px, ${y * speed}px)`;
    });
});

// Hide page loader when DOM is ready
window.addEventListener('DOMContentLoaded', () => {
    const loader = document.getElementById('pageLoader');
    if (loader) {
        setTimeout(() => {
            loader.classList.add('hidden');
        }, 500);
    }
});


    /* ============================================================
   KONFIRMASI SEBELUM KELUAR (LOGOUT)
   Berlaku untuk semua link yang mengarah ke logout.php,
   di sidebar maupun di dropdown topbar, pada SEMUA halaman.
   ============================================================ */
(function () {
    const SWAL_CDN = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
    let swalLoading = null;

    // Muat SweetAlert2 secara otomatis bila halaman belum memuatnya
    // (mis. dashboard/view_admin.php) supaya tampilan dialog seragam.
    function ensureSwal() {
        if (typeof Swal !== 'undefined') return Promise.resolve();
        if (swalLoading) return swalLoading;

        swalLoading = new Promise(function (resolve, reject) {
            const s = document.createElement('script');
            s.src = SWAL_CDN;
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        });
        return swalLoading;
    }

    function showLogoutDialog(url) {
        Swal.fire({
            title: 'Keluar dari HoopBall?',
            html: 'Apakah Anda yakin ingin keluar?<br>' +
                  '<span style="font-size:12px;color:#6B7280;">Sesi Anda akan diakhiri dan Anda perlu masuk kembali.</span>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-right-from-bracket"></i> Ya, Keluar',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#6B7280',
            reverseButtons: true,
            focusCancel: true,
            allowOutsideClick: false
        }).then(function (result) {
            if (!result.isConfirmed) return;

            Swal.fire({
                title: 'Sedang keluar...',
                text: 'Mohon tunggu sebentar.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function () { Swal.showLoading(); }
            });

            setTimeout(function () { window.location.href = url; }, 500);
        });
    }

    document.addEventListener('click', function (e) {
        const link = e.target.closest('a[href*="logout.php"]');
        if (!link) return;

        e.preventDefault();
        const url = link.getAttribute('href');

        ensureSwal()
            .then(function () { showLogoutDialog(url); })
            .catch(function () {
                // CDN tidak bisa diakses -> jangan biarkan logout tanpa konfirmasi
                if (confirm('Apakah Anda yakin ingin keluar?')) window.location.href = url;
            });
    });
})();


// ============================================================================
// TAB NAVIGATION
// ============================================================================
function switchTab(tab) {
    const beliSection = document.getElementById('beliSection');
    const riwayatSection = document.getElementById('riwayatSection');
    const tabBeli = document.getElementById('tabBeli');
    const tabRiwayat = document.getElementById('tabRiwayat');

    if (tab === 'beli') {
        beliSection.style.display = 'block';
        riwayatSection.style.display = 'none';
        riwayatSection.classList.remove('active');
        tabBeli.classList.add('active');
        tabRiwayat.classList.remove('active');
        // Re-trigger card animations
        document.querySelectorAll('.alat-card').forEach(card => {
            card.classList.remove('visible');
            setTimeout(() => card.classList.add('visible'), 50);
        });
    } else {
        beliSection.style.display = 'none';
        riwayatSection.style.display = 'block';
        riwayatSection.classList.add('active');
        tabBeli.classList.remove('active');
        tabRiwayat.classList.add('active');
        // Animate riwayat cards
        document.querySelectorAll('.riwayat-card').forEach((card, i) => {
            card.classList.remove('visible');
            setTimeout(() => card.classList.add('visible'), i * 100);
        });
    }
    // Scroll to top of main container
    document.querySelector('.main-container').scrollIntoView({ behavior: 'smooth', block: 'start' });
}

// ============================================================================
// BUKTI PEMBAYARAN MODAL
// ============================================================================
function openBuktiModal(src) {
    const overlay = document.getElementById('buktiModal');
    const img = document.getElementById('buktiModalImg');
    img.src = src;
    overlay.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeBuktiModal() {
    document.getElementById('buktiModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Close bukti modal on outside click
window.addEventListener('click', function(e) {
    const buktiModal = document.getElementById('buktiModal');
    if (e.target === buktiModal) closeBuktiModal();
});

            window.Swal = Swal.mixin({
            scrollbarPadding: false
        });
</script>
    <?php if (function_exists('tampilkan_sensor_auto_logout')) tampilkan_sensor_auto_logout(); ?>
</body>
</html>