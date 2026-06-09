<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pemilik') {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
    exit();
}
$nama = $_SESSION['nama'];
$role = $_SESSION['role'];

// ═══════════════════════════════════════════
// HELPER: Get Profile Photo Path (Consistent across all pages)
// ═══════════════════════════════════════════
function getProfilePhotoPath() {
    $photo = $_SESSION['Profile_Photo'] ?? '';
    if (empty($photo)) return '';

    $current_dir = dirname($_SERVER['PHP_SELF']);
    if (strpos($current_dir, '/master/') !== false || strpos($current_dir, '/laporan/') !== false) {
        return '../' . $photo;
    }
    return $photo;
}

function getProfilePhotoAbsolutePath() {
    $photo = $_SESSION['Profile_Photo'] ?? '';
    if (empty($photo)) return '';
    return dirname(__DIR__) . '/' . $photo;
}

$profile_photo = getProfilePhotoPath();
$profile_photo_abs = getProfilePhotoAbsolutePath();

if (!empty($profile_photo) && !file_exists($profile_photo_abs)) {
    $profile_photo = '';
}

$map_jabatan = [1=>'Admin Utama', 2=>'Pemilik / Owner', 3=>'Kasir Pembayaran', 4=>'Staf Operasional', 5=>'Keamanan'];

if (isset($_POST['add_karyawan'])) {
    $id_kry = $_POST['id_kry']; $nama_kry = $_POST['nama']; $jk = $_POST['jk']; $jabatan = $_POST['jabatan']; $telp = $_POST['telp']; $id_akun = $_POST['id_akun'];
    $checkID = sqlsrv_query($conn, "SELECT ID_Karyawan FROM Karyawan WHERE ID_Karyawan=?", array($id_kry));
    if (sqlsrv_has_rows($checkID)) { header("Location: karyawan.php?status=error&msg=ID Karyawan sudah terdaftar!"); exit(); }
    $stmt = sqlsrv_query($conn, "INSERT INTO Karyawan (ID_Karyawan,ID_Akun,Nama_Karyawan,Jenis_Kelamin,Jabatan,No_Telepon) VALUES (?,?,?,?,?,?)", array($id_kry,$id_akun,$nama_kry,$jk,$jabatan,$telp));
    header($stmt ? "Location: karyawan.php?status=success&msg=Karyawan baru berhasil didaftarkan!" : "Location: karyawan.php?status=error&msg=Gagal menambahkan data!"); exit();
}
if (isset($_POST['update_karyawan'])) {
    $stmt = sqlsrv_query($conn, "UPDATE Karyawan SET Nama_Karyawan=?,Jenis_Kelamin=?,Jabatan=?,No_Telepon=? WHERE ID_Karyawan=?", array($_POST['nama'],$_POST['jk'],$_POST['jabatan'],$_POST['telp'],$_POST['id_kry']));
    header($stmt ? "Location: karyawan.php?status=success&msg=Data staf berhasil diperbarui!" : "Location: karyawan.php?status=error&msg=Gagal memperbarui data!"); exit();
}
if (isset($_GET['delete_id'])) {
    $stmt = sqlsrv_query($conn, "DELETE FROM Karyawan WHERE ID_Karyawan=?", array($_GET['delete_id']));
    header($stmt ? "Location: karyawan.php?status=success&msg=Data karyawan dihapus permanen!" : "Location: karyawan.php?status=error&msg=Gagal hapus, data mungkin masih terikat!"); exit();
}

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $r = sqlsrv_query($conn, "SELECT * FROM Karyawan WHERE ID_Karyawan=?", array($_GET['edit_id']));
    $edit_data = sqlsrv_fetch_array($r, SQLSRV_FETCH_ASSOC);
}
$show_add = isset($_GET['add']) && $_GET['add'] == '1';

$q_akun_bebas = sqlsrv_query($conn, "SELECT A.ID_Akun,A.Email FROM Akun A WHERE A.ID_Akun NOT IN (SELECT ID_Akun FROM Karyawan WHERE ID_Akun IS NOT NULL) AND A.Role != 3 ORDER BY A.ID_Akun ASC");
$akun_bebas = [];
if ($q_akun_bebas) while ($ak = sqlsrv_fetch_array($q_akun_bebas, SQLSRV_FETCH_ASSOC)) $akun_bebas[] = $ak;

