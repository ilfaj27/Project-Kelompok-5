<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['login']) || $_SESSION['role'] !== 'pemilik') {
    header("Location: ../login/login.php");
    exit();
}

// ============================================
// TRIGGER DOWNLOAD EXCEL (.XLS)
// ============================================
header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=Laporan_Sewa_Lapangan.xls");
header("Pragma: no-cache");
header("Expires: 0");

// ============================================
// FORMAT RUPIAH & TANGGAL
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
$lapangan_filter = $_GET['lapangan'] ?? 'all';
$status_filter = $_GET['status'] ?? 'all';

$p_filter_type = $filter_type;
$p_start_date = (!empty($start_date) && $filter_type === 'custom') ? $start_date : null;
$p_end_date = (!empty($end_date) && $filter_type === 'custom') ? $end_date : null;
$p_lapangan = ($lapangan_filter !== 'all') ? (int) $lapangan_filter : null;
$p_status = ($status_filter !== 'all') ? (int) $status_filter : null;

$params = array(
    array($p_filter_type, SQLSRV_PARAM_IN),
    array($p_start_date, SQLSRV_PARAM_IN),
    array($p_end_date, SQLSRV_PARAM_IN),
    array($p_lapangan, SQLSRV_PARAM_IN),
    array($p_status, SQLSRV_PARAM_IN)
);

// ============================================
// AMBIL DATA STATISTIK & TRANSAKSI
// ============================================
$total_omzet = 0;
$total_refund = 0;
$stats_sql = "SELECT omzet, refund FROM dbo.fn_GetBookingStats(?, ?, ?, ?, ?)";
$q = safeQuery($conn, $stats_sql, $params);
$d = safeFetch($q);
if ($d) {
    $total_omzet = $d['omzet'] ?? 0;
    $total_refund = $d['refund'] ?? 0;
}
$omzet_bersih = $total_omzet - $total_refund;

$print_bookings = [];
$print_sql = "SELECT 
        ID_Booking, Tanggal_Booking, Metode_Pembayaran, Total_Bayar, Status,
        Nama_Customer, Nama_Lapangan, Harga_Sewa, Tanggal_Main, Jam_Mulai, 
        Jam_Selesai, Nama_Promo, Diskon_Promo, Nama_Tipe, Potongan_Member, Nominal_Refund
    FROM dbo.fn_GetBookingReport(?, ?, ?, ?, ?)
    WHERE Status <> 0
    ORDER BY 
        CASE WHEN Status = 2 THEN 0 ELSE 1 END ASC, 
        Tanggal_Booking DESC, 
        ID_Booking DESC";

$q_print = safeQuery($conn, $print_sql, $params);
if ($q_print !== null) {
    while ($row = sqlsrv_fetch_array($q_print, SQLSRV_FETCH_ASSOC)) {
        $print_bookings[] = $row;
    }
}
$total_transaksi = count($print_bookings);

// ============================================
// STRUKTUR HTML UNTUK EXCEL (SINKRON DENGAN KOP)
// ============================================

// 1. Rekam output Kop Laporan secara otomatis dari file kop_laporan.php Anda
ob_start();
$judul_cetak = "LAPORAN SEWA LAPANGAN KESELURUHAN";
$jumlah_data_cetak = count($print_bookings);
include '../includes/kop_laporan_excel.php';
$kop_html = ob_get_clean();

// 2. Gabungkan isi Kop Laporan dengan struktur tabel transaksi di bawah ini
$html = $kop_html . '
<table border="1" cellspacing="0" cellpadding="6" style="width: 100%; font-family: Arial, Helvetica, sans-serif; font-size: 10px;">
    <thead>
        <tr style="background-color: #F3F4F6; font-weight: bold; text-align: center; vertical-align: middle;">
            <th style="width: 5%;">No</th>
            <th style="width: 22%;">Lapangan</th>
            <th style="width: 18%;">Jadwal Main</th>
            <th style="width: 13%;">Tanggal Booking</th>
            <th style="width: 12%;">Metode Bayar</th>
            <th style="width: 15%;">Diskon</th>
            <th style="width: 15%;">Total Bayar</th>
        </tr>
    </thead>
    <tbody>';

