<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'pemilik') {
    header("Location: ../login/login.php");
    exit();
}

header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Omzet.xls");
header("Pragma: no-cache");
header("Expires: 0");

function rupiahFormat($n) {
    return 'Rp ' . number_format($n, 0, ',', '.');
}

function safeQuery($conn, $sql, $params = array()) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("SQL Error: " . print_r(sqlsrv_errors(), true));
        return null;
    }
    return $stmt;
}

function safeFetch($stmt) {
    if ($stmt === null) return false;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

$filter_type = $_GET['filter'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_booking = ["1=1"];
$where_langganan = ["1=1"];
$where_beli = ["1=1"];
$params_b = [];
$params_l = [];
$params_ba = [];

if ($filter_type === 'today') {
    $where_booking[] = "CAST(b.Tanggal_Booking AS DATE) = CAST(GETDATE() AS DATE)";
    $where_langganan[] = "CAST(lg.Tanggal_Mulai AS DATE) = CAST(GETDATE() AS DATE)";
    $where_beli[] = "CAST(ba.Tanggal_Beli AS DATE) = CAST(GETDATE() AS DATE)";
} elseif ($filter_type === 'week') {
    $where_booking[] = "b.Tanggal_Booking >= DATEADD(day, -7, CAST(GETDATE() AS DATE))";
    $where_langganan[] = "lg.Tanggal_Mulai >= DATEADD(day, -7, CAST(GETDATE() AS DATE))";
    $where_beli[] = "ba.Tanggal_Beli >= DATEADD(day, -7, CAST(GETDATE() AS DATE))";
} elseif ($filter_type === 'month') {
    $where_booking[] = "MONTH(b.Tanggal_Booking) = MONTH(GETDATE()) AND YEAR(b.Tanggal_Booking) = YEAR(GETDATE())";
    $where_langganan[] = "MONTH(lg.Tanggal_Mulai) = MONTH(GETDATE()) AND YEAR(lg.Tanggal_Mulai) = YEAR(GETDATE())";
    $where_beli[] = "MONTH(ba.Tanggal_Beli) = MONTH(GETDATE()) AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())";
} elseif ($filter_type === 'year') {
    $where_booking[] = "YEAR(b.Tanggal_Booking) = YEAR(GETDATE())";
    $where_langganan[] = "YEAR(lg.Tanggal_Mulai) = YEAR(GETDATE())";
    $where_beli[] = "YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())";
} elseif ($filter_type === 'custom' && !empty($start_date) && !empty($end_date)) {
    $where_booking[] = "b.Tanggal_Booking BETWEEN ? AND ?";
    $where_langganan[] = "lg.Tanggal_Mulai BETWEEN ? AND ?";
    $where_beli[] = "ba.Tanggal_Beli BETWEEN ? AND ?";
    $params_b[] = $start_date; $params_b[] = $end_date;
    $params_l[] = $start_date; $params_l[] = $end_date;
    $params_ba[] = $start_date; $params_ba[] = $end_date;
}

$where_booking_sql = implode(" AND ", $where_booking);
$where_langganan_sql = implode(" AND ", $where_langganan);
$where_beli_sql = implode(" AND ", $where_beli);

// Statistik
$omzet_booking = 0;
$omzet_langganan = 0;
$omzet_beli_alat = 0;
$total_refund = 0;

$q = safeQuery($conn, "SELECT ISNULL(SUM(Total_Bayar), 0) as total FROM Booking b WHERE b.Status IN (1,2) AND " . $where_booking_sql, $params_b);
$d = safeFetch($q);
if ($d) $omzet_booking = $d['total'] ?? 0;

$q = safeQuery($conn, "SELECT ISNULL(SUM(pb.Nominal_Refund), 0) as total FROM Pembatalan_Booking pb LEFT JOIN Booking b ON pb.ID_Booking = b.ID_Booking WHERE " . $where_booking_sql, $params_b);
$d = safeFetch($q);
if ($d) $total_refund = $d['total'] ?? 0;

$q = safeQuery($conn, "SELECT ISNULL(SUM(Total_Bayar), 0) as total FROM Langganan lg WHERE " . $where_langganan_sql, $params_l);
$d = safeFetch($q);
if ($d) $omzet_langganan = $d['total'] ?? 0;

$q = safeQuery($conn, "SELECT ISNULL(SUM(Total_Bayar), 0) as total FROM Beli_Alat ba WHERE ba.Status = 1 AND " . $where_beli_sql, $params_ba);
$d = safeFetch($q);
if ($d) $omzet_beli_alat = $d['total'] ?? 0;

$total_omzet_kotor = $omzet_booking + $omzet_langganan + $omzet_beli_alat;
$total_omzet_bersih = $total_omzet_kotor - $total_refund;

// Data Booking
$print_bookings = [];
$q = safeQuery($conn, 
    "SELECT b.ID_Booking, b.Tanggal_Booking, b.Total_Bayar, b.Status, b.Metode_Pembayaran,
     c.Nama_Customer, l.Nama_Lapangan
     FROM Booking b
     LEFT JOIN Customer c ON b.ID_Customer = c.ID_Customer
     LEFT JOIN Jadwal j ON b.ID_Jadwal = j.ID_Jadwal
     LEFT JOIN Lapangan l ON j.ID_Lapangan = l.ID_Lapangan
     WHERE " . $where_booking_sql . "
     ORDER BY b.Tanggal_Booking DESC", $params_b);
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $print_bookings[] = $row;
    }
}

// Data Langganan
$print_langganan = [];
$q = safeQuery($conn, 
    "SELECT lg.ID_Langganan, lg.Tanggal_Mulai, lg.Total_Bayar, lg.Status, lg.Metode_Pembayaran,
     c.Nama_Customer, tm.Nama_Tipe
     FROM Langganan lg
     LEFT JOIN Customer c ON lg.ID_Customer = c.ID_Customer
     LEFT JOIN Tipe_Member tm ON lg.ID_Tipe = tm.ID_Tipe
     WHERE " . $where_langganan_sql . "
     ORDER BY lg.Tanggal_Mulai DESC", $params_l);
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $print_langganan[] = $row;
    }
}

