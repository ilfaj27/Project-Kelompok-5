<?php
session_start();
date_default_timezone_set("Asia/Jakarta");
include '../includes/config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard/dashboard.php';</script>";
    exit();
}
$role = $_SESSION['role'];
$nama = $_SESSION['nama'] ?? 'USER';

// ===== PHOTO PROFILE =====
$profile_photo = '';
$id_karyawan_session = $_SESSION['id_karyawan'] ?? $_SESSION['id_akun'] ?? '';
if (!empty($id_karyawan_session)) {
    $stmt_photo = sqlsrv_query($conn, "SELECT Photo_Profile FROM Karyawan WHERE ID_Karyawan = ?", array($id_karyawan_session));
    if ($stmt_photo !== false) {
        $row_photo = sqlsrv_fetch_array($stmt_photo, SQLSRV_FETCH_ASSOC);
        if ($row_photo && !empty($row_photo['Photo_Profile'])) {
            $photo_path = $row_photo['Photo_Profile'];
            if (strpos($photo_path, '../') === 0) {
                $profile_photo = $photo_path;
            } elseif (strpos($photo_path, 'uploads/') === 0) {
                $profile_photo = '../' . $photo_path;
            } else {
                $profile_photo = '../uploads/profiles/' . $photo_path;
            }
        }
    }
}

function safeQuery($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) return null;
    return $stmt;
}
function safeFetch($stmt) {
    if ($stmt === null) return false;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

function formatInputDate($d) {
    if ($d instanceof DateTime) {
        return $d->format('Y-m-d');
    }
    return !empty($d) ? date('Y-m-d', strtotime($d)) : '';
}
function formatInputTime($t) {
    if ($t instanceof DateTime) {
        return $t->format('H:i');
    }
    return !empty($t) ? date('H:i', strtotime($t)) : '';
}

$lapangan_list = [];
$q_lap = safeQuery($conn, "SELECT ID_Lapangan, Nama_Lapangan, Harga_Sewa FROM Lapangan WHERE Is_Deleted=0 AND Status=1 ORDER BY Nama_Lapangan", []);
if ($q_lap) {
    while ($row = sqlsrv_fetch_array($q_lap, SQLSRV_FETCH_ASSOC)) {
        $lapangan_list[] = $row;
    }
}

if (isset($_POST['save_jadwal'])) {
    $id = isset($_POST['id_jadwal']) ? trim($_POST['id_jadwal']) : '';
    $id_lapangan = $_POST['id_lapangan'];
    $tanggal = $_POST['tanggal'];
    $jam_mulai = $_POST['jam_mulai'];
    $jam_selesai = $_POST['jam_selesai'];

    if (empty($id_lapangan)) {
        header("Location: jadwal.php?page=1&status=error&msg=Lapangan wajib dipilih.");
        exit();
    }
    if (empty($tanggal)) {
        header("Location: jadwal.php?page=1&status=error&msg=Tanggal wajib diisi.");
        exit();
    }
    $hari_ini = date('Y-m-d');
    if ($tanggal < $hari_ini) {
        header("Location: jadwal.php?page=1&status=error&msg=Tanggal tidak boleh kurang dari hari ini.");
        exit();
    }
    // ===== VALIDASI JAM REAL-TIME (SERVER-SIDE) =====
    if ($tanggal == $hari_ini) {
        $jam_sekarang = date('H:i');
        if ($jam_mulai <= $jam_sekarang) {
            header("Location: jadwal.php?page=1&status=error&msg=Jam mulai harus lebih besar dari jam sekarang (" . $jam_sekarang . " WIB).");
            exit();
        }
    }
    // =================================================
    // ===== VALIDASI JAM OPERASIONAL (08:30 - 00:00) =====
    if ($jam_mulai < '08:30') {
        header("Location: jadwal.php?page=1&status=error&msg=Jam operasional dimulai pukul 08:30 WIB.");
        exit();
    }
    if ($jam_selesai > '00:00' && $jam_selesai < '08:30') {
        header("Location: jadwal.php?page=1&status=error&msg=Jam operasional berakhir pukul 00:00 WIB.");
        exit();
    }
    // =====================================================
    if (empty($jam_mulai)) {
        header("Location: jadwal.php?page=1&status=error&msg=Jam mulai wajib diisi.");
        exit();
    }
    if (empty($jam_selesai)) {
        header("Location: jadwal.php?page=1&status=error&msg=Jam selesai wajib diisi.");
        exit();
    }
    // ===== VALIDASI JAM SELESAI MAKSIMAL 00:00 =====
    if ($jam_selesai > '00:00' && $jam_mulai > $jam_selesai) {
        header("Location: jadwal.php?page=1&status=error&msg=Jam selesai maksimal 00:00 WIB. Penyewaan tutup setelah tengah malam.");
        exit();
    }
    // ================================================
    // ===== VALIDASI MINIMAL 1 JAM (SERVER-SIDE) =====
    $mulai_ts = strtotime($tanggal . ' ' . $jam_mulai);
    if ($jam_selesai == '00:00') {
        $selesai_ts = strtotime($tanggal . ' ' . $jam_selesai . ' +1 day');
    } else {
        $selesai_ts = strtotime($tanggal . ' ' . $jam_selesai);
    }
    $selisih_jam = ($selesai_ts - $mulai_ts) / 3600;
    if ($selisih_jam < 1) {
        header("Location: jadwal.php?page=1&status=error&msg=Durasi jadwal minimal 1 jam.");
        exit();
    }
    // ================================================
    if ($jam_selesai != '00:00' && $jam_selesai <= $jam_mulai) {
        header("Location: jadwal.php?page=1&status=error&msg=Jam selesai harus lebih besar dari jam mulai.");
        exit();
    }

    $selesai_compare = ($jam_selesai == '00:00') ? '24:00' : $jam_selesai;
    $sql_check_bentrok = "SELECT ID_Jadwal FROM Jadwal 
                          WHERE ID_Lapangan = ? AND Tanggal = ? AND ID_Jadwal <> ? AND Is_Deleted = 0
                          AND NOT (
                              (CASE WHEN Jam_Selesai = '00:00' THEN '24:00' ELSE CONVERT(VARCHAR(5), Jam_Selesai, 108) END) <= ?
                              OR CONVERT(VARCHAR(5), Jam_Mulai, 108) >= ?
                          )";
    $q_check = safeQuery($conn, $sql_check_bentrok, [$id_lapangan, $tanggal, $id, $jam_mulai, $selesai_compare]);

    if ($q_check && safeFetch($q_check)) {
        header("Location: jadwal.php?page=1&status=error&msg=Jadwal tidak boleh bentrok dengan jadwal lain pada lapangan yang sama.");
        exit();
    }

    if (isset($_POST['edit_mode'])) {
        safeQuery($conn, "EXEC SP_Jadwal_Update @ID_Jadwal=?, @ID_Lapangan=?, @Tanggal=?, @Jam_Mulai=?, @Jam_Selesai=?, @Modified_By=?", [$id, $id_lapangan, $tanggal, $jam_mulai, $jam_selesai, $nama]);
        header("Location: jadwal.php?page=1&status=success&msg=Jadwal berhasil diperbarui!");
    } else {
        safeQuery($conn, "EXEC SP_Jadwal_Insert @ID_Lapangan=?, @Tanggal=?, @Jam_Mulai=?, @Jam_Selesai=?, @Status=1, @Created_By=?", [$id_lapangan, $tanggal, $jam_mulai, $jam_selesai, $nama]);
        header("Location: jadwal.php?page=1&status=success&msg=Jadwal baru berhasil ditambahkan!");
    }
    exit();
}

if (isset($_GET['toggle_id'])) {
    $s_baru = ($_GET['s'] == 1) ? 0 : 1;
    safeQuery($conn, "EXEC SP_Jadwal_Update @ID_Jadwal=?, @Status=?, @Modified_By=?", [$_GET['toggle_id'], $s_baru, $nama]);
    header("Location: jadwal.php?page=1&status=success&msg=Status jadwal berhasil diubah!");
    exit();
}

if (isset($_GET['delete_id'])) {
    safeQuery($conn, "EXEC SP_Jadwal_Delete @ID_Jadwal=?, @Deleted_By=?", [$_GET['delete_id'], $nama]);
    header("Location: jadwal.php?page=1&status=success&msg=Jadwal berhasil dihapus!");
    exit();
}

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $r = safeQuery($conn, "EXEC SP_Jadwal_Select @ID_Jadwal=?", [$_GET['edit_id']]);
    if ($r) $edit_data = safeFetch($r);
}

