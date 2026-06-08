<?php
session_start();
include '../includes/config.php';

// 1. PROTEKSI AKSES
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'pemilik') {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
    exit();
}

$nama_user = $_SESSION['nama'];
$role_user = $_SESSION['role'];

// --- 2. LOGIKA MAPPING ROLE ---
$current_filter = isset($_GET['role']) ? $_GET['role'] : 'all';
$role_map = ['manajer' => 1, 'karyawan' => 2, 'customer' => 3];

// --- 3. LOGIKA PROSES CRUD ---

// CREATE KARYAWAN
if (isset($_POST['create_karyawan'])) {
    $email = $_POST['new_email'];
    $username = $_POST['new_username'];
    $pass  = $_POST['new_password'];

    // Check email exists
    $checkEmail = sqlsrv_query($conn, "SELECT Email FROM Akun WHERE Email = ?", array($email));
    if (sqlsrv_has_rows($checkEmail)) {
        header("Location: akun.php?role=karyawan&status=error&msg=Email sudah terdaftar!");
        exit();
    }
    // Check username exists
    $checkUser = sqlsrv_query($conn, "SELECT Username FROM Akun WHERE Username = ?", array($username));
    if (sqlsrv_has_rows($checkUser)) {
        header("Location: akun.php?role=karyawan&status=error&msg=Username sudah terdaftar!");
        exit();
    }

    $sql_id = "SELECT TOP 1 ID_Akun FROM Akun ORDER BY ID_Akun DESC";
    $query_id = sqlsrv_query($conn, $sql_id);
    $row_id = sqlsrv_fetch_array($query_id, SQLSRV_FETCH_ASSOC);
    $new_id = $row_id ? "AKN" . str_pad((int)substr($row_id['ID_Akun'], 3) + 1, 3, "0", STR_PAD_LEFT) : "AKN001";

    $sql_cr = "INSERT INTO Akun (ID_Akun, Username, Email, Kata_Sandi, Role, Status_Akun) VALUES (?, ?, ?, ?, 2, 1)";
    if(sqlsrv_query($conn, $sql_cr, array($new_id, $username, $email, $pass))) {
        header("Location: akun.php?role=karyawan&status=success&msg=Akun $new_id berhasil dibuat!");
    } else {
        header("Location: akun.php?role=karyawan&status=error&msg=Gagal Simpan Akun!");
    }
    exit();
}

// UPDATE AKUN
if (isset($_POST['update_akun'])) {
    $id = $_POST['id_akun'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $pass = $_POST['password'];
    $role = $_POST['role'];
    $sql_up = "UPDATE Akun SET Username=?, Email=?, Kata_Sandi=?, Role=? WHERE ID_Akun=?";
    sqlsrv_query($conn, $sql_up, array($username, $email, $pass, $role, $id));
    header("Location: akun.php?role=$current_filter&status=success&msg=Data akun diperbarui!");
    exit();
}

// TOGGLE STATUS (Soft disable/enable)
if (isset($_GET['toggle_id'])) {
    $status_baru = ($_GET['s'] == 1) ? 0 : 1;
    sqlsrv_query($conn, "UPDATE Akun SET Status_Akun = ? WHERE ID_Akun = ?", array($status_baru, $_GET['toggle_id']));
    header("Location: akun.php?role=$current_filter&status=success&msg=Status akun berhasil diubah!");
    exit();
}

// HARD DELETE (Permanent delete from database)
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];

    // First, delete related records in Karyawan table if exists
    sqlsrv_query($conn, "DELETE FROM Karyawan WHERE ID_Akun = ?", array($delete_id));

    // Then, delete related records in Customer table if exists
    sqlsrv_query($conn, "DELETE FROM Customer WHERE ID_Akun = ?", array($delete_id));

    // Finally, delete the account itself
    $stmt = sqlsrv_query($conn, "DELETE FROM Akun WHERE ID_Akun = ?", array($delete_id));

    if ($stmt) {
        header("Location: akun.php?role=$current_filter&status=success&msg=Akun $delete_id berhasil dihapus permanen!");
    } else {
        header("Location: akun.php?role=$current_filter&status=error&msg=Gagal menghapus akun! Mungkin masih terikat dengan data lain.");
    }
    exit();
}

