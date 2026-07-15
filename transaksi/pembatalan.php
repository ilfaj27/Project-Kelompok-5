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
    $stmt_photo = sqlsrv_query($conn, "SELECT Photo_Profile FROM Karyawan WHERE ID_Karyawan = ?", array($id_karyawan));
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
// STATUS REFUND PEMBATALAN
// 0 = Menunggu Transfer Refund | 1 = Refund Selesai Ditransfer
// ============================================================================
$status_labels = [
    0 => ['label' => 'Menunggu', 'class' => 'sp-pending', 'icon' => 'fa-clock'],
    1 => ['label' => 'Selesai', 'class' => 'sp-success', 'icon' => 'fa-check-circle']
];

// ============================================================================
// PROSES KONFIRMASI PEMBAYARAN REFUND
// Catatan: Update status refund (0->1) tetap pakai SQL UPDATE biasa.
// SP sp_TransaksiPembatalan hanya untuk PROSES CREATE pembatalan oleh customer.
// Trigger trg_Pembatalan_Booking_AutoUpdate akan otomatis mengisi Modified_By/Date.
// ============================================================================
if (isset($_POST['konfirmasi_refund'])) {
    $id_pembatalan = intval($_POST['id_pembatalan']);
    $stmt = sqlsrv_query($conn,
        "UPDATE Pembatalan_Booking SET Status = 1, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Pembatalan = ? AND Status = 0",
        array($nama, $id_pembatalan)
    );
    if ($stmt) {
        header("Location: pembatalan.php?status=success&msg=Pembayaran refund berhasil dikonfirmasi.");
        exit();
    } else {
        header("Location: pembatalan.php?status=error&msg=Gagal mengonfirmasi pembayaran refund.");
        exit();
    }
}

// ============================================================================
// PROSES UPDATE/EDIT DATA PEMBATALAN
// Catatan: Update data pembatalan tetap pakai SQL UPDATE biasa.
// Trigger trg_Pembatalan_Booking_AutoUpdate (INSTEAD OF UPDATE) akan
// otomatis mengisi Modified_By dan Modified_Date saat update.
// Trigger trg_Pembatalan_Booking_Log (AFTER UPDATE) akan mencatat
// perubahan ke tabel Log_Pembatalan_Booking untuk audit trail.
// ============================================================================
if (isset($_POST['update_pembatalan'])) {
    $id_pembatalan = intval($_POST['id_pembatalan']);
    $alasan = htmlspecialchars($_POST['alasan']);
    $biaya_batal = floatval($_POST['biaya_batal']);
    $nominal_refund = floatval($_POST['nominal_refund']);
    $metode_refund = htmlspecialchars($_POST['metode_refund']);

    $stmt = sqlsrv_query($conn,
        "UPDATE Pembatalan_Booking SET Alasan = ?, Biaya_Batal = ?, Nominal_Refund = ?, Metode_Refund = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Pembatalan = ? AND Status = 0",
        array($alasan, $biaya_batal, $nominal_refund, $metode_refund, $nama, $id_pembatalan)
    );

    if ($stmt) {
        header("Location: pembatalan.php?status=success&msg=Data pembatalan berhasil diperbarui.");
        exit();
    } else {
        header("Location: pembatalan.php?status=error&msg=Gagal memperbarui data pembatalan.");
        exit();
    }
}

// ============================================================================
// AMBIL DATA PEMBATALAN DENGAN FILTER
// ============================================================================
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_customer = isset($_GET['filter_customer']) ? $_GET['filter_customer'] : '';
$filter_tanggal = isset($_GET['filter_tanggal']) ? $_GET['filter_tanggal'] : '';

$sql_where = "WHERE 1=1";
$params = [];

