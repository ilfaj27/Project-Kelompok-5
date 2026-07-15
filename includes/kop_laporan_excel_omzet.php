<?php
// ============================================================
// KOP SURAT RESMI KHUSUS EXCEL OMZET (SISTEM LOCAL FILE PROTOCOL)
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

$judul_tampil = $judul_cetak ?? 'LAPORAN REKAPITULASI KESELURUHAN';
$total_tampil = $jumlah_data_cetak ?? $total_transaksi ?? 0;

// URL absolut file:/// agar gambar otomatis terunduh di Excel desktop Anda tanpa diblokir
$logo_real_path = '';
$physical_path = realpath(__DIR__ . '/../asset/image/logo3.jpg');

if ($physical_path && file_exists($physical_path)) {
    $clean_path = str_replace('\\', '/', $physical_path);
    $logo_real_path = 'file:///' . $clean_path;
} else {
    $logo_real_path = 'http://' . $_SERVER['HTTP_HOST'] . '/Project-Kelompok-5/asset/image/logo3.jpeg';
}
?>

<!-- TAMPILAN KOP SURAT KHUSUS EXCEL -->
<table cellpadding="5" cellspacing="0"
    style="width: 100%; border: none; text-align: center; font-family: Arial, Helvetica, sans-serif;">
    <tr>
        <!-- 1. Spacer Kiri (Membatasi kolom A dan B agar logo terdorong ke tengah) -->
        <td colspan="2" style="border: none !important;"></td>

        <!-- 2. Kolom Logo (Diikat khusus di Kolom C, rata kanan) -->
        <td align="right" valign="middle" style="width: 18%; border: none !important; padding-bottom: 6px;">
            <img src="<?= $logo_real_path ?>" height="35" width="114"
                style="height: 38px; width: 114px; display: block;" alt="Logo" onerror="this.style.display='none'">
        </td>

        <!-- 3. Kolom Nama (Diubah menjadi colspan="3" agar pas menutup lebar 6 kolom) -->
        <td colspan="3" align="left" valign="middle"
            style="border: none !important; font-family: 'Times New Roman', Times, serif; font-size: 28px; font-weight: bold; letter-spacing: 2px; color: #000000; text-transform: uppercase; padding-left: 12px; padding-bottom: 6px;">
            &nbsp;&nbsp;HOOPBALL
        </td>
    </tr>
    <tr>
        <td colspan="6"
            style="text-align: center; font-size: 10px; color: #111827; line-height: 1.5; font-weight: bold;">
            Politeknik Astra, Delta Silicon II, Cibatu, Cikarang Selatan, Bekasi, Jawa Barat 17530<br>
            Email: info@hoopball.id | No.Telp: 0812-3456-7890
        </td>
    </tr>
    <tr>
        <td colspan="6" style="padding: 6px 0 0 0; border: none !important;">
            <hr style="border: none; border-top: 1.5px solid #000000; margin: 0;">
        </td>
    </tr>
    <!-- BARIS SPACER KHUSUS EXCEL (Memaksa jarak renggang 12px secara presisi) -->
    <tr>
        <td colspan="6" style="height: 12px; line-height: 12px; border: none !important; background-color: #FFFFFF;">
            &nbsp;</td>
    </tr>
    <tr>
        <td colspan="6"
            style="text-align: center; font-family: 'Times New Roman', Times, serif; font-size: 15px; font-weight: bold; color: #000000; text-transform: uppercase; letter-spacing: 0.5px; border: none !important;">
            <?= htmlspecialchars($judul_tampil) ?>
        </td>
    </tr>
    <tr>
        <!-- Diubah dari colspan="7" menjadi colspan="6" agar pas sejajar dengan tabel 6 kolom -->
        <td colspan="6"
            style="text-align: center; font-size: 9px; color: #374151; font-weight: bold; padding-top: 4px;">
            Tanggal Cetak: <?= $tgl_cetak ?> | Total Data: <?= $total_tampil ?> Transaksi / Baris <br>
            Periode: <?= $periode_lbl ?>
        </td>
    </tr>
</table>
<br>