$q_total = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Karyawan");
$total_kry = sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC)['t'] ?? 0;

$q_total_akun = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Akun WHERE Status_Akun = 1");
$total_akun_aktif = sqlsrv_fetch_array($q_total_akun, SQLSRV_FETCH_ASSOC)['t'] ?? 0;

// --- PAGING ---
$limit = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$count_query = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Karyawan");
$total_rows = sqlsrv_fetch_array($count_query, SQLSRV_FETCH_ASSOC)['total'] ?? 0;
$total_pages = ceil($total_rows / $limit);
$page = min($page, max(1, $total_pages));
$offset = ($page - 1) * $limit;

$query = sqlsrv_query($conn, "SELECT * FROM Karyawan ORDER BY ID_Karyawan ASC OFFSET ? ROWS FETCH NEXT ? ROWS ONLY", array($offset, $limit));
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Karyawan | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10);
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --purple: #8B5CF6; --purple-lt: rgba(139,92,246,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10); --red-dk: #DC2626;
    --yellow: #F59E0B; --yellow-lt: rgba(245,158,11,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --text-md: #374151; --muted: #6B7280; --bg: #F3F4F6;
    --zebra-orange: #FFF7ED;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

/* ═══ SIDEBAR ═══ */
.sidebar { width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0; display: flex; flex-direction: column; padding: 28px 18px; border-right: 1px solid rgba(255,255,255,.04); z-index: 200; overflow-y: auto; }
.sidebar::-webkit-scrollbar { width: 4px; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.1); border-radius: 4px; }
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
.sb-avatar { width: 36px; height: 36px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; line-height: 1.1; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
.sb-logout:hover { color: var(--red); background: rgba(239,68,68,.1); }

/* ═══ MAIN & TOPBAR ═══ */
.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }
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
.t-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.1; }
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
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 28px; }
.page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; letter-spacing: -.5px; }

/* ═══ STAT CARDS ═══ */
.stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-bottom: 28px; }
.stat-card { background: var(--card-bg); border-radius: 16px; padding: 22px 24px; border: 1px solid var(--border); position: relative; overflow: hidden; transition: all .2s ease; }
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,.08); }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 4px 0 0 4px; }
.sc-purple::before { background: var(--purple); }
.sc-blue::before { background: var(--blue); }
.sc-green::before { background: var(--green); }
.stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.stat-icon-wrap { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.si-purple { background: var(--purple-lt); color: var(--purple); }
.si-blue { background: var(--blue-lt); color: var(--blue); }
.si-green { background: var(--green-lt); color: var(--green); }
.stat-trend { font-size: 11px; font-weight: 800; display: flex; align-items: center; gap: 3px; padding: 4px 8px; border-radius: 20px; }
.trend-up { color: var(--green); background: var(--green-lt); }
.stat-value { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); line-height: 1; margin-bottom: 6px; }
.stat-label { font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.stat-sublabel { font-size: 11px; color: var(--muted); margin-top: 4px; opacity: .7; }

/* ═══ CARD ═══ */
.card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; transition: all .2s ease; }
.card:hover { box-shadow: 0 8px 24px rgba(0,0,0,.06); }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; }
.card-title i { color: var(--orange); font-size: 14px; }
.card-badge { background: var(--orange-lt); color: var(--orange); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; }

