<?php
session_start();
include '../includes/auth_helper.php';
include '../includes/config.php';

// ============================================================================
// CEK AKSES - KARYAWAN ONLY
// ============================================================================
cek_akses('karyawan');

$nama = $_SESSION['nama'] ?? 'Karyawan';
$role = $_SESSION['role'] ?? 'karyawan';
$id_karyawan = $_SESSION['id_karyawan'] ?? '';

// ============================================================================
// AMBIL FOTO PROFIL
// ============================================================================
$profile_photo = '';
if (!empty($id_karyawan)) {
    $stmt_photo = sqlsrv_query($conn, "{call sp_Karyawan_GetProfilePhoto(?)}", array($id_karyawan));
    if ($stmt_photo !== false) {
        $row_photo = sqlsrv_fetch_array($stmt_photo, SQLSRV_FETCH_ASSOC);
        if ($row_photo && !empty($row_photo['Photo_Profile'])) {
            $profile_photo = $row_photo['Photo_Profile'];
        }
    }
}

$sidebar_photo = '';
if (!empty($profile_photo)) {
    if (strpos($profile_photo, '../') === 0) {
        $sidebar_photo = $profile_photo;
    } elseif (strpos($profile_photo, 'uploads/') === 0) {
        $sidebar_photo = '../' . $profile_photo;
    } else {
        $sidebar_photo = '../uploads/profiles/' . $profile_photo;
    }
    if (!file_exists($sidebar_photo)) {
        $sidebar_photo = '';
    }
}

// ============================================================================
// AUTO-COMPLETE BOOKING (OTOMATIS SELESAI)
// ============================================================================
sqlsrv_query($conn, "{call sp_Booking_AutoComplete}");

// ============================================================================
// STATUS BOOKING
// ============================================================================
$status_labels = [
    0 => ['label' => 'Menunggu', 'class' => 'sp-pending', 'icon' => 'fa-clock'],
    1 => ['label' => 'Berhasil', 'class' => 'sp-active', 'icon' => 'fa-check-circle'],
    2 => ['label' => 'Selesai', 'class' => 'sp-success', 'icon' => 'fa-flag-checkered'],
    3 => ['label' => 'Dibatalkan', 'class' => 'sp-inactive', 'icon' => 'fa-ban']
];

// ============================================================================
// PROSES KONFIRMASI PEMBAYARAN
// ============================================================================
if (isset($_POST['konfirmasi_bayar'])) {
    $id_booking = $_POST['id_booking'];
    $stmt = sqlsrv_query($conn, "{call sp_Booking_ConfirmPayment(?, ?)}", array($id_booking, $nama));
    if ($stmt) {
        header("Location: booking.php?status=success&msg=Pembayaran berhasil dikonfirmasi.");
        exit();
    } else {
        header("Location: booking.php?status=error&msg=Gagal mengkonfirmasi pembayaran.");
        exit();
    }
}

// ============================================================================
// PROSES PEMBATALAN BOOKING (REFUND 50%)
// ============================================================================
if (isset($_POST['batal_booking'])) {
    $id_booking = $_POST['id_booking'];
    $alasan = $_POST['alasan_batal'];

    // Ambil detail kueri data sewa awal melalui SP
    $q_booking = sqlsrv_query($conn, "{call sp_Booking_GetDetail(?)}", array($id_booking));
    $booking_data = $q_booking ? sqlsrv_fetch_array($q_booking, SQLSRV_FETCH_ASSOC) : null;

    if ($booking_data) {
        $total_bayar = (float) $booking_data['Total_Bayar'];
        $biaya_batal = 0;                     // Biaya denda pembatalan Rp 0
        $nominal_refund = $total_bayar;       // Refund 100% dari total bayar
        $metode_refund = $booking_data['Metode_Pembayaran'];

        // Menjalankan pembatalan dan pencatatan kas keluar secara transaksional di basis data
        $stmt_batal = sqlsrv_query(
            $conn,
            "{call sp_Booking_CancelByKaryawan(?, ?, ?, ?, ?, ?, ?)}",
            array($id_booking, $id_karyawan, $alasan, $biaya_batal, $nominal_refund, $metode_refund, $nama)
        );

        if ($stmt_batal) {
            header("Location: booking.php?status=success&msg=Booking dibatalkan");
            exit();
        } else {
            header("Location: booking.php?status=error&msg=Gagal memproses pembatalan");
            exit();
        }
    } else {
        header("Location: booking.php?status=error&msg=Data booking tidak ditemukan.");
        exit();
    }
}

// ============================================================================
// AMBIL DATA BOOKING - URUTAN KHUSUS
// ============================================================================
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_customer = isset($_GET['filter_customer']) ? $_GET['filter_customer'] : '';
$filter_tanggal = isset($_GET['filter_tanggal']) ? $_GET['filter_tanggal'] : '';

