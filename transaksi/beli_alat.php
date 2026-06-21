<?php
ob_start();
session_start();
date_default_timezone_set("Asia/Jakarta");
include '../includes/config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard/dashboard.php';</script>";
    exit();
}
$role = $_SESSION['role'];
$nama = substr($_SESSION['nama'] ?? 'USER', 0, 50);

$profile_photo = '';
$id_karyawan_session = $_SESSION['id_karyawan'] ?? $_SESSION['id_akun'] ?? '';
if (!empty($id_karyawan_session)) {
    $stmt_photo = sqlsrv_query($conn, "SELECT Photo_Profile FROM Karyawan WHERE ID_Karyawan = ?", array($id_karyawan_session));
    if ($stmt_photo !== false) {
        $row_photo = sqlsrv_fetch_array($stmt_photo, SQLSRV_FETCH_ASSOC);
        if ($row_photo && !empty($row_photo['Photo_Profile'])) {
            $photo_path = $row_photo['Photo_Profile'];
            if (strpos($photo_path, 'uploads/profiles/') !== false) {
                $profile_photo = '../' . $photo_path;
            } else {
                $profile_photo = '../uploads/profiles/' . $photo_path;
            }
        }
    }
}

function safeQuery($conn, $sql, $params = []) {
    $stmt = empty($params) ? sqlsrv_query($conn, $sql) : sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
        error_log("[PEMBELIAN-ERROR] SQL Error: " . print_r($errors, true));
        return false;
    }
    return $stmt;
}

function safeFetch($stmt) {
    if ($stmt === false || $stmt === null) return false;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function getLastSqlError($conn) {
    $errors = sqlsrv_errors(SQLSRV_ERR_ALL);
    if (!empty($errors) && isset($errors[0]['message'])) {
        return $errors[0]['message'];
    }
    return 'Unknown database error';
}

function rupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }

// ==== CONFIRM PAYMENT (Karyawan) ====
if (isset($_GET['confirm_id'])) {
    $confirm_id = intval($_GET['confirm_id']);
    $id_karyawan = intval($id_karyawan_session);

    // Start transaction
    sqlsrv_begin_transaction($conn);

    try {
        // Get details for stock reduction
        $detail_query = safeQuery($conn, "SELECT ID_Alat, Jumlah FROM Detail_Beli_Alat WHERE ID_Beli = ?", [$confirm_id]);
        if ($detail_query) {
            while ($detail = sqlsrv_fetch_array($detail_query, SQLSRV_FETCH_ASSOC)) {
                // Reduce stock
                $update_stock = safeQuery($conn, "UPDATE Alat SET Stok = Stok - ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Alat = ? AND Stok >= ?", 
                    [$detail['Jumlah'], $nama, $detail['ID_Alat'], $detail['Jumlah']]);
                if ($update_stock === false) {
                    throw new Exception('Gagal mengurangi stok untuk alat ID: ' . $detail['ID_Alat']);
                }
            }
        }

        // Update status to Berhasil (1)
        $result = safeQuery($conn, "UPDATE Beli_Alat SET Status = 1, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Beli = ? AND Status = 0", [$nama, $confirm_id]);
        if ($result === false) {
            throw new Exception('Gagal mengupdate status pembelian.');
        }

        sqlsrv_commit($conn);
        ob_end_clean();
        header("Location: beli_alat.php?status=success&msg=" . urlencode("Pembayaran berhasil dikonfirmasi! Stok alat telah dikurangi."));
        exit();
    } catch (Exception $e) {
        sqlsrv_rollback($conn);
        ob_end_clean();
        header("Location: beli_alat.php?status=error&msg=" . urlencode($e->getMessage()));
        exit();
    }
}

// ==== CANCEL TRANSACTION ====
if (isset($_GET['cancel_id'])) {
    $cancel_id = intval($_GET['cancel_id']);
    $result = safeQuery($conn, "UPDATE Beli_Alat SET Status = 0, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Beli = ? AND Status = 1", [$nama, $cancel_id]);
    if ($result !== false) {
        ob_end_clean();
        header("Location: beli_alat.php?status=success&msg=" . urlencode("Transaksi dibatalkan."));
    } else {
        ob_end_clean();
        header("Location: beli_alat.php?status=error&msg=" . urlencode('Gagal membatalkan transaksi: ' . getLastSqlError($conn)));
    }
    exit();
}

// ==== DETAIL MODAL ====
$detail_data = null;
$detail_items = [];
$show_detail = false;
if (isset($_GET['detail_id'])) {
    $detail_id = intval($_GET['detail_id']);
    $r = safeQuery($conn, "SELECT B.*, C.Nama_Customer, C.Email, C.No_Telepon, K.Nama_Karyawan FROM Beli_Alat B INNER JOIN Customer C ON B.ID_Customer = C.ID_Customer INNER JOIN Karyawan K ON B.ID_Karyawan = K.ID_Karyawan WHERE B.ID_Beli = ?", [$detail_id]);
    if ($r) {
        $detail_data = safeFetch($r);
        $show_detail = ($detail_data !== false && $detail_data !== null);

        if ($show_detail) {
            $items_q = safeQuery($conn, "SELECT D.*, A.Nama_Alat, A.Harga_Alat, A.Photo_Alat FROM Detail_Beli_Alat D INNER JOIN Alat A ON D.ID_Alat = A.ID_Alat WHERE D.ID_Beli = ?", [$detail_id]);
            if ($items_q) {
                while ($item = sqlsrv_fetch_array($items_q, SQLSRV_FETCH_ASSOC)) {
                    $detail_items[] = $item;
                }
            }
        }
    }
}

