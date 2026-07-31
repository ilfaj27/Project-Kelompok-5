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
$tanggal_unduh = date('dmy');
$nama_file = 'LaporanSewaLapangan_' . $tanggal_unduh . '.xls';

header("Content-type: application/vnd-ms-excel");
header("Content-Disposition: attachment; filename=" . $nama_file);
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

// ============================================
// AMBIL DATA BOOKING & GROUPING PRESISI EXCEL
// ============================================
$raw_report_bookings = [];
$print_sql = "SELECT 
        ID_Booking, Tanggal_Booking, Metode_Pembayaran, Total_Bayar, Status,
        Nama_Customer, Nama_Lapangan, Harga_Sewa, Tanggal_Main, Jam_Mulai, 
        Jam_Selesai, Nama_Promo, Diskon_Promo, Nama_Tipe, Potongan_Member, Nominal_Refund
    FROM dbo.fn_GetBookingReport(?, ?, ?, ?, ?)
    WHERE Status <> 0
    ORDER BY Tanggal_Booking DESC, ID_Booking DESC";

$q_print = safeQuery($conn, $print_sql, $params);
if ($q_print !== null) {
    while ($row = sqlsrv_fetch_array($q_print, SQLSRV_FETCH_ASSOC)) {
        $raw_report_bookings[] = $row;
    }
    sqlsrv_free_stmt($q_print);
}

// --- LOGIKA GROUPING TRANSAKSI EXCEL ---
$grouped_report = [];
foreach ($raw_report_bookings as $b) {
    $tgl_booking_str = ($b['Tanggal_Booking'] instanceof DateTime) ? $b['Tanggal_Booking']->format('Y-m-d H:i:s') : strval($b['Tanggal_Booking']);
    $tgl_main_str = ($b['Tanggal_Main'] instanceof DateTime) ? $b['Tanggal_Main']->format('Y-m-d') : strval($b['Tanggal_Main']);

    $group_key = 'grp_' . ($b['Nama_Customer'] ?? '') . '_' . ($b['Nama_Lapangan'] ?? '') . '_' . $tgl_main_str . '_' . $tgl_booking_str . '_' . ($b['Status'] ?? '');

    $harga_sewa_slot = floatval($b['Harga_Sewa'] ?? 120000);
    $diskon_member_slot = floatval($b['Potongan_Member'] ?? 0);
    
    // HITUNG PROMO (PERSEN % VS NOMINAL RP)
    $raw_promo_diskon = floatval($b['Diskon_Promo'] ?? 0);
    $diskon_promo_slot = 0;
    if ($raw_promo_diskon > 0) {
        if ($raw_promo_diskon <= 100) {
            $diskon_promo_slot = ($harga_sewa_slot * $raw_promo_diskon) / 100.0;
        } else {
            $diskon_promo_slot = $raw_promo_diskon;
        }
    }

    if (!isset($grouped_report[$group_key])) {
        $grouped_report[$group_key] = $b;
        $grouped_report[$group_key]['Min_Jam_Mulai'] = $b['Jam_Mulai'];
        $grouped_report[$group_key]['Max_Jam_Selesai'] = $b['Jam_Selesai'];
        $grouped_report[$group_key]['Base_Harga_Sewa_Total'] = $harga_sewa_slot;
        $grouped_report[$group_key]['Diskon_Member_Applied'] = $diskon_member_slot;
        $grouped_report[$group_key]['Diskon_Promo_Applied'] = $diskon_promo_slot;
    } else {
        $grouped_report[$group_key]['Base_Harga_Sewa_Total'] += $harga_sewa_slot;

        if ($diskon_promo_slot > 0 && $raw_promo_diskon <= 100) {
            $grouped_report[$group_key]['Diskon_Promo_Applied'] += $diskon_promo_slot;
        }

        $curr_min = $grouped_report[$group_key]['Min_Jam_Mulai'];
        $curr_max = $grouped_report[$group_key]['Max_Jam_Selesai'];

        $jam_m_new = ($b['Jam_Mulai'] instanceof DateTime) ? $b['Jam_Mulai']->format('H:i') : substr(strval($b['Jam_Mulai']), 0, 5);
        $jam_m_curr = ($curr_min instanceof DateTime) ? $curr_min->format('H:i') : substr(strval($curr_min), 0, 5);

        $jam_s_new = ($b['Jam_Selesai'] instanceof DateTime) ? $b['Jam_Selesai']->format('H:i') : substr(strval($b['Jam_Selesai']), 0, 5);
        $jam_s_curr = ($curr_max instanceof DateTime) ? $curr_max->format('H:i') : substr(strval($curr_max), 0, 5);

        if ($jam_m_new < $jam_m_curr) {
            $grouped_report[$group_key]['Min_Jam_Mulai'] = $b['Jam_Mulai'];
        }
        if ($jam_s_new > $jam_s_curr) {
            $grouped_report[$group_key]['Max_Jam_Selesai'] = $b['Jam_Selesai'];
        }
    }
}