/* ═══ TOMBOL ADD ═══ */
.btn-add { display: flex; align-items: center; gap: 8px; background: var(--text); color: #fff; padding: 11px 22px; border-radius: 10px; font-size: 13px; font-weight: 800; border: none; cursor: pointer; text-transform: uppercase; letter-spacing: .5px; transition: .2s; text-decoration: none; }
.btn-add:hover { background: var(--orange); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(255,69,0,.25); }

/* ═══ TABLE ═══ */
.table-wrap { overflow-x: auto; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 13px 20px; font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; border-bottom: 2px solid var(--border-lt); text-align: left; }
.data-table td { padding: 16px 20px; font-size: 13px; border-bottom: 1px solid var(--border-lt); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }

/* ═══ ZEBRA STRIPING — ORANGE & PUTIH ═══ */
.data-table tbody tr:nth-child(odd) { background-color: var(--zebra-orange); }
.data-table tbody tr:nth-child(even) { background-color: #FFFFFF; }
.data-table tbody tr:hover td { background-color: #FED7AA !important; }

.emp-id { color: var(--orange); font-weight: 800; font-family: 'Barlow Condensed'; font-size: 16px; }
.emp-name { font-weight: 700; color: var(--text); }
.cell-detail { font-size: 11px; color: var(--muted); font-weight: 600; margin-top: 2px; }

/* ═══ BADGE JABATAN ═══ */
.jabatan-badge { background: #EEF2FF; color: #4338CA; padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; display: inline-block; }
.jk-badge { font-size: 12px; color: var(--muted); font-weight: 600; }

/* ═══ ELEGANT ACTION BUTTONS — SAMA PERSIS MASTER AKUN ═══ */
.action-group { display: flex; align-items: center; gap: 6px; justify-content: flex-end; }
.btn-action {
    display: inline-flex; align-items: center; justify-content: center; gap: 6px;
    padding: 8px 14px; border-radius: 10px; font-size: 12px; font-weight: 700;
    font-family: 'Barlow', sans-serif; text-decoration: none; cursor: pointer;
    transition: all .25s cubic-bezier(.4,0,.2,1); border: 1.5px solid transparent; letter-spacing: .3px;
}
.btn-edit {
    background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%); color: #1E40AF; border-color: #BFDBFE;
}
.btn-edit i { font-size: 13px; }
.btn-edit:hover {
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%); color: #fff; border-color: #3B82F6;
    transform: translateY(-2px); box-shadow: 0 6px 20px rgba(59,130,246,.35);
}
.btn-edit:active { transform: translateY(0); }
.btn-delete {
    background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%); color: #DC2626; border-color: #FECACA;
}
.btn-delete i { font-size: 13px; }
.btn-delete:hover {
    background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%); color: #fff; border-color: #EF4444;
    transform: translateY(-2px); box-shadow: 0 6px 20px rgba(239,68,68,.35);
}
.btn-delete:active { transform: translateY(0); }

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
.empty-state { text-align: center; padding: 60px 20px; color: var(--muted); }
.empty-state i { font-size: 40px; opacity: .3; display: block; margin-bottom: 14px; }
.empty-state p { font-size: 13px; font-weight: 700; }