// ==== FILTER & QUERY ====
$where_clauses = ["1=1"];
$params = [];

if (isset($_GET['f_status']) && $_GET['f_status'] !== '') {
    $where_clauses[] = "B.Status = ?";
    $params[] = intval($_GET['f_status']);
}
if (isset($_GET['f_customer']) && $_GET['f_customer'] !== '') {
    $where_clauses[] = "C.Nama_Customer LIKE ?";
    $params[] = '%' . $_GET['f_customer'] . '%';
}
if (isset($_GET['f_tanggal_awal']) && $_GET['f_tanggal_awal'] !== '') {
    $where_clauses[] = "CAST(B.Tanggal_Beli AS DATE) >= ?";
    $params[] = $_GET['f_tanggal_awal'];
}
if (isset($_GET['f_tanggal_akhir']) && $_GET['f_tanggal_akhir'] !== '') {
    $where_clauses[] = "CAST(B.Tanggal_Beli AS DATE) <= ?";
    $params[] = $_GET['f_tanggal_akhir'];
}

$where_sql = implode(" AND ", $where_clauses);

$sort_by = "B.Created_Date DESC";
if (isset($_GET['f_sort'])) {
    switch ($_GET['f_sort']) {
        case 'tanggal_asc': $sort_by = "B.Tanggal_Beli ASC"; break;
        case 'tanggal_desc': $sort_by = "B.Tanggal_Beli DESC"; break;
        case 'total_asc': $sort_by = "B.Total_Bayar ASC"; break;
        case 'total_desc': $sort_by = "B.Total_Bayar DESC"; break;
    }
}

// Stats
$q_total = safeQuery($conn, "SELECT COUNT(*) as t FROM Beli_Alat");
$total_beli = 0;
if ($q_total) { $row = safeFetch($q_total); $total_beli = $row['t'] ?? 0; }

$q_menunggu = safeQuery($conn, "SELECT COUNT(*) as t FROM Beli_Alat WHERE Status = 0");
$menunggu_count = 0;
if ($q_menunggu) { $row = safeFetch($q_menunggu); $menunggu_count = $row['t'] ?? 0; }

$q_berhasil = safeQuery($conn, "SELECT COUNT(*) as t FROM Beli_Alat WHERE Status = 1");
$berhasil_count = 0;
if ($q_berhasil) { $row = safeFetch($q_berhasil); $berhasil_count = $row['t'] ?? 0; }

$q_omzet = safeQuery($conn, "SELECT ISNULL(SUM(Total_Bayar), 0) as t FROM Beli_Alat WHERE Status = 1");
$total_omzet = 0;
if ($q_omzet) { $row = safeFetch($q_omzet); $total_omzet = $row['t'] ?? 0; }

// Pagination
$limit = 12;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

$count_sql = "SELECT COUNT(*) as t FROM Beli_Alat B INNER JOIN Customer C ON B.ID_Customer = C.ID_Customer WHERE $where_sql";
$count_res = safeQuery($conn, $count_sql, $params);
$total_data = 0;
if ($count_res) { $row = safeFetch($count_res); $total_data = $row['t'] ?? 0; }

$total_pages = max(1, ceil($total_data / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

// Main query
$query_sql = "SELECT B.*, C.Nama_Customer, C.Email, K.Nama_Karyawan FROM Beli_Alat B INNER JOIN Customer C ON B.ID_Customer = C.ID_Customer INNER JOIN Karyawan K ON B.ID_Karyawan = K.ID_Karyawan WHERE $where_sql ORDER BY $sort_by OFFSET " . intval($offset) . " ROWS FETCH NEXT " . intval($limit) . " ROWS ONLY";
$query = safeQuery($conn, $query_sql, $params);

$filter_url = "";
if (isset($_GET['f_sort'])) $filter_url .= "&f_sort=" . urlencode($_GET['f_sort']);
if (isset($_GET['f_status'])) $filter_url .= "&f_status=" . urlencode($_GET['f_status']);
if (isset($_GET['f_customer'])) $filter_url .= "&f_customer=" . urlencode($_GET['f_customer']);
if (isset($_GET['f_tanggal_awal'])) $filter_url .= "&f_tanggal_awal=" . urlencode($_GET['f_tanggal_awal']);
if (isset($_GET['f_tanggal_akhir'])) $filter_url .= "&f_tanggal_akhir=" . urlencode($_GET['f_tanggal_akhir']);

$status_labels = [0 => ['label'=>'Menunggu Konfirmasi','class'=>'badge-menunggu','icon'=>'fa-clock'], 1 => ['label'=>'Berhasil','class'=>'badge-berhasil','icon'=>'fa-check-circle']];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Pembelian Alat | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10);
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10);
    --yellow: #F59E0B; --yellow-lt: rgba(245,158,11,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --text-md: #374151; --muted: #6B7280; --bg: #F3F4F6;
    --shopee-orange: #EE4D2D;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

