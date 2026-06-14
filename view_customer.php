<?php
// ============================================================================
// BUFFER OUTPUT — Agar header() bisa dipanggil kapan saja tanpa error
// ============================================================================
ob_start();

session_start();
include 'includes/auth_helper.php';
include 'includes/config.php';

// ============================================================================
// HARD DELETE AKUN CUSTOMER — Soft Delete di DB, Hard Delete di Program
// ============================================================================
if (isset($_GET['hapus_akun']) && $_GET['hapus_akun'] == '1') {
    $id_customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';

    if (!empty($id_customer)) {
        // SOFT DELETE: Update Is_Deleted = 1, Status = 0 (data masih ada di DB)
        $modified_by = $_SESSION['nama'] ?? 'CUSTOMER';

        $stmt = sqlsrv_query($conn, 
            "UPDATE Customer SET 
                Is_Deleted = 1, 
                Status = 0, 
                Deleted_By = ?, 
                Deleted_Date = GETDATE() 
             WHERE ID_Customer = ? AND Is_Deleted = 0", 
            array($modified_by, $id_customer)
        );

        if ($stmt) {
            // ========================================
            // DESTROY SESSION — Logout total
            // ========================================
            $_SESSION = array();

            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params['path'], $params['domain'],
                    $params['secure'], $params['httponly']
                );
            }

            session_destroy();
            setcookie('remember_me', '', time() - 3600, "/");

            ob_end_clean();
            header("Location: login.php?status=success&msg=Akun Anda telah dihapus permanen. Anda harus mendaftar ulang untuk menggunakan layanan kami.");
            exit();
        } else {
            ob_end_clean();
            header("Location: view_customer.php?status=error&msg=Gagal menghapus akun. Silakan coba lagi.");
            exit();
        }
    } else {
        ob_end_clean();
        header("Location: login.php?status=error&msg=Sesi tidak valid. Silakan login kembali.");
        exit();
    }
}

// ============================================================================
// CEK AKSES — Baru dicek setelah proses hapus akun selesai
// ============================================================================
cek_akses('customer');

