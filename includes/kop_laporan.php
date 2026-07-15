<?php
// ============================================================
// KOP SURAT LAPORAN RESMI (REUSABLE MODUL)
// ============================================================
date_default_timezone_set('Asia/Jakarta');
$bulan_indo = [
    1 => 'Januari',
    'Februari',
    'Maret',
    'April',
    'Mei',
    'Juni',
    'Juli',
    'Agustus',
    'September',
    'Oktober',
    'November',
    'Desember'
];
$tgl_cetak = date('d ') . $bulan_indo[(int) date('m')] . date(' Y, H:i') . ' WIB';

// Konversi format periode filter laporan secara otomatis
$periode_lbl = 'Semua Waktu';
if (isset($filter_type)) {
    if ($filter_type === 'today') {
        $periode_lbl = 'Hari Ini (' . date('d ') . $bulan_indo[(int) date('m')] . date(' Y') . ')';
    } elseif ($filter_type === 'week') {
        $periode_lbl = '7 Hari Terakhir';
    } elseif ($filter_type === 'month') {
        $periode_lbl = 'Bulan Ini (' . $bulan_indo[(int) date('m')] . date(' Y') . ')';
    } elseif ($filter_type === 'year') {
        $periode_lbl = 'Tahun Ini (' . date('Y') . ')';
    } elseif ($filter_type === 'custom' && !empty($start_date) && !empty($end_date)) {
        $start_dt = new DateTime($start_date);
        $end_dt = new DateTime($end_date);
        $periode_lbl = $start_dt->format('d ') . $bulan_indo[(int) $start_dt->format('m')] . $start_dt->format(' Y') . ' s/d ' .
            $end_dt->format('d ') . $bulan_indo[(int) $end_dt->format('m')] . $end_dt->format(' Y');
    }
}

// ---------------------------------------------------------
// PENGATURAN DINAMIS YANG DITANGKAP DARI HALAMAN PEMANGGIL
// ---------------------------------------------------------
$judul_tampil = $judul_cetak ?? 'LAPORAN REKAPITULASI KESELURUHAN';
$total_tampil = $jumlah_data_cetak ?? $total_transaksi ?? 0;

// Penyesuaian Path Logo otomatis agar terbaca sempurna di layar monitor & PDF
$logo_real_path = '../asset/image/logo3.jpeg';

if (defined('K_PATH_IMAGES') || isset($pdf)) {
    // Fungsi realpath(__DIR__) otomatis mendeteksi lokasi absolut harddisk lokal (XAMPP/Linux)
    $logo_real_path = realpath(__DIR__ . '/../asset/image/logo3.jpeg');
}
// ---------------------------------------------------------
?>

<!-- TAMPILAN KOP SURAT (Datar Tanpa Nested Table / Kebal Overlap di TCPDF) -->
<table class="print-only" cellpadding="2" cellspacing="0"
    style="width: 100%; border: none; text-align: center; font-family: Arial, Helvetica, sans-serif;">
    <tr>
        <!-- Kolom Logo (Kiri) -->
        <td style="width: 40%; text-align: right; vertical-align: middle;">
            <img src="<?= $logo_real_path ?>" style="height: 38px; width: auto;" alt="Logo"
                onerror="this.style.display='none'">
        </td>
        <!-- Kolom Nama Perusahaan (Kanan) -->
        <td
            style="width: 60%; text-align: left; vertical-align: middle; font-family: 'Times New Roman', Times, serif; font-size: 26px; font-weight: bold; letter-spacing: 2px; color: #000000; text-transform: uppercase;">
            HOOPBALL
        </td>
    </tr>
    <tr>
        <td colspan="2"
            style="text-align: center; font-size: 9px; color: #111827; line-height: 1.4; font-weight: bold;">
            Politeknik Astra, Delta Silicon II, Cibatu, Cikarang Selatan, Bekasi, Jawa Barat 17530<br>
            Email: info@hoopball.id | No.Telp: 0812-3456-7890
        </td>
    </tr>
    <tr>
        <td colspan="2" style="padding: 6px 0;">
            <hr style="border: none; border-top: 1.5px solid #000000; margin: 0;">
        </td>
    </tr>
    <tr>
        <td colspan="2"
            style="text-align: center; font-family: 'Times New Roman', Times, serif; font-size: 15px; font-weight: bold; color: #000000; text-transform: uppercase; letter-spacing: 0.5px; padding-top: 4px;">
            <?= htmlspecialchars($judul_tampil) ?>
        </td>
    </tr>
    <tr>
        <td colspan="2"
            style="text-align: center; font-size: 9px; color: #374151; font-weight: bold; padding-top: 4px;">
            Tanggal Cetak: <?= $tgl_cetak ?> | Total Data: <?= $total_tampil ?> Transaksi / Baris <br>
            Periode: <?= $periode_lbl ?>
        </td>
    </tr>
</table>
<br>