if ($filter_status !== '' && $filter_status !== 'all') {
    $sql_where .= " AND P.Status = ?";
    $params[] = (int) $filter_status;
}
if (!empty($filter_customer)) {
    $sql_where .= " AND C.Nama_Customer LIKE ?";
    $params[] = "%$filter_customer%";
}
if (!empty($filter_tanggal)) {
    $sql_where .= " AND CAST(P.Tanggal_Batal AS DATE) = ?";
    $params[] = $filter_tanggal;
}

// --- HITUNG TOTAL DATA UNTUK PAGING ---
$count_sql = "SELECT COUNT(*) as total FROM Pembatalan_Booking P
              INNER JOIN Booking B ON P.ID_Booking = B.ID_Booking
              INNER JOIN Customer C ON B.ID_Customer = C.ID_Customer
              INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
              INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
              LEFT JOIN Karyawan K ON P.ID_Karyawan = K.ID_Karyawan
              $sql_where";
$q_count = sqlsrv_query($conn, $count_sql, $params);
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

$sql_pembatalan = "SELECT P.ID_Pembatalan, P.ID_Booking, P.Tanggal_Batal, P.Alasan, 
                          P.Biaya_Batal, P.Nominal_Refund, P.Metode_Refund, P.Status AS StatusRefund,
                          P.Created_Date, P.Modified_Date,
                          B.Total_Bayar AS Total_Booking_Awal, B.Metode_Pembayaran AS Metode_Bayar_Awal,
                          C.Nama_Customer, C.Email, C.No_Telepon,
                          L.Nama_Lapangan,
                          J.Tanggal, J.Jam_Mulai, J.Jam_Selesai,
                          K.Nama_Karyawan AS Nama_Karyawan_Proses
                   FROM Pembatalan_Booking P
                   INNER JOIN Booking B ON P.ID_Booking = B.ID_Booking
                   INNER JOIN Customer C ON B.ID_Customer = C.ID_Customer
                   INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
                   INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
                   LEFT JOIN Karyawan K ON P.ID_Karyawan = K.ID_Karyawan
                   $sql_where
                   ORDER BY 
                    CASE 
                        WHEN P.Status = 0 THEN 0
                        WHEN P.Status = 1 THEN 1
                    END ASC,
                    P.Tanggal_Batal DESC
                   OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

$params_with_paging = array_merge($params, [$offset, $limit]);

$pembatalan_list = [];
$q_pembatalan = sqlsrv_query($conn, $sql_pembatalan, $params_with_paging);
if ($q_pembatalan === false) {
    die("<pre>" . print_r(sqlsrv_errors(), true) . "</pre>");
} else {
    while ($row = sqlsrv_fetch_array($q_pembatalan, SQLSRV_FETCH_ASSOC)) {
        $pembatalan_list[] = $row;
    }
}

// ============================================================================
// HITUNG STATISTIK (dari semua data, tanpa paging)
// ============================================================================
$stats = [
    'total' => 0, 'menunggu' => 0, 'selesai' => 0,
    'total_denda' => 0, 'total_refund' => 0
];

$stats_sql = "SELECT P.Status, P.Biaya_Batal, P.Nominal_Refund FROM Pembatalan_Booking P
              INNER JOIN Booking B ON P.ID_Booking = B.ID_Booking
              INNER JOIN Customer C ON B.ID_Customer = C.ID_Customer
              INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
              INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
              LEFT JOIN Karyawan K ON P.ID_Karyawan = K.ID_Karyawan
              $sql_where";
$q_stats = sqlsrv_query($conn, $stats_sql, $params);
if ($q_stats) {
    while ($row = sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC)) {
        $stats['total']++;
        if ($row['Status'] == 0) {
            $stats['menunggu']++;
        } elseif ($row['Status'] == 1) {
            $stats['selesai']++;
            $stats['total_refund'] += (float) $row['Nominal_Refund'];
        }
        $stats['total_denda'] += (float) $row['Biaya_Batal'];
    }
}