$edit_data = null;
if (isset($_GET['edit_id'])) {
    $res_edit = sqlsrv_query($conn, "SELECT * FROM Akun WHERE ID_Akun = ?", array($_GET['edit_id']));
    $edit_data = sqlsrv_fetch_array($res_edit, SQLSRV_FETCH_ASSOC);
}
$show_create = isset($_GET['create']) && $_GET['create'] == '1' && $current_filter === 'karyawan';

// STATISTIK
$q_active = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Akun WHERE Status_Akun = 1");
$active_count = sqlsrv_fetch_array($q_active, SQLSRV_FETCH_ASSOC)['total'] ?? 0;
$q_suspended = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Akun WHERE Status_Akun = 0");
$suspended_count = sqlsrv_fetch_array($q_suspended, SQLSRV_FETCH_ASSOC)['total'] ?? 0;
$q_total = sqlsrv_query($conn, "SELECT COUNT(*) as total FROM Akun");
$total_count = sqlsrv_fetch_array($q_total, SQLSRV_FETCH_ASSOC)['total'] ?? 0;

// QUERY DATA TABEL
if ($current_filter == 'all') {
    $query = sqlsrv_query($conn, "SELECT * FROM Akun ORDER BY Role ASC");
} else {
    $role_id = $role_map[$current_filter] ?? null;
    $query = sqlsrv_query($conn, "SELECT * FROM Akun WHERE Role = ? ORDER BY ID_Akun ASC", array($role_id));
}
$role_label_map = [1 => 'Manajer', 2 => 'Karyawan', 3 => 'Customer'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Data Akun | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --orange:    #FF4500;
    --orange-lt: rgba(255,69,0,.10);
    --orange-dk: #E03E00;
    --green:     #10B981;
    --green-lt:  rgba(16,185,129,.10);
    --green-dk:  #059669;
    --blue:      #3B82F6;
    --blue-lt:   rgba(59,130,246,.10);
    --purple:    #8B5CF6;
    --purple-lt: rgba(139,92,246,.10);
    --red:       #EF4444;
    --red-lt:    rgba(239,68,68,.10);
    --red-dk:    #DC2626;
    --yellow:    #F59E0B;
    --yellow-lt: rgba(245,158,11,.10);
    --sidebar:   #0D1117;
    --sidebar-w: 260px;
    --topbar-h:  70px;
    --card-bg:   #FFFFFF;
    --border:    #E5E7EB;
    --border-lt: #F3F4F6;
    --text:      #111827;
    --text-md:   #374151;
    --muted:     #6B7280;
    --bg:        #F3F4F6;
    --bg-dark:   #1F2937;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

/* ═══════════════════════════════════════════
   SIDEBAR - SAMA PERSIS DENGAN DASHBOARD
   ═══════════════════════════════════════════ */
.sidebar {
    width: var(--sidebar-w); background: var(--sidebar); height: 100vh; position: fixed; top: 0; left: 0;
    display: flex; flex-direction: column; padding: 28px 18px; border-right: 1px solid rgba(255,255,255,.04);
    z-index: 200; overflow-y: auto;
}
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

/* ═══════════════════════════════════════════
   MAIN & TOPBAR - SAMA PERSIS DENGAN DASHBOARD
   ═══════════════════════════════════════════ */
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
.t-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.1; text-transform: uppercase; }
.t-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.t-chevron { color: var(--muted); font-size: 10px; margin-left: 4px; }
.dropdown-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #fff; min-width: 200px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 15px 40px rgba(0,0,0,.12); overflow: hidden; padding: 8px 0; z-index: 999; }
.dropdown-wrap:hover .dropdown-menu { display: block; }
.dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-item i { font-size: 14px; width: 18px; text-align: center; }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