// Data Beli Alat
$print_beli = [];
$q = safeQuery($conn, 
    "SELECT ba.ID_Beli, ba.Tanggal_Beli, ba.Total_Bayar, ba.Metode_Pembayaran,
     c.Nama_Customer
     FROM Beli_Alat ba
     LEFT JOIN Customer c ON ba.ID_Customer = c.ID_Customer
     WHERE ba.Status = 1 AND " . $where_beli_sql . "
     ORDER BY ba.Tanggal_Beli DESC", $params_ba);
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $print_beli[] = $row;
    }
}

$total_transaksi = count($print_bookings) + count($print_langganan) + count($print_beli);

function statusBookingLabel($status) {
    switch($status) {
        case 0: return 'Menunggu';
        case 1: return 'Berhasil';
        case 2: return 'Selesai';
        case 3: return 'Dibatalkan';
        default: return 'Unknown';
    }
}

function statusLanggananLabel($status) {
    switch($status) {
        case 0: return 'Menunggu';
        case 1: return 'Aktif';
        case 2: return 'Berakhir';
        case 3: return 'Ditolak';
        default: return 'Unknown';
    }
}

ob_start();
$judul_cetak = "LAPORAN OMZET KESELURUHAN";
$jumlah_data_cetak = $total_transaksi;
include '../includes/kop_laporan_excel_omzet.php';
$kop_html = ob_get_clean();

$html = $kop_html . '

<!-- ============================================ -->
<!-- TABEL 1: SEWA LAPANGAN -->
<!-- ============================================ -->
<table border="0" cellspacing="0" cellpadding="4" style="width: 100%; font-family: Arial, Helvetica, sans-serif; font-size: 11px; margin-bottom: 4px;">
    <tr>
        <td style="font-weight: bold; color: #10B981; text-transform: uppercase; letter-spacing: 0.5px; border: none !important;">
            <strong>TABEL SEWA LAPANGAN</strong>
        </td>
    </tr>
