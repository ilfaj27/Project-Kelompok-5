<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'karyawan') {
    echo "<script>alert('Akses Ditolak!'); window.location='../login/login.php';</script>";
    exit();
}

$nama = $_SESSION['nama'];
$role = $_SESSION['role'];
$jabatan = $_SESSION['jabatan'] ?? 'Karyawan';
$id_karyawan = $_SESSION['id_karyawan'] ?? '';

// FIX: Ambil foto profil dari database dengan kolom Photo_Profile
$profile_photo = '';
if (!empty($id_karyawan)) {
    $stmt_photo = sqlsrv_query($conn, "SELECT Photo_Profile FROM Karyawan WHERE ID_Karyawan = ?", array($id_karyawan));
    if ($stmt_photo !== false) {
        $row_photo = sqlsrv_fetch_array($stmt_photo, SQLSRV_FETCH_ASSOC);
        if ($row_photo && !empty($row_photo['Photo_Profile'])) {
            $profile_photo = $row_photo['Photo_Profile'];
            $_SESSION['Photo_Profile'] = $profile_photo;
        }
    }
}
if (empty($profile_photo)) {
    $profile_photo = $_SESSION['Photo_Profile'] ?? '';
}

// FIX: Sesuaikan path foto untuk folder transaksi/
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

function safeQuery($conn, $sql, $params = array()) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        error_log("SQL Error: " . print_r($errors, true));
        // DEBUG: Uncomment untuk melihat error di browser
        // echo "<pre>SQL Error: " . print_r($errors, true) . "</pre>";
        return false;
    }
    return $stmt;
}
function safeFetch($stmt) {
    if ($stmt === false || $stmt === null) return false;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

// ============================================================================
// PROSES KONFIRMASI PEMBAYARAN (KARYAWAN)
// ============================================================================
$notif_status = '';
$notif_msg = '';

if (isset($_POST['konfirmasi_pembayaran']) && isset($_POST['id_langganan'])) {
    $id_langganan = intval($_POST['id_langganan']);
    $id_karyawan_conf = intval($id_karyawan);
    $nama_karyawan = $_SESSION['nama'] ?? 'KARYAWAN';

    // Mulai transaksi
    if (sqlsrv_begin_transaction($conn) === false) {
        $notif_status = 'error';
        $notif_msg = 'Gagal memulai transaksi database.';
    } else {
        try {
            // 1. Ambil data langganan
            // FIX: Hapus T.Masa_Aktif_Hari karena kolom tidak ada
            $stmt_langganan = safeQuery($conn, 
                "SELECT L.*, T.Nama_Tipe 
                 FROM Langganan L 
                 INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe 
                 WHERE L.ID_Langganan = ? AND L.Status = 0", 
                array($id_langganan)
            );
            $data_langganan = safeFetch($stmt_langganan);

            if (!$data_langganan) {
                throw new Exception("Data langganan tidak ditemukan atau sudah dikonfirmasi.");
            }

            // 2. Update status langganan menjadi Aktif (1)
            $stmt_update = sqlsrv_query($conn,
                "UPDATE Langganan SET 
                    Status = 1, 
                    Modified_By = ?, 
                    Modified_Date = GETDATE()
                 WHERE ID_Langganan = ?",
                array($nama_karyawan, $id_langganan)
            );

            if ($stmt_update === false) {
                throw new Exception("Gagal mengupdate status langganan.");
            }

            // 3. Update status customer menjadi Member (1)
            $id_customer = $data_langganan['ID_Customer'];
            $stmt_update_customer = sqlsrv_query($conn,
                "UPDATE Customer SET 
                    Status = 1, 
                    Modified_By = ?, 
                    Modified_Date = GETDATE()
                 WHERE ID_Customer = ?",
                array($nama_karyawan, $id_customer)
            );

            if ($stmt_update_customer === false) {
                throw new Exception("Gagal mengupdate status customer.");
            }

            sqlsrv_commit($conn);
            $notif_status = 'success';
            $notif_msg = 'Pembayaran berhasil dikonfirmasi! Status member telah diaktifkan.';

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            $notif_status = 'error';
            $notif_msg = $e->getMessage();
        }
    }
}

// ============================================================================
// PROSES TOLAK PEMBAYARAN (KARYAWAN)
// ============================================================================
if (isset($_POST['tolak_pembayaran']) && isset($_POST['id_langganan'])) {
    $id_langganan = intval($_POST['id_langganan']);
    $alasan_penolakan = htmlspecialchars($_POST['alasan_penolakan'] ?? '');
    $nama_karyawan = $_SESSION['nama'] ?? 'KARYAWAN';

    if (sqlsrv_begin_transaction($conn) === false) {
        $notif_status = 'error';
        $notif_msg = 'Gagal memulai transaksi database.';
    } else {
        try {
            $stmt_update = sqlsrv_query($conn,
                "UPDATE Langganan SET 
                    Status = 3, 
                    Modified_By = ?, 
                    Modified_Date = GETDATE()
                 WHERE ID_Langganan = ? AND Status = 0",
                array($nama_karyawan, $id_langganan)
            );

            if ($stmt_update === false || sqlsrv_rows_affected($stmt_update) === 0) {
                throw new Exception("Data langganan tidak ditemukan atau sudah diproses.");
            }

            sqlsrv_commit($conn);
            $notif_status = 'success';
            $notif_msg = 'Pembayaran ditolak. Customer akan menerima notifikasi penolakan.';

        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            $notif_status = 'error';
            $notif_msg = $e->getMessage();
        }
    }
}

// ============================================================================
// AMBIL DATA LANGGANAN
// ============================================================================

// 1. Langganan Menunggu Konfirmasi (Status = 0)
// FIX: Hapus T.Masa_Aktif_Hari, gunakan 30 AS Masa_Aktif_Hari
$menunggu_list = [];
$q = safeQuery($conn, 
    "SELECT L.*, C.Nama_Customer, C.Email, C.No_Telepon, T.Nama_Tipe, T.Harga_Member, T.Potongan_Harga, 30 AS Masa_Aktif_Hari
     FROM Langganan L 
     INNER JOIN Customer C ON L.ID_Customer = C.ID_Customer
     INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe
     WHERE L.Status = 0
     ORDER BY L.Created_Date DESC"
);
if ($q !== false) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $menunggu_list[] = $row;
    }
}