$detail_data = null;
$show_detail = false;
if (isset($_GET['detail_id'])) {
    $r = safeQuery($conn, "EXEC SP_Jadwal_Select @ID_Jadwal=?", [$_GET['detail_id']]);
    if ($r) {
        $detail_data = safeFetch($r);
        $show_detail = true;
    }
}

$show_add = isset($_GET['add']) && $_GET['add'] == '1';

$where_clauses = ["j.Is_Deleted = 0"];
$params = [];

if (isset($_GET['f_status']) && $_GET['f_status'] !== '') {
    $where_clauses[] = "j.Status = ?";
    $params[] = intval($_GET['f_status']);
}
if (isset($_GET['f_lapangan']) && $_GET['f_lapangan'] !== '') {
    $where_clauses[] = "j.ID_Lapangan = ?";
    $params[] = $_GET['f_lapangan'];
}

$where_sql = implode(" AND ", $where_clauses);

$sort_by = "j.Tanggal DESC, j.Jam_Mulai ASC";
if (isset($_GET['f_sort'])) {
    if ($_GET['f_sort'] === 'tanggal_asc') $sort_by = "j.Tanggal ASC, j.Jam_Mulai ASC";
    elseif ($_GET['f_sort'] === 'lapangan_asc') $sort_by = "l.Nama_Lapangan ASC";
}

$q_total = safeQuery($conn, "EXEC SP_Jadwal_Count", []);
$total_jadwal = 0;
if ($q_total) {
    $row = safeFetch($q_total);
    $total_jadwal = $row['t'] ?? 0;
}

$q_aktif = safeQuery($conn, "EXEC SP_Jadwal_Count @StatusFilter=1", []);
$aktif_count = 0;
if ($q_aktif) {
    $row = safeFetch($q_aktif);
    $aktif_count = $row['t'] ?? 0;
}

$q_nonaktif = safeQuery($conn, "EXEC SP_Jadwal_Count @StatusFilter=0", []);
$nonaktif_count = 0;
if ($q_nonaktif) {
    $row = safeFetch($q_nonaktif);
    $nonaktif_count = $row['t'] ?? 0;
}

$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

// Hitung total data pakai SP (dengan filter)
$q_status = (isset($_GET['f_status']) && $_GET['f_status'] !== '') ? intval($_GET['f_status']) : null;
$q_lapangan = (isset($_GET['f_lapangan']) && $_GET['f_lapangan'] !== '') ? $_GET['f_lapangan'] : null;

$q_count = safeQuery($conn, "EXEC SP_Jadwal_Count @StatusFilter=?, @LapanganFilter=?", [$q_status, $q_lapangan]);
$total_data = 0;
if ($q_count) {
    $row = safeFetch($q_count);
    $total_data = $row['t'] ?? 0;
}

$total_pages = max(1, ceil($total_data / $limit));
$page = min($page, $total_pages);
$offset = ($page - 1) * $limit;

// Query data pakai SP (dengan filter + pagination)
$q_sort = isset($_GET['f_sort']) ? $_GET['f_sort'] : 'tanggal_desc';
$query = safeQuery($conn, "EXEC SP_Jadwal_SelectFiltered @StatusFilter=?, @LapanganFilter=?, @SortBy=?, @PageNumber=?, @PageSize=?", 
    [$q_status, $q_lapangan, $q_sort, $page, $limit]);

$q_pending = safeQuery($conn, "SELECT COUNT(*) as t FROM Booking WHERE Status=0", []);
$total_pending = 0;
if ($q_pending) {
    $row = safeFetch($q_pending);
    $total_pending = $row['t'] ?? 0;   // ✅ BENER: kolomnya 't'
}

$filter_url = "";
if (isset($_GET['f_sort'])) $filter_url .= "&f_sort=" . urlencode($_GET['f_sort']);
if (isset($_GET['f_status'])) $filter_url .= "&f_status=" . urlencode($_GET['f_status']);
if (isset($_GET['f_lapangan'])) $filter_url .= "&f_lapangan=" . urlencode($_GET['f_lapangan']);

// ===== PAGINATION =====
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Hitung total data
$q_count = safeQuery($conn, "EXEC SP_Jadwal_Count", []);
$total_data = 0;
if ($q_count) {
    $row = safeFetch($q_count);
    $total_data = $row['t'] ?? 0;
}
$total_pages = ceil($total_data / $limit);

