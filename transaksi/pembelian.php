<?php
session_start();
$path_prefix = "../";
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
// 0 = Menunggu Konfirmasi | 1 = Berhasil | 2 = Ditolak
// ============================================================================
$status_labels = [
    0 => ['label' => 'Menunggu', 'class' => 'sp-pending', 'icon' => 'fa-clock'],
    1 => ['label' => 'Berhasil', 'class' => 'sp-active', 'icon' => 'fa-check-circle'],
    2 => ['label' => 'Ditolak', 'class' => 'sp-inactive', 'icon' => 'fa-ban']
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
// PROSES TOLAK PEMBELIAN (Status -> 2 = Ditolak, stok dikembalikan)
// ============================================================================

// Helper: urai label ukuran ("S x2, M x1" / "All Size x3" / "Multi") jadi array [ukuran => qty]
function parseUkuranLabel($label, $jumlah_total) {
    $label = trim((string)$label);
    if ($label === '' || strcasecmp($label, 'Multi') === 0) {
        return ['All Size' => (int)$jumlah_total];
    }
    $out = []; $sum = 0;
    foreach (explode(',', $label) as $part) {
        $part = trim($part);
        if ($part === '') continue;
        if (preg_match('/^(.*)\sx(\d+)$/i', $part, $m)) {
            $uk = trim($m[1]); $qty = (int)$m[2];
        } else {
            $uk = $part; $qty = 0;
        }
        $out[$uk] = ($out[$uk] ?? 0) + $qty;
        $sum += $qty;
    }
    if ($sum < (int)$jumlah_total) {
        $keys = array_keys($out);
        $target = (count($keys) === 1) ? $keys[0] : 'All Size';
        $out[$target] = ($out[$target] ?? 0) + ((int)$jumlah_total - $sum);
    }
    return $out;
}

if (isset($_POST['tolak_pembelian'])) {
    $id_beli = $_POST['id_beli'];
    $alasan = trim($_POST['alasan_tolak'] ?? '');

    $q_beli = sqlsrv_query($conn, 
        "SELECT BA.ID_Beli, BA.Status, DBA.ID_Alat, DBA.Jumlah, DBA.Ukuran 
         FROM Beli_Alat BA 
         INNER JOIN Detail_Beli_Alat DBA ON BA.ID_Beli = DBA.ID_Beli 
         WHERE BA.ID_Beli = ?",
        array($id_beli)
    );
    $details = [];
    $beli_data = null;
    if ($q_beli) {
        while ($row = sqlsrv_fetch_array($q_beli, SQLSRV_FETCH_ASSOC)) {
            if (!$beli_data) $beli_data = $row;
            $details[] = ['id_alat' => $row['ID_Alat'], 'jumlah' => (int)$row['Jumlah'], 'ukuran' => $row['Ukuran'] ?? ''];
        }
    }

    if ($beli_data && (int)$beli_data['Status'] === 0) {
        $stmt = sqlsrv_query($conn, 
            "UPDATE Beli_Alat SET Status = 2, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Beli = ? AND Status = 0",
            array($nama . ' (TOLAK: ' . $alasan . ')', $id_beli)
        );
        $affected = ($stmt !== false) ? sqlsrv_rows_affected($stmt) : 0;

        if ($affected > 0) {
            foreach ($details as $d) {
                sqlsrv_query($conn, 
                    "UPDATE Alat SET Stok = Stok + ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Alat = ?",
                    array($d['jumlah'], $nama, $d['id_alat'])
                );
                foreach (parseUkuranLabel($d['ukuran'], $d['jumlah']) as $uk => $qty) {
                    if ($qty <= 0) continue;
                    sqlsrv_query($conn,
                        "UPDATE Alat_Size SET Stok = Stok + ? WHERE ID_Alat = ? AND Ukuran = ?",
                        array($qty, $d['id_alat'], $uk)
                    );
                }
            }
            header("Location: pembelian.php?status=success&msg=Pembelian ditolak. Stok telah dikembalikan.");
        } else {
            header("Location: pembelian.php?status=error&msg=Pembelian sudah diproses sebelumnya.");
        }
        exit();
    } else {
        header("Location: pembelian.php?status=error&msg=Data pembelian tidak ditemukan atau sudah diproses.");
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
                       BA.Tanggal_Beli, BA.Metode_Pembayaran, BA.Total_Bayar, BA.Status, BA.Bukti_Pembayaran,
                       BA.Created_Date, BA.Modified_Date, BA.Modified_By,
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
                        ELSE 2
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
            "SELECT DBA.Jumlah, DBA.SubTotal, DBA.Ukuran, A.Nama_Alat, A.Harga_Beli, A.Harga_Jual
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
// STATISTIK GLOBAL
// ============================================================================
$stats = [
    'total' => 0, 'menunggu' => 0, 'berhasil' => 0, 'ditolak' => 0,
    'total_omzet' => 0
];

$q_stats = sqlsrv_query($conn, "SELECT Status, Total_Bayar FROM Beli_Alat");
if ($q_stats) {
    while ($row = sqlsrv_fetch_array($q_stats, SQLSRV_FETCH_ASSOC)) {
        $stats['total']++;
        if ($row['Status'] == 0) $stats['menunggu']++;
        if ($row['Status'] == 2) $stats['ditolak']++;
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

// ============================================================================
// VARIABEL SIDEBAR - HARUS SAMA DENGAN CHECK DI SIDEBAR.PHP
// ============================================================================
$current_page = 'pembelian_alat';
$sidebar_folder = 'transaksi';

// Topbar variables
$topbar_title = 'Kelola Pembelian Alat';
$topbar_breadcrumb = 'Transaksi / Konfirmasi & Manajemen Pembelian Alat';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php include '../includes/favicon.php'; ?>
<title>Kelola Pembelian Alat | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../asset/css/global.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* ============================================================
   Style SIDEBAR, TOPBAR & CLOCK dihapus dari file ini.
   Topbar kini memakai ../includes/topbar.php + global.css
   sehingga jam & tanggal identik dengan halaman lainnya.
   ============================================================ */

.content { padding: 32px 40px; flex: 1 1 0%; width: 100%; max-width: 100%; box-sizing: border-box;  }

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
.stat-sublabel { font-size: 11px; color: var(--muted); font-weight: 600; margin-top: 4px; opacity: .8; }

/* ---- FILTER BAR ---- */
.action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 16px; flex-wrap: wrap; }
.filter-group { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.filter-input { padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; font-size: 13px; font-family: inherit; background: var(--card-bg); color: var(--text); outline: none; transition: .2s; }
.filter-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.btn-secondary { background: var(--card-bg); color: var(--text); border: 1px solid var(--border); padding: 10px 18px; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: .2s; text-decoration: none; }
.btn-secondary:hover { border-color: var(--orange); color: var(--orange); }

/* ---- TABLE ---- */
.card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); max-width: 100%; overflow: hidden; }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; }
.card-title i { color: var(--orange); font-size: 14px; }
.card-body { padding: 0; }
.data-table { width: 100%; border-collapse: collapse; min-width: 1050px; }
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
.sp-inactive { background: var(--red-lt); color: var(--red); }
.action-btns { display: flex; gap: 6px; flex-wrap: nowrap; align-items: center; justify-content: center; }

/* ===== TOMBOL PROSES ===== */
.btn-proses { display: inline-flex; align-items: center; gap: 6px; padding: 8px 14px; border-radius: 8px; border: none; background: linear-gradient(135deg, var(--orange), var(--orange-dk)); color: #fff; font-size: 12px; font-weight: 800; font-family: 'Barlow', sans-serif; cursor: pointer; transition: all .25s cubic-bezier(0.34,1.56,0.64,1); box-shadow: 0 3px 10px rgba(255,69,0,.25); white-space: nowrap; }
.btn-proses:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(255,69,0,.35); }
.btn-proses:active { transform: scale(0.96); }

/* ===== BARIS DETAIL ALAT DI TABEL ===== */
.detail-alat-row { display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--muted); padding: 3px 0; white-space: nowrap; }
.detail-alat-row .da-nama { color: var(--text-md); font-weight: 600; }
.detail-alat-row i { color: var(--orange); font-size: 10px; }
.detail-alat-row .da-qty { color: var(--text); font-weight: 700; }
.detail-alat-row .da-ukuran { background: var(--orange-lt); color: var(--orange); font-size: 10px; font-weight: 800; padding: 1px 8px; border-radius: 10px; }

.btn-icon { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border); background: var(--card-bg); color: var(--muted); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 13px; transition: all .25s cubic-bezier(0.34,1.56,0.64,1); position: relative; overflow: hidden; }
.btn-icon:hover { transform: translateY(-2px) scale(1.08); box-shadow: 0 4px 12px rgba(0,0,0,.1); }
.btn-icon:active { transform: scale(0.95); }
.btn-icon.view { color: var(--blue); border-color: rgba(59,130,246,.25); background: var(--blue-lt); }
.btn-icon.view:hover { background: var(--blue); color: #fff; border-color: var(--blue); box-shadow: 0 4px 14px rgba(59,130,246,.35); }
.btn-icon.success { color: var(--green); border-color: rgba(16,185,129,.25); background: var(--green-lt); }
.btn-icon.success:hover { background: var(--green); color: #fff; border-color: var(--green); box-shadow: 0 4px 14px rgba(16,185,129,.35); }
.btn-icon.danger { color: var(--red); border-color: rgba(239,68,68,.25); background: var(--red-lt); }
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
.alat-detail-hpp { font-size: 11px; color: var(--muted); margin-top: 2px; }
.alat-detail-price { font-weight: 800; color: var(--orange); }
.ukuran-badge { display: inline-block; background: var(--orange-lt); color: var(--orange); font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 10px; margin-left: 6px; vertical-align: middle; }
.detail-item.detail-note-tolak { background: var(--red-lt); border: 1px solid rgba(239,68,68,.25); }
.detail-note-tolak .detail-label { color: var(--red); }
.detail-note-tolak .detail-value { color: var(--red-dk); font-size: 13px; font-weight: 600; line-height: 1.5; }
.detail-id-badge { font-family: 'Barlow', sans-serif; letter-spacing: .5px; }

/* ---- BUKTI PEMBAYARAN (di modal detail) ---- */
.bukti-bayar-link { display: block; margin-top: 8px; border-radius: 10px; overflow: hidden; border: 1px solid var(--border-lt); }
.bukti-bayar-img { display: block; width: 100%; max-height: 260px; object-fit: contain; background: #FAFAFA; cursor: zoom-in; transition: opacity .2s; }
.bukti-bayar-link:hover .bukti-bayar-img { opacity: .9; }
.bukti-bayar-missing { margin-top: 8px; padding: 20px; text-align: center; background: var(--bg); border: 1px dashed var(--border); border-radius: 10px; color: var(--muted); font-size: 12.5px; font-weight: 600; }
.bukti-bayar-missing i { font-size: 20px; display: block; margin-bottom: 6px; opacity: .6; }

/* ---- CLEANUP: utility classes (ganti inline style yang berulang) ---- */
.role-info-box { background: var(--blue-lt); border: 1px solid var(--blue); border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px; }
.role-info-box i { color: var(--blue); font-size: 20px; flex-shrink: 0; }
.role-info-box .rib-text { font-size: 13px; color: var(--text); line-height: 1.5; }
.role-info-box .rib-text .rib-sub { color: var(--muted); }
.card-header-count { font-size: 12px; color: var(--muted); font-weight: 600; }
.text-center { text-align: center; }
.text-right { text-align: right; }
.nowrap { white-space: nowrap; }
.col-no { width: 50px; }
.col-customer { width: 170px; }
.col-tanggal { width: 110px; }
.col-metode { width: 110px; }
.col-total { width: 120px; }
.col-status { width: 115px; }
.col-aksi { width: 160px; }
.empty-state-cell { text-align: center; padding: 50px; color: var(--muted); }
.empty-state-cell i { font-size: 40px; margin-bottom: 16px; opacity: .5; display: block; }
.empty-state-title { font-size: 14px; font-weight: 700; }
.empty-state-sub { font-size: 12px; margin-top: 4px; }

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
            <div class="stat-header"><div class="stat-icon-wrap si-orange"><i class="fa-solid fa-cart-shopping"></i></div></div>
            <div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total Transaksi</div>
            <div class="stat-sublabel"><?= $stats['ditolak'] ?> ditolak</div>
        </div>
        <div class="stat-card sc-yellow">
            <div class="stat-header"><div class="stat-icon-wrap si-yellow"><i class="fa-solid fa-clock"></i></div></div>
            <div class="stat-value"><?= $stats['menunggu'] ?></div><div class="stat-label">Menunggu Konfirmasi</div>
            <div class="stat-sublabel">Perlu tindakan Anda</div>
        </div>
        <div class="stat-card sc-green">
            <div class="stat-header"><div class="stat-icon-wrap si-green"><i class="fa-solid fa-check-circle"></i></div></div>
            <div class="stat-value"><?= $stats['berhasil'] ?></div><div class="stat-label">Berhasil</div>
            <div class="stat-sublabel">Terkonfirmasi</div>
        </div>
        <div class="stat-card sc-blue">
            <div class="stat-header"><div class="stat-icon-wrap si-blue"><i class="fa-solid fa-money-bill-wave"></i></div></div>
            <div class="stat-value"><?= rupiahFormat($stats['total_omzet']) ?></div><div class="stat-label">Total Dana Terkumpul</div>
            <div class="stat-sublabel">Dari pembelian terkonfirmasi</div>
        </div>
    </div>

    <!-- INFO BOX -->
    <div class="role-info-box">
        <i class="fa-solid fa-circle-info"></i>
        <div class="rib-text">
            <strong>Peran Karyawan:</strong> Customer membuat pembelian alat melalui website. Karyawan hanya mengkonfirmasi pembayaran yang sudah dilakukan customer. 
            <span class="rib-sub">Pembelian baru dengan status "Menunggu" menunggu verifikasi pembayaran Anda.</span>
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
                    <option value="2" <?= $filter_status === '2' ? 'selected' : '' ?>>Ditolak</option>
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
            <span class="card-header-count"><?= $total_data ?> data ditemukan</span>
        </div>
        <div class="card-body" style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="text-align: center;">No.</th>
                        <th style="text-align: center;">Customer</th>
                        <th style="text-align: right;">Tanggal Beli</th>
                        <th style="text-align: center;">Detail Alat</th>
                        <th style="text-align: center;">Metode Bayar</th>
                        <th style="text-align: right;">Total Bayar</th>
                        <th style="text-align: center;">Status</th>
                        <th style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($pembelians) > 0): ?>
                        <?php $no = $offset + 1; foreach ($pembelians as $p): 
                            $status = $status_labels[$p['Status']] ?? $status_labels[0];
                        ?>
                        <tr>
                            <td class="text-center nowrap" style="font-weight: 700; color: var(--text);"><?= $no++ ?></td>
                            <td style="text-align: center;">
                                <div class="cell-name"><?= htmlspecialchars($p['Nama_Customer']) ?></div>
                                <div class="cell-detail"><?= htmlspecialchars($p['Email'] ?? '-') ?></div>
                            </td>
                            <td class="nowrap" style="text-align: right;"><?= formatTanggal($p['Tanggal_Beli']) ?></td>
                            <td style="text-align: center;">
                                <?php foreach ($p['details'] as $detail):
                                    $uk = trim($detail['Ukuran'] ?? '');
                                ?>
                                <div class="detail-alat-row">
                                    <i class="fa-solid fa-basketball"></i>
                                    <span class="da-nama"><?= htmlspecialchars($detail['Nama_Alat']) ?></span>
                                    <span class="da-qty"><?= $detail['Jumlah'] ?>x</span>
                                    <?php if ($uk !== '' && strcasecmp($uk, 'All Size') !== 0): ?>
                                        <span class="da-ukuran"><?= htmlspecialchars($uk) ?></span>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </td>
                            <td class="text-center"><?= htmlspecialchars($p['Metode_Pembayaran']) ?></td>
                            <td class="cell-price nowrap text-right"><?= rupiahFormat($p['Total_Bayar']) ?></td>
                            <td class="text-center"><span class="status-pill <?= $status['class'] ?>"><i class="fa-solid <?= $status['icon'] ?>"></i> <?= $status['label'] ?></span></td>
                            <td>
                                <div class="action-btns" style="justify-content: center;">
                                    <button class="btn-icon view" onclick="showDetail(<?= $p['ID_Beli'] ?>)" title="Detail"><i class="fa-solid fa-eye"></i></button>
                                    <?php if ($p['Status'] == 0): ?>
                                        <button class="btn-proses" onclick="prosesPembelian(<?= $p['ID_Beli'] ?>, '<?= htmlspecialchars($p['Nama_Customer'], ENT_QUOTES) ?>')">
                                            <i class="fa-solid fa-clipboard-check"></i> Proses
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="empty-state-cell">
                                <i class="fa-solid fa-inbox"></i>
                                <div class="empty-state-title">Belum ada data pembelian</div>
                                <div class="empty-state-sub">Customer belum melakukan pembelian alat</div>
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
<form method="POST" id="formTolak" style="display: none;">
    <input type="hidden" name="id_beli" id="tolakId">
    <input type="hidden" name="alasan_tolak" id="tolakAlasan">
    <input type="hidden" name="tolak_pembelian" value="1">
</form>

<!-- GLOBAL JS: clock, dropdown, dsb -->
<script src="../asset/js/global.js"></script>

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
        1: { label: 'Berhasil (Dikonfirmasi)', class: 'sp-active', icon: 'fa-check-circle' },
        2: { label: 'Ditolak', class: 'sp-inactive', icon: 'fa-ban' }
    };
    const status = statusMap[pembelian.Status] || statusMap[0];

    const tanggalBeli = pembelian.Tanggal_Beli ? new Date(pembelian.Tanggal_Beli.date || pembelian.Tanggal_Beli).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '-';

    let alatHtml = '';
    pembelian.details.forEach(d => {
        const ukuranBadge = (d.Ukuran && d.Ukuran !== 'All Size')
            ? `<span class="ukuran-badge">${d.Ukuran}</span>` : '';
        const hargaBeli = parseFloat(d.Harga_Beli || 0);
        const hargaJual = parseFloat(d.Harga_Jual || 0);
        const untungPcs = hargaJual - hargaBeli;
        alatHtml += `
            <div class="alat-detail-item">
                <div>
                    <div class="alat-detail-name">${d.Nama_Alat}${ukuranBadge}</div>
                    <div class="alat-detail-qty">${d.Jumlah} x Rp ${hargaJual.toLocaleString('id-ID')}</div>
                    <div class="alat-detail-hpp">Modal: Rp ${hargaBeli.toLocaleString('id-ID')} / pcs <span style="color: var(--green);">(Untung Rp ${untungPcs.toLocaleString('id-ID')}/pcs)</span></div>
                </div>
                <div class="alat-detail-price">Rp ${parseFloat(d.SubTotal).toLocaleString('id-ID')}</div>
            </div>
        `;
    });

    let tolakHtml = '';
    if (pembelian.Status == 2) {
        let alasan = '-';
        let penolak = pembelian.Modified_By || '';
        const m = penolak.match(/^(.*?)\s*\(TOLAK:\s*([\s\S]*)\)\s*$/);
        if (m) { penolak = m[1]; alasan = m[2]; }
        tolakHtml = `
            <div class="detail-item detail-full detail-note-tolak">
                <div class="detail-label"><i class="fa-solid fa-circle-exclamation" style="margin-right: 6px;"></i>Catatan Penolakan${penolak ? ' — oleh ' + penolak : ''}</div>
                <div class="detail-value">${alasan}</div>
            </div>
        `;
    }

    let buktiHtml = '';
    if (pembelian.Bukti_Pembayaran && pembelian.Bukti_Pembayaran.trim() !== '') {
        let raw = pembelian.Bukti_Pembayaran.trim();
        let src;
        if (raw.startsWith('http://') || raw.startsWith('https://') || raw.startsWith('../')) {
            src = raw;
        } else if (raw.startsWith('asset/')) {
            src = '../' + raw;
        } else {
            // Data lama / nama file lepas (sebelum fitur upload wajib aktif)
            src = '../asset/image/bukti_pembayaran/' + raw;
        }
        buktiHtml = `
            <div class="detail-item detail-full">
                <div class="detail-label"><i class="fa-solid fa-receipt" style="color: var(--orange); margin-right: 6px;"></i>Bukti Pembayaran</div>
                <a href="${src}" target="_blank" class="bukti-bayar-link">
                    <img src="${src}" class="bukti-bayar-img" onerror="this.closest('.bukti-bayar-link').outerHTML='<div class=\\'bukti-bayar-missing\\'><i class=\\'fa-solid fa-image-slash\\'></i> Foto bukti pembayaran tidak ditemukan</div>';">
                </a>
            </div>
        `;
    } else {
        buktiHtml = `
            <div class="detail-item detail-full">
                <div class="detail-label"><i class="fa-solid fa-receipt" style="color: var(--orange); margin-right: 6px;"></i>Bukti Pembayaran</div>
                <div class="bukti-bayar-missing"><i class="fa-solid fa-image-slash"></i> Belum ada bukti pembayaran diupload</div>
            </div>
        `;
    }

    const html = `
        <div class="detail-grid">
            <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value status"><span class="status-pill ${status.class}"><i class="fa-solid ${status.icon}"></i> ${status.label}</span></div></div>
            <div class="detail-item"><div class="detail-label">ID Transaksi</div><div class="detail-value detail-id-badge">#${String(pembelian.ID_Beli).padStart(4, '0')}</div></div>
            <div class="detail-item"><div class="detail-label">Customer</div><div class="detail-value">${pembelian.Nama_Customer}</div><div style="font-size: 11px; color: var(--muted); margin-top: 2px;">${pembelian.Email || '-'} | ${pembelian.No_Telepon || '-'}</div></div>
            <div class="detail-item"><div class="detail-label">Tanggal Pembelian</div><div class="detail-value">${tanggalBeli}</div></div>
            <div class="detail-item"><div class="detail-label">Metode Pembayaran</div><div class="detail-value">${pembelian.Metode_Pembayaran}</div></div>
            <div class="detail-item"><div class="detail-label">Input Oleh</div><div class="detail-value">${pembelian.Nama_Karyawan_Input || 'System'}</div></div>
            ${tolakHtml}
            <div class="detail-item detail-full">
                <div class="detail-label"><i class="fa-solid fa-boxes-stacked" style="color: var(--orange); margin-right: 6px;"></i>Detail Alat (${pembelian.details.length} item)</div>
                <div class="alat-detail-list">${alatHtml}</div>
            </div>
            ${buktiHtml}
            <div class="detail-item detail-full"><div class="detail-label">Total Bayar</div><div class="detail-value price">Rp ${parseFloat(pembelian.Total_Bayar).toLocaleString('id-ID')}</div></div>
        </div>
    `;

    document.getElementById('detailContent').innerHTML = html;
    openModal('modalDetail');
}

