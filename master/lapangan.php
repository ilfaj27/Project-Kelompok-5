<?php
ob_start();
session_start();
date_default_timezone_set("Asia/Jakarta");
include '../includes/config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
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
        error_log("[LAPANGAN-ERROR] SQL Error: " . print_r($errors, true));
        error_log("[LAPANGAN-ERROR] Query: " . $sql);
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

function getPhotoUrl($photo_path) {
    if (empty($photo_path)) return '';
    $path = str_replace('../', '', $photo_path);
    $path = ltrim($path, '/');
    return '../' . $path;
}

function getUploadDirectory() {
    $upload_dir = '../asset/image/';
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    return $upload_dir;
}

function processPhotoUpload($file, $edit_data = null) {
    $upload_dir = getUploadDirectory();
    if (!isset($file) || empty($file['name'])) {
        return ($edit_data && !empty($edit_data['Photo_Lapangan'])) ? $edit_data['Photo_Lapangan'] : '';
    }
    if (!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0755, true);
    }
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
    if (!in_array($file_ext, $allowed_ext)) {
        return ($edit_data ? $edit_data['Photo_Lapangan'] : '');
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        return ($edit_data ? $edit_data['Photo_Lapangan'] : '');
    }
    $new_file_name = 'lapangan_' . time() . '_' . uniqid() . '.' . $file_ext;
    $target_path = $upload_dir . $new_file_name;
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return 'asset/image/' . $new_file_name;
    }
    return ($edit_data && !empty($edit_data['Photo_Lapangan'])) ? $edit_data['Photo_Lapangan'] : '';
}

// PROSES SIMPAN (TAMBAH/EDIT)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_lapangan'])) {
    $id = isset($_POST['id_lap']) ? intval($_POST['id_lap']) : 0;
    $nama_lapangan = trim($_POST['nama_lapangan'] ?? '');
    $harga_raw = preg_replace('/[^0-9]/', '', trim($_POST['harga_sewa'] ?? ''));
    $edit_mode = isset($_POST['edit_mode']) && $_POST['edit_mode'] == '1';
    $edit_photo_path = isset($_POST['edit_photo_path']) ? trim($_POST['edit_photo_path']) : '';

    $errors = [];
    if ($nama_lapangan === '') {
        $errors[] = 'Nama lapangan wajib diisi.';
    } elseif (strlen($nama_lapangan) < 3) {
        $errors[] = 'Nama lapangan minimal 3 karakter.';
    } elseif (strlen($nama_lapangan) > 50) {
        $errors[] = 'Nama lapangan maksimal 50 karakter.';
    } elseif (!preg_match('/^[a-zA-Z\s]+$/', $nama_lapangan)) {
        $errors[] = 'Nama lapangan hanya boleh huruf dan spasi.';
    }
    if ($harga_raw === '' || !is_numeric($harga_raw)) {
        $errors[] = 'Harga sewa harus berupa angka.';
    } else {
        $harga_num = intval($harga_raw);
        if ($harga_num < 10000) {
            $errors[] = 'Harga sewa minimal Rp 10.000.';
        } elseif ($harga_num > 10000000) {
            $errors[] = 'Harga sewa maksimal Rp 10.000.000.';
        }
    }

    if (empty($errors)) {
        $sql_check = "SELECT ID_Lapangan FROM Lapangan WHERE LOWER(Nama_Lapangan) = LOWER(?) AND ID_Lapangan <> ? AND Is_Deleted = 0";
        $q_check = safeQuery($conn, $sql_check, [$nama_lapangan, $id]);
        if ($q_check && safeFetch($q_check)) {
            $errors[] = 'Nama lapangan sudah terdaftar.';
        }
    }

    if (empty($errors)) {
        $harga_sewa = number_format(floatval($harga_raw), 2, '.', '');
        $edit_data_for_photo = ($edit_mode && !empty($edit_photo_path)) ? ['Photo_Lapangan' => $edit_photo_path] : null;
        $photo_lapangan = processPhotoUpload($_FILES['photo_lapangan'] ?? null, $edit_data_for_photo);

        if ($edit_mode && $id > 0) {
            $sql = "UPDATE Lapangan SET Nama_Lapangan=?, Harga_Sewa=?, Photo_Lapangan=?, Modified_By=?, Modified_Date=GETDATE() WHERE ID_Lapangan=?";
            $params = [$nama_lapangan, $harga_sewa, $photo_lapangan, $nama, $id];
        } else {
            $sql = "INSERT INTO Lapangan (Nama_Lapangan, Harga_Sewa, Photo_Lapangan, Status, Is_Deleted, Created_By, Created_Date) VALUES (?, ?, ?, 1, 0, ?, GETDATE())";
            $params = [$nama_lapangan, $harga_sewa, $photo_lapangan, $nama];
        }
        $result = safeQuery($conn, $sql, $params);
        if ($result !== false) {
            ob_end_clean();
            $msg = $edit_mode ? 'Data lapangan berhasil diperbarui!' : 'Lapangan baru berhasil ditambahkan!';
            header("Location: lapangan.php?status=success&msg=" . urlencode($msg));
            exit();
        } else {
            $db_error = getLastSqlError($conn);
            header("Location: lapangan.php?status=error&msg=" . urlencode("Gagal menyimpan data: " . $db_error));
            exit();
        }
    } else {
        header("Location: lapangan.php?status=error&msg=" . urlencode(implode(' | ', $errors)));
        exit();
    }
}

// TOGGLE STATUS
if (isset($_GET['toggle_id'])) {
    $toggle_id = intval($_GET['toggle_id']);
    $current_status = intval($_GET['s']);
    $s_baru = ($current_status == 1) ? 0 : 1;
    $result = safeQuery($conn, "UPDATE Lapangan SET Status=?, Modified_By=?, Modified_Date=GETDATE() WHERE ID_Lapangan=?", [$s_baru, $nama, $toggle_id]);
    if ($result !== false) {
        ob_end_clean();
        $msg = ($s_baru == 1) ? 'Lapangan berhasil diaktifkan!' : 'Lapangan berhasil dinonaktifkan!';
        header("Location: lapangan.php?status=success&msg=" . urlencode($msg));
    } else {
        ob_end_clean();
        header("Location: lapangan.php?status=error&msg=" . urlencode('Gagal mengubah status lapangan: ' . getLastSqlError($conn)));
    }
    exit();
}

