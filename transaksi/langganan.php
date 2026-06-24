<?php
session_start();
include '../includes/config.php';

// --- HAK AKSES ---
// Sesuai proses bisnis: Customer hanya bisa lihat riwayat transaksi milik pribadi
// Karyawan bisa mengelola semua data langganan
if (!isset($_SESSION['role'])) {
    header("Location: ../login/login.php");
    exit();
}

$role = $_SESSION['role'];
$user_id = $_SESSION['id'] ?? 0;
$nama = $_SESSION['nama'] ?? 'USER';
$is_customer = ($role === 'customer');
$is_karyawan = ($role === 'karyawan' || $role === 'pemilik');

if (!$is_customer && !$is_karyawan) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
    exit();
}

// --- PROFILE PHOTO ---
$profile_photo = '';
if ($is_karyawan) {
    $stmt_photo = sqlsrv_query($conn, "SELECT Photo_Profile FROM Karyawan WHERE ID_Karyawan = ?", array($user_id));
} else {
    $stmt_photo = sqlsrv_query($conn, "SELECT Photo_Profile FROM Customer WHERE ID_Customer = ?", array($user_id));
}
if ($stmt_photo !== false) {
    $row_photo = sqlsrv_fetch_array($stmt_photo, SQLSRV_FETCH_ASSOC);
    if ($row_photo && !empty($row_photo['Photo_Profile'])) {
        $profile_photo = '../uploads/profiles/' . $row_photo['Photo_Profile'];
    }
}

// --- SAFE QUERY HELPER ---
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
            </div>";
            exit();
        }
        return false;
    }
    return $stmt;
}

function safe_sqlsrv_fetch_array($stmt, $fetch_type = SQLSRV_FETCH_ASSOC) {
    if ($stmt === false || $stmt === null) return false;
    return sqlsrv_fetch_array($stmt, $fetch_type);
}

// --- KONFIRMASI PEMBAYARAN (KARYAWAN ONLY) ---
if ($is_karyawan && isset($_GET['confirm_id'])) {
    $confirm_id = intval($_GET['confirm_id']);
    $modified_by = $_SESSION['nama'] ?? 'SYSTEM';

    // Update status langganan menjadi Aktif (1) dari Menunggu Konfirmasi (0)
    $stmt_confirm = safe_sqlsrv_query($conn, 
        "UPDATE Langganan SET Status = 1, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Langganan = ? AND Status = 0", 
        array($modified_by, $confirm_id), false
    );

    if ($stmt_confirm) {
        header("Location: langganan.php?status=success&msg=Pembayaran langganan berhasil dikonfirmasi!");
    } else {
        header("Location: langganan.php?status=error&msg=Gagal mengkonfirmasi pembayaran!");
    }
    exit();
}

// --- TOLAK PEMBAYARAN (KARYAWAN ONLY) ---
if ($is_karyawan && isset($_GET['reject_id'])) {
    $reject_id = intval($_GET['reject_id']);
    $modified_by = $_SESSION['nama'] ?? 'SYSTEM';

    $stmt_reject = safe_sqlsrv_query($conn, 
        "UPDATE Langganan SET Status = 3, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Langganan = ? AND Status = 0", 
        array($modified_by, $reject_id), false
    );

    if ($stmt_reject) {
        header("Location: langganan.php?status=success&msg=Langganan berhasil ditolak!");
    } else {
        header("Location: langganan.php?status=error&msg=Gagal menolak langganan!");
    }
    exit();
}

// --- DETAIL LANGGANAN ---
$detail_data = null;
$show_detail = false;
if (isset($_GET['detail_id'])) {
    $detail_sql = "SELECT l.*, c.Nama_Customer, c.Email, c.No_Telepon, tm.Nama_Tipe, tm.Harga_Member, tm.Potongan_Harga, k.Nama_Karyawan 
                   FROM Langganan l
                   LEFT JOIN Customer c ON l.ID_Customer = c.ID_Customer
                   LEFT JOIN Tipe_Member tm ON l.ID_Tipe = tm.ID_Tipe
                   LEFT JOIN Karyawan k ON l.ID_Karyawan = k.ID_Karyawan
                   WHERE l.ID_Langganan = ?";
    if ($is_customer) {
        $detail_sql .= " AND l.ID_Customer = ?";
        $r_detail = safe_sqlsrv_query($conn, $detail_sql, array($_GET['detail_id'], $user_id), false);
    } else {
        $r_detail = safe_sqlsrv_query($conn, $detail_sql, array($_GET['detail_id']), false);
    }
    if ($r_detail) {
        $detail_data = safe_sqlsrv_fetch_array($r_detail, SQLSRV_FETCH_ASSOC);
        $show_detail = ($detail_data !== false);
    }
}

// --- FILTER & SORTING ---
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'l.ID_Langganan';
$sort_order = isset($_GET['order']) && strtoupper($_GET['order']) == 'DESC' ? 'DESC' : 'ASC';
$filter_status = isset($_GET['status_filter']) ? $_GET['status_filter'] : 'all';
$filter_tipe = isset($_GET['tipe_filter']) ? intval($_GET['tipe_filter']) : 0;

$allowed_sort = ['l.ID_Langganan', 'c.Nama_Customer', 'tm.Nama_Tipe', 'l.Tanggal_Mulai', 'l.Total_Bayar', 'l.Status', 'l.Created_Date'];
if (!in_array($sort_by, $allowed_sort)) $sort_by = 'l.ID_Langganan';

$where_clauses = [];
$params = [];

// Customer hanya bisa lihat data sendiri (sesuai proses bisnis)
if ($is_customer) {
    $where_clauses[] = "l.ID_Customer = ?";
    $params[] = $user_id;
}

if ($filter_status != 'all') {
    $status_map = ['pending' => 0, 'aktif' => 1, 'berakhir' => 2, 'ditolak' => 3];
    if (isset($status_map[$filter_status])) {
        $where_clauses[] = "l.Status = ?";
        $params[] = $status_map[$filter_status];
    }
}

