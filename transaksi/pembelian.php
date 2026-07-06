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
// STATUS PEMBELIAN
// 0 = Menunggu Konfirmasi
// 1 = Berhasil
// ============================================================================
$status_labels = [
    0 => ['label' => 'Menunggu', 'class' => 'sp-pending', 'icon' => 'fa-clock'],
    1 => ['label' => 'Berhasil', 'class' => 'sp-active', 'icon' => 'fa-check-circle']
];

// ============================================================================
// PROSES KONFIRMASI PEMBAYARAN
// ============================================================================
if (isset($_POST['konfirmasi_bayar'])) {
    $id_beli = $_POST['id_beli'];
    $stmt = sqlsrv_query($conn, 
        "UPDATE Beli_Alat SET Status = 1, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Beli = ? AND Status = 0",
        array($nama, $id_beli)
    );
    if ($stmt) {
        header("Location: pembelian.php?status=success&msg=Pembayaran pembelian alat berhasil dikonfirmasi.");
        exit();
    } else {
        header("Location: pembelian.php?status=error&msg=Gagal mengkonfirmasi pembayaran pembelian alat.");
        exit();
    }
}

// ============================================================================
// PROSES PEMBATALAN PEMBELIAN
// ============================================================================
if (isset($_POST['batal_pembelian'])) {
    $id_beli = $_POST['id_beli'];
    $alasan = $_POST['alasan_batal'];

    $q_beli = sqlsrv_query($conn, 
        "SELECT BA.*, DBA.ID_Alat, DBA.Jumlah FROM Beli_Alat BA INNER JOIN Detail_Beli_Alat DBA ON BA.ID_Beli = DBA.ID_Beli WHERE BA.ID_Beli = ?",
        array($id_beli)
    );
    $details = [];
    $beli_data = null;
    while ($row = sqlsrv_fetch_array($q_beli, SQLSRV_FETCH_ASSOC)) {
        if (!$beli_data) $beli_data = $row;
        $details[] = ['id_alat' => $row['ID_Alat'], 'jumlah' => $row['Jumlah']];
    }

    if ($beli_data) {
        sqlsrv_query($conn, 
            "UPDATE Beli_Alat SET Status = 0, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Beli = ?",
            array($nama . ' (BATAL: ' . $alasan . ')', $id_beli)
        );
        foreach ($details as $d) {
            sqlsrv_query($conn, 
                "UPDATE Alat SET Stok = Stok + ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Alat = ?",
                array($d['jumlah'], $nama, $d['id_alat'])
            );
        }
        header("Location: pembelian.php?status=success&msg=Pembelian alat dibatalkan. Stok telah dikembalikan.");
        exit();
    } else {
        header("Location: pembelian.php?status=error&msg=Data pembelian tidak ditemukan.");
        exit();
    }
}

// ============================================================================
// AMBIL DATA PEMBELIAN
// ============================================================================
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_customer = isset($_GET['filter_customer']) ? $_GET['filter_customer'] : '';
$filter_tanggal = isset($_GET['filter_tanggal']) ? $_GET['filter_tanggal'] : '';

$sql_where = "WHERE 1=1";
$params = [];

if ($filter_status !== '' && $filter_status !== 'all') {
    $sql_where .= " AND BA.Status = ?";
    $params[] = (int)$filter_status;
}
if (!empty($filter_customer)) {
    $sql_where .= " AND C.Nama_Customer LIKE ?";
    $params[] = "%$filter_customer%";
}
if (!empty($filter_tanggal)) {
    $sql_where .= " AND CAST(BA.Tanggal_Beli AS DATE) = ?";
    $params[] = $filter_tanggal;
}

// --- HITUNG TOTAL DATA UNTUK PAGING ---
$count_sql = "SELECT COUNT(*) as total FROM Beli_Alat BA
              INNER JOIN Customer C ON BA.ID_Customer = C.ID_Customer
              LEFT JOIN Karyawan K ON BA.ID_Karyawan = K.ID_Karyawan
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