if ($total_transaksi > 0) {
    $no = 1;
    foreach ($print_bookings as $b) {
        $diskon_info = '-';
        if (!empty($b['Nama_Tipe'])) {
            $diskon_info = 'Member ' . htmlspecialchars($b['Nama_Tipe']) . ' (-' . rupiahFormat($b['Potongan_Member']) . ')';
        } elseif (!empty($b['Nama_Promo'])) {
            $diskon_info = htmlspecialchars($b['Nama_Promo']) . ' (-' . rupiahFormat($b['Diskon_Promo']) . ')';
        }

        $tanggal_main = ($b['Tanggal_Main'] instanceof DateTime) ? $b['Tanggal_Main']->format('d M Y') : '-';
        $jam_mulai = ($b['Jam_Mulai'] instanceof DateTime) ? $b['Jam_Mulai']->format('H:i') : '-';
        $jam_selesai = ($b['Jam_Selesai'] instanceof DateTime) ? $b['Jam_Selesai']->format('H:i') : '-';
        $tanggal_booking = ($b['Tanggal_Booking'] instanceof DateTime) ? $b['Tanggal_Booking']->format('d M Y') : '-';

        $total_bayar_html = rupiahFormat($b['Total_Bayar']);
        if ($b['Status'] == 3 && $b['Nominal_Refund'] > 0) {
            // Menggunakan ganti baris <br> dan warna merah khusus untuk nominal refund
            $total_bayar_html .= '<br><span style="color: #EF4444; font-size: 9px; font-weight: bold;">Refund: ' . rupiahFormat($b['Nominal_Refund']) . '</span>';
        }
        $html .= '
        <tr>
            <td align="center" valign="center" style="width: 5%; vertical-align: middle;">' . $no++ . '</td>
            <!-- Menggunakan ganti baris <br> dan font abu-abu agar bersusun rapi (Sama dengan PDF) -->
            <td align="left" valign="center" style="width: 22%; vertical-align: middle;"><strong>' . htmlspecialchars($b['Nama_Lapangan']) . '</strong><br><span style="color: #6B7280; font-size: 9px;">' . rupiahFormat($b['Harga_Sewa']) . '/jam</span></td>
            <!-- Menggunakan ganti baris <br> dan font abu-abu agar bersusun rapi (Sama dengan PDF) -->
            <td align="left" valign="center" style="width: 18%; vertical-align: middle;">' . $tanggal_main . '<br><span style="color: #6B7280; font-size: 9px;">' . $jam_mulai . ' - ' . $jam_selesai . '</span></td>
            <td align="center" valign="center" style="width: 13%; vertical-align: middle;">' . $tanggal_booking . '</td>
            <td align="left" valign="center" style="width: 12%; vertical-align: middle;">' . htmlspecialchars($b['Metode_Pembayaran']) . '</td>
            <td align="left" valign="center" style="width: 15%; vertical-align: middle;">' . $diskon_info . '</td>
            <td align="right" valign="center" style="width: 15%; vertical-align: middle;">' . $total_bayar_html . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="9" style="text-align: center; padding: 20px;">Tidak ada data booking</td></tr>';
}

$html .= '
        <tr style="font-weight: bold; background-color: #F3F4F6;">
            <td colspan="6" align="center" valign="middle" style="width: 85%;">TOTAL PENDAPATAN</td>
            <td align="right" valign="middle" style="width: 15%;">' . rupiahFormat($total_omzet) . '</td>
        </tr>
        <tr style="font-weight: bold; background-color: #FFFFFF; color: #EF4444;">
            <td colspan="6" align="center" valign="middle" style="width: 85%;">TOTAL REFUND</td>
            <td align="right" valign="middle" style="width: 15%;">- ' . rupiahFormat($total_refund) . '</td>
        </tr>
        <tr style="font-weight: bold; background-color: #E5E7EB;">
            <td colspan="6" align="center" valign="middle" style="width: 85%;">OMZET BERSIH</td>
            <td align="right" valign="middle" style="width: 15%;">' . rupiahFormat($omzet_bersih) . '</td>
        </tr>
    </tbody>
</table>';

// Cetak ke layar agar browser langsung men-download sebagai file .xls
echo $html;