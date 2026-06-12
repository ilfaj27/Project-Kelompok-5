<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
    exit();
}
$role = $_SESSION['role'];
$nama = $_SESSION['nama'] ?? 'USER';

// ⬇️ DEFINISIKAN $profile_photo AGAR TIDAK ERROR
$profile_photo = '';
$stmt_photo = sqlsrv_query($conn, "SELECT Foto_Profil FROM Karyawan WHERE Nama = ?", array($nama));
if ($stmt_photo !== false) {
    $row_photo = sqlsrv_fetch_array($stmt_photo, SQLSRV_FETCH_ASSOC);
    if ($row_photo && !empty($row_photo['Foto_Profil'])) {
        $profile_photo = '../uploads/profiles/' . $row_photo['Foto_Profil'];
    }
}

// ═══════════════════════════════════════════
// HELPER: Safe SQLSRV Operations
// ═══════════════════════════════════════════
function safe_sqlsrv_query($conn, $sql, $params = [], $die_on_error = true) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $error_details = [];
        if ($errors) {
            foreach ($errors as $error) {
                $error_details[] = "[SQLSTATE: " . $error['SQLSTATE'] . "] [Code: " . $error['code'] . "] " . $error['message'];
            }
        }
        $error_msg = implode(" | ", $error_details);
        error_log("[SQL ERROR] " . $error_msg . " | SQL: " . $sql . " | Params: " . json_encode($params));

        if ($die_on_error) {
            echo "<div style='padding:20px;background:#fee;border:1px solid #fcc;border-radius:8px;font-family:sans-serif;margin:20px;'>
                <h3 style='color:#c00;margin:0 0 10px;'><i class='fa-solid fa-circle-exclamation'></i> Database Error</h3>
                <p style='color:#333;margin:0 0 5px;'><strong>Detail Error:</strong></p>
                <pre style='background:#fff;padding:10px;border-radius:4px;overflow-x:auto;font-size:12px;'>" . htmlspecialchars($error_msg) . "</pre>
                <p style='color:#666;font-size:12px;margin:10px 0 0;'>SQL: " . htmlspecialchars($sql) . "</p>
                <p style='color:#666;font-size:12px;margin:5px 0 0;'>Silakan periksa koneksi database atau hubungi administrator.</p>
            </div>";
            exit();
        }
        return false;
    }
    return $stmt;
}

function safe_sqlsrv_fetch_array($stmt, $fetch_type = SQLSRV_FETCH_ASSOC) {
    if ($stmt === false || $stmt === null) {
        return false;
    }
    return sqlsrv_fetch_array($stmt, $fetch_type);
}

function safe_sqlsrv_has_rows($stmt) {
    if ($stmt === false || $stmt === null) {
        return false;
    }
    return sqlsrv_has_rows($stmt);
}

// --- PROSES CRUD (KODE BARU: CEK NAMA DUPLIKAT & AUTO-GENERATE ID) ---
if (isset($_POST['save_lapangan'])) {
    $id = $_POST['id_lap'];
    $nama_lapangan = trim($_POST['nama_arena']); 
    $harga = floatval($_POST['harga']);

    // SEBAIKNYA TIDAK BOLEH DUPLIKAT: Cari apakah ada nama lapangan yang sama di database
    // (Jika mengedit, izinkan jika nama sama dengan ID-nya sendiri)
    $sql_check_name = "SELECT ID_Lapangan FROM Lapangan WHERE Nama_Lapangan = ? AND ID_Lapangan <> ?";
    $q_check_name = safe_sqlsrv_query($conn, $sql_check_name, array($nama_lapangan, $id), false);

    if ($q_check_name && safe_sqlsrv_has_rows($q_check_name)) {
        header("Location: lapangan.php?page=1&status=error&msg=Nama lapangan sudah tersedia!");
        exit();
    }

    if (isset($_POST['edit_mode'])) {
        safe_sqlsrv_query($conn, "UPDATE Lapangan SET Nama_Lapangan=?, Harga_Sewa=? WHERE ID_Lapangan=?", array($nama_lapangan, $harga, $id), false);
        header("Location: lapangan.php?page=1&status=success&msg=Data lapangan berhasil diperbarui!");
    } else {
        // Auto-generate ID baru di database
        $q_max = safe_sqlsrv_query($conn, "SELECT MAX(ID_Lapangan) as max_id FROM Lapangan", [], false);
        $d_max = safe_sqlsrv_fetch_array($q_max, SQLSRV_FETCH_ASSOC);
        $num = ($d_max['max_id']) ? (int) substr($d_max['max_id'], 2) + 1 : 1;
        $id_lap_baru = "LP" . sprintf("%04d", $num);

        safe_sqlsrv_query($conn, "INSERT INTO Lapangan (ID_Lapangan, Nama_Lapangan, Harga_Sewa, Status, Is_Deleted, Created_By, Created_Date) VALUES (?,?,?,1,0,?,GETDATE())", array($id_lap_baru, $nama_lapangan, $harga, $nama), false);
        header("Location: lapangan.php?page=1&status=success&msg=Lapangan baru berhasil ditambahkan!");
    }
    exit();
}

if (isset($_GET['toggle_id'])) {
    $s_baru = ($_GET['s'] == 1) ? 0 : 1;
    safe_sqlsrv_query($conn, "UPDATE Lapangan SET Status=? WHERE ID_Lapangan=?", array($s_baru, $_GET['toggle_id']), false);
    header("Location: lapangan.php?page=1&status=success&msg=Status lapangan berhasil diubah!"); 
    exit();
}

if (isset($_GET['delete_id'])) {
    $stmt = safe_sqlsrv_query($conn, "DELETE FROM Lapangan WHERE ID_Lapangan=?", array($_GET['delete_id']), false);
    header($stmt ? "Location: lapangan.php?page=1&status=success&msg=Lapangan berhasil dihapus!" : "Location: lapangan.php?page=1&status=error&msg=Gagal hapus, data masih terikat!");
    exit();
}

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $r = safe_sqlsrv_query($conn, "SELECT * FROM Lapangan WHERE ID_Lapangan=?", array($_GET['edit_id']), false);
    if ($r) {
        $edit_data = safe_sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC);
    }
}

// --- KODE BARU: QUERY AMBIL DETAIL LAPANGAN UNTUK POPUP ---
$detail_data = null;
$show_detail = false;
if (isset($_GET['detail_id'])) {
    $r_detail = safe_sqlsrv_query($conn, "SELECT * FROM Lapangan WHERE ID_Lapangan=?", array($_GET['detail_id']), false);
    if ($r_detail) {
        $detail_data = safe_sqlsrv_fetch_array($r_detail, SQLSRV_FETCH_ASSOC);
        $show_detail = true; // Aktifkan flag untuk membuka modal detail
    }
}