if ($filter_tipe > 0) {
    $where_clauses[] = "l.ID_Tipe = ?";
    $params[] = $filter_tipe;
}

$where_sql = count($where_clauses) > 0 ? implode(" AND ", $where_clauses) : "1=1";

// --- STAT COUNTS ---
$count_sql = "SELECT COUNT(*) as t FROM Langganan l WHERE " . str_replace("l.", "", $where_sql);
$q_total = safe_sqlsrv_query($conn, $count_sql, $params, false);
$total_langganan = 0;
if ($q_total !== false) {
    $row_total = safe_sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC);
    $total_langganan = $row_total['t'] ?? 0;
}

// Count by status
$status_counts = ['pending' => 0, 'aktif' => 0, 'berakhir' => 0, 'ditolak' => 0];
$status_labels = [0 => 'pending', 1 => 'aktif', 2 => 'berakhir', 3 => 'ditolak'];
foreach ($status_labels as $st => $label) {
    $st_where = "Status = ?";
    $st_params = [$st];
    if ($is_customer) {
        $st_where .= " AND ID_Customer = ?";
        $st_params[] = $user_id;
    }
    $q_st = safe_sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Langganan WHERE " . $st_where, $st_params, false);
    if ($q_st !== false) {
        $r_st = safe_sqlsrv_fetch_array($q_st, SQLSRV_FETCH_ASSOC);
        $status_counts[$label] = $r_st['t'] ?? 0;
    }
}

$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$total_pages = max(1, ceil($total_langganan / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

// --- MAIN QUERY ---
$query_sql = "SELECT l.ID_Langganan, l.ID_Customer, l.ID_Tipe, l.Tanggal_Mulai, l.Tanggal_Selesai, 
                      l.Total_Bayar, l.Metode_Pembayaran, l.Status, l.Created_Date,
                      c.Nama_Customer, c.Email, tm.Nama_Tipe, tm.Harga_Member, tm.Potongan_Harga
               FROM Langganan l
               LEFT JOIN Customer c ON l.ID_Customer = c.ID_Customer
               LEFT JOIN Tipe_Member tm ON l.ID_Tipe = tm.ID_Tipe
               WHERE " . $where_sql . 
               " ORDER BY " . $sort_by . " " . $sort_order . 
               " OFFSET " . intval($offset) . " ROWS FETCH NEXT " . intval($limit) . " ROWS ONLY";

$query = safe_sqlsrv_query($conn, $query_sql, $params, false);

$query_error = false;
$query_error_msg = '';
if ($query === false) {
    $query_error = true;
    $errors = sqlsrv_errors();
    if ($errors) {
        foreach ($errors as $error) {
            $query_error_msg .= "[" . $error['SQLSTATE'] . "] " . $error['message'] . " ";
        }
    }
}

// --- GET TIPE MEMBER LIST FOR FILTER ---
$tipe_list = [];
$q_tipe = safe_sqlsrv_query($conn, "SELECT ID_Tipe, Nama_Tipe FROM Tipe_Member WHERE Status = 1 ORDER BY ID_Tipe", [], false);
if ($q_tipe !== false) {
    while ($row_tipe = safe_sqlsrv_fetch_array($q_tipe, SQLSRV_FETCH_ASSOC)) {
        $tipe_list[] = $row_tipe;
    }
}

// --- PENDING NOTIFICATIONS (KARYAWAN) ---
$total_pending = 0;
if ($is_karyawan) {
    $q_pending = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Langganan WHERE Status = 0");
    if ($q_pending !== false) {
        $row_pending = sqlsrv_fetch_array($q_pending, SQLSRV_FETCH_ASSOC);
        $total_pending = $row_pending['t'] ?? 0;
    }
}

// --- HELPER FUNCTIONS ---
function format_rupiah($angka) {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

function format_tgl($date) {
    if (empty($date)) return '-';
    if (is_object($date) && method_exists($date, 'format')) {
        $d = $date->format('Y-m-d');
    } else {
        $d = $date;
    }
    $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $parts = explode('-', $d);
    return (int)$parts[2] . ' ' . $bulan[(int)$parts[1]-1] . ' ' . $parts[0];
}

function status_langganan_label($status) {
    $labels = [
        0 => ['text' => 'Menunggu Konfirmasi', 'class' => 'sp-pending', 'icon' => 'fa-clock'],
        1 => ['text' => 'Aktif', 'class' => 'sp-active', 'icon' => 'fa-circle-check'],
        2 => ['text' => 'Berakhir', 'class' => 'sp-ended', 'icon' => 'fa-circle-xmark'],
        3 => ['text' => 'Ditolak', 'class' => 'sp-rejected', 'icon' => 'fa-ban']
    ];
    return $labels[$status] ?? ['text' => 'Unknown', 'class' => 'sp-pending', 'icon' => 'fa-question'];
}

function status_langganan_badge($status) {
    $labels = [
        0 => ['text' => 'MENUNGGU', 'class' => 'status-pending'],
        1 => ['text' => 'AKTIF', 'class' => 'status-active'],
        2 => ['text' => 'BERAKHIR', 'class' => 'status-ended'],
        3 => ['text' => 'DITOLAK', 'class' => 'status-rejected']
    ];
    return $labels[$status] ?? ['text' => 'UNKNOWN', 'class' => 'status-pending'];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Langganan | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10); --green-dk: #059669;
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10); --blue-dk: #2563EB;
    --yellow: #F59E0B; --yellow-lt: rgba(245,158,11,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10); --red-dk: #DC2626;
    --purple: #8B5CF6; --purple-lt: rgba(139,92,246,.10);
    --gray: #6B7280; --gray-lt: rgba(107,114,128,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --text-md: #374151; --muted: #6B7280; --bg: #F3F4F6;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

/* ========== SIDEBAR (SAMA PERSIS CUSTOMER.PHP) ========== */
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
.sb-bottom { margin-top: auto; padding-top: 20px; }
.sb-user { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.04); border-radius: 12px; padding: 12px; border: 1px solid rgba(255,255,255,.06); }
.sb-avatar { width: 36px; height: 36px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; overflow: hidden; }
.sb-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; line-height: 1.1; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
.sb-logout:hover { color: var(--red); background: rgba(239,68,68,.1); }

/* ========== MAIN CONTENT ========== */
.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.content { padding: 32px 40px; flex: 1; }

/* ========== TOPBAR (SAMA PERSIS CUSTOMER.PHP) ========== */
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
.t-avatar { width: 32px; height: 32px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; overflow: hidden; }
.t-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.t-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.1; text-transform: uppercase; }
.t-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.t-chevron { color: var(--muted); font-size: 10px; margin-left: 4px; }
.dropdown-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #fff; min-width: 200px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 15px 40px rgba(0,0,0,.12); overflow: hidden; padding: 8px 0; z-index: 999; }
.dropdown-wrap:hover .dropdown-menu { display: block; }
.dropdown-wrap.active .dropdown-menu { display: block; }
.dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-item i { font-size: 14px; width: 18px; text-align: center; }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

