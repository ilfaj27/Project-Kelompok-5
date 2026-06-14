<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
    exit();
}
$role = $_SESSION['role'];
$nama = $_SESSION['nama'] ?? 'USER';

$profile_photo = '';
$stmt_photo = sqlsrv_query($conn, "SELECT Foto_Profil FROM Karyawan WHERE Nama = ?", array($nama));
if ($stmt_photo !== false) {
    $row_photo = sqlsrv_fetch_array($stmt_photo, SQLSRV_FETCH_ASSOC);
    if ($row_photo && !empty($row_photo['Foto_Profil'])) {
        $profile_photo = '../uploads/profiles/' . $row_photo['Foto_Profil'];
    }
}

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
            echo "<div style='padding:20px;background:#fee;border:1px solid #fcc;border-radius:8px;font-family:sans-serif;margin:20px;'>"
                . "<h3 style='color:#c00;margin:0 0 10px;'><i class='fa-solid fa-circle-exclamation'></i> Database Error</h3>"
                . "<p style='color:#333;margin:0 0 5px;'><strong>Detail Error:</strong></p>"
                . "<pre style='background:#fff;padding:10px;border-radius:4px;overflow-x:auto;font-size:12px;'>" . htmlspecialchars($error_msg) . "</pre>"
                . "<p style='color:#666;font-size:12px;margin:10px 0 0;'>SQL: " . htmlspecialchars($sql) . "</p>"
                . "<p style='color:#666;font-size:12px;margin:5px 0 0;'>Silakan periksa koneksi database atau hubungi administrator.</p>"
                . "</div>";
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

function safe_sqlsrv_has_rows($stmt) {
    if ($stmt === false || $stmt === null) return false;
    return sqlsrv_has_rows($stmt);
}

// CRUD
// CRUD
if (isset($_POST['save_tipe'])) {
    $id = trim($_POST['id_tipe']);
    $nama_tipe = trim($_POST['nama_tipe']);
    $harga_member_raw = $_POST['harga_member'];
    $potongan_harga_raw = $_POST['potongan_harga'];

    // ── VALIDASI NAMA TIPE MEMBER ──
    if ($nama_tipe === '') {
        header("Location: tipe_member.php?page=1&status=error&msg=Nama tipe member wajib diisi.");
        exit();
    }
    if (strlen($nama_tipe) < 3) {
        header("Location: tipe_member.php?page=1&status=error&msg=Nama tipe member minimal 3 karakter.");
        exit();
    }
    if (is_numeric($nama_tipe)) {
        header("Location: tipe_member.php?page=1&status=error&msg=Nama tipe member tidak boleh hanya angka.");
        exit();
    }

    // ── VALIDASI HARGA MEMBER ──
    if ($harga_member_raw === '') {
        header("Location: tipe_member.php?page=1&status=error&msg=Harga member wajib diisi.");
        exit();
    }
    if (!is_numeric($harga_member_raw)) {
        header("Location: tipe_member.php?page=1&status=error&msg=Harga member harus berupa angka.");
        exit();
    }
    $harga_member = floatval($harga_member_raw);
    if ($harga_member == 0) {
        header("Location: tipe_member.php?page=1&status=error&msg=Harga member tidak boleh 0.");
        exit();
    }
    if ($harga_member < 0) {
        header("Location: tipe_member.php?page=1&status=error&msg=Harga member tidak boleh kurang dari 0.");
        exit();
    }
    if ($harga_member < 80000) {
        header("Location: tipe_member.php?page=1&status=error&msg=Harga member minimal 80000.");
        exit();
    }

    // ── VALIDASI POTONGAN HARGA ──
// ── VALIDASI POTONGAN HARGA ──
    if ($potongan_harga_raw === '') {
        header("Location: tipe_member.php?page=1&status=error&msg=Potongan harga wajib diisi.");
        exit();
    }
    if (!is_numeric($potongan_harga_raw)) {
        header("Location: tipe_member.php?page=1&status=error&msg=Potongan harga harus berupa angka.");
        exit();
    }
    $potongan_harga = floatval($potongan_harga_raw);
    if ($potongan_harga < 0) {
        header("Location: tipe_member.php?page=1&status=error&msg=Potongan harga tidak boleh kurang dari 0.");
        exit();
    }
    // Tambahkan baris pengecekan minimal 50000 di bawah ini:
    if ($potongan_harga < 50000) {
        header("Location: tipe_member.php?page=1&status=error&msg=Potongan harga minimal 50000.");
        exit();
    }
    if ($potongan_harga > $harga_member) {
        header("Location: tipe_member.php?page=1&status=error&msg=Potongan harga tidak boleh lebih besar dari harga member.");
        exit();
    }

    // Validasi duplikat nama tipe
    $sql_check_name = "SELECT ID_Tipe FROM Tipe_Member WHERE Nama_Tipe = ? AND ID_Tipe <> ? AND Is_Deleted = 0";
    $q_check_name = safe_sqlsrv_query($conn, $sql_check_name, array($nama_tipe, $id), false);
    if ($q_check_name && safe_sqlsrv_has_rows($q_check_name)) {
        header("Location: tipe_member.php?page=1&status=error&msg=Nama tipe member sudah terdaftar.");
        exit();
    }

    if (isset($_POST['edit_mode'])) {
        safe_sqlsrv_query($conn, 
            "UPDATE Tipe_Member SET Nama_Tipe=?, Harga_Member=?, Potongan_Harga=?, Modified_By=?, Modified_Date=GETDATE() WHERE ID_Tipe=?", 
            array($nama_tipe, $harga_member, $potongan_harga, $nama, $id), false);
        header("Location: tipe_member.php?page=1&status=success&msg=Data tipe member berhasil diperbarui!");
    } else {
        if (empty($id)) {
            $q_max = safe_sqlsrv_query($conn, "SELECT MAX(ID_Tipe) as max_id FROM Tipe_Member", [], false);
            $d_max = safe_sqlsrv_fetch_array($q_max, SQLSRV_FETCH_ASSOC);
            $num = ($d_max['max_id']) ? (int) substr($d_max['max_id'], 2) + 1 : 1;
            $id = "TM" . sprintf("%06d", $num);
        }
        safe_sqlsrv_query($conn, 
            "INSERT INTO Tipe_Member (ID_Tipe, Nama_Tipe, Harga_Member, Potongan_Harga, Status, Is_Deleted, Created_By, Created_Date) VALUES (?,?,?,?,1,0,?,GETDATE())", 
            array($id, $nama_tipe, $harga_member, $potongan_harga, $nama), false);
        header("Location: tipe_member.php?page=1&status=success&msg=Tipe member baru berhasil ditambahkan!");
    }
    exit();
}