// ============================================
// PROSES PEMBELIAN — 1 TOMBOL, 2 PILIHAN
// ============================================
function prosesPembelian(id, nama) {
    Swal.fire({
        title: 'Proses Pembelian #' + String(id).padStart(4, '0'),
        html: `Transaksi atas nama <strong>${nama}</strong>.<br><span style="color: #6B7280; font-size: 12.5px;">Verifikasi jika pembayaran sudah diterima, atau tolak pesanan ini.</span>`,
        icon: 'question',
        showDenyButton: true,
        showCancelButton: true,
        confirmButtonText: '<i class="fa-solid fa-check"></i> Verifikasi',
        denyButtonText: '<i class="fa-solid fa-ban"></i> Tolak',
        cancelButtonText: 'Batal',
        confirmButtonColor: '#10B981',
        denyButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('konfirmasiId').value = id;
            document.getElementById('formKonfirmasi').submit();
        } else if (result.isDenied) {
            Swal.fire({
                title: 'Tolak Pembelian #' + String(id).padStart(4, '0'),
                html: '<span style="font-size: 13px; color: #6B7280;">Status akan menjadi <strong style="color:#EF4444">Ditolak</strong> dan stok alat <strong>dikembalikan</strong> ke inventory (termasuk stok per ukuran).</span>',
                icon: 'warning',
                input: 'textarea',
                inputLabel: 'Alasan Penolakan',
                inputPlaceholder: 'Contoh: pembayaran tidak masuk dalam batas waktu...',
                inputAttributes: { 'aria-label': 'Alasan penolakan' },
                inputValidator: (value) => { if (!value || !value.trim()) return 'Alasan penolakan wajib diisi!'; },
                showCancelButton: true,
                confirmButtonText: 'Ya, Tolak Pesanan',
                cancelButtonText: 'Kembali',
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                reverseButtons: true
            }).then((r2) => {
                if (r2.isConfirmed) {
                    document.getElementById('tolakId').value = id;
                    document.getElementById('tolakAlasan').value = r2.value.trim();
                    document.getElementById('formTolak').submit();
                }
            });
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