// Hitung Total Bayar Gabungan Presisi
foreach ($grouped_report as $key => $g) {
    $base = $g['Base_Harga_Sewa_Total'];
    $disc = max($g['Diskon_Member_Applied'], $g['Diskon_Promo_Applied']);
    $grouped_report[$key]['Total_Bayar'] = max(0, $base - $disc);
}

$print_bookings = array_values($grouped_report);

// URUTKAN PRESISI: Status Selesai (2) / Berhasil (1) Dulu, Lalu Waktu Transaksi Terbaru
usort($print_bookings, function ($a, $b) {
    $prio = [2 => 1, 1 => 2, 3 => 3, 0 => 4];
    $wA = $prio[$a['Status']] ?? 99;
    $wB = $prio[$b['Status']] ?? 99;

    if ($wA !== $wB) return $wA <=> $wB;

    $dA = is_object($a['Tanggal_Booking']) ? $a['Tanggal_Booking']->getTimestamp() : strtotime($a['Tanggal_Booking']);
    $dB = is_object($b['Tanggal_Booking']) ? $b['Tanggal_Booking']->getTimestamp() : strtotime($b['Tanggal_Booking']);

    return $dB <=> $dA;
});

$total_transaksi = count($print_bookings);

// ============================================
// STRUKTUR HTML UNTUK EXCEL
// ============================================
ob_start();
$judul_cetak = "LAPORAN SEWA LAPANGAN KESELURUHAN";
$jumlah_data_cetak = count($print_bookings);
include '../includes/kop_laporan_excel.php';
$kop_html = ob_get_clean();

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
        $disc_mem = floatval($b['Diskon_Member_Applied'] ?? $b['Potongan_Member'] ?? 0);
        $disc_pro = floatval($b['Diskon_Promo_Applied'] ?? $b['Diskon_Promo'] ?? 0);

        if ($disc_mem > 0) {
            $diskon_info = 'Member ' . htmlspecialchars($b['Nama_Tipe'] ?? 'Aktif') . ' (-' . rupiahFormat($disc_mem) . ')';
        } elseif ($disc_pro > 0) {
            $diskon_info = htmlspecialchars($b['Nama_Promo'] ?? 'Promo') . ' (-' . rupiahFormat($disc_pro) . ')';
        }

        $tanggal_main = ($b['Tanggal_Main'] instanceof DateTime) ? $b['Tanggal_Main']->format('d M Y') : '-';
        $jam_mulai = ($b['Min_Jam_Mulai'] instanceof DateTime) ? $b['Min_Jam_Mulai']->format('H:i') : (is_object($b['Jam_Mulai']) ? $b['Jam_Mulai']->format('H:i') : substr(strval($b['Jam_Mulai']), 0, 5));
        $jam_selesai = ($b['Max_Jam_Selesai'] instanceof DateTime) ? $b['Max_Jam_Selesai']->format('H:i') : (is_object($b['Jam_Selesai']) ? $b['Jam_Selesai']->format('H:i') : substr(strval($b['Jam_Selesai']), 0, 5));
        $tanggal_booking = ($b['Tanggal_Booking'] instanceof DateTime) ? $b['Tanggal_Booking']->format('d M Y') : '-';

        $total_bayar_html = rupiahFormat($b['Total_Bayar']);
        if ($b['Status'] == 3) {
            $refund_val = floatval($b['Total_Bayar']) * 0.50; // Hitung 50% Refund dari Total Bayar Gabungan
            if ($refund_val > 0) {
                $total_bayar_html .= '<br><span style="color: #EF4444; font-size: 9px; font-weight: bold;">Refund: ' . rupiahFormat($refund_val) . '</span>';
            }
        }

        $html .= '
        <tr>
            <td align="center" valign="center" style="width: 5%; vertical-align: middle;">' . $no++ . '</td>
            <td align="left" valign="center" style="width: 22%; vertical-align: middle;"><strong>' . htmlspecialchars($b['Nama_Lapangan']) . '</strong><br><span style="color: #6B7280; font-size: 9px;">' . rupiahFormat($b['Harga_Sewa']) . '/jam</span></td>
            <td align="left" valign="center" style="width: 18%; vertical-align: middle;">' . $tanggal_main . '<br><span style="color: #6B7280; font-size: 9px;">' . $jam_mulai . ' - ' . $jam_selesai . ' WIB</span></td>
            <td align="center" valign="center" style="width: 13%; vertical-align: middle;">' . $tanggal_booking . '</td>
            <td align="left" valign="center" style="width: 12%; vertical-align: middle;">' . htmlspecialchars($b['Metode_Pembayaran']) . '</td>
            <td align="left" valign="center" style="width: 15%; vertical-align: middle;">' . $diskon_info . '</td>
            <td align="right" valign="center" style="width: 15%; vertical-align: middle;">' . $total_bayar_html . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="7" style="text-align: center; padding: 20px;">Tidak ada data booking</td></tr>';
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

echo $html;