// DELETE (SOFT DELETE)
if (isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt_nama = safeQuery($conn, "SELECT Nama_Lapangan FROM Lapangan WHERE ID_Lapangan = ?", [$delete_id]);
    $nama_lap_deleted = '';
    if ($stmt_nama) {
        $row_nama = safeFetch($stmt_nama);
        if ($row_nama) $nama_lap_deleted = $row_nama['Nama_Lapangan'];
    }
    $result = safeQuery($conn, "UPDATE Lapangan SET Is_Deleted=1, Deleted_By=?, Deleted_Date=GETDATE() WHERE ID_Lapangan=?", [$nama, $delete_id]);
    if ($result !== false) {
        ob_end_clean();
        $msg = !empty($nama_lap_deleted) ? 'Lapangan "' . $nama_lap_deleted . '" berhasil dihapus!' : 'Lapangan berhasil dihapus!';
        header("Location: lapangan.php?status=success&msg=" . urlencode($msg));
    } else {
        ob_end_clean();
        header("Location: lapangan.php?status=error&msg=" . urlencode('Gagal menghapus lapangan: ' . getLastSqlError($conn)));
    }
    exit();
}

// AMBIL DATA EDIT
$edit_data = null;
if (isset($_GET['edit_id'])) {
    $r = safeQuery($conn, "SELECT * FROM Lapangan WHERE ID_Lapangan=? AND Is_Deleted=0", [intval($_GET['edit_id'])]);
    if ($r) $edit_data = safeFetch($r);
}

// AMBIL DATA DETAIL
$detail_data = null;
$show_detail = false;
if (isset($_GET['detail_id'])) {
    $r = safeQuery($conn, "SELECT * FROM Lapangan WHERE ID_Lapangan=? AND Is_Deleted=0", [intval($_GET['detail_id'])]);
    if ($r) {
        $detail_data = safeFetch($r);
        $show_detail = ($detail_data !== false && $detail_data !== null);
    }
}

$show_add = isset($_GET['add']) && $_GET['add'] == '1';

// FILTER & SORTING
$where_clauses = ["Is_Deleted = 0"];
$params = [];
if (isset($_GET['f_status']) && $_GET['f_status'] !== '') {
    $where_clauses[] = "Status = ?";
    $params[] = intval($_GET['f_status']);
}
$where_sql = implode(" AND ", $where_clauses);

$sort_by = "ID_Lapangan ASC";
if (isset($_GET['f_sort'])) {
    switch ($_GET['f_sort']) {
        case 'nama_asc': $sort_by = "Nama_Lapangan ASC"; break;
        case 'harga_desc': $sort_by = "Harga_Sewa DESC"; break;
        case 'harga_asc': $sort_by = "Harga_Sewa ASC"; break;
    }
}

// STATISTIK
$q_ready = safeQuery($conn, "SELECT COUNT(*) as t FROM Lapangan WHERE Status=1 AND Is_Deleted=0", []);
$cnt_ready = 0;
if ($q_ready) { $row = safeFetch($q_ready); $cnt_ready = $row['t'] ?? 0; }

$q_maint = safeQuery($conn, "SELECT COUNT(*) as t FROM Lapangan WHERE Status=0 AND Is_Deleted=0", []);
$cnt_maint = 0;
if ($q_maint) { $row = safeFetch($q_maint); $cnt_maint = $row['t'] ?? 0; }

$q_total = safeQuery($conn, "SELECT COUNT(*) as t FROM Lapangan WHERE $where_sql", $params);
$total_lapangan = 0;
if ($q_total) { $row = safeFetch($q_total); $total_lapangan = $row['t'] ?? 0; }