// ============================================================================
// CEK: Jika customer sudah di-soft-delete, force logout
// ============================================================================
$id_customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';
if (!empty($id_customer)) {
    $cek_deleted = sqlsrv_query($conn, 
        "SELECT Is_Deleted, Status FROM Customer WHERE ID_Customer = ?", 
        array($id_customer)
    );
    if ($cek_deleted) {
        $row_del = sqlsrv_fetch_array($cek_deleted, SQLSRV_FETCH_ASSOC);
        if ($row_del && ($row_del['Is_Deleted'] == 1 || $row_del['Status'] == 0)) {
            $_SESSION = array();
            session_destroy();
            setcookie('remember_me', '', time() - 3600, "/");
            ob_end_clean();
            header("Location: login.php?status=error&msg=Akun Anda telah dihapus atau dinonaktifkan. Silakan hubungi admin atau daftar ulang.");
            exit();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hoop Arena | Booking Lapangan Basket</title>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --orange: #FF6B00;
            --orange-light: #FF8C2A;
            --blue: #0061FF;
            --blue-dark: #0047CC;
            --purple: #6B21FF;
            --purple-dark: #5010CC;
            --black: #0A0A0A;
            --dark: #111118;
            --white: #FFFFFF;
            --gray-100: #F8F9FA;
            --gray-200: #E9ECEF;
            --gray-500: #888;
            --green: #16A34A;
            --green-bg: #F0FDF4;
            --green-border: #DCFCE7;
            --red: #DC2626;
            --red-bg: #FEF2F2;
            --red-border: #FEE2E2;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: 'Barlow', sans-serif; background: #fff; color: var(--black); overflow-x: hidden; }

        /* ---- NAVBAR ---- */
        nav {
            background: var(--black);
            padding: 0 60px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 68px;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 2px solid #1a1a1a;
        }
        .nav-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            height: 68px;
            padding: 8px 0;
        }
        .nav-logo-img {
            height: 100%;
            width: auto;
            max-width: 180px;
            object-fit: contain;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.3));
            transition: transform 0.3s ease;
        }
        .nav-logo:hover .nav-logo-img {
            transform: scale(1.05);
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 0;
        }
        .nav-links a {
            color: #888;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 0 18px;
            height: 68px;
            display: flex;
            align-items: center;
            border-bottom: 2px solid transparent;
            transition: 0.2s;
        }
        .nav-links a:hover { color: #fff; }
        .nav-links a.active { color: #fff; border-bottom-color: var(--orange); }

        /* ---- USER DROPDOWN ---- */
        .nav-user-container {
            position: relative;
            height: 68px;
            display: flex;
            align-items: center;
        }
        .nav-user {
            color: #fff;
            font-size: 24px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 10px;
            transition: 0.2s;
        }
        .nav-user:hover { color: var(--orange); }
        .nav-user i.arrow { font-size: 11px; color: #888; transition: 0.3s; }

        .nav-user-container:hover i.arrow { transform: rotate(180deg); color: var(--orange); }

        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: #151515;
            min-width: 200px;
            border-radius: 12px;
            border: 1px solid #333;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            padding: 10px 0;
            display: none; 
            z-index: 1001;
            transform: translateY(10px);
            transition: 0.3s;
        }
        .nav-user-container:hover .dropdown-menu {
            display: block;
            transform: translateY(0);
        }
        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: #bbb;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s;
        }
        .dropdown-menu a i { font-size: 14px; width: 16px; text-align: center; }
        .dropdown-menu a:hover {
            background: #222;
            color: var(--orange);
        }
        .dropdown-divider {
            height: 1px;
            background: #333;
            margin: 8px 0;
        }
        .dropdown-menu a.logout:hover { color: var(--red); }

        /* ---- HERO ---- */
        .hero {
            height: 580px;
            position: relative;
            display: flex;
            align-items: center;
            overflow: hidden;
            background: var(--black);
        }
        .hero-bg {
            position: absolute;
            inset: 0;
            background-image: url('https://images.unsplash.com/photo-1546519638-68e109498ffc?q=80&w=2000');
            background-size: cover;
            background-position: center 30%;
            opacity: 0.5;
        }
        .hero-overlay {
            position: absolute;
            inset: 0;
            background: linear-gradient(to right, rgba(0,0,0,0.95) 40%, rgba(0,0,0,0.3) 70%, transparent 100%);
        }
        .hero-content {
            position: relative;
            z-index: 2;
            padding: 0 60px;
            max-width: 600px;
        }
        .hero-label {
            color: var(--orange);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 12px;
            display: block;
        }
        .hero-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 70px;
            font-weight: 900;
            color: var(--white);
            line-height: 0.95;
            text-transform: uppercase;
            letter-spacing: -1px;
            margin-bottom: 16px;
        }
        .hero-desc {
            color: #aaa;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 30px;
            max-width: 380px;
        }
        .btn-hero {
            background: var(--orange);
            color: #fff;
            border: none;
            padding: 16px 40px;
            border-radius: 6px;
            font-family: 'Barlow Condensed', sans-serif;
            font-weight: 800;
            font-size: 16px;
            letter-spacing: 1px;
            text-transform: uppercase;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: 0.25s;
        }
        .btn-hero:hover { background: var(--orange-light); transform: translateY(-2px); }

        /* ---- STATS BAR ---- */
        .stats-bar {
            background: var(--black);
            border-bottom: 3px solid var(--orange);
            padding: 32px 60px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
        }
        .stat-item {
            text-align: center;
            border-right: 1px solid #222;
        }
        .stat-item:last-child { border-right: none; }
        .stat-num {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 36px;
            font-weight: 900;
            color: var(--white);
            display: block;
            margin-bottom: 4px;
        }
        .stat-label {
            color: var(--orange);
            font-size: 10px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1.5px;
        }

        /* ---- MAIN CONTENT ---- */
        .main { padding: 70px 60px; }

        /* Section Title */
        .section-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        .section-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 26px;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .section-title::after {
            content: '';
            display: block;
            width: 40px;
            height: 3px;
            background: var(--orange);
            border-radius: 2px;
        }
        .section-link {
            color: var(--blue);
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 5px;
            transition: 0.2s;
        }
        .section-link:hover { color: var(--blue-dark); }

        /* ---- COURT CARDS ---- */
        .court-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 80px;
        }
        .court-card {
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            border: 1px solid #eee;
            box-shadow: 0 4px 20px rgba(0,0,0,0.06);
            transition: 0.3s;
            position: relative;
        }
        .court-card:hover { transform: translateY(-8px); box-shadow: 0 16px 40px rgba(0,0,0,0.12); }
        .court-img {
            width: 100%;
            height: 180px;
            object-fit: cover;
        }
        .court-badge {
            position: absolute;
            top: 12px;
            left: 12px;
            background: var(--orange);
            color: #fff;
            font-size: 10px;
            font-weight: 900;
            padding: 4px 10px;
            border-radius: 4px;
            letter-spacing: 0.5px;
        }
        .court-info {
            padding: 18px;
        }
        .court-name {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 18px;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 4px;
        }
        .court-price {
            color: var(--gray-500);
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 14px;
        }
        .court-price span { color: var(--orange); font-weight: 800; }
        .btn-book {
            background: var(--blue);
            color: #fff;
            border: none;
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-book:hover { background: var(--blue-dark); }

        /* ---- SCHEDULE ---- */
        .schedule-section {
            background: var(--dark);
            border-radius: 24px;
            padding: 50px;
            margin-bottom: 80px;
        }
        .schedule-section .section-title { color: #fff; }
        .date-btn {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            background: #fff;
            border: 1px solid #e0e0e0;
            padding: 11px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            color: #333;
            cursor: pointer;
            margin-top: 8px;
            transition: 0.2s;
        }
        .date-btn:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .date-btn i.cal { color: #555; font-size: 15px; }
        .date-btn i.chevron { color: #aaa; font-size: 11px; margin-left: 30px; }
        .slot-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 12px;
            margin-top: 28px;
        }
        .slot {
            border-radius: 12px;
            padding: 18px 8px;
            text-align: center;
            transition: 0.2s;
        }
        .slot.available {
            background: var(--green-bg);
            border: 1px solid var(--green-border);
        }
        .slot.booked {
            background: var(--red-bg);
            border: 1px solid var(--red-border);
        }
        .slot-time {
            display: block;
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 6px;
        }
        .slot.available .slot-time { color: #14532D; }
        .slot.booked .slot-time { color: #7F1D1D; }
        .slot-status {
            display: block;
            font-size: 11px;
            font-weight: 700;
        }
        .slot.available .slot-status { color: var(--green); }
        .slot.booked .slot-status { color: var(--red); }

        /* ---- MEMBER BANNER ---- */
        .member-banner {
            background: linear-gradient(135deg, #0f0720 0%, #3b0a7d 50%, #2d0a6e 100%);
            border-radius: 24px;
            padding: 60px 60px;
            margin-bottom: 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }
        .member-banner::before {
            content: '';
            position: absolute;
            top: -50px; right: 200px;
            width: 300px; height: 300px;
            border-radius: 50%;
            background: rgba(107,33,255,0.3);
            filter: blur(80px);
        }
        .member-label {
            color: var(--orange);
            font-size: 11px;
            font-weight: 800;
            letter-spacing: 3px;
            text-transform: uppercase;
            margin-bottom: 16px;
            display: block;
        }
        .member-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 38px;
            font-weight: 900;
            color: #fff;
            text-transform: uppercase;
            line-height: 1.05;
        }
        .btn-member {
            background: var(--purple);
            color: #fff;
            border: none;
            padding: 16px 40px;
            border-radius: 10px;
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 16px;
            font-weight: 800;
            text-transform: uppercase;
            cursor: pointer;
            margin-top: 30px;
            transition: 0.2s;
            display: block;
        }
        .btn-member:hover { background: var(--purple-dark); transform: translateY(-2px); }
        .member-player {
            position: absolute;
            right: 0;
            bottom: -20px;
            height: 340px;
            opacity: 0.9;
            z-index: 1;
        }

        /* ---- PROMO ---- */
        .promo-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 24px;
            margin-bottom: 40px;
        }
        .promo-card {
            height: 200px;
            border-radius: 18px;
            background-size: cover;
            background-position: center;
            position: relative;
            overflow: hidden;
            transition: 0.4s;
            cursor: pointer;
        }
        .promo-card:hover { transform: scale(1.02); }
        .promo-card::after {
            content: '';
            position: absolute;
            inset: 0;
            background: linear-gradient(to top, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0.1) 60%, transparent 100%);
        }
        .promo-body {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 24px;
            z-index: 2;
        }
        .promo-tag {
            display: block;
            color: var(--orange);
            font-size: 10px;
            font-weight: 800;
            letter-spacing: 2px;
            text-transform: uppercase;
            margin-bottom: 6px;
        }
        .promo-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 26px;
            font-weight: 900;
            color: #fff;
            text-transform: uppercase;
            line-height: 1;
        }

        /* ---- FOOTER ---- */
        footer {
            background: var(--black);
            padding: 40px 60px;
            text-align: center;
            border-top: 1px solid #1a1a1a;
        }
        footer p { color: #444; font-size: 13px; font-weight: 500; }
    </style>
</head>
<body>

<!-- NAVBAR -->
<nav>
    <a href="view_customer.php" class="nav-logo">
        <img src="logo.png" alt="HoopBall" class="nav-logo-img">
    </a>
    <div class="nav-links">
        <a href="#" class="active">Beranda</a>
        <a href="#">Lapangan</a>
        <a href="#">Jadwal</a>
        <a href="#">Member</a>
        <a href="#">Promo</a>
        <a href="#">Tentang Kami</a>
    </div>

    <!-- Bagian User Dropdown yang Baru -->
    <div class="nav-user-container">
        <div class="nav-user">
            <i class="fa-regular fa-circle-user"></i>
            <i class="fa-solid fa-chevron-down arrow"></i>
        </div>
        <div class="dropdown-menu">
            <a href="profile_customer.php"><i class="fa-solid fa-user"></i> Profil Saya</a>
            <a href="#"><i class="fa-solid fa-calendar-check"></i> Riwayat Booking</a>
            <a href="#"><i class="fa-solid fa-gear"></i> Pengaturan</a>
            <div class="dropdown-divider"></div>
            <a href="#" onclick="confirmHapusAkun(event)" style="color: var(--red);"><i class="fa-solid fa-trash-can"></i> Hapus Akun</a>
            <a href="logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
        </div>
    </div>
</nav>

<!-- HERO -->
<header class="hero">
    <div class="hero-bg"></div>
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <span class="hero-label">Booking Lapangan</span>
        <h1 class="hero-title">LEBIH MUDAH,<br>LEBIH SERU!</h1>
        <p class="hero-desc">Temukan pengalaman bermain basket terbaik bersama kami.</p>
        <button class="btn-hero">Booking Sekarang <i class="fa-solid fa-arrow-right"></i></button>
    </div>
</header>

<!-- STATS BAR -->
<div class="stats-bar">
    <div class="stat-item">
        <span class="stat-num">14</span>
        <span class="stat-label">Arena Tersedia</span>
    </div>
    <div class="stat-item">
        <span class="stat-num">5000+</span>
        <span class="stat-label">Pemain Aktif</span>
    </div>
    <div class="stat-item">
        <span class="stat-num">24/7</span>
        <span class="stat-label">Akses Booking</span>
    </div>
    <div class="stat-item">
        <span class="stat-num">4.9</span>
        <span class="stat-label">Rating Fasilitas</span>
    </div>
</div>

<main class="main">

    <!-- PILIH LAPANGAN -->
    <div class="section-head">
        <h2 class="section-title">Pilih Lapangan</h2>
    </div>
    <div class="court-grid">
        <div class="court-card">
            <span class="court-badge">Tersedia</span>
            <img class="court-img" src="https://images.unsplash.com/photo-1546519638-68e109498ffc?q=80&w=800" alt="Lapangan A">
            <div class="court-info">
                <div class="court-name">Lapangan A</div>
                <div class="court-price"><span>Rp 200.000</span> / jam</div>
                <button class="btn-book">BOOKING</button>
            </div>
        </div>
        <div class="court-card">
            <span class="court-badge">Tersedia</span>
            <img class="court-img" src="https://images.unsplash.com/photo-1504450758481-7338eba7524a?q=80&w=800" alt="Lapangan B">
            <div class="court-info">
                <div class="court-name">Lapangan B</div>
                <div class="court-price"><span>Rp 180.000</span> / jam</div>
                <button class="btn-book">BOOKING</button>
            </div>
        </div>
        <div class="court-card">
            <span class="court-badge">Tersedia</span>
            <img class="court-img" src="https://images.unsplash.com/photo-1505666287802-931dc83948e9?q=80&w=800" alt="Lapangan C">
            <div class="court-info">
                <div class="court-name">Lapangan C</div>
                <div class="court-price"><span>Rp 150.000</span> / jam</div>
                <button class="btn-book">BOOKING</button>
            </div>
        </div>
        <div class="court-card">
            <span class="court-badge">Tersedia</span>
            <img class="court-img" src="https://images.unsplash.com/photo-1544919982-b61976f0ba43?q=80&w=800" alt="Lapangan D">
            <div class="court-info">
                <div class="court-name">Lapangan D</div>
                <div class="court-price"><span>Rp 150.000</span> / jam</div>
                <button class="btn-book">BOOKING</button>
            </div>
        </div>
    </div>

    <!-- JADWAL TERSEDIA -->
    <div class="schedule-section">
        <h2 class="section-title" style="color:#fff; margin-bottom: 20px;">Jadwal Tersedia</h2>
        <button class="date-btn">
            <i class="fa-solid fa-calendar-days cal"></i>
            24 Mei 2024
            <i class="fa-solid fa-chevron-down chevron"></i>
        </button>
        <div class="slot-grid">
            <div class="slot available">
                <span class="slot-time">07:00 - 08:00</span>
                <span class="slot-status">Tersedia</span>
            </div>
            <div class="slot available">
                <span class="slot-time">08:00 - 09:00</span>
                <span class="slot-status">Tersedia</span>
            </div>
            <div class="slot available">
                <span class="slot-time">09:00 - 10:00</span>
                <span class="slot-status">Tersedia</span>
            </div>
            <div class="slot booked">
                <span class="slot-time">10:00 - 11:00</span>
                <span class="slot-status">Terbooking</span>
            </div>
            <div class="slot available">
                <span class="slot-time">11:00 - 12:00</span>
                <span class="slot-status">Tersedia</span>
            </div>
            <div class="slot available">
                <span class="slot-time">12:00 - 13:00</span>
                <span class="slot-status">Tersedia</span>
            </div>
            <div class="slot booked">
                <span class="slot-time">13:00 - 14:00</span>
                <span class="slot-status">Terbooking</span>
            </div>
        </div>
    </div>

    <!-- MEMBER BANNER -->
    <div class="member-banner">
        <div style="position:relative; z-index:2; max-width:55%;">
            <span class="member-label">Jadi Member</span>
            <h2 class="member-title">DAPATKAN HARGA SPESIAL<br>DAN BERBAGAI KEUNTUNGAN MENARIK!</h2>
            <button class="btn-member">DAFTAR MEMBER</button>
        </div>
        <img class="member-player" src="https://www.pngall.com/wp-content/uploads/2/Basketball-Player-PNG-Transparent-HD-Photo.png" alt="Player">
    </div>

    <!-- PROMO TERBARU -->
    <div class="section-head" style="margin-bottom:24px;">
        <h2 class="section-title">Promo Terbaru</h2>
        <a href="#" class="section-link">Lihat Semua <i class="fa-solid fa-chevron-right" style="font-size:10px;"></i></a>
    </div>
    <div class="promo-grid">
        <div class="promo-card" style="background-image:url('https://images.unsplash.com/photo-1515444744559-7be63e1600de?q=80&w=800');">
            <div class="promo-body">
                <span class="promo-tag">Weekday</span>
                <div class="promo-title">DISKON 20%</div>
            </div>
        </div>
        <div class="promo-card" style="background-image:url('https://images.unsplash.com/photo-1574623452334-1e0ac2b3ccb4?q=80&w=800');">
            <div class="promo-body">
                <span class="promo-tag">Paket Member</span>
                <div class="promo-title">HEMAT 30%</div>
            </div>
        </div>
        <div class="promo-card" style="background-image:url('https://images.unsplash.com/photo-1504450758481-7338eba7524a?q=80&w=800');">
            <div class="promo-body">
                <span class="promo-tag">Untuk Member Baru</span>
                <div class="promo-title">GRATIS 1 JAM</div>
            </div>
        </div>
    </div>

</main>

<footer>
    <p>© 2024 Hoop Arena Kelompok 05. All rights reserved.</p>
</footer>

<script>
// ============================================
// HARD DELETE AKUN CONFIRMATION
// ============================================
function confirmHapusAkun(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Hapus Akun Permanen?',
        html: '<strong style="color:#DC2626;">PERINGATAN:</strong> Tindakan ini tidak dapat dibatalkan!<br><br>' +
              'Akun Anda akan dihapus dari sistem dan Anda harus mendaftar ulang untuk menggunakan layanan kami.<br><br>' +
              '<span style="color:#6B7280; font-size:12px;">Data akan dihapus secara permanen.</span>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#DC2626',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus Akun Saya!',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (result.isConfirmed) {
            let timerInterval;
            Swal.fire({
                title: 'Menghapus Akun...',
                html: 'Mohon tunggu, akun Anda sedang diproses.<br><b></b>',
                timer: 2000,
                timerProgressBar: true,
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                    const timer = Swal.getHtmlContainer().querySelector('b');
                    timerInterval = setInterval(() => {
                        timer.textContent = Math.ceil(Swal.getTimerLeft() / 1000) + ' detik';
                    }, 100);
                },
                willClose: () => {
                    clearInterval(timerInterval);
                }
            }).then(() => {
                window.location.href = '?hapus_akun=1';
            });
        }
    });
}

// ============================================
// URL PARAMETER NOTIFICATION
// ============================================
const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
const msg = urlParams.get('msg');

if (status && msg) {
    const isSuccess = status === 'success';
    Swal.fire({
        icon: isSuccess ? 'success' : 'error',
        title: isSuccess ? 'Berhasil!' : 'Gagal!',
        text: msg,
        timer: 5000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end',
        timerProgressBar: true,
        showCloseButton: true,
        background: '#ffffff',
        color: '#1e293b',
        iconColor: isSuccess ? '#16A34A' : '#DC2626',
        customClass: { popup: 'swal-toast' }
    });
    window.history.replaceState({}, document.title, window.location.pathname);
}
</script>
</body>
</html>