$sql_pembelian = "SELECT BA.ID_Beli, BA.ID_Customer, BA.ID_Karyawan, 
                       BA.Tanggal_Beli, BA.Metode_Pembayaran, BA.Total_Bayar, BA.Status,
                       BA.Created_Date, BA.Modified_Date,
                       C.Nama_Customer, C.Email, C.No_Telepon,
                       K.Nama_Karyawan as Nama_Karyawan_Input
                FROM Beli_Alat BA
                INNER JOIN Customer C ON BA.ID_Customer = C.ID_Customer
                LEFT JOIN Karyawan K ON BA.ID_Karyawan = K.ID_Karyawan
                $sql_where
                ORDER BY 
                    CASE 
                        WHEN BA.Status = 0 THEN 0
                        WHEN BA.Status = 1 THEN 1
                    END ASC,
                    BA.Tanggal_Beli DESC
                OFFSET ? ROWS FETCH NEXT ? ROWS ONLY";

$params_with_paging = array_merge($params, [$offset, $limit]);

$pembelians = [];
$q_pembelian = sqlsrv_query($conn, $sql_pembelian, $params_with_paging);
if ($q_pembelian) {
    while ($row = sqlsrv_fetch_array($q_pembelian, SQLSRV_FETCH_ASSOC)) {
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
        $pembelians[] = $row;
    }
}

// ============================================================================
// HITUNG STATISTIK (dari semua data, tanpa paging)
// ============================================================================
$stats = [
    'total' => 0, 'menunggu' => 0, 'berhasil' => 0,
    'total_omzet' => 0, 'total_item' => 0
];

$stats_sql = "SELECT BA.Status, BA.Total_Bayar FROM Beli_Alat BA
              INNER JOIN Customer C ON BA.ID_Customer = C.ID_Customer
              LEFT JOIN Karyawan K ON BA.ID_Karyawan = K.ID_Karyawan
              $sql_where";
$q_stats = sqlsrv_query($conn, $stats_sql, $params);
if ($q_stats) {
    while ($row = sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC)) {
        $stats['total']++;
        if ($row['Status'] == 0) $stats['menunggu']++;
        if ($row['Status'] == 1) {
            $stats['berhasil']++;
            $stats['total_omzet'] += (float)$row['Total_Bayar'];
        }
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

// Build URL params untuk paging
function buildPageUrl($page_num) {
    $parts = [];
    if (isset($_GET['filter_status']) && $_GET['filter_status'] !== '') $parts[] = 'filter_status=' . urlencode($_GET['filter_status']);
    if (isset($_GET['filter_customer']) && $_GET['filter_customer'] !== '') $parts[] = 'filter_customer=' . urlencode($_GET['filter_customer']);
    if (isset($_GET['filter_tanggal']) && $_GET['filter_tanggal'] !== '') $parts[] = 'filter_tanggal=' . urlencode($_GET['filter_tanggal']);
    $parts[] = 'page=' . $page_num;
    return 'pembelian.php?' . implode('&', $parts);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Pembelian Alat | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10); --green-dk: #059669;
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10); --red-dk: #DC2626;
    --yellow: #F59E0B; --yellow-lt: rgba(245,158,11,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --text-md: #374151; --muted: #6B7280; --bg: #F3F4F6;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