if (isset($_GET['toggle_id'])) {
    $s_baru = ($_GET['s'] == 1) ? 0 : 1;
    safe_sqlsrv_query($conn, "UPDATE Tipe_Member SET Status=? WHERE ID_Tipe=?", array($s_baru, $_GET['toggle_id']), false);
    header("Location: tipe_member.php?page=1&status=success&msg=Status tipe member berhasil diubah!");
    exit();
}

if (isset($_GET['delete_id'])) {
    $stmt = safe_sqlsrv_query($conn, 
        "UPDATE Tipe_Member SET Is_Deleted=1, Deleted_By=?, Deleted_Date=GETDATE() WHERE ID_Tipe=?", 
        array($nama, $_GET['delete_id']), false);
    header($stmt ? "Location: tipe_member.php?page=1&status=success&msg=Tipe member berhasil dihapus!" : "Location: tipe_member.php?page=1&status=error&msg=Gagal hapus data!");
    exit();
}

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $r = safe_sqlsrv_query($conn, "SELECT * FROM Tipe_Member WHERE ID_Tipe=? AND Is_Deleted=0", array($_GET['edit_id']), false);
    if ($r) $edit_data = safe_sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC);
}

$detail_data = null;
$show_detail = false;
if (isset($_GET['detail_id'])) {
    $r_detail = safe_sqlsrv_query($conn, "SELECT * FROM Tipe_Member WHERE ID_Tipe=? AND Is_Deleted=0", array($_GET['detail_id']), false);
    if ($r_detail) {
        $detail_data = safe_sqlsrv_fetch_array($r_detail, SQLSRV_FETCH_ASSOC);
        $show_detail = true;
    }
}

$show_add = isset($_GET['add']) && $_GET['add'] == '1';

$next_id_add = "";
if (!$edit_data) {
    $q_max = safe_sqlsrv_query($conn, "SELECT MAX(ID_Tipe) as max_id FROM Tipe_Member", [], false);
    $d_max = safe_sqlsrv_fetch_array($q_max, SQLSRV_FETCH_ASSOC);
    $num = ($d_max['max_id']) ? (int) substr($d_max['max_id'], 2) + 1 : 1;
    $next_id_add = "TM" . sprintf("%06d", $num);
}

// Filter & Sorting
$where_clauses = array("Is_Deleted = 0");
$query_params = array();
if (isset($_GET['f_status']) && $_GET['f_status'] !== '') {
    $where_clauses[] = "Status = " . intval($_GET['f_status']);
}
$where_sql = implode(" AND ", $where_clauses);

$sort_by = "ID_Tipe ASC";
if (isset($_GET['f_sort'])) {
    if ($_GET['f_sort'] === 'id_desc') $sort_by = "ID_Tipe DESC";
    elseif ($_GET['f_sort'] === 'nama_asc') $sort_by = "Nama_Tipe ASC";
    elseif ($_GET['f_sort'] === 'harga_desc') $sort_by = "Harga_Member DESC";
}

// Statistik
$q_active = safe_sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Tipe_Member WHERE Is_Deleted=0 AND Status=1", [], false);
$active_count = 0;
if ($q_active) { $row = safe_sqlsrv_fetch_array($q_active, SQLSRV_FETCH_ASSOC); $active_count = $row['total'] ?? 0; }

$q_inactive = safe_sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Tipe_Member WHERE Is_Deleted=0 AND Status=0", [], false);
$inactive_count = 0;
if ($q_inactive) { $row = safe_sqlsrv_fetch_array($q_inactive, SQLSRV_FETCH_ASSOC); $inactive_count = $row['total'] ?? 0; }

$count_res = safe_sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Tipe_Member WHERE $where_sql", $query_params, false);
$total_data = 0;
if ($count_res) { $total_row = safe_sqlsrv_fetch_array($count_res, SQLSRV_FETCH_ASSOC); $total_data = $total_row['total'] ?? 0; }

