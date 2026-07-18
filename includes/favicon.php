<?php
// File: includes/favicon.php
// Hanya favicon & meta dasar.
// Gunakan variabel $path_prefix (didefinisikan di file pemanggil) 
// atau default ke string kosong untuk root path.
$prefix = isset($path_prefix) ? $path_prefix : '';
?><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="description" content="HoopBall - Platform booking lapangan basket terbaik. Sewa lapangan, beli perlengkapan, dan jadilah member untuk diskon menarik.">
<meta name="theme-color" content="#FF4500">
<meta name="msapplication-TileColor" content="#FF4500">

<link rel="icon" type="image/png" sizes="32x32" href="<?= $prefix ?>asset/image/Favicon/image.png">
<link rel="icon" type="image/png" sizes="16x16" href="<?= $prefix ?>asset/image/Favicon/image.png">
<link rel="apple-touch-icon" sizes="180x180" href="<?= $prefix ?>asset/image/Favicon/image.png">
<link rel="manifest" href="<?= $prefix ?>asset/image/Favicon/site.webmanifest">
<link rel="shortcut icon" href="<?= $prefix ?>asset/image/Favicon/image.png" type="image/png">
<link rel="mask-icon" href="<?= $prefix ?>asset/image/Favicon/image.png" color="#FF4500">