function rupiahFormat($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
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

// Build URL params untuk paging
function buildPageUrl($page_num) {
    $parts = [];
    if (isset($_GET['filter_status']) && $_GET['filter_status'] !== '') $parts[] = 'filter_status=' . urlencode($_GET['filter_status']);
    if (isset($_GET['filter_customer']) && $_GET['filter_customer'] !== '') $parts[] = 'filter_customer=' . urlencode($_GET['filter_customer']);
    if (isset($_GET['filter_tanggal']) && $_GET['filter_tanggal'] !== '') $parts[] = 'filter_tanggal=' . urlencode($_GET['filter_tanggal']);
    $parts[] = 'page=' . $page_num;
    return 'pembatalan.php?' . implode('&', $parts);
}

$current_page = 'pembatalan';
$sidebar_folder = 'transaksi';

// Topbar variables
$topbar_title = 'Kelola Pembatalan';
$topbar_breadcrumb = 'Transaksi / Pengembalian Dana (Refund)';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Pembatalan | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../asset/css/global.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* ============================================================
   Style SIDEBAR, TOPBAR & CLOCK dihapus dari file ini.
   Topbar kini memakai ../includes/topbar.php + global.css
   ============================================================ */

.content { padding: 32px 40px; flex: 1; }

/* ---- STAT CARDS ---- */
.stat-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 28px; }
.stat-card { background: var(--card-bg); border-radius: 14px; padding: 20px; border: 1px solid var(--border); position: relative; overflow: hidden; transition: all .2s ease; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 4px 0 0 4px; }
.sc-orange::before { background: var(--orange); }
.sc-yellow::before { background: var(--yellow); }
.sc-green::before { background: var(--green); }
.sc-blue::before { background: var(--blue); }
.sc-red::before { background: var(--red); }
.stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.stat-icon-wrap { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.si-orange { background: var(--orange-lt); color: var(--orange); }
.si-yellow { background: var(--yellow-lt); color: #D97706; }
.si-green { background: var(--green-lt); color: var(--green); }
.si-blue { background: var(--blue-lt); color: var(--blue); }
.si-red { background: var(--red-lt); color: var(--red); }
.stat-value { font-family: 'Barlow Condensed', sans-serif; font-size: 28px; font-weight: 900; color: var(--text); line-height: 1; margin-bottom: 4px; }
.stat-label { font-size: 11px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }

/* ---- FILTER BAR ---- */
.action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 16px; flex-wrap: wrap; }
.filter-group { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.filter-input { padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; font-size: 13px; font-family: inherit; background: var(--card-bg); color: var(--text); outline: none; transition: .2s; }
.filter-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.btn-secondary { background: var(--card-bg); color: var(--text); border: 1px solid var(--border); padding: 10px 18px; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: .2s; text-decoration: none; }
.btn-secondary:hover { border-color: var(--orange); color: var(--orange); }
.btn-orange { background: var(--orange); color: #fff; border: 1px solid var(--orange); padding: 10px 18px; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: .2s; text-decoration: none; }
.btn-orange:hover { background: var(--orange-dk); border-color: var(--orange-dk); }

/* ---- TABLE ---- */
.card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; }
.card-title i { color: var(--orange); font-size: 14px; }
.card-body { padding: 0; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 14px 16px; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; border-bottom: 2px solid var(--border-lt); text-align: left; background: #FAFAFA; }
.data-table td { padding: 14px 16px; font-size: 13px; border-bottom: 1px solid var(--border-lt); vertical-align: middle; }
.data-table tbody tr { transition: background .15s; }
.data-table tbody tr:hover { background: #FAFAFA; }
.cell-name { font-weight: 700; color: var(--text); }
.cell-detail { font-size: 11px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.status-pill { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; display: inline-flex; align-items: center; gap: 5px; }
.sp-pending { background: var(--yellow-lt); color: #D97706; }
.sp-success { background: var(--green-lt); color: var(--green); }
.action-btns { display: flex; gap: 6px; }
.btn-icon { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border); background: var(--card-bg); color: var(--muted); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 13px; transition: all .25s cubic-bezier(0.34,1.56,0.64,1); position: relative; overflow: hidden; }
.btn-icon:hover { transform: translateY(-2px) scale(1.08); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
.btn-icon:active { transform: scale(0.95); }
.btn-icon.view { color: var(--blue); border-color: rgba(59,130,246,.25); background: var(--blue-lt); }
.btn-icon.view:hover { background: var(--blue); color: #fff; border-color: var(--blue); box-shadow: 0 4px 14px rgba(59,130,246,.35); }
.btn-icon.success { color: var(--green); border-color: rgba(16,185,129,.25); background: var(--green-lt); }
.btn-icon.success:hover { background: var(--green); color: #fff; border-color: var(--green); box-shadow: 0 4px 14px rgba(16,185,129,.35); }
.btn-icon.edit { color: var(--orange); border-color: rgba(255,69,0,.25); background: var(--orange-lt); }
.btn-icon.edit:hover { background: var(--orange); color: #fff; border-color: var(--orange); box-shadow: 0 4px 14px rgba(255,69,0,.35); }

/* ---- PAGINATION ---- */
.pagination-wrap { background: var(--card-bg); border: 1px solid var(--border); border-top: none; border-radius: 0 0 16px 16px; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; }
.pagination-info { font-size: 12px; color: var(--muted); font-weight: 600; }
.pagination-info strong { color: var(--text); font-weight: 800; }
.pagination-nav { display: flex; align-items: center; gap: 4px; }
.page-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 10px; border-radius: 10px; font-size: 13px; font-weight: 700; font-family: 'Barlow', sans-serif; text-decoration: none; cursor: pointer; transition: all .2s ease; border: 1.5px solid var(--border); color: var(--text-md); background: #fff; }
.page-btn:hover:not(.disabled):not(.active) { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); transform: translateY(-1px); }
.page-btn.active { background: var(--orange); color: #fff; border-color: var(--orange); box-shadow: 0 4px 12px rgba(255,69,0,.3); font-weight: 800; }
.page-btn.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
.page-btn i { font-size: 11px; }
.page-ellipsis { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; color: var(--muted); font-size: 13px; font-weight: 800; }

/* ---- MODAL ---- */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,.5); z-index: 1000; backdrop-filter: blur(4px); align-items: center; justify-content: center; }
.modal-overlay.active { display: flex; }
.modal { background: var(--card-bg); border-radius: 16px; width: 90%; max-width: 600px; max-height: 90vh; overflow-y: auto; box-shadow: 0 20px 60px rgba(0,0,0,.2); animation: modalIn .3s ease-out; }
@keyframes modalIn { from { opacity: 0; transform: translateY(20px) scale(.95); } to { opacity: 1; transform: translateY(0) scale(1); } }
.modal-header { padding: 24px 28px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.modal-title { font-size: 18px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 10px; }
.modal-title i { color: var(--orange); }
.modal-close { width: 36px; height: 36px; border-radius: 10px; border: none; background: var(--bg); color: var(--muted); cursor: pointer; font-size: 16px; display: flex; align-items: center; justify-content: center; transition: .2s; }
.modal-close:hover { background: var(--red-lt); color: var(--red); }
.modal-body { padding: 24px 28px; }
.modal-footer { padding: 20px 28px; border-top: 1px solid var(--border); display: flex; justify-content: flex-end; gap: 10px; }

.detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.detail-item { padding: 12px; background: var(--bg); border-radius: 10px; }
.detail-label { font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 4px; }
.detail-value { font-size: 14px; font-weight: 700; color: var(--text); }
.detail-value.price { color: var(--orange); font-size: 16px; }
.detail-value.refund { color: var(--green); font-size: 16px; }
.detail-full { grid-column: span 2; }

@media(max-width: 1200px) { .stat-grid { grid-template-columns: repeat(3, 1fr); } }
@media(max-width: 768px) {
    .content { padding: 20px; }
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
    .action-bar { flex-direction: column; align-items: stretch; }
    .filter-group { width: 100%; }
    .detail-grid { grid-template-columns: 1fr; }
    .detail-full { grid-column: span 1; }
    .pagination-wrap { flex-direction: column; gap: 12px; }
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
                <span class="stat-label">Total Pengajuan</span>
                <div class="stat-icon-wrap si-orange"><i class="fa-solid fa-file-circle-xmark"></i></div>
            </div>
            <div class="stat-value"><?= $stats['total'] ?></div>
        </div>
        <div class="stat-card sc-yellow">
            <div class="stat-header">
                <span class="stat-label">Menunggu Refund</span>
                <div class="stat-icon-wrap si-yellow"><i class="fa-solid fa-clock"></i></div>
            </div>
            <div class="stat-value"><?= $stats['menunggu'] ?></div>
        </div>
        <div class="stat-card sc-green">
            <div class="stat-header">
                <span class="stat-label">Refund Selesai</span>
                <div class="stat-icon-wrap si-green"><i class="fa-solid fa-check-circle"></i></div>
            </div>
            <div class="stat-value"><?= $stats['selesai'] ?></div>
        </div>
        <div class="stat-card sc-blue">
            <div class="stat-header">
                <span class="stat-label">Total Denda</span>
                <div class="stat-icon-wrap si-blue"><i class="fa-solid fa-money-bill-wave"></i></div>
            </div>
            <div class="stat-value"><?= rupiahFormat($stats['total_denda']) ?></div>
        </div>
        <div class="stat-card sc-red">
            <div class="stat-header">
                <span class="stat-label">Total Refund</span>
                <div class="stat-icon-wrap si-red"><i class="fa-solid fa-hand-holding-dollar"></i></div>
            </div>
            <div class="stat-value"><?= rupiahFormat($stats['total_refund']) ?></div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="action-bar">
        <form method="GET" class="filter-group" style="flex:1;">
            <select name="filter_status" class="filter-input" style="min-width:160px;">
                <option value="all" <?= $filter_status === '' || $filter_status === 'all' ? 'selected' : '' ?>>Semua Status</option>
                <option value="0" <?= $filter_status === '0' ? 'selected' : '' ?>>Menunggu Transfer</option>
                <option value="1" <?= $filter_status === '1' ? 'selected' : '' ?>>Selesai Ditransfer</option>
            </select>
            <input type="text" name="filter_customer" class="filter-input" placeholder="Cari nama customer..." value="<?= htmlspecialchars($filter_customer) ?>" style="min-width:200px;">
            <input type="date" name="filter_tanggal" class="filter-input" value="<?= htmlspecialchars($filter_tanggal) ?>">
            <button type="submit" class="btn-orange"><i class="fa-solid fa-filter"></i> Filter</button>
            <a href="pembatalan.php" class="btn-secondary"><i class="fa-solid fa-rotate-left"></i> Reset</a>
        </form>
    </div>

    <!-- DATA TABLE -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-table-list"></i> Daftar Pengajuan Pembatalan</div>
        </div>
        <div class="card-body">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="text-align: center;">No</th>
                        <th style="text-align: center;">Pelanggan</th>
                        <th style="text-align: center;">Lapangan & Jadwal</th>
                        <th style="text-align: right;">Tanggal Batal</th>
                        <th style="text-align: right;">Denda / Refund</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($pembatalan_list)): ?>
                        <?php foreach ($pembatalan_list as $index => $p): 
                            $no = (($page - 1) * $limit) + $index + 1;
                            $statusInfo = $status_labels[$p['StatusRefund']] ?? $status_labels[0];
                        ?>
                        <tr>
                            <td style="text-align: center;"><?= $no ?></td>
                            <td style="text-align: center;">
                                <div class="cell-name"><?= htmlspecialchars($p['Nama_Customer']) ?></div>
                                <div class="cell-detail"><?= htmlspecialchars($p['Email'] ?? '') ?></div>
                            </td>
                            <td style="text-align: center;">
                                <div class="cell-name"><?= htmlspecialchars($p['Nama_Lapangan']) ?></div>
                                <div class="cell-detail">
                                    <?= formatTanggal($p['Tanggal']) ?> • <?= formatJam($p['Jam_Mulai']) ?> - <?= formatJam($p['Jam_Selesai']) ?>
                                </div>
                            </td>
                            <td style="text-align: right;"><?= formatTanggal($p['Tanggal_Batal']) ?></td>
                            <td style="text-align: right;">
                                <div style="font-size:12px; font-weight:700; color:var(--red);">Denda: <?= rupiahFormat($p['Biaya_Batal']) ?></div>
                                <div style="font-size:11px; color:var(--green); font-weight:600;">Refund: <?= rupiahFormat($p['Nominal_Refund']) ?></div>
                                <div style="font-size:10px; color:var(--muted);"><?= htmlspecialchars($p['Metode_Refund']) ?></div>
                            </td>
                            <td style="text-align: center;">
                                <span class="status-pill <?= $statusInfo['class'] ?>">
                                    <i class="fa-solid <?= $statusInfo['icon'] ?>"></i> <?= $statusInfo['label'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="action-btns" style="justify-content:center;">
                                    <button class="btn-icon view" onclick="showDetail(<?= $p['ID_Pembatalan'] ?>)" title="Lihat Detail">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                    <?php if ($p['StatusRefund'] == 0): ?>
                                        <button class="btn-icon success" onclick="confirmRefund(<?= $p['ID_Pembatalan'] ?>, '<?= htmlspecialchars($p['Metode_Refund']) ?>', <?= $p['Nominal_Refund'] ?>)" title="Konfirmasi Refund">
                                            <i class="fa-solid fa-check"></i>
                                        </button>
                                        <button class="btn-icon edit" onclick="editPembatalan(<?= $p['ID_Pembatalan'] ?>)" title="Edit Data">
                                            <i class="fa-solid fa-pen"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:40px; color:var(--muted);">
                                <i class="fa-solid fa-inbox" style="font-size:32px; margin-bottom:10px; display:block;"></i>
                                <span style="font-size:13px; font-weight:600;">Tidak ada data pembatalan.</span>
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
        <div class="pagination-info">Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> - <strong><?= min($page * $limit, $total_data) ?></strong> dari <strong><?= $total_data ?></strong> data</div>
        <div class="pagination-nav">
            <a href="<?= buildPageUrl(1) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Pertama"><i class="fa-solid fa-angles-left"></i></a>
            <a href="<?= buildPageUrl($page - 1) ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Sebelumnya"><i class="fa-solid fa-angle-left"></i></a>
            <?php 
            $start_page = max(1, $page - 2); 
            $end_page = min($total_pages, $page + 2); 
            if ($end_page - $start_page < 4 && $total_pages >= 5) { 
                if ($start_page == 1) { $end_page = min(5, $total_pages); } else { $start_page = max(1, $total_pages - 4); } 
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
            <a href="<?= buildPageUrl($page + 1) ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Selanjutnya"><i class="fa-solid fa-angle-right"></i></a>
            <a href="<?= buildPageUrl($total_pages) ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Terakhir"><i class="fa-solid fa-angles-right"></i></a>
        </div>
    </div>
    <?php else: ?>
    <div class="pagination-wrap">
        <div class="pagination-info">Menampilkan <strong>1</strong> - <strong><?= $total_data ?></strong> dari <strong><?= $total_data ?></strong> data</div>
    </div>
    <?php endif; ?>

</div>
</main>

<!-- MODAL DETAIL -->
<div class="modal-overlay" id="modalDetail">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-file-invoice-dollar"></i> Detail Pembatalan & Refund</div>
            <button class="modal-close" onclick="closeModal('modalDetail')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="detailContent"></div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('modalDetail')"><i class="fa-solid fa-xmark"></i> Tutup</button>
        </div>
    </div>
</div>

<!-- MODAL EDIT PEMBATALAN -->
<div class="modal-overlay" id="modalEdit">
    <div class="modal" style="max-width: 500px;">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-pen-to-square"></i> Edit Data Pembatalan</div>
            <button class="modal-close" onclick="closeModal('modalEdit')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <form method="POST" action="">
            <div class="modal-body">
                <input type="hidden" name="id_pembatalan" id="editId">
                <input type="hidden" name="update_pembatalan" value="1">
                <div style="display:flex; flex-direction:column; gap:6px; margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:800; color:var(--text); text-transform:uppercase; letter-spacing:.8px;">Metode Refund</label>
                    <input type="text" name="metode_refund" id="editMetode" class="filter-input" required style="width:100%;">
                </div>
                <div style="display:flex; flex-direction:column; gap:6px; margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:800; color:var(--text); text-transform:uppercase; letter-spacing:.8px;">Denda Pembatalan (Biaya Batal)</label>
                    <input type="number" name="biaya_batal" id="editBiaya" class="filter-input" required style="width:100%;" step="0.01">
                </div>
                <div style="display:flex; flex-direction:column; gap:6px; margin-bottom:12px;">
                    <label style="font-size:11px; font-weight:800; color:var(--text); text-transform:uppercase; letter-spacing:.8px;">Nominal Refund (Kembali ke Customer)</label>
                    <input type="number" name="nominal_refund" id="editRefund" class="filter-input" required style="width:100%;" step="0.01">
                </div>
                <div style="display:flex; flex-direction:column; gap:6px;">
                    <label style="font-size:11px; font-weight:800; color:var(--text); text-transform:uppercase; letter-spacing:.8px;">Alasan Pembatalan</label>
                    <textarea name="alasan" id="editAlasan" class="filter-input" required style="width:100%; min-height:80px; resize:vertical; font-family:inherit;"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('modalEdit')"><i class="fa-solid fa-xmark"></i> Batal</button>
                <button type="submit" class="btn-orange" style="padding:10px 20px; font-size:13px;"><i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

<!-- HIDDEN FORM -->
<form method="POST" id="formRefund" style="display: none;">
    <input type="hidden" name="id_pembatalan" id="refundId">
    <input type="hidden" name="konfirmasi_refund" value="1">
</form>

<!-- GLOBAL JS: clock, dropdown, dsb -->
<script src="../asset/js/global.js"></script>

<script>
const pembatalanData = <?= json_encode($pembatalan_list) ?>;

function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
}
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) { this.classList.remove('active'); document.body.style.overflow = ''; }
    });
});

function showDetail(id) {
    const data = pembatalanData.find(p => p.ID_Pembatalan == id);
    if (!data) return;

    const statusMap = {
        0: { label: 'Menunggu Pengiriman Dana (Refund)', class: 'sp-pending', icon: 'fa-clock' },
        1: { label: 'Refund Selesai Ditransfer', class: 'sp-success', icon: 'fa-check-circle' }
    };
    const status = statusMap[data.StatusRefund] || statusMap[0];

    const tglBatal = data.Tanggal_Batal ? new Date(data.Tanggal_Batal.date || data.Tanggal_Batal).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '-';
    const tglJadwal = data.Tanggal ? new Date(data.Tanggal.date || data.Tanggal).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '-';

    const getJamString = (timeObj) => {
        if (!timeObj) return '-';
        let timeStr = (typeof timeObj === 'object' && timeObj.date) ? timeObj.date : timeObj;
        if (typeof timeStr === 'string') {
            if (timeStr.includes(' ')) return timeStr.split(' ')[1].substring(0, 5);
            return timeStr.substring(0, 5);
        }
        return '-';
    };
    const jamMulai = getJamString(data.Jam_Mulai);
    const jamSelesai = getJamString(data.Jam_Selesai);

    const html = `
        <div class="detail-grid">
            <div class="detail-item detail-full">
                <div class="detail-label">Status Refund</div>
                <div class="detail-value"><span class="status-pill ${status.class}"><i class="fa-solid ${status.icon}"></i> ${status.label}</span></div>
            </div>
            <div class="detail-item"><div class="detail-label">Nama Pelanggan</div><div class="detail-value">${data.Nama_Customer}</div><div style="font-size:11px;color:var(--muted);margin-top:2px;">${data.Email} | ${data.No_Telepon}</div></div>
            <div class="detail-item"><div class="detail-label">Lapangan</div><div class="detail-value">${data.Nama_Lapangan}</div></div>
            <div class="detail-item"><div class="detail-label">Jadwal Sewa Asli</div><div class="detail-value">${tglJadwal}</div><div style="font-size:11px;color:var(--muted);margin-top:2px;">${jamMulai} - ${jamSelesai} WIB</div></div>
            <div class="detail-item"><div class="detail-label">Tanggal Pengajuan Batal</div><div class="detail-value">${tglBatal}</div></div>
            <div class="detail-item detail-full"><div class="detail-label">Alasan Pembatalan</div><div class="detail-value" style="font-weight:500;font-style:italic;line-height:1.4;">"${data.Alasan}"</div></div>
            <div class="detail-item"><div class="detail-label">Pembayaran Awal</div><div class="detail-value">${formatRupiah(data.Total_Booking_Awal)} (${data.Metode_Bayar_Awal})</div></div>
            <div class="detail-item"><div class="detail-label">Denda Pembatalan (50%)</div><div class="detail-value price">${formatRupiah(data.Biaya_Batal)}</div></div>
            <div class="detail-item detail-full" style="background:#ECFDF5;border:1px solid #A7F3D0;"><div class="detail-label" style="color:#047857;">Dana Refund Dikembalikan (50%)</div><div class="detail-value refund">${formatRupiah(data.Nominal_Refund)} (${data.Metode_Refund})</div></div>
            <div class="detail-item detail-full"><div class="detail-label">Dikonfirmasi Oleh</div><div class="detail-value">${data.Nama_Karyawan_Proses || 'Belum Dikonfirmasi'}</div></div>
        </div>
    `;
    document.getElementById('detailContent').innerHTML = html;
    openModal('modalDetail');
}

function formatRupiah(angka) {
    return 'Rp ' + parseFloat(angka).toLocaleString('id-ID');
}

function confirmRefund(id, metode, nominal) {
    Swal.fire({
        title: 'Konfirmasi Kirim Refund?',
        html: `Apakah Anda sudah mentransfer balik dana refund sebesar <strong style="color:var(--green);">${formatRupiah(nominal)}</strong> via <strong>${metode}</strong>?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10B981',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Sudah Ditransfer',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('refundId').value = id;
            document.getElementById('formRefund').submit();
        }
    });
}

function editPembatalan(id) {
    const data = pembatalanData.find(p => p.ID_Pembatalan == id);
    if (!data) return;
    document.getElementById('editId').value = data.ID_Pembatalan;
    document.getElementById('editMetode').value = data.Metode_Refund;
    document.getElementById('editBiaya').value = parseFloat(data.Biaya_Batal);
    document.getElementById('editRefund').value = parseFloat(data.Nominal_Refund);
    document.getElementById('editAlasan').value = data.Alasan;
    openModal('modalEdit');
}

// ============================================
// NOTIFIKASI POPUP TENGAH (CENTERED MODAL)
// ============================================
const urlParams = new URLSearchParams(window.location.search);
const statusMsg = urlParams.get('status');
const msgText = urlParams.get('msg');

if (statusMsg && msgText) {
    const isSuccess = statusMsg === 'success';
    Swal.fire({
        icon: isSuccess ? 'success' : 'error',
        title: isSuccess ? 'Berhasil!' : 'Gagal!',
        text: msgText,
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