<?php
session_start();
include '../includes/config.php';

require_once '../TCPDF-main/tcpdf.php';

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'pemilik') {
    header("Location: ../login/login.php");
    exit();
}

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

class MYPDF extends TCPDF {
    public function Footer() {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . ' dari ' . $this->getAliasNbPages(), 0, false, 'R', 0, '', 0, false, 'T', 'M');
    }
}

$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('HoopBall System');
$pdf->SetTitle('Laporan Omzet');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20.3);

// ============================================
// HALAMAN 1: KOP + TABEL SEWA LAPANGAN
// ============================================
$pdf->AddPage();

ob_start();
$judul_cetak = "LAPORAN OMZET KESELURUHAN";
$jumlah_data_cetak = $total_transaksi;
include '../includes/kop_laporan.php';
$kop_html = ob_get_clean();

$html1 = $kop_html . '
<div style="line-height: 9px;">&nbsp;</div>

<div style="font-family: Arial, Helvetica, sans-serif; font-size: 11px; font-weight: bold; color: #10B981; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
    <i class="fa-solid fa-calendar-check"></i> TABEL SEWA LAPANGAN
</div>
<table border="1" cellspacing="0" cellpadding="5" style="width: 100%; font-family: Arial, Helvetica, sans-serif; font-size: 8px;">
    <thead>
        <tr style="background-color: #F3F4F6; font-weight: bold;">
            <th align="center" valign="middle" style="width: 5%;">No.</th>
            <th align="center" valign="middle" style="width: 20%;">Customer</th>
            <th align="center" valign="middle" style="width: 18%;">Lapangan</th>
            <th align="center" valign="middle" style="width: 15%;">Tanggal</th>
            <th align="center" valign="middle" style="width: 12%;">Metode Bayar</th>
            <th align="center" valign="middle" style="width: 15%;">Total Bayar</th>
            <th align="center" valign="middle" style="width: 15%;">Status</th>
        </tr>
    </thead>
    <tbody>';

if (count($print_bookings) > 0) {
    $no = 1;
    foreach ($print_bookings as $b) {
        $status_lbl = statusBookingLabel($b['Status']);
        $tanggal = ($b['Tanggal_Booking'] instanceof DateTime) ? $b['Tanggal_Booking']->format('d M Y') : '-';

        $html1 .= '
        <tr>
            <td align="center" valign="middle" style="width: 5%;">' . $no++ . '</td>
            <td align="left" valign="middle" style="width: 20%;"><strong>' . htmlspecialchars($b['Nama_Customer'] ?? '-') . '</strong></td>
            <td align="left" valign="middle" style="width: 18%;">' . htmlspecialchars($b['Nama_Lapangan'] ?? '-') . '</td>
            <td align="center" valign="middle" style="width: 15%;">' . $tanggal . '</td>
            <td align="left" valign="middle" style="width: 12%;">' . htmlspecialchars($b['Metode_Pembayaran'] ?? '-') . '</td>
            <td align="right" valign="middle" style="width: 15%;">' . rupiahFormat($b['Total_Bayar'] ?? 0) . '</td>
            <td align="center" valign="middle" style="width: 15%;">' . $status_lbl . '</td>
        </tr>';
    }
} else {
    $html1 .= '<tr><td colspan="7" style="text-align: center; padding: 20px;">Tidak ada data transaksi booking</td></tr>';
}

$html1 .= '
    </tbody>
</table>';

$pdf->writeHTML($html1, true, false, true, false, '');

// ============================================
// HALAMAN 2: TABEL LANGGANAN MEMBER
// ============================================
$pdf->AddPage();

$html2 = '
<div style="font-family: Arial, Helvetica, sans-serif; font-size: 11px; font-weight: bold; color: #3B82F6; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
    <i class="fa-solid fa-crown"></i> TABEL LANGGANAN MEMBER
</div>
<table border="1" cellspacing="0" cellpadding="5" style="width: 100%; font-family: Arial, Helvetica, sans-serif; font-size: 8px;">
    <thead>
        <tr style="background-color: #F3F4F6; font-weight: bold;">
            <th align="center" valign="middle" style="width: 5%;">No.</th>
            <th align="center" valign="middle" style="width: 20%;">Customer</th>
            <th align="center" valign="middle" style="width: 18%;">Tipe Member</th>
            <th align="center" valign="middle" style="width: 15%;">Tanggal Mulai</th>
            <th align="center" valign="middle" style="width: 12%;">Metode Bayar</th>
            <th align="center" valign="middle" style="width: 15%;">Total Bayar</th>
            <th align="center" valign="middle" style="width: 15%;">Status</th>
        </tr>
    </thead>
    <tbody>';