// Normalisasi parameter filter input untuk SP
$status_val = ($filter_status === '' || $filter_status === 'all') ? -1 : (int) $filter_status;
$tgl_val = empty($filter_tanggal) ? null : $filter_tanggal;

// --- HITUNG TOTAL DATA UNTUK PAGING ---
$q_count = sqlsrv_query($conn, "{call sp_Booking_GetCount(?, ?, ?)}", array($status_val, $filter_customer, $tgl_val));
$total_data = 0;
if ($q_count) {
    $row_count = sqlsrv_fetch_array($q_count, SQLSRV_FETCH_ASSOC);
    $total_data = $row_count['total'] ?? 0;
}

// --- PAGING ---
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$total_pages = max(1, ceil($total_data / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

// Memuat baris data ter-paging melalui SP
$bookings = [];
$q_booking = sqlsrv_query($conn, "{call sp_Booking_GetPagedList(?, ?, ?, ?, ?)}", array($status_val, $filter_customer, $tgl_val, $offset, $limit));
if ($q_booking) {
    while ($row = sqlsrv_fetch_array($q_booking, SQLSRV_FETCH_ASSOC)) {
        $bookings[] = $row;
    }
}

// ============================================================================
// HITUNG STATISTIK (dari semua data, tanpa paging)
// ============================================================================
$stats = [
    'total' => 0,
    'menunggu' => 0,
    'berhasil' => 0,
    'selesai' => 0,
    'dibatalkan' => 0,
    'total_omzet' => 0,
    'total_refund' => 0
];

// Mengambil fungsi agregasi analisis dari UDF dbo.fn_Booking_GetDashboardStats
$q_stats = sqlsrv_query($conn, "SELECT * FROM dbo.fn_Booking_GetDashboardStats(?, ?, ?)", array($status_val, $filter_customer, $tgl_val));
if ($q_stats) {
    $row_stats = sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC);
    if ($row_stats) {
        $stats['total'] = (int) ($row_stats['Total'] ?? 0);
        $stats['menunggu'] = (int) ($row_stats['Menunggu'] ?? 0);
        $stats['berhasil'] = (int) ($row_stats['Berhasil'] ?? 0);
        $stats['selesai'] = (int) ($row_stats['Selesai'] ?? 0);
        $stats['dibatalkan'] = (int) ($row_stats['Dibatalkan'] ?? 0);
        $stats['total_omzet'] = (float) ($row_stats['Total_Omzet'] ?? 0);
        $stats['total_refund'] = (float) ($row_stats['Total_Refund'] ?? 0);
    }
}

function rupiahFormat($n)
{
    return 'Rp ' . number_format($n, 0, ',', '.');
}
function formatTanggal($tanggal)
{
    if (empty($tanggal))
        return '-';
    if (is_object($tanggal) && method_exists($tanggal, 'format')) {
        return $tanggal->format('d M Y');
    }
    return date('d M Y', strtotime($tanggal));
}
function formatJam($jam)
{
    if (empty($jam))
        return '-';
    if (is_object($jam) && method_exists($jam, 'format')) {
        return $jam->format('H:i');
    }
    return substr($jam, 0, 5);
}

/**
 * Menyusun path bukti pembayaran agar selalu valid diakses dari folder karyawan/.
 */
function resolveBuktiPath($path)
{
    if (empty($path))
        return '';
    if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0)
        return $path;
    if (strpos($path, '../') === 0)
        return $path;
    if (strpos($path, '/') === 0)
        return '..' . $path;
    if (strpos($path, 'uploads/') === 0)
        return '../' . $path;
    // Tambahkan kondisi untuk membaca path yang diawali dengan 'asset/'
    if (strpos($path, 'asset/') === 0) {
        return '../' . $path;
    }
    // Ubah fallback default ke folder asset yang baru
    return '../asset/Bukti_Pembayaran/' . ltrim($path, '/');
}

/**
 * Cek apakah file bukti pembayaran benar-benar ada di disk.
 * Fungsi ini membantu debug masalah "bukti tidak muncul".
 */
function checkBuktiExists($path)
{
    $resolved = resolveBuktiPath($path);
    if (empty($resolved))
        return false;
    return file_exists($resolved);
}

// Build URL params untuk paging
function buildPageUrl($page_num)
{
    $parts = [];
    if (isset($_GET['filter_status']) && $_GET['filter_status'] !== '')
        $parts[] = 'filter_status=' . urlencode($_GET['filter_status']);
    if (isset($_GET['filter_customer']) && $_GET['filter_customer'] !== '')
        $parts[] = 'filter_customer=' . urlencode($_GET['filter_customer']);
    if (isset($_GET['filter_tanggal']) && $_GET['filter_tanggal'] !== '')
        $parts[] = 'filter_tanggal=' . urlencode($_GET['filter_tanggal']);
    $parts[] = 'page=' . $page_num;
    return 'booking.php?' . implode('&', $parts);
}

