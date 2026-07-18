<?php
/**
 * =============================================================================
 * HEAD COMMON - HoopBall
 * =============================================================================
 * Include file ini di <head> setiap halaman untuk favicon & meta tags
 * 
 * Cara pakai:
 *   <?php $base_path = '../'; include '../includes/favicon.php'; ?>
 *   (sesuaikan $base_path: '' untuk root, '../' untuk subfolder)
 * =============================================================================
 */

// Pastikan base_path terdefinisi
if (!isset($base_path)) {
    $base_path = '';
}

// Deteksi depth otomatis (fallback jika $base_path tidak di-set)
$current_file = $_SERVER['PHP_SELF'] ?? '';
$folder_depth = substr_count(dirname($current_file), '/') - 1;
if ($folder_depth > 0 && empty($base_path)) {
    $base_path = str_repeat('../', $folder_depth);
}
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">

<!-- =============================================================================
     FAVICON - HoopBall Logo (Menggunakan Logo Anda: asset/image/Favicon/image.png)
     ============================================================================= -->
<!-- Favicon utama (semua ukuran pakai logo yang sama) -->
<link rel="icon" type="image/png" sizes="32x32" href="<?= $base_path ?>asset/image/Favicon/image.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= $base_path ?>asset/image/Favicon/image.png">
<!-- Apple Touch Icon (iPhone, iPad) -->
<link rel="apple-touch-icon" sizes="180x180" href="<?= $base_path ?>asset/image/Favicon/image.png">
<!-- Android/Chrome -->
<link rel="manifest" href="<?= $base_path ?>asset/image/Favicon/site.webmanifest">
<!-- Legacy IE -->
<link rel="shortcut icon" href="<?= $base_path ?>asset/image/Favicon/image.png" type="image/png">
<!-- Safari Pinned Tab -->
<link rel="mask-icon" href="<?= $base_path ?>asset/image/Favicon/image.png" color="#FF4500">

<!-- =============================================================================
     META TAGS SEO & THEME
     ============================================================================= -->
<meta name="theme-color" content="#FF4500">
<meta name="msapplication-TileColor" content="#FF4500">
<meta name="description" content="HoopBall - Platform booking lapangan basket terbaik. Sewa lapangan, beli perlengkapan, dan jadilah member untuk diskon menarik.">
<meta name="keywords" content="HoopBall, booking lapangan basket, sewa lapangan, perlengkapan basket, olahraga">
<meta name="author" content="HoopBall Team">

<!-- =============================================================================
     OPEN GRAPH (Facebook, WhatsApp, dll)
     ============================================================================= -->
<meta property="og:type" content="website">
<meta property="og:site_name" content="HoopBall">
<meta property="og:image" content="<?= $base_path ?>asset/image/Favicon/image.png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">

<!-- =============================================================================
     FONTS
     ============================================================================= -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- =============================================================================
     ICONS
     ============================================================================= -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<!-- =============================================================================
     CSS GLOBAL
     ============================================================================= -->
<link rel="stylesheet" href="<?= $base_path ?>asset/css/global.css">

<!-- =============================================================================
     SWEETALERT2 (Notifikasi Popup)
     ============================================================================= -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>