$show_add = isset($_GET['add']) && $_GET['add'] == '1';

// --- KODE BARU: GENERATOR CALON ID LAPANGAN BARU OTOMATIS ---
$next_id_add = "";
if (!$edit_data) {
    $q_max = safe_sqlsrv_query($conn, "SELECT MAX(ID_Lapangan) as max_id FROM Lapangan", [], false);
    $d_max = safe_sqlsrv_fetch_array($q_max, SQLSRV_FETCH_ASSOC);
    
    // Jika data kosong, mulai dari 1. Jika ada, ambil angka terakhirnya (mulai dari karakter ke-2) lalu tambah 1
    $num = ($d_max['max_id']) ? (int) substr($d_max['max_id'], 2) + 1 : 1;
    $next_id_add = "LP" . sprintf("%04d", $num); // Format menjadi LP0005 (total panjang 6 digit)
}

// --- 1. MEMBUAT FILTER DAN SORTING DINAMIS BERDASARKAN URL (GET) ---
$where_clauses = array("Is_Deleted = 0"); // Hanya menampilkan data aktif
$query_params = array();

// Filter berdasarkan Status (AKTIF / MAINTENANCE)
if (isset($_GET['f_status']) && $_GET['f_status'] !== '') {
    $where_clauses[] = "Status = ?";
    $query_params[] = intval($_GET['f_status']);
}

$where_sql = implode(" AND ", $where_clauses);

// Pengurutan Data (Sorting)
$sort_by = "ID_Lapangan ASC";
if (isset($_GET['f_sort'])) {
    if ($_GET['f_sort'] === 'id_desc') {
        $sort_by = "ID_Lapangan DESC";
    } elseif ($_GET['f_sort'] === 'nama_asc') {
        $sort_by = "Nama_Lapangan ASC";
    }
}

// --- STATISTIK (TETAP SINKRON) ---
$q_ready = safe_sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Lapangan WHERE Status=1 AND Is_Deleted=0", [], false);
$cnt_ready = 0;
if ($q_ready) {
    $row = safe_sqlsrv_fetch_array($q_ready, SQLSRV_FETCH_ASSOC);
    $cnt_ready = $row['t'] ?? 0;
}

$q_maint = safe_sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Lapangan WHERE Status=0 AND Is_Deleted=0", [], false);
$cnt_maint = 0;
if ($q_maint) {
    $row = safe_sqlsrv_fetch_array($q_maint, SQLSRV_FETCH_ASSOC);
    $cnt_maint = $row['t'] ?? 0;
}

// Menghitung total data lapangan yang TERFILTER
$q_total = safe_sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Lapangan WHERE $where_sql", $query_params, false);
$total_lapangan = 0;
if ($q_total) {
    $row = safe_sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC);
    $total_lapangan = $row['t'] ?? 0;
}