/* ---- SIDEBAR ---- */
.sidebar { width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 28px 18px; border-right: 1px solid rgba(255,255,255,.04); z-index: 200; overflow-y: auto; scrollbar-width: none; -ms-overflow-style: none; }
.sidebar::-webkit-scrollbar { display: none; }
.sb-brand { display: flex; align-items: center; gap: 12px; padding: 0 8px; margin-bottom: 36px; text-decoration: none; position: relative; transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1); }
.sb-brand:hover { transform: scale(1.02); }
.sb-brand::after { content: ''; position: absolute; bottom: -8px; left: 0; width: 0; height: 2px; background: linear-gradient(90deg, var(--orange), transparent); transition: width 0.4s cubic-bezier(0.16,1,0.3,1); }
.sb-brand:hover::after { width: 100%; }
.sb-icon { width: 40px; height: 40px; background: var(--orange); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; flex-shrink: 0; box-shadow: 0 4px 14px rgba(255,69,0,.4); transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1); }
.sb-brand:hover .sb-icon { transform: rotate(5deg) scale(1.1); box-shadow: 0 6px 20px rgba(255,69,0,.5); }
.sb-brand-name { font-family: 'Barlow Condensed', sans-serif; font-size: 20px; font-weight: 900; color: #fff; letter-spacing: 1px; transition: color 0.3s ease; }
.sb-brand-sub { font-size: 9px; color: #4B5563; font-weight: 700; text-transform: uppercase; transition: color 0.3s ease; }
.sb-brand:hover .sb-brand-sub { color: var(--orange); }

.sb-section-label { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #374151; letter-spacing: .8px; padding: 0 10px; margin: 22px 0 8px; position: relative; }
.sb-section-label::after { content: ''; position: absolute; bottom: -4px; left: 10px; width: 20px; height: 2px; background: var(--orange); border-radius: 1px; transition: width 0.3s ease; }
.sb-section-label:hover::after { width: 40px; }

.sb-link { display: flex; align-items: center; gap: 12px; color: #6B7280; text-decoration: none; padding: 10px 12px; border-radius: 10px; margin-bottom: 2px; font-size: 13px; font-weight: 600; transition: all 0.35s cubic-bezier(0.16,1,0.3,1); position: relative; overflow: hidden; }
.sb-link::before { content: ''; position: absolute; left: 0; top: 0; width: 0; height: 100%; background: linear-gradient(90deg, rgba(255,69,0,0.15), rgba(255,69,0,0.05)); border-radius: 10px; transition: width 0.35s cubic-bezier(0.16,1,0.3,1); z-index: 0; }
.sb-link:hover::before { width: 100%; }
.sb-link .sb-icon-wrap { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: all 0.35s cubic-bezier(0.34,1.56,0.64,1); flex-shrink: 0; background: rgba(255,255,255,.04); position: relative; z-index: 1; }
.sb-link:hover { color: #E5E7EB; transform: translateX(4px); }
.sb-link:hover .sb-icon-wrap { background: rgba(255,255,255,.12); transform: scale(1.15) rotate(5deg); }
.sb-link.active { color: #fff; background: var(--orange-lt); }
.sb-link.active::before { width: 100%; background: linear-gradient(90deg, rgba(255,69,0,0.2), rgba(255,69,0,0.08)); }
.sb-link.active .sb-icon-wrap { background: var(--orange); color: #fff; transform: scale(1.1); box-shadow: 0 4px 12px rgba(255,69,0,.3); }
.sb-link.active::after { content: ''; position: absolute; right: -18px; top: 50%; transform: translateY(-50%); width: 3px; height: 20px; background: var(--orange); border-radius: 3px 0 0 3px; transition: all 0.3s cubic-bezier(0.16,1,0.3,1); }

.sb-bottom { margin-top: auto; padding-top: 20px; }
.sb-user { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.04); border-radius: 12px; padding: 12px; border: 1px solid rgba(255,255,255,.06); transition: all 0.3s cubic-bezier(0.16,1,0.3,1); cursor: pointer; }
.sb-user:hover { background: rgba(255,255,255,.08); border-color: rgba(255,69,0,.2); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,.15); }
.sb-avatar { width: 36px; height: 36px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; overflow: hidden; transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1); }
.sb-user:hover .sb-avatar { transform: scale(1.1); box-shadow: 0 4px 12px rgba(255,69,0,.3); }
.sb-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; transition: transform 0.3s ease; }
.sb-user:hover .sb-avatar img { transform: scale(1.1); }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; line-height: 1.1; transition: color 0.3s ease; }
.sb-user:hover .sb-user-name { color: #fff; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; transition: all 0.3s ease; }
.sb-user:hover .sb-user-role { letter-spacing: 1px; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1); cursor: pointer; text-decoration: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; position: relative; overflow: hidden; }
.sb-logout::before { content: ''; position: absolute; inset: 0; background: var(--red-lt); border-radius: 8px; transform: scale(0); transition: transform 0.3s cubic-bezier(0.34,1.56,0.64,1); }
.sb-logout:hover { color: var(--red); }
.sb-logout:hover::before { transform: scale(1); }
.sb-logout i { position: relative; z-index: 1; transition: transform 0.3s ease; }
.sb-logout:hover i { transform: translateX(2px); }

@keyframes sidebarSlideIn { from { transform: translateX(-100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.sidebar { animation: sidebarSlideIn 0.6s cubic-bezier(0.16,1,0.3,1) forwards; }

@keyframes menuItemFadeIn { from { opacity: 0; transform: translateX(-20px); } to { opacity: 1; transform: translateX(0); } }
.sb-link { animation: menuItemFadeIn 0.5s cubic-bezier(0.16,1,0.3,1) forwards; opacity: 0; }
.sb-brand { animation: menuItemFadeIn 0.5s cubic-bezier(0.16,1,0.3,1) 0.1s forwards; opacity: 0; }
.sb-section-label { animation: menuItemFadeIn 0.5s cubic-bezier(0.16,1,0.3,1) forwards; opacity: 0; }
.sb-section-label:nth-of-type(1) { animation-delay: 0.2s; }
.sb-link:nth-of-type(1) { animation-delay: 0.25s; }
.sb-link:nth-of-type(2) { animation-delay: 0.3s; }
.sb-link:nth-of-type(3) { animation-delay: 0.35s; }
.sb-link:nth-of-type(4) { animation-delay: 0.4s; }
.sb-link:nth-of-type(5) { animation-delay: 0.45s; }
.sb-link:nth-of-type(6) { animation-delay: 0.5s; }
.sb-link:nth-of-type(7) { animation-delay: 0.55s; }
.sb-link:nth-of-type(8) { animation-delay: 0.6s; }
.sb-section-label:nth-of-type(2) { animation-delay: 0.65s; }
.sb-link:nth-of-type(9) { animation-delay: 0.7s; }
.sb-link:nth-of-type(10) { animation-delay: 0.75s; }
.sb-link:nth-of-type(11) { animation-delay: 0.8s; }
.sb-link:nth-of-type(12) { animation-delay: 0.85s; }
.sb-section-label:nth-of-type(3) { animation-delay: 0.9s; }
.sb-link:nth-of-type(13) { animation-delay: 0.95s; }
.sb-section-label:nth-of-type(3) + nav .sb-link:nth-of-type(1) { animation: menuItemFadeIn 0.5s cubic-bezier(0.16,1,0.3,1) 0.95s forwards; opacity: 0; }
.sb-bottom { animation: menuItemFadeIn 0.5s cubic-bezier(0.16,1,0.3,1) 1s forwards; opacity: 0; }

.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar { background: var(--card-bg); height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
.topbar-left { display: flex; flex-direction: column; }
.topbar-title { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); letter-spacing: -.5px; line-height: 1; }
.topbar-breadcrumb { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.topbar-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); cursor: pointer; font-size: 14px; text-decoration: none; transition: .2s; }
.topbar-btn:hover { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); }
.dropdown-wrap { position: relative; }
.topbar-user { display: flex; align-items: center; gap: 10px; background: var(--bg); border: 1px solid var(--border); padding: 6px 14px 6px 8px; border-radius: 12px; cursor: pointer; transition: .2s; }
.topbar-user:hover { border-color: var(--orange); }
.t-avatar { width: 32px; height: 32px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; overflow: hidden; }
.t-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.t-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.1; }
.t-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.t-chevron { color: var(--muted); font-size: 10px; margin-left: 4px; }
.dropdown-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #fff; min-width: 200px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 15px 40px rgba(0,0,0,.12); overflow: hidden; padding: 8px 0; z-index: 999; }
.dropdown-wrap:hover .dropdown-menu { display: block; }
.dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-item i { font-size: 14px; width: 18px; text-align: center; }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

.content { padding: 32px 40px; flex: 1; }

/* ---- STAT CARDS ---- */
.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 28px; }
.stat-card { background: var(--card-bg); border-radius: 14px; padding: 20px; border: 1px solid var(--border); position: relative; overflow: hidden; transition: all .2s ease; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 4px 0 0 4px; }
.sc-orange::before { background: var(--orange); }
.sc-yellow::before { background: var(--yellow); }
.sc-green::before { background: var(--green); }
.sc-blue::before { background: var(--blue); }
.stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.stat-icon-wrap { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.si-orange { background: var(--orange-lt); color: var(--orange); }
.si-yellow { background: var(--yellow-lt); color: #D97706; }
.si-green { background: var(--green-lt); color: var(--green); }
.si-blue { background: var(--blue-lt); color: var(--blue); }
.stat-value { font-family: 'Barlow Condensed', sans-serif; font-size: 28px; font-weight: 900; color: var(--text); line-height: 1; margin-bottom: 4px; }
.stat-label { font-size: 11px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }

/* ---- FILTER BAR ---- */
.action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 16px; flex-wrap: wrap; }
.filter-group { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.filter-input { padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; font-size: 13px; font-family: inherit; background: var(--card-bg); color: var(--text); outline: none; transition: .2s; }
.filter-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.btn-secondary { background: var(--card-bg); color: var(--text); border: 1px solid var(--border); padding: 10px 18px; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: .2s; text-decoration: none; }
.btn-secondary:hover { border-color: var(--orange); color: var(--orange); }

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
.data-table tbody tr:last-child td { border-bottom: none; }
.cell-name { font-weight: 700; color: var(--text); }
.cell-detail { font-size: 11px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.cell-price { font-weight: 800; color: var(--orange); white-space: nowrap; }
.status-pill { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; display: inline-flex; align-items: center; gap: 5px; }
.sp-active { background: var(--green-lt); color: var(--green); }
.sp-pending { background: var(--yellow-lt); color: #D97706; }
.action-btns { display: flex; gap: 6px; flex-wrap: nowrap; }
.btn-icon { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border); background: var(--card-bg); color: var(--muted); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 13px; transition: all .25s cubic-bezier(0.34,1.56,0.64,1); position: relative; overflow: hidden; }
.btn-icon::before { content: ''; position: absolute; inset: 0; border-radius: 8px; opacity: 0; transition: opacity .25s ease; }
.btn-icon:hover { transform: translateY(-2px) scale(1.08); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
.btn-icon:active { transform: scale(0.95); }

/* VIEW / DETAIL - Blue */
.btn-icon.view { color: var(--blue); border-color: rgba(59,130,246,.25); background: var(--blue-lt); }
.btn-icon.view::before { background: var(--blue); opacity: 0; }
.btn-icon.view:hover { background: var(--blue); color: #fff; border-color: var(--blue); box-shadow: 0 4px 14px rgba(59,130,246,.35); }

/* SUCCESS / CHECK - Green */
.btn-icon.success { color: var(--green); border-color: rgba(16,185,129,.25); background: var(--green-lt); }
.btn-icon.success::before { background: var(--green); opacity: 0; }
.btn-icon.success:hover { background: var(--green); color: #fff; border-color: var(--green); box-shadow: 0 4px 14px rgba(16,185,129,.35); }

/* DANGER / CANCEL - Red */
.btn-icon.danger { color: var(--red); border-color: rgba(239,68,68,.25); background: var(--red-lt); }
.btn-icon.danger::before { background: var(--red); opacity: 0; }
.btn-icon.danger:hover { background: var(--red); color: #fff; border-color: var(--red); box-shadow: 0 4px 14px rgba(239,68,68,.35); }

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

/* ---- MODAL DETAIL ---- */
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
.detail-full { grid-column: span 2; }
.alat-detail-list { margin-top: 8px; }
.alat-detail-item { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border-lt); }
.alat-detail-item:last-child { border-bottom: none; }
.alat-detail-name { font-weight: 700; color: var(--text); }
.alat-detail-qty { font-size: 12px; color: var(--muted); }
.alat-detail-price { font-weight: 800; color: var(--orange); }

@media(max-width: 768px) {
    .sidebar { width: 0; overflow: hidden; padding: 0; }
    .main { margin-left: 0; }
    .content { padding: 20px; }
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
    .action-bar { flex-direction: column; align-items: stretch; }
    .filter-group { width: 100%; }
    .detail-grid { grid-template-columns: 1fr; }
    .detail-full { grid-column: span 1; }
    .pagination-wrap { flex-direction: column; gap: 12px; }
}

html, body { scrollbar-width: none; -ms-overflow-style: none; }
html::-webkit-scrollbar, body::-webkit-scrollbar { display: none; }
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <a href="../dashboard/view_admin.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div><div class="sb-brand-name">HOOP BALL</div><div class="sb-brand-sub">Sistem Manajemen</div></div>
    </a>

    <div class="sb-section-label">Operasional</div>
    <nav>
        <a href="../dashboard/view_admin.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>Dashboard
        </a>
        <a href="../master/customer.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>Kelola Customer
        </a>
        <a href="../master/lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>Kelola Lapangan
        </a>
        <a href="../master/fasilitas_lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-list-check"></i></div>Kelola Fasilitas
        </a>
        <a href="../master/jadwal.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-days"></i></div>Kelola Jadwal
        </a>
        <a href="../master/promo.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div>Kelola Promo
        </a>
        <a href="../master/tipe_member.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-card"></i></div>Kelola Tipe Member
        </a>
        <a href="../master/alat.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div>Kelola Alat
        </a>
    </nav>

    <div class="sb-section-label">Transaksi</div>
    <nav>
        <a href="booking.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>Kelola Booking
        </a>
        <a href="langganan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-crown"></i></div>Kelola Langganan
        </a>
        <a href="pembelian.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-cart-shopping"></i></div>Kelola Pembelian Alat
        </a>
        <a href="pembatalan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-ban"></i></div>Kelola Pembatalan
        </a>
    </nav>

    <div class="sb-section-label">Akun</div>
    <nav>
        <a href="../profile/profile.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>Profil Saya
        </a>
    </nav>

    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
                <?php if (!empty($sidebar_photo)): ?>
                    <img src="<?= $sidebar_photo ?>" alt="Profile">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
            <div><div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="sb-user-role">KARYAWAN</div></div>
            <a href="../login/logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<main class="main">
<header class="topbar">
    <div class="topbar-left">
        <div class="topbar-title">Kelola Pembelian Alat</div>
        <div class="topbar-breadcrumb">Transaksi / Konfirmasi & Manajemen Pembelian Alat</div>
    </div>
    <div class="topbar-right">
        <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
        <a href="#" class="topbar-btn"><i class="fa-solid fa-bell"></i></a>
        <div class="dropdown-wrap">
            <div class="topbar-user">
                <div class="t-avatar">
                    <?php if (!empty($sidebar_photo)): ?>
                        <img src="<?= $sidebar_photo ?>" alt="Profile">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </div>
                <div><div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="t-role">KARYAWAN</div></div>
                <i class="fa-solid fa-chevron-down t-chevron"></i>
            </div>
            <div class="dropdown-menu">
                <a href="../profile/profile.php" class="dd-item"><i class="fa-solid fa-id-badge"></i> Profil Saya</a>
                <hr class="dd-divider">
                <a href="../login/logout.php" class="dd-item" style="color:var(--red);"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
            </div>
        </div>
    </div>
</header>

<div class="content">
    <!-- STAT CARDS -->
    <div class="stat-grid">
        <div class="stat-card sc-orange">
            <div class="stat-header"><div class="stat-icon-wrap si-orange"><i class="fa-solid fa-cart-shopping"></i></div></div>
            <div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total Transaksi</div>
        </div>
        <div class="stat-card sc-yellow">
            <div class="stat-header"><div class="stat-icon-wrap si-yellow"><i class="fa-solid fa-clock"></i></div></div>
            <div class="stat-value"><?= $stats['menunggu'] ?></div><div class="stat-label">Menunggu Konfirmasi</div>
        </div>
        <div class="stat-card sc-green">
            <div class="stat-header"><div class="stat-icon-wrap si-green"><i class="fa-solid fa-check-circle"></i></div></div>
            <div class="stat-value"><?= $stats['berhasil'] ?></div><div class="stat-label">Berhasil</div>
        </div>
        <div class="stat-card sc-blue">
            <div class="stat-header"><div class="stat-icon-wrap si-blue"><i class="fa-solid fa-money-bill-wave"></i></div></div>
            <div class="stat-value"><?= rupiahFormat($stats['total_omzet']) ?></div><div class="stat-label">Total Omzet</div>
        </div>
    </div>

    <!-- INFO BOX -->
    <div style="background: var(--blue-lt); border: 1px solid var(--blue); border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
        <i class="fa-solid fa-circle-info" style="color: var(--blue); font-size: 20px;"></i>
        <div style="font-size: 13px; color: var(--text); line-height: 1.5;">
            <strong>Peran Karyawan:</strong> Customer membuat pembelian alat melalui website. Karyawan hanya mengkonfirmasi pembayaran yang sudah dilakukan customer. 
            <span style="color: var(--muted);">Pembelian baru dengan status "Menunggu" menunggu verifikasi pembayaran Anda.</span>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="action-bar">
        <div class="filter-group">
            <form method="GET" action="" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <select name="filter_status" class="filter-input" onchange="this.form.submit()">
                    <option value="all">Semua Status</option>
                    <option value="0" <?= $filter_status === '0' ? 'selected' : '' ?>>Menunggu Konfirmasi</option>
                    <option value="1" <?= $filter_status === '1' ? 'selected' : '' ?>>Berhasil</option>
                </select>
                <input type="text" name="filter_customer" class="filter-input" placeholder="Cari customer..." value="<?= htmlspecialchars($filter_customer) ?>">
                <input type="date" name="filter_tanggal" class="filter-input" value="<?= htmlspecialchars($filter_tanggal) ?>">
                <button type="submit" class="btn-secondary"><i class="fa-solid fa-filter"></i> Filter</button>
                <?php if ($filter_status || $filter_customer || $filter_tanggal): ?>
                    <a href="pembelian.php" class="btn-secondary"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- PEMBELIAN TABLE -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-list"></i> Daftar Pembelian Alat</div>
            <span style="font-size: 12px; color: var(--muted); font-weight: 600;"><?= $total_data ?> data ditemukan</span>
        </div>
        <div class="card-body" style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 50px; text-align: center;">No.</th>
                        <th style="text-align: center;">Customer</th>
                        <th style="text-align: right;">Tanggal Beli</th>
                        <th style="text-align: center;">Detail Alat</th>
                        <th style="text-align: center;">Metode Bayar</th>
                        <th style="text-align: right;">Total Bayar</th>
                        <th style="text-align: center;">Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pembelians) > 0): ?>
                        <?php $no = $offset + 1; foreach ($pembelians as $p): 
                            $status = $status_labels[$p['Status']] ?? $status_labels[0];
                        ?>
                        <tr>
                            <td style="text-align: center; font-weight: 700; color: var(--text);"><?= $no++ ?></td>
                            <td style="text-align: center;">
                                <div class="cell-name"><?= htmlspecialchars($p['Nama_Customer']) ?></div>
                                <div class="cell-detail"><?= htmlspecialchars($p['Email'] ?? '-') ?></div>
                            </td>
                            <td style="text-align: right;"><?= formatTanggal($p['Tanggal_Beli']) ?></td>
                            <td style="text-align: center;">
                                <?php foreach ($p['details'] as $detail): ?>
                                <div style="font-size: 12px; color: var(--muted); padding: 3px 0; white-space: nowrap;">
                                    <i class="fa-solid fa-basketball" style="color: var(--orange); font-size: 10px; margin-right: 6px;"></i>
                                    <?= htmlspecialchars($detail['Nama_Alat']) ?> <span style="color: var(--text); font-weight: 600;">(<?= $detail['Jumlah'] ?>x)</span>
                                </div>
                                <?php endforeach; ?>
                            </td>
                            <td style="text-align: center;"><?= $p['Metode_Pembayaran'] ?></td>
                            <td class="cell-price" style="white-space: nowrap; text-align: right;"><?= rupiahFormat($p['Total_Bayar']) ?></td>
                            <td style="text-align: center;"><span class="status-pill <?= $status['class'] ?>"><i class="fa-solid <?= $status['icon'] ?>"></i> <?= $status['label'] ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-icon view" onclick="showDetail(<?= $p['ID_Beli'] ?>)" title="Detail"><i class="fa-solid fa-eye"></i></button>
                                    <?php if ($p['Status'] == 0): ?>
                                        <button class="btn-icon success" onclick="confirmBayar(<?= $p['ID_Beli'] ?>)" title="Konfirmasi Pembayaran"><i class="fa-solid fa-check"></i></button>
                                        <button class="btn-icon danger" onclick="confirmBatal(<?= $p['ID_Beli'] ?>)" title="Batalkan"><i class="fa-solid fa-xmark"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 50px; color: var(--muted);">
                                <i class="fa-solid fa-inbox" style="font-size: 40px; margin-bottom: 16px; opacity: .5; display: block;"></i>
                                <div style="font-size: 14px; font-weight: 700;">Belum ada data pembelian</div>
                                <div style="font-size: 12px; margin-top: 4px;">Customer belum melakukan pembelian alat</div>
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
            <div class="modal-title"><i class="fa-solid fa-file-invoice"></i> Detail Pembelian</div>
            <button class="modal-close" onclick="closeModal('modalDetail')"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="modal-body" id="detailContent"></div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('modalDetail')"><i class="fa-solid fa-xmark"></i> Tutup</button>
        </div>
    </div>
</div>

<!-- HIDDEN FORMS -->
<form method="POST" id="formKonfirmasi" style="display: none;">
    <input type="hidden" name="id_beli" id="konfirmasiId">
    <input type="hidden" name="konfirmasi_bayar" value="1">
</form>
<form method="POST" id="formBatal" style="display: none;">
    <input type="hidden" name="id_beli" id="batalId">
    <input type="hidden" name="alasan_batal" id="batalAlasan">
    <input type="hidden" name="batal_pembelian" value="1">
</form>

<script>
const pembelianData = <?= json_encode($pembelians) ?>;

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
    const pembelian = pembelianData.find(p => p.ID_Beli == id);
    if (!pembelian) return;

    const statusMap = {
        0: { label: 'Menunggu Konfirmasi', class: 'sp-pending', icon: 'fa-clock' },
        1: { label: 'Berhasil (Dikonfirmasi)', class: 'sp-active', icon: 'fa-check-circle' }
    };
    const status = statusMap[pembelian.Status] || statusMap[0];

    const tanggalBeli = pembelian.Tanggal_Beli ? new Date(pembelian.Tanggal_Beli.date || pembelian.Tanggal_Beli).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '-';

    let alatHtml = '';
    pembelian.details.forEach(d => {
        alatHtml += `
            <div class="alat-detail-item">
                <div>
                    <div class="alat-detail-name">${d.Nama_Alat}</div>
                    <div class="alat-detail-qty">${d.Jumlah} x Rp ${parseFloat(d.Harga_Alat).toLocaleString('id-ID')}</div>
                </div>
                <div class="alat-detail-price">Rp ${parseFloat(d.SubTotal).toLocaleString('id-ID')}</div>
            </div>
        `;
    });

    const html = `
        <div class="detail-grid">
            <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value status"><span class="status-pill ${status.class}"><i class="fa-solid ${status.icon}"></i> ${status.label}</span></div></div>
            <div class="detail-item"><div class="detail-label">Customer</div><div class="detail-value">${pembelian.Nama_Customer}</div><div style="font-size: 11px; color: var(--muted); margin-top: 2px;">${pembelian.Email || '-'} | ${pembelian.No_Telepon || '-'}</div></div>
            <div class="detail-item"><div class="detail-label">Tanggal Pembelian</div><div class="detail-value">${tanggalBeli}</div></div>
            <div class="detail-item"><div class="detail-label">Metode Pembayaran</div><div class="detail-value">${pembelian.Metode_Pembayaran}</div></div>
            <div class="detail-item"><div class="detail-label">Input Oleh</div><div class="detail-value">${pembelian.Nama_Karyawan_Input || 'System'}</div></div>
            <div class="detail-item detail-full">
                <div class="detail-label"><i class="fa-solid fa-boxes-stacked" style="color: var(--orange); margin-right: 6px;"></i>Detail Alat</div>
                <div class="alat-detail-list">${alatHtml}</div>
            </div>
            <div class="detail-item detail-full"><div class="detail-label">Total Bayar</div><div class="detail-value price">Rp ${parseFloat(pembelian.Total_Bayar).toLocaleString('id-ID')}</div></div>
        </div>
    `;

    document.getElementById('detailContent').innerHTML = html;
    openModal('modalDetail');
}

function confirmBayar(id) {
    Swal.fire({
        title: 'Konfirmasi Pembayaran?',
        html: 'Customer sudah melakukan pembayaran untuk pembelian alat ini?<br><span style="color: var(--muted); font-size: 12px;">Status pembelian akan berubah menjadi <strong>Berhasil</strong></span>',
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
        title: 'Batalkan Pembelian?',
        html: 'Pembelian alat ini akan dibatalkan.<br><span style="color: var(--red); font-size: 12px;"><strong>Stok akan dikembalikan</strong> ke inventory.</span>',
        icon: 'warning',
        input: 'textarea',
        inputLabel: 'Alasan Pembatalan',
        inputPlaceholder: 'Masukkan alasan pembatalan...',
        inputAttributes: { 'aria-label': 'Alasan pembatalan' },
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Batalkan',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        inputValidator: (value) => { if (!value) return 'Alasan pembatalan wajib diisi!'; }
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('batalId').value = id;
            document.getElementById('batalAlasan').value = result.value;
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

document.addEventListener('DOMContentLoaded', function () {
    const userDropdown = document.querySelector('.dropdown-wrap');
    if (userDropdown) {
        userDropdown.addEventListener('click', function (e) {
            e.stopPropagation();
            this.classList.toggle('active');
        });
    }
    document.addEventListener('click', function () {
        if (userDropdown) userDropdown.classList.remove('active');
    });
});
</script>
</body>
</html>