/* ═══════════════════════════════════════════
   CONTENT & PAGE HEADER
   ═══════════════════════════════════════════ */
.content { padding: 32px 40px; flex: 1; }
.page-header { display: flex; align-items: flex-end; justify-content: space-between; margin-bottom: 24px; }
.page-title-tag { width: 36px; height: 4px; background: var(--orange); border-radius: 2px; margin-bottom: 8px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); text-transform: uppercase; }

/* ═══════════════════════════════════════════
   STAT CARDS - SAMA PERSIS DASHBOARD
   ═══════════════════════════════════════════ */
.stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; margin-bottom: 28px; }
.stat-card { background: var(--card-bg); border-radius: 16px; padding: 22px 24px; border: 1px solid var(--border); position: relative; overflow: hidden; transition: all .2s ease; }
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,.08); }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 4px 0 0 4px; }
.sc-orange::before { background: var(--orange); }
.sc-green::before  { background: var(--green); }
.sc-red::before    { background: var(--red); }
.stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.stat-icon-wrap { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.si-orange { background: var(--orange-lt); color: var(--orange); }
.si-green  { background: var(--green-lt);  color: var(--green); }
.si-red    { background: var(--red-lt);    color: var(--red); }
.stat-trend { font-size: 11px; font-weight: 800; display: flex; align-items: center; gap: 3px; padding: 4px 8px; border-radius: 20px; }
.trend-up   { color: var(--green); background: var(--green-lt); }
.trend-down { color: var(--red);   background: var(--red-lt); }
.stat-value { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); line-height: 1; margin-bottom: 6px; }
.stat-label { font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }
.stat-sublabel { font-size: 11px; color: var(--muted); margin-top: 4px; opacity: .7; }

/* ═══════════════════════════════════════════
   TOOLBAR (Filter & Search)
   ═══════════════════════════════════════════ */