// 2. Langganan Aktif (Status = 1)
// FIX: Hapus T.Masa_Aktif_Hari, gunakan 30 AS Masa_Aktif_Hari
$aktif_list = [];
$q = safeQuery($conn, 
    "SELECT L.*, C.Nama_Customer, C.Email, T.Nama_Tipe, T.Harga_Member, T.Potongan_Harga, 30 AS Masa_Aktif_Hari
     FROM Langganan L 
     INNER JOIN Customer C ON L.ID_Customer = C.ID_Customer
     INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe
     WHERE L.Status = 1
     ORDER BY L.Tanggal_Mulai DESC"
);
if ($q !== false) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $aktif_list[] = $row;
    }
}

// 3. Langganan Berakhir / Ditolak (Status = 2, 3)
$riwayat_list = [];
$q = safeQuery($conn, 
    "SELECT L.*, C.Nama_Customer, T.Nama_Tipe, T.Harga_Member, T.Potongan_Harga
     FROM Langganan L 
     INNER JOIN Customer C ON L.ID_Customer = C.ID_Customer
     INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe
     WHERE L.Status IN (2, 3)
     ORDER BY L.Modified_Date DESC"
);
if ($q !== false) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $riwayat_list[] = $row;
    }
}

// 4. Statistik
$total_menunggu = count($menunggu_list);
$total_aktif = count($aktif_list);
$total_berakhir = 0;
$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Langganan WHERE Status = 2");
$d = safeFetch($q); if ($d) $total_berakhir = $d['total'] ?? 0;

$total_ditolak = 0;
$q = safeQuery($conn, "SELECT COUNT(*) as total FROM Langganan WHERE Status = 3");
$d = safeFetch($q); if ($d) $total_ditolak = $d['total'] ?? 0;

$total_pendapatan = 0;
$q = safeQuery($conn, "SELECT ISNULL(SUM(Total_Bayar), 0) as total FROM Langganan WHERE Status = 1");
$d = safeFetch($q); if ($d) $total_pendapatan = $d['total'] ?? 0;

function rupiahFormat($n) { return 'Rp ' . number_format($n, 0, ',', '.'); }

function formatTanggal($tanggal) {
    if (empty($tanggal)) return '-';
    if (is_object($tanggal) && method_exists($tanggal, 'format')) {
        return $tanggal->format('d M Y');
    }
    return date('d M Y', strtotime($tanggal));
}