$current_page = 'booking';
$sidebar_folder = 'transaksi';
// CATATAN: $sidebar_photo sudah dinormalisasi di atas, JANGAN ditimpa lagi.

// Topbar variables
$topbar_title = 'Kelola Booking';
$topbar_breadcrumb = 'Transaksi / Konfirmasi & Manajemen Booking';
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Booking | HoopBall</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../asset/css/global.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* ============================================================
   CATATAN:
   - Style TOPBAR & CLOCK DIHAPUS dari file ini.
     Semuanya sekarang mengikuti ../asset/css/global.css
     agar konsisten dengan fasilitas_lapangan.php
   - Script clock inline juga dihapus, diganti ../asset/js/global.js
   ============================================================ */

        .content {
            padding: 32px 40px;
            flex: 1;
        }

        /* ---- STAT CARDS ---- */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 14px;
            padding: 20px;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
            transition: all .2s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(0, 0, 0, .08);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            border-radius: 4px 0 0 4px;
        }

        .sc-orange::before {
            background: var(--orange);
        }

        .sc-yellow::before {
            background: var(--yellow);
        }

        .sc-green::before {
            background: var(--green);
        }

        .sc-blue::before {
            background: var(--blue);
        }

        .sc-red::before {
            background: var(--red);
        }

        .stat-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .stat-icon-wrap {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .si-orange {
            background: var(--orange-lt);
            color: var(--orange);
        }

        .si-yellow {
            background: var(--yellow-lt);
            color: #D97706;
        }

        .si-green {
            background: var(--green-lt);
            color: var(--green);
        }

        .si-blue {
            background: var(--blue-lt);
            color: var(--blue);
        }

        .si-red {
            background: var(--red-lt);
            color: var(--red);
        }

        .stat-value {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 28px;
            font-weight: 900;
            color: var(--text);
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-label {
            font-size: 11px;
            color: var(--muted);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        /* ---- FILTER BAR ---- */
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            gap: 16px;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-input {
            padding: 10px 14px;
            border: 1px solid var(--border);
            border-radius: 10px;
            font-size: 13px;
            font-family: inherit;
            background: var(--card-bg);
            color: var(--text);
            outline: none;
            transition: .2s;
        }

        .filter-input:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px var(--orange-lt);
        }

        .btn-secondary {
            background: var(--card-bg);
            color: var(--text);
            border: 1px solid var(--border);
            padding: 10px 18px;
            border-radius: 10px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: .2s;
            text-decoration: none;
        }

        .btn-secondary:hover {
            border-color: var(--orange);
            color: var(--orange);
        }

        /* ---- TABLE ---- */
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
        }

        .card-header {
            padding: 20px 24px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .card-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .card-title i {
            color: var(--orange);
            font-size: 14px;
        }

        .card-body {
            padding: 0;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            padding: 14px 16px;
            font-size: 11px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .6px;
            border-bottom: 2px solid var(--border-lt);
            text-align: left;
            background: #FAFAFA;
        }

        .data-table td {
            padding: 14px 16px;
            font-size: 13px;
            border-bottom: 1px solid var(--border-lt);
            vertical-align: middle;
        }

        .data-table tbody tr {
            transition: background .15s;
        }

        .data-table tbody tr:hover {
            background: #FAFAFA;
        }

        .data-table tbody tr:last-child td {
            border-bottom: none;
        }

        .cell-name {
            font-weight: 700;
            color: var(--text);
        }

        .cell-detail {
            font-size: 11px;
            color: var(--muted);
            font-weight: 600;
            margin-top: 2px;
        }

        .cell-price {
            font-weight: 800;
            color: var(--orange);
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

        .sp-active {
            background: var(--green-lt);
            color: var(--green);
        }

        .sp-success {
            background: var(--blue-lt);
            color: var(--blue);
        }

        .sp-pending {
            background: var(--yellow-lt);
            color: #D97706;
        }

        .sp-inactive {
            background: var(--red-lt);
            color: var(--red);
        }

        .action-btns {
            display: flex;
            gap: 6px;
        }

        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            border: 1px solid var(--border);
            background: var(--card-bg);
            color: var(--muted);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 13px;
            transition: all .25s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
        }

        .btn-icon:hover {
            transform: translateY(-2px) scale(1.08);
            box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
        }

        .btn-icon:active {
            transform: scale(0.95);
        }

        .btn-icon.view {
            color: var(--blue);
            border-color: rgba(59, 130, 246, .25);
            background: var(--blue-lt);
        }

        .btn-icon.view:hover {
            background: var(--blue);
            color: #fff;
            border-color: var(--blue);
            box-shadow: 0 4px 14px rgba(59, 130, 246, .35);
        }

        .btn-icon.success {
            color: var(--green);
            border-color: rgba(16, 185, 129, .25);
            background: var(--green-lt);
        }

        .btn-icon.success:hover {
            background: var(--green);
            color: #fff;
            border-color: var(--green);
            box-shadow: 0 4px 14px rgba(16, 185, 129, .35);
        }

        .btn-icon.danger {
            color: var(--red);
            border-color: rgba(239, 68, 68, .25);
            background: var(--red-lt);
        }

        .btn-icon.danger:hover {
            background: var(--red);
            color: #fff;
            border-color: var(--red);
            box-shadow: 0 4px 14px rgba(239, 68, 68, .35);
        }

        .btn-icon.bukti {
            color: #8B5CF6;
            border-color: rgba(139, 92, 246, .25);
            background: rgba(139, 92, 246, .1);
        }

        .btn-icon.bukti:hover {
            background: #8B5CF6;
            color: #fff;
            border-color: #8B5CF6;
            box-shadow: 0 4px 14px rgba(139, 92, 246, .35);
        }

        /* ---- PAGINATION ---- */
        .pagination-wrap {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-top: none;
            border-radius: 0 0 16px 16px;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .pagination-info {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
        }

        .pagination-info strong {
            color: var(--text);
            font-weight: 800;
        }

        .pagination-nav {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            font-family: 'Barlow', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: all .2s ease;
            border: 1.5px solid var(--border);
            color: var(--text-md);
            background: #fff;
        }

        .page-btn:hover:not(.disabled):not(.active) {
            border-color: var(--orange);
            color: var(--orange);
            background: var(--orange-lt);
            transform: translateY(-1px);
        }

        .page-btn.active {
            background: var(--orange);
            color: #fff;
            border-color: var(--orange);
            box-shadow: 0 4px 12px rgba(255, 69, 0, .3);
            font-weight: 800;
        }

        .page-btn.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }

        .page-btn i {
            font-size: 11px;
        }

        .page-ellipsis {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            color: var(--muted);
            font-size: 13px;
            font-weight: 800;
        }

        /* ---- MODAL DETAIL ---- */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, .5);
            z-index: 1000;
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal {
            background: var(--card-bg);
            border-radius: 16px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .2);
            animation: modalIn .3s ease-out;
            /* Sembunyikan scrollbar secara visual */
            scrollbar-width: none;
            /* Firefox */
            -ms-overflow-style: none;
            /* IE dan Edge */
        }

        .modal::-webkit-scrollbar {
            display: none;
            /* Chrome, Safari, dan Opera */
        }

        @keyframes modalIn {
            from {
                opacity: 0;
                transform: translateY(20px) scale(.95);
            }

            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            padding: 24px 28px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-title i {
            color: var(--orange);
        }

        .modal-close {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: none;
            background: var(--bg);
            color: var(--muted);
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: .2s;
        }

        .modal-close:hover {
            background: var(--red-lt);
            color: var(--red);
        }

        .modal-body {
            padding: 24px 28px;
        }

        .modal-footer {
            padding: 20px 28px;
            border-top: 1px solid var(--border);
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .detail-item {
            padding: 12px;
            background: var(--bg);
            border-radius: 10px;
        }

        .detail-label {
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 4px;
        }

        .detail-value {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
        }

        .detail-value.price {
            color: var(--orange);
            font-size: 16px;
        }

        .detail-full {
            grid-column: span 2;
        }

        @media(max-width: 1200px) {
            .stat-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }

        @media(max-width: 768px) {
            .content {
                padding: 20px;
            }

            .stat-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                width: 100%;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }

            .detail-full {
                grid-column: span 1;
            }

            .pagination-wrap {
                flex-direction: column;
                gap: 12px;
            }
        }
    </style>
</head>

<body>
    <!-- SIDEBAR -->
    <?php include '../includes/sidebar.php'; ?>

    <main class="main">
        <?php include '../includes/topbar.php'; ?>

        <div class="content">
            <!-- STAT CARDS -->
            <div class="stat-grid">
                <div class="stat-card sc-orange">
                    <div class="stat-header">
                        <div class="stat-icon-wrap si-orange"><i class="fa-solid fa-calendar-check"></i></div>
                    </div>
                    <div class="stat-value"><?= $stats['total'] ?></div>
                    <div class="stat-label">Total Booking</div>
                </div>
                <div class="stat-card sc-yellow">
                    <div class="stat-header">
                        <div class="stat-icon-wrap si-yellow"><i class="fa-solid fa-clock"></i></div>
                    </div>
                    <div class="stat-value"><?= $stats['menunggu'] ?></div>
                    <div class="stat-label">Menunggu Konfirmasi</div>
                </div>
                <div class="stat-card sc-green">
                    <div class="stat-header">
                        <div class="stat-icon-wrap si-green"><i class="fa-solid fa-check-circle"></i></div>
                    </div>
                    <div class="stat-value"><?= $stats['berhasil'] ?></div>
                    <div class="stat-label">Berhasil</div>
                </div>
                <div class="stat-card sc-blue">
                    <div class="stat-header">
                        <div class="stat-icon-wrap si-blue"><i class="fa-solid fa-flag-checkered"></i></div>
                    </div>
                    <div class="stat-value"><?= $stats['selesai'] ?></div>
                    <div class="stat-label">Selesai</div>
                </div>
                <div class="stat-card sc-red">
                    <div class="stat-header">
                        <div class="stat-icon-wrap si-red"><i class="fa-solid fa-ban"></i></div>
                    </div>
                    <div class="stat-value"><?= $stats['dibatalkan'] ?></div>
                    <div class="stat-label">Dibatalkan</div>
                </div>
            </div>

            <!-- INFO BOX -->
            <div
                style="background: var(--blue-lt); border: 1px solid var(--blue); border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
                <i class="fa-solid fa-circle-info" style="color: var(--blue); font-size: 20px;"></i>
                <div style="font-size: 13px; color: var(--text); line-height: 1.5;">
                    <strong>Peran Karyawan:</strong> Customer membuat booking melalui website. Karyawan hanya
                    mengkonfirmasi pembayaran yang sudah dilakukan customer.
                    <span style="color: var(--muted);">Booking baru dengan status "Menunggu" menunggu verifikasi
                        pembayaran Anda. Gunakan tombol <strong>Bukti Pembayaran</strong> untuk memeriksa bukti
                        transfer/QRIS yang diunggah customer sebelum mengkonfirmasi.</span>
                    <br><span style="color: var(--green); font-weight: 700;"><i class="fa-solid fa-robot"></i> Status
                        "Selesai" akan otomatis terupdate ketika waktu bermain sudah lewat.</span>
                </div>
            </div>

            <!-- FILTER BAR -->
            <div class="action-bar">
                <div class="filter-group">
                    <form method="GET" action=""
                        style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                        <select name="filter_status" class="filter-input" onchange="this.form.submit()">
                            <option value="all">Semua Status</option>
                            <option value="0" <?= $filter_status === '0' ? 'selected' : '' ?>>Menunggu Konfirmasi</option>
                            <option value="1" <?= $filter_status === '1' ? 'selected' : '' ?>>Berhasil</option>
                            <option value="2" <?= $filter_status === '2' ? 'selected' : '' ?>>Selesai</option>
                            <option value="3" <?= $filter_status === '3' ? 'selected' : '' ?>>Dibatalkan</option>
                        </select>
                        <input type="text" name="filter_customer" class="filter-input" placeholder="Cari customer..."
                            value="<?= htmlspecialchars($filter_customer) ?>">
                        <input type="date" name="filter_tanggal" class="filter-input"
                            value="<?= htmlspecialchars($filter_tanggal) ?>">
                        <button type="submit" class="btn-secondary"><i class="fa-solid fa-filter"></i> Filter</button>
                        <?php if (($filter_status !== '' && $filter_status !== 'all') || $filter_customer !== '' || $filter_tanggal !== ''): ?>
                            <a href="booking.php" class="btn-secondary"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <!-- BOOKING TABLE -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-list"></i> Daftar Booking</div>
                    <span style="font-size: 12px; color: var(--muted); font-weight: 600;"><?= $total_data ?> data
                        ditemukan</span>
                </div>
                <div class="card-body" style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th style="width: 70px; text-align: center;">No.</th>
                                <th style="text-align: center;">Customer</th>
                                <th style="text-align: center;">Lapangan & Jadwal</th>
                                <th style="text-align: right;">Tanggal Booking</th>
                                <th style="text-align: center;">Metode Bayar</th>
                                <th style="text-align: right;">Total Bayar</th>
                                <th style="text-align: center;">Status</th>
                                <th style="text-align: center;">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($bookings) > 0): ?>
                                <?php $no = $offset + 1;
                                foreach ($bookings as $b):
                                    $status = $status_labels[$b['Status']] ?? $status_labels[0];
                                    $tanggal_jadwal = formatTanggal($b['Tanggal']);
                                    $jam_mulai = formatJam($b['Jam_Mulai']);
                                    $jam_selesai = formatJam($b['Jam_Selesai']);
                                    ?>
                                    <tr>
                                        <td style="text-align: center; font-weight: 700; color: var(--text);"><?= $no++ ?></td>
                                        <td style="text-align: center;">
                                            <div class="cell-name"><?= htmlspecialchars($b['Nama_Customer']) ?></div>
                                            <div class="cell-detail"><?= htmlspecialchars($b['Email']) ?></div>
                                        </td>
                                        <td style="text-align: center;">
                                            <div class="cell-name"><?= htmlspecialchars($b['Nama_Lapangan']) ?></div>
                                            <div class="cell-detail"><?= $tanggal_jadwal ?> | <?= $jam_mulai ?> -
                                                <?= $jam_selesai ?>
                                            </div>
                                        </td>
                                        <td style="text-align: right;"><?= formatTanggal($b['Tanggal_Booking']) ?></td>
                                        <td style="text-align: center;"><?= $b['Metode_Pembayaran'] ?></td>
                                        <td class="cell-price" style="text-align: right;"><?= rupiahFormat($b['Total_Bayar']) ?>
                                        </td>
                                        <td style="text-align: center;"><span class="status-pill <?= $status['class'] ?>"><i
                                                    class="fa-solid <?= $status['icon'] ?>"></i> <?= $status['label'] ?></span>
                                        </td>
                                        <td>
                                            <div class="action-btns">
                                                <button class="btn-icon view" onclick="showDetail(<?= $b['ID_Booking'] ?>)"
                                                    title="Detail"><i class="fa-solid fa-eye"></i></button>
                                                <?php
                                                $bukti_path = $b['Bukti_Pembayaran'] ?? '';
                                                $bukti_exists = checkBuktiExists($bukti_path);
                                                if (!empty($bukti_path)):
                                                    ?>
                                                    <button class="btn-icon bukti" onclick="showBukti(<?= $b['ID_Booking'] ?>)"
                                                        title="Lihat Bukti Pembayaran<?= $bukti_exists ? '' : ' (File tidak ditemukan)' ?>"><i
                                                            class="fa-solid fa-receipt"></i></button>
                                                <?php endif; ?>
                                                <?php if ($b['Status'] == 0): ?>
                                                    <button class="btn-icon success" onclick="confirmBayar(<?= $b['ID_Booking'] ?>)"
                                                        title="Konfirmasi Pembayaran"><i class="fa-solid fa-check"></i></button>
                                                    <button class="btn-icon danger" onclick="confirmBatal(<?= $b['ID_Booking'] ?>)"
                                                        title="Batalkan"><i class="fa-solid fa-xmark"></i></button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" style="text-align: center; padding: 50px; color: var(--muted);">
                                        <i class="fa-solid fa-inbox"
                                            style="font-size: 40px; margin-bottom: 16px; opacity: .5; display: block;"></i>
                                        <div style="font-size: 14px; font-weight: 700;">Belum ada data booking</div>
                                        <div style="font-size: 12px; margin-top: 4px;">Customer belum melakukan booking
                                        </div>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- PAGINATION -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-wrap">
                    <div class="pagination-info">Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> -
                        <strong><?= min($page * $limit, $total_data) ?></strong> dari <strong><?= $total_data ?></strong>
                        data
                    </div>
                    <div class="pagination-nav">
                        <a href="<?= buildPageUrl(1) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"
                            title="Halaman Pertama"><i class="fa-solid fa-angles-left"></i></a>
                        <a href="<?= buildPageUrl($page - 1) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"
                            title="Halaman Sebelumnya"><i class="fa-solid fa-angle-left"></i></a>

                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        if ($end_page - $start_page < 4 && $total_pages >= 5) {
                            if ($start_page == 1) {
                                $end_page = min(5, $total_pages);
                            } else {
                                $start_page = max(1, $total_pages - 4);
                            }
                        }
                        if ($start_page > 1):
                            ?>
                            <a href="<?= buildPageUrl(1) ?>" class="page-btn">1</a>
                            <?php if ($start_page > 2): ?><span class="page-ellipsis">...</span><?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <a href="<?= buildPageUrl($i) ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                        <?php endfor; ?>

                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?><span class="page-ellipsis">...</span><?php endif; ?>
                            <a href="<?= buildPageUrl($total_pages) ?>" class="page-btn"><?= $total_pages ?></a>
                        <?php endif; ?>

                        <a href="<?= buildPageUrl($page + 1) ?>"
                            class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Selanjutnya"><i
                                class="fa-solid fa-angle-right"></i></a>
                        <a href="<?= buildPageUrl($total_pages) ?>"
                            class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Terakhir"><i
                                class="fa-solid fa-angles-right"></i></a>
                    </div>
                </div>
            <?php else: ?>
                <div class="pagination-wrap">
                    <div class="pagination-info">Menampilkan <strong>1</strong> - <strong><?= $total_data ?></strong> dari
                        <strong><?= $total_data ?></strong> data
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- MODAL DETAIL -->
    <div class="modal-overlay" id="modalDetail">
        <div class="modal">
            <div class="modal-header">
                <div class="modal-title"><i class="fa-solid fa-file-invoice"></i> Detail Booking</div>
                <button class="modal-close" onclick="closeModal('modalDetail')"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body" id="detailContent"></div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modalDetail')"><i
                        class="fa-solid fa-xmark"></i> Tutup</button>
            </div>
        </div>
    </div>

    <!-- MODAL BUKTI PEMBAYARAN -->
    <div class="modal-overlay" id="modalBukti">
        <div class="modal" style="max-width: 520px">
            <div class="modal-header">
                <div class="modal-title"><i class="fa-solid fa-receipt"></i> Bukti Pembayaran</div>
                <button class="modal-close" onclick="closeModal('modalBukti')"><i
                        class="fa-solid fa-xmark"></i></button>
            </div>
            <div class="modal-body" id="buktiContent" style="text-align:center"></div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modalBukti')"><i
                        class="fa-solid fa-xmark"></i> Tutup</button>
            </div>
        </div>
    </div>

    <!-- HIDDEN FORMS -->
    <form method="POST" id="formKonfirmasi" style="display: none;">
        <input type="hidden" name="id_booking" id="konfirmasiId">
        <input type="hidden" name="konfirmasi_bayar" value="1">
    </form>
    <form method="POST" id="formBatal" style="display: none;">
        <input type="hidden" name="id_booking" id="batalId">
        <input type="hidden" name="alasan_batal" id="batalAlasan">
        <input type="hidden" name="batal_booking" value="1">
    </form>

    <!-- GLOBAL JS: clock, dropdown, dsb (sama seperti fasilitas_lapangan.php) -->
    <script src="../asset/js/global.js"></script>

    <script>
        const bookingData = <?= json_encode($bookings) ?>;

        function openModal(id) {
            document.getElementById(id).classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
            document.body.style.overflow = '';
        }
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function (e) {
                if (e.target === this) {
                    this.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // ============ BUKTI PEMBAYARAN ============
        function resolveBuktiPath(path) {
            if (!path) return '';
            if (path.startsWith('http://') || path.startsWith('https://')) return path;
            if (path.startsWith('../')) return path;
            if (path.startsWith('/')) return '..' + path;
            if (path.startsWith('uploads/')) return '../' + path;
            // Tambahkan kondisi untuk membaca path yang diawali dengan 'asset/'
            if (path.startsWith('asset/')) return '../' + path;
            // Ubah fallback default ke folder asset yang baru
            return '../asset/Bukti_Pembayaran/' + path;
        }

        function showBukti(id) {
            const booking = bookingData.find(b => b.ID_Booking == id);
            if (!booking || !booking.Bukti_Pembayaran) {
                Swal.fire({ icon: 'info', title: 'Belum Ada Bukti', text: 'Customer belum mengunggah bukti pembayaran untuk booking ini.', confirmButtonColor: 'var(--orange)' });
                return;
            }

            const url = resolveBuktiPath(booking.Bukti_Pembayaran);
            console.log('Bukti path resolved:', url, 'Original:', booking.Bukti_Pembayaran);
            const ext = url.split('.').pop().toLowerCase();
            let html = '';
            if (ext === 'pdf') {
                html = `<iframe src="${url}" style="width:100%;height:480px;border:1px solid var(--border);border-radius:10px"></iframe>
                <div style="margin-top:14px"><a href="${url}" target="_blank" class="btn-secondary"><i class="fa-solid fa-up-right-from-square"></i> Buka di Tab Baru</a></div>`;
            } else {
                html = `<img src="${url}" alt="Bukti Pembayaran" style="max-width:100%;border-radius:10px;border:1px solid var(--border)">
                <div style="margin-top:14px"><a href="${url}" target="_blank" class="btn-secondary"><i class="fa-solid fa-up-right-from-square"></i> Buka di Tab Baru</a></div>`;
            }
            document.getElementById('buktiContent').innerHTML = html;
            openModal('modalBukti');
        }

        function showDetail(id) {
            const booking = bookingData.find(b => b.ID_Booking == id);
            if (!booking) return;

            const statusMap = {
                0: { label: 'Menunggu Konfirmasi', class: 'sp-pending', icon: 'fa-clock' },
                1: { label: 'Berhasil (Dikonfirmasi)', class: 'sp-active', icon: 'fa-check-circle' },
                2: { label: 'Selesai', class: 'sp-success', icon: 'fa-flag-checkered' },
                3: { label: 'Dibatalkan', class: 'sp-inactive', icon: 'fa-ban' }
            };
            const status = statusMap[booking.Status] || statusMap[0];

            const tanggalBooking = booking.Tanggal_Booking ? new Date(booking.Tanggal_Booking.date || booking.Tanggal_Booking).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '-';
            const tanggalJadwal = booking.Tanggal ? new Date(booking.Tanggal.date || booking.Tanggal).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '-';

            let jamMulai = '-';
            if (booking.Jam_Mulai) {
                let timeStr = typeof booking.Jam_Mulai === 'object' && booking.Jam_Mulai.date ? booking.Jam_Mulai.date : booking.Jam_Mulai;
                if (typeof timeStr === 'string') {
                    if (timeStr.includes(' ')) timeStr = timeStr.split(' ')[1];
                    jamMulai = timeStr.substring(0, 5);
                }
            }

            let jamSelesai = '-';
            if (booking.Jam_Selesai) {
                let timeStr = typeof booking.Jam_Selesai === 'object' && booking.Jam_Selesai.date ? booking.Jam_Selesai.date : booking.Jam_Selesai;
                if (typeof timeStr === 'string') {
                    if (timeStr.includes(' ')) timeStr = timeStr.split(' ')[1];
                    jamSelesai = timeStr.substring(0, 5);
                }
            }

            const buktiInfo = booking.Bukti_Pembayaran
                ? `<div class="detail-item"><div class="detail-label">Bukti Pembayaran</div><div class="detail-value"><button class="btn-secondary" style="margin-top:4px" onclick="showBukti(${booking.ID_Booking})"><i class="fa-solid fa-receipt"></i> Lihat Bukti Pembayaran</button></div></div>`
                : `<div class="detail-item"><div class="detail-label">Bukti Pembayaran</div><div class="detail-value" style="color:var(--muted);font-weight:500">Belum diunggah customer</div></div>`;

            const promoInfo = booking.Nama_Promo
                ? `<div class="detail-item detail-full"><div class="detail-label">Promo Digunakan</div><div class="detail-value">${booking.Nama_Promo} (Diskon ${formatRupiah(booking.Diskon || 0)})</div></div>`
                : '';

            const html = `
        <div class="detail-grid">
            <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value status"><span class="status-pill ${status.class}"><i class="fa-solid ${status.icon}"></i> ${status.label}</span></div></div>
            <div class="detail-item"><div class="detail-label">Customer</div><div class="detail-value">${booking.Nama_Customer}</div><div style="font-size: 11px; color: var(--muted); margin-top: 2px;">${booking.Email} | ${booking.No_Telepon}</div></div>
            <div class="detail-item"><div class="detail-label">Lapangan</div><div class="detail-value">${booking.Nama_Lapangan}</div></div>
            <div class="detail-item"><div class="detail-label">Jadwal Bermain</div><div class="detail-value">${tanggalJadwal}</div><div style="font-size: 11px; color: var(--muted); margin-top: 2px;">${jamMulai} - ${jamSelesai}</div></div>
            <div class="detail-item"><div class="detail-label">Tanggal Booking</div><div class="detail-value">${tanggalBooking}</div></div>
            <div class="detail-item"><div class="detail-label">Metode Pembayaran</div><div class="detail-value">${booking.Metode_Pembayaran}</div></div>
            <div class="detail-item"><div class="detail-label">Input Oleh</div><div class="detail-value">${booking.Nama_Karyawan_Input || 'System'}</div></div>
            ${buktiInfo}
            ${promoInfo}
            <div class="detail-item detail-full"><div class="detail-label">Total Bayar</div><div class="detail-value price">${formatRupiah(booking.Total_Bayar)}</div></div>
        </div>
    `;

            document.getElementById('detailContent').innerHTML = html;
            openModal('modalDetail');
        }

        function formatRupiah(angka) {
            const num = parseFloat(angka);
            return 'Rp ' + (isNaN(num) ? 0 : num).toLocaleString('id-ID');
        }

        function confirmBayar(id) {
            Swal.fire({
                title: 'Konfirmasi Pembayaran?',
                html: 'Customer sudah melakukan pembayaran?<br><span style="color: var(--muted); font-size: 12px;">Status booking akan berubah menjadi <strong>Berhasil</strong></span>',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#10B981',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Ya, Konfirmasi',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('konfirmasiId').value = id;
                    document.getElementById('formKonfirmasi').submit();
                }
            });
        }

        function confirmBatal(id) {
            Swal.fire({
                title: 'Batalkan Booking?',
                html: '<span style="color: var(--red);"><strong>Booking ini akan dibatalkan.</strong></span>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Ya, Batalkan',
                cancelButtonText: 'Batal',
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('batalId').value = id;
                    // Mengisi alasan secara otomatis agar data di database tetap terisi
                    document.getElementById('batalAlasan').value = 'Dibatalkan oleh Karyawan';
                    document.getElementById('formBatal').submit();
                }
            });
        }

        // ============================================
        // NOTIFIKASI POPUP TENGAH (CENTERED MODAL)
        // ============================================
        const urlParams = new URLSearchParams(window.location.search);
        const status = urlParams.get('status');
        const msg = urlParams.get('msg');

        if (status && msg) {
            const isSuccess = status === 'success';

            Swal.fire({
                icon: isSuccess ? 'success' : 'error',
                title: isSuccess ? 'Berhasil!' : 'Gagal!',
                text: msg,
                showConfirmButton: true,
                confirmButtonText: 'OK',
                confirmButtonColor: isSuccess ? '#10B981' : '#EF4444',
                allowOutsideClick: false,
                allowEscapeKey: false
            });

            window.history.replaceState({}, document.title, window.location.pathname);
        }
    </script>

</body>

</html>