if (count($print_langganan) > 0) {
    $no = 1;
    foreach ($print_langganan as $b) {
        $status_lbl = statusLanggananLabel($b['Status']);
        $tanggal = ($b['Tanggal_Mulai'] instanceof DateTime) ? $b['Tanggal_Mulai']->format('d M Y') : '-';

        $html2 .= '
        <tr>
            <td align="center" valign="middle" style="width: 5%;">' . $no++ . '</td>
            <td align="left" valign="middle" style="width: 20%;"><strong>' . htmlspecialchars($b['Nama_Customer'] ?? '-') . '</strong></td>
            <td align="left" valign="middle" style="width: 18%;">' . htmlspecialchars($b['Nama_Tipe'] ?? '-') . ' Member</td>
            <td align="center" valign="middle" style="width: 15%;">' . $tanggal . '</td>
            <td align="left" valign="middle" style="width: 12%;">' . htmlspecialchars($b['Metode_Pembayaran'] ?? '-') . '</td>
            <td align="right" valign="middle" style="width: 15%;">' . rupiahFormat($b['Total_Bayar'] ?? 0) . '</td>
            <td align="center" valign="middle" style="width: 15%;">' . $status_lbl . '</td>
        </tr>';
    }
} else {
    $html2 .= '<tr><td colspan="7" style="text-align: center; padding: 20px;">Tidak ada data transaksi langganan</td></tr>';
}

$html2 .= '
    </tbody>
</table>';

$pdf->writeHTML($html2, true, false, true, false, '');

// ============================================
// HALAMAN 3: TABEL PEMBELIAN ALAT + RINGKASAN
// ============================================
$pdf->AddPage();

$html3 = '
<div style="font-family: Arial, Helvetica, sans-serif; font-size: 11px; font-weight: bold; color: #8B5CF6; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">
    <i class="fa-solid fa-basketball"></i> TABEL PEMBELIAN ALAT
</div>
<table border="1" cellspacing="0" cellpadding="5" style="width: 100%; font-family: Arial, Helvetica, sans-serif; font-size: 8px; margin-bottom: 16px;">
    <thead>
        <tr style="background-color: #F3F4F6; font-weight: bold;">
            <th align="center" valign="middle" style="width: 5%;">No.</th>
            <th align="center" valign="middle" style="width: 25%;">Customer</th>
            <th align="center" valign="middle" style="width: 20%;">Tanggal Beli</th>
            <th align="center" valign="middle" style="width: 20%;">Metode Bayar</th>
            <th align="center" valign="middle" style="width: 15%;">Total Bayar</th>
            <th align="center" valign="middle" style="width: 15%;">Status</th>
        </tr>
    </thead>
    <tbody>';

if (count($print_beli) > 0) {
    $no = 1;
    foreach ($print_beli as $b) {
        $tanggal = ($b['Tanggal_Beli'] instanceof DateTime) ? $b['Tanggal_Beli']->format('d M Y') : '-';

        $html3 .= '
        <tr>
            <td align="center" valign="middle" style="width: 5%;">' . $no++ . '</td>
            <td align="left" valign="middle" style="width: 25%;"><strong>' . htmlspecialchars($b['Nama_Customer'] ?? '-') . '</strong></td>
            <td align="center" valign="middle" style="width: 20%;">' . $tanggal . '</td>
            <td align="left" valign="middle" style="width: 20%;">' . htmlspecialchars($b['Metode_Pembayaran'] ?? '-') . '</td>
            <td align="right" valign="middle" style="width: 15%;">' . rupiahFormat($b['Total_Bayar'] ?? 0) . '</td>
            <td align="center" valign="middle" style="width: 15%;">Berhasil</td>
        </tr>';
    }
} else {
    $html3 .= '<tr><td colspan="6" style="text-align: center; padding: 20px;">Tidak ada data transaksi pembelian alat</td></tr>';
}

$html3 .= '
    </tbody>
</table>

<div style="line-height: 6px;">&nbsp;</div>

<table border="1" cellspacing="0" cellpadding="6" style="width: 100%; font-family: Arial, Helvetica, sans-serif; font-size: 9px;">
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

$pdf->writeHTML($html3, true, false, true, false, '');

$pdf->Output('Laporan_Omzet.pdf', 'D');