#clock-display { display: flex; align-items: center; gap: 16px; }
.clock-time { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--orange); display: flex; align-items: center; gap: 6px; line-height: 1; }
.clock-colon { color: var(--orange); opacity: .5; animation: blink 1s infinite; }
@keyframes blink { 0%, 100% { opacity: .5; } 50% { opacity: 1; } }
.clock-divider { width: 1.5px; height: 28px; background-color: var(--border); }
.clock-date { font-family: 'Barlow', sans-serif; font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }

/* ========== PAGE HEADER ========== */
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
.page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }

/* ========== STAT CHIPS ========== */
.stat-chips { display: flex; gap: 10px; flex-wrap: wrap; }
.stat-chip { display: flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 10px; font-size: 12px; font-weight: 700; transition: all .2s; cursor: default; }
.stat-chip:hover { transform: translateY(-2px); }
.chip-pending { background: var(--yellow-lt); color: var(--yellow); border: 1px solid rgba(245,158,11,.2); }
.chip-active { background: var(--green-lt); color: var(--green); border: 1px solid rgba(16,185,129,.2); }
.chip-ended { background: var(--gray-lt); color: var(--gray); border: 1px solid rgba(107,114,128,.2); }
.chip-rejected { background: var(--red-lt); color: var(--red); border: 1px solid rgba(239,68,68,.2); }
.chip-total { background: var(--blue-lt); color: var(--blue); border: 1px solid rgba(59,130,246,.2); }
.chip-val { font-family: 'Barlow Condensed'; font-size: 20px; font-weight: 900; }

/* ========== ACTION BAR ========== */
.action-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.search-box { position: relative; width: 300px; }
.search-box i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; }
.search-box input { width: 100%; padding: 10px 14px 10px 40px; background: var(--card-bg); border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; outline: none; transition: all .2s; color: var(--text); }
.search-box input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.search-box input::placeholder { color: #9CA3AF; }

/* ========== CARD & TABLE ========== */
.card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; transition: all .2s ease; }
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
    background: #FAFAFA;
}

.data-table th, .data-table td { padding: 16px 20px; vertical-align: middle; }

/* Kolom No */
.data-table th:nth-child(1), .data-table td:nth-child(1) { text-align: center !important; width: 6%; font-size: 14px; font-weight: 700; }
/* Kolom Customer/Tipe */
.data-table th:nth-child(2), .data-table td:nth-child(2) { width: 22%; text-align: left; }
/* Kolom Periode */
.data-table th:nth-child(3), .data-table td:nth-child(3) { width: 18%; text-align: left; }
/* Kolom Total Bayar */
.data-table th:nth-child(4), .data-table td:nth-child(4) { width: 16%; text-align: left; }
/* Kolom Metode */
.data-table th:nth-child(5), .data-table td:nth-child(5) { width: 12%; text-align: center; }
/* Kolom Status */
.data-table th:nth-child(6), .data-table td:nth-child(6) { width: 14%; text-align: center !important; }
/* Kolom Aksi */
.data-table th:nth-child(7), .data-table td:nth-child(7) { width: 18%; text-align: left !important; }