// --- PAGING CONFIGURATION ---
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$total_pages = max(1, ceil($total_lapangan / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

// --- 2. EKSEKUSI QUERY DENGAN ATURAN FILTER & SORTING AKTIF ---
$query_sql = "SELECT * FROM Lapangan WHERE $where_sql ORDER BY $sort_by OFFSET " . intval($offset) . " ROWS FETCH NEXT " . intval($limit) . " ROWS ONLY";
$query = safe_sqlsrv_query($conn, "SELECT * FROM Lapangan WHERE $where_sql ORDER BY $sort_by OFFSET " . intval($offset) . " ROWS FETCH NEXT " . intval($limit) . " ROWS ONLY", $query_params, false);

$query_error = ($query === false);
$query_error_msg = '';
if ($query_error) {
    $errors = sqlsrv_errors();
    if ($errors) {
        foreach ($errors as $error) {
            $query_error_msg .= "[" . $error['SQLSTATE'] . "] " . $error['message'] . " ";
        }
    }
}

// --- 3. MEMBUAT STRING URL AGAR FILTER TETAP AKTIF SAAT BERPINDAH HALAMAN ---
$filter_url = "";
if (isset($_GET['f_sort'])) $filter_url .= "&f_sort=" . urlencode($_GET['f_sort']);
if (isset($_GET['f_status'])) $filter_url .= "&f_status=" . urlencode($_GET['f_status']);

// --- TAMBAHKAN QUERY INI UNTUK PENDING COUNT SINKRON ---
$q_pending = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Booking WHERE Status=1"); // Status 1 = pending
$total_pending = 0;
if ($q_pending !== false) {
    $row_pending = sqlsrv_fetch_array($q_pending, SQLSRV_FETCH_ASSOC);
    $total_pending = $row_pending['t'] ?? 0;
}

function rupiah($n){ return 'Rp '.number_format($n,0,',','.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Lapangan | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10); --green-dk: #059669;
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --purple: #8B5CF6; --purple-lt: rgba(139,92,246,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10); --red-dk: #DC2626;
    --yellow: #F59E0B; --yellow-lt: rgba(245,158,11,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --text-md: #374151; --muted: #6B7280;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

/* ═══ SIDEBAR (TANPA SCROLLBAR) ═══ */
.sidebar { width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 28px 18px; border-right: 1px solid rgba(255,255,255,.04); z-index: 200; overflow-y: auto; scrollbar-width: none; -ms-overflow-style: none; }
.sidebar::-webkit-scrollbar { display: none; }
.sb-brand { display: flex; align-items: center; gap: 12px; padding: 0 8px; margin-bottom: 36px; text-decoration: none; }
.sb-icon { width: 40px; height: 40px; background: var(--orange); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; flex-shrink: 0; box-shadow: 0 4px 14px rgba(255,69,0,.4); }
.sb-brand-name { font-family: 'Barlow Condensed', sans-serif; font-size: 20px; font-weight: 900; color: #fff; letter-spacing: 1px; }
.sb-brand-sub { font-size: 9px; color: #4B5563; font-weight: 700; text-transform: uppercase; }
.sb-section-label { font-size: 10px; font-weight: 800; text-transform: uppercase; color: #374151; letter-spacing: .8px; padding: 0 10px; margin: 22px 0 8px; }
.sb-link { display: flex; align-items: center; gap: 12px; color: #6B7280; text-decoration: none; padding: 10px 12px; border-radius: 10px; margin-bottom: 2px; font-size: 13px; font-weight: 600; transition: all .2s ease; position: relative; }
.sb-link .sb-icon-wrap { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: .2s; flex-shrink: 0; background: rgba(255,255,255,.04); }
.sb-link:hover { color: #E5E7EB; background: rgba(255,255,255,.04); }
.sb-link:hover .sb-icon-wrap { background: rgba(255,255,255,.08); }
.sb-link.active { color: #fff; background: var(--orange-lt); }
.sb-link.active .sb-icon-wrap { background: var(--orange); color: #fff; }
.sb-link .badge { margin-left: auto; background: var(--orange); color: #fff; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 20px; }
.sb-bottom { margin-top: auto; padding-top: 20px; }
.sb-user { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.04); border-radius: 12px; padding: 12px; border: 1px solid rgba(255,255,255,.06); }
.sb-avatar { width: 36px; height: 36px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; line-height: 1.1; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
.sb-logout:hover { color: var(--red); background: rgba(239,68,68,.1); }

/* ═══ MAIN & TOPBAR (MENUTUP CELAH 1PX) ═══ */
.main { 
    margin-left: calc(var(--sidebar-w) - 1px); /* Tumpuk 1px ke kiri */
    flex: 1; 
    display: flex; 
    flex-direction: column; 
    min-height: 100vh; 
}
.topbar { background: var(--card-bg); height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 0 rgba(0,0,0,.04); }
.topbar-left { display: flex; flex-direction: column; }
.topbar-title { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); letter-spacing: -.5px; line-height: 1; }
.topbar-breadcrumb { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.topbar-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); cursor: pointer; font-size: 14px; text-decoration: none; transition: .2s; position: relative; }
.topbar-btn:hover { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); }
.notif-dot { position: absolute; top: 7px; right: 7px; width: 7px; height: 7px; background: var(--orange); border-radius: 50%; border: 2px solid #fff; }
.dropdown-wrap { position: relative; }
.topbar-user { display: flex; align-items: center; gap: 10px; background: var(--bg); border: 1px solid var(--border); padding: 6px 14px 6px 8px; border-radius: 12px; cursor: pointer; transition: .2s; }
.topbar-user:hover { border-color: var(--orange); }
.t-avatar { width: 32px; height: 32px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; }
.t-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.1; text-transform: uppercase; }
.t-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.t-chevron { color: var(--muted); font-size: 10px; margin-left: 4px; }
.dropdown-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #fff; min-width: 200px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 15px 40px rgba(0,0,0,.12); overflow: hidden; padding: 8px 0; z-index: 999; }
.dropdown-wrap:hover .dropdown-menu { display: block; }
.dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-item i { font-size: 14px; width: 18px; text-align: center; }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

/* ═══ CONTENT ═══ */
.content { padding: 32px 40px; flex: 1; }
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
.page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }

/* ═══ STAT CHIPS ═══ */
.stat-chips { display: flex; gap: 10px; flex-wrap: wrap; }
.stat-chip { display: flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 10px; font-size: 12px; font-weight: 700; transition: all .2s; }
.stat-chip:hover { transform: translateY(-2px); }
.chip-green { background: var(--green-lt); color: var(--green); }
.chip-red   { background: var(--red-lt); color: var(--red); }
.chip-blue  { background: var(--blue-lt); color: var(--blue); }
.chip-val   { font-family: 'Barlow Condensed'; font-size: 20px; font-weight: 900; }

/* ═══ ACTION BAR ═══ */
.action-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.search-box { position: relative; width: 300px; }
.search-box i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; }
.search-box input { width: 100%; padding: 10px 14px 10px 40px; background: var(--card-bg); border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; outline: none; transition: all .2s; color: var(--text); }
.search-box input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.search-box input::placeholder { color: #9CA3AF; }

/* ═══ CARD & TABLE (SINKRON DETAIL PRESISI GLOBAL) ═══ */
.card { 
    background: var(--card-bg); 
    border-radius: 16px; 
    border: 1px solid var(--border); 
    overflow: hidden; 
    transition: all .2s ease; 
    background-color: #FFFFFF !important;
}
.main, .content { background-color: #F3F4F6 !important; }
.card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.06); }
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }

.data-table th {
    font-family: 'Barlow Condensed', sans-serif !important; 
    font-size: 13px !important; 
    font-weight: 900 !important; 
    color: var(--muted) !important; 
    text-transform: uppercase !important; 
    letter-spacing: 0.8px !important; 
    padding: 14px 20px;
    border-bottom: 2px solid var(--border-lt);
}

.data-table th, .data-table td { 
    padding: 16px 20px; 
    vertical-align: middle; 
}

/* 1. Kolom No (Rata Tengah) */
.data-table th:nth-child(1),
.data-table td:nth-child(1) {
    text-align: center !important;
    width: 8%;
    font-size: 15px;
    font-weight: 700;
}

/* 2. Kolom Nama Lapangan */
.data-table th:nth-child(2),
.data-table td:nth-child(2) {
    width: 32%;
    text-align: left;
}
.lap-name { font-weight: 700; color: var(--text); font-size: 15px; }

/* 3. Kolom Harga Sewa */
.data-table th:nth-child(3),
.data-table td:nth-child(3) {
    width: 22%;
    text-align: left !important;
    padding-left: 0 !important;
    position: relative;
    left: -0px !important; 
}

.lap-price { 
    font-family: 'Barlow', sans-serif; 
    font-weight: 700; 
    font-size: 15px; 
    color: var(--text);  
}

/* 4. Kolom Status (Tengah Presisi) */
.data-table th:nth-child(4),
.data-table td:nth-child(4) {
    width: 18%;
    text-align: center !important;
    padding-left: 0 !important;
}

.data-table th:nth-child(4) {
    position: relative;
    left: -60px !important; 
}

.data-table td:nth-child(4) {
    font-size: 0 !important; 
}

.data-table td:nth-child(4) .status-pill {
    position: relative;
    left: -60px !important; 
    display: inline-flex !important;
    font-size: 12px !important; 
    margin: 0 !important; 
}

/* 5. Kolom Aksi (Rata Kiri) */
.data-table th:nth-child(5),
.data-table td:nth-child(5) {
    width: 20%;
    text-align: left !important;
}

.lap-id { color: var(--orange); font-weight: 800; font-family: 'Barlow Condensed'; font-size: 16px; }

/* ═══ STATUS TOGGLE SWITCH ═══ */
.toggle-switch { 
    position: relative; 
    display: inline-flex;
    align-items: center;
    width: 44px;
    height: 24px;
    cursor: pointer;
    margin: 0;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--red); transition: .3s; border-radius: 24px; }
.toggle-slider::before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,.2); }
.toggle-switch input:checked + .toggle-slider { background-color: var(--green); }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }
.toggle-switch:hover .toggle-slider { opacity: .9; }

.status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 20px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; }
.sp-ready { background: var(--green-lt); color: var(--green); }
.sp-maint { background: var(--red-lt); color: var(--red); }
.sp-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.sp-ready .sp-dot { background: var(--green); }
.sp-maint .sp-dot { background: var(--red); }

/* ═══ ACTIONS ═══ */
.actions { 
    display: flex;
    gap: 12px; 
    justify-content: flex-start; 
    align-items: center;  
}
.btn-action {
    width: 38px;
    height: 38px;
    display: inline-flex; 
    align-items: center; 
    justify-content: center; 
    border-radius: 10px; 
    font-size: 14px; 
    font-weight: 700;
    transition: all .25s cubic-bezier(.4,0,.2,1); 
    border: 1.5px solid transparent; 
}
.btn-edit {
    background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); color: #1E40AF; border-color: #BFDBFE;
}
.btn-edit:hover {
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: #fff; border-color: #3B82F6;
    transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,.35);
}
.btn-delete {
    background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%); color: #DC2626; border-color: #FECACA;
}
.btn-delete:hover {
    background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%); color: #fff; border-color: #EF4444;
    transform: translateY(-2px); box-shadow: 0 6px 20px rgba(239,68,68,.35);
}

