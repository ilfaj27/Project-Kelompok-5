<?php
session_start();
include '../includes/config.php';

require_once '../TCPDF-main/tcpdf.php';

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'pemilik') {
    header("Location: ../login/login.php");
    exit();
}

// ============================================
// FORMAT RUPIAH
// ============================================
function rupiahFormat($n)
{
    return 'Rp ' . number_format($n, 0, ',', '.');
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
$alat_filter = $_GET['alat'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

$where_clauses = ["1=1"];
$params = [];

if ($filter_type === 'today') {
    $where_clauses[] = "CAST(ba.Tanggal_Beli AS DATE) = CAST(GETDATE() AS DATE)";
} elseif ($filter_type === 'week') {
    $where_clauses[] = "ba.Tanggal_Beli >= DATEADD(day, -7, CAST(GETDATE() AS DATE))";
} elseif ($filter_type === 'month') {
    $where_clauses[] = "MONTH(ba.Tanggal_Beli) = MONTH(GETDATE()) AND YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())";
} elseif ($filter_type === 'year') {
    $where_clauses[] = "YEAR(ba.Tanggal_Beli) = YEAR(GETDATE())";
} elseif ($filter_type === 'custom' && !empty($start_date) && !empty($end_date)) {
    $where_clauses[] = "ba.Tanggal_Beli BETWEEN ? AND ?";
    $params[] = $start_date;
    $params[] = $end_date;
}

if ($alat_filter !== 'all') {
    $where_clauses[] = "EXISTS (SELECT 1 FROM Detail_Beli_Alat dba_f WHERE dba_f.ID_Beli = ba.ID_Beli AND dba_f.ID_Alat = ?)";
    $params[] = $alat_filter;
}

if ($status_filter !== 'all') {
    $where_clauses[] = "ba.Status = ?";
    $params[] = $status_filter;
}

$where_sql = implode(" AND ", $where_clauses);

// ============================================
// AMBIL DATA STATISTIK
// ============================================
$total_pendapatan = 0;
$stats_sql = "SELECT SUM(ba.Total_Bayar) as pendapatan FROM Beli_Alat ba WHERE " . $where_sql;
$q = safeQuery($conn, $stats_sql, $params);
$d = safeFetch($q);
if ($d) {
    $total_pendapatan = $d['pendapatan'] ?? 0;
}

// ============================================
// AMBIL DATA TRANSAKSI
// ============================================
$print_bookings = [];
$print_sql = "SELECT 
    ba.ID_Beli,
    ba.Tanggal_Beli,
    ba.Metode_Pembayaran,
    ba.Total_Bayar,
    ba.Status,
    c.Nama_Customer,
    c.Email
FROM Beli_Alat ba
LEFT JOIN Customer c ON ba.ID_Customer = c.ID_Customer
WHERE " . $where_sql . "
ORDER BY ba.Tanggal_Beli DESC, ba.ID_Beli DESC";

$q_print = safeQuery($conn, $print_sql, $params);
if ($q_print !== null) {
    while ($row = sqlsrv_fetch_array($q_print, SQLSRV_FETCH_ASSOC)) {
        $print_bookings[] = $row;
    }
}
$total_transaksi = count($print_bookings);

// ============================================
// AMBIL DETAIL ITEM PER TRANSAKSI
// ============================================
$detail_items = [];
$detail_sql = "SELECT 
    dba.ID_Beli,
    a.Nama_Alat,
    dba.Jumlah
FROM Detail_Beli_Alat dba
LEFT JOIN Alat a ON dba.ID_Alat = a.ID_Alat
LEFT JOIN Beli_Alat ba ON dba.ID_Beli = ba.ID_Beli
WHERE " . $where_sql . "
ORDER BY dba.ID_Beli, a.Nama_Alat";

$q_detail = safeQuery($conn, $detail_sql, $params);
if ($q_detail !== null) {
    while ($row = sqlsrv_fetch_array($q_detail, SQLSRV_FETCH_ASSOC)) {
        $detail_items[$row['ID_Beli']][] = $row;
    }
}

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
$pdf->SetTitle('Laporan Pembelian Alat');
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(true);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20.3);
$pdf->AddPage();

// ============================================
// STRUKTUR HTML UNTUK PDF
// ============================================
ob_start();
$judul_cetak = "LAPORAN PEMBELIAN ALAT KESELURUHAN";
$jumlah_data_cetak = count($print_bookings);
include '../includes/kop_laporan.php';
$kop_html = ob_get_clean();

$html = $kop_html . '
<div style="line-height: 9px;">&nbsp;</div>
<table border="1" cellspacing="0" cellpadding="6" style="width: 100%; font-family: Arial, Helvetica, sans-serif; font-size: 8px;">
    <thead>
        <tr style="background-color: #F3F4F6; font-weight: bold;">
            <th align="center" valign="middle" style="width: 5%;">No.</th>
            <th align="center" valign="middle" style="width: 20%;">Customer</th>
            <th align="center" valign="middle" style="width: 23%;">Item Dibeli</th>
            <th align="center" valign="middle" style="width: 12%;">Tanggal</th>
            <th align="center" valign="middle" style="width: 12%;">Metode Bayar</th>
            <th align="center" valign="middle" style="width: 13%;">Status</th>
            <th align="center" valign="middle" style="width: 15%;">Total Bayar</th>
        </tr>
    </thead>
    <tbody>';

if ($total_transaksi > 0) {
    $no = 1;
    foreach ($print_bookings as $b) {
        $status_label = 'Menunggu';
        $status_color = '#D97706';
        if ($b['Status'] == 1) {
            $status_label = 'Berhasil';
            $status_color = '#10B981';
        } elseif ($b['Status'] == 2) {
            $status_label = 'Ditolak';
            $status_color = '#EF4444';
        }

        $items = $detail_items[$b['ID_Beli']] ?? [];
        $item_html = '';
        if (count($items) > 0) {
            foreach ($items as $it) {
                $item_html .= htmlspecialchars($it['Nama_Alat']) . ' x' . $it['Jumlah'] . '<br>';
            }
        } else {
            $item_html = '-';
        }

        $tanggal_beli = ($b['Tanggal_Beli'] instanceof DateTime) ? $b['Tanggal_Beli']->format('d M Y') : '-';

        $html .= '
        <tr>
            <td align="center" valign="middle" style="width: 5%;">' . $no++ . '</td>
            <td align="left" valign="middle" style="width: 20%;"><strong>' . htmlspecialchars($b['Nama_Customer']) . '</strong><br><span style="color: #6B7280;">' . htmlspecialchars($b['Email'] ?? '') . '</span></td>
            <td align="left" valign="middle" style="width: 23%;">' . $item_html . '</td>
            <td align="center" valign="middle" style="width: 12%;">' . $tanggal_beli . '</td>
            <td align="left" valign="middle" style="width: 12%;">' . htmlspecialchars($b['Metode_Pembayaran']) . '</td>
            <td align="center" valign="middle" style="width: 13%;"><span style="color: ' . $status_color . '; font-weight: bold;">' . $status_label . '</span></td>
            <td align="right" valign="middle" style="width: 15%;">' . rupiahFormat($b['Total_Bayar']) . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="7" style="text-align: center; padding: 20px;">Tidak ada data transaksi pembelian</td></tr>';
}

$html .= '
        <tr style="font-weight: bold; background-color: #E5E7EB;">
            <td colspan="6" align="center" valign="middle" style="width: 85%;">TOTAL PENDAPATAN</td>
            <td align="right" valign="middle" style="width: 15%;">' . rupiahFormat($total_pendapatan) . '</td>
        </tr>
    </tbody>
</table>';

$pdf->writeHTML($html, true, false, true, false, '');

$tanggal_unduh = date('dmy');
$nama_file = 'LaporanPembelianAlat_' . $tanggal_unduh . '.pdf';
$pdf->Output($nama_file, 'D');