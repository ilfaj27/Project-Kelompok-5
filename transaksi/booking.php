<?php
session_start();
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
if (!empty($profile_photo) && !file_exists($profile_photo)) {
    $profile_photo = '';
}

// ============================================================================
// STATUS BOOKING
// 0 = Menunggu Konfirmasi (Customer sudah bayar, tunggu karyawan cek)
// 1 = Berhasil (Sudah Dikonfirmasi Karyawan)
// 2 = Selesai
// 3 = Dibatalkan
// ============================================================================
$status_labels = [
    0 => ['label' => 'Menunggu', 'class' => 'sp-pending', 'icon' => 'fa-clock'],
    1 => ['label' => 'Berhasil', 'class' => 'sp-active', 'icon' => 'fa-check-circle'],
    2 => ['label' => 'Selesai', 'class' => 'sp-success', 'icon' => 'fa-flag-checkered'],
    3 => ['label' => 'Dibatalkan', 'class' => 'sp-inactive', 'icon' => 'fa-ban']
];

// ============================================================================
// PROSES KONFIRMASI PEMBAYARAN (KARYAWAN)
// ============================================================================
if (isset($_POST['konfirmasi_bayar'])) {
    $id_booking = $_POST['id_booking'];

    $stmt = sqlsrv_query($conn, 
        "UPDATE Booking SET Status = 1, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Booking = ? AND Status = 0",
        array($nama, $id_booking)
    );

    if ($stmt) {
        header("Location: booking.php?status=success&msg=Pembayaran berhasil dikonfirmasi. Booking status: Berhasil.");
        exit();
    } else {
        header("Location: booking.php?status=error&msg=Gagal mengkonfirmasi pembayaran.");
        exit();
    }
}

// ============================================================================
// PROSES UPDATE STATUS SELESAI
// ============================================================================
if (isset($_POST['selesai_booking'])) {
    $id_booking = $_POST['id_booking'];

    $stmt = sqlsrv_query($conn, 
        "UPDATE Booking SET Status = 2, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Booking = ? AND Status = 1",
        array($nama, $id_booking)
    );

    if ($stmt) {
        header("Location: booking.php?status=success&msg=Booking telah diselesaikan.");
        exit();
    } else {
        header("Location: booking.php?status=error&msg=Gagal menyelesaikan booking.");
        exit();
    }
}

// ============================================================================
// PROSES PEMBATALAN BOOKING (REFUND 50%)
// ============================================================================
if (isset($_POST['batal_booking'])) {
    $id_booking = $_POST['id_booking'];
    $alasan = $_POST['alasan_batal'];

    $q_booking = sqlsrv_query($conn, 
        "SELECT B.*, J.ID_Lapangan FROM Booking B INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal WHERE B.ID_Booking = ?",
        array($id_booking)
    );
    $booking_data = sqlsrv_fetch_array($q_booking, SQLSRV_FETCH_ASSOC);

    if ($booking_data) {
        $total_bayar = (float)$booking_data['Total_Bayar'];
        $biaya_batal = $total_bayar * 0.5;
        $nominal_refund = $total_bayar * 0.5;
        $id_jadwal = $booking_data['ID_Jadwal'];
        $metode_refund = $booking_data['Metode_Pembayaran'];

        sqlsrv_query($conn, 
            "UPDATE Booking SET Status = 3, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Booking = ?",
            array($nama, $id_booking)
        );

        sqlsrv_query($conn, 
            "INSERT INTO Pembatalan_Booking (ID_Booking, ID_Karyawan, Tanggal_Batal, Alasan, Biaya_Batal, Nominal_Refund, Metode_Refund, Status, Created_By, Created_Date) 
             VALUES (?, ?, GETDATE(), ?, ?, ?, ?, 1, ?, GETDATE())",
            array($id_booking, $id_karyawan, $alasan, $biaya_batal, $nominal_refund, $metode_refund, $nama)
        );

        sqlsrv_query($conn, "UPDATE Jadwal SET Status = 1 WHERE ID_Jadwal = ?", array($id_jadwal));

        header("Location: booking.php?status=success&msg=Booking dibatalkan. Refund 50% (" . number_format($nominal_refund, 0, ',', '.') . ") dikembalikan via $metode_refund.");
        exit();
    } else {
        header("Location: booking.php?status=error&msg=Data booking tidak ditemukan.");
        exit();
    }
}