/* ═══ MODAL ═══ */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,.55); backdrop-filter: blur(6px); display: flex; justify-content: center; align-items: center; z-index: 2000; }
.modal-overlay.hidden { display: none; }
.modal-box { background: #fff; border-radius: 20px; width: 560px; max-width: 95vw; box-shadow: 0 25px 60px rgba(0,0,0,.2); position: relative; max-height: 90vh; overflow-y: auto; }
.modal-head { padding: 28px 32px 24px; border-bottom: 1px solid var(--border); }
.modal-tag { font-size: 10px; font-weight: 800; color: var(--orange); text-transform: uppercase; letter-spacing: .8px; margin-bottom: 6px; }
.modal-title { font-family: 'Barlow Condensed', sans-serif; font-size: 24px; font-weight: 900; color: var(--text); letter-spacing: -.3px; }
.modal-sub { font-size: 13px; color: var(--muted); margin-top: 4px; }
.modal-body { padding: 28px 32px 32px; }
.modal-close { position: absolute; top: 20px; right: 20px; background: var(--bg); border: 1px solid var(--border); font-size: 14px; color: var(--muted); cursor: pointer; transition: .2s; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; }
.modal-close:hover { color: var(--red); border-color: var(--red); background: var(--red-lt); }
.form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.full-width { grid-column: span 2; }
.field-label { font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 6px; }
.field-label .required { color: var(--red); margin-left: 2px; font-size: 14px; font-weight: 900; }
.modal-input { width: 100%; padding: 12px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 14px; font-family: 'Barlow', sans-serif; transition: .2s; background: #FAFAFA; color: var(--text); margin-bottom: 4px; }
.modal-input:focus { outline: none; border-color: var(--orange); background: #fff; box-shadow: 0 0 0 3px rgba(255,69,0,.08); }
.modal-input:invalid:not(:placeholder-shown) { border-color: var(--red); }
.modal-input:valid:not(:placeholder-shown) { border-color: var(--green); }
.modal-input[readonly] { background: var(--border-lt); color: var(--muted); }
.akun-notice { background: #FFF7ED; border: 1px solid #FED7AA; border-radius: 10px; padding: 12px 15px; font-size: 12px; color: #92400E; font-weight: 600; display: flex; align-items: center; gap: 8px; grid-column: span 2; }
.btn-submit { grid-column: span 2; width: 100%; background: var(--orange); color: #fff; border: none; padding: 14px; border-radius: 10px; font-weight: 800; font-size: 14px; cursor: pointer; text-transform: uppercase; letter-spacing: .5px; transition: .2s; font-family: 'Barlow', sans-serif; margin-top: 8px; }
.btn-submit:hover { background: var(--orange-dk); transform: translateY(-1px); box-shadow: 0 8px 20px rgba(255,69,0,.25); }
.btn-submit:disabled { background: #D1D5DB; cursor: not-allowed; transform: none; box-shadow: none; }

/* ═══ VALIDASI ERROR MESSAGE ═══ */
.val-msg { font-size: 11px; color: var(--red); font-weight: 600; margin-bottom: 10px; display: none; min-height: 16px; }
.val-msg.show { display: block; }
.val-msg i { margin-right: 4px; }

/* ═══ RESPONSIVE ═══ */
@media(max-width: 1100px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
@media(max-width: 768px) {
    .sidebar { width: 0; overflow: hidden; padding: 0; }
    .main { margin-left: 0; }
    .stat-grid { grid-template-columns: 1fr; }
    .content { padding: 20px; }
    .topbar { padding: 0 20px; }
    .pagination-wrap { flex-direction: column; gap: 12px; }
}
</style>
</head>
<body>

<!-- ═══ MODAL ═══ -->
<div class="modal-overlay <?= ($edit_data || $show_add) ? '' : 'hidden' ?>" id="modal">
    <div class="modal-box">
        <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <div class="modal-head">
            <div class="modal-tag">Master Karyawan</div>
            <div class="modal-title"><?= $edit_data ? 'Edit Data Staf' : 'Tambah Karyawan Baru' ?></div>
            <div class="modal-sub"><?= $edit_data ? 'Perbarui informasi karyawan yang ada' : 'Daftarkan karyawan baru ke dalam sistem' ?></div>
        </div>
        <div class="modal-body">
            <form method="POST" id="formKaryawan" onsubmit="return validateForm(this)">
                <div class="form-grid">
                    <?php if (!$edit_data && empty($akun_bebas)): ?>
                    <div class="akun-notice">
                        <i class="fa-solid fa-triangle-exclamation"></i>
                        Tidak ada akun tersedia. <a href="akun.php" style="color:var(--orange);font-weight:800;margin-left:4px;">Buat akun baru</a> terlebih dahulu.
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="field-label">ID Karyawan <span class="required">*</span></label>
                        <input type="text" name="id_kry" id="id_kry" class="modal-input" value="<?= $edit_data['ID_Karyawan'] ?? '' ?>" <?= $edit_data ? 'readonly' : 'required' ?> placeholder="KRY001">
                        <div class="val-msg" id="val-id_kry"><i class="fa-solid fa-circle-exclamation"></i> ID Karyawan wajib diisi</div>
                    </div>
                    <div>
                        <label class="field-label">Nama Lengkap <span class="required">*</span></label>
                        <input type="text" name="nama" id="nama" class="modal-input" value="<?= htmlspecialchars($edit_data['Nama_Karyawan'] ?? '') ?>" required minlength="3" maxlength="100" pattern="[a-zA-Z\s]+" placeholder="Nama lengkap (huruf & spasi)">
                        <div class="val-msg" id="val-nama"><i class="fa-solid fa-circle-exclamation"></i> Nama minimal 3 karakter, hanya huruf dan spasi</div>
                    </div>
                    <div>
                        <label class="field-label">Jenis Kelamin <span class="required">*</span></label>
                        <select name="jk" id="jk" class="modal-input" required>
                            <option value="1" <?= (isset($edit_data['Jenis_Kelamin']) && $edit_data['Jenis_Kelamin']==1)?'selected':''?>>Laki-laki</option>
                            <option value="2" <?= (isset($edit_data['Jenis_Kelamin']) && $edit_data['Jenis_Kelamin']==2)?'selected':''?>>Perempuan</option>
                        </select>
                    </div>
                    <div>
                        <label class="field-label">Jabatan <span class="required">*</span></label>
                        <select name="jabatan" id="jabatan" class="modal-input" required>
                            <?php foreach ($map_jabatan as $id => $val): ?>
                            <option value="<?= $id ?>" <?= (isset($edit_data['Jabatan']) && $edit_data['Jabatan']==$id)?'selected':''?>><?= $val ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if (!$edit_data): ?>
                    <div class="full-width">
                        <label class="field-label">Akun Terhubung <span class="required">*</span></label>
                        <select name="id_akun" id="id_akun" class="modal-input" required <?= empty($akun_bebas) ? 'disabled' : '' ?>>
                            <option value="">-- Pilih Akun yang Belum Dipakai --</option>
                            <?php foreach ($akun_bebas as $ak): ?>
                            <option value="<?= $ak['ID_Akun'] ?>"><?= $ak['ID_Akun'] ?> — <?= htmlspecialchars($ak['Email']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="val-msg" id="val-id_akun"><i class="fa-solid fa-circle-exclamation"></i> Pilih akun yang terhubung</div>
                    </div>
                    <?php endif; ?>
                    <div class="full-width">
                        <label class="field-label">Nomor Telepon <span class="required">*</span></label>
                        <input type="text" name="telp" id="telp" class="modal-input" value="<?= htmlspecialchars($edit_data['No_Telepon'] ?? '') ?>" placeholder="08123456789" required pattern="[0-9]{10,14}" maxlength="14">
                        <div class="val-msg" id="val-telp"><i class="fa-solid fa-circle-exclamation"></i> Nomor telepon 10-14 digit, hanya angka</div>
                    </div>
                    <button type="submit" name="<?= $edit_data ? 'update_karyawan' : 'add_karyawan' ?>" class="btn-submit" <?= (!$edit_data && empty($akun_bebas)) ? 'disabled' : '' ?>>
                        <i class="fa-solid <?= $edit_data ? 'fa-floppy-disk' : 'fa-user-plus' ?>"></i>
                        <?= $edit_data ? 'Simpan Perubahan' : 'Daftarkan Karyawan' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ═══ SIDEBAR ═══ -->
<aside class="sidebar">
    <a href="../view_pemilik.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">Management System</div>
        </div>
    </a>
    <div class="sb-section-label">Manajemen</div>
    <nav>
        <a href="../view_pemilik.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div> Dashboard</a>
        <a href="akun.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-user-shield"></i></div> Kelola Akun</a>
        <a href="karyawan.php" class="sb-link active"><div class="sb-icon-wrap"><i class="fa-solid fa-user-tie"></i></div> Kelola Karyawan</a>
        <a href="alat.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div> Kelola Alat</a>
        <a href="../laporan/omzet.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-chart-line"></i></div> Laporan & Omzet</a>
    </nav>
    <div class="sb-section-label">Akun</div>
    <a href="../profile.php" class="sb-link"><div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div> Profil Saya</a>
    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
                    <?php if ($profile_photo): ?>
                        <img src="<?= $profile_photo ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
        </div>
            <div>
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                <div class="sb-user-role">PEMILIK</div>
            </div>
            <a href="../logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- ═══ MAIN ═══ -->
<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Kelola Data Karyawan</div>
            <div class="topbar-breadcrumb">Manajemen / Karyawan</div>
        </div>
        <div class="topbar-right">
            <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a href="#" class="topbar-btn"><i class="fa-solid fa-bell"></i><span class="notif-dot"></span></a>
            <div class="dropdown-wrap">
                <div class="topbar-user">
                    <div class="t-avatar">
                    <?php if ($profile_photo): ?>
                        <img src="<?= $profile_photo ?>" alt="Profile" style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </div>
                    <div>
                        <div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                        <div class="t-role">PEMILIK</div>
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
                <div class="page-title">Daftar Karyawan</div>
            </div>
            <button class="btn-add" onclick="openModal()"><i class="fa-solid fa-plus"></i> Tambah Karyawan</button>
        </div>

        <div class="stat-grid">
            <div class="stat-card sc-purple">
                <div class="stat-header">
                    <div class="stat-icon-wrap si-purple"><i class="fa-solid fa-user-tie"></i></div>
                    <div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Aktif</div>
                </div>
                <div class="stat-value"><?= $total_kry ?></div>
                <div class="stat-label">Total Karyawan</div>
                <div class="stat-sublabel">Terdaftar di sistem</div>
            </div>
            <div class="stat-card sc-blue">
                <div class="stat-header">
                    <div class="stat-icon-wrap si-blue"><i class="fa-solid fa-users"></i></div>
                    <div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Total</div>
                </div>
                <div class="stat-value"><?= $total_akun_aktif ?></div>
                <div class="stat-label">Akun Aktif</div>
                <div class="stat-sublabel">Akun dengan status aktif</div>
            </div>
            <div class="stat-card sc-green">
                <div class="stat-header">
                    <div class="stat-icon-wrap si-green"><i class="fa-solid fa-briefcase"></i></div>
                    <div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Posisi</div>
                </div>
                <div class="stat-value"><?= count($map_jabatan) ?></div>
                <div class="stat-label">Jenis Jabatan</div>
                <div class="stat-sublabel">Posisi tersedia di sistem</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-user-tie"></i> Data Karyawan</div>
                <span class="card-badge"><?= $total_kry ?> total</span>
            </div>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr><th>ID</th><th>Nama Lengkap</th><th>Jabatan</th><th>No. Telepon</th><th style="text-align:right;">Aksi</th></tr>
                    </thead>
                    <tbody>
                    <?php $row_num = 0; $has_data = false; while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)): $row_num++; $has_data = true; ?>
                        <tr class="row-<?= $row_num % 2 == 1 ? 'odd' : 'even' ?>">
                            <td class="emp-id"><?= htmlspecialchars($row['ID_Karyawan']) ?></td>
                            <td>
                                <div class="emp-name"><?= htmlspecialchars($row['Nama_Karyawan']) ?></div>
                                <div class="cell-detail"><?= $row['Jenis_Kelamin'] == 1 ? '♂ Laki-laki' : '♀ Perempuan' ?></div>
                            </td>
                            <td><span class="jabatan-badge"><?= $map_jabatan[$row['Jabatan']] ?? 'Staf' ?></span></td>
                            <td style="color:var(--muted); font-weight:600;"><?= htmlspecialchars($row['No_Telepon']) ?></td>
                            <td>
                                <div class="action-group">
                                    <a href="?page=<?= $page ?>&edit_id=<?= $row['ID_Karyawan'] ?>" class="btn-action btn-edit" title="Edit Data"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                                    <button type="button" class="btn-action btn-delete" onclick="confirmDelete('<?= $row['ID_Karyawan'] ?>', '<?= htmlspecialchars($row['Nama_Karyawan']) ?>')" title="Hapus Permanen"><i class="fa-solid fa-trash-can"></i> Hapus</button>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    <?php if (!$has_data): ?>
                        <tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-user-tie"></i><p>Belum ada data karyawan terdaftar</p></div></td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($total_pages > 1): ?>
        <div class="pagination-wrap">
            <div class="pagination-info">Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> - <strong><?= min($page * $limit, $total_rows) ?></strong> dari <strong><?= $total_rows ?></strong> data</div>
            <div class="pagination-nav">
                <a href="?page=1" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Pertama"><i class="fa-solid fa-angles-left"></i></a>
                <a href="?page=<?= $page - 1 ?>" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" title="Halaman Sebelumnya"><i class="fa-solid fa-angle-left"></i></a>
                <?php $start_page = max(1, $page - 2); $end_page = min($total_pages, $page + 2); if ($end_page - $start_page < 4 && $total_pages >= 5) { if ($start_page == 1) { $end_page = min(5, $total_pages); } else { $start_page = max(1, $total_pages - 4); } } if ($start_page > 1): ?><a href="?page=1" class="page-btn">1</a><?php if ($start_page > 2): ?><span class="page-ellipsis">...</span><?php endif; ?><?php endif; ?>
                <?php for ($i = $start_page; $i <= $end_page; $i++): ?><a href="?page=<?= $i ?>" class="page-btn <?= $i == $page ? 'active' : '' ?>"><?= $i ?></a><?php endfor; ?>
                <?php if ($end_page < $total_pages): ?><?php if ($end_page < $total_pages - 1): ?><span class="page-ellipsis">...</span><?php endif; ?><a href="?page=<?= $total_pages ?>" class="page-btn"><?= $total_pages ?></a><?php endif; ?>
                <a href="?page=<?= $page + 1 ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Selanjutnya"><i class="fa-solid fa-angle-right"></i></a>
                <a href="?page=<?= $total_pages ?>" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>" title="Halaman Terakhir"><i class="fa-solid fa-angles-right"></i></a>
            </div>
        </div>
        <?php else: ?>
        <div class="pagination-wrap"><div class="pagination-info">Menampilkan <strong>1</strong> - <strong><?= $total_rows ?></strong> dari <strong><?= $total_rows ?></strong> data</div></div>
        <?php endif; ?>
    </div>
</main>

<script>
function openModal()  { document.getElementById('modal').classList.remove('hidden'); }
function closeModal() { window.location.href = 'karyawan.php'; }

function confirmDelete(id, nama) {
    Swal.fire({
        title: 'Hapus Karyawan?',
        html: `Karyawan <strong style="color:#FF4500;">${nama}</strong> (${id}) akan dihapus <strong style="color:#DC2626;">secara permanen</strong>!`,
        icon: 'warning', showCancelButton: true, confirmButtonColor: '#EF4444', cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!', cancelButtonText: 'Batal', reverseButtons: true, borderRadius: '16px'
    }).then((r) => { if (r.isConfirmed) window.location.href = '?page=<?= $page ?>&delete_id=' + id; });
}

/* ═══ VALIDASI FORM ═══ */
function validateForm(form) {
    let valid = true;
    const inputs = form.querySelectorAll('.modal-input[required]');
    inputs.forEach(input => {
        const valMsg = document.getElementById('val-' + input.id);
        if (!input.checkValidity()) {
            if (valMsg) valMsg.classList.add('show');
            valid = false;
        } else {
            if (valMsg) valMsg.classList.remove('show');
        }
    });
    return valid;
}

// Live validation on input & blur
document.addEventListener('DOMContentLoaded', function() {
    const inputs = document.querySelectorAll('.modal-input');
    inputs.forEach(input => {
        input.addEventListener('input', function() {
            const valMsg = document.getElementById('val-' + this.id);
            if (valMsg) {
                if (!this.checkValidity() && this.value !== '') { valMsg.classList.add('show'); }
                else { valMsg.classList.remove('show'); }
            }
        });
        input.addEventListener('blur', function() {
            const valMsg = document.getElementById('val-' + this.id);
            if (valMsg) {
                if (!this.checkValidity()) { valMsg.classList.add('show'); }
                else { valMsg.classList.remove('show'); }
            }
        });
    });
});

const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
const msg = urlParams.get('msg');
if (status && msg) {
    Swal.fire({ icon: status === 'success' ? 'success' : 'error', title: status === 'success' ? 'Berhasil!' : 'Gagal!', text: msg, timer: 3000, showConfirmButton: false, iconColor: '#FF4500' });
    window.history.replaceState({}, document.title, window.location.pathname);
}

window.onclick = function(e) { if (e.target == document.getElementById('modal')) closeModal(); };
</script>
</body>
</html>