/* ========== STATUS PILLS ========== */
.status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 7px 14px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; }
.sp-pending { background: var(--yellow-lt); color: #B45309; }
.sp-active { background: var(--green-lt); color: var(--green); }
.sp-ended { background: var(--gray-lt); color: var(--gray); }
.sp-rejected { background: var(--red-lt); color: var(--red); }
.sp-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.sp-pending .sp-dot { background: var(--yellow); }
.sp-active .sp-dot { background: var(--green); }
.sp-ended .sp-dot { background: var(--gray); }
.sp-rejected .sp-dot { background: var(--red); }

/* ========== ACTIONS ========== */
.actions { display: flex; gap: 8px; justify-content: flex-start; align-items: center; }
.btn-action {
    width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; 
    border-radius: 10px; font-size: 13px; font-weight: 700; transition: all .25s cubic-bezier(.4,0,.2,1); 
    border: 1.5px solid transparent; cursor: pointer; background: none; text-decoration: none;
}
.btn-view { background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); color: #1E40AF; border-color: #BFDBFE; }
.btn-view:hover { background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: #fff; border-color: #3B82F6; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,.35); }
.btn-confirm { background: linear-gradient(135deg, #ECFDF5 0%, #D1FAE5 100%); color: #065F46; border-color: #A7F3D0; }
.btn-confirm:hover { background: linear-gradient(135deg, #10B981 0%, #059669 100%); color: #fff; border-color: #10B981; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(16,185,129,.35); }
.btn-reject { background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%); color: #991B1B; border-color: #FECACA; }
.btn-reject:hover { background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%); color: #fff; border-color: #EF4444; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(239,68,68,.35); }

/* ========== ZEBRA STRIPING & HOVER ========== */
.data-table tbody tr:nth-child(odd) { background-color: #FFF7ED; }
.data-table tbody tr:nth-child(even) { background-color: #FFFFFF; }
.data-table tbody tr:hover td { background-color: #FFEDD5 !important; }
.data-table tbody tr:nth-child(odd):hover { background-color: #FFEDD5; }
.data-table tbody tr:nth-child(even):hover { background-color: #FFEDD5; }

/* ========== PAGINATION ========== */
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

/* ========== EMPTY STATE ========== */
.empty-state { text-align: center; padding: 50px 20px; color: var(--muted); }
.empty-state i { font-size: 48px; margin-bottom: 16px; opacity: .3; display: block; }
.empty-state div { font-size: 14px; font-weight: 700; }

/* ========== MODAL ========== */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(6px); display: none; align-items: center; justify-content: center; z-index: 2000; }
.modal-overlay.open { display: flex; }
.modal-box { background: #fff; border-radius: 20px; width: 480px; max-height: 85vh; overflow-y: auto; overflow-x: hidden; box-shadow: 0 25px 60px rgba(0,0,0,.2); position: relative; }
.modal-header { padding: 20px 24px 14px; border-bottom: 1px solid var(--border); }
.modal-subtitle { font-size: 10px; font-weight: 800; color: var(--orange); text-transform: uppercase; margin-bottom: 4px; letter-spacing: .8px; }
.modal-title { font-family: 'Barlow Condensed', sans-serif; font-size: 18px; font-weight: 900; color: var(--text); }
.modal-body { padding: 16px 24px 24px; }
.modal-close { position: absolute; top: 20px; right: 20px; width: 36px; height: 36px; border: none; background: var(--border-lt); border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 16px; transition: all .2s; }
.modal-close:hover { background: var(--red-lt); color: var(--red); }

.detail-photo-card { text-align: center; margin-bottom: 16px; padding-bottom: 14px; border-bottom: 1.5px dashed var(--border); }
.detail-icon-wrap { width: 60px; height: 60px; background: var(--orange-lt); color: var(--orange); border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 10px; box-shadow: 0 6px 16px rgba(255,69,0,0.15); }
.detail-main-name { font-family: 'Barlow Condensed', sans-serif; font-size: 20px; font-weight: 900; color: var(--text); text-transform: uppercase; }

.info-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1px solid var(--border-lt); }
.info-row:last-child { border-bottom: none; }
.info-key { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.3px; }
.info-key i { color: var(--orange); font-size: 14px; width: 18px; text-align: center; }
.info-val { font-size: 14px; font-weight: 700; color: var(--text); }
.info-val.id-code { color: var(--orange); font-weight: 800; font-family: 'Barlow Condensed'; font-size: 16px; }
.info-val.harga-val { color: var(--green); font-weight: 800; }
.info-val.status-val { font-weight: 800; }

.btn-submit { width: 100%; background: var(--orange); color: #fff; border: none; padding: 14px; border-radius: 10px; font-weight: 800; font-size: 13px; cursor: pointer; transition: all .2s; text-transform: uppercase; letter-spacing: .5px; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-submit:hover { background: var(--orange-dk); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(255,69,0,.3); }

/* ========== FILTER ========== */
.filter-wrap { position: relative; display: inline-block; }
.btn-filter { display: inline-flex; align-items: center; gap: 8px; background: var(--orange); color: #fff; padding: 10px 20px; border-radius: 10px; font-size: 13px; font-weight: 800; border: none; cursor: pointer; text-transform: uppercase; letter-spacing: .5px; transition: all .2s ease; font-family: 'Barlow', sans-serif; }
.btn-filter:hover { background: var(--orange-dk); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(255,69,0,.25); }
.btn-filter i { font-size: 12px; }
.btn-filter .arrow-icon { transition: transform .2s; }
.btn-filter.active .arrow-icon { transform: rotate(180deg); }

.filter-card { position: absolute; top: calc(100% + 12px); right: 0; width: 340px; max-height: calc(100vh - 200px); background: #fff; border-radius: 16px; border: 1px solid var(--border-lt); box-shadow: 0 20px 60px rgba(0,0,0,.15); z-index: 3000; padding: 24px; overflow-y: auto; opacity: 0; visibility: hidden; transform: translateY(-10px) scale(0.98); transform-origin: top right; transition: all .2s cubic-bezier(.4,0,.2,1); }
.filter-card.open { opacity: 1; visibility: visible; transform: translateY(0) scale(1); }
.filter-card::before { content: ''; position: absolute; top: -6px; right: 50px; width: 12px; height: 12px; background: #fff; transform: rotate(45deg); border-left: 1px solid var(--border-lt); border-top: 1px solid var(--border-lt); }
.filter-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; }
.filter-title { font-family: 'Barlow', sans-serif; font-size: 16px; font-weight: 800; color: var(--text); letter-spacing: -.2px; }
.filter-close { width: 28px; height: 28px; border-radius: 6px; border: none; background: transparent; color: var(--muted); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: .2s; font-size: 14px; }
.filter-close:hover { color: var(--red); }
.filter-group { margin-bottom: 16px; }
.filter-label { font-size: 11px; font-weight: 800; color: var(--text); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 8px; display: block; }
.filter-select { width: 100%; padding: 10px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-family: 'Barlow', sans-serif; font-size: 13px; color: var(--text-md); background: #fff; cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 40px; transition: all .2s; }
.filter-select:focus { outline: none; border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.filter-select:hover { border-color: #D1D5DB; }
.filter-actions { display: flex; gap: 10px; margin-top: 20px; padding-top: 16px; border-top: 1px solid var(--border-lt); }
.btn-filter-apply { flex: 1; background: var(--orange); color: #fff; border: none; padding: 10px 14px; border-radius: 10px; font-family: 'Barlow', sans-serif; font-size: 12px; font-weight: 800; cursor: pointer; text-transform: uppercase; letter-spacing: .5px; transition: .2s; display: flex; align-items: center; justify-content: center; gap: 6px; }
.btn-filter-apply:hover { background: var(--orange-dk); transform: translateY(-1px); box-shadow: 0 6px 16px rgba(255,69,0,.25); }
.btn-filter-reset { flex: 1; background: var(--bg); color: var(--text-md); border: 1.5px solid var(--border); padding: 10px 14px; border-radius: 10px; font-family: 'Barlow', sans-serif; font-size: 12px; font-weight: 800; cursor: pointer; text-transform: uppercase; letter-spacing: .5px; transition: .2s; display: flex; align-items: center; justify-content: center; gap: 6px; text-decoration: none; }
.btn-filter-reset:hover { background: var(--border-lt); border-color: var(--text-md); }
.filter-active-badge { display: inline-flex; align-items: center; gap: 6px; background: var(--orange-lt); color: var(--orange); padding: 6px 14px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; margin-right: 8px; }

/* ========== RESPONSIVE ========== */
@media(max-width: 1100px) { .page-header { flex-direction: column; align-items: flex-start; } }
@media(max-width: 768px) {
    .sidebar { width: 0; overflow: hidden; padding: 0; }
    .main { margin-left: 0; }
    .content { padding: 20px; }
    .topbar { padding: 0 20px; }
    .stat-chips { width: 100%; }
    .search-box { width: 100%; }
    .action-bar { flex-direction: column; align-items: stretch; }
    .data-table th, .data-table td { padding: 12px 16px; font-size: 12px; }
    .btn-action { width: 32px; height: 32px; font-size: 11px; }
    .pagination-wrap { flex-direction: column; gap: 12px; }
    .modal-box { width: 90%; margin: 20px; }
}
</style>
</head>
<body>

<aside class="sidebar">
    <a href="../dashboard/view_admin.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">MANAGEMENT SYSTEM</div>
        </div>
    </a>
    <div class="sb-section-label">Operasional</div>
    <nav>
        <a href="../dashboard/view_admin.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>
            Dashboard
        </a>
        <a href="../master/customer.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>
            Kelola Customer
        </a>
        <a href="../master/lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>
            Kelola Lapangan
        </a>
        <a href="../master/fasilitas_lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-list-check"></i></div>
            Kelola Fasilitas
        </a>
        <a href="../master/jadwal.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-days"></i></div>
            Kelola Jadwal
        </a>
        <a href="../master/promo.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div>
            Kelola Promo
        </a>
        <a href="../master/tipe_member.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-card"></i></div>
            Kelola Tipe Member
        </a>
        <a href="../master/alat.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div>
            Kelola Alat
        </a>
    </nav>

    <div class="sb-section-label">Transaksi</div>
    <nav>
        <a href="booking.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>
            Kelola Booking
        </a>
        <a href="langganan.php" class="sb-link active">
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
                    <img src="<?= $profile_photo ?>" alt="Profile">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
            <div><div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="sb-user-role"><?= strtoupper(htmlspecialchars($role)) ?></div></div>
            <a href="../login/logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Kelola Langganan</div>
            <div class="topbar-breadcrumb">Transaksi / Langganan Member</div>
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
                <?php if($is_karyawan && $total_pending > 0): ?><span class="notif-dot"></span><?php endif; ?>
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
                <div class="page-title">Kelola Langganan</div>
            </div>
            <div class="stat-chips">
                <div class="stat-chip chip-pending"><i class="fa-solid fa-clock"></i> MENUNGGU <span class="chip-val"><?= $status_counts['pending'] ?></span></div>
                <div class="stat-chip chip-active"><i class="fa-solid fa-circle-check"></i> AKTIF <span class="chip-val"><?= $status_counts['aktif'] ?></span></div>
                <div class="stat-chip chip-ended"><i class="fa-solid fa-circle-xmark"></i> BERAKHIR <span class="chip-val"><?= $status_counts['berakhir'] ?></span></div>
                <div class="stat-chip chip-rejected"><i class="fa-solid fa-ban"></i> DITOLAK <span class="chip-val"><?= $status_counts['ditolak'] ?></span></div>
                <div class="stat-chip chip-total"><i class="fa-solid fa-crown"></i> TOTAL <span class="chip-val"><?= $total_langganan ?></span></div>
            </div>
        </div>

        <div class="action-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="src" placeholder="Cari langganan..." onkeyup="searchTable()">
            </div>
            <div class="action-right">
                <div class="filter-wrap">
                    <?php if ($filter_status != 'all' || $filter_tipe > 0): ?>
                    <span class="filter-active-badge"><i class="fa-solid fa-filter"></i> Filter Aktif</span>
                    <?php endif; ?>
                    <button class="btn-filter" id="btnFilterToggle">
                        <i class="fa-solid fa-filter"></i> Filter <i class="fa-solid fa-chevron-down arrow-icon"></i>
                    </button>

                    <div class="filter-card" id="filterCard">
                        <div class="filter-head">
                            <div class="filter-title">Filter Data</div>
                            <button type="button" class="filter-close" id="filterClose">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                        <form method="GET" id="filterForm">
                            <input type="hidden" name="page" value="1">

                            <div class="filter-group">
                                <label class="filter-label">Urut Berdasarkan</label>
                                <select name="sort" class="filter-select">
                                    <option value="l.ID_Langganan" <?= $sort_by == 'l.ID_Langganan' ? 'selected' : '' ?>>ID Langganan</option>
                                    <option value="c.Nama_Customer" <?= $sort_by == 'c.Nama_Customer' ? 'selected' : '' ?>>Nama Customer</option>
                                    <option value="tm.Nama_Tipe" <?= $sort_by == 'tm.Nama_Tipe' ? 'selected' : '' ?>>Tipe Member</option>
                                    <option value="l.Tanggal_Mulai" <?= $sort_by == 'l.Tanggal_Mulai' ? 'selected' : '' ?>>Tanggal Mulai</option>
                                    <option value="l.Total_Bayar" <?= $sort_by == 'l.Total_Bayar' ? 'selected' : '' ?>>Total Bayar</option>
                                    <option value="l.Status" <?= $sort_by == 'l.Status' ? 'selected' : '' ?>>Status</option>
                                    <option value="l.Created_Date" <?= $sort_by == 'l.Created_Date' ? 'selected' : '' ?>>Tanggal Dibuat</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Urutan</label>
                                <select name="order" class="filter-select">
                                    <option value="ASC" <?= $sort_order == 'ASC' ? 'selected' : '' ?>>Naik (A-Z)</option>
                                    <option value="DESC" <?= $sort_order == 'DESC' ? 'selected' : '' ?>>Turun (Z-A)</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Status Langganan</label>
                                <select name="status_filter" class="filter-select">
                                    <option value="all" <?= $filter_status == 'all' ? 'selected' : '' ?>>Semua Status</option>
                                    <option value="pending" <?= $filter_status == 'pending' ? 'selected' : '' ?>>Menunggu Konfirmasi</option>
                                    <option value="aktif" <?= $filter_status == 'aktif' ? 'selected' : '' ?>>Aktif</option>
                                    <option value="berakhir" <?= $filter_status == 'berakhir' ? 'selected' : '' ?>>Berakhir</option>
                                    <option value="ditolak" <?= $filter_status == 'ditolak' ? 'selected' : '' ?>>Ditolak</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label class="filter-label">Tipe Member</label>
                                <select name="tipe_filter" class="filter-select">
                                    <option value="0" <?= $filter_tipe == 0 ? 'selected' : '' ?>>Semua Tipe</option>
                                    <?php foreach ($tipe_list as $tipe): ?>
                                    <option value="<?= $tipe['ID_Tipe'] ?>" <?= $filter_tipe == $tipe['ID_Tipe'] ? 'selected' : '' ?>><?= htmlspecialchars($tipe['Nama_Tipe']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-actions">
                                <a href="langganan.php" class="btn-filter-reset">
                                    <i class="fa-solid fa-rotate-left"></i> Reset
                                </a>
                                <button type="submit" class="btn-filter-apply">
                                    <i class="fa-solid fa-check"></i> Terapkan
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($query_error): ?>
        <div style="padding:20px;background:#fee;border:1px solid #fcc;border-radius:8px;margin:20px 0;">
            <p style="color:#c00;font-weight:bold;margin:0;"><i class="fa-solid fa-circle-exclamation"></i> Gagal mengambil data dari database. Silakan refresh halaman atau hubungi administrator.</p>
            <p style="color:#666;font-size:11px;margin:5px 0 0;">Error: <?= htmlspecialchars($query_error_msg) ?></p>
        </div>
        <?php else: ?>

        <div class="card">
            <div class="table-wrap">
                <table class="data-table" id="tbl">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th><?= $is_customer ? 'Tipe Member' : 'Customer / Tipe' ?></th>
                            <th>Periode</th>
                            <th>Total Bayar</th>
                            <th>Metode</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $has_data = false;
                    $no = $offset + 1;
                    if ($query):
                    while ($row = safe_sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                        $has_data = true;
                        $status_int = isset($row['Status']) ? intval($row['Status']) : 0;
                        $status_info = status_langganan_label($status_int);
                        $is_pending = ($status_int === 0);
                    ?>
                        <tr>
                            <td><?= $no++ ?></td>
                            <td>
                                <?php if ($is_customer): ?>
                                    <div class="tipe-name"><?= htmlspecialchars($row['Nama_Tipe'] ?? '-') ?></div>
                                    <div style="font-size:12px;color:var(--muted);font-weight:600;"><?= format_rupiah($row['Harga_Member'] ?? 0) ?> /bulan</div>
                                <?php else: ?>
                                    <div class="cust-name" style="font-weight:700;color:var(--text);font-size:14px;"><?= htmlspecialchars($row['Nama_Customer'] ?? '-') ?></div>
                                    <div style="font-size:12px;color:var(--orange);font-weight:700;"><i class="fa-solid fa-crown"></i> <?= htmlspecialchars($row['Nama_Tipe'] ?? '-') ?></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="font-weight:700;font-size:13px;"><?= format_tgl($row['Tanggal_Mulai']) ?></div>
                                <div style="font-size:12px;color:var(--muted);font-weight:600;"><i class="fa-solid fa-arrow-right" style="font-size:10px;"></i> <?= format_tgl($row['Tanggal_Selesai']) ?></div>
                            </td>
                            <td class="tipe-harga" style="color:var(--green);font-weight:800;"><?= format_rupiah($row['Total_Bayar']) ?></td>
                            <td style="text-align:center;">
                                <span style="font-size:12px;font-weight:700;color:var(--text-md);background:var(--border-lt);padding:4px 10px;border-radius:6px;">
                                    <i class="fa-solid <?= $row['Metode_Pembayaran'] == 'QRIS' ? 'fa-qrcode' : 'fa-building-columns' ?>"></i> <?= htmlspecialchars($row['Metode_Pembayaran']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-pill <?= $status_info['class'] ?>">
                                    <span class="sp-dot"></span>
                                    <?= $status_info['text'] ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="?detail_id=<?= $row['ID_Langganan'] ?>" class="btn-action btn-view" title="Lihat Detail">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <?php if ($is_karyawan && $is_pending): ?>
                                    <a href="?confirm_id=<?= $row['ID_Langganan'] ?>" class="btn-action btn-confirm" title="Konfirmasi Pembayaran" onclick="return confirmAction(event, 'konfirmasi', '<?= htmlspecialchars($row['Nama_Customer'] ?? 'Customer', ENT_QUOTES) ?>')">
                                        <i class="fa-solid fa-check"></i>
                                    </a>
                                    <a href="?reject_id=<?= $row['ID_Langganan'] ?>" class="btn-action btn-reject" title="Tolak Pembayaran" onclick="return confirmAction(event, 'tolak', '<?= htmlspecialchars($row['Nama_Customer'] ?? 'Customer', ENT_QUOTES) ?>')">
                                        <i class="fa-solid fa-xmark"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                    <?php if (!$has_data): ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <i class="fa-solid fa-crown"></i>
                                <div>Belum ada data langganan</div>
                                <div style="font-size: 12px; font-weight: 500; margin-top: 8px; opacity: .7;">
                                    <?= $is_customer ? 'Anda belum memiliki langganan member. Silakan daftar member terlebih dahulu.' : 'Data langganan member akan muncul di sini setelah customer melakukan pendaftaran.' ?>
                                </div>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrap">
            <div class="pagination-info">Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> - <strong><?= min($page * $limit, $total_langganan) ?></strong> dari <strong><?= $total_langganan ?></strong> data</div>
            <div class="pagination-nav">
                <a href="?page=1&sort=<?= urlencode($sort_by) ?>&order=<?= $sort_order ?>&status_filter=<?= $filter_status ?>&tipe_filter=<?= $filter_tipe ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Pertama"><i class="fa-solid fa-angles-left"></i></a>
                <a href="?page=<?= $page - 1 ?>&sort=<?= urlencode($sort_by) ?>&order=<?= $sort_order ?>&status_filter=<?= $filter_status ?>&tipe_filter=<?= $filter_tipe ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Sebelumnya"><i class="fa-solid fa-angle-left"></i></a>

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
                    <a href="?page=1&sort=<?= urlencode($sort_by) ?>&order=<?= $sort_order ?>&status_filter=<?= $filter_status ?>&tipe_filter=<?= $filter_tipe ?>" class="page-btn">1</a>
                    <?php if ($start_page > 2): ?><span class="page-ellipsis">...</span><?php endif; ?>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <a href="?page=<?= $i ?>&sort=<?= urlencode($sort_by) ?>&order=<?= $sort_order ?>&status_filter=<?= $filter_status ?>&tipe_filter=<?= $filter_tipe ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?><span class="page-ellipsis">...</span><?php endif; ?>
                    <a href="?page=<?= $total_pages ?>&sort=<?= urlencode($sort_by) ?>&order=<?= $sort_order ?>&status_filter=<?= $filter_status ?>&tipe_filter=<?= $filter_tipe ?>" class="page-btn"><?= $total_pages ?></a>
                <?php endif; ?>

                <a href="?page=<?= $page + 1 ?>&sort=<?= urlencode($sort_by) ?>&order=<?= $sort_order ?>&status_filter=<?= $filter_status ?>&tipe_filter=<?= $filter_tipe ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Selanjutnya"><i class="fa-solid fa-angle-right"></i></a>
                <a href="?page=<?= $total_pages ?>&sort=<?= urlencode($sort_by) ?>&order=<?= $sort_order ?>&status_filter=<?= $filter_status ?>&tipe_filter=<?= $filter_tipe ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Terakhir"><i class="fa-solid fa-angles-right"></i></a>
            </div>
        </div>
        <?php else: ?>
        <div class="pagination-wrap"><div class="pagination-info">Menampilkan <strong>1</strong> - <strong><?= $total_langganan ?></strong> dari <strong><?= $total_langganan ?></strong> data</div></div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</main>

<!-- ============================================
   MODAL DETAIL LANGGANAN
   ============================================ -->
<div class="modal-overlay <?= $show_detail ? 'open' : '' ?>" id="modalDetail">
    <div class="modal-box">
        <button class="modal-close" onclick="closeDetail()"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0; padding-top: 20px;">
            <div class="modal-subtitle">Informasi Langganan Member</div>
            <div class="modal-title">Detail Langganan</div>
        </div>
        <div class="modal-body" style="padding-top: 8px;">
            <?php if ($detail_data): 
                $status_int = intval($detail_data['Status']);
                $status_info = status_langganan_label($status_int);
                $is_pending = ($status_int === 0);
            ?>
                <div class="detail-photo-card">
                    <div class="detail-icon-wrap"><i class="fa-solid fa-crown"></i></div>
                    <div class="detail-main-name"><?= htmlspecialchars($detail_data['Nama_Customer'] ?? 'Customer') ?></div>
                    <div style="font-size:13px;color:var(--muted);font-weight:600;margin-top:4px;">
                        <i class="fa-solid fa-crown" style="color:var(--orange);"></i> <?= htmlspecialchars($detail_data['Nama_Tipe'] ?? '-') ?>
                    </div>
                </div>

                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-fingerprint"></i> ID Langganan</span>
                    <span class="info-val id-code">#<?= str_pad($detail_data['ID_Langganan'], 4, '0', STR_PAD_LEFT) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-user"></i> Nama Customer</span>
                    <span class="info-val"><?= htmlspecialchars($detail_data['Nama_Customer'] ?? '-') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-envelope"></i> Email</span>
                    <span class="info-val"><?= htmlspecialchars($detail_data['Email'] ?? '-') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-crown"></i> Tipe Member</span>
                    <span class="info-val" style="color:var(--orange);font-weight:800;"><?= htmlspecialchars($detail_data['Nama_Tipe'] ?? '-') ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-money-bill-wave"></i> Harga Member</span>
                    <span class="info-val harga-val"><?= format_rupiah($detail_data['Harga_Member'] ?? 0) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-percent"></i> Potongan Harga</span>
                    <span class="info-val" style="color:var(--orange);font-weight:800;"><?= format_rupiah($detail_data['Potongan_Harga'] ?? 0) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-calendar-days"></i> Periode</span>
                    <span class="info-val"><?= format_tgl($detail_data['Tanggal_Mulai']) ?> <i class="fa-solid fa-arrow-right" style="font-size:10px;color:var(--muted);"></i> <?= format_tgl($detail_data['Tanggal_Selesai']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-wallet"></i> Total Bayar</span>
                    <span class="info-val harga-val"><?= format_rupiah($detail_data['Total_Bayar']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-credit-card"></i> Metode Pembayaran</span>
                    <span class="info-val">
                        <span style="font-size:12px;font-weight:700;color:var(--text-md);background:var(--border-lt);padding:4px 10px;border-radius:6px;">
                            <i class="fa-solid <?= $detail_data['Metode_Pembayaran'] == 'QRIS' ? 'fa-qrcode' : 'fa-building-columns' ?>"></i> <?= htmlspecialchars($detail_data['Metode_Pembayaran']) ?>
                        </span>
                    </span>
                </div>
                <div class="info-row" style="border-bottom:none;">
                    <span class="info-key"><i class="fa-solid fa-shield-halved"></i> Status</span>
                    <span class="info-val">
                        <span class="status-pill <?= $status_info['class'] ?>">
                            <span class="sp-dot"></span>
                            <?= $status_info['text'] ?>
                        </span>
                    </span>
                </div>

                <?php if ($is_karyawan && $is_pending): ?>
                <div style="display:flex;gap:10px;margin-top:16px;">
                    <a href="?confirm_id=<?= $detail_data['ID_Langganan'] ?>" class="btn-submit" style="flex:1;background:var(--green);text-decoration:none;" onclick="return confirmAction(event, 'konfirmasi', '<?= htmlspecialchars($detail_data['Nama_Customer'] ?? 'Customer', ENT_QUOTES) ?>')">
                        <i class="fa-solid fa-check"></i> Konfirmasi
                    </a>
                    <a href="?reject_id=<?= $detail_data['ID_Langganan'] ?>" class="btn-submit" style="flex:1;background:var(--red);text-decoration:none;" onclick="return confirmAction(event, 'tolak', '<?= htmlspecialchars($detail_data['Nama_Customer'] ?? 'Customer', ENT_QUOTES) ?>')">
                        <i class="fa-solid fa-xmark"></i> Tolak
                    </a>
                </div>
                <?php else: ?>
                <button onclick="closeDetail()" class="btn-submit" style="margin-top: 16px; background: #0D1117;">
                    <i class="fa-solid fa-arrow-left"></i> Kembali Ke List
                </button>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state" style="padding:30px;">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <div>Data tidak ditemukan</div>
                </div>
                <button onclick="closeDetail()" class="btn-submit" style="margin-top: 16px; background: #0D1117;">
                    <i class="fa-solid fa-arrow-left"></i> Kembali Ke List
                </button>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
// ============================================
// CLOCK / JAM
// ============================================
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

    const dayName = days[now.getDay()];
    const date = now.getDate();
    const monthName = months[now.getMonth()];
    const year = now.getFullYear();

    document.getElementById('full-date').innerText = dayName + ', ' + date + ' ' + monthName + ' ' + year;
}

updateClock();
setInterval(updateClock, 1000);


// ============================================
// MODAL FUNCTIONS
// ============================================
function closeDetail() { 
    window.location.href = 'langganan.php'; 
}


// ============================================
// SEARCH TABLE
// ============================================
function searchTable() {
    var input = document.getElementById('src').value.toUpperCase();
    var rows = document.getElementById('tbl').getElementsByTagName('tr');
    for (var i = 1; i < rows.length; i++) {
        var tdCustomer = rows[i].getElementsByTagName('td')[1];
        var tdPeriode = rows[i].getElementsByTagName('td')[2];
        var tdMetode = rows[i].getElementsByTagName('td')[4];
        if (tdCustomer || tdPeriode || tdMetode) {
            var match = false;
            if (tdCustomer && tdCustomer.textContent.toUpperCase().indexOf(input) > -1) match = true;
            if (tdPeriode && tdPeriode.textContent.toUpperCase().indexOf(input) > -1) match = true;
            if (tdMetode && tdMetode.textContent.toUpperCase().indexOf(input) > -1) match = true;
            rows[i].style.display = match ? '' : 'none';
        }
    }
}


// ============================================
// CONFIRM ACTION (KONFIRMASI / TOLAK)
// ============================================
function confirmAction(event, action, name) {
    event.preventDefault();
    const url = event.currentTarget.href;
    const isConfirm = action === 'konfirmasi';
    const title = isConfirm ? 'Konfirmasi Pembayaran?' : 'Tolak Langganan?';
    const text = isConfirm 
        ? 'Konfirmasi pembayaran langganan dari ' + name + '?' 
        : 'Tolak langganan dari ' + name + '? Data ini akan dianggap ditolak.';
    const icon = isConfirm ? 'question' : 'warning';
    const confirmColor = isConfirm ? '#10B981' : '#EF4444';
    const confirmText = isConfirm ? 'Ya, Konfirmasi!' : 'Ya, Tolak!';

    Swal.fire({
        title: title,
        text: text,
        icon: icon,
        showCancelButton: true,
        confirmButtonColor: confirmColor,
        cancelButtonColor: '#6B7280',
        confirmButtonText: confirmText,
        cancelButtonText: 'Batal',
        reverseButtons: true,
        allowOutsideClick: false
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Memproses...',
                text: isConfirm ? 'Mengkonfirmasi pembayaran' : 'Menolak langganan',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            // Redirect to the URL from href attribute
            window.location.href = url;
        }
    });
    return false;
}


// ============================================
// FILTER DROPDOWN
// ============================================
document.addEventListener('DOMContentLoaded', function() {
    const btnFilterToggle = document.getElementById('btnFilterToggle');
    const filterCard = document.getElementById('filterCard');
    const filterClose = document.getElementById('filterClose');

    if (btnFilterToggle && filterCard) {
        btnFilterToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('active');
            filterCard.classList.toggle('open');
        });

        filterCard.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        if (filterClose) {
            filterClose.addEventListener('click', function() {
                btnFilterToggle.classList.remove('active');
                filterCard.classList.remove('open');
            });
        }

        document.addEventListener('click', function() {
            btnFilterToggle.classList.remove('active');
            filterCard.classList.remove('open');
        });
    }

    // Dropdown profil user
    const userDropdown = document.querySelector('.dropdown-wrap');
    if (userDropdown) {
        userDropdown.addEventListener('click', function (e) {
            e.stopPropagation();
            this.classList.toggle('active');
        });
        document.addEventListener('click', function () {
            userDropdown.classList.remove('active');
        });
    }


// ============================================
// URL PARAMETER NOTIFICATION (Status & Msg)
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
            timer: 3000,
            showConfirmButton: false,
            toast: true,
            position: 'top-end',
            timerProgressBar: true,
            showCloseButton: true
        });

        window.history.replaceState({}, document.title, window.location.pathname);
    }
});
</script>
</body>
</html>