$status_labels = [
    0 => ['label' => 'Menunggu Konfirmasi', 'class' => 'sp-pending', 'icon' => 'fa-clock'],
    1 => ['label' => 'Aktif', 'class' => 'sp-active', 'icon' => 'fa-check-circle'],
    2 => ['label' => 'Berakhir', 'class' => 'sp-inactive', 'icon' => 'fa-flag-checkered'],
    3 => ['label' => 'Ditolak', 'class' => 'sp-inactive', 'icon' => 'fa-ban']
];

$metode_labels = [
    'Virtual Account' => ['icon' => 'fa-building-columns', 'color' => '#3B82F6'],
    'QRIS' => ['icon' => 'fa-qrcode', 'color' => '#8B5CF6']
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Kelola Langganan Member | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10);
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --purple: #8B5CF6; --purple-lt: rgba(139,92,246,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10);
    --yellow: #F59E0B; --yellow-lt: rgba(245,158,11,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --text-md: #374151; --muted: #6B7280; --bg: #F3F4F6;
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
.sb-link { display: flex; align-items: center; gap: 12px; color: #6B7280; text-decoration: none; padding: 10px 12px; border-radius: 10px; margin-bottom: 2px; font-size: 13px; font-weight: 600; transition: all .2s ease; position: relative; }
.sb-link .sb-icon-wrap { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 13px; transition: .2s; flex-shrink: 0; background: rgba(255,255,255,.04); }
.sb-link:hover { color: #E5E7EB; background: rgba(255,255,255,.04); }
.sb-link:hover .sb-icon-wrap { background: rgba(255,255,255,.08); }
.sb-link.active { color: #fff; background: var(--orange-lt); }
.sb-link.active .sb-icon-wrap { background: var(--orange); color: #fff; }
.sb-link .badge { margin-left: auto; background: var(--orange); color: #fff; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 20px; }
.sb-bottom { margin-top: auto; padding-top: 20px; }
.sb-user { display: flex; align-items: center; gap: 10px; background: rgba(255,255,255,.04); border-radius: 12px; padding: 12px; border: 1px solid rgba(255,255,255,.06); }
.sb-avatar { width: 36px; height: 36px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px; flex-shrink: 0; overflow: hidden; }
.sb-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; line-height: 1.1; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.sb-logout { margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 8px; }
.sb-logout:hover { color: var(--red); background: rgba(239,68,68,.1); }

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
.t-avatar { width: 32px; height: 32px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; overflow: hidden; }
.t-avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 50%; }
.t-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.1; }
.t-role { font-size: 10px; color: var(--orange); font-weight: 700; text-transform: uppercase; }
.t-chevron { color: var(--muted); font-size: 10px; margin-left: 4px; }
.dropdown-menu { display: none; position: absolute; right: 0; top: calc(100% + 8px); background: #fff; min-width: 200px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 15px 40px rgba(0,0,0,.12); overflow: hidden; padding: 8px 0; z-index: 999; }
.dropdown-wrap:hover .dropdown-menu { display: block; }
.dropdown-wrap.active .dropdown-menu { display: block; }
.dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-item i { font-size: 14px; width: 18px; text-align: center; }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

.content { padding: 32px 40px; flex: 1; }

/* Page Header */
.page-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; }
.page-title { font-family: 'Barlow Condensed', sans-serif; font-size: 28px; font-weight: 900; color: var(--text); letter-spacing: -.5px; }
.page-subtitle { font-size: 13px; color: var(--muted); margin-top: 4px; font-weight: 500; }

/* Stat Cards */
.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 28px; }
.stat-card { background: var(--card-bg); border-radius: 16px; padding: 22px 24px; border: 1px solid var(--border); position: relative; overflow: hidden; transition: all .2s ease; }
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,.08); }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 4px 0 0 4px; }
.sc-yellow::before { background: var(--yellow); }
.sc-green::before { background: var(--green); }
.sc-blue::before { background: var(--blue); }
.sc-purple::before { background: var(--purple); }
.stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.stat-icon-wrap { width: 44px; height: 44px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
.si-yellow { background: var(--yellow-lt); color: var(--yellow); }
.si-green { background: var(--green-lt); color: var(--green); }
.si-blue { background: var(--blue-lt); color: var(--blue); }
.si-purple { background: var(--purple-lt); color: var(--purple); }
.stat-value { font-family: 'Barlow Condensed', sans-serif; font-size: 30px; font-weight: 900; color: var(--text); line-height: 1; margin-bottom: 6px; }
.stat-label { font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }

/* Tabs */
.tab-nav { display: flex; gap: 8px; margin-bottom: 24px; border-bottom: 2px solid var(--border); padding-bottom: 0; }
.tab-btn { padding: 12px 20px; border: none; background: none; font-family: 'Barlow', sans-serif; font-size: 13px; font-weight: 700; color: var(--muted); cursor: pointer; border-radius: 8px 8px 0 0; transition: all .2s; position: relative; }
.tab-btn:hover { color: var(--text); background: var(--border-lt); }
.tab-btn.active { color: var(--orange); background: var(--orange-lt); }
.tab-btn.active::after { content: ''; position: absolute; bottom: -2px; left: 0; width: 100%; height: 2px; background: var(--orange); }
.tab-btn .badge { margin-left: 6px; background: var(--orange); color: #fff; font-size: 10px; font-weight: 800; padding: 2px 8px; border-radius: 20px; }

/* Cards & Tables */
.card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; }
.card-title i { color: var(--orange); font-size: 14px; }
.card-body { padding: 20px 24px; }

.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 12px 16px; font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; border-bottom: 2px solid var(--border-lt); text-align: left; background: #FAFAFA; }
.data-table td { padding: 16px; font-size: 13px; border-bottom: 1px solid var(--border-lt); vertical-align: middle; }
.data-table tr:last-child td { border-bottom: none; }
.data-table tbody tr { transition: background .15s; }
.data-table tbody tr:hover td { background: #FAFAFA; }

.cell-name { font-weight: 700; color: var(--text); }
.cell-detail { font-size: 11px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.cell-email { font-size: 11px; color: var(--muted); }

.status-pill { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; display: inline-flex; align-items: center; gap: 5px; }
.sp-active { background: var(--green-lt); color: var(--green); }
.sp-inactive { background: var(--red-lt); color: var(--red); }
.sp-pending { background: var(--yellow-lt); color: #D97706; }

/* Action Buttons */
.btn-group { display: flex; gap: 8px; }
.btn { padding: 8px 14px; border-radius: 8px; font-family: 'Barlow', sans-serif; font-size: 12px; font-weight: 700; cursor: pointer; border: none; transition: all .2s; display: inline-flex; align-items: center; gap: 6px; }
.btn-sm { padding: 6px 12px; font-size: 11px; }
.btn-success { background: var(--green); color: #fff; }
.btn-success:hover { background: #059669; }
.btn-danger { background: var(--red); color: #fff; }
.btn-danger:hover { background: #DC2626; }
.btn-primary { background: var(--blue); color: #fff; }
.btn-primary:hover { background: #2563EB; }
.btn-outline { background: transparent; border: 1px solid var(--border); color: var(--text-md); }
.btn-outline:hover { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); }

/* Detail Modal */
.modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,.6); backdrop-filter: blur(4px); z-index: 2000; align-items: center; justify-content: center; padding: 20px; }
.modal-overlay.active { display: flex; }
.modal-box { background: #fff; border-radius: 20px; width: 100%; max-width: 560px; max-height: 90vh; overflow-y: auto; padding: 32px; animation: slideUp .3s ease-out; position: relative; }
@keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }
.modal-close { position: absolute; top: 20px; right: 20px; width: 36px; height: 36px; border-radius: 50%; background: var(--border-lt); border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--muted); font-size: 14px; transition: .2s; }
.modal-close:hover { background: var(--red-lt); color: var(--red); }
.modal-title { font-size: 20px; font-weight: 800; color: var(--text); margin-bottom: 24px; display: flex; align-items: center; gap: 10px; }
.modal-title i { color: var(--orange); }

.detail-row { display: flex; justify-content: space-between; align-items: center; padding: 12px 0; border-bottom: 1px solid var(--border-lt); }
.detail-row:last-child { border-bottom: none; }
.detail-label { font-size: 12px; color: var(--muted); font-weight: 600; }
.detail-value { font-size: 14px; font-weight: 700; color: var(--text); }
.detail-value.primary { color: var(--orange); }
.detail-value.green { color: var(--green); }
.detail-value.red { color: var(--red); }

.payment-proof-box { background: var(--border-lt); border-radius: 12px; padding: 20px; text-align: center; margin: 16px 0; border: 2px dashed var(--border); }
.payment-proof-box i { font-size: 48px; color: var(--muted); margin-bottom: 12px; }
.payment-proof-box p { font-size: 13px; color: var(--muted); font-weight: 600; }

/* Empty State */
.empty-state { text-align: center; padding: 60px 20px; }
.empty-state i { font-size: 48px; color: var(--border); margin-bottom: 16px; }
.empty-state h3 { font-size: 16px; font-weight: 800; color: var(--text); margin-bottom: 8px; }
.empty-state p { font-size: 13px; color: var(--muted); }

/* Swal Toast */
.swal-toast { border-radius: 12px !important; font-family: 'Barlow', sans-serif !important; }

html, body { scrollbar-width: none; -ms-overflow-style: none; }
html::-webkit-scrollbar, body::-webkit-scrollbar { display: none; }

@media(max-width: 768px) {
    .sidebar { width: 0; overflow: hidden; padding: 0; }
    .main { margin-left: 0; }
    .content { padding: 20px; }
    .stat-grid { grid-template-columns: 1fr 1fr; }
    .tab-nav { overflow-x: auto; flex-wrap: nowrap; }
}
</style>
</head>
<body>

<aside class="sidebar">
    <a href="../dashboard/view_admin.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div><div class="sb-brand-name">HOOP BALL</div><div class="sb-brand-sub">Sistem Managemen</div></div>
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
        <a href="../transaksi/booking.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>Kelola Booking
        </a>
        <a href="../transaksi/langganan.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-crown"></i></div>Kelola Langganan
            <?php if($total_menunggu > 0): ?><span class="badge"><?= $total_menunggu ?></span><?php endif; ?>
        </a>
        <a href="../transaksi/pembelian.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-cart-shopping"></i></div>Kelola Pembelian Alat
        </a>
        <a href="../transaksi/pembatalan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-ban"></i></div>Kelola Pembatalan
        </a>
    </nav>

    <div class="sb-section-label">Akun</div>
    <a href="../profile/profile.php" class="sb-link">
        <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>Profil Saya
    </a>

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
        <div class="topbar-title">Kelola Langganan Member</div>
        <div class="topbar-breadcrumb">Transaksi / Kelola Langganan</div>
    </div>
    <div class="topbar-right">
        <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
        <a href="#" class="topbar-btn"><i class="fa-solid fa-bell"></i><?php if($total_menunggu > 0): ?><span class="notif-dot"></span><?php endif; ?></a>
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
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Kelola Langganan Member</h1>
            <p class="page-subtitle">Konfirmasi pembayaran dan kelola status langganan member customer.</p>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="stat-grid">
        <div class="stat-card sc-yellow">
            <div class="stat-header"><div class="stat-icon-wrap si-yellow"><i class="fa-solid fa-clock"></i></div></div>
            <div class="stat-value"><?= $total_menunggu ?></div>
            <div class="stat-label">Menunggu Konfirmasi</div>
        </div>
        <div class="stat-card sc-green">
            <div class="stat-header"><div class="stat-icon-wrap si-green"><i class="fa-solid fa-check-circle"></i></div></div>
            <div class="stat-value"><?= $total_aktif ?></div>
            <div class="stat-label">Member Aktif</div>
        </div>
        <div class="stat-card sc-blue">
            <div class="stat-header"><div class="stat-icon-wrap si-blue"><i class="fa-solid fa-money-bill-wave"></i></div></div>
            <div class="stat-value"><?= rupiahFormat($total_pendapatan) ?></div>
            <div class="stat-label">Total Pendapatan</div>
        </div>
        <div class="stat-card sc-purple">
            <div class="stat-header"><div class="stat-icon-wrap si-purple"><i class="fa-solid fa-crown"></i></div></div>
            <div class="stat-value"><?= $total_berakhir + $total_ditolak ?></div>
            <div class="stat-label">Riwayat Langganan</div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tab-nav">
        <button class="tab-btn active" onclick="switchTab('menunggu', this)">
            <i class="fa-solid fa-clock"></i> Menunggu Konfirmasi
            <?php if($total_menunggu > 0): ?><span class="badge"><?= $total_menunggu ?></span><?php endif; ?>
        </button>
        <button class="tab-btn" onclick="switchTab('aktif', this)">
            <i class="fa-solid fa-check-circle"></i> Member Aktif
        </button>
        <button class="tab-btn" onclick="switchTab('riwayat', this)">
            <i class="fa-solid fa-history"></i> Riwayat
        </button>
    </div>

    <!-- TAB 1: MENUNGGU KONFIRMASI -->
    <div id="tab-menunggu" class="tab-content">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-clock"></i> Daftar Menunggu Konfirmasi</div>
                <span class="status-pill sp-pending"><?= count($menunggu_list) ?> Data</span>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Tipe Member</th>
                            <th>Total Bayar</th>
                            <th>Metode</th>
                            <th>Tanggal Daftar</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(count($menunggu_list) > 0): ?>
                        <?php foreach($menunggu_list as $m): 
                            $metode = $metode_labels[$m['Metode_Pembayaran']] ?? ['icon' => 'fa-money-bill', 'color' => '#6B7280'];
                        ?>
                        <tr>
                            <td><span class="cell-name">#<?= $m['ID_Langganan'] ?></span></td>
                            <td>
                                <div class="cell-name"><?= htmlspecialchars($m['Nama_Customer']) ?></div>
                                <div class="cell-email"><?= htmlspecialchars($m['Email'] ?? '-') ?></div>
                            </td>
                            <td>
                                <span class="cell-name"><?= htmlspecialchars($m['Nama_Tipe']) ?></span>
                                <div class="cell-detail">Potongan <?= rupiahFormat($m['Potongan_Harga']) ?>/booking</div>
                            </td>
                            <td><strong style="color: var(--orange);"><?= rupiahFormat($m['Total_Bayar']) ?></strong></td>
                            <td>
                                <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600;">
                                    <i class="fa-solid <?= $metode['icon'] ?>" style="color: <?= $metode['color'] ?>"></i>
                                    <?= htmlspecialchars($m['Metode_Pembayaran']) ?>
                                </span>
                            </td>
                            <td><?= formatTanggal($m['Created_Date']) ?></td>
                            <td>
                                <div class="btn-group">
                                    <button class="btn btn-success btn-sm" onclick="konfirmasiPembayaran(<?= $m['ID_Langganan'] ?>, '<?= htmlspecialchars($m['Nama_Customer']) ?>', '<?= htmlspecialchars($m['Nama_Tipe']) ?>', '<?= rupiahFormat($m['Total_Bayar']) ?>')">
                                        <i class="fa-solid fa-check"></i> Konfirmasi
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="tolakPembayaran(<?= $m['ID_Langganan'] ?>, '<?= htmlspecialchars($m['Nama_Customer']) ?>')">
                                        <i class="fa-solid fa-xmark"></i> Tolak
                                    </button>
                                    <button class="btn btn-outline btn-sm" onclick="lihatDetail(<?= htmlspecialchars(json_encode($m)) ?>)">
                                        <i class="fa-solid fa-eye"></i> Detail
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <i class="fa-solid fa-inbox"></i>
                                <h3>Tidak Ada Data Menunggu</h3>
                                <p>Semua langganan sudah dikonfirmasi atau tidak ada pendaftaran baru.</p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: MEMBER AKTIF -->
    <div id="tab-aktif" class="tab-content" style="display:none;">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-check-circle"></i> Member Aktif</div>
                <span class="status-pill sp-active"><?= count($aktif_list) ?> Data</span>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Tipe Member</th>
                            <th>Tanggal Mulai</th>
                            <th>Tanggal Selesai</th>
                            <th>Sisa Hari</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(count($aktif_list) > 0): ?>
                        <?php foreach($aktif_list as $a): 
                            $tgl_selesai = is_object($a['Tanggal_Selesai']) ? $a['Tanggal_Selesai']->format('Y-m-d') : $a['Tanggal_Selesai'];
                            $sisa_hari = ceil((strtotime($tgl_selesai) - time()) / 86400);
                            $sisa_text = $sisa_hari > 0 ? $sisa_hari . ' hari' : 'Hari ini berakhir';
                            $sisa_class = $sisa_hari <= 3 ? 'red' : 'green';
                        ?>
                        <tr>
                            <td><span class="cell-name">#<?= $a['ID_Langganan'] ?></span></td>
                            <td>
                                <div class="cell-name"><?= htmlspecialchars($a['Nama_Customer']) ?></div>
                                <div class="cell-email"><?= htmlspecialchars($a['Email'] ?? '-') ?></div>
                            </td>
                            <td>
                                <span class="cell-name"><?= htmlspecialchars($a['Nama_Tipe']) ?></span>
                                <div class="cell-detail"><?= rupiahFormat($a['Potongan_Harga']) ?>/booking</div>
                            </td>
                            <td><?= formatTanggal($a['Tanggal_Mulai']) ?></td>
                            <td><?= formatTanggal($a['Tanggal_Selesai']) ?></td>
                            <td><span class="detail-value <?= $sisa_class ?>"><?= $sisa_text ?></span></td>
                            <td><span class="status-pill sp-active"><i class="fa-solid fa-check-circle"></i> Aktif</span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <i class="fa-solid fa-inbox"></i>
                                <h3>Tidak Ada Member Aktif</h3>
                                <p>Belum ada langganan member yang aktif saat ini.</p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 3: RIWAYAT -->
    <div id="tab-riwayat" class="tab-content" style="display:none;">
        <div class="card">
            <div class="card-header">
                <div class="card-title"><i class="fa-solid fa-history"></i> Riwayat Langganan</div>
                <span class="status-pill sp-inactive"><?= count($riwayat_list) ?> Data</span>
            </div>
            <div style="overflow-x:auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Tipe Member</th>
                            <th>Total Bayar</th>
                            <th>Metode</th>
                            <th>Status</th>
                            <th>Tanggal Update</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if(count($riwayat_list) > 0): ?>
                        <?php foreach($riwayat_list as $r): 
                            $status = $status_labels[$r['Status']] ?? $status_labels[0];
                            $metode = $metode_labels[$r['Metode_Pembayaran']] ?? ['icon' => 'fa-money-bill', 'color' => '#6B7280'];
                        ?>
                        <tr>
                            <td><span class="cell-name">#<?= $r['ID_Langganan'] ?></span></td>
                            <td><div class="cell-name"><?= htmlspecialchars($r['Nama_Customer']) ?></div></td>
                            <td><span class="cell-name"><?= htmlspecialchars($r['Nama_Tipe']) ?></span></td>
                            <td><strong style="color: var(--orange);"><?= rupiahFormat($r['Total_Bayar']) ?></strong></td>
                            <td>
                                <span style="display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600;">
                                    <i class="fa-solid <?= $metode['icon'] ?>" style="color: <?= $metode['color'] ?>"></i>
                                    <?= htmlspecialchars($r['Metode_Pembayaran']) ?>
                                </span>
                            </td>
                            <td><span class="status-pill <?= $status['class'] ?>"><i class="fa-solid <?= $status['icon'] ?>"></i> <?= $status['label'] ?></span></td>
                            <td><?= formatTanggal($r['Modified_Date']) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <i class="fa-solid fa-inbox"></i>
                                <h3>Tidak Ada Riwayat</h3>
                                <p>Belum ada riwayat langganan yang berakhir atau ditolak.</p>
                            </div>
                        </td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</main>

<!-- MODAL DETAIL -->
<div class="modal-overlay" id="detailModal">
    <div class="modal-box">
        <button class="modal-close" onclick="tutupModal('detailModal')"><i class="fa-solid fa-xmark"></i></button>
        <h3 class="modal-title"><i class="fa-solid fa-file-invoice"></i> Detail Langganan</h3>
        <div id="detailContent"></div>
    </div>
</div>

<!-- FORM KONFIRMASI (HIDDEN) -->
<form id="formKonfirmasi" method="POST" style="display:none;">
    <input type="hidden" name="id_langganan" id="inputIdLangganan">
    <input type="hidden" name="konfirmasi_pembayaran" value="1">
</form>

<!-- FORM TOLAK (HIDDEN) -->
<form id="formTolak" method="POST" style="display:none;">
    <input type="hidden" name="id_langganan" id="inputIdTolak">
    <input type="hidden" name="alasan_penolakan" id="inputAlasanTolak">
    <input type="hidden" name="tolak_pembayaran" value="1">
</form>

<script>
// Tab Switching
function switchTab(tabName, btn) {
    document.querySelectorAll('.tab-content').forEach(t => t.style.display = 'none');
    document.getElementById('tab-' + tabName).style.display = 'block';
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
}

// Konfirmasi Pembayaran
function konfirmasiPembayaran(id, nama, tipe, harga) {
    Swal.fire({
        title: 'Konfirmasi Pembayaran?',
        html: '<div style="text-align:left; font-size:14px;">' +
              '<p><strong>Customer:</strong> ' + nama + '</p>' +
              '<p><strong>Tipe Member:</strong> ' + tipe + '</p>' +
              '<p><strong>Total Bayar:</strong> <span style="color:#FF4500; font-weight:800;">' + harga + '</span></p>' +
              '<p style="margin-top:12px; color:#6B7280; font-size:12px;">Pastikan pembayaran sudah diterima sebelum mengkonfirmasi.</p>' +
              '</div>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10B981',
        cancelButtonColor: '#6B7280',
        confirmButtonText: '<i class="fa-solid fa-check"></i> Ya, Konfirmasi',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('inputIdLangganan').value = id;
            document.getElementById('formKonfirmasi').submit();
        }
    });
}

// Tolak Pembayaran
function tolakPembayaran(id, nama) {
    Swal.fire({
        title: 'Tolak Pembayaran?',
        html: '<div style="text-align:left; font-size:14px;">' +
              '<p><strong>Customer:</strong> ' + nama + '</p>' +
              '<p style="margin-top:8px; color:#EF4444; font-size:12px;">Customer akan menerima notifikasi bahwa pembayaran ditolak.</p>' +
              '</div>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: '<i class="fa-solid fa-xmark"></i> Ya, Tolak',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('inputIdTolak').value = id;
            document.getElementById('inputAlasanTolak').value = 'Pembayaran tidak valid';
            document.getElementById('formTolak').submit();
        }
    });
}

// Lihat Detail
function lihatDetail(data) {
    const content = document.getElementById('detailContent');
    const tglMulai = data.Tanggal_Mulai ? new Date(data.Tanggal_Mulai).toLocaleDateString('id-ID', {day:'numeric', month:'short', year:'numeric'}) : '-';
    const tglSelesai = data.Tanggal_Selesai ? new Date(data.Tanggal_Selesai).toLocaleDateString('id-ID', {day:'numeric', month:'short', year:'numeric'}) : '-';
    const tglDaftar = data.Created_Date ? new Date(data.Created_Date).toLocaleDateString('id-ID', {day:'numeric', month:'short', year:'numeric'}) : '-';

    content.innerHTML = `
        <div class="detail-row"><span class="detail-label">ID Langganan</span><span class="detail-value">#${data.ID_Langganan}</span></div>
        <div class="detail-row"><span class="detail-label">Customer</span><span class="detail-value">${data.Nama_Customer}</span></div>
        <div class="detail-row"><span class="detail-label">Email</span><span class="detail-value">${data.Email || '-'}</span></div>
        <div class="detail-row"><span class="detail-label">No. Telepon</span><span class="detail-value">${data.No_Telepon || '-'}</span></div>
        <div class="detail-row"><span class="detail-label">Tipe Member</span><span class="detail-value primary">${data.Nama_Tipe}</span></div>
        <div class="detail-row"><span class="detail-label">Harga Member</span><span class="detail-value primary">Rp ${parseInt(data.Harga_Member).toLocaleString('id-ID')}</span></div>
        <div class="detail-row"><span class="detail-label">Potongan Harga</span><span class="detail-value green">Rp ${parseInt(data.Potongan_Harga).toLocaleString('id-ID')}/booking</span></div>
        <div class="detail-row"><span class="detail-label">Masa Aktif</span><span class="detail-value">${data.Masa_Aktif_Hari || 30} hari</span></div>
        <div class="detail-row"><span class="detail-label">Total Bayar</span><span class="detail-value primary">Rp ${parseInt(data.Total_Bayar).toLocaleString('id-ID')}</span></div>
        <div class="detail-row"><span class="detail-label">Metode Pembayaran</span><span class="detail-value">${data.Metode_Pembayaran}</span></div>
        <div class="detail-row"><span class="detail-label">Tanggal Daftar</span><span class="detail-value">${tglDaftar}</span></div>
        <div class="detail-row"><span class="detail-label">Tanggal Mulai</span><span class="detail-value">${tglMulai}</span></div>
        <div class="detail-row"><span class="detail-label">Tanggal Selesai</span><span class="detail-value">${tglSelesai}</span></div>
        <div class="payment-proof-box">
            <i class="fa-solid fa-receipt"></i>
            <p>Bukti Pembayaran</p>
            <p style="font-size:11px; margin-top:4px;">Customer membayar via ${data.Metode_Pembayaran}</p>
        </div>
    `;
    document.getElementById('detailModal').classList.add('active');
}

function tutupModal(id) {
    document.getElementById(id).classList.remove('active');
}

// Tutup modal klik luar
window.addEventListener('click', function(e) {
    if (e.target.classList.contains('modal-overlay')) {
        e.target.classList.remove('active');
    }
});

// URL Parameter Notification
const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
const msg = urlParams.get('msg');

if (status && msg) {
    const isSuccess = status === 'success';
    Swal.fire({
        icon: isSuccess ? 'success' : 'error',
        title: isSuccess ? 'Berhasil!' : 'Gagal!',
        text: msg,
        timer: 5000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end',
        timerProgressBar: true,
        showCloseButton: true,
        background: '#ffffff',
        color: '#1c1c1e',
        iconColor: isSuccess ? '#10B981' : '#EF4444',
        customClass: { popup: 'swal-toast' }
    });
    window.history.replaceState({}, document.title, window.location.pathname);
}
</script>
</body>
</html>