$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$total_pages = max(1, ceil($total_data / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

$query_sql = "SELECT * FROM Tipe_Member WHERE $where_sql ORDER BY $sort_by OFFSET " . intval($offset) . " ROWS FETCH NEXT " . intval($limit) . " ROWS ONLY";
$query = safe_sqlsrv_query($conn, $query_sql, $query_params, false);

$query_error = ($query === false);
$query_error_msg = '';
if ($query_error) {
    $errors = sqlsrv_errors();
    if ($errors) { foreach ($errors as $error) { $query_error_msg .= "[" . $error['SQLSTATE'] . "] " . $error['message'] . " "; } }
}

$filter_url = "";
if (isset($_GET['f_sort'])) $filter_url .= "&f_sort=" . urlencode($_GET['f_sort']);
if (isset($_GET['f_status'])) $filter_url .= "&f_status=" . urlencode($_GET['f_status']);

$q_pending = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Booking WHERE Status=1");
$total_pending = 0;
if ($q_pending !== false) { $row_pending = sqlsrv_fetch_array($q_pending, SQLSRV_FETCH_ASSOC); $total_pending = $row_pending['t'] ?? 0; }

function rupiah($n){ return 'Rp '.number_format($n,0,',','.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Tipe Member | HoopBall</title>
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

/* SIDEBAR */
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

/* MAIN & TOPBAR */
.main { margin-left: calc(var(--sidebar-w) - 1px); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
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

/* CONTENT */
.content { padding: 32px 40px; flex: 1; }
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 24px; flex-wrap: wrap; gap: 16px; }
.page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }

/* STAT CHIPS */
.stat-chips { display: flex; gap: 10px; flex-wrap: wrap; }
.stat-chip { display: flex; align-items: center; gap: 8px; padding: 8px 18px; border-radius: 10px; font-size: 12px; font-weight: 700; transition: all .2s; }
.stat-chip:hover { transform: translateY(-2px); }
.chip-green { background: var(--green-lt); color: var(--green); }
.chip-red   { background: var(--red-lt); color: var(--red); }
.chip-blue  { background: var(--blue-lt); color: var(--blue); }
.chip-val   { font-family: 'Barlow Condensed'; font-size: 20px; font-weight: 900; }

/* ACTION BAR */
.action-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.search-box { position: relative; width: 300px; }
.search-box i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; }
.search-box input { width: 100%; padding: 10px 14px 10px 40px; background: var(--card-bg); border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; outline: none; transition: all .2s; color: var(--text); }
.search-box input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.search-box input::placeholder { color: #9CA3AF; }

/* CARD & TABLE */
.card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; transition: all .2s ease; background-color: #FFFFFF !important; }
.main, .content { background-color: #F3F4F6 !important; }
.card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.06); }
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }

.data-table th {
    font-family: 'Barlow Condensed', sans-serif !important; font-size: 13px !important; font-weight: 900 !important; 
    color: var(--muted) !important; text-transform: uppercase !important; letter-spacing: 0.8px !important; 
    padding: 14px 20px; border-bottom: 2px solid var(--border-lt);
}
.data-table th, .data-table td { padding: 16px 20px; vertical-align: middle; }

/* Kolom No */
.data-table th:nth-child(1), .data-table td:nth-child(1) { text-align: center !important; width: 8%; font-size: 15px; font-weight: 700; }
/* Kolom Nama Tipe */
.data-table th:nth-child(2), .data-table td:nth-child(2) { width: 25%; text-align: left; }
.tipe-name { font-weight: 700; color: var(--text); font-size: 15px; }
/* Kolom Harga Member */
.data-table th:nth-child(3), .data-table td:nth-child(3) { width: 20%; text-align: left !important; }
.tipe-harga { font-family: 'Barlow', sans-serif; font-weight: 700; font-size: 15px; color: var(--text); }
/* Kolom Potongan */
.data-table th:nth-child(4), .data-table td:nth-child(4) { width: 20%; text-align: left !important; }
.tipe-potongan { font-family: 'Barlow', sans-serif; font-weight: 700; font-size: 15px; color: var(--green); }
/* Kolom Status */
.data-table th:nth-child(5), .data-table td:nth-child(5) { width: 15%; text-align: center !important; }
.data-table th:nth-child(5) { position: relative; left: -60px !important; }
.data-table td:nth-child(5) { font-size: 0 !important; }
.data-table td:nth-child(5) .status-pill { position: relative; left: -60px !important; display: inline-flex !important; font-size: 12px !important; margin: 0 !important; }
/* Kolom Aksi */
.data-table th:nth-child(6), .data-table td:nth-child(6) { width: 20%; text-align: left !important; }

.tipe-id-badge { color: var(--orange); font-weight: 800; font-family: 'Barlow Condensed'; font-size: 16px; }

/* STATUS TOGGLE SWITCH */
.toggle-switch { position: relative; display: inline-flex; align-items: center; width: 44px; height: 24px; cursor: pointer; margin: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--red); transition: .3s; border-radius: 24px; }
.toggle-slider::before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,.2); }
.toggle-switch input:checked + .toggle-slider { background-color: var(--green); }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }
.toggle-switch:hover .toggle-slider { opacity: .9; }

.status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 20px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; }
.sp-active { background: var(--green-lt); color: var(--green); }
.sp-inactive { background: var(--red-lt); color: var(--red); }
.sp-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.sp-active .sp-dot { background: var(--green); }
.sp-inactive .sp-dot { background: var(--red); }