// Query data jadwal dengan filter
$q_status = (isset($_GET['f_status']) && $_GET['f_status'] !== '') ? intval($_GET['f_status']) : null;
$q_lapangan = (isset($_GET['f_lapangan']) && $_GET['f_lapangan'] !== '') ? $_GET['f_lapangan'] : null;
$q_sort = isset($_GET['f_sort']) ? $_GET['f_sort'] : 'tanggal_desc';

$query = safeQuery($conn, "EXEC SP_Jadwal_SelectFiltered @StatusFilter=?, @LapanganFilter=?, @SortBy=?, @PageNumber=?, @PageSize=?", 
    [$q_status, $q_lapangan, $q_sort, $page, $limit]);

?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Jadwal | HoopBall</title>
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
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

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

/* Active indicator pill */
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

/* Sidebar entrance animation */
@keyframes sidebarSlideIn { from { transform: translateX(-100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
.sidebar { animation: sidebarSlideIn 0.6s cubic-bezier(0.16,1,0.3,1) forwards; }

/* Staggered menu item entrance */
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
.sb-section-label:nth-of-type(3) + nav .sb-link:nth-of-type(1) { animation-delay: 0.95s; }
.sb-bottom { animation: menuItemFadeIn 0.5s cubic-bezier(0.16,1,0.3,1) 1s forwards; opacity: 0; }

.main { margin-left: calc(var(--sidebar-w) - 1px); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
.topbar { background: var(--card-bg); height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; box-shadow: 0 1px 0 rgba(0,0,0,.04); }
.topbar-left { display: flex; flex-direction: column; }
.topbar-title { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); letter-spacing: -.5px; line-height: 1; }
.topbar-breadcrumb { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.topbar-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); cursor: pointer; font-size: 14px; text-decoration: none; transition: .2s; position: relative; }
.topbar-btn:hover { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); }
.topbar-btn, .topbar-user { background-color: #FFFFFF !important; }
.notif-dot { position: absolute; top: 7px; right: 7px; width: 7px; height: 7px; background: var(--orange); border-radius: 50%; border: 2px solid #fff; }
.dropdown-wrap { position: relative; }
.topbar-user { display: flex; align-items: center; gap: 10px; background: var(--bg); border: 1px solid var(--border); padding: 6px 14px 6px 8px; border-radius: 12px; cursor: pointer; transition: .2s; }
.topbar-user:hover { border-color: var(--orange); }
.t-avatar { width: 32px; height: 32px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; overflow: hidden; flex-shrink: 0; }
.t-avatar img { width: 100%; height: 100%; object-fit: cover; }
.t-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.1; text-transform: uppercase; }
.t-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.t-chevron { color: var(--muted); font-size: 10px; margin-left: 4px; }
.dropdown-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #fff; min-width: 200px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 15px 40px rgba(0,0,0,.12); overflow: hidden; padding: 8px 0; z-index: 999; }
.dropdown-wrap.active .dropdown-menu { display: block !important; }
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

.card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; transition: all .2s ease; background-color: #FFFFFF !important; }
.main, .content { background-color: #F3F4F6 !important; }
.card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.06); }
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }

.data-table th { font-family: 'Barlow Condensed', sans-serif !important; font-size: 13px !important; font-weight: 900 !important; color: var(--muted) !important; text-transform: uppercase !important; letter-spacing: 0.8px !important; padding: 14px 20px; border-bottom: 2px solid var(--border-lt); }
.data-table th, .data-table td { padding: 16px 20px; vertical-align: middle; }

.data-table th:nth-child(1), .data-table td:nth-child(1) { text-align: center !important; width: 8%; font-size: 15px; font-weight: 700; }
.data-table th:nth-child(2), .data-table td:nth-child(2) { width: 32%; text-align: center !important; }
.jadwal-lapangan { font-weight: 700; color: var(--text); font-size: 15px; }
.jadwal-id-tag { font-size: 11px; color: var(--muted); margin-top: 2px; }

.data-table th:nth-child(3), .data-table td:nth-child(3) { width: 40%; text-align: center !important; }
.jadwal-waktu { font-family: 'Barlow Condensed', sans-serif; font-weight: 800; font-size: 16px; color: var(--orange); }

.data-table th:nth-child(4), .data-table td:nth-child(4) { width: 18%; text-align: center !important; }
.data-table th:nth-child(4) { position: relative;}
.data-table td:nth-child(4) { font-size: 0 !important; }
.data-table td:nth-child(4) .status-pill { position: relative; display: inline-flex !important; font-size: 12px !important; margin: 0 !important; }

.data-table th:nth-child(5), .data-table td:nth-child(5) { width: 20%; text-align: center !important; }

.status-pill { display: inline-flex; align-items: center; gap: 6px; padding: 7px 16px; border-radius: 20px; font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; }
.sp-active { background: var(--green-lt); color: var(--green); }
.sp-inactive { background: var(--red-lt); color: var(--red); }
.sp-dot { width: 7px; height: 7px; border-radius: 50%; display: inline-block; }
.sp-active .sp-dot { background: var(--green); }
.sp-inactive .sp-dot { background: var(--red); }

.actions { display: flex; gap: 12px; justify-content: flex-start; align-items: center; }
.btn-action { width: 38px; height: 38px; display: inline-flex; align-items: center; justify-content: center; border-radius: 10px; font-size: 14px; font-weight: 700; transition: all .25s cubic-bezier(.4,0,.2,1); border: 1.5px solid transparent; cursor: pointer; }
.btn-view { background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); color: #1E40AF; border-color: #BFDBFE; }
.btn-view:hover { background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: #fff; border-color: #3B82F6; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,.35); }
.btn-edit { background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); color: #1E40AF; border-color: #BFDBFE; }
.btn-edit:hover { background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: #fff; border-color: #3B82F6; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,.35); }
.btn-delete { background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%); color: #DC2626; border-color: #FECACA; }
.btn-delete:hover { background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%); color: #fff; border-color: #EF4444; transform: translateY(-2px); box-shadow: 0 6px 20px rgba(239,68,68,.35); }

.toggle-switch { position: relative; display: inline-flex; align-items: center; width: 44px; height: 24px; cursor: pointer; margin: 0; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--red); transition: .3s; border-radius: 24px; }
.toggle-slider::before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,.2); }
.toggle-switch input:checked + .toggle-slider { background-color: var(--green); }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }
.toggle-switch:hover .toggle-slider { opacity: .9; }