// ============================================================================
// AMBIL DATA BOOKING
// ============================================================================
$filter_status = isset($_GET['filter_status']) ? $_GET['filter_status'] : '';
$filter_customer = isset($_GET['filter_customer']) ? $_GET['filter_customer'] : '';
$filter_tanggal = isset($_GET['filter_tanggal']) ? $_GET['filter_tanggal'] : '';

$sql_where = "WHERE 1=1";
$params = [];

if ($filter_status !== '' && $filter_status !== 'all') {
    $sql_where .= " AND B.Status = ?";
    $params[] = (int)$filter_status;
}
if (!empty($filter_customer)) {
    $sql_where .= " AND C.Nama_Customer LIKE ?";
    $params[] = "%$filter_customer%";
}
if (!empty($filter_tanggal)) {
    $sql_where .= " AND CAST(B.Tanggal_Booking AS DATE) = ?";
    $params[] = $filter_tanggal;
}

$sql_booking = "SELECT B.ID_Booking, B.ID_Customer, B.ID_Karyawan, B.ID_Jadwal, B.ID_Promo, 
                       B.Tanggal_Booking, B.Metode_Pembayaran, B.Total_Bayar, B.Status,
                       B.Created_Date, B.Modified_Date,
                       C.Nama_Customer, C.Email, C.No_Telepon,
                       L.Nama_Lapangan, L.Harga_Sewa,
                       J.Tanggal, J.Jam_Mulai, J.Jam_Selesai,
                       P.Nama_Promo, P.Diskon,
                       K.Nama_Karyawan as Nama_Karyawan_Input
                FROM Booking B
                INNER JOIN Customer C ON B.ID_Customer = C.ID_Customer
                INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
                INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
                LEFT JOIN Promo P ON B.ID_Promo = P.ID_Promo
                LEFT JOIN Karyawan K ON B.ID_Karyawan = K.ID_Karyawan
                $sql_where
                ORDER BY B.Created_Date DESC";

$bookings = [];
$q_booking = sqlsrv_query($conn, $sql_booking, $params);
if ($q_booking) {
    while ($row = sqlsrv_fetch_array($q_booking, SQLSRV_FETCH_ASSOC)) {
        $bookings[] = $row;
    }
}

// ============================================================================
// HITUNG STATISTIK
// ============================================================================
$stats = [
    'total' => 0, 'menunggu' => 0, 'berhasil' => 0, 'selesai' => 0, 'dibatalkan' => 0,
    'total_omzet' => 0, 'total_refund' => 0
];