/* ACTIONS */
.actions { display: flex; gap: 12px; justify-content: flex-start; align-items: center; }
.btn-action { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 14px; font-weight: 700; transition: all .25s cubic-bezier(.4,0,.2,1); border: 1.5px solid transparent; }
.btn-edit { background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); color: #1E40AF; border-color: #BFDBFE; }
.btn-edit:hover { background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: #fff; border-color: #3B82F6; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,.35); }
.btn-delete { background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%); color: #DC2626; border-color: #FECACA; }
.btn-delete:hover { background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%); color: #fff; border-color: #EF4444; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(239,68,68,.35); }
.btn-view { background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); color: #1E40AF; border-color: #BFDBFE; }
.btn-view:hover { background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: #fff; border-color: #3B82F6; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,.35); }

/* MODAL */
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
.modal-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.modal-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
.btn-submit { width: 100%; background: var(--orange); color: #fff; border: none; padding: 14px; border-radius: 10px; font-weight: 800; font-size: 13px; cursor: pointer; transition: all .2s; text-transform: uppercase; letter-spacing: .5px; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-submit:hover { background: var(--orange-dk); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(255,69,0,.3); }
.btn-cancel { display: block; text-align: center; margin-top: 16px; color: var(--muted); text-decoration: none; font-size: 13px; font-weight: 700; transition: .2s; cursor: pointer; }
.btn-cancel:hover { color: var(--orange); }
.modal-close { position: absolute; top: 20px; right: 20px; width: 36px; height: 36px; border: none; background: var(--border-lt); border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 16px; transition: all .2s; }
.modal-close:hover { background: var(--red-lt); color: var(--red); }

/* VALIDASI ERROR STATE */
.modal-input.error { border-color: var(--red) !important; background-color: #FEF2F2 !important; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.15) !important; }
.modal-input.error:focus { border-color: var(--red) !important; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.25) !important; }
.val-msg { font-size: 11px; color: var(--red); font-weight: 600; margin-bottom: 10px; display: none; min-height: 16px; }
.val-msg.show { display: block; }
.val-msg i { margin-right: 4px; }

/* PAGINATION */
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

/* EMPTY STATE */
.empty-state { text-align: center; padding: 50px 20px; color: var(--muted); }
.empty-state i { font-size: 48px; margin-bottom: 16px; opacity: .3; display: block; }
.empty-state div { font-size: 14px; font-weight: 700; }

/* ZEBRA STRIPING */
.data-table tbody tr:nth-child(odd) { background-color: #FFF7ED; }
.data-table tbody tr:nth-child(even) { background-color: #FFFFFF; }
.data-table tbody tr:hover td { background-color: #FFEDD5 !important; }
.data-table tbody tr:nth-child(odd):hover { background-color: #FFEDD5; }
.data-table tbody tr:nth-child(even):hover { background-color: #FFEDD5; }

/* CLOCK */
#clock-display { display: flex; align-items: center; gap: 16px; }
.clock-time { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--orange); display: flex; align-items: center; gap: 6px; line-height: 1; }
.clock-colon { color: var(--orange); opacity: .5; animation: blink 1s infinite; }
@keyframes blink { 0%, 100% { opacity: .5; } 50% { opacity: 1; } }
.clock-divider { width: 1.5px; height: 28px; background-color: var(--border); }
.clock-date { font-family: 'Barlow', sans-serif; font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }

.btn-add { display: inline-flex !important; align-items: center !important; gap: 8px !important; background-color: var(--text) !important; color: #fff !important; padding: 11px 22px !important; border-radius: 10px !important; font-size: 13px !important; font-weight: 800 !important; text-decoration: none !important; text-transform: uppercase !important; transition: all .2s ease !important; border: none !important; cursor: pointer !important; }
.btn-add:hover { background-color: var(--orange) !important; transform: translateY(-2px) !important; box-shadow: 0 8px 20px rgba(255,69,0,.3) !important; }
.btn-add i { font-size: 14px !important; }

/* DETAIL MODAL */
.detail-photo-card { text-align: center; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1.5px dashed var(--border); }
.detail-icon-wrap { width: 80px; height: 80px; background: var(--orange-lt); color: var(--orange); border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 16px; box-shadow: 0 8px 20px rgba(255,69,0,0.15); }
.detail-main-name { font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--text); text-transform: uppercase; }
.info-row { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid var(--border-lt); }
.info-row:last-child { border-bottom: none; }
.info-key { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.3px; }
.info-key i { color: var(--orange); font-size: 14px; width: 18px; text-align: center; }
.info-val { font-size: 14px; font-weight: 700; color: var(--text); }
.info-val.price { font-family: 'Barlow Condensed'; font-size: 18px; color: var(--orange); font-weight: 800; }
.info-val.discount { font-family: 'Barlow Condensed'; font-size: 18px; color: var(--green); font-weight: 800; }

/* FILTER DROPDOWN */
.filter-dropdown-wrap { position: relative; display: inline-block; }
.btn-filter { display: inline-flex; align-items: center; gap: 8px; background-color: var(--orange); color: #ffffff !important; padding: 11px 20px; border-radius: 10px; font-size: 13px; font-weight: 800; text-transform: uppercase; border: none; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(255,69,0,0.2); }
.btn-filter:hover { background-color: var(--orange-dk) !important; color: #ffffff !important; transform: translateY(-2px); box-shadow: 0 6px 16px rgba(255,69,0,0.35); }
.btn-filter i.arrow-icon { font-size: 10px; transition: transform 0.3s; }
.btn-filter.active i.arrow-icon { transform: rotate(180deg); }
.filter-card { position: absolute; top: calc(100% + 10px); right: 0; background: #ffffff; border-radius: 16px; border: 1px solid var(--border); padding: 24px; width: 300px; box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12); z-index: 50; display: none; }
.filter-card.open { display: block; animation: slideFilter 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
@keyframes slideFilter { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.filter-card h4 { font-size: 15px; font-weight: 800; color: var(--text); margin-bottom: 20px; text-align: left; }
.filter-group { margin-bottom: 16px; text-align: left; }
.filter-group label { display: block; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
.filter-input { width: 100%; padding: 10px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; outline: none; transition: all .2s; color: var(--text); cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 40px; }
.filter-input:focus { border-color: var(--orange); }
.filter-buttons { display: flex; gap: 10px; margin-top: 24px; }
.btn-filter-apply { flex: 1.2; background: var(--orange); color: white; border: none; padding: 12px; border-radius: 10px; font-weight: 800; font-size: 12px; text-transform: uppercase; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all .2s; }
.btn-filter-apply:hover { background: var(--orange-dk); }
.btn-filter-reset { flex: 1; background: var(--border-lt); color: var(--text-md); border: 1px solid var(--border); padding: 12px; border-radius: 10px; font-weight: 800; font-size: 12px; text-transform: uppercase; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; transition: all .2s; }
.btn-filter-reset:hover { background: #E5E7EB; }

.topbar-btn, .topbar-user {
    background-color: #FFFFFF !important;
}

/* 2. Menambahkan efek hover & active (klik) berwarna abu-abu */
.topbar-btn:hover, .topbar-user:hover {
    background-color: #E5E7EB !important; /* Latar belakang abu-abu saat di-hover */
    border-color: #D1D5DB !important;    /* Batas border abu-abu medium */
    color: #4B5563 !important;           /* Warna ikon/teks abu-abu gelap */
}

.topbar-btn:active, .topbar-user:active {
    background-color: #D1D5DB !important; /* Latar belakang abu-abu lebih gelap saat diklik */
    border-color: #9CA3AF !important;    /* Batas border saat diklik */
    color: #1F2937 !important;           /* Warna ikon/teks saat diklik */
}

/* Mendukung pembukaan menu dropdown via klik */
.dropdown-wrap.active .dropdown-menu { 
    display: block; 
}


/* RESPONSIVE */
@media(max-width: 1100px) { .page-header { flex-direction: column; align-items: flex-start; } }
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

<!-- MODAL FORM TIPE MEMBER -->
<div class="modal-overlay <?= ($edit_data || $show_add) ? 'open' : '' ?>" id="modalTipe">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header">
            <div class="modal-subtitle">Kelola Tipe Member</div>
            <div class="modal-title"><?= $edit_data ? 'Edit Tipe Member' : 'Tambah Tipe Member Baru' ?></div>
        </div>
        <div class="modal-body">
            <form method="POST" id="formTipe" onsubmit="return validateForm()" novalidate>
                <?php if ($edit_data): ?><input type="hidden" name="edit_mode" value="1"><?php endif; ?>

                <label class="modal-label">ID Tipe Member</label>
                <input type="text" name="id_tipe" id="id_tipe" class="modal-input" 
                    value="<?= htmlspecialchars($edit_data['ID_Tipe'] ?? $next_id_add) ?>" 
                    readonly placeholder="Contoh: TM000001">
                <div class="val-msg" id="val-id_tipe"><i class="fa-solid fa-circle-exclamation"></i> ID Tipe wajib diisi</div>

                <label class="modal-label">Nama Tipe <span class="required">*</span></label>
                <input type="text" name="nama_tipe" id="nama_tipe" class="modal-input" 
                    placeholder="Masukkan nama tipe (misal: Silver, Gold, Platinum)" autocomplete="off" 
                    value="<?= htmlspecialchars($edit_data['Nama_Tipe'] ?? '') ?>" 
                    required minlength="3" maxlength="10">
                <div class="val-msg" id="val-nama_tipe"></div>

                <div class="modal-grid-2">
                    <div>
                        <label class="modal-label">Harga Member (Rp) <span class="required">*</span></label>
                        <input type="number" name="harga_member" id="harga_member" class="modal-input" 
                            placeholder="100000" min="0" step="1000" autocomplete="off"
                            value="<?= (int)($edit_data['Harga_Member'] ?? 0) ?>" required>
                        <div class="val-msg" id="val-harga_member"></div>
                    </div>
                    <div>
                        <label class="modal-label">Potongan Harga (Rp) <span class="required">*</span></label>
                        <input type="number" name="potongan_harga" id="potongan_harga" class="modal-input" 
                            placeholder="10000" min="0" step="1000" autocomplete="off"
                            value="<?= (int)($edit_data['Potongan_Harga'] ?? 0) ?>" required>
                        <div class="val-msg" id="val-potongan_harga"></div>
                    </div>
                </div>

                <button type="submit" name="save_tipe" class="btn-submit">
                    <i class="fa-solid fa-<?= $edit_data ? 'floppy-disk' : 'plus' ?>"></i>
                    <?= $edit_data ? 'Simpan Perubahan' : 'Tambah Tipe Member' ?>
                </button>
                <a onclick="closeModal()" class="btn-cancel">Batal</a>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DETAIL TIPE MEMBER -->
<div class="modal-overlay <?= $show_detail ? 'open' : '' ?>" id="modalDetail">
    <div class="modal-box" style="width: 440px;">
        <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <div class="modal-subtitle">Informasi Tipe Member</div>
            <div class="modal-title">Profil Tipe Member</div>
        </div>
        <div class="modal-body" style="padding-top: 10px;">
            <?php if ($detail_data): 
                $is_active_detail = $detail_data['Status'] == 1;
            ?>
                <div class="detail-photo-card">
                    <div class="detail-icon-wrap"><i class="fa-solid fa-star"></i></div>
                    <div class="detail-main-name"><?= htmlspecialchars($detail_data['Nama_Tipe']) ?></div>
                </div>

                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-fingerprint"></i> ID Tipe</span>
                    <span class="info-val" style="color:var(--orange); font-weight:800; font-family:'Barlow Condensed'; font-size:16px;"><?= htmlspecialchars($detail_data['ID_Tipe']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-star"></i> Nama Tipe</span>
                    <span class="info-val" style="font-weight:700;"><?= htmlspecialchars($detail_data['Nama_Tipe']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-money-bill-wave"></i> Harga Member</span>
                    <span class="info-val price"><?= rupiah($detail_data['Harga_Member']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-tags"></i> Potongan Harga</span>
                    <span class="info-val discount"><?= rupiah($detail_data['Potongan_Harga']) ?></span>
                </div>
                <div class="info-row" style="border-bottom:none;">
                    <span class="info-key"><i class="fa-solid fa-circle-check"></i> Status</span>
                    <span class="info-val">
                        <span class="status-pill <?= $is_active_detail ? 'sp-active' : 'sp-inactive' ?>">
                            <span class="sp-dot"></span>
                            <?= $is_active_detail ? 'AKTIF' : 'NONAKTIF' ?>
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

<!-- SIDEBAR -->
<aside class="sidebar">
    <a href="../view_admin.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">Sistem Managemen</div>
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
        <a href="lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>
            Kelola Lapangan
        </a>
        <a href="fasilitas_lapangan.php" class="sb-link">
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
        <a href="tipe_member.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-card"></i></div>
            Kelola Tipe Member
        </a>
        <a href="alat.php" class="sb-link">
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
            <div><div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div><div class="sb-user-role"><?= strtoupper(htmlspecialchars($role)) ?></div></div>
            <a href="../logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- MAIN & TOPBAR -->
<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Kelola Tipe Member</div>
            <div class="topbar-breadcrumb">Operasional / Tipe Member</div>
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
                <div class="page-title">Kelola Tipe Member</div>
            </div>
            <div class="stat-chips">
                <div class="stat-chip chip-green"><i class="fa-solid fa-circle-check"></i> AKTIF <span class="chip-val"><?= $active_count ?></span></div>
                <div class="stat-chip chip-red"><i class="fa-solid fa-circle-xmark"></i> NONAKTIF <span class="chip-val"><?= $inactive_count ?></span></div>
                <div class="stat-chip chip-blue"><i class="fa-solid fa-list"></i> TOTAL <span class="chip-val"><?= $total_data ?></span></div>
            </div>
        </div>

        <!-- ACTION BAR -->
        <div class="action-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="src" placeholder="Cari tipe member..." onkeyup="searchTable()">
            </div>

            <div style="display: flex; gap: 12px; align-items: center;">
                <div class="filter-dropdown-wrap">
                    <button class="btn-filter" id="btnFilterToggle">
                        <i class="fa-solid fa-filter"></i> Filter <i class="fa-solid fa-chevron-down arrow-icon"></i>
                    </button>

                    <div class="filter-card" id="filterCard">
                        <h4>Filter Data</h4>
                        <form method="GET" action="tipe_member.php">
                            <div class="filter-group">
                                <label>Urut Berdasarkan</label>
                                <select name="f_sort" class="filter-input">
                                    <option value="id_asc" <?= ($_GET['f_sort'] ?? '') === 'id_asc' ? 'selected' : '' ?>>ID Tipe &uarr;</option>
                                    <option value="id_desc" <?= ($_GET['f_sort'] ?? '') === 'id_desc' ? 'selected' : '' ?>>ID Tipe &darr;</option>
                                    <option value="nama_asc" <?= ($_GET['f_sort'] ?? '') === 'nama_asc' ? 'selected' : '' ?>>Nama A - Z</option>
                                    <option value="harga_desc" <?= ($_GET['f_sort'] ?? '') === 'harga_desc' ? 'selected' : '' ?>>Harga Tertinggi</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label>Status Tipe Member</label>
                                <select name="f_status" class="filter-input">
                                    <option value="">Semua Status</option>
                                    <option value="1" <?= ($_GET['f_status'] ?? '') === '1' ? 'selected' : '' ?>>AKTIF</option>
                                    <option value="0" <?= ($_GET['f_status'] ?? '') === '0' ? 'selected' : '' ?>>NONAKTIF</option>
                                </select>
                            </div>

                            <div class="filter-buttons">
                                <button type="button" class="btn-filter-reset" onclick="resetFilter()"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                                <button type="submit" class="btn-filter-apply"><i class="fa-solid fa-check"></i> Terapkan</button>
                            </div>
                        </form>
                    </div>
                </div>
                <a href="tipe_member.php?add=1" class="btn-add"><i class="fa-solid fa-plus"></i> Tambah Tipe Member</a>
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
                                <th>Nama Tipe</th>
                                <th>Harga Member</th>
                                <th>Potongan Harga</th>
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
                            $is_active = $row['Status'] == 1;
                        ?>
                            <tr class="row-<?= $row_num % 2 == 1 ? 'odd' : 'even' ?>">
                                <td style="font-family:'Barlow'; font-weight:700;"><?= $no++ ?></td>
                                <td>
                                    <div class="tipe-name"><?= htmlspecialchars($row['Nama_Tipe']) ?></div>
                                </td>
                                <td>
                                    <div class="tipe-harga"><?= rupiah($row['Harga_Member']) ?></div>
                                </td>
                                <td>
                                    <div class="tipe-potongan"><?= rupiah($row['Potongan_Harga']) ?></div>
                                </td>
                                <td>
                                    <span class="status-pill <?= $is_active ? 'sp-active' : 'sp-inactive' ?>">
                                        <span class="sp-dot"></span>
                                        <?= $is_active ? 'AKTIF' : 'NONAKTIF' ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="actions">
                                        <a href="?detail_id=<?= $row['ID_Tipe'] ?>" class="btn-action btn-view" title="Lihat Detail">
                                            <i class="fa-solid fa-eye"></i>
                                        </a>
                                        <a href="?edit_id=<?= $row['ID_Tipe'] ?>" class="btn-action btn-edit" title="Edit Tipe Member">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <label class="toggle-switch" title="<?= $is_active ? 'Nonaktifkan' : 'Aktifkan' ?> tipe member">
                                            <input type="checkbox" <?= $is_active ? 'checked' : '' ?> onchange="confirmToggle('<?= $row['ID_Tipe'] ?>', <?= $row['Status'] ?>)">
                                            <span class="toggle-slider"></span>
                                        </label>
                                        <button onclick="confirmDelete('<?= $row['ID_Tipe'] ?>', '<?= htmlspecialchars($row['Nama_Tipe']) ?>')" class="btn-action btn-delete" title="Hapus Tipe Member">
                                            <i class="fa-solid fa-trash-can"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; endif; ?>

                        <?php if (!$has_data): ?>
                            <tr>
                                <td colspan="6">
                                    <div class="empty-state">
                                        <i class="fa-solid fa-star"></i>
                                        <div>Belum ada data tipe member</div>
                                        <div style="font-size: 12px; font-weight: 500; margin-top: 8px; opacity: .7;">Tambah tipe member baru untuk memulai</div>
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
                <strong><?= min($page * $limit, $total_data) ?></strong> dari 
                <strong><?= $total_data ?></strong> data
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
                Menampilkan <strong>1</strong> - <strong><?= $total_data ?></strong> dari <strong><?= $total_data ?></strong> data
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>

// Mengaktifkan interaksi klik/tekan pada dropdown profil user
document.addEventListener('DOMContentLoaded', function () {
    const userDropdown = document.querySelector('.dropdown-wrap');
    if (userDropdown) {
        userDropdown.addEventListener('click', function (e) {
            e.stopPropagation(); // Mencegah event menutup sendiri saat diklik
            this.classList.toggle('active');
        });
    }

    // Otomatis menutup menu dropdown jika mengklik area lain di luar menu
    document.addEventListener('click', function () {
        if (userDropdown) {
            userDropdown.classList.remove('active');
        }
    });
});

// ============================================
// CLOCK / JAM REAL-TIME
// ============================================
(function initClock() {
    function updateClock() {
        try {
            const now = new Date();
            const h = String(now.getHours()).padStart(2, '0');
            const m = String(now.getMinutes()).padStart(2, '0');
            const s = String(now.getSeconds()).padStart(2, '0');

            const hEl = document.getElementById('h');
            const mEl = document.getElementById('m');
            const sEl = document.getElementById('s');
            const dateEl = document.getElementById('full-date');

            if (hEl) hEl.textContent = h;
            if (mEl) mEl.textContent = m;
            if (sEl) sEl.textContent = s;

            const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
            const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

            if (dateEl) {
                dateEl.textContent = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
            }
        } catch (e) {
            console.error('Clock error:', e);
        }
    }

    // Run immediately and then every second
    updateClock();
    setInterval(updateClock, 1000);
})();


// ============================================
// MODAL FUNCTIONS
// ============================================
function closeModal() { 
    window.location.href = 'tipe_member.php'; 
}


// ============================================
// SEARCH TABLE
// ============================================
function searchTable() {
    var input = document.getElementById('src').value.toUpperCase();
    var rows = document.getElementById('tbl').getElementsByTagName('tr');
    for (var i = 1; i < rows.length; i++) {
        var tdName = rows[i].getElementsByTagName('td')[1];
        if (tdName) {
            var match = tdName.textContent.toUpperCase().indexOf(input) > -1;
            rows[i].style.display = match ? '' : 'none';
        }
    }
}


// ============================================
// VALIDASI FORM - REAL TIME & SUBMIT
// ============================================
// VALIDASI FIELD INDIVIDUAL
function validateField(fieldId, valId, rules) {
    const field = document.getElementById(fieldId);
    const valMsg = document.getElementById(valId);
    const value = field.value.trim();

    field.classList.remove('error');
    valMsg.classList.remove('show');

    // Cek Wajib Diisi (Required)
    if (rules.required && value === '') {
        field.classList.add('error');
        valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + rules.label + ' wajib diisi.';
        valMsg.classList.add('show');
        return false;
    }

    // Aturan Khusus Nama Tipe Member
    if (fieldId === 'nama_tipe') {
        if (value.length < 3) {
            field.classList.add('error');
            valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama tipe member minimal 3 karakter.';
            valMsg.classList.add('show');
            return false;
        }
        if (/^\d+$/.test(value)) {
            field.classList.add('error');
            valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama tipe member tidak boleh hanya angka.';
            valMsg.classList.add('show');
            return false;
        }
    }

    // Aturan Angka (Harga Member & Potongan Harga)
    if (rules.isNumeric) {
        if (isNaN(value) || value === '') {
            field.classList.add('error');
            valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + rules.label + ' harus berupa angka.';
            valMsg.classList.add('show');
            return false;
        }
        const numVal = parseFloat(value);
        
        if (rules.notZero && numVal === 0) {
            field.classList.add('error');
            valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + rules.label + ' tidak boleh 0.';
            valMsg.classList.add('show');
            return false;
        }
        
        if (rules.minVal !== undefined && numVal < rules.minVal) {
            field.classList.add('error');
            if (fieldId === 'harga_member' && rules.minVal === 80000) {
                valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Harga member minimal 80000.';
            } else if (fieldId === 'potongan_harga' && rules.minVal === 50000) {
                // Tambahkan baris pengecekan ini:
                valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Potongan harga minimal 50000.';
            } else {
                valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + rules.label + ' tidak boleh kurang dari ' + rules.minVal + '.';
            }
            valMsg.classList.add('show');
            return false;
        }

        // Aturan Khusus Potongan tidak boleh lebih besar dari harga member
        if (fieldId === 'potongan_harga') {
            const hargaValRaw = document.getElementById('harga_member').value.trim();
            const hargaVal = parseFloat(hargaValRaw);
            if (!isNaN(hargaVal) && numVal > hargaVal) {
                field.classList.add('error');
                valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Potongan harga tidak boleh lebih besar dari harga member.';
                valMsg.classList.add('show');
                return false;
            }
        }
    }

    return true;
}

// VALIDASI SAAT SUBMIT FORM
function validateForm() {
    let valid = true;

    if (!validateField('nama_tipe', 'val-nama_tipe', { required: true, label: 'Nama tipe member' })) valid = false;
    
    if (!validateField('harga_member', 'val-harga_member', { 
        required: true, 
        isNumeric: true, 
        notZero: true, 
        minVal: 80000, 
        label: 'Harga member' 
    })) valid = false;
    
    if (!validateField('potongan_harga', 'val-potongan_harga', { 
        required: true, 
        isNumeric: true, 
        minVal: 50000, // <-- Ubah dari 0 menjadi 50000
        label: 'Potongan harga' 
    })) valid = false;

    return valid;
}

// ============================================
// NOTIFIKASI TOAST
// ============================================
function showToast(type, title, message) {
    Swal.fire({
        icon: type,
        title: title,
        text: message,
        timer: 3000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end',
        timerProgressBar: true,
        showCloseButton: true,
        customClass: { popup: 'colored-toast' }
    });
}

function showSuccess(title, message) {
    showToast('success', title, message);
}

function showError(title, message) {
    showToast('error', title, message);
}

function showWarning(title, message) {
    showToast('warning', title, message);
}

function showInfo(title, message) {
    showToast('info', title, message);
}

function showLoading(title, message) {
    return Swal.fire({
        title: title,
        text: message,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
    });
}


// ============================================
// TOGGLE STATUS
// ============================================
function confirmToggle(id, status) {
    const action = status == 1 ? 'nonaktifkan' : 'aktifkan';
    const iconType = status == 1 ? 'warning' : 'question';

    Swal.fire({
        title: 'Konfirmasi Perubahan Status',
        text: 'Apakah Anda yakin ingin ' + action + ' tipe member ini?',
        icon: iconType,
        showCancelButton: true,
        confirmButtonColor: '#FF4500',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, ' + action + '!',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        allowOutsideClick: false
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Memproses...',
                text: 'Mengubah status tipe member',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            setTimeout(() => {
                window.location.href = '?toggle_id=' + id + '&s=' + status;
            }, 600);
        } else {
            // Kembalikan checkbox ke posisi semula jika user batal
            var checkboxes = document.querySelectorAll('input[type="checkbox"]');
            checkboxes.forEach(function(cb) {
                if (cb.getAttribute('onchange') && cb.getAttribute('onchange').indexOf(id) !== -1) {
                    cb.checked = !cb.checked;
                }
            });
        }
    });
}


// ============================================
// DELETE CONFIRMATION
// ============================================
function confirmDelete(id, name) {
    Swal.fire({
        title: 'Hapus Tipe Member?',
        html: 'Anda akan menghapus tipe member <strong style="color:var(--orange);">' + name + '</strong><br><span style="font-size:12px;color:var(--muted);">Data akan dihapus secara Permanen</span>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        allowOutsideClick: false
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                title: 'Memproses...',
                text: 'Menghapus tipe member',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            setTimeout(() => {
                window.location.href = '?delete_id=' + id;
            }, 600);
        }
    });
}


// ============================================
// FILTER DROPDOWN
// ============================================
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
    window.location.href = 'tipe_member.php';
}


// ============================================
// URL PARAMETER NOTIFICATION
// ============================================
document.addEventListener('DOMContentLoaded', function() {
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

    // Real-time validation
// Real-time validation
    const namaTipe = document.getElementById('nama_tipe');
    if (namaTipe) {
        namaTipe.addEventListener('blur', function() {
            validateField('nama_tipe', 'val-nama_tipe', { required: true, label: 'Nama tipe member' });
        });
        namaTipe.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                validateField('nama_tipe', 'val-nama_tipe', { required: true, label: 'Nama tipe member' });
            }
        });
    }

    const hargaMember = document.getElementById('harga_member');
    if (hargaMember) {
        hargaMember.addEventListener('blur', function() {
            validateField('harga_member', 'val-harga_member', { 
                required: true, 
                isNumeric: true, 
                notZero: true, 
                minVal: 80000, 
                label: 'Harga member' 
            });
        });
        hargaMember.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                validateField('harga_member', 'val-harga_member', { 
                    required: true, 
                    isNumeric: true, 
                    notZero: true, 
                    minVal: 80000, 
                    label: 'Harga member' 
                });
            }
        });
    }

    const potonganHarga = document.getElementById('potongan_harga');
    if (potonganHarga) {
        potonganHarga.addEventListener('blur', function() {
            validateField('potongan_harga', 'val-potongan_harga', { 
                required: true, 
                isNumeric: true, 
                minVal: 50000, // <-- Ubah dari 0 menjadi 50000
                label: 'Potongan harga' 
            });
        });
        potonganHarga.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                validateField('potongan_harga', 'val-potongan_harga', { 
                    required: true, 
                    isNumeric: true, 
                    minVal: 50000, // <-- Ubah dari 0 menjadi 50000
                    label: 'Potongan harga' 
                });
            }
        });
    }
    });
</script>
</body>
</html>