</table>
<table border="1" cellspacing="0" cellpadding="6" style="width: 100%; font-family: Arial, Helvetica, sans-serif; font-size: 10px; margin-bottom: 16px;">
    <thead>
        <tr style="background-color: #F3F4F6; font-weight: bold; text-align: center; vertical-align: middle;">
            <th style="width: 5%;">No</th>
            <th style="width: 20%;">Customer</th>
            <th style="width: 18%;">Lapangan</th>
            <th style="width: 15%;">Tanggal</th>
            <th style="width: 12%;">Metode Bayar</th>
            <th style="width: 15%;">Total Bayar</th>
            <th style="width: 15%;">Status</th>
        </tr>
    </thead>
    <tbody>';

if (count($print_bookings) > 0) {
    $no = 1;
    foreach ($print_bookings as $b) {
        $status_lbl = statusBookingLabel($b['Status']);
        $tanggal = ($b['Tanggal_Booking'] instanceof DateTime) ? $b['Tanggal_Booking']->format('d M Y') : '-';

        $html .= '
        <tr>
            <td align="center" valign="center" style="width: 5%; vertical-align: middle;">' . $no++ . '</td>
            <td align="left" valign="center" style="width: 20%; vertical-align: middle;"><strong>' . htmlspecialchars($b['Nama_Customer'] ?? '-') . '</strong></td>
            <td align="left" valign="center" style="width: 18%; vertical-align: middle;">' . htmlspecialchars($b['Nama_Lapangan'] ?? '-') . '</td>
            <td align="center" valign="center" style="width: 15%; vertical-align: middle;">' . $tanggal . '</td>
            <td align="left" valign="center" style="width: 12%; vertical-align: middle;">' . htmlspecialchars($b['Metode_Pembayaran'] ?? '-') . '</td>
            <td align="right" valign="center" style="width: 15%; vertical-align: middle;">' . rupiahFormat($b['Total_Bayar'] ?? 0) . '</td>
            <td align="center" valign="center" style="width: 15%; vertical-align: middle;">' . $status_lbl . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="7" style="text-align: center; padding: 20px;">Tidak ada data transaksi booking</td></tr>';
}

$html .= '
    </tbody>
</table>

<br>

<!-- ============================================ -->
<!-- TABEL 2: LANGGANAN MEMBER -->
<!-- ============================================ -->
<table border="0" cellspacing="0" cellpadding="4" style="width: 100%; font-family: Arial, Helvetica, sans-serif; font-size: 11px; margin-bottom: 4px;">
    <tr>
        <td style="font-weight: bold; color: #3B82F6; text-transform: uppercase; letter-spacing: 0.5px; border: none !important;">
            <strong>TABEL LANGGANAN MEMBER</strong>
        </td>
    </tr>
</table>
<table border="1" cellspacing="0" cellpadding="6" style="width: 100%; font-family: Arial, Helvetica, sans-serif; font-size: 10px; margin-bottom: 16px;">
    <thead>
        <tr style="background-color: #F3F4F6; font-weight: bold; text-align: center; vertical-align: middle;">
            <th style="width: 5%;">No</th>
            <th style="width: 20%;">Customer</th>
            <th style="width: 18%;">Tipe Member</th>
            <th style="width: 15%;">Tanggal Mulai</th>
            <th style="width: 12%;">Metode Bayar</th>
            <th style="width: 15%;">Total Bayar</th>
            <th style="width: 15%;">Status</th>
        </tr>
    </thead>
    <tbody>';

if (count($print_langganan) > 0) {
    $no = 1;
    foreach ($print_langganan as $b) {
        $status_lbl = statusLanggananLabel($b['Status']);
        $tanggal = ($b['Tanggal_Mulai'] instanceof DateTime) ? $b['Tanggal_Mulai']->format('d M Y') : '-';

        $html .= '
        <tr>
            <td align="center" valign="center" style="width: 5%; vertical-align: middle;">' . $no++ . '</td>
            <td align="left" valign="center" style="width: 20%; vertical-align: middle;"><strong>' . htmlspecialchars($b['Nama_Customer'] ?? '-') . '</strong></td>
            <td align="left" valign="center" style="width: 18%; vertical-align: middle;">' . htmlspecialchars($b['Nama_Tipe'] ?? '-') . ' Member</td>
            <td align="center" valign="center" style="width: 15%; vertical-align: middle;">' . $tanggal . '</td>
            <td align="left" valign="center" style="width: 12%; vertical-align: middle;">' . htmlspecialchars($b['Metode_Pembayaran'] ?? '-') . '</td>
            <td align="right" valign="center" style="width: 15%; vertical-align: middle;">' . rupiahFormat($b['Total_Bayar'] ?? 0) . '</td>
            <td align="center" valign="center" style="width: 15%; vertical-align: middle;">' . $status_lbl . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="7" style="text-align: center; padding: 20px;">Tidak ada data transaksi langganan</td></tr>';
}

$html .= '
    </tbody>
</table>

<br>

<!-- ============================================ -->
<!-- TABEL 3: PEMBELIAN ALAT -->
<!-- ============================================ -->
<table border="0" cellspacing="0" cellpadding="4" style="width: 100%; font-family: Arial, Helvetica, sans-serif; font-size: 11px; margin-bottom: 4px;">
    <tr>
        <td style="font-weight: bold; color: #8B5CF6; text-transform: uppercase; letter-spacing: 0.5px; border: none !important;">
            <strong>TABEL PEMBELIAN ALAT</strong>
        </td>
    </tr>
</table>
<table border="1" cellspacing="0" cellpadding="6" style="width: 100%; font-family: Arial, Helvetica, sans-serif; font-size: 10px; margin-bottom: 16px;">
    <thead>
        <tr style="background-color: #F3F4F6; font-weight: bold; text-align: center; vertical-align: middle;">
            <th style="width: 5%;">No</th>
            <th style="width: 25%;">Customer</th>
            <th style="width: 20%;">Tanggal Beli</th>
            <th style="width: 20%;">Metode Bayar</th>
            <th style="width: 15%;">Total Bayar</th>
            <th style="width: 15%;">Status</th>
        </tr>
    </thead>
    <tbody>';

if (count($print_beli) > 0) {
    $no = 1;
    foreach ($print_beli as $b) {
        $tanggal = ($b['Tanggal_Beli'] instanceof DateTime) ? $b['Tanggal_Beli']->format('d M Y') : '-';

        $html .= '
        <tr>
            <td align="center" valign="center" style="width: 5%; vertical-align: middle;">' . $no++ . '</td>
            <td align="left" valign="center" style="width: 25%; vertical-align: middle;"><strong>' . htmlspecialchars($b['Nama_Customer'] ?? '-') . '</strong></td>
            <td align="center" valign="center" style="width: 20%; vertical-align: middle;">' . $tanggal . '</td>
            <td align="left" valign="center" style="width: 20%; vertical-align: middle;">' . htmlspecialchars($b['Metode_Pembayaran'] ?? '-') . '</td>
            <td align="right" valign="center" style="width: 15%; vertical-align: middle;">' . rupiahFormat($b['Total_Bayar'] ?? 0) . '</td>
            <td align="center" valign="center" style="width: 15%; vertical-align: middle;">Berhasil</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="6" style="text-align: center; padding: 20px;">Tidak ada data transaksi pembelian alat</td></tr>';
}

$html .= '
    </tbody>
</table>

<br>

<!-- ============================================ -->
<!-- TOTAL KESELURUHAN -->
<!-- ============================================ -->
<table border="1" cellspacing="0" cellpadding="6" style="width: 100%; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
    <tbody>
        <tr style="font-weight: bold; background-color: #E5E7EB;">
            <td colspan="5" align="center" valign="middle" style="width: 70%;">TOTAL OMZET KOTOR</td>
            <td align="right" valign="middle" style="width: 30%;">' . rupiahFormat($total_omzet_kotor) . '</td>
        </tr>
        <tr style="font-weight: bold; background-color: #FEE2E2;">
            <td colspan="5" align="center" valign="middle" style="width: 70%;">TOTAL REFUND</td>
            <td align="right" valign="middle" style="width: 30%; color: #EF4444;">-' . rupiahFormat($total_refund) . '</td>
        </tr>
        <tr style="font-weight: bold; background-color: #D1FAE5;">
            <td colspan="5" align="center" valign="middle" style="width: 70%;">OMZET BERSIH</td>
            <td align="right" valign="middle" style="width: 30%; color: #10B981;">' . rupiahFormat($total_omzet_bersih) . '</td>
        </tr>
    </tbody>
</table>';

echo $html;