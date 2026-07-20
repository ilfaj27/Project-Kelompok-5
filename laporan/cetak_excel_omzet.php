<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'pemilik') {
    header("Location: ../login/login.php");
    exit();
}

$tanggal_unduh = date('dmy');
$nama_file = 'LaporanOmzet_' . $tanggal_unduh . '.xls';

header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=" . $nama_file);
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

// ============================================
// STATISTIK OMZET - MENGGUNAKAN UDF
// ============================================
$omzet_booking = 0;
$omzet_langganan = 0;
$omzet_beli_alat = 0;
$total_refund = 0;

// UDF: fn_GetOmzetBookingStats
$q = safeQuery($conn, "SELECT * FROM dbo.fn_GetOmzetBookingStats(?, ?, ?)", array($filter_type, $start_date, $end_date));
$d = safeFetch($q);
if ($d) {
    $omzet_booking = $d['omzet'] ?? 0;
    $total_refund = $d['total_refund'] ?? 0;
}

// UDF: fn_GetOmzetLanggananStats
$q = safeQuery($conn, "SELECT * FROM dbo.fn_GetOmzetLanggananStats(?, ?, ?)", array($filter_type, $start_date, $end_date));
$d = safeFetch($q);
if ($d) $omzet_langganan = $d['omzet'] ?? 0;

// UDF: fn_GetOmzetBeliAlatStats
$q = safeQuery($conn, "SELECT * FROM dbo.fn_GetOmzetBeliAlatStats(?, ?, ?)", array($filter_type, $start_date, $end_date));
$d = safeFetch($q);
if ($d) $omzet_beli_alat = $d['omzet'] ?? 0;

$total_omzet_kotor = $omzet_booking + $omzet_langganan + $omzet_beli_alat;
$total_omzet_bersih = $total_omzet_kotor - $total_refund;

// ============================================
// DATA CETAK - MENGGUNAKAN UDF
// ============================================
// UDF: fn_GetBookingReport
$print_bookings = [];
$q = safeQuery($conn, "SELECT * FROM dbo.fn_GetBookingReport(?, ?, ?) ORDER BY Tanggal_Booking DESC", array($filter_type, $start_date, $end_date));
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $print_bookings[] = $row;
    }
}

// UDF: fn_GetLanggananReportOmzet
$print_langganan = [];
$q = safeQuery($conn, "SELECT * FROM dbo.fn_GetLanggananReportOmzet(?, ?, ?) ORDER BY Tanggal_Mulai DESC", array($filter_type, $start_date, $end_date));
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $print_langganan[] = $row;
    }
}

// UDF: fn_GetBeliAlatReport
$print_beli = [];
$q = safeQuery($conn, "SELECT * FROM dbo.fn_GetBeliAlatReport(?, ?, ?) ORDER BY Tanggal_Beli DESC", array($filter_type, $start_date, $end_date));
if ($q !== null) {
    while ($row = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $print_beli[] = $row;
    }
}

// UDF: fn_GetTransaksiCount
$total_transaksi = 0;
$q = safeQuery($conn, "SELECT * FROM dbo.fn_GetTransaksiCount(?, ?, ?)", array($filter_type, $start_date, $end_date));
$d = safeFetch($q);
if ($d) {
    $total_transaksi = ($d['total_booking'] ?? 0) + ($d['total_langganan'] ?? 0) + ($d['total_beli'] ?? 0);
}

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