.data-table tbody tr:nth-child(odd) { background-color: #FFF7ED; }
.data-table tbody tr:nth-child(even) { background-color: #FFFFFF; }
.data-table tbody tr:hover td { background-color: #FFEDD5 !important; }

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
.modal-input.error { border-color: var(--red); box-shadow: 0 0 0 3px var(--red-lt); }
.modal-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.btn-submit { width: 100%; background: var(--orange); color: #fff; border: none; padding: 14px; border-radius: 10px; font-weight: 800; font-size: 13px; cursor: pointer; transition: all .2s; text-transform: uppercase; letter-spacing: .5px; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-submit:hover { background: var(--orange-dk); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(255,69,0,.3); }
.btn-cancel { display: block; text-align: center; margin-top: 16px; color: var(--muted); text-decoration: none; font-size: 13px; font-weight: 700; transition: .2s; cursor: pointer; }
.btn-cancel:hover { color: var(--orange); }
.modal-close { position: absolute; top: 20px; right: 20px; width: 36px; height: 36px; border: none; background: var(--border-lt); border-radius: 10px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: var(--muted); font-size: 16px; transition: all .2s; }
.modal-close:hover { background: var(--red-lt); color: var(--red); }

.val-msg { font-size: 11px; color: var(--red); font-weight: 600; margin-bottom: 10px; display: none; min-height: 16px; }
.val-msg.show { display: block; }
.val-msg i { margin-right: 4px; }

/* ===== INFO BOX ===== */
.jam-info-box {
    display: none;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: var(--blue-lt);
    border: 1px solid rgba(59,130,246,.2);
    border-radius: 10px;
    margin-bottom: 16px;
    font-size: 12px;
    font-weight: 700;
    color: var(--blue);
}
.jam-info-box.show { display: flex; }
.jam-info-box i { font-size: 14px; }

.durasi-info-box {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: var(--green-lt);
    border: 1px solid rgba(16,185,129,.2);
    border-radius: 10px;
    margin-bottom: 16px;
    font-size: 12px;
    font-weight: 700;
    color: var(--green);
}
.durasi-info-box i { font-size: 14px; }

.batas-info-box {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 14px;
    background: rgba(239,68,68,.08);
    border: 1px solid rgba(239,68,68,.2);
    border-radius: 10px;
    margin-bottom: 16px;
    font-size: 12px;
    font-weight: 700;
    color: var(--red);
}
.batas-info-box i { font-size: 14px; }

.pagination-wrap { background: var(--card-bg); border: 1px solid var(--border); border-top: none; border-radius: 0 0 16px 16px; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; }
.pagination-info { font-size: 12px; color: var(--muted); font-weight: 600; }
.pagination-info strong { color: var(--text); font-weight: 800; }
.pagination-nav { display: flex; align-items: center; gap: 4px; }
.page-btn { display: inline-flex; align-items: center; justify-content: center; min-width: 36px; height: 36px; padding: 0 10px; border-radius: 10px; font-size: 13px; font-weight: 700; font-family: 'Barlow', sans-serif; text-decoration: none; cursor: pointer; transition: all .2s ease; border: 1.5px solid var(--border); color: var(--text-md); background: #fff; }
.page-btn:hover:not(.disabled):not(.active) { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); transform: translateY(-1px); }
.page-btn.active { background: var(--orange); color: #fff; border-color: var(--orange); box-shadow: 0 4px 12px rgba(255,69,0,.3); font-weight: 800; }
.page-btn.disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; }
.page-btn i { font-size: 11px; }

.empty-state { text-align: center; padding: 50px 20px; color: var(--muted); }
.empty-state i { font-size: 48px; margin-bottom: 16px; opacity: .3; display: block; }
.empty-state div { font-size: 14px; font-weight: 700; }

.filter-dropdown-wrap { position: relative; display: inline-block; }
.btn-filter { display: inline-flex; align-items: center; gap: 8px; background-color: var(--orange); color: #ffffff; padding: 11px 20px; border-radius: 10px; font-size: 13px; font-weight: 800; text-transform: uppercase; border: none; cursor: pointer; transition: all 0.2s; box-shadow: 0 4px 12px rgba(255,69,0,0.2); }
.btn-filter:hover { background-color: var(--orange-dk); transform: translateY(-2px); box-shadow: 0 6px 16px rgba(255,69,0,0.35); }
.btn-filter i.arrow-icon { font-size: 10px; transition: transform 0.3s; }
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

.btn-add { display: inline-flex; align-items: center; gap: 8px; background-color: var(--text); color: #fff; padding: 11px 22px; border-radius: 10px; font-size: 13px; font-weight: 800; text-decoration: none; text-transform: uppercase; transition: all .2s ease; border: none; cursor: pointer; }
.btn-add:hover { background-color: var(--orange); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255,69,0,.3); }
.btn-add i { font-size: 14px; }

.detail-icon-wrap { width: 80px; height: 80px; background: var(--orange-lt); color: var(--orange); border-radius: 20px; display: inline-flex; align-items: center; justify-content: center; font-size: 32px; margin-bottom: 16px; box-shadow: 0 8px 20px rgba(255,69,0,0.15); }
.detail-main-name { font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--text); text-transform: uppercase; }
.info-row { display: flex; justify-content: space-between; align-items: center; padding: 14px 0; border-bottom: 1px solid var(--border-lt); }
.info-row:last-child { border-bottom: none; }
.info-key { display: flex; align-items: center; gap: 10px; font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.3px; }
.info-key i { color: var(--orange); font-size: 14px; width: 18px; text-align: center; }
.info-val { font-size: 14px; font-weight: 700; color: var(--text); }

#clock-display { display: flex; align-items: center; gap: 16px; }
.clock-time { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--orange); display: flex; align-items: center; gap: 6px; line-height: 1; }
.clock-colon { color: var(--orange); opacity: .5; animation: blink 1s infinite; }
@keyframes blink { 0%, 100% { opacity: .5; } 50% { opacity: 1; } }
.clock-divider { width: 1.5px; height: 28px; background-color: var(--border); }
.clock-date { font-family: 'Barlow', sans-serif; font-size: 13px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.5px; }

html, body { scrollbar-width: none; -ms-overflow-style: none; }
html::-webkit-scrollbar, body::-webkit-scrollbar { display: none; }

.topbar-btn:hover, .topbar-user:hover { background-color: #E5E7EB !important; border-color: #D1D5DB !important; color: #4B5563 !important; }
.topbar-btn:active, .topbar-user:active { background-color: #D1D5DB !important; border-color: #9CA3AF !important; color: #1F2937 !important; }
.dropdown-wrap.active .dropdown-menu { display: block !important; }
body.swal2-shown, html.swal2-shown { padding-right: 0px !important; }

select.modal-input,
input[type="date"].modal-input,
input[type="time"].modal-input {
    background-color: #FFFFFF !important;
    cursor: pointer !important;
}
.modal-input::-webkit-calendar-picker-indicator {
    cursor: pointer !important;
}

@media(max-width: 768px) {
    .sidebar { width: 0; overflow: hidden; padding: 0; }
    .main { margin-left: 0; }
    .content { padding: 20px; }
    .modal-box { width: 90%; margin: 20px; }
}
</style>
</head>
<body>
<!-- MODAL FORM JADWAL -->
<div class="modal-overlay <?= ($edit_data || $show_add) ? 'open' : '' ?>" id="modalJadwal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header">
            <div class="modal-subtitle">Kelola Jadwal</div>
            <div class="modal-title"><?= $edit_data ? 'Edit Jadwal' : 'Tambah Jadwal Baru' ?></div>
        </div>
        <div class="modal-body">
            <form method="POST" id="formJadwal" onsubmit="return validateForm()" novalidate>
                <?php if ($edit_data): ?>
                    <input type="hidden" name="edit_mode" value="1">
                    <input type="hidden" name="id_jadwal" id="id_jadwal" value="<?= htmlspecialchars($edit_data['ID_Jadwal']) ?>">
                <?php endif; ?>

                <label class="modal-label">Lapangan <span class="required">*</span></label>
                <select name="id_lapangan" id="id_lapangan" class="modal-input" required>
                    <option value="">Pilih Lapangan</option>
                    <?php foreach ($lapangan_list as $lap): ?>
                        <option value="<?= $lap['ID_Lapangan'] ?>" <?= (isset($edit_data['ID_Lapangan']) && $edit_data['ID_Lapangan'] == $lap['ID_Lapangan']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($lap['Nama_Lapangan']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <div class="val-msg" id="val-id_lapangan"></div>

                <label class="modal-label">Tanggal <span class="required">*</span></label>
                <input type="date" name="tanggal" id="tanggal" class="modal-input" value="<?= isset($edit_data['Tanggal']) ? formatInputDate($edit_data['Tanggal']) : '' ?>" required>
                <div class="val-msg" id="val-tanggal"></div>

                <!-- INFO JAM REAL-TIME -->
                <div class="jam-info-box" id="jamInfoBox">
                    <i class="fa-solid fa-circle-info"></i>
                    <span id="jamInfoText">Jam mulai harus lebih besar dari jam sekarang</span>
                </div>

                <div class="modal-grid-2">
                    <div>
                        <label class="modal-label">Jam Mulai <span class="required">*</span></label>
                        <input type="time" name="jam_mulai" id="jam_mulai" class="modal-input" value="<?= isset($edit_data['Jam_Mulai']) ? formatInputTime($edit_data['Jam_Mulai']) : '' ?>" required>
                        <div class="val-msg" id="val-jam_mulai"></div>
                    </div>
                    <div>
                        <label class="modal-label">Jam Selesai <span class="required">*</span></label>
                        <input type="time" name="jam_selesai" id="jam_selesai" class="modal-input" value="<?= isset($edit_data['Jam_Selesai']) ? formatInputTime($edit_data['Jam_Selesai']) : '' ?>" required>
                        <div class="val-msg" id="val-jam_selesai"></div>
                    </div>
                </div>

                <!-- INFO JAM OPERASIONAL -->
                <div class="durasi-info-box" id="durasiInfoBox">
                    <i class="fa-solid fa-clock"></i>
                    <span>Jam operasional: <strong>08:30 - 00:00 WIB</strong> | Durasi minimal <strong>1 jam</strong></span>
                </div>

                <button type="submit" name="save_jadwal" class="btn-submit">
                    <i class="fa-solid fa-<?= $edit_data ? 'floppy-disk' : 'plus' ?>"></i>
                    <?= $edit_data ? 'Simpan Perubahan' : 'Tambah Jadwal' ?>
                </button>
                <a onclick="closeModal()" class="btn-cancel">Batal</a>
            </form>
        </div>
    </div>
</div>

<!-- MODAL DETAIL JADWAL -->
<div class="modal-overlay <?= $show_detail ? 'open' : '' ?>" id="modalDetail">
    <div class="modal-box" style="width: 440px;">
        <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
            <div class="modal-subtitle">Informasi Jadwal</div>
            <div class="modal-title">Detail Jadwal</div>
        </div>
        <div class="modal-body" style="padding-top: 10px;">
            <?php if ($detail_data): ?>
                <div style="text-align: center; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1.5px dashed var(--border);">
                    <div class="detail-icon-wrap"><i class="fa-solid fa-calendar-days"></i></div>
                    <div class="detail-main-name"><?= htmlspecialchars($detail_data['Nama_Lapangan']) ?></div>
                </div>
                <input type="hidden" value="<?= htmlspecialchars($detail_data['ID_Jadwal']) ?>">
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-calendar-day"></i> Tanggal</span>
                    <span class="info-val" style="font-weight:700;"><?= formatInputDate($detail_data['Tanggal']) ?></span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-clock"></i> Jam Operasional</span>
                    <span class="info-val" style="font-family:'Barlow Condensed'; font-size:18px; color:var(--orange); font-weight:800;"><?= formatInputTime($detail_data['Jam_Mulai']) ?> - <?= formatInputTime($detail_data['Jam_Selesai']) ?> WIB</span>
                </div>
                <div class="info-row" style="border-bottom:none;">
                    <span class="info-key"><i class="fa-solid fa-circle-check"></i> Status</span>
                    <span class="info-val">
                        <span class="status-pill <?= $detail_data['Status'] == 1 ? 'sp-active' : 'sp-inactive' ?>">
                            <span class="sp-dot"></span>
                            <?= $detail_data['Status'] == 1 ? 'AKTIF' : 'NONAKTIF' ?>
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
    <a href="../dashboard/view_admin.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">Sistem Managemen</div>
        </div>
    </a>

    <div class="sb-section-label">Operasional</div>
    <nav>
        <a href="../dashboard/view_admin.php" class="sb-link">
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
        <a href="jadwal.php" class="sb-link active">
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
    </nav>

    <div class="sb-section-label">Transaksi</div>
    <nav>
        <a href="../transaksi/booking.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>
            Kelola Booking
        </a>
        <a href="../transaksi/langganan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-crown"></i></div>
            Kelola Langganan
        </a>
        <a href="../transaksi/pembelian.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-cart-shopping"></i></div>
            Kelola Pembelian Alat
        </a>
        <a href="../transaksi/pembatalan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-ban"></i></div>
            Kelola Pembatalan
        </a>
    </nav>

    <div class="sb-section-label">Akun</div>
    <nav>
        <a href="../profile/profile.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>
            Profil Saya
        </a>
    </nav>

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

<!-- MAIN -->
<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Kelola Jadwal</div>
            <div class="topbar-breadcrumb">Operasional / Jadwal</div>
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
                <?php if($total_pending > 0): ?><span class="notif-dot"></span><?php endif; ?>
            </a>
            <div class="dropdown-wrap" id="userDropdown">
                <div class="topbar-user" onclick="toggleUserDropdown()">
                    <div class="t-avatar">
                        <?php if (!empty($profile_photo)): ?>
                            <img src="<?= $profile_photo ?>" alt="Profile">
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
                <div class="page-title">Kelola Jadwal</div>
            </div>
            <div class="stat-chips">
                <div class="stat-chip chip-green"><i class="fa-solid fa-circle-check"></i> AKTIF <span class="chip-val"><?= $aktif_count ?></span></div>
                <div class="stat-chip chip-red"><i class="fa-solid fa-circle-xmark"></i> NONAKTIF <span class="chip-val"><?= $nonaktif_count ?></span></div>
                <div class="stat-chip chip-blue"><i class="fa-solid fa-calendar-days"></i> TOTAL <span class="chip-val"><?= $total_jadwal ?></span></div>
            </div>
        </div>

        <div class="action-bar">
            <div class="search-box">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="src" placeholder="Cari jadwal..." onkeyup="searchTable()">
            </div>
            <div style="display: flex; gap: 12px; align-items: center;">
                <div class="filter-dropdown-wrap">
                    <button class="btn-filter" id="btnFilterToggle">
                        <i class="fa-solid fa-filter"></i> Filter <i class="fa-solid fa-chevron-down arrow-icon"></i>
                    </button>
                    <div class="filter-card" id="filterCard">
                        <h4>Filter Data</h4>
                        <form method="GET" action="jadwal.php">
                            <div class="filter-group">
                                <label>Lapangan</label>
                                <select name="f_lapangan" class="filter-input">
                                    <option value="">Semua Lapangan</option>
                                    <?php foreach ($lapangan_list as $lap): ?>
                                        <option value="<?= $lap['ID_Lapangan'] ?>" <?= ($_GET['f_lapangan'] ?? '') == $lap['ID_Lapangan'] ? 'selected' : '' ?>><?= htmlspecialchars($lap['Nama_Lapangan']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Status</label>
                                <select name="f_status" class="filter-input">
                                    <option value="">Semua Status</option>
                                    <option value="1" <?= ($_GET['f_status'] ?? '') === '1' ? 'selected' : '' ?>>AKTIF</option>
                                    <option value="0" <?= ($_GET['f_status'] ?? '') === '0' ? 'selected' : '' ?>>NONAKTIF</option>
                                </select>
                            </div>
                            <div class="filter-group">
                                <label>Urutkan</label>
                                <select name="f_sort" class="filter-input">
                                    <option value="tanggal_asc" <?= ($_GET['f_sort'] ?? '') === 'tanggal_asc' ? 'selected' : '' ?>>Tanggal Terdekat</option>
                                    <option value="lapangan_asc" <?= ($_GET['f_sort'] ?? '') === 'lapangan_asc' ? 'selected' : '' ?>>Lapangan A-Z</option>
                                </select>
                            </div>
                            <div class="filter-buttons">
                                <button type="button" class="btn-filter-reset" onclick="resetFilter()"><i class="fa-solid fa-rotate-left"></i> Reset</button>
                                <button type="submit" class="btn-filter-apply"><i class="fa-solid fa-check"></i> Terapkan</button>
                            </div>
                        </form>
                    </div>
                </div>
                <a href="jadwal.php?add=1" class="btn-add"><i class="fa-solid fa-plus"></i>Tambah</a>
            </div>
        </div>

        <div class="card">
            <div class="table-wrap">
                <table class="data-table" id="tbl">
                    <thead>
                        <tr>
                            <th style="width: 80px;">No</th>
                            <th>Lapangan</th>
                            <th>Waktu (Jam)</th>
                            <th style="width: 150px;">Status</th>
                            <th style="text-align: left; width: 180px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php
                    $has_data = false;
                    $no = $offset + 1;
                    if ($query):
                    while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                        $has_data = true;
                        $mulai_formatted = formatInputTime($row['Jam_Mulai']);
                        $selesai_formatted = formatInputTime($row['Jam_Selesai']);
                    ?>
                        <tr>
                            <td style="font-family:'Barlow'; font-weight:700; color:var(--text);"><?= $no++ ?></td>
                            <td>
                                <div class="jadwal-lapangan"><?= htmlspecialchars($row['Nama_Lapangan']) ?></div>
                            </td>
                            <td>
                                <div class="jadwal-waktu"><?= $mulai_formatted ?> - <?= $selesai_formatted ?> WIB</div>
                            </td>
                            <td>
                                <span class="status-pill <?= $row['Status'] == 1 ? 'sp-active' : 'sp-inactive' ?>">
                                    <span class="sp-dot"></span>
                                    <?= $row['Status'] == 1 ? 'AKTIF' : 'NONAKTIF' ?>
                                </span>
                            </td>
                            <td>
                                <div class="actions">
                                    <a href="?detail_id=<?= $row['ID_Jadwal'] ?>" class="btn-action btn-view" title="Lihat Detail"><i class="fa-solid fa-eye"></i></a>
                                    <a href="?edit_id=<?= $row['ID_Jadwal'] ?>" class="btn-action btn-edit" title="Edit Jadwal"><i class="fa-solid fa-pen-to-square"></i></a>
                                    <label class="toggle-switch" title="<?= $row['Status'] == 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                        <input type="checkbox" <?= $row['Status'] == 1 ? 'checked' : '' ?> onchange="confirmToggle('<?= $row['ID_Jadwal'] ?>', <?= $row['Status'] ?>)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                    <button onclick="confirmDelete('<?= $row['ID_Jadwal'] ?>', '<?= htmlspecialchars($row['Nama_Lapangan']) ?>')" class="btn-action btn-delete" title="Hapus Jadwal"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; endif; ?>
                    <?php if (!$has_data): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <i class="fa-solid fa-calendar-days"></i>
                                    <div>Belum ada data jadwal</div>
                                    <div style="font-size: 12px; font-weight: 500; margin-top: 8px; opacity: .7;">Tambah jadwal baru untuk memulai</div>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrap">
            <div class="pagination-info">Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> - <strong><?= min($page * $limit, $total_data) ?></strong> dari <strong><?= $total_data ?></strong> data</div>
            <div class="pagination-nav">
                <a href="?page=1<?= $filter_url ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="fa-solid fa-angles-left"></i></a>
                <a href="?page=<?= $page - 1 ?><?= $filter_url ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>"><i class="fa-solid fa-angle-left"></i></a>
                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                    <a href="?page=<?= $i ?><?= $filter_url ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <a href="?page=<?= $page + 1 ?><?= $filter_url ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"><i class="fa-solid fa-angle-right"></i></a>
                <a href="?page=<?= $total_pages ?><?= $filter_url ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>"><i class="fa-solid fa-angles-right"></i></a>
            </div>
        </div>
        <?php else: ?>
        <div class="pagination-wrap">
            <div class="pagination-info">Menampilkan <strong>1</strong> - <strong><?= $total_data ?></strong> dari <strong><?= $total_data ?></strong> data</div>
        </div>
        <?php endif; ?>
    </div>
</main>
<script>
function toggleUserDropdown() {
    var dd = document.getElementById('userDropdown');
    if (dd) dd.classList.toggle('active');
}
document.addEventListener('click', function(e) {
    var dd = document.getElementById('userDropdown');
    if (dd && !dd.contains(e.target)) dd.classList.remove('active');
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
    const dayName = days[now.getDay()];
    const date = now.getDate();
    const monthName = months[now.getMonth()];
    const year = now.getFullYear();
    document.getElementById('full-date').innerText = dayName + ', ' + date + ' ' + monthName + ' ' + year;
}
updateClock();
setInterval(updateClock, 1000);

function closeModal() { 
    window.location.href = 'jadwal.php'; 
}

function searchTable() {
    var input = document.getElementById('src').value.toUpperCase();
    var rows = document.getElementById('tbl').getElementsByTagName('tr');
    for (var i = 1; i < rows.length; i++) {
        var tdName = rows[i].getElementsByTagName('td')[1];
        if (tdName) {
            var match = false;
            if (tdName && tdName.textContent.toUpperCase().indexOf(input) > -1) match = true;
            rows[i].style.display = match ? '' : 'none';
        }
    }
}

function validateField(fieldId, valId, rules) {
    const field = document.getElementById(fieldId);
    const valMsg = document.getElementById(valId);
    const value = field.value.trim();
    field.classList.remove('error');
    valMsg.classList.remove('show');
    if (rules.required && value === '') {
        field.classList.add('error');
        valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + rules.label + ' wajib diisi.';
        valMsg.classList.add('show');
        return false;
    }
    return true;
}

// ===== VALIDASI JAM REAL-TIME (CLIENT-SIDE) =====
function getTodayDate() {
    const now = new Date();
    const y = now.getFullYear();
    const m = String(now.getMonth() + 1).padStart(2, '0');
    const d = String(now.getDate()).padStart(2, '0');
    return y + '-' + m + '-' + d;
}

function getCurrentTime() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const min = String(now.getMinutes()).padStart(2, '0');
    return h + ':' + min;
}

function checkJamRealTime() {
    const tanggalField = document.getElementById('tanggal');
    const jamMulaiField = document.getElementById('jam_mulai');
    const jamInfoBox = document.getElementById('jamInfoBox');
    const jamInfoText = document.getElementById('jamInfoText');
    
    if (!tanggalField || !jamMulaiField || !jamInfoBox) return;
    
    const selectedDate = tanggalField.value;
    const today = getTodayDate();
    const currentTime = getCurrentTime();
    
    if (selectedDate === today) {
        jamInfoBox.classList.add('show');
        jamInfoText.innerText = 'Jam mulai harus lebih besar dari jam sekarang (' + currentTime + ' WIB)';
        
        jamMulaiField.setAttribute('min', currentTime);
        
        if (jamMulaiField.value && jamMulaiField.value <= currentTime) {
            jamMulaiField.classList.add('error');
            const valMsg = document.getElementById('val-jam_mulai');
            valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Jam mulai harus lebih besar dari jam sekarang (' + currentTime + ' WIB).';
            valMsg.classList.add('show');
            return false;
        }
    } else {
        jamInfoBox.classList.remove('show');
        jamMulaiField.removeAttribute('min');
    }
    return true;
}

// ===== VALIDASI JAM OPERASIONAL (08:30 - 00:00) =====
function checkJamOperasional() {
    const jamMulaiField = document.getElementById('jam_mulai');
    const jamSelesaiField = document.getElementById('jam_selesai');
    const valMulai = document.getElementById('val-jam_mulai');
    const valSelesai = document.getElementById('val-jam_selesai');

    if (!jamMulaiField || !jamSelesaiField) return true;

    let valid = true;

    // Jam mulai minimal 08:30
    if (jamMulaiField.value && jamMulaiField.value < '08:30') {
        jamMulaiField.classList.add('error');
        valMulai.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Jam operasional dimulai pukul 08:30 WIB.';
        valMulai.classList.add('show');
        valid = false;
    }

    // Jam selesai maksimal 00:00 (tengah malam), tidak boleh di antara 00:00-08:30
    if (jamSelesaiField.value && jamSelesaiField.value > '00:00' && jamSelesaiField.value < '08:30') {
        jamSelesaiField.classList.add('error');
        valSelesai.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Jam operasional berakhir pukul 00:00 WIB.';
        valSelesai.classList.add('show');
        valid = false;
    }

    return valid;
}
// =====================================================

// ===== VALIDASI DURASI MINIMAL 1 JAM =====
function checkDurasiMinimal() {
    const jamMulaiField = document.getElementById('jam_mulai');
    const jamSelesaiField = document.getElementById('jam_selesai');
    const valMsg = document.getElementById('val-jam_selesai');
    
    if (!jamMulaiField || !jamSelesaiField || !jamMulaiField.value || !jamSelesaiField.value) return true;
    
    const mulai = jamMulaiField.value;
    const selesai = jamSelesaiField.value;
    
    const [h1, m1] = mulai.split(':').map(Number);
    const [h2, m2] = selesai.split(':').map(Number);
    
    let selesaiMenit = h2 * 60 + m2;
    if (selesai === '00:00') {
        selesaiMenit = 24 * 60;
    }
    
    const mulaiMenit = h1 * 60 + m1;
    const selisihMenit = selesaiMenit - mulaiMenit;
    const selisihJam = selisihMenit / 60;
    
    if (selisihJam < 1) {
        jamSelesaiField.classList.add('error');
        valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Durasi minimal 1 jam.';
        valMsg.classList.add('show');
        return false;
    }
    return true;
}

// ===== VALIDASI BATAS JAM 00:00 =====
function checkBatasJam() {
    const jamMulaiField = document.getElementById('jam_mulai');
    const jamSelesaiField = document.getElementById('jam_selesai');
    const valMsg = document.getElementById('val-jam_selesai');
    
    if (!jamMulaiField || !jamSelesaiField || !jamMulaiField.value || !jamSelesaiField.value) return true;
    
    const mulai = jamMulaiField.value;
    const selesai = jamSelesaiField.value;
    
    // Jika selesai !== '00:00' dan mulai > selesai → melewati tengah malam
    if (selesai !== '00:00' && mulai > selesai) {
        jamSelesaiField.classList.add('error');
        valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Jam selesai maksimal 00:00 WIB. Penyewaan tutup setelah tengah malam.';
        valMsg.classList.add('show');
        return false;
    }
    
    return true;
}

function validateForm() {
    let valid = true;
    document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));
    document.querySelectorAll('.val-msg').forEach(el => el.classList.remove('show'));
    if (!validateField('id_lapangan', 'val-id_lapangan', { required: true, label: 'Lapangan' })) valid = false;
    if (!validateField('tanggal', 'val-tanggal', { required: true, label: 'Tanggal' })) valid = false;
    if (!validateField('jam_mulai', 'val-jam_mulai', { required: true, label: 'Jam mulai' })) valid = false;
    if (!validateField('jam_selesai', 'val-jam_selesai', { required: true, label: 'Jam selesai' })) valid = false;
    if (valid) {
        const tanggalField = document.getElementById('tanggal');
        const tanggalVal = tanggalField.value;
        const hariIni = new Date().toISOString().split('T')[0];
        if (tanggalVal < hariIni) {
            tanggalField.classList.add('error');
            const valMsg = document.getElementById('val-tanggal');
            valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Tanggal tidak boleh kurang dari hari ini.';
            valMsg.classList.add('show');
            valid = false;
        }
        // ===== VALIDASI JAM REAL-TIME =====
        if (tanggalVal === hariIni) {
            const currentTime = getCurrentTime();
            const mulai = document.getElementById('jam_mulai').value;
            if (mulai <= currentTime) {
                document.getElementById('jam_mulai').classList.add('error');
                const valMsg = document.getElementById('val-jam_mulai');
                valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Jam mulai harus lebih besar dari jam sekarang (' + currentTime + ' WIB).';
                valMsg.classList.add('show');
                valid = false;
            }
        }
        // ====================================
        const mulai = document.getElementById('jam_mulai').value;
        const selesai = document.getElementById('jam_selesai').value;
        
        // ===== VALIDASI BATAS JAM 00:00 =====
        if (!checkBatasJam()) {
            valid = false;
        }
        // ====================================
        
        // ===== VALIDASI JAM SELESAI > JAM MULAI (kecuali 00:00) =====
        if (selesai !== '00:00' && selesai <= mulai) {
            document.getElementById('jam_selesai').classList.add('error');
            const valMsg = document.getElementById('val-jam_selesai');
            valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Jam selesai harus lebih besar dari jam mulai.';
            valMsg.classList.add('show');
            valid = false;
        }
        // ====================================
        
        // ===== VALIDASI JAM OPERASIONAL (08:30 - 00:00) =====
        if (!checkJamOperasional()) {
            valid = false;
        }
        // =====================================================

        // ===== VALIDASI DURASI MINIMAL 1 JAM =====
        if (!checkDurasiMinimal()) {
            valid = false;
        }
        // =========================================
    }
    return valid;
}

function confirmToggle(id, status) {
    const action = status == 1 ? 'nonaktifkan' : 'aktifkan';
    const iconType = status == 1 ? 'warning' : 'question';
    Swal.fire({
        title: 'Konfirmasi Perubahan Status',
        text: 'Apakah Anda yakin ingin ' + action + ' jadwal ini?',
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
                text: 'Mengubah status jadwal',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            setTimeout(() => {
                window.location.href = '?toggle_id=' + id + '&s=' + status;
            }, 600);
        } else {
            var checkbox = document.querySelector('input[onchange*="confirmToggle(\'' + id + '\'"]');
            if (checkbox) checkbox.checked = !checkbox.checked;
        }
    });
}

function confirmDelete(id, name) {
    Swal.fire({
        title: 'Hapus Jadwal?',
        html: 'Anda akan menghapus jadwal lapangan <strong style="color:var(--orange);">' + name + '</strong><br><span style="font-size:12px;color:var(--muted);">Data akan dihapus secara Permanen</span>',
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
                text: 'Menghapus data jadwal',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });
            setTimeout(() => {
                window.location.href = '?delete_id=' + id;
            }, 600);
        }
    });
}

const btnFilterToggle = document.getElementById('btnFilterToggle');
const filterCard = document.getElementById('filterCard');
if (btnFilterToggle && filterCard) {
    btnFilterToggle.addEventListener('click', function(e) {
        e.stopPropagation();
        this.classList.toggle('active');
        filterCard.classList.toggle('open');
    });
    filterCard.addEventListener('click', function(e) { e.stopPropagation(); });
    document.addEventListener('click', function() {
        btnFilterToggle.classList.remove('active');
        filterCard.classList.remove('open');
    });
}

function resetFilter() {
    window.location.href = 'jadwal.php';
}

document.addEventListener('DOMContentLoaded', function() {
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
    // ============================================
    const idLapangan = document.getElementById('id_lapangan');
    if (idLapangan) {
        idLapangan.addEventListener('change', function() {
            validateField('id_lapangan', 'val-id_lapangan', { required: true, label: 'Lapangan' });
        });
    }
    const tanggal = document.getElementById('tanggal');
    if (tanggal) {
        tanggal.addEventListener('change', function() {
            validateField('tanggal', 'val-tanggal', { required: true, label: 'Tanggal' });
            checkJamRealTime();
        });
    }
    const jamMulai = document.getElementById('jam_mulai');
    if (jamMulai) {
        jamMulai.addEventListener('change', function() {
            validateField('jam_mulai', 'val-jam_mulai', { required: true, label: 'Jam mulai' });
            checkJamRealTime();
            checkJamOperasional();
            checkDurasiMinimal();
            checkBatasJam();
        });
    }
    const jamSelesai = document.getElementById('jam_selesai');
    if (jamSelesai) {
        jamSelesai.addEventListener('change', function() {
            validateField('jam_selesai', 'val-jam_selesai', { required: true, label: 'Jam selesai' });
            checkJamOperasional();
            checkDurasiMinimal();
            checkBatasJam();
        });
    }
    
    checkJamRealTime();
});
</script>
</body>
</html>