.sidebar { width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 28px 18px; border-right: 1px solid rgba(255,255,255,.04); z-index: 200; overflow-y: auto; scrollbar-width: none; }
.sidebar::-webkit-scrollbar { display: none; }
.sb-brand { display: flex; align-items: center; gap: 12px; padding: 0 8px; margin-bottom: 36px; text-decoration: none; }
.sb-icon { width: 40px; height: 40px; background: var(--orange); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; flex-shrink: 0; box-shadow: 0 4px 14px rgba(255,69,0,.4); }
.sb-brand-name { font-family: 'Barlow Condensed', sans-serif; font-size: 20px; font-weight: 900; color: #fff; letter-spacing: 1px; }
.sb-brand-sub { font-size: 9px; color: #4B5563; font-weight: 700; text-transform: uppercase; }
.sb-section-label { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #374151; letter-spacing: .8px; padding: 0 10px; margin: 22px 0 8px; }
.sb-link { display: flex; align-items: center; gap: 12px; color: #6B7280; text-decoration: none; padding: 10px 12px; border-radius: 10px; margin-bottom: 2px; font-size: 13px; font-weight: 600; transition: all .2s ease; }
.sb-link .sb-icon-wrap { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: .2s; flex-shrink: 0; background: rgba(255,255,255,.04); }
.sb-link:hover { color: #E5E7EB; background: rgba(255,255,255,.04); }
.sb-link:hover .sb-icon-wrap { background: rgba(255,255,255,.08); }
.sb-link.active { color: #fff; background: var(--orange-lt); }
.sb-link.active .sb-icon-wrap { background: var(--orange); color: #fff; }
.sb-bottom { margin-top: auto; padding-top: 20px; }
.sb-user { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.04); border-radius: 12px; padding: 12px; border: 1px solid rgba(255,255,255,.06); }
.sb-avatar { width: 36px; height: 36px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; overflow: hidden; }
.sb-avatar img { width: 100%; height: 100%; object-fit: cover; }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; line-height: 1.1; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
.sb-logout:hover { color: var(--red); background: rgba(239,68,68,.1); }

.main { margin-left: calc(var(--sidebar-w) - 1px); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar { background: var(--card-bg); height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 0 rgba(0,0,0,.04); }
.topbar-left { display: flex; flex-direction: column; }
.topbar-title { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); letter-spacing: -.5px; line-height: 1; }
.topbar-breadcrumb { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.topbar-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); cursor: pointer; font-size: 14px; text-decoration: none; transition: .2s; position: relative; }
.topbar-btn:hover { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); }
.dropdown-wrap { position: relative; }
.topbar-user { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid var(--border); padding: 6px 14px 6px 8px; border-radius: 12px; cursor: pointer; transition: .2s; }
.topbar-user:hover { border-color: var(--orange); }
.t-avatar { width: 32px; height: 32px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; overflow: hidden; flex-shrink: 0; }
.t-avatar img { width: 100%; height: 100%; object-fit: cover; }
.t-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.1; text-transform: uppercase; }
.t-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.t-chevron { color: var(--muted); font-size: 10px; margin-left: 4px; }
.dropdown-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #fff; min-width: 200px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 15px 40px rgba(0,0,0,.12); overflow: hidden; padding: 8px 0; z-index: 999; }
.dropdown-wrap.active .dropdown-menu { display: block; }
.dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-item i { font-size: 14px; width: 18px; text-align: center; }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

.content { padding: 32px 40px; flex: 1; }
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
.page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }

.stat-chips { display: flex; gap: 10px; flex-wrap: wrap; }
.stat-chip { display: flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 10px; font-size: 12px; font-weight: 700; transition: all .2s; }
.stat-chip:hover { transform: translateY(-2px); }
.chip-yellow { background: var(--yellow-lt); color: var(--yellow); }
.chip-green { background: var(--green-lt); color: var(--green); }
.chip-blue { background: var(--blue-lt); color: var(--blue); }
.chip-orange { background: var(--orange-lt); color: var(--orange); }
.chip-val { font-family: 'Barlow Condensed'; font-size: 20px; font-weight: 900; }

.action-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.search-box { position: relative; width: 300px; }
.search-box i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; }
.search-box input { width: 100%; padding: 10px 14px 10px 40px; background: var(--card-bg); border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; outline: none; transition: all .2s; color: var(--text); }
.search-box input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.search-box input::placeholder { color: #9CA3AF; }

/* Table */
.data-table { width: 100%; border-collapse: collapse; background: var(--card-bg); border-radius: 12px; overflow: hidden; border: 1px solid var(--border); }
.data-table thead { background: #FAFBFD; }
.data-table th { padding: 14px 16px; text-align: left; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; border-bottom: 1px solid var(--border); }
.data-table td { padding: 14px 16px; font-size: 13px; border-bottom: 1px solid var(--border-lt); vertical-align: middle; }
.data-table tbody tr { transition: all .2s ease; }
.data-table tbody tr:hover { background: #FFF7ED; }
.data-table tbody tr:last-child td { border-bottom: none; }

.badge-menunggu { background: var(--yellow-lt); color: var(--yellow); padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; display: inline-flex; align-items: center; gap: 5px; border: 1px solid rgba(245,158,11,.2); }
.badge-berhasil { background: var(--green-lt); color: var(--green); padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; display: inline-flex; align-items: center; gap: 5px; border: 1px solid rgba(16,185,129,.2); }

.action-btns { display: flex; gap: 6px; }
.btn-action { width: 32px; height: 32px; border-radius: 8px; border: 1.5px solid transparent; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 13px; transition: all .25s ease; text-decoration: none; }
.btn-view { background: var(--blue-lt); color: var(--blue); border-color: rgba(59,130,246,.2); }
.btn-view:hover { background: var(--blue); color: #fff; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(59,130,246,.3); }
.btn-confirm { background: var(--green-lt); color: var(--green); border-color: rgba(16,185,129,.2); }
.btn-confirm:hover { background: var(--green); color: #fff; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(16,185,129,.3); }
.btn-cancel { background: var(--red-lt); color: var(--red); border-color: rgba(239,68,68,.2); }
.btn-cancel:hover { background: var(--red); color: #fff; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(239,68,68,.3); }

/* Pagination */
.pagination-wrap { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; }
.pagination-info { font-size: 12px; color: var(--muted); font-weight: 600; }
.pagination-info strong { color: var(--text); font-weight: 800; }
.pagination-nav { display: flex; align-items: center; gap: 4px; }
.page-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 10px; border-radius: 10px; font-size: 13px; font-weight: 700; font-family: 'Barlow', sans-serif; text-decoration: none; cursor: pointer; transition: all .2s ease; border: 1.5px solid var(--border); color: var(--text-md); background: #fff; }
.page-btn:hover:not(.disabled):not(.active) { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); transform: translateY(-1px); }
.page-btn.active { background: var(--orange); color: #fff; border-color: var(--orange); box-shadow: 0 4px 12px rgba(255,69,0,.3); font-weight: 800; }
.page-btn.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
.page-btn i { font-size: 11px; }
.page-ellipsis { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; color: var(--muted); font-size: 13px; font-weight: 800; }

/* Filter */
.filter-dropdown-wrap { position: relative; display: inline-block; }
.btn-filter { display: inline-flex; align-items: center; gap: 8px; background-color: var(--orange); color: #ffffff; padding: 11px 20px; border-radius: 10px; font-size: 13px; font-weight: 800; text-transform: uppercase; border: none; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(255,69,0,0.2); }
.btn-filter:hover { background-color: var(--orange-dk); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(255,69,0,0.35); }
.btn-filter i.arrow-icon { font-size: 10px; transition: transform 0.3s; }
.filter-card { position: absolute; top: calc(100% + 10px); right: 0; background: #ffffff; border-radius: 16px; border: 1px solid var(--border); padding: 24px; width: 340px; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12); z-index: 50; display: none; }
.filter-card.open { display: block; animation: slideFilter 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes slideFilter { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.filter-card h4 { font-size: 15px; font-weight: 800; color: var(--text); margin-bottom: 20px; text-align: left; }
.filter-group { margin-bottom: 16px; text-align: left; }
.filter-group label { display: block; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
.filter-input { width: 100%; padding: 10px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; outline: none; transition: all .2s; color: var(--text); }
.filter-input:focus { border-color: var(--orange); }
.filter-input.select { appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 40px; cursor: pointer; }
.filter-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.filter-buttons { display: flex; gap: 10px; margin-top: 24px; }
.btn-filter-apply { flex: 1.2; background: var(--orange); color: white; border: none; padding: 12px; border-radius: 10px; font-weight: 800; font-size: 12px; text-transform: uppercase; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all .2s; }
.btn-filter-apply:hover { background: var(--orange-dk); }
.btn-filter-reset { flex: 1; background: var(--border-lt); color: var(--text-md); border: 1px solid var(--border); padding: 12px; border-radius: 10px; font-weight: 800; font-size: 12px; text-transform: uppercase; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all .2s; }
.btn-filter-reset:hover { background: #E5E7EB; }

/* Detail Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(6px); display: none; align-items: center; justify-content: center; z-index: 2000; }
.modal-overlay.open { display: flex; }
.modal-box { background: #fff; border-radius: 20px; width: 560px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 60px rgba(0,0,0,.2); position: relative; }
.modal-header { padding: 28px 32px 20px; border-bottom: 1px solid var(--border); }
.modal-subtitle { font-size: 10px; font-weight: 800; color: var(--orange); text-transform: uppercase; margin-bottom: 6px; letter-spacing: .8px; }
.modal-title { font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 900; color: var(--text); }
.modal-body { padding: 24px 32px 32px; }
.modal-close { position: absolute; top: 20px; right: 20px; width: 36px; height: 36px; border: none; background: var(--border-lt); border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 16px; transition: all .2s; z-index: 10; }
.modal-close:hover { background: var(--red-lt); color: var(--red); }

.detail-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px; }
.detail-info-item { background: #FAFBFD; border: 1px solid var(--border-lt); border-radius: 14px; padding: 14px; }
.detail-info-label { font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
.detail-info-label i { color: var(--orange); font-size: 12px; }
.detail-info-value { font-size: 16px; font-weight: 800; color: var(--text); }

.detail-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 20px; font-size: 12px; font-weight: 800; letter-spacing: .3px; margin-bottom: 14px; text-transform: uppercase; }

.detail-items-table { width: 100%; border-collapse: collapse; margin-top: 16px; }
.detail-items-table th { padding: 10px 12px; text-align: left; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; background: #FAFBFD; border-bottom: 1px solid var(--border); }
.detail-items-table td { padding: 12px; font-size: 13px; border-bottom: 1px solid var(--border-lt); }
.detail-items-table tr:last-child td { border-bottom: none; }
.detail-item-img { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; background: #f0f0f0; }
.detail-item-img-placeholder { width: 40px; height: 40px; border-radius: 8px; background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); display: flex; align-items: center; justify-content: center; }
.detail-item-img-placeholder i { font-size: 16px; color: var(--orange); opacity: .5; }

.detail-total-row { display: flex; justify-content: space-between; align-items: center; padding: 16px 0; border-top: 2px solid var(--border); margin-top: 8px; }
.detail-total-label { font-size: 14px; font-weight: 700; color: var(--text); }
.detail-total-value { font-size: 22px; font-weight: 900; color: var(--shopee-orange); font-family: 'Barlow Condensed', sans-serif; }

.empty-state { text-align: center; padding: 80px 20px; color: var(--muted); }
.empty-state i { font-size: 64px; margin-bottom: 20px; opacity: .3; display: block; }
.empty-state div { font-size: 16px; font-weight: 700; }
.empty-state p { font-size: 13px; margin-top: 8px; opacity: .7; }

#clock-display { display: flex; align-items: center; gap: 16px; }
.clock-time { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--orange); display: flex; align-items: center; gap: 6px; line-height: 1; }
.clock-colon { color: var(--orange); opacity: .5; animation: blink 1s infinite; }
@keyframes blink { 0%, 100% { opacity: .5; } 50% { opacity: 1; } }
.clock-divider { width: 1.5px; height: 28px; background-color: var(--border); }
.clock-date { font-family: 'Barlow', sans-serif; font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }

html, body { scrollbar-width: none; -ms-overflow-style: none; }
html::-webkit-scrollbar, body::-webkit-scrollbar { display: none; }
body.swal2-shown, html.swal2-shown { padding-right: 0px !important; }

@media(max-width:768px){
    .sidebar{width:0;overflow:hidden;padding:0;}
    .main{margin-left:0;}
    .content{padding:20px;}
    .modal-box{width:90%;margin:20px;}
    .search-box{width:100%;}
    .action-bar{flex-direction:column;align-items:stretch;}
    .detail-info-grid{grid-template-columns:1fr;}
    .filter-card{width:280px;}
}
</style>
</head>
<body>

<!-- DETAIL MODAL -->
<div class="modal-overlay <?= $show_detail ? 'open' : '' ?>" id="modalDetail">
    <div class="modal-box">
        <button type="button" class="modal-close" onclick="closeModal()" title="Tutup"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header">
            <div class="modal-subtitle">Detail Transaksi</div>
            <div class="modal-title">Pembelian Alat #<?= $detail_data['ID_Beli'] ?? '' ?></div>
        </div>
        <div class="modal-body">
            <?php if ($detail_data): ?>
                <div class="detail-status-badge <?= $detail_data['Status'] == 1 ? 'badge-berhasil' : 'badge-menunggu' ?>">
                    <i class="fa-solid fa-circle"></i> <?= $detail_data['Status'] == 1 ? 'Berhasil' : 'Menunggu Konfirmasi' ?>
                </div>

                <div class="detail-info-grid">
                    <div class="detail-info-item">
                        <div class="detail-info-label"><i class="fa-solid fa-user"></i> Customer</div>
                        <div class="detail-info-value"><?= htmlspecialchars($detail_data['Nama_Customer']) ?></div>
                    </div>
                    <div class="detail-info-item">
                        <div class="detail-info-label"><i class="fa-solid fa-envelope"></i> Email</div>
                        <div class="detail-info-value" style="font-size:13px;"><?= htmlspecialchars($detail_data['Email'] ?? '-') ?></div>
                    </div>
                    <div class="detail-info-item">
                        <div class="detail-info-label"><i class="fa-solid fa-calendar"></i> Tanggal Beli</div>
                        <div class="detail-info-value">
                            <?php 
                                $tgl = $detail_data['Tanggal_Beli'];
                                echo ($tgl instanceof DateTime) ? $tgl->format('d M Y H:i') : date('d M Y H:i', strtotime($tgl));
                            ?>
                        </div>
                    </div>
                    <div class="detail-info-item">
                        <div class="detail-info-label"><i class="fa-solid fa-credit-card"></i> Metode Pembayaran</div>
                        <div class="detail-info-value"><?= htmlspecialchars($detail_data['Metode_Pembayaran']) ?></div>
                    </div>
                    <div class="detail-info-item">
                        <div class="detail-info-label"><i class="fa-solid fa-user-tie"></i> Karyawan</div>
                        <div class="detail-info-value"><?= htmlspecialchars($detail_data['Nama_Karyawan']) ?></div>
                    </div>
                    <div class="detail-info-item">
                        <div class="detail-info-label"><i class="fa-solid fa-box"></i> Jumlah Item</div>
                        <div class="detail-info-value"><?= count($detail_items) ?> item</div>
                    </div>
                </div>

                <h4 style="font-size:14px; font-weight:800; color:var(--text); margin-bottom:12px; text-transform:uppercase; letter-spacing:0.5px;">
                    <i class="fa-solid fa-list" style="color:var(--orange); margin-right:6px;"></i> Daftar Alat
                </h4>
                <table class="detail-items-table">
                    <thead>
                        <tr>
                            <th>Alat</th>
                            <th>Nama</th>
                            <th>Harga</th>
                            <th>Jumlah</th>
                            <th style="text-align:right;">Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detail_items as $item): 
                            $item_photo = !empty($item['Photo_Alat']) ? '../' . str_replace('../', '', $item['Photo_Alat']) : '';
                        ?>
                        <tr>
                            <td>
                                <?php if (!empty($item_photo)): ?>
                                    <img src="<?= htmlspecialchars($item_photo) ?>" class="detail-item-img" alt="" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="detail-item-img-placeholder" style="display:none;"><i class="fa-solid fa-toolbox"></i></div>
                                <?php else: ?>
                                    <div class="detail-item-img-placeholder"><i class="fa-solid fa-toolbox"></i></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($item['Nama_Alat']) ?></td>
                            <td><?= rupiah($item['Harga_Alat']) ?></td>
                            <td><?= intval($item['Jumlah']) ?> pcs</td>
                            <td style="text-align:right; font-weight:800; color:var(--shopee-orange);"><?= rupiah($item['SubTotal']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="detail-total-row">
                    <span class="detail-total-label">Total Pembayaran</span>
                    <span class="detail-total-value"><?= rupiah($detail_data['Total_Bayar']) ?></span>
                </div>

                <?php if ($detail_data['Status'] == 0): ?>
                <div style="display:flex; gap:10px; margin-top:20px;">
                    <a href="?confirm_id=<?= intval($detail_data['ID_Beli']) ?>" class="btn-filter-apply" style="flex:1; text-decoration:none;" onclick="return confirmConfirm(this)">
                        <i class="fa-solid fa-check"></i> Konfirmasi Pembayaran
                    </a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- SIDEBAR -->
<aside class="sidebar">
    <a href="../dashboard/view_admin.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">Sistem Managemen</div>
        </div>
    </a>
    <div class="sb-section-label">Operasional</div>
    <nav>
        <a href="../dashboard/view_admin.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>Dashboard</a>
        <a href="customer.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>Kelola Customer</a>
        <a href="lapangan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>Kelola Lapangan</a>
        <a href="fasilitas_lapangan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-list-check"></i></div>Kelola Fasilitas</a>
        <a href="jadwal.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-calendar-days"></i></div>Kelola Jadwal</a>
        <a href="promo.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div>Kelola Promo</a>
        <a href="tipe_member.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-id-card"></i></div>Kelola Tipe Member</a>
        <a href="alat.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div>Kelola Alat</a>
    </nav>
    <div class="sb-section-label">Transaksi</div>
    <nav>
        <a href="../transaksi/booking.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>Kelola Booking</a>
        <a href="../transaksi/langganan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-crown"></i></div>Kelola Langganan</a>
        <a href="beli_alat.php" class="sb-link active"><div class="sb-icon-wrap"><i class="fa-solid fa-cart-shopping"></i></div>Kelola Pembelian Alat</a>
        <a href="../transaksi/pembatalan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-ban"></i></div>Kelola Pembatalan</a>
    </nav>
    <div class="sb-section-label">Akun</div>
    <a href="../profile/profile.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>Profil Saya</a>
    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
                <?php if (!empty($profile_photo)): ?>
                    <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
            <div>
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                <div class="sb-user-role"><?= strtoupper(htmlspecialchars($role)) ?></div>
            </div>
            <a href="../login/logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Kelola Pembelian Alat</div>
            <div class="topbar-breadcrumb">Transaksi / Pembelian Alat</div>
        </div>
        <div class="topbar-right">
            <div id="clock-display">
                <div class="clock-time">
                    <span id="clock-h">00</span><span class="clock-colon">:</span>
                    <span id="clock-m">00</span><span class="clock-colon">:</span>
                    <span id="clock-s">00</span>
                </div>
                <div class="clock-divider"></div>
                <div class="clock-date" id="full-date">MEMUAT...</div>
            </div>
            <div class="dropdown-wrap" id="userDropdown">
                <div class="topbar-user" onclick="toggleUserDropdown()">
                    <div class="t-avatar">
                        <?php if (!empty($profile_photo)): ?>
                            <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile">
                        <?php else: ?>
                            <i class="fa-solid fa-user"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                        <div class="t-role"><?= strtoupper(htmlspecialchars($role)) ?></div>
                    </div>
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
        <div class="page-header">
            <div>
                <div class="page-title-tag"></div>
                <div class="page-title">Kelola Pembelian Alat</div>
            </div>
            <div class="stat-chips">
                <div class="stat-chip chip-yellow"><i class="fa-solid fa-clock"></i> MENUNGGU <span class="chip-val"><?= $menunggu_count ?></span></div>
                <div class="stat-chip chip-green"><i class="fa-solid fa-check-circle"></i> BERHASIL <span class="chip-val"><?= $berhasil_count ?></span></div>
                <div class="stat-chip chip-blue"><i class="fa-solid fa-cart-shopping"></i> TOTAL <span class="chip-val"><?= $total_beli ?></span></div>
                <div class="stat-chip chip-orange"><i class="fa-solid fa-money-bill-wave"></i> OMZET <span class="chip-val"><?= rupiah($total_omzet) ?></span></div>
            </div>
        </div>

        <div class="action-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="src" placeholder="Cari customer..." onkeyup="searchTable()">
            </div>
            <div style="display:flex;gap:12px;align-items:center;">
                <div class="filter-dropdown-wrap">
                    <button class="btn-filter" id="btnFilterToggle">
                        <i class="fa-solid fa-filter"></i> Filter <i class="fa-solid fa-chevron-down arrow-icon"></i>
                    </button>
                    <div class="filter-card" id="filterCard">
                        <h4><i class="fa-solid fa-sliders" style="margin-right:8px;color:var(--orange);"></i>Filter Data</h4>
                        <form method="GET" action="beli_alat.php">
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="f_status" class="filter-input select">
                                    <option value="">Semua Status</option>
                                    <option value="0" <?= ($_GET['f_status'] ?? '') === '0' ? 'selected' : '' ?>>Menunggu Konfirmasi</option>
                                    <option value="1" <?= ($_GET['f_status'] ?? '') === '1' ? 'selected' : '' ?>>Berhasil</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Nama Customer</label>
                                <input type="text" name="f_customer" class="filter-input" placeholder="Cari customer..." value="<?= htmlspecialchars($_GET['f_customer'] ?? '') ?>">
                            </div>
                            <div class="filter-row-2">
                                <div class="filter-group">
                                    <label>Tanggal Awal</label>
                                    <input type="date" name="f_tanggal_awal" class="filter-input" value="<?= htmlspecialchars($_GET['f_tanggal_awal'] ?? '') ?>">
                                </div>
                                <div class="filter-group">
                                    <label>Tanggal Akhir</label>
                                    <input type="date" name="f_tanggal_akhir" class="filter-input" value="<?= htmlspecialchars($_GET['f_tanggal_akhir'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="filter-group">
                                <label>Urutkan</label>
                                <select name="f_sort" class="filter-input select">
                                    <option value="tanggal_desc" <?= ($_GET['f_sort'] ?? '') === 'tanggal_desc' ? 'selected' : '' ?>>Tanggal Terbaru</option>
                                    <option value="tanggal_asc" <?= ($_GET['f_sort'] ?? '') === 'tanggal_asc' ? 'selected' : '' ?>>Tanggal Terlama</option>
                                    <option value="total_desc" <?= ($_GET['f_sort'] ?? '') === 'total_desc' ? 'selected' : '' ?>>Total Terbesar</option>
                                    <option value="total_asc" <?= ($_GET['f_sort'] ?? '') === 'total_asc' ? 'selected' : '' ?>>Total Terkecil</option>
                                </select>
                            </div>
                            <div class="filter-buttons">
                                <button type="button" class="btn-filter-reset" onclick="resetFilter()"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                                <button type="submit" class="btn-filter-apply"><i class="fa-solid fa-check"></i> Terapkan</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- DATA TABLE -->
        <div class="data-table-wrap" style="margin-bottom:24px;">
            <table class="data-table" id="dataTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Tanggal Beli</th>
                        <th>Metode</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Karyawan</th>
                        <th style="text-align:center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $has_data = false;
                if ($query):
                    while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                        $has_data = true;
                        $status = $status_labels[$row['Status']] ?? $status_labels[0];
                        $tgl_beli = $row['Tanggal_Beli'] instanceof DateTime ? $row['Tanggal_Beli']->format('d M Y H:i') : date('d M Y H:i', strtotime($row['Tanggal_Beli']));
                ?>
                    <tr data-name="<?= strtolower(htmlspecialchars($row['Nama_Customer'])) ?>">
                        <td style="font-weight:700; color:var(--text);">#<?= $row['ID_Beli'] ?></td>
                        <td>
                            <div style="font-weight:700; color:var(--text);"><?= htmlspecialchars($row['Nama_Customer']) ?></div>
                            <div style="font-size:11px; color:var(--muted);"><?= htmlspecialchars($row['Email'] ?? '-') ?></div>
                        </td>
                        <td><?= $tgl_beli ?></td>
                        <td>
                            <span style="display:inline-flex; align-items:center; gap:6px;">
                                <i class="fa-solid <?= $row['Metode_Pembayaran'] == 'QRIS' ? 'fa-qrcode' : 'fa-building-columns' ?>" style="color:var(--muted);"></i>
                                <?= htmlspecialchars($row['Metode_Pembayaran']) ?>
                            </span>
                        </td>
                        <td style="font-weight:800; color:var(--shopee-orange);"><?= rupiah($row['Total_Bayar']) ?></td>
                        <td><span class="<?= $status['class'] ?>"><i class="fa-solid <?= $status['icon'] ?>"></i> <?= $status['label'] ?></span></td>
                        <td><?= htmlspecialchars($row['Nama_Karyawan']) ?></td>
                        <td>
                            <div class="action-btns" style="justify-content:center;">
                                <a href="?detail_id=<?= intval($row['ID_Beli']) ?>" class="btn-action btn-view" title="Lihat Detail"><i class="fa-solid fa-eye"></i></a>
                                <?php if ($row['Status'] == 0): ?>
                                <a href="?confirm_id=<?= intval($row['ID_Beli']) ?>" class="btn-action btn-confirm" title="Konfirmasi Pembayaran" onclick="return confirmConfirm(this)"><i class="fa-solid fa-check"></i></a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php
                    endwhile;
                endif;
                if (!$has_data):
                ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <i class="fa-solid fa-cart-shopping"></i>
                                <div>Belum ada data pembelian</div>
                                <p>Data pembelian alat akan muncul di sini setelah customer melakukan transaksi.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrap">
            <div class="pagination-info">
                Menampilkan <strong><?= (($page-1)*$limit)+1 ?></strong> - <strong><?= min($page*$limit,$total_data) ?></strong>
                dari <strong><?= $total_data ?></strong> data
            </div>
            <div class="pagination-nav">
                <a href="?page=1<?= $filter_url ?>" class="page-btn <?= $page<=1?'disabled':'' ?>"><i class="fa-solid fa-angles-left"></i></a>
                <a href="?page=<?= $page-1 ?><?= $filter_url ?>" class="page-btn <?= $page<=1?'disabled':'' ?>"><i class="fa-solid fa-angle-left"></i></a>
                <?php for($i=max(1,$page-2);$i<=min($total_pages,$page+2);$i++): ?>
                    <a href="?page=<?= $i ?><?= $filter_url ?>" class="page-btn <?= $i==$page?'active':'' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="?page=<?= $page+1 ?><?= $filter_url ?>" class="page-btn <?= $page>=$total_pages?'disabled':'' ?>"><i class="fa-solid fa-angle-right"></i></a>
                <a href="?page=<?= $total_pages ?><?= $filter_url ?>" class="page-btn <?= $page>=$total_pages?'disabled':'' ?>"><i class="fa-solid fa-angles-right"></i></a>
            </div>
        </div>
        <?php else: ?>
        <div class="pagination-wrap">
            <div class="pagination-info">
                Menampilkan <strong>1</strong> - <strong><?= $total_data ?></strong>
                dari <strong><?= $total_data ?></strong> data
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
function updateClock() {
    var now = new Date();
    var h = String(now.getHours()).padStart(2,'0');
    var m = String(now.getMinutes()).padStart(2,'0');
    var s = String(now.getSeconds()).padStart(2,'0');
    var hEl = document.getElementById('clock-h');
    var mEl = document.getElementById('clock-m');
    var sEl = document.getElementById('clock-s');
    var dEl = document.getElementById('full-date');
    if(hEl) hEl.textContent = h;
    if(mEl) mEl.textContent = m;
    if(sEl) sEl.textContent = s;
    var days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    var months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    if(dEl) dEl.textContent = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
}
updateClock();
setInterval(updateClock, 1000);

function closeModal() {
    window.location.href = 'beli_alat.php';
}

function searchTable() {
    var filter = document.getElementById('src').value.toLowerCase();
    var rows = document.querySelectorAll('#dataTable tbody tr');
    rows.forEach(function(row) {
        var name = row.getAttribute('data-name') || '';
        row.style.display = name.indexOf(filter) > -1 ? '' : 'none';
    });
}

function toggleUserDropdown() {
    var dd = document.getElementById('userDropdown');
    if (dd) dd.classList.toggle('active');
}
document.addEventListener('click', function(e) {
    var dd = document.getElementById('userDropdown');
    if (dd && !dd.contains(e.target)) dd.classList.remove('active');
});

function confirmConfirm(el) {
    var href = el.getAttribute('href');
    Swal.fire({
        title: 'Konfirmasi Pembayaran?',
        html: '<div style="text-align:center; font-size:14px;">' +
              '<p>Customer sudah melakukan pembayaran?</p>' +
              '<p style="margin-top:8px; color:#6B7280; font-size:12px;">Stok alat akan otomatis dikurangi.</p>' +
              '</div>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10B981',
        cancelButtonColor: '#6B7280',
        confirmButtonText: '<i class="fa-solid fa-check"></i> Ya, Konfirmasi',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = href;
        }
    });
    return false;
}

function resetFilter() {
    window.location.href = 'beli_alat.php';
}

// Filter dropdown
document.addEventListener('DOMContentLoaded', function() {
    var btnFilterToggle = document.getElementById('btnFilterToggle');
    var filterCard = document.getElementById('filterCard');
    if (btnFilterToggle && filterCard) {
        btnFilterToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            filterCard.classList.toggle('open');
        });
        filterCard.addEventListener('click', function(e) { e.stopPropagation(); });
        document.addEventListener('click', function() { filterCard.classList.remove('open'); });
    }

    // URL notifications
    var urlParams = new URLSearchParams(window.location.search);
    var status = urlParams.get('status');
    var msg = urlParams.get('msg');
    if (status && msg) {
        var isSuccess = status === 'success';
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
            didOpen: function(toast) {
                toast.addEventListener('mouseenter', Swal.stopTimer);
                toast.addEventListener('mouseleave', Swal.resumeTimer);
            }
        });
        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
</script>
</body>
</html>