foreach ($bookings as $b) {
    $stats['total']++;
    switch ($b['Status']) {
        case 0: $stats['menunggu']++; break;
        case 1: $stats['berhasil']++; $stats['total_omzet'] += (float)$b['Total_Bayar']; break;
        case 2: $stats['selesai']++; $stats['total_omzet'] += (float)$b['Total_Bayar']; break;
        case 3: $stats['dibatalkan']++; $stats['total_refund'] += ((float)$b['Total_Bayar'] * 0.5); break;
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
function formatJam($jam) {
    if (empty($jam)) return '-';
    if (is_object($jam) && method_exists($jam, 'format')) {
        return $jam->format('H:i');
    }
    return substr($jam, 0, 5);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Booking | HoopBall</title>
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
    --text: #111827; --text-md: #374151; --muted: #6B7280; --bg: #F3F4F6;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

/* ---- SIDEBAR ---- */
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
.topbar { background: var(--card-bg); height: var(--topbar-h); padding: 0 40px; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); position: sticky; top: 0; z-index: 100; }
.topbar-left { display: flex; flex-direction: column; }
.topbar-title { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); letter-spacing: -.5px; line-height: 1; }
.topbar-breadcrumb { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.topbar-right { display: flex; align-items: center; gap: 16px; }
.topbar-btn { width: 38px; height: 38px; border-radius: 10px; background: var(--bg); border: 1px solid var(--border); display: flex; align-items: center; justify-content: center; color: var(--muted); cursor: pointer; font-size: 14px; text-decoration: none; transition: .2s; }
.topbar-btn:hover { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); }
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
.dd-item { display: flex; align-items: center; gap: 10px; padding: 11px 16px; color: #444; text-decoration: none; font-size: 13px; font-weight: 700; transition: .15s; }
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-item i { font-size: 14px; width: 18px; text-align: center; }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

.content { padding: 32px 40px; flex: 1; }

/* ---- STAT CARDS ---- */
.stat-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 16px; margin-bottom: 28px; }
.stat-card { background: var(--card-bg); border-radius: 14px; padding: 20px; border: 1px solid var(--border); position: relative; overflow: hidden; transition: all .2s ease; }
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }
.stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 4px; height: 100%; border-radius: 4px 0 0 4px; }
.sc-orange::before { background: var(--orange); }
.sc-yellow::before { background: var(--yellow); }
.sc-green::before { background: var(--green); }
.sc-blue::before { background: var(--blue); }
.sc-red::before { background: var(--red); }
.stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px; }
.stat-icon-wrap { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 16px; }
.si-orange { background: var(--orange-lt); color: var(--orange); }
.si-yellow { background: var(--yellow-lt); color: #D97706; }
.si-green { background: var(--green-lt); color: var(--green); }
.si-blue { background: var(--blue-lt); color: var(--blue); }
.si-red { background: var(--red-lt); color: var(--red); }
.stat-value { font-family: 'Barlow Condensed', sans-serif; font-size: 28px; font-weight: 900; color: var(--text); line-height: 1; margin-bottom: 4px; }
.stat-label { font-size: 11px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }

/* ---- FILTER BAR ---- */
.action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; gap: 16px; flex-wrap: wrap; }
.filter-group { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.filter-input { padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; font-size: 13px; font-family: inherit; background: var(--card-bg); color: var(--text); outline: none; transition: .2s; }
.filter-input:focus { border-color: var(--orange); box-shadow: 0 0 0 3px var(--orange-lt); }
.btn-secondary { background: var(--card-bg); color: var(--text); border: 1px solid var(--border); padding: 10px 18px; border-radius: 10px; font-weight: 700; font-size: 13px; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: .2s; text-decoration: none; }
.btn-secondary:hover { border-color: var(--orange); color: var(--orange); }
.btn-success { background: var(--green); color: #fff; border: none; padding: 8px 14px; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: .2s; }
.btn-success:hover { background: var(--green-dk); }
.btn-danger { background: var(--red); color: #fff; border: none; padding: 8px 14px; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: .2s; }
.btn-danger:hover { background: var(--red-dk); }
.btn-info { background: var(--blue); color: #fff; border: none; padding: 8px 14px; border-radius: 8px; font-weight: 700; font-size: 12px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: .2s; }
.btn-info:hover { background: #2563EB; }

/* ---- TABLE ---- */
.card { background: var(--card-bg); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; }
.card-header { padding: 20px 24px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; }
.card-title i { color: var(--orange); font-size: 14px; }
.card-body { padding: 0; }
.data-table { width: 100%; border-collapse: collapse; }
.data-table th { padding: 14px 16px; font-size: 11px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; border-bottom: 2px solid var(--border-lt); text-align: left; background: #FAFAFA; }
.data-table td { padding: 14px 16px; font-size: 13px; border-bottom: 1px solid var(--border-lt); vertical-align: middle; }
.data-table tbody tr { transition: background .15s; }
.data-table tbody tr:hover { background: #FAFAFA; }
.data-table tbody tr:last-child td { border-bottom: none; }
.cell-name { font-weight: 700; color: var(--text); }
.cell-detail { font-size: 11px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.cell-price { font-weight: 800; color: var(--orange); }
.status-pill { padding: 5px 12px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; display: inline-flex; align-items: center; gap: 5px; }
.sp-active { background: var(--green-lt); color: var(--green); }
.sp-success { background: var(--blue-lt); color: var(--blue); }
.sp-pending { background: var(--yellow-lt); color: #D97706; }
.sp-inactive { background: var(--red-lt); color: var(--red); }
.action-btns { display: flex; gap: 6px; }
.btn-icon { width: 32px; height: 32px; border-radius: 8px; border: 1px solid var(--border); background: var(--card-bg); color: var(--muted); display: flex; align-items: center; justify-content: center; cursor: pointer; font-size: 12px; transition: .2s; }
.btn-icon:hover { border-color: var(--orange); color: var(--orange); background: var(--orange-lt); }
.btn-icon.view:hover { border-color: var(--blue); color: var(--blue); background: var(--blue-lt); }
.btn-icon.success:hover { border-color: var(--green); color: var(--green); background: var(--green-lt); }
.btn-icon.danger:hover { border-color: var(--red); color: var(--red); background: var(--red-lt); }

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

.swal-toast { border-radius: 12px !important; font-family: 'Barlow', sans-serif !important; }

@media(max-width: 1200px) { .stat-grid { grid-template-columns: repeat(3, 1fr); } }
@media(max-width: 768px) {
    .sidebar { width: 0; overflow: hidden; padding: 0; }
    .main { margin-left: 0; }
    .content { padding: 20px; }
    .stat-grid { grid-template-columns: repeat(2, 1fr); }
    .action-bar { flex-direction: column; align-items: stretch; }
    .filter-group { width: 100%; }
    .detail-grid { grid-template-columns: 1fr; }
    .detail-full { grid-column: span 1; }
}

html, body { scrollbar-width: none; -ms-overflow-style: none; }
html::-webkit-scrollbar, body::-webkit-scrollbar { display: none; }
</style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar">
    <a href="../dashboard/view_admin.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div><div class="sb-brand-name">HOOP BALL</div><div class="sb-brand-sub">Sistem Manajemen</div></div>
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
        <a href="booking.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>Kelola Booking
        </a>
        <a href="langganan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-crown"></i></div>Kelola Langganan
        </a>
        <a href="pembelian.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-cart-shopping"></i></div>Kelola Pembelian Alat
        </a>
        <a href="pembatalan.php" class="sb-link">
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
                <?php if ($profile_photo): ?>
                    <img src="<?= $profile_photo ?>" alt="Profile">
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
        <div class="topbar-title">Kelola Booking</div>
        <div class="topbar-breadcrumb">Transaksi / Konfirmasi & Manajemen Booking</div>
    </div>
    <div class="topbar-right">
        <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
        <a href="#" class="topbar-btn"><i class="fa-solid fa-bell"></i></a>
        <div class="dropdown-wrap">
            <div class="topbar-user">
                <div class="t-avatar">
                    <?php if ($profile_photo): ?>
                        <img src="<?= $profile_photo ?>" alt="Profile">
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
    <!-- STAT CARDS -->
    <div class="stat-grid">
        <div class="stat-card sc-orange">
            <div class="stat-header"><div class="stat-icon-wrap si-orange"><i class="fa-solid fa-calendar-check"></i></div></div>
            <div class="stat-value"><?= $stats['total'] ?></div><div class="stat-label">Total Booking</div>
        </div>
        <div class="stat-card sc-yellow">
            <div class="stat-header"><div class="stat-icon-wrap si-yellow"><i class="fa-solid fa-clock"></i></div></div>
            <div class="stat-value"><?= $stats['menunggu'] ?></div><div class="stat-label">Menunggu Konfirmasi</div>
        </div>
        <div class="stat-card sc-green">
            <div class="stat-header"><div class="stat-icon-wrap si-green"><i class="fa-solid fa-check-circle"></i></div></div>
            <div class="stat-value"><?= $stats['berhasil'] ?></div><div class="stat-label">Berhasil</div>
        </div>
        <div class="stat-card sc-blue">
            <div class="stat-header"><div class="stat-icon-wrap si-blue"><i class="fa-solid fa-flag-checkered"></i></div></div>
            <div class="stat-value"><?= $stats['selesai'] ?></div><div class="stat-label">Selesai</div>
        </div>
        <div class="stat-card sc-red">
            <div class="stat-header"><div class="stat-icon-wrap si-red"><i class="fa-solid fa-ban"></i></div></div>
            <div class="stat-value"><?= $stats['dibatalkan'] ?></div><div class="stat-label">Dibatalkan</div>
        </div>
    </div>

    <!-- INFO BOX -->
    <div style="background: var(--blue-lt); border: 1px solid var(--blue); border-radius: 12px; padding: 16px 20px; margin-bottom: 24px; display: flex; align-items: center; gap: 12px;">
        <i class="fa-solid fa-circle-info" style="color: var(--blue); font-size: 20px;"></i>
        <div style="font-size: 13px; color: var(--text); line-height: 1.5;">
            <strong>Peran Karyawan:</strong> Customer membuat booking melalui website. Karyawan hanya mengkonfirmasi pembayaran yang sudah dilakukan customer. 
            <span style="color: var(--muted);">Booking baru dengan status "Menunggu" menunggu verifikasi pembayaran Anda.</span>
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
                    <option value="2" <?= $filter_status === '2' ? 'selected' : '' ?>>Selesai</option>
                    <option value="3" <?= $filter_status === '3' ? 'selected' : '' ?>>Dibatalkan</option>
                </select>
                <input type="text" name="filter_customer" class="filter-input" placeholder="Cari customer..." value="<?= htmlspecialchars($filter_customer) ?>">
                <input type="date" name="filter_tanggal" class="filter-input" value="<?= htmlspecialchars($filter_tanggal) ?>">
                <button type="submit" class="btn-secondary"><i class="fa-solid fa-filter"></i> Filter</button>
                <?php if ($filter_status || $filter_customer || $filter_tanggal): ?>
                    <a href="booking.php" class="btn-secondary"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- BOOKING TABLE -->
    <div class="card">
        <div class="card-header">
            <div class="card-title"><i class="fa-solid fa-list"></i> Daftar Booking</div>
            <span style="font-size: 12px; color: var(--muted); font-weight: 600;"><?= count($bookings) ?> data ditemukan</span>
        </div>
        <div class="card-body" style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Lapangan & Jadwal</th>
                        <th>Tanggal Booking</th>
                        <th>Metode Bayar</th>
                        <th>Total Bayar</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($bookings) > 0): ?>
                        <?php foreach ($bookings as $b): 
                            $status = $status_labels[$b['Status']] ?? $status_labels[0];
                            $tanggal_jadwal = formatTanggal($b['Tanggal']);
                            $jam_mulai = formatJam($b['Jam_Mulai']);
                            $jam_selesai = formatJam($b['Jam_Selesai']);
                        ?>
                        <tr>
                            <td><div class="cell-name">#<?= $b['ID_Booking'] ?></div></td>
                            <td>
                                <div class="cell-name"><?= htmlspecialchars($b['Nama_Customer']) ?></div>
                                <div class="cell-detail"><?= htmlspecialchars($b['Email']) ?></div>
                            </td>
                            <td>
                                <div class="cell-name"><?= htmlspecialchars($b['Nama_Lapangan']) ?></div>
                                <div class="cell-detail"><?= $tanggal_jadwal ?> | <?= $jam_mulai ?> - <?= $jam_selesai ?></div>
                            </td>
                            <td><?= formatTanggal($b['Tanggal_Booking']) ?></td>
                            <td><?= $b['Metode_Pembayaran'] ?></td>
                            <td class="cell-price"><?= rupiahFormat($b['Total_Bayar']) ?></td>
                            <td><span class="status-pill <?= $status['class'] ?>"><i class="fa-solid <?= $status['icon'] ?>"></i> <?= $status['label'] ?></span></td>
                            <td>
                                <div class="action-btns">
                                    <button class="btn-icon view" onclick="showDetail(<?= $b['ID_Booking'] ?>)" title="Detail"><i class="fa-solid fa-eye"></i></button>
                                    <?php if ($b['Status'] == 0): ?>
                                        <button class="btn-icon success" onclick="confirmBayar(<?= $b['ID_Booking'] ?>)" title="Konfirmasi Pembayaran"><i class="fa-solid fa-check"></i></button>
                                        <button class="btn-icon danger" onclick="confirmBatal(<?= $b['ID_Booking'] ?>)" title="Batalkan"><i class="fa-solid fa-xmark"></i></button>
                                    <?php elseif ($b['Status'] == 1): ?>
                                        <button class="btn-icon success" onclick="confirmSelesai(<?= $b['ID_Booking'] ?>)" title="Selesaikan"><i class="fa-solid fa-flag-checkered"></i></button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 50px; color: var(--muted);">
                                <i class="fa-solid fa-inbox" style="font-size: 40px; margin-bottom: 16px; opacity: .5; display: block;"></i>
                                <div style="font-size: 14px; font-weight: 700;">Belum ada data booking</div>
                                <div style="font-size: 12px; margin-top: 4px;">Customer belum melakukan booking</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
</main>

<!-- MODAL DETAIL -->
<div class="modal-overlay" id="modalDetail">
    <div class="modal">
        <div class="modal-header">
            <div class="modal-title"><i class="fa-solid fa-file-invoice"></i> Detail Booking</div>
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
    <input type="hidden" name="id_booking" id="konfirmasiId">
    <input type="hidden" name="konfirmasi_bayar" value="1">
</form>
<form method="POST" id="formSelesai" style="display: none;">
    <input type="hidden" name="id_booking" id="selesaiId">
    <input type="hidden" name="selesai_booking" value="1">
</form>
<form method="POST" id="formBatal" style="display: none;">
    <input type="hidden" name="id_booking" id="batalId">
    <input type="hidden" name="alasan_batal" id="batalAlasan">
    <input type="hidden" name="batal_booking" value="1">
</form>

<script>
const bookingData = <?= json_encode($bookings) ?>;

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
        if (e.target === this) {
            this.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
});

function showDetail(id) {
    const booking = bookingData.find(b => b.ID_Booking == id);
    if (!booking) return;

    const statusMap = {
        0: { label: 'Menunggu Konfirmasi', class: 'sp-pending', icon: 'fa-clock' },
        1: { label: 'Berhasil (Dikonfirmasi)', class: 'sp-active', icon: 'fa-check-circle' },
        2: { label: 'Selesai', class: 'sp-success', icon: 'fa-flag-checkered' },
        3: { label: 'Dibatalkan', class: 'sp-inactive', icon: 'fa-ban' }
    };
    const status = statusMap[booking.Status] || statusMap[0];

    const tanggalBooking = booking.Tanggal_Booking ? new Date(booking.Tanggal_Booking.date || booking.Tanggal_Booking).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '-';
    const tanggalJadwal = booking.Tanggal ? new Date(booking.Tanggal.date || booking.Tanggal).toLocaleDateString('id-ID', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' }) : '-';

    const jamMulai = booking.Jam_Mulai ? (typeof booking.Jam_Mulai === 'string' ? booking.Jam_Mulai.substring(0, 5) : booking.Jam_Mulai) : '-';
    const jamSelesai = booking.Jam_Selesai ? (typeof booking.Jam_Selesai === 'string' ? booking.Jam_Selesai.substring(0, 5) : booking.Jam_Selesai) : '-';

    const promoInfo = booking.Nama_Promo ? `<div class="detail-item"><div class="detail-label">Promo Digunakan</div><div class="detail-value">${booking.Nama_Promo} (Diskon ${formatRupiah(booking.Diskon || 0)})</div></div>` : '';

    const html = `
        <div class="detail-grid">
            <div class="detail-item"><div class="detail-label">ID Booking</div><div class="detail-value">#${booking.ID_Booking}</div></div>
            <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value status"><span class="status-pill ${status.class}"><i class="fa-solid ${status.icon}"></i> ${status.label}</span></div></div>
            <div class="detail-item"><div class="detail-label">Customer</div><div class="detail-value">${booking.Nama_Customer}</div><div style="font-size: 11px; color: var(--muted); margin-top: 2px;">${booking.Email} | ${booking.No_Telepon}</div></div>
            <div class="detail-item"><div class="detail-label">Lapangan</div><div class="detail-value">${booking.Nama_Lapangan}</div></div>
            <div class="detail-item"><div class="detail-label">Jadwal Bermain</div><div class="detail-value">${tanggalJadwal}</div><div style="font-size: 11px; color: var(--muted); margin-top: 2px;">${jamMulai} - ${jamSelesai}</div></div>
            <div class="detail-item"><div class="detail-label">Tanggal Booking</div><div class="detail-value">${tanggalBooking}</div></div>
            <div class="detail-item"><div class="detail-label">Metode Pembayaran</div><div class="detail-value">${booking.Metode_Pembayaran}</div></div>
            <div class="detail-item"><div class="detail-label">Input Oleh</div><div class="detail-value">${booking.Nama_Karyawan_Input || 'System'}</div></div>
            ${promoInfo}
            <div class="detail-item detail-full"><div class="detail-label">Total Bayar</div><div class="detail-value price">${formatRupiah(booking.Total_Bayar)}</div></div>
        </div>
    `;

    document.getElementById('detailContent').innerHTML = html;
    openModal('modalDetail');
}

function formatRupiah(angka) {
    return 'Rp ' + angka.toLocaleString('id-ID');
}

function confirmBayar(id) {
    Swal.fire({
        title: 'Konfirmasi Pembayaran?',
        html: 'Customer sudah melakukan pembayaran?<br><span style="color: var(--muted); font-size: 12px;">Status booking akan berubah menjadi <strong>Berhasil</strong></span>',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#10B981',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Konfirmasi',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('konfirmasiId').value = id;
            document.getElementById('formKonfirmasi').submit();
        }
    });
}

function confirmSelesai(id) {
    Swal.fire({
        title: 'Selesaikan Booking?',
        html: 'Booking ini sudah selesai digunakan?<br><span style="color: var(--muted); font-size: 12px;">Status akan berubah menjadi <strong>Selesai</strong></span>',
        icon: 'info',
        showCancelButton: true,
        confirmButtonColor: '#3B82F6',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Selesai',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('selesaiId').value = id;
            document.getElementById('formSelesai').submit();
        }
    });
}

function confirmBatal(id) {
    Swal.fire({
        title: 'Batalkan Booking?',
        html: 'Booking ini akan dibatalkan.<br><span style="color: var(--red); font-size: 12px;"><strong>Refund 50%</strong> akan dikembalikan ke customer.</span>',
        icon: 'warning',
        input: 'textarea',
        inputLabel: 'Alasan Pembatalan',
        inputPlaceholder: 'Masukkan alasan pembatalan...',
        inputAttributes: { 'aria-label': 'Alasan pembatalan' },
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Batalkan',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        inputValidator: (value) => { if (!value) return 'Alasan pembatalan wajib diisi!'; }
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('batalId').value = id;
            document.getElementById('batalAlasan').value = result.value;
            document.getElementById('formBatal').submit();
        }
    });
}

const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
const msg = urlParams.get('msg');

if (status && msg) {
    const isSuccess = status === 'success';
    Swal.fire({
        icon: isSuccess ? 'success' : 'error',
        title: isSuccess ? 'Berhasil!' : 'Gagal!',
        text: msg,
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        showCloseButton: true,
        background: '#ffffff',
        color: '#1c1c1e',
        iconColor: isSuccess ? '#10B981' : '#EF4444',
        customClass: { popup: 'swal-toast' }
    });
    window.history.replaceState({}, document.title, window.location.pathname);
}

document.addEventListener('DOMContentLoaded', function () {
    const userDropdown = document.querySelector('.dropdown-wrap');
    if (userDropdown) {
        userDropdown.addEventListener('click', function (e) {
            e.stopPropagation();
            this.classList.toggle('active');
        });
    }
    document.addEventListener('click', function () {
        if (userDropdown) userDropdown.classList.remove('active');
    });
});
</script>

</body>
</html>