/* ═══ MODAL ═══ */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(6px); display: none; align-items: center; justify-content: center; z-index: 2000; }
.modal-overlay.open { display: flex; }
.modal-box { background: #fff; border-radius: 20px; width: 480px; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,.2); position: relative; }
.modal-header { padding: 28px 32px 20px; border-bottom: 1px solid var(--border); }
.modal-subtitle { font-size: 10px; font-weight: 800; color: var(--orange); text-transform: uppercase; margin-bottom: 6px; letter-spacing: .8px; }
.modal-title { font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 900; color: var(--text); }
.modal-body { padding: 24px 32px 32px; }
.modal-label { display: block; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
.modal-label .required { color: var(--red); margin-left: 2px; font-size: 14px; font-weight: 900; }
.modal-input { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; margin-bottom: 16px; outline: none; transition: all .2s; color: var(--text); }
.modal-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.modal-input::placeholder { color: #9CA3AF; }
.modal-input:read-only { background: var(--border-lt); color: var(--muted); cursor: not-allowed; }
.btn-submit { width: 100%; background: var(--orange); color: #fff; border: none; padding: 14px; border-radius: 10px; font-weight: 800; font-size: 13px; cursor: pointer; transition: all .2s; text-transform: uppercase; letter-spacing: .5px; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-submit:hover { background: var(--orange-dk); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(255,69,0,.3); }
.btn-cancel { display: block; text-align: center; margin-top: 16px; color: var(--muted); text-decoration: none; font-size: 13px; font-weight: 700; transition: .2s; cursor: pointer; }
.btn-cancel:hover { color: var(--orange); }
.modal-close { position: absolute; top: 20px; right: 20px; width: 36px; height: 36px; border: none; background: var(--border-lt); border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 16px; transition: all .2s; }
.modal-close:hover { background: var(--red-lt); color: var(--red); }

/* ═══ VALIDASI ERROR STATE ═══ */
.modal-input.error {
    border-color: var(--red) !important;
    background-color: #FEF2F2 !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important;
}
.modal-input.error:focus {
    border-color: var(--red) !important;
    box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25) !important;
}
.val-msg {
    font-size: 11px; color: var(--red); font-weight: 600; 
    margin-bottom: 10px; display: none; min-height: 16px;
}
.val-msg.show { display: block; }
.val-msg i { margin-right: 4px; }

/* ═══ PAGINATION ═══ */
.pagination-wrap { background: var(--card-bg); border: 1px solid var(--border); border-top: none; border-radius: 0 0 16px 16px; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; }
.pagination-info { font-size: 12px; color: var(--muted); font-weight: 600; }
.pagination-info strong { color: var(--text); font-weight: 800; }
.pagination-nav { display: flex; align-items: center; gap: 4px; }
.page-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 10px; border-radius: 10px; font-size: 13px; font-weight: 700; font-family: 'Barlow', sans-serif; text-decoration: none; cursor: pointer; transition: all .2s ease; border: 1.5px solid var(--border); color: var(--text-md); background: #fff; }
.page-btn:hover:not(.disabled):not(.active) { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); transform: translateY(-1px); }
.page-btn.active { background: var(--orange); color: #fff; border-color: var(--orange); box-shadow: 0 4px 12px rgba(255,69,0,.3); font-weight: 800; }
.page-btn.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
.page-btn i { font-size: 11px; }
.page-ellipsis { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; color: var(--muted); font-size: 13px; font-weight: 800; }

/* ═══ EMPTY STATE ═══ */
.empty-state { text-align: center; padding: 50px 20px; color: var(--muted); }
.empty-state i { font-size: 48px; margin-bottom: 16px; opacity: .3; display: block; }
.empty-state div { font-size: 14px; font-weight: 700; }

/* ═══ ZEBRA STRIPING ═══ */
.data-table tbody tr:nth-child(odd) { background-color: #FFF7ED; }
.data-table tbody tr:nth-child(even) { background-color: #FFFFFF; }
.data-table tbody tr:hover td { background-color: #FFEDD5 !important; }
.data-table tbody tr:nth-child(odd):hover { background-color: #FFEDD5; }
.data-table tbody tr:nth-child(even):hover { background-color: #FFEDD5; }

/* ═══ CLOCK ═══ */
#clock-display { 
    display: flex; 
    align-items: center; 
    gap: 16px; 
}

.clock-time { 
    font-family: 'Barlow Condensed', sans-serif; 
    font-size: 26px; 
    font-weight: 900; 
    color: var(--orange); 
    display: flex; 
    align-items: center; 
    gap: 6px; 
    line-height: 1; 
}

.clock-colon { 
    color: var(--orange); 
    opacity: .5; 
    animation: blink 1s infinite; 
}

@keyframes blink { 
    0%, 100% { opacity: .5; } 
    50% { opacity: 1; } 
}

.clock-divider { 
    width: 1.5px; 
    height: 28px; 
    background-color: var(--border); 
}

.clock-date { 
    font-family: 'Barlow', sans-serif; 
    font-size: 13px; 
    font-weight: 700; 
    color: var(--muted); 
    text-transform: uppercase; 
    letter-spacing: 0.5px; 
}

.btn-add { 
    display: inline-flex !important; 
    align-items: center !important; 
    gap: 8px !important; 
    background-color: var(--text) !important;
    color: #fff !important; 
    padding: 11px 22px !important; 
    border-radius: 10px !important; 
    font-size: 13px !important; 
    font-weight: 800 !important; 
    text-decoration: none !important; 
    text-transform: uppercase !important; 
    transition: all .2s ease !important; 
    border: none !important; 
    cursor: pointer !important; 
}

.btn-add:hover { 
    background-color: var(--orange) !important;
    transform: translateY(-2px) !important; 
    box-shadow: 0 8px 20px rgba(255,69,0,.3) !important; 
}

.btn-add i { 
    font-size: 14px !important; 
}

/* ═══ CSS UNTUK DETAIL MODAL & TOMBOL MATA BIRU (BARU) ═══ */
.btn-view {
    background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); 
    color: #1E40AF; 
    border-color: #BFDBFE;
}
.btn-view:hover {
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); 
    color: #fff; 
    border-color: #3B82F6;
    transform: translateY(-2px); 
    box-shadow: 0 6px 20px rgba(59,130,246,.35);
}

.detail-photo-card { text-align: center; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1.5px dashed var(--border); }
.detail-icon-wrap { width: 80px; height: 80px; background: var(--orange-lt); color: var(--orange); border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 16px; box-shadow: 0 8px 20px rgba(255,69,0,0.15); }
.detail-main-name { font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--text); text-transform: uppercase; }
.info-row { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid var(--border-lt); }
.info-row:last-child { border-bottom: none; }
.info-key { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.3px; }
.info-key i { color: var(--orange); font-size: 14px; width: 18px; text-align: center; }
.info-val { font-size: 14px; font-weight: 700; color: var(--text); }
.info-val.price { font-family: 'Barlow Condensed'; font-size: 18px; color: var(--orange); font-weight: 800; }

/* ═══ CSS UNTUK TOMBOL FILTER & KARTU FILTER (BARU) ═══ */
.filter-dropdown-wrap { position: relative; display: inline-block; }
.btn-filter {
    display: inline-flex; 
    align-items: center; 
    gap: 8px; 
    background-color: var(--orange); 
    color: #ffffff !important;
    padding: 11px 20px; 
    border-radius: 10px; 
    font-size: 13px; 
    font-weight: 800; 
    text-transform: uppercase; 
    border: none; 
    cursor: pointer; 
    transition: all 0.2s; 
    box-shadow: 0 4px 12px rgba(255,69,0,0.2);
}
.btn-filter:hover { 
    background-color: var(--orange-dk) !important; 
    color: #ffffff !important;
    transform: translateY(-2px); 
    box-shadow: 0 6px 16px rgba(255,69,0,0.35); 
}
.btn-filter i.arrow-icon { font-size: 10px; transition: transform 0.3s; }
.btn-filter.active i.arrow-icon { transform: rotate(180deg); }

.filter-card {
    position: absolute; 
    top: calc(100% + 10px); 
    right: 0; 
    background: #ffffff; 
    border-radius: 16px; 
    border: 1px solid var(--border); 
    padding: 24px; 
    width: 300px; 
    box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12); 
    z-index: 50; 
    display: none;
}
.filter-card.open { 
    display: block; 
    animation: slideFilter 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; 
}
@keyframes slideFilter { 
    from { opacity: 0; transform: translateY(10px); } 
    to { opacity: 1; transform: translateY(0); } 
}

.filter-card h4 { 
    font-size: 15px; 
    font-weight: 800; 
    color: var(--text); 
    margin-bottom: 20px; 
    text-align: left; 
}
.filter-group { 
    margin-bottom: 16px; 
    text-align: left; 
}
.filter-group label { 
    display: block; 
    font-size: 11px; 
    font-weight: 800; 
    color: var(--muted); 
    text-transform: uppercase; 
    letter-spacing: 0.5px; 
    margin-bottom: 8px; 
}
.filter-input { 
    width: 100%; 
    padding: 10px 14px; 
    border: 1.5px solid var(--border); 
    border-radius: 10px; 
    font-size: 13px; 
    font-family: 'Barlow', sans-serif; 
    outline: none; 
    transition: all .2s; 
    color: var(--text); 
    cursor: pointer; 
    appearance: none; 
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); 
    background-repeat: no-repeat; 
    background-position: right 14px center; 
    padding-right: 40px; 
}
.filter-input:focus { border-color: var(--orange); }

.filter-buttons { display: flex; gap: 10px; margin-top: 24px; }
.btn-filter-apply { 
    flex: 1.2; 
    background: var(--orange); 
    color: white; 
    border: none; 
    padding: 12px; 
    border-radius: 10px; 
    font-weight: 800; 
    font-size: 12px; 
    text-transform: uppercase; 
    cursor: pointer; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    gap: 6px; 
    transition: all .2s; 
}
.btn-filter-apply:hover { background: var(--orange-dk); }
.btn-filter-reset { 
    flex: 1; 
    background: var(--border-lt); 
    color: var(--text-md); 
    border: 1px solid var(--border); 
    padding: 12px; 
    border-radius: 10px; 
    font-weight: 800; 
    font-size: 12px; 
    text-transform: uppercase; 
    cursor: pointer; 
    display: flex; 
    align-items: center; 
    justify-content: center; 
    gap: 6px; 
    transition: all .2s; 
}
.btn-filter-reset:hover { background: var(--bg); }

/* ═══ RESPONSIVE (KHUSUS LAYAR TABLET & HP) ═══ */
@media(max-width: 1100px) { 
    .page-header { flex-direction: column; align-items: flex-start; } 
}

@media(max-width: 768px) {
    .sidebar { width: 0; overflow: hidden; padding: 0; }
    .main { margin-left: 0; }
    .content { padding: 20px; }
    .topbar { padding: 0 20px; }
    .stat-chips { width: 100%; }
    .search-box { width: 100%; }
    .action-bar { flex-direction: column; align-items: stretch; }
    .btn-action { padding: 6px 10px; font-size: 11px; }
    .pagination-wrap { flex-direction: column; gap: 12px; }
    .modal-box { width: 90%; margin: 20px; }
}
</style>
</head>
<body>

<!-- ═══ MODAL FORM ═══ -->
<div class="modal-overlay <?= ($edit_data || $show_add) ? 'open' : '' ?>" id="modalLapangan">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header">
            <div class="modal-subtitle">Master Lapangan</div>
            <div class="modal-title"><?= $edit_data ? 'Edit Lapangan' : 'Tambah Lapangan Baru' ?></div>
        </div>
        <div class="modal-body">
            <form method="POST" id="formLapangan" onsubmit="return validateForm()" novalidate>
                <?php if ($edit_data): ?><input type="hidden" name="edit_mode" value="1"><?php endif; ?>
                
                <label class="modal-label">ID Lapangan</label>
                <input type="text" name="id_lap" id="id_lap" class="modal-input" 
                    value="<?= htmlspecialchars($edit_data['ID_Lapangan'] ?? $next_id_add) ?>" 
                    readonly placeholder="Contoh: LP0001">
                <div class="val-msg" id="val-id_lap"><i class="fa-solid fa-circle-exclamation"></i> ID Lapangan wajib diisi</div>

               <label class="modal-label">Nama Lapangan <span class="required">*</span></label>
                <input type="text" name="nama_arena" id="nama_arena" class="modal-input" 
                    value="<?= htmlspecialchars($edit_data['Nama_Lapangan'] ?? '') ?>" 
                    required minlength="3" maxlength="50" placeholder="Contoh: Basket Indoor Pro">
                <div class="val-msg" id="val-nama_arena"></div>

                <label class="modal-label">Harga Sewa (Rp) <span class="required">*</span></label>
                <input type="number" name="harga" id="harga" class="modal-input" 
                    value="<?= (int)($edit_data['Harga_Sewa'] ?? 0) ?>" 
                    required placeholder="200000" min="50000" max="1000000">
                <div class="val-msg" id="val-harga"></div>

                <button type="submit" name="save_lapangan" class="btn-submit">
                    <i class="fa-solid fa-<?= $edit_data ? 'floppy-disk' : 'plus' ?>"></i>
                    <?= $edit_data ? 'Simpan Perubahan' : 'Tambah Lapangan' ?>
                </button>
                <a onclick="closeModal()" class="btn-cancel">Batal</a>
            </form>
        </div>
    </div>
</div>

<!-- ═══ MODAL DETAIL LAPANGAN ═══ -->
<div class="modal-overlay <?= $show_detail ? 'open' : '' ?>" id="modalDetail">
    <div class="modal-box" style="width: 440px;">
        <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <div class="modal-subtitle">Informasi Arena</div>
            <div class="modal-title">Profil Lapangan</div>
        </div>
        <div class="modal-body" style="padding-top: 10px;">
            <?php if ($detail_data): 
                $is_ready_detail = $detail_data['Status'] == 1;
            ?>
                <div class="detail-photo-card">
                    <div class="detail-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>
                    <div class="detail-main-name"><?= htmlspecialchars($detail_data['Nama_Lapangan']) ?></div>
                </div>

                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-fingerprint"></i> ID Lapangan</span>
                    <span class="info-val" style="color:var(--orange); font-weight:800; font-family:'Barlow Condensed'; font-size:16px;"><?= htmlspecialchars($detail_data['ID_Lapangan']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-layer-group"></i> Nama Lapangan</span>
                    <span class="info-val" style="font-weight:700;"><?= htmlspecialchars($detail_data['Nama_Lapangan']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-money-bill-wave"></i> Harga Sewa</span>
                    <span class="info-val price" style="font-family:'Barlow Condensed'; font-size:18px; color:var(--orange); font-weight:800;"><?= rupiah($detail_data['Harga_Sewa']) ?> <span style="font-size:12px; color:var(--muted); font-family:'Barlow'; font-weight:600;">/ jam</span></span>
                </div>
                <div class="info-row" style="border-bottom:none;">
                    <span class="info-key"><i class="fa-solid fa-circle-check"></i> Status Arena</span>
                    <span class="info-val">
                        <span class="status-pill <?= $is_ready_detail ? 'sp-ready' : 'sp-maint' ?>">
                            <span class="sp-dot"></span>
                            <?= $is_ready_detail ? 'AKTIF' : 'MAINTENANCE' ?>
                        </span>
                    </span>
                </div>
            <?php endif; ?>
            
            <button onclick="closeModal()" class="btn-submit" style="margin-top: 24px; background: #0D1117;">
                <i class="fa-solid fa-arrow-left"></i> Kembali Ke List
            </button>
        </div>
    </div>
</div>

<!-- ═══ SIDEBAR ═══ -->
<<aside class="sidebar">
    <a href="../view_admin.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">Management System</div>
        </div>
    </a>

    <div class="sb-section-label">Operasional</div>
    <nav>
        <a href="../view_admin.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>
            Dashboard
        </a>
        <a href="customer.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>
            Kelola Customer
        </a>
        <a href="lapangan.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>
            Kelola Lapangan
        </a>
        <a href="fasilitas.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-list-check"></i></div>
            Kelola Fasilitas
        </a>
        <a href="jadwal.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-days"></i></div>
            Kelola Jadwal
        </a>
        <a href="promo.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div>
            Kelola Promo
        </a>
        <a href="tipe_member.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-card"></i></div>
            Kelola Tipe Member
        </a>
        <a href="alat.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div>
            Kelola Alat
        </a>
        <a href="../m_Alat/index.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-boxes-stacked"></i></div>
            Alat
        </a>
        <a href="../m_Jadwal/index.php" class="sb-link"> 
            <div class="sb-icon-wrap"><i class="fa-solid fa-clock"></i></div>
            Jadwal
        </a>
    </nav>

    <div class="sb-section-label">Transaksi</div>
    <nav>
        <a href="booking.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>
            Kelola Booking
        </a>
        <a href="langganan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-crown"></i></div>
            Kelola Langganan
        </a>
        <a href="pembelian.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-cart-shopping"></i></div>
            Kelola Pembelian Alat
        </a>
        <a href="pembatalan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-ban"></i></div>
            Kelola Pembatalan
        </a>
    </nav>

    <div class="sb-section-label">Akun</div>
    <a href="../profile.php" class="sb-link">
        <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>
        Profil Saya
    </a>

    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
                <?php if (!empty($profile_photo)): ?>
                    <img src="<?= $profile_photo ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
            <div><div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="sb-user-role">KARYAWAN</div></div>
            <a href="../logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- ═══ MAIN & TOPBAR ═══ -->
<<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Kelola Lapangan</div>
            <div class="topbar-breadcrumb">Operasional / Lapangan</div>
        </div>
        <div class="topbar-right">
            <div id="clock-display">
                <div class="clock-time">
                    <span id="h">00</span><span class="clock-colon">:</span><span id="m">00</span><span class="clock-colon">:</span><span id="s">00</span>
                </div>
                <div class="clock-divider"></div>
                <div class="clock-date" id="full-date">MEMUAT...</div>
            </div>
            
            <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
            
            <a href="#" class="topbar-btn">
                <i class="fa-solid fa-bell"></i>
                <?php if(isset($total_pending) && $total_pending > 0): ?><span class="notif-dot"></span><?php endif; ?>
            </a>
            
            <div class="dropdown-wrap">
                <div class="topbar-user">
                    <div class="t-avatar"><i class="fa-solid fa-user"></i></div>
                    <div>
                        <div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                        <div class="t-role"><?= strtoupper(htmlspecialchars($role)) ?></div>
                    </div>
                    <i class="fa-solid fa-chevron-down t-chevron"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="../profile.php" class="dd-item"><i class="fa-solid fa-id-badge"></i> Profil Saya</a>
                    <hr class="dd-divider">
                    <a href="../logout.php" class="dd-item" style="color:var(--red);"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
                </div>
            </div>
        </div>
    </header>

    <div class="content">
        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <div class="page-title-tag"></div>
                <div class="page-title">Kelola Lapangan</div>
            </div>
            <div class="stat-chips">
                <div class="stat-chip chip-green">
                    <i class="fa-solid fa-circle-check"></i> AKTIF <span class="chip-val"><?= $cnt_ready ?></span>
                </div>
                <div class="stat-chip chip-red">
                    <i class="fa-solid fa-triangle-exclamation"></i> MAINTENANCE <span class="chip-val"><?= $cnt_maint ?></span>
                </div>
                <div class="stat-chip chip-blue">
                    <i class="fa-solid fa-layer-group"></i> TOTAL <span class="chip-val"><?= $total_lapangan ?></span>
                </div>
            </div>
        </div>

        <!-- ACTION BAR -->
        <div class="action-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="src" placeholder="Cari nama lapangan..." onkeyup="searchTable()">
            </div>
            
            <div style="display: flex; gap: 12px; align-items: center;">
                <div class="filter-dropdown-wrap">
                    <button class="btn-filter" id="btnFilterToggle">
                        <i class="fa-solid fa-filter"></i> Filter <i class="fa-solid fa-chevron-down arrow-icon"></i>
                    </button>
                    
                    <div class="filter-card" id="filterCard">
                        <h4>Filter Data</h4>
                        <form method="GET" action="lapangan.php">
                            <div class="filter-group">
                                <label>Urut Berdasarkan</label>
                                <select name="f_sort" class="filter-input">
                                    <option value="id_asc" <?= ($_GET['f_sort'] ?? '') === 'id_asc' ? 'selected' : '' ?>>ID Lapangan ↑</option>
                                    <option value="id_desc" <?= ($_GET['f_sort'] ?? '') === 'id_desc' ? 'selected' : '' ?>>ID Lapangan ↓</option>
                                    <option value="nama_asc" <?= ($_GET['f_sort'] ?? '') === 'nama_asc' ? 'selected' : '' ?>>Nama A - Z</option>
                                </select>
                            </div>
                            
                            <div class="filter-group">
                                <label>Status Lapangan</label>
                                <select name="f_status" class="filter-input">
                                    <option value="">Semua Status</option>
                                    <option value="1" <?= ($_GET['f_status'] ?? '') === '1' ? 'selected' : '' ?>>AKTIF</option>
                                    <option value="0" <?= ($_GET['f_status'] ?? '') === '0' ? 'selected' : '' ?>>MAINTENANCE</option>
                                </select>
                            </div>
                            
                            <div class="filter-buttons">
                                <button type="button" class="btn-filter-reset" onclick="resetFilter()"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                                <button type="submit" class="btn-filter-apply"><i class="fa-solid fa-check"></i> Terapkan</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <a href="lapangan.php?add=1" class="btn-add"><i class="fa-solid fa-plus"></i> Tambah Lapangan</a>
            </div>
        </div>

        <!-- TABLE CARD -->
        <div class="card">
            <?php if ($query_error): ?>
                <div style="padding:20px;background:#fee;border:1px solid #fcc;border-radius:8px;margin:20px 0;">
                    <p style="color:#c00;font-weight:bold;margin:0;"><i class="fa-solid fa-circle-exclamation"></i> Gagal mengambil data dari database. Silakan refresh halaman atau hubungi administrator.</p>
                    <p style="color:#666;font-size:11px;margin:5px 0 0;">Error: <?php echo htmlspecialchars($query_error_msg); ?></p>
                </div>
            <?php else: ?>
                <div class="table-wrap">
                 <table class="data-table" id="tbl">
                    <thead>
                        <tr>
                            <th style="width: 80px;">No</th>
                            <th>Nama Lapangan</th>
                            <th>Harga Sewa</th>
                            <th style="width: 150px;">Status</th>
                            <th style="text-align: left; width: 180px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $has_data = false;
                    $row_num = 0;
                    $no = $offset + 1; 

                    if (!$query_error && $query):
                    while ($row = safe_sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                        $has_data = true;
                        $row_num++;
                        $is_ready = $row['Status'] == 1;
                    ?>
                        <tr class="row-<?= $row_num % 2 == 1 ? 'odd' : 'even' ?>">
                            <td class="lap-id" style="font-family:'Barlow'; font-weight:700; color:var(--text);"><?= $no++ ?></td>
                            <td class="lap-name"><?= htmlspecialchars($row['Nama_Lapangan']) ?></td>
                            <td class="lap-price"><?= rupiah($row['Harga_Sewa']) ?></td>
                            <td>
                                <span class="status-pill <?= $is_ready ? 'sp-ready' : 'sp-maint' ?>">
                                    <span class="sp-dot"></span>
                                    <?= $is_ready ? 'AKTIF' : 'MAINTENANCE' ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="?detail_id=<?= $row['ID_Lapangan'] ?>" class="btn-action btn-view" title="Lihat Detail">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="?edit_id=<?= $row['ID_Lapangan'] ?>" class="btn-action btn-edit" title="Edit Lapangan">
                                        <i class="fa-solid fa-pen-to-square"></i>
                                    </a>
                                    <label class="toggle-switch" title="<?= $is_ready ? 'Nonaktifkan' : 'Aktifkan' ?> lapangan">
                                        <input type="checkbox" <?= $is_ready ? 'checked' : '' ?> onchange="confirmToggle('<?= $row['ID_Lapangan'] ?>', <?= $row['Status'] ?>)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <button onclick="confirmDelete('<?= $row['ID_Lapangan'] ?>', '<?= htmlspecialchars($row['Nama_Lapangan']) ?>')" class="btn-action btn-delete" title="Hapus Lapangan">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                    
                    <?php if (!$has_data): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fa-solid fa-layer-group"></i>
                                    <div>Belum ada data lapangan</div>
                                    <div style="font-size: 12px; font-weight: 500; margin-top: 8px; opacity: .7;">Tambah lapangan baru untuk memulai</div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrap">
            <div class="pagination-info">
                Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> - 
                <strong><?= min($page * $limit, $total_lapangan) ?></strong> dari 
                <strong><?= $total_lapangan ?></strong> data
            </div>
            <div class="pagination-nav">
                <a href="?page=1<?= $filter_url ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Pertama">
                    <i class="fa-solid fa-angles-left"></i>
                </a>
                <a href="?page=<?= $page - 1 ?><?= $filter_url ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Sebelumnya">
                    <i class="fa-solid fa-angle-left"></i>
                </a>
                
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
                    <a href="?page=1<?= $filter_url ?>" class="page-btn">1</a>
                    <?php if ($start_page > 2): ?><span class="page-ellipsis">...</span><?php endif; ?>
                <?php endif; ?>
                
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=<?= $i ?><?= $filter_url ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                
                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?><span class="page-ellipsis">...</span><?php endif; ?>
                    <a href="?page=<?= $total_pages ?><?= $filter_url ?>" class="page-btn"><?= $total_pages ?></a>
                <?php endif; ?>
                
                <a href="?page=<?= $page + 1 ?><?= $filter_url ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Selanjutnya">
                    <i class="fa-solid fa-angle-right"></i>
                </a>
                <a href="?page=<?= $total_pages ?><?= $filter_url ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Terakhir">
                    <i class="fa-solid fa-angles-right"></i>
                </a>
            </div>
        </div>
        <?php else: ?>
        <div class="pagination-wrap">
            <div class="pagination-info">
                Menampilkan <strong>1</strong> - <strong><?= $total_lapangan ?></strong> dari <strong><?= $total_lapangan ?></strong> data
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
function closeModal() { 
    window.location.href = 'lapangan.php'; 
}

function searchTable() {
    var input = document.getElementById('src').value.toUpperCase();
    var rows = document.getElementById('tbl').getElementsByTagName('tr');
    
    for (var i = 1; i < rows.length; i++) {
        var tdName = rows[i].getElementsByTagName('td')[2];
        var tdId = rows[i].getElementsByTagName('td')[1];
        
        if (tdName || tdId) {
            var match = false;
            if (tdName && tdName.textContent.toUpperCase().indexOf(input) > -1) match = true;
            if (tdId && tdId.textContent.toUpperCase().indexOf(input) > -1) match = true;
            
            rows[i].style.display = match ? '' : 'none';
        }
    }
}

function confirmToggle(id, status) {
    const action = status == 1 ? 'nonaktifkan' : 'aktifkan';
    const icon = status == 1 ? 'warning' : 'question';
    
    Swal.fire({
        title: 'Konfirmasi',
        text: `Apakah Anda yakin ingin ${action} lapangan ini?`,
        icon: icon,
        showCancelButton: true,
        confirmButtonColor: '#FF4500',
        cancelButtonColor: '#6B7280',
        confirmButtonText: `Ya, ${action}!`,
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `?toggle_id=${id}&s=${status}`;
        } else {
            var checkbox = document.querySelector(`input[onchange*="confirmToggle('${id}'"]`);
            if (checkbox) checkbox.checked = !checkbox.checked;
        }
    });
}

function confirmDelete(id, name) {
    Swal.fire({
        title: 'Hapus Lapangan?',
        html: `Anda akan menghapus lapangan <strong style="color:var(--orange);">${name}</strong><br>Data akan dihapus <strong style="color:var(--red);">permanen</strong>!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = '?delete_id=' + id;
        }
    });
}

function validateForm() {
    let valid = true;
    
    const nama = document.getElementById('nama_arena');
    const harga = document.getElementById('harga');
    
    const valNama = document.getElementById('val-nama_arena');
    const valHarga = document.getElementById('val-harga');

    const namaVal = nama.value.trim();
    const onlyNumbers = /^[0-9\s]+$/;

    nama.classList.remove('error');
    valNama.classList.remove('show');

    if (namaVal === '') {
        nama.classList.add('error');
        valNama.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama lapangan wajib diisi';
        valNama.classList.add('show');
        valid = false;
    } else if (namaVal.length < 3) {
        nama.classList.add('error');
        valNama.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama lapangan minimal 3 karakter';
        valNama.classList.add('show');
        valid = false;
    } else if (namaVal.length > 50) {
        nama.classList.add('error');
        valNama.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama lapangan maksimal 50 karakter';
        valNama.classList.add('show');
        valid = false;
    } else if (onlyNumbers.test(namaVal)) {
        nama.classList.add('error');
        valNama.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama lapangan tidak valid';
        valNama.classList.add('show');
        valid = false;
    }

    const hargaVal = harga.value.trim();
    const hargaNum = Number(hargaVal);

    harga.classList.remove('error');
    valHarga.classList.remove('show');

    if (hargaVal === '') {
        harga.classList.add('error');
        valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Harga sewa wajib diisi';
        valHarga.classList.add('show');
        valid = false;
    } else if (isNaN(hargaNum)) {
        harga.classList.add('error');
        valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Harga sewa harus berupa angka';
        valHarga.classList.add('show');
        valid = false;
    } else if (hargaNum < 0) {
        harga.classList.add('error');
        valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Harga sewa tidak boleh negatif';
        valHarga.classList.add('show');
        valid = false;
    } else if (hargaNum === 0) {
        harga.classList.add('error');
        valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Harga sewa harus lebih dari 0';
        valHarga.classList.add('show');
        valid = false;
    } else if (hargaNum < 50000) {
        harga.classList.add('error');
        valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Harga sewa terlalu kecil (Minimal Rp50.000)';
        valHarga.classList.add('show');
        valid = false;
    } else if (hargaNum > 1000000) {
        harga.classList.add('error');
        valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Harga sewa terlalu besar (Maksimal Rp1.000.000)';
        valHarga.classList.add('show');
        valid = false;
    }

    return valid;
}

document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('#nama_arena, #harga');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            this.classList.remove('error');
            const valMsg = document.getElementById('val-' + this.id);
            if (valMsg) valMsg.classList.remove('show');
        });
        
        input.addEventListener('blur', function() {
            validateForm();
        });
    });
});

function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    const s = String(now.getSeconds()).padStart(2, '0');
    document.getElementById('h').innerText = h;
    document.getElementById('m').innerText = m;
    document.getElementById('s').innerText = s;

    const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    document.getElementById('full-date').innerText = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
}
setInterval(updateClock, 1000);
updateClock();

const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
const msg = urlParams.get('msg');

const btnFilterToggle = document.getElementById('btnFilterToggle');
const filterCard = document.getElementById('filterCard');

if (btnFilterToggle && filterCard) {
    btnFilterToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        this.classList.toggle('active');
        filterCard.classList.toggle('open');
    });

    filterCard.addEventListener('click', function(e) {
        e.stopPropagation();
    });

    document.addEventListener('click', function() {
        btnFilterToggle.classList.remove('active');
        filterCard.classList.remove('open');
    });
}

function resetFilter() {
    window.location.href = 'lapangan.php';
}

if (status && msg) {
    Swal.fire({
        icon: status === 'success' ? 'success' : 'error',
        title: status === 'success' ? 'Berhasil!' : 'Gagal!',
        text: msg,
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end',
        timerProgressBar: true
    });
    window.history.replaceState({}, document.title, window.location.pathname);
}
</script>
</body>
</html>