// PAGING
$limit = 12;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$total_pages = max(1, ceil($total_lapangan / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

$query_sql = "SELECT * FROM Lapangan WHERE $where_sql ORDER BY $sort_by OFFSET " . intval($offset) . " ROWS FETCH NEXT " . intval($limit) . " ROWS ONLY";
$query = safeQuery($conn, $query_sql, $params);

$filter_url = "";
if (isset($_GET['f_sort'])) $filter_url .= "&f_sort=" . urlencode($_GET['f_sort']);
if (isset($_GET['f_status'])) $filter_url .= "&f_status=" . urlencode($_GET['f_status']);

$q_pending = safeQuery($conn, "SELECT COUNT(*) as t FROM Booking WHERE Status=0", []);
$total_pending = 0;
if ($q_pending) { $row = safeFetch($q_pending); $total_pending = $row['t'] ?? 0; }

function rupiah($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }
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
    --green: #10B981; --green-lt: rgba(16,185,129,.10);
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10);
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
.notif-dot { position: absolute; top: 7px; right: 7px; width: 7px; height: 7px; background: var(--orange); border-radius: 50%; border: 2px solid #fff; }
.dropdown-wrap { position: relative; }
.topbar-user { display: flex; align-items: center; gap: 10px; background: #fff; border: 1px solid var(--border); padding: 6px 14px 6px 8px; border-radius: 12px; cursor: pointer; transition: .2s; }
.topbar-user:hover { border-color: var(--orange); }
.t-avatar { width: 32px; height: 32px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; }
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
.chip-green { background: var(--green-lt); color: var(--green); }
.chip-red { background: var(--red-lt); color: var(--red); }
.chip-blue { background: var(--blue-lt); color: var(--blue); }
.chip-val { font-family: 'Barlow Condensed'; font-size: 20px; font-weight: 900; }

.action-bar { display: flex; align-items: center; justify-content: space-between; margin-bottom: 20px; flex-wrap: wrap; gap: 12px; }
.search-box { position: relative; width: 300px; }
.search-box i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 13px; }
.search-box input { width: 100%; padding: 10px 14px 10px 40px; background: var(--card-bg); border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; outline: none; transition: all .2s; color: var(--text); }
.search-box input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.search-box input::placeholder { color: #9CA3AF; }

.lapangan-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 16px; margin-bottom: 24px; }
.lapangan-card { background: var(--card-bg); border-radius: 12px; border: 1px solid var(--border); overflow: hidden; transition: all .25s ease; cursor: pointer; position: relative; }
.lapangan-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,.12); border-color: var(--orange); }
.lapangan-card-photo-wrap { position: relative; width: 100%; aspect-ratio: 16 / 10; background: var(--border-lt); overflow: hidden; }
.lapangan-card-photo-wrap img { width: 100%; height: 100%; object-fit: cover; transition: transform .3s ease; display: block; }
.lapangan-card:hover .lapangan-card-photo-wrap img { transform: scale(1.05); }
.lapangan-card-photo-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); position: absolute; top: 0; left: 0; }
.lapangan-card-photo-placeholder i { font-size: 48px; color: var(--orange); opacity: .5; }
.lapangan-card-badge { position: absolute; top: 8px; left: 8px; padding: 4px 10px; border-radius: 6px; font-size: 10px; font-weight: 800; text-transform: uppercase; letter-spacing: .5px; z-index: 2; }
.badge-aktif { background: var(--green); color: #fff; }
.badge-nonaktif { background: var(--red); color: #fff; }
.lapangan-card-actions { position: absolute; top: 8px; right: 8px; display: flex; gap: 6px; opacity: 0; transition: opacity .2s ease; z-index: 3; }
.lapangan-card:hover .lapangan-card-actions { opacity: 1; }
.lapangan-card-action-btn { width: 32px; height: 32px; border-radius: 8px; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 13px; transition: all .2s ease; backdrop-filter: blur(4px); text-decoration: none; }
.ac-btn-view { background: rgba(59,130,246,.9); color: #fff; }
.ac-btn-view:hover { background: #2563EB; }
.ac-btn-edit { background: rgba(16,185,129,.9); color: #fff; }
.ac-btn-edit:hover { background: #059669; }
.ac-btn-delete { background: rgba(239,68,68,.9); color: #fff; }
.ac-btn-delete:hover { background: #DC2626; }
.lapangan-card-info { padding: 12px; }
.lapangan-card-name { font-size: 15px; font-weight: 700; color: var(--text); line-height: 1.3; margin-bottom: 6px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; min-height: 20px; }
.lapangan-card-price { font-family: 'Barlow Condensed', sans-serif; font-size: 20px; font-weight: 900; color: var(--shopee-orange); margin-bottom: 8px; }
.lapangan-card-meta { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.lapangan-card-harga { font-size: 12px; color: var(--muted); font-weight: 600; }
.lapangan-card-harga i { color: var(--orange); margin-right: 4px; }
.lapangan-card-toggle { display: flex; align-items: center; gap: 6px; }
.lapangan-card-toggle-label { font-size: 10px; font-weight: 700; color: var(--muted); text-transform: uppercase; }
.toggle-switch-mini { position: relative; display: inline-flex; align-items: center; width: 36px; height: 20px; cursor: pointer; }
.toggle-switch-mini input { opacity: 0; width: 0; height: 0; }
.toggle-slider-mini { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--red); transition: .3s; border-radius: 20px; }
.toggle-slider-mini::before { position: absolute; content: ""; height: 14px; width: 14px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 1px 3px rgba(0,0,0,.2); }
.toggle-switch-mini input:checked + .toggle-slider-mini { background-color: var(--green); }
.toggle-switch-mini input:checked + .toggle-slider-mini::before { transform: translateX(16px); }
.empty-grid { grid-column: 1 / -1; text-align: center; padding: 80px 20px; color: var(--muted); }
.empty-grid i { font-size: 64px; margin-bottom: 20px; opacity: .3; display: block; }
.empty-grid div { font-size: 16px; font-weight: 700; }
.empty-grid p { font-size: 13px; font-weight: 500; margin-top: 8px; opacity: .7; }

.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(6px); display: none; align-items: center; justify-content: center; z-index: 2000; }
.modal-overlay.open { display: flex; }
.modal-box { background: #fff; border-radius: 20px; width: 520px; max-height: 90vh; overflow-y: auto; box-shadow: 0 25px 60px rgba(0,0,0,.2); position: relative; }
.modal-header { padding: 28px 32px 20px; border-bottom: 1px solid var(--border); }
.modal-subtitle { font-size: 10px; font-weight: 800; color: var(--orange); text-transform: uppercase; margin-bottom: 6px; letter-spacing: .8px; }
.modal-title { font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 900; color: var(--text); }
.modal-body { padding: 24px 32px 32px; }
.modal-label { display: block; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 8px; }
.modal-label .required { color: var(--red); margin-left: 2px; font-size: 14px; font-weight: 900; }
.modal-input { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; margin-bottom: 4px; outline: none; transition: all .2s; color: var(--text); }
.modal-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.modal-input::placeholder { color: #9CA3AF; }
.modal-input.error { border-color: var(--red); box-shadow: 0 0 0 3px var(--red-lt); }
.btn-submit { width: 100%; background: var(--orange); color: #fff; border: none; padding: 14px; border-radius: 10px; font-weight: 800; font-size: 13px; cursor: pointer; transition: all .2s; text-transform: uppercase; letter-spacing: .5px; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-submit:hover { background: var(--orange-dk); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(255,69,0,.3); }
.btn-cancel { display: block; text-align: center; margin-top: 16px; color: var(--muted); text-decoration: none; font-size: 13px; font-weight: 700; transition: .2s; cursor: pointer; background: none; border: none; width: 100%; }
.btn-cancel:hover { color: var(--orange); }
.modal-close { position: absolute; top: 20px; right: 20px; width: 36px; height: 36px; border: none; background: var(--border-lt); border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 16px; transition: all .2s; z-index: 10; }
.modal-close:hover { background: var(--red-lt); color: var(--red); }

.photo-upload-area { width: 100%; height: 180px; border: 2px dashed var(--border); border-radius: 12px; display: flex; flex-direction: column; align-items: center; justify-content: center; cursor: pointer; transition: all .2s ease; margin-bottom: 16px; position: relative; overflow: hidden; background: var(--border-lt); }
.photo-upload-area:hover { border-color: var(--orange); background: var(--orange-lt); }
.photo-upload-area.has-image { border-style: solid; border-color: var(--orange); }
.photo-upload-area i.upload-icon { font-size: 32px; color: var(--orange); margin-bottom: 8px; }
.photo-upload-area p { font-size: 13px; font-weight: 600; color: var(--muted); text-align: center; }
.photo-upload-area input[type="file"] { position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; z-index: 5; }
.photo-upload-preview { width: 100%; height: 100%; object-fit: cover; position: absolute; top: 0; left: 0; z-index: 2; }
.photo-upload-remove { position: absolute; top: 8px; right: 8px; width: 28px; height: 28px; background: rgba(239,68,68,.9); color: #fff; border: none; border-radius: 6px; cursor: pointer; font-size: 12px; display: flex; align-items: center; justify-content: center; z-index: 10; }
.upload-placeholder-inner { display: flex; flex-direction: column; align-items: center; justify-content: center; pointer-events: none; z-index: 1; }

.val-msg { font-size: 11px; color: var(--red); font-weight: 600; margin-bottom: 10px; display: none; min-height: 16px; }
.val-msg.show { display: block; }
.val-msg i { margin-right: 4px; }

/* ========== DETAIL MODAL STYLE SAMA DENGAN ALAT.PHP ========== */
.detail-modal-box { width: 460px; border-radius: 24px; border: 1px solid var(--border); overflow-y: auto; }
.detail-photo-wrap { width: 100%; aspect-ratio: 1.5 / 1; background: #ffffff; border-radius: 16px; overflow: hidden; margin-bottom: 16px; position: relative; border: 1.5px solid var(--border); box-shadow: inset 0 0 20px rgba(0,0,0,.02); }
.detail-photo-wrap img { width: 100%; height: 100%; object-fit: contain; background: #ffffff; display: block; }
.detail-photo-placeholder { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%); }
.detail-photo-placeholder i { font-size: 56px; color: var(--orange); opacity: .6; }
.detail-name { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); line-height: 1.2; margin-bottom: 6px; text-transform: uppercase; }
.detail-price { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--shopee-orange); margin-bottom: 20px; border-bottom: 1px solid var(--border-lt); padding-bottom: 14px; }
.detail-info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 20px; }
.detail-info-item { background: #FAFBFD; border: 1px solid var(--border-lt); border-radius: 14px; padding: 14px; transition: all .2s ease; }
.detail-info-label { font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
.detail-info-value { font-size: 18px; font-weight: 800; color: var(--text); }
.detail-status-wrap { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px; border-radius: 10px; margin-bottom: 20px; }
.detail-status-aktif { background: var(--green-lt); color: var(--green); }
.detail-status-nonaktif { background: var(--red-lt); color: var(--red); }
.detail-status-text { font-size: 14px; font-weight: 800; text-transform: uppercase; }

.detail-status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 30px; font-size: 11px; font-weight: 800; letter-spacing: .5px; margin-bottom: 14px; text-transform: uppercase; }
.badge-status-aktif { background: var(--green-lt); color: var(--green); border: 1px solid rgba(16,185,129,.2); }
.badge-status-nonaktif { background: var(--red-lt); color: var(--red); border: 1px solid rgba(239,68,68,.2); }
.detail-status-badge i { font-size: 8px; }

.detail-info-item:hover { background: #ffffff; border-color: var(--orange); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,.02); }
.detail-info-label i { color: var(--orange); font-size: 12px; }

.pagination-wrap { background: var(--card-bg); border: 1px solid var(--border); border-radius: 12px; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; }
.pagination-info { font-size: 12px; color: var(--muted); font-weight: 600; }
.pagination-info strong { color: var(--text); font-weight: 800; }
.pagination-nav { display: flex; align-items: center; gap: 4px; }
.page-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 10px; border-radius: 10px; font-size: 13px; font-weight: 700; font-family: 'Barlow', sans-serif; text-decoration: none; cursor: pointer; transition: all .2s ease; border: 1.5px solid var(--border); color: var(--text-md); background: #fff; }
.page-btn:hover:not(.disabled):not(.active) { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); }
.page-btn.active { background: var(--orange); color: #fff; border-color: var(--orange); box-shadow: 0 4px 12px rgba(255,69,0,.3); font-weight: 800; }
.page-btn.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }

.filter-dropdown-wrap { position: relative; display: inline-block; }
.btn-filter { display: inline-flex; align-items: center; gap: 8px; background-color: var(--orange); color: #ffffff; padding: 11px 20px; border-radius: 10px; font-size: 13px; font-weight: 800; text-transform: uppercase; border: none; cursor: pointer; transition: all 0.2s; }
.btn-filter:hover { background-color: var(--orange-dk); transform: translateY(-2px); }
.filter-card { position: absolute; top: calc(100% + 10px); right: 0; background: #ffffff; border-radius: 16px; border: 1px solid var(--border); padding: 24px; width: 300px; box-shadow: 0 15px 35px rgba(0,0,0,.12); z-index: 50; display: none; }
.filter-card.open { display: block; }
.filter-card h4 { font-size: 15px; font-weight: 800; color: var(--text); margin-bottom: 20px; }
.filter-group { margin-bottom: 16px; }
.filter-group label { display: block; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
.filter-input { width: 100%; padding: 10px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; outline: none; color: var(--text); cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 40px; }
.filter-input:focus { border-color: var(--orange); }
.filter-buttons { display: flex; gap: 10px; margin-top: 24px; }
.btn-filter-apply { flex: 1.2; background: var(--orange); color: white; border: none; padding: 12px; border-radius: 10px; font-weight: 800; font-size: 12px; text-transform: uppercase; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; }
.btn-filter-apply:hover { background: var(--orange-dk); }
.btn-filter-reset { flex: 1; background: var(--border-lt); color: var(--text-md); border: 1px solid var(--border); padding: 12px; border-radius: 10px; font-weight: 800; font-size: 12px; text-transform: uppercase; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 6px; }
.btn-filter-reset:hover { background: #E5E7EB; }
.btn-add { display: inline-flex; align-items: center; gap: 8px; background-color: var(--text); color: #fff; padding: 11px 22px; border-radius: 10px; font-size: 13px; font-weight: 800; text-decoration: none; text-transform: uppercase; transition: all .2s ease; border: none; cursor: pointer; }
.btn-add:hover { background-color: var(--orange); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255,69,0,.3); }

#clock-display { display: flex; align-items: center; gap: 16px; }
.clock-time { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--orange); display: flex; align-items: center; gap: 6px; line-height: 1; }
.clock-colon { color: var(--orange); opacity: .5; animation: blink 1s infinite; }
@keyframes blink { 0%, 100% { opacity: .5; } 50% { opacity: 1; } }
.clock-divider { width: 1.5px; height: 28px; background-color: var(--border); }
.clock-date { font-family: 'Barlow', sans-serif; font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }

html, body { scrollbar-width: none; -ms-overflow-style: none; }
html::-webkit-scrollbar, body::-webkit-scrollbar { display: none; }
body.swal2-shown, html.swal2-shown { padding-right: 0px !important; }

.modal-box {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.modal-box::-webkit-scrollbar {
    display: none;
}

@media(max-width:768px){
    .sidebar{width:0;overflow:hidden;padding:0;}
    .main{margin-left:0;}
    .content{padding:20px;}
    .lapangan-grid{grid-template-columns:repeat(2,1fr);gap:12px;}
    .modal-box{width:90%;margin:20px;}
    .search-box{width:100%;}
    .action-bar{flex-direction:column;align-items:stretch;}
}
@media(max-width:480px){.lapangan-grid{grid-template-columns:1fr;}}
</style>
</head>
<body>
<!-- MODAL FORM TAMBAH/EDIT LAPANGAN -->
<div class="modal-overlay <?= ($edit_data || $show_add) ? 'open' : '' ?>" id="modalLapangan">
    <div class="modal-box">
        <button type="button" class="modal-close" onclick="closeModal()" title="Tutup"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header">
            <div class="modal-subtitle">Kelola Lapangan</div>
            <div class="modal-title"><?= $edit_data ? 'Edit Lapangan' : 'Tambah Lapangan Baru' ?></div>
        </div>
        <div class="modal-body">
            <form method="POST" id="formLapangan" enctype="multipart/form-data" action="lapangan.php" onsubmit="return validateForm()">
                <input type="hidden" name="save_lapangan" value="1">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="edit_mode" value="1">
                    <input type="hidden" name="id_lap" value="<?= intval($edit_data['ID_Lapangan']) ?>">
                    <input type="hidden" name="edit_photo_path" value="<?= htmlspecialchars($edit_data['Photo_Lapangan'] ?? '') ?>">
                <?php endif; ?>

                <label class="modal-label">Foto Lapangan <span style="color:var(--muted);font-size:10px;">(Opsional, max 5MB)</span></label>
                <div class="photo-upload-area <?= ($edit_data && !empty($edit_data['Photo_Lapangan'])) ? 'has-image' : '' ?>" id="uploadArea">
                    <input type="file" name="photo_lapangan" id="photo_lapangan" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" onchange="handlePhotoUpload(this)" style="position:absolute;inset:0;opacity:0;cursor:pointer;z-index:5;">

                    <?php if ($edit_data && !empty($edit_data['Photo_Lapangan'])): ?>
                        <img src="<?= htmlspecialchars(getPhotoUrl($edit_data['Photo_Lapangan'])) ?>"
                             class="photo-upload-preview" id="previewImg" alt="Foto Lapangan"
                             onerror="this.style.display='none'; document.getElementById('uploadPlaceholder').style.display='flex';">
                        <button type="button" class="photo-upload-remove" id="removeBtn"
                                onclick="event.stopPropagation(); removePhoto();" title="Hapus Foto">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    <?php else: ?>
                        <img class="photo-upload-preview" id="previewImg" style="display:none;" alt="Preview">
                        <button type="button" class="photo-upload-remove" id="removeBtn"
                                onclick="event.stopPropagation(); removePhoto();" style="display:none;" title="Hapus Foto">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    <?php endif; ?>

                    <div class="upload-placeholder-inner" id="uploadPlaceholder"
                         style="display:<?= ($edit_data && !empty($edit_data['Photo_Lapangan'])) ? 'none' : 'flex' ?>; flex-direction:column; align-items:center;">
                        <i class="fa-solid fa-cloud-arrow-up upload-icon"></i>
                        <p>Klik untuk upload foto lapangan</p>
                        <p style="font-size:11px; margin-top:4px; opacity:.7;">JPG, PNG, GIF, WEBP (Max 5MB)</p>
                    </div>
                </div>

                <label class="modal-label">Nama Lapangan <span class="required">*</span></label>
                <input type="text" name="nama_lapangan" id="nama_lapangan" class="modal-input"
                       value="<?= htmlspecialchars($edit_data['Nama_Lapangan'] ?? '') ?>"
                       placeholder="Contoh: Lapangan Indoor A" autocomplete="off" maxlength="50">
                <div class="val-msg" id="val-nama_lapangan"></div>

                <label class="modal-label">Harga Sewa per Jam (Rp) <span class="required">*</span></label>
                <input type="number" name="harga_sewa" id="harga_sewa" class="modal-input"
                       value="<?= isset($edit_data['Harga_Sewa']) ? intval($edit_data['Harga_Sewa']) : '' ?>"
                       placeholder="Contoh: 100000" min="10000" max="10000000" autocomplete="off">
                <div class="val-msg" id="val-harga_sewa"></div>

                <button type="submit" class="btn-submit" id="btnSubmit">
                    <i class="fa-solid fa-<?= $edit_data ? 'floppy-disk' : 'plus' ?>"></i>
                    <?= $edit_data ? 'Simpan Perubahan' : 'Tambah Lapangan' ?>
                </button>
                <button type="button" class="btn-cancel" onclick="closeModal()">Batal</button>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DETAIL LAPANGAN - DISAMAKAN DENGAN ALAT.PHP -->
<div class="modal-overlay <?= $show_detail ? 'open' : '' ?>" id="modalDetail">
    <div class="modal-box detail-modal-box">
        <button type="button" class="modal-close" onclick="closeModal()" title="Tutup"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header">
            <div class="modal-subtitle">Detail Informasi</div>
            <div class="modal-title">Spesifikasi Lapangan</div>
        </div>
        <div class="modal-body" style="padding-top:20px;">
            <?php if ($detail_data): ?>
                <div class="detail-photo-wrap">
                    <?php
                    $detail_photo_url = getPhotoUrl($detail_data['Photo_Lapangan'] ?? '');
                    if (!empty($detail_photo_url)):
                    ?>
                        <img src="<?= htmlspecialchars($detail_photo_url) ?>"
                             alt="<?= htmlspecialchars($detail_data['Nama_Lapangan']) ?>"
                             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="detail-photo-placeholder" style="display:none;"><i class="fa-solid fa-layer-group"></i></div>
                    <?php else: ?>
                        <div class="detail-photo-placeholder"><i class="fa-solid fa-layer-group"></i></div>
                    <?php endif; ?>
                </div>
                
                <!-- STATUS BADGE SAMA DENGAN ALAT.PHP -->
                <div class="detail-status-badge <?= $detail_data['Status'] == 1 ? 'badge-status-aktif' : 'badge-status-nonaktif' ?>">
                    <i class="fa-solid fa-circle"></i> <?= $detail_data['Status'] == 1 ? 'Lapangan Aktif' : 'Lapangan Maintenance' ?>
                </div>

                <div class="detail-name"><?= htmlspecialchars($detail_data['Nama_Lapangan']) ?></div>
                <div class="detail-price"><?= rupiah($detail_data['Harga_Sewa']) ?> <span style="font-size:14px;color:var(--muted);font-family:'Barlow';font-weight:600;">/ jam</span></div>
                
                <div class="detail-info-grid">
                    <div class="detail-info-item">
                        <div class="detail-info-label"><i class="fa-solid fa-money-bill-wave"></i> Harga Sewa</div>
                        <div class="detail-info-value" style="color:var(--shopee-orange);"><?= rupiah($detail_data['Harga_Sewa']) ?></div>
                    </div>
                    <div class="detail-info-item">
                        <div class="detail-info-label"><i class="fa-solid fa-tag"></i> Tarif per Jam</div>
                        <div class="detail-info-value"><?= rupiah($detail_data['Harga_Sewa']) ?> <span style="font-size:11px; font-weight:500; color:var(--muted);">/jam</span></div>
                    </div>
                </div>

                <button type="button" onclick="closeModal()" class="btn-submit" style="background:#0D1117;">
                    <i class="fa-solid fa-arrow-left"></i> Kembali
                </button>
            <?php endif; ?>
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
        <a href="../view_admin.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>Dashboard</a>
        <a href="customer.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>Kelola Customer</a>
        <a href="lapangan.php" class="sb-link active"><div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>Kelola Lapangan</a>
        <a href="fasilitas_lapangan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-list-check"></i></div>Kelola Fasilitas</a>
        <a href="jadwal.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-calendar-days"></i></div>Kelola Jadwal</a>
        <a href="promo.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div>Kelola Promo</a>
        <a href="tipe_member.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-id-card"></i></div>Kelola Tipe Member</a>
        <a href="alat.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div>Kelola Alat</a>
    </nav>
    <div class="sb-section-label">Transaksi</div>
    <nav>
        <a href="booking.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>Kelola Booking</a>
        <a href="langganan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-crown"></i></div>Kelola Langganan</a>
        <a href="pembelian.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-cart-shopping"></i></div>Kelola Pembelian Alat</a>
        <a href="pembatalan.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-ban"></i></div>Kelola Pembatalan</a>
    </nav>
    <div class="sb-section-label">Akun</div>
    <a href="../profile.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>Profil Saya</a>
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
            <a href="../logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- MAIN CONTENT -->
<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Kelola Lapangan</div>
            <div class="topbar-breadcrumb">Operasional / Kelola Lapangan</div>
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
            <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a href="#" class="topbar-btn">
                <i class="fa-solid fa-bell"></i>
                <?php if ($total_pending > 0): ?><span class="notif-dot"></span><?php endif; ?>
            </a>
            <div class="dropdown-wrap" id="userDropdown">
                <div class="topbar-user" onclick="toggleUserDropdown()">
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
        <div class="page-header">
            <div>
                <div class="page-title-tag"></div>
                <div class="page-title">Kelola Lapangan</div>
            </div>
            <div class="stat-chips">
                <div class="stat-chip chip-green"><i class="fa-solid fa-circle-check"></i> AKTIF <span class="chip-val"><?= $cnt_ready ?></span></div>
                <div class="stat-chip chip-red"><i class="fa-solid fa-circle-xmark"></i> MAINTENANCE <span class="chip-val"><?= $cnt_maint ?></span></div>
                <div class="stat-chip chip-blue"><i class="fa-solid fa-layer-group"></i> TOTAL <span class="chip-val"><?= $total_lapangan ?></span></div>
            </div>
        </div>

        <div class="action-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="src" placeholder="Cari lapangan..." onkeyup="searchGrid()">
            </div>
            <div style="display:flex;gap:12px;align-items:center;">
                <div class="filter-dropdown-wrap">
                    <button class="btn-filter" id="btnFilterToggle">
                        <i class="fa-solid fa-filter"></i> Filter <i class="fa-solid fa-chevron-down" style="font-size:10px;"></i>
                    </button>
                    <div class="filter-card" id="filterCard">
                        <h4><i class="fa-solid fa-sliders" style="margin-right:8px;color:var(--orange);"></i>Filter Data</h4>
                        <form method="GET" action="lapangan.php">
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="f_status" class="filter-input">
                                    <option value="">Semua Status</option>
                                    <option value="1" <?= ($_GET['f_status'] ?? '') === '1' ? 'selected' : '' ?>>AKTIF</option>
                                    <option value="0" <?= ($_GET['f_status'] ?? '') === '0' ? 'selected' : '' ?>>MAINTENANCE</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Urutkan</label>
                                <select name="f_sort" class="filter-input">
                                    <option value="nama_asc"  <?= ($_GET['f_sort'] ?? '') === 'nama_asc'  ? 'selected' : '' ?>>Nama A-Z</option>
                                    <option value="harga_desc"<?= ($_GET['f_sort'] ?? '') === 'harga_desc'? 'selected' : '' ?>>Harga Termahal</option>
                                    <option value="harga_asc" <?= ($_GET['f_sort'] ?? '') === 'harga_asc' ? 'selected' : '' ?>>Harga Termurah</option>
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

        <!-- GRID KARTU LAPANGAN -->
        <div class="lapangan-grid" id="lapanganGrid">
        <?php
        $has_data = false;
        if ($query):
            while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                $has_data = true;
                $photo_url = getPhotoUrl($row['Photo_Lapangan'] ?? '');
                $is_aktif = intval($row['Status']) === 1;
        ?>
            <div class="lapangan-card" data-name="<?= strtolower(htmlspecialchars($row['Nama_Lapangan'])) ?>">
                <div class="lapangan-card-photo-wrap" onclick="window.location.href='?detail_id=<?= intval($row['ID_Lapangan']) ?>'">
                    <div class="lapangan-card-photo-placeholder">
                        <i class="fa-solid fa-layer-group"></i>
                    </div>
                    <?php if (!empty($photo_url)): ?>
                        <img src="<?= htmlspecialchars($photo_url) ?>"
                             alt="<?= htmlspecialchars($row['Nama_Lapangan']) ?>"
                             loading="lazy"
                             style="position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;z-index:1;"
                             onerror="this.style.display='none';">
                    <?php endif; ?>
                    <span class="lapangan-card-badge <?= $is_aktif ? 'badge-aktif' : 'badge-nonaktif' ?>" style="z-index:2;">
                        <?= $is_aktif ? 'AKTIF' : 'MAINTENANCE' ?>
                    </span>
                    <div class="lapangan-card-actions" style="z-index:3;">
                        <a href="?detail_id=<?= intval($row['ID_Lapangan']) ?>"
                           class="lapangan-card-action-btn ac-btn-view" title="Lihat Detail"
                           onclick="event.stopPropagation()"><i class="fa-solid fa-eye"></i></a>
                        <a href="?edit_id=<?= intval($row['ID_Lapangan']) ?>"
                           class="lapangan-card-action-btn ac-btn-edit" title="Edit Lapangan"
                           onclick="event.stopPropagation()"><i class="fa-solid fa-pen-to-square"></i></a>
                        <button type="button"
                                onclick="event.stopPropagation(); doDelete(<?= intval($row['ID_Lapangan']) ?>, '<?= htmlspecialchars($row['Nama_Lapangan'], ENT_QUOTES) ?>')"
                                class="lapangan-card-action-btn ac-btn-delete" title="Hapus Lapangan">
                            <i class="fa-solid fa-trash-can"></i>
                        </button>
                    </div>
                </div>
                <div class="lapangan-card-info">
                    <div class="lapangan-card-name"><?= htmlspecialchars($row['Nama_Lapangan']) ?></div>
                    <div class="lapangan-card-price"><?= rupiah($row['Harga_Sewa']) ?> <span style="font-size:12px;color:var(--muted);font-weight:600;">/ jam</span></div>
                    <div class="lapangan-card-meta">
                        <span class="lapangan-card-harga">
                            <i class="fa-solid fa-money-bill-wave"></i><?= rupiah($row['Harga_Sewa']) ?>
                        </span>
                        <div class="lapangan-card-toggle">
                            <span class="lapangan-card-toggle-label"><?= $is_aktif ? 'ON' : 'OFF' ?></span>
                            <label class="toggle-switch-mini" title="<?= $is_aktif ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                <input type="checkbox" <?= $is_aktif ? 'checked' : '' ?>
                                       onchange="event.stopPropagation(); doToggle(<?= intval($row['ID_Lapangan']) ?>, <?= intval($row['Status']) ?>, '<?= htmlspecialchars($row['Nama_Lapangan'], ENT_QUOTES) ?>')">
                                <span class="toggle-slider-mini"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        <?php
            endwhile;
        endif;
        if (!$has_data): ?>
            <div class="empty-grid">
                <i class="fa-solid fa-layer-group"></i>
                <div>Belum ada data lapangan</div>
                <p>Klik "+ Tambah Lapangan" untuk menambahkan lapangan baru</p>
            </div>
        <?php endif; ?>
        </div>

        <!-- PAGINATION -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrap">
            <div class="pagination-info">
                Menampilkan <strong><?= (($page-1)*$limit)+1 ?></strong> - <strong><?= min($page*$limit,$total_lapangan) ?></strong>
                dari <strong><?= $total_lapangan ?></strong> data
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
                Menampilkan <strong>1</strong> - <strong><?= $total_lapangan ?></strong>
                dari <strong><?= $total_lapangan ?></strong> data
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
    window.location.href = 'lapangan.php';
}

function handlePhotoUpload(input) {
    if (!input.files || !input.files[0]) return;
    var file = input.files[0];
    if (file.size > 5 * 1024 * 1024) {
        Swal.fire({
            icon: 'error',
            title: 'File Terlalu Besar',
            text: 'Ukuran file maksimal 5MB!',
            confirmButtonColor: '#FF4500'
        });
        input.value = '';
        return;
    }
    var reader = new FileReader();
    reader.onload = function(e) {
        var previewImg = document.getElementById('previewImg');
        var uploadPlaceholder = document.getElementById('uploadPlaceholder');
        var uploadArea = document.getElementById('uploadArea');
        var removeBtn = document.getElementById('removeBtn');
        if (previewImg) {
            previewImg.src = e.target.result;
            previewImg.style.display = 'block';
        }
        if (uploadArea) uploadArea.classList.add('has-image');
        if (uploadPlaceholder) uploadPlaceholder.style.display = 'none';
        if (removeBtn) removeBtn.style.display = 'flex';
    };
    reader.readAsDataURL(file);
}

function removePhoto() {
    var previewImg = document.getElementById('previewImg');
    var uploadPlaceholder = document.getElementById('uploadPlaceholder');
    var fileInput = document.getElementById('photo_lapangan');
    var uploadArea = document.getElementById('uploadArea');
    var removeBtn = document.getElementById('removeBtn');
    if (fileInput) fileInput.value = '';
    if (previewImg) {
        previewImg.src = '';
        previewImg.style.display = 'none';
    }
    if (uploadArea) uploadArea.classList.remove('has-image');
    if (uploadPlaceholder) uploadPlaceholder.style.display = 'flex';
    if (removeBtn) removeBtn.style.display = 'none';
}

function searchGrid() {
    var filter = document.getElementById('src').value.toLowerCase();
    var cards = document.querySelectorAll('.lapangan-card');
    cards.forEach(function(card) {
        var name = card.getAttribute('data-name') || '';
        card.style.display = name.indexOf(filter) > -1 ? '' : 'none';
    });
}

function validateForm() {
    var valid = true;
    document.querySelectorAll('.modal-input').forEach(function(el) { el.classList.remove('error'); });
    document.querySelectorAll('.val-msg').forEach(function(el) { el.classList.remove('show'); el.innerHTML = ''; });

    var nama = document.getElementById('nama_lapangan');
    var valNama = document.getElementById('val-nama_lapangan');
    if (nama && valNama) {
        var v = nama.value.trim();
        var errNama = '';
        if (v === '') errNama = 'Nama lapangan wajib diisi.';
        else if (v.length < 3) errNama = 'Nama lapangan minimal 3 karakter.';
        else if (v.length > 50) errNama = 'Nama lapangan maksimal 50 karakter.';
        else if (!/^[a-zA-Z\s]+$/.test(v)) errNama = 'Nama lapangan hanya boleh huruf dan spasi.';
        if (errNama) {
            nama.classList.add('error');
            valNama.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + errNama;
            valNama.classList.add('show');
            valid = false;
        }
    }

    var harga = document.getElementById('harga_sewa');
    var valHarga = document.getElementById('val-harga_sewa');
    if (harga && valHarga) {
        var vh = harga.value.trim();
        var errHarga = '';
        if (vh === '') errHarga = 'Harga sewa wajib diisi.';
        else if (isNaN(vh)) errHarga = 'Harga sewa harus berupa angka.';
        else if (parseFloat(vh) < 10000) errHarga = 'Harga sewa minimal Rp 10.000.';
        else if (parseFloat(vh) > 10000000) errHarga = 'Harga sewa maksimal Rp 10.000.000.';
        if (errHarga) {
            harga.classList.add('error');
            valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + errHarga;
            valHarga.classList.add('show');
            valid = false;
        }
    }

    if (!valid) return false;

    var btn = document.getElementById('btnSubmit');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...';
    }
    return true;
}

document.addEventListener('DOMContentLoaded', function() {
    var namaLap = document.getElementById('nama_lapangan');
    if (namaLap) {
        namaLap.addEventListener('input', function() {
            var valNama = document.getElementById('val-nama_lapangan');
            var v = this.value.trim();
            this.classList.remove('error'); valNama.classList.remove('show');
            if (v.length > 0 && v.length < 3) {
                this.classList.add('error');
                valNama.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama lapangan minimal 3 karakter.';
                valNama.classList.add('show');
            } else if (v.length > 50) {
                this.classList.add('error');
                valNama.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama lapangan maksimal 50 karakter.';
                valNama.classList.add('show');
            }
        });
    }

    var hargaEl = document.getElementById('harga_sewa');
    if (hargaEl) {
        hargaEl.addEventListener('input', function() {
            var valHarga = document.getElementById('val-harga_sewa');
            var v = this.value.trim();
            this.classList.remove('error'); valHarga.classList.remove('show');
            if (v !== '' && !isNaN(v)) {
                if (parseFloat(v) < 10000) {
                    this.classList.add('error');
                    valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Harga sewa minimal Rp 10.000.';
                    valHarga.classList.add('show');
                } else if (parseFloat(v) > 10000000) {
                    this.classList.add('error');
                    valHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Harga sewa maksimal Rp 10.000.000.';
                    valHarga.classList.add('show');
                }
            }
        });
    }

    // NOTIFIKASI TOAST (sama seperti tipe_member.php)
    var urlParams = new URLSearchParams(window.location.search);
    var status = urlParams.get('status');
    var msg = urlParams.get('msg');

    if (status && msg) {
        var isSuccess = status === 'success';
        Swal.fire({
            icon: isSuccess ? 'success' : 'error',
            title: isSuccess ? 'Berhasil!' : 'Gagal!',
            text: msg,
            timer: 3000,
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
        var cleanUrl = window.location.pathname;
        window.history.replaceState({}, document.title, cleanUrl);
    }

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
});

function toggleUserDropdown() {
    var dd = document.getElementById('userDropdown');
    if (dd) dd.classList.toggle('active');
}
document.addEventListener('click', function(e) {
    var dd = document.getElementById('userDropdown');
    if (dd && !dd.contains(e.target)) dd.classList.remove('active');
});

// TOGGLE DENGAN NOTIFIKASI DETAIL
function doToggle(id, currentStatus, namaLapangan) {
    var action = currentStatus == 1 ? 'nonaktifkan' : 'aktifkan';
    var iconType = currentStatus == 1 ? 'warning' : 'question';
    var titleText = currentStatus == 1 ? 'Nonaktifkan Lapangan?' : 'Aktifkan Lapangan?';
    var bodyText = currentStatus == 1 
        ? 'Apakah Anda yakin ingin menonaktifkan lapangan "' + namaLapangan + '"?' 
        : 'Apakah Anda yakin ingin mengaktifkan lapangan "' + namaLapangan + '"?';

    Swal.fire({
        title: titleText,
        html: bodyText + '<br><span style="font-size:12px;color:var(--muted);">Status lapangan akan diubah secara permanen.</span>',
        icon: iconType,
        showCancelButton: true,
        confirmButtonColor: '#FF4500',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, ' + action + '!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then(function(result) {
        if (result.isConfirmed) {
            Swal.fire({ 
                title: 'Memproses...', 
                allowOutsideClick: false, 
                didOpen: function() { Swal.showLoading(); } 
            });
            setTimeout(function() {
                window.location.href = 'lapangan.php?toggle_id=' + id + '&s=' + currentStatus;
            }, 500);
        } else {
            var allCheckboxes = document.querySelectorAll('input[type="checkbox"]');
            allCheckboxes.forEach(function(cb) {
                var oc = cb.getAttribute('onchange') || '';
                if (oc.indexOf('doToggle(' + id + ',' + currentStatus) > -1) {
                    cb.checked = (currentStatus == 1);
                }
            });
        }
    });
}

// DELETE DENGAN NOTIFIKASI DETAIL
function doDelete(id, name) {
    Swal.fire({
        title: 'Hapus Lapangan?',
        html: 'Anda akan menghapus lapangan <strong style="color:var(--orange);">' + name + '</strong><br><span style="font-size:12px;color:var(--muted);">Data akan dihapus secara permanen.</span>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then(function(result) {
        if (result.isConfirmed) {
            Swal.fire({ 
                title: 'Menghapus...', 
                allowOutsideClick: false, 
                didOpen: function() { Swal.showLoading(); } 
            });
            setTimeout(function() {
                window.location.href = 'lapangan.php?delete_id=' + id;
            }, 500);
        }
    });
}

function resetFilter() {
    window.location.href = 'lapangan.php';
}
</script>
</body>
</html>