.toolbar { background: var(--card-bg); border: 1px solid var(--border); border-radius: 16px 16px 0 0; padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid var(--bg); }
.tab-group { display: flex; gap: 4px; background: var(--bg); padding: 4px; border-radius: 10px; }
.tab-item { padding: 7px 16px; border-radius: 8px; text-decoration: none; color: var(--muted); font-size: 12px; font-weight: 700; transition: 0.2s; }
.tab-item.active { background: #fff; color: var(--text); box-shadow: 0 1px 4px rgba(0,0,0,0.1); }
.search-wrap { position: relative; }
.search-wrap i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 12px; }
.search-input { padding: 9px 12px 9px 34px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; color: var(--text); width: 220px; outline: none; transition: 0.2s; }
.search-input:focus { border-color: var(--orange); }
.btn-add { background: var(--text); color: #fff; padding: 10px 20px; border-radius: 10px; font-size: 12px; font-weight: 800; text-decoration: none; text-transform: uppercase; transition: 0.2s; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; }
.btn-add:hover { background: var(--orange); transform: translateY(-1px); }

/* ═══════════════════════════════════════════
   TABLE - SAMA PERSIS DASHBOARD
   ═══════════════════════════════════════════ */
.table-wrap { background: var(--card-bg); border: 1px solid var(--border); border-top: none; border-radius: 0 0 16px 16px; overflow: hidden; margin-bottom: 32px; }
table { width: 100%; border-collapse: collapse; }
th { padding: 13px 20px; font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; border-bottom: 1px solid var(--border); text-align: left; letter-spacing: .6px; }
td { padding: 15px 20px; font-size: 13px; border-bottom: 1px solid #F9FAFB; vertical-align: middle; }
tr:hover td { background: #FAFAFA; }

.role-badge { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; display: inline-block; }
.badge-1 { background: #FEF3C7; color: #92400E; }
.badge-2 { background: #DBEAFE; color: #1E40AF; }
.badge-3 { background: #F3F4F6; color: #4B5563; }

.status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.status-active   { background: var(--green); }
.status-inactive { background: var(--red); }
.status-text { font-size: 11px; font-weight: 800; }
.status-text-active   { color: var(--green); }
.status-text-inactive { color: var(--red); }

.id-akun { font-family: 'Barlow Condensed', sans-serif; font-weight: 800; color: var(--orange); font-size: 15px; }
.email-text { font-weight: 600; color: var(--text); }
.username-text { font-weight: 700; color: var(--text-md); font-size: 13px; }

/* PASSWORD MASK IN TABLE */
.password-mask-table { display: flex; align-items: center; gap: 8px; font-family: monospace; font-size: 14px; letter-spacing: 2px; color: var(--muted); }
.password-dots-table { font-size: 16px; letter-spacing: 3px; }
.btn-toggle-pass { background: none; border: none; color: var(--muted); cursor: pointer; font-size: 14px; padding: 4px; transition: .2s; border-radius: 6px; width: 28px; height: 28px; display: inline-flex; align-items: center; justify-content: center; }
.btn-toggle-pass:hover { color: var(--orange); background: var(--orange-lt); }

/* CUSTOMER PASSWORD - NO TOGGLE */
.password-customer { font-family: monospace; font-size: 16px; letter-spacing: 3px; color: var(--muted); }

/* TOGGLE SWITCH */
.toggle-switch { position: relative; display: inline-block; width: 44px; height: 24px; cursor: pointer; }
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: var(--red); transition: .3s; border-radius: 24px; }
.toggle-slider::before { position: absolute; content: ""; height: 18px; width: 18px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; box-shadow: 0 2px 4px rgba(0,0,0,.2); }
.toggle-switch input:checked + .toggle-slider { background-color: var(--green); }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(20px); }
.toggle-switch:hover .toggle-slider { opacity: .9; }

/* ═══════════════════════════════════════════
   ELEGANT ACTION BUTTONS - GANTENG STYLE
   ═══════════════════════════════════════════ */
.action-group { display: flex; align-items: center; gap: 6px; justify-content: flex-end; }

.btn-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 14px;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 700;
    font-family: 'Barlow', sans-serif;
    text-decoration: none;
    cursor: pointer;
    transition: all .25s cubic-bezier(.4,0,.2,1);
    border: 1.5px solid transparent;
    letter-spacing: .3px;
}

/* EDIT BUTTON - ELEGANT */
.btn-edit {
    background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
    color: #1E40AF;
    border-color: #BFDBFE;
}
.btn-edit i { font-size: 13px; }
.btn-edit:hover {
    background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
    color: #fff;
    border-color: #3B82F6;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(59,130,246,.35);
}
.btn-edit:active { transform: translateY(0); }

/* DELETE BUTTON - ELEGANT */
.btn-delete {
    background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%);
    color: #DC2626;
    border-color: #FECACA;
}
.btn-delete i { font-size: 13px; }
.btn-delete:hover {
    background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
    color: #fff;
    border-color: #EF4444;
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(239,68,68,.35);
}
.btn-delete:active { transform: translateY(0); }

/* ICON ONLY VARIANT */
.btn-icon {
    width: 36px;
    height: 36px;
    padding: 0;
    border-radius: 10px;
}

/* ═══════════════════════════════════════════
   MODAL - SAMA PERSIS DASHBOARD + REQUIRED MARK
   ═══════════════════════════════════════════ */
.modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.5); backdrop-filter: blur(6px); display: flex; align-items: center; justify-content: center; z-index: 2000; }
.modal-overlay.hidden { display: none; }
.modal-box { background: #fff; border-radius: 20px; width: 480px; overflow: hidden; box-shadow: 0 25px 60px rgba(0,0,0,0.2); }
.modal-header { padding: 28px 32px 20px; border-bottom: 1px solid var(--border); }
.modal-subtitle { font-size: 10px; font-weight: 800; color: var(--orange); text-transform: uppercase; margin-bottom: 6px; letter-spacing: .8px; }
.modal-title { font-family: 'Barlow Condensed', sans-serif; font-size: 22px; font-weight: 900; color: var(--text); }
.modal-body { padding: 24px 32px 32px; }
.modal-label { font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; display: block; margin-bottom: 6px; letter-spacing: .5px; }
.modal-label .required { color: var(--red); margin-left: 2px; font-size: 14px; }
.modal-input { width: 100%; padding: 11px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; margin-bottom: 16px; outline: none; transition: .2s; color: var(--text); }
.modal-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.modal-select { width: 100%; padding: 11px 14px; border: 1.5px solid var(--border); border-radius: 10px; font-size: 13px; font-family: 'Barlow', sans-serif; margin-bottom: 16px; outline: none; background: #fff; color: var(--text); cursor: pointer; appearance: none; background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E"); background-repeat: no-repeat; background-position: right 14px center; padding-right: 40px; }
.modal-select:focus { border-color: var(--orange); }
.btn-save { background: var(--text); color: #fff; padding: 12px 20px; border-radius: 10px; font-size: 13px; font-weight: 800; text-transform: uppercase; border: none; cursor: pointer; width: 100%; transition: .2s; letter-spacing: .5px; display: flex; align-items: center; justify-content: center; gap: 8px; }
.btn-save:hover { background: var(--orange); }
.btn-cancel { display: block; text-align: center; margin-top: 12px; color: var(--muted); font-size: 12px; text-decoration: none; font-weight: 700; transition: .2s; }
.btn-cancel:hover { color: var(--orange); }

/* RESPONSIVE */
@media(max-width: 1100px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }
@media(max-width: 768px) {
    .sidebar { width: 0; overflow: hidden; padding: 0; }
    .main { margin-left: 0; }
    .stat-grid { grid-template-columns: 1fr; }
    .toolbar { flex-direction: column; gap: 12px; align-items: stretch; }
    .tab-group { overflow-x: auto; }
    .content { padding: 20px; }
    .topbar { padding: 0 20px; }
}
</style>
</head>
<body>

<!-- ═══════════════════════════════════════════
     MODAL (Create / Edit)
     ═══════════════════════════════════════════ -->
<div class="modal-overlay <?= ($edit_data || $show_create) ? '' : 'hidden' ?>" id="modalAkun">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-subtitle">Master Akun</div>
            <h2 class="modal-title"><?= $edit_data ? 'Edit Akses Akun' : 'Tambah Karyawan Baru' ?></h2>
        </div>
        <div class="modal-body">
            <form method="POST">
                <?php if($edit_data): ?>
                    <input type="hidden" name="id_akun" value="<?= $edit_data['ID_Akun'] ?>">

                    <label class="modal-label">Username <span class="required">*</span></label>
                    <input type="text" name="username" class="modal-input" value="<?= $edit_data['Username'] ?>" required placeholder="Masukkan username">

                    <label class="modal-label">Email <span class="required">*</span></label>
                    <input type="email" name="email" class="modal-input" value="<?= $edit_data['Email'] ?>" required placeholder="email@hoopball.com">

                    <label class="modal-label">Password <span class="required">*</span></label>
                    <input type="text" name="password" class="modal-input" value="<?= $edit_data['Kata_Sandi'] ?>" required placeholder="Masukkan password">

                    <label class="modal-label">Role <span class="required">*</span></label>
                    <select name="role" class="modal-select" required>
                        <option value="1" <?= $edit_data['Role'] == 1 ? 'selected' : '' ?>>Manajer</option>
                        <option value="2" <?= $edit_data['Role'] == 2 ? 'selected' : '' ?>>Karyawan</option>
                        <option value="3" <?= $edit_data['Role'] == 3 ? 'selected' : '' ?>>Customer</option>
                    </select>

                    <button type="submit" name="update_akun" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan</button>
                <?php else: ?>
                    <label class="modal-label">Username <span class="required">*</span></label>
                    <input type="text" name="new_username" class="modal-input" required placeholder="Masukkan username karyawan">

                    <label class="modal-label">Email Karyawan <span class="required">*</span></label>
                    <input type="email" name="new_email" class="modal-input" required placeholder="email@hoopball.com">

                    <label class="modal-label">Password <span class="required">*</span></label>
                    <input type="password" name="new_password" class="modal-input" required minlength="3" placeholder="Minimal 3 karakter">

                    <button type="submit" name="create_karyawan" class="btn-save"><i class="fa-solid fa-plus"></i> Buat Akun Karyawan</button>
                <?php endif; ?>
                <a href="akun.php?role=<?= $current_filter ?>" class="btn-cancel">Batal</a>
            </form>
        </div>
    </div>
</div>

<!-- ═══════════════════════════════════════════
     SIDEBAR - SAMA PERSIS DENGAN DASHBOARD PEMILIK
     ═══════════════════════════════════════════ -->
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
        <a href="../view_pemilik.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div> Dashboard
        </a>
        <a href="akun.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-user-shield"></i></div> Kelola Akun
        </a>
        <a href="karyawan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-user-tie"></i></div> Kelola Karyawan
        </a>
        <a href="alat.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-truck-fast"></i></div> Kelola Alat
        </a>
        <a href="../laporan/omzet.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-chart-line"></i></div> Laporan & Omzet
        </a>
    </nav>

    <div class="sb-section-label">Akun</div>
    <a href="../profile.php" class="sb-link">
        <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div> Profil Saya
    </a>

    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar"><i class="fa-solid fa-user"></i></div>
            <div>
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama_user)) ?></div>
                <div class="sb-user-role">PEMILIK</div>
            </div>
            <a href="../logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- ═══════════════════════════════════════════
     MAIN & TOPBAR - SAMA PERSIS DENGAN DASHBOARD
     ═══════════════════════════════════════════ -->
<main class="main">
    <header class="topbar">
        <div class="topbar-left">
            <div class="topbar-title">Kelola Data Akun</div>
            <div class="topbar-breadcrumb">Dashboard / Manajemen Akun</div>
        </div>
        <div class="topbar-right">
            <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a href="#" class="topbar-btn"><i class="fa-solid fa-bell"></i><span class="notif-dot"></span></a>
            <div class="dropdown-wrap">
                <div class="topbar-user">
                    <div class="t-avatar"><i class="fa-solid fa-user"></i></div>
                    <div>
                        <div class="t-name"><?= strtoupper(htmlspecialchars($nama_user)) ?></div>
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
        <!-- PAGE HEADER -->
        <div class="page-header">
            <div>
                <div class="page-title-tag"></div>
                <div class="page-title">Master Akun</div>
            </div>
        </div>

        <!-- STAT CARDS -->
        <div class="stat-grid">
            <div class="stat-card sc-orange">
                <div class="stat-header">
                    <div class="stat-icon-wrap si-orange"><i class="fa-solid fa-users"></i></div>
                    <div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Total</div>
                </div>
                <div class="stat-value"><?= $total_count ?></div>
                <div class="stat-label">Total Akun</div>
                <div class="stat-sublabel">Semua role</div>
            </div>
            <div class="stat-card sc-green">
                <div class="stat-header">
                    <div class="stat-icon-wrap si-green"><i class="fa-solid fa-circle-check"></i></div>
                    <div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> Aktif</div>
                </div>
                <div class="stat-value"><?= $active_count ?></div>
                <div class="stat-label">Akun Aktif</div>
                <div class="stat-sublabel">Dapat mengakses sistem</div>
            </div>
            <div class="stat-card sc-red">
                <div class="stat-header">
                    <div class="stat-icon-wrap si-red"><i class="fa-solid fa-ban"></i></div>
                    <div class="stat-trend trend-down"><i class="fa-solid fa-arrow-down"></i> Nonaktif</div>
                </div>
                <div class="stat-value"><?= $suspended_count ?></div>
                <div class="stat-label">Suspended</div>
                <div class="stat-sublabel">Tidak dapat login</div>
            </div>
        </div>

        <!-- TOOLBAR (FILTER + SEARCH) -->
        <div class="toolbar">
            <div class="tab-group">
                <a href="akun.php?role=all"      class="tab-item <?= $current_filter == 'all' ? 'active' : '' ?>">Semua</a>
                <a href="akun.php?role=manajer"  class="tab-item <?= $current_filter == 'manajer' ? 'active' : '' ?>">Manajer</a>
                <a href="akun.php?role=karyawan" class="tab-item <?= $current_filter == 'karyawan' ? 'active' : '' ?>">Karyawan</a>
                <a href="akun.php?role=customer" class="tab-item <?= $current_filter == 'customer' ? 'active' : '' ?>">Customer</a>
            </div>
            <div style="display:flex; align-items:center; gap:12px;">
                <div class="search-wrap">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" class="search-input" id="src" placeholder="Cari username/email..." onkeyup="searchTable()">
                </div>
                <?php if($current_filter === 'karyawan'): ?>
                    <a href="akun.php?role=karyawan&create=1" class="btn-add"><i class="fa-solid fa-plus"></i> Tambah</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- TABLE -->
        <div class="table-wrap">
            <table id="tbl">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>ID Akun</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Password</th>
                        <th>Hak Akses</th>
                        <th style="text-align:right;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                        $is_active = $row['Status_Akun'] == 1;
                        $is_customer = $row['Role'] == 3;
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:8px;">
                                <span class="status-dot <?= $is_active ? 'status-active' : 'status-inactive' ?>"></span>
                                <span class="status-text <?= $is_active ? 'status-text-active' : 'status-text-inactive' ?>"><?= $is_active ? 'Aktif' : 'Nonaktif' ?></span>
                            </div>
                        </td>
                        <td class="id-akun"><?= $row['ID_Akun'] ?></td>
                        <td class="username-text"><?= htmlspecialchars($row['Username'] ?? '-') ?></td>
                        <td class="email-text"><?= htmlspecialchars($row['Email']) ?></td>
                        <td>
                            <?php if ($is_customer): ?>
                                <!-- Customer: just dots, no eye -->
                                <span class="password-customer">••••••</span>
                            <?php else: ?>
                                <!-- Karyawan/Manager: dots + eye toggle -->
                                <div class="password-mask-table">
                                    <span class="password-dots-table">••••••</span>
                                    <button type="button" class="btn-toggle-pass" onclick="togglePass(this, '<?= addslashes($row['Kata_Sandi']) ?>')" title="Lihat password">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="role-badge badge-<?= $row['Role'] ?>"><?= $role_label_map[$row['Role']] ?></span>
                        </td>
                        <td style="text-align:right;">
                            <div class="action-group">
                                <?php if (!$is_customer): ?>
                                    <!-- Edit button for Karyawan & Manager only -->
                                    <a href="?role=<?= $current_filter ?>&edit_id=<?= $row['ID_Akun'] ?>" class="btn-action btn-edit" title="Edit Akun">
                                        <i class="fa-solid fa-pen-to-square"></i> Edit
                                    </a>
                                <?php endif; ?>
                                <!-- Toggle switch for all roles -->
                                <label class="toggle-switch" title="<?= $is_active ? 'Nonaktifkan' : 'Aktifkan' ?> akun">
                                    <input type="checkbox" <?= $is_active ? 'checked' : '' ?> onchange="confirmToggle('<?= $row['ID_Akun'] ?>', <?= $row['Status_Akun'] ?>)">
                                    <span class="toggle-slider"></span>
                                </label>
                                <!-- Hard Delete button for all roles -->
                                <button type="button" class="btn-action btn-delete" onclick="confirmDelete('<?= $row['ID_Akun'] ?>', '<?= htmlspecialchars($row['Username']) ?>')" title="Hapus Permanen">
                                    <i class="fa-solid fa-trash-can"></i> Hapus
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<script>
function searchTable() {
    var input = document.getElementById('src').value.toUpperCase();
    var rows = document.getElementById('tbl').getElementsByTagName('tr');
    for (var i = 1; i < rows.length; i++) {
        var tdUser = rows[i].getElementsByTagName('td')[2];
        var tdEmail = rows[i].getElementsByTagName('td')[3];
        var match = false;
        if (tdUser && tdUser.textContent.toUpperCase().indexOf(input) > -1) match = true;
        if (tdEmail && tdEmail.textContent.toUpperCase().indexOf(input) > -1) match = true;
        rows[i].style.display = match ? '' : 'none';
    }
}

function togglePass(btn, realPass) {
    var dots = btn.parentElement.querySelector('.password-dots-table');
    var icon = btn.querySelector('i');
    if (dots.textContent === '••••••') {
        dots.textContent = realPass;
        dots.style.letterSpacing = 'normal';
        dots.style.fontSize = '13px';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        dots.textContent = '••••••';
        dots.style.letterSpacing = '3px';
        dots.style.fontSize = '16px';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function confirmToggle(id, current) {
    const act = (current == 1) ? 'nonaktifkan' : 'aktifkan';
    const icon = (current == 1) ? 'warning' : 'question';
    Swal.fire({
        title: 'Konfirmasi',
        text: 'Apakah Anda yakin ingin ' + act + ' akun ini?',
        icon: icon,
        showCancelButton: true,
        confirmButtonText: 'Ya, ' + act + '!',
        confirmButtonColor: '#FF4500',
        cancelButtonText: 'Batal',
        cancelButtonColor: '#6B7280',
        reverseButtons: true
    }).then((result) => {
        if(result.isConfirmed) {
            window.location.href = `?role=<?= $current_filter ?>&toggle_id=${id}&s=${current}`;
        } else {
            var checkbox = document.querySelector('input[onchange*="' + id + '"');
            if (checkbox) checkbox.checked = !checkbox.checked;
        }
    });
}

function confirmDelete(id, username) {
    Swal.fire({
        title: 'Hapus Akun Permanen?',
        html: `Akun <strong style="color:#FF4500;">${username}</strong> (${id}) akan dihapus <strong style="color:#DC2626;">secara permanen</strong>!<br><br>Data yang terkait (Karyawan/Customer) juga akan terhapus.`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Ya, Hapus Permanen!',
        confirmButtonColor: '#DC2626',
        cancelButtonText: 'Batal',
        cancelButtonColor: '#6B7280',
        reverseButtons: true,
        customClass: {
            confirmButton: 'swal2-confirm-btn',
            cancelButton: 'swal2-cancel-btn'
        }
    }).then((result) => {
        if(result.isConfirmed) {
            window.location.href = `?role=<?= $current_filter ?>&delete_id=${id}`;
        }
    });
}

const urlParams = new URLSearchParams(window.location.search);
if(urlParams.get('status')){
    Swal.fire({
        icon: urlParams.get('status'),
        title: urlParams.get('msg'),
        showConfirmButton: false,
        timer: 2500,
        timerProgressBar: true,
        toast: true,
        position: 'top-end'
    });
    window.history.replaceState({}, '', window.location.pathname + "?role=<?= $current_filter ?>");
}
</script>
</body>
</html>