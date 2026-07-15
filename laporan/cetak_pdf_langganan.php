<?php
session_start();
include '../includes/config.php';

// Pastikan jalur (path) pemanggilan library TCPDF di bawah ini sudah sesuai dengan struktur folder Anda
require_once '../TCPDF-main/tcpdf.php';

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'pemilik') {
    header("Location: ../login/login.php");
    exit();
}

// ============================================
// FORMAT RUPIAH & TANGGAL
// ============================================
function rupiahFormat($n)
{
    return 'Rp ' . number_format($n, 0, ',', '.');
}

function isExpired($tanggal_selesai) {
    if (!$tanggal_selesai) return false;
    $today = new DateTime();
    $end = new DateTime($tanggal_selesai->format('Y-m-d'));
    return $today > $end;
}

// ============================================
// UTILITY DATABASE
// ============================================
function safeQuery($conn, $sql, $params = array())
{
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        error_log("SQL Error: " . print_r(sqlsrv_errors(), true));
        return null;
    }
    return $stmt;
}

function safeFetch($stmt)
{
    if ($stmt === null)
        return false;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

// ============================================
// CAPTURE FILTER PARAMETERS
// ============================================
$filter_type = $_GET['filter'] ?? 'all';
$start_date = $_GET['start_date'] ?? null;
$end_date = $_GET['end_date'] ?? null;
$tipe_filter = $_GET['tipe'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

$p_filter_type = $filter_type;
$p_start_date = (!empty($start_date) && $filter_type === 'custom') ? $start_date : null;
$p_end_date = (!empty($end_date) && $filter_type === 'custom') ? $end_date : null;
$p_tipe = ($tipe_filter !== 'all') ? (int) $tipe_filter : null;
$p_status = ($status_filter !== 'all') ? (int) $status_filter : null;

$params = array(
    array($p_filter_type, SQLSRV_PARAM_IN),
    array($p_start_date, SQLSRV_PARAM_IN),
    array($p_end_date, SQLSRV_PARAM_IN),
    array($p_tipe, SQLSRV_PARAM_IN),
    array($p_status, SQLSRV_PARAM_IN)
);

// ============================================
// AMBIL DATA STATISTIK & TRANSAKSI (Menggunakan UDF)
// ============================================
$total_pendapatan = 0;
$stats_sql = "SELECT pendapatan FROM dbo.fn_GetLanggananStats(?, ?, ?, ?, ?)";
$q = safeQuery($conn, $stats_sql, $params);
$d = safeFetch($q);
if ($d) {
    $total_pendapatan = $d['pendapatan'] ?? 0;
}

$print_bookings = [];
$print_sql = "SELECT 
        ID_Langganan, Tanggal_Mulai, Tanggal_Selesai, Total_Bayar, Metode_Pembayaran, Status,
        Nama_Customer, Email, No_Telepon, Nama_Karyawan_Konfirm, Nama_Tipe, Harga_Member, Potongan_Harga
    FROM dbo.fn_GetLanggananReport(?, ?, ?, ?, ?)
    WHERE Status <> 0
    ORDER BY 
        CASE WHEN Status = 1 THEN 1 WHEN Status = 2 THEN 2 ELSE 3 END ASC, 
        Tanggal_Mulai DESC, 
        ID_Langganan DESC";

$q_print = safeQuery($conn, $print_sql, $params);
if ($q_print !== null) {
    while ($row = sqlsrv_fetch_array($q_print, SQLSRV_FETCH_ASSOC)) {
        $print_bookings[] = $row;
    }
}
$total_transaksi = count($print_bookings);

// ============================================
// KELAS KUSTOM UNTUK FOOTER NOMOR HALAMAN
// ============================================
class MYPDF extends TCPDF
{
    public function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('helvetica', 'I', 8);
        $this->Cell(0, 10, 'Halaman ' . $this->getAliasNumPage() . ' dari ' . $this->getAliasNbPages(), 0, false, 'R', 0, '', 0, false, 'T', 'M');
    }
}

// ============================================
// INISIALISASI TCPDF
// ============================================
$pdf = new MYPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator(PDF_CREATOR);
$pdf->SetAuthor('HoopBall System');
$pdf->SetTitle('Laporan Langganan Member');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20.3);
$pdf->AddPage();

// ============================================
// STRUKTUR HTML UNTUK PDF
// ============================================

ob_start();
$judul_cetak = "LAPORAN LANGGANAN MEMBER KESELURUHAN";
$jumlah_data_cetak = count($print_bookings);
include '../includes/kop_laporan.php';
$kop_html = ob_get_clean();

$html = $kop_html . '
<div style="line-height: 9px;">&nbsp;</div>
<table border="1" cellspacing="0" cellpadding="6" style="width: 100%; font-family: Arial, Helvetica, sans-serif; font-size: 8px;">
    <thead>
        <tr style="background-color: #F3F4F6; font-weight: bold;">
            <th align="center" valign="middle" style="width: 5%;">No.</th>
            <th align="center" valign="middle" style="width: 25%;">Customer</th>
            <th align="center" valign="middle" style="width: 23%;">Tipe Member</th>
            <th align="center" valign="middle" style="width: 17%;">Periode</th>
            <th align="center" valign="middle" style="width: 15%;">Metode Bayar</th>
            <th align="center" valign="middle" style="width: 15%;">Total Bayar</th>
        </tr>
    </thead>
    <tbody>';

if ($total_transaksi > 0) {
    $no = 1;
    foreach ($print_bookings as $b) {
        $expired = isExpired($b['Tanggal_Selesai'] ?? null);
        
        $tanggal_mulai = ($b['Tanggal_Mulai'] instanceof DateTime) ? $b['Tanggal_Mulai']->format('d M Y') : '-';
        $tanggal_selesai = ($b['Tanggal_Selesai'] instanceof DateTime) ? $b['Tanggal_Selesai']->format('d M Y') : '-';

        $periode_html = $tanggal_mulai . ' - ' . $tanggal_selesai;
        if ($expired) {
            $periode_html .= '<br><span style="color: #EF4444; font-size: 7px;">Masa berlaku habis</span>';
        }

        $html .= '
        <tr>
            <td align="center" valign="middle" style="width: 5%;">' . $no++ . '</td>
            <td align="left" valign="middle" style="width: 25%;"><strong>' . htmlspecialchars($b['Nama_Customer']) . '</strong><br><span style="color: #6B7280;">' . htmlspecialchars($b['Email'] ?? '') . '</span></td>
            <td align="left" valign="middle" style="width: 23%;"><strong>' . htmlspecialchars($b['Nama_Tipe']) . ' Member</strong><br><span style="color: #6B7280;">' . rupiahFormat($b['Harga_Member']) . ' | Potongan ' . rupiahFormat($b['Potongan_Harga']) . '</span></td>
            <td align="center" valign="middle" style="width: 17%;">' . $periode_html . '</td>
            <td align="left" valign="middle" style="width: 15%;">' . htmlspecialchars($b['Metode_Pembayaran']) . '</td>
            <td align="right" valign="middle" style="width: 15%;">' . rupiahFormat($b['Total_Bayar']) . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="6" style="text-align: center; padding: 20px;">Tidak ada data transaksi langganan</td></tr>';
}

$html .= '
        <tr style="font-weight: bold; background-color: #E5E7EB;">
            <td colspan="5" align="center" valign="middle" style="width: 85%;">TOTAL PENDAPATAN</td>
            <td align="right" valign="middle" style="width: 15%;">' . rupiahFormat($total_pendapatan) . '</td>
        </tr>
    </tbody>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');
$pdf->Output('Laporan_Langganan_Member.pdf', 'D');