<?php
// Mengamankan koneksi database jika file konfigurasi disertakan
$total_lapangan = 15; // default fallback
$total_promo = 3;     // default fallback

if (file_exists('includes/config.php')) {
    include 'includes/config.php';

    if (isset($conn)) {
        $sql_lap = "SELECT COUNT(*) as total FROM Lapangan WHERE Status = 1";
        $q_lap = sqlsrv_query($conn, $sql_lap);
        if ($q_lap) {
            $d_lap = sqlsrv_fetch_array($q_lap, SQLSRV_FETCH_ASSOC);
            $total_lapangan = $d_lap['total'] ?? $total_lapangan;
        }

        $sql_prm = "SELECT COUNT(*) as total FROM Promo WHERE Tanggal_Selesai >= GETDATE()";
        $q_prm = sqlsrv_query($conn, $sql_prm);
        if ($q_prm) {
            $d_prm = sqlsrv_fetch_array($q_prm, SQLSRV_FETCH_ASSOC);
            $total_promo = $d_prm['total'] ?? $total_promo;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HoopBall - Sewa Lapangan Basket Jadi Lebih Mudah</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* AKTIFKAN SCROLL HALUS DI SELURUH HALAMAN */
        html {
            scroll-behavior: smooth;
        }

        /* OFFSET SCROLL: 
   Mencegah judul seksi tertutup/tertabrak oleh Navbar Sticky saat proses scroll berhenti */
        section[id],
        footer[id] {
            scroll-margin-top: 90px;
            /* Nilai disesuaikan dengan tinggi navbar (~80px - 90px) */
        }


        :root {
            --orange: #FF4500;
            --orange-hover: #E03E00;
            --dark: #0F172A;
            --text-dark: #1E293B;
            --text-muted: #64748B;
            --bg-light: #F8FAFC;
            --border-color: #E2E8F0;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        body {
            background-color: #FFFFFF;
            color: var(--text-dark);
            overflow-x: hidden;
            line-height: 1.5;
        }

        a {
            text-decoration: none;
            transition: all 0.3s ease;
        }

        /* NAVBAR */
        .navbar {
            position: sticky;
            top: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            display: flex;
            justify-content: space-between;
            align-items: center;

            /* DIUBAH: Dari 20px 8% menjadi 16px 4% agar Hoopball mentok kiri & Booking mentok kanan */
            padding: 16px 4%;

            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.04);
            z-index: 1000;
        }

        .logo {
            font-size: 24px;
            font-weight: 800;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .logo i {
            color: var(--orange);
            display: inline-block;

            /* Transisi untuk putaran ikon */
            transition: transform 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .logo:hover i {
            transform: rotate(360deg);
        }

        .logo span {
            color: var(--orange);
        }

        .nav-menu {
            /* Mengunci posisi tepat di tengah-tengah navbar */
            position: absolute;
            left: 50%;
            transform: translateX(-50%);

            display: flex;
            gap: 32px;
            z-index: 5;
        }

        .nav-menu a {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 14px;
            position: relative;
            padding: 6px 0;
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .nav-menu a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--orange);
            transform: scaleX(0);
            transform-origin: right;
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .nav-menu a:hover {
            color: var(--orange);
        }

        .nav-menu a:hover::after {
            transform: scaleX(1);
            transform-origin: left;
        }

        .nav-menu a.active {
            color: var(--orange) !important;
        }

        /* Paksa garis bawah oranye menu aktif agar tetap muncul */
        .nav-menu a.active::after {
            transform: scaleX(1) !important;
            transform-origin: left !important;
        }


        .nav-btns {
            display: flex;
            align-items: center;
            gap: 16px;
            /* Jarak dirapatkan sedikit agar rapi */
        }

        .btn-login {
            color: var(--text-dark);
            font-weight: 700;
            font-size: 14px;
            padding: 10px 24px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: transparent;
            text-decoration: none;

            /* Transisi halus */
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-login:hover {
            border-color: var(--orange);
            /* Garis tepi berubah oranye */
            color: var(--orange);
            /* Tulisan berubah oranye */
            background-color: rgba(255, 69, 0, 0.02);
            /* Efek background oranye pudar */
            transform: translateY(-1px);
        }

        .btn-join {
            background: var(--orange);
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(255, 84, 0, 0.2);

            /* Transisi melayang */
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-join:hover {
            background-color: var(--orange-hover);
            transform: translateY(-2px);
            /* Melayang naik */
            box-shadow: 0 6px 20px rgba(255, 69, 0, 0.35);
            /* Glow oranye kuat */
        }

        /* HERO SECTION DENGAN EFEK FADE TRANSISI */
        .hero-section {
            display: grid;
            grid-template-columns: 35% 65%;

            /* GANTI INI: Dari min-height menjadi height pasti */
            height: 620px;

            background-color: #ffffff;
            padding-left: 5%;
            padding-top: 0;
            padding-bottom: 0;
            position: relative;
            overflow: hidden;
        }

        .hero-content {
            padding-right: 20px;
            z-index: 10;
            display: flex;
            flex-direction: column;
            justify-content: center;
            height: 100%;
        }

        .hero-content h1 {
            font-size: 52px;
            font-weight: 850;
            line-height: 1.15;
            color: #111111;
            margin-bottom: 24px;
            letter-spacing: -1px;
        }

        .hero-content h1 span {
            color: var(--orange);
        }

        .hero-content p {
            font-size: 15px;
            color: var(--text-muted);
            line-height: 1.65;
            margin-bottom: 40px;
            max-width: 485px;
        }

        .hero-cta {
            display: flex;
            gap: 16px;
        }

        .btn-hero-primary {
            background-color: var(--orange);
            color: #ffffff;
            padding: 14px 28px;
            font-weight: 700;
            font-size: 14px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: none;
            cursor: pointer;

            /* Transisi mulus */
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-hero-primary i {
            transition: transform 0.3s ease;
            /* Transisi ikon di dalam */
        }

        .btn-hero-primary:hover {
            background-color: var(--orange-hover);
            transform: translateY(-2px);
            /* Melayang naik */
            box-shadow: 0 8px 25px rgba(255, 69, 0, 0.35);
            /* Pancaran cahaya oranye */
        }

        .btn-hero-primary:hover i {
            transform: rotate(-10deg) scale(1.1);
        }

        .btn-hero-secondary {
            background-color: #ffffff;
            color: #111111;
            padding: 14px 28px;
            font-weight: 700;
            font-size: 14px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1px solid var(--border-color);
            cursor: pointer;

            /* Transisi mulus */
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-hero-secondary i {
            transition: transform 0.4s ease;
            /* Transisi ikon jam */
        }

        .btn-hero-secondary:hover {
            background-color: var(--bg-light);
            border-color: #CBD5E1;
            transform: translateY(-2px);
            /* Melayang naik */
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
        }

        .btn-hero-secondary:hover i {
            transform: rotate(30deg);
        }

        .hero-visual {
            position: relative;
            height: 100%;
            width: 100%;
            overflow: hidden;
            /* Memotong kelebihan zoom agar rapi */
        }

        .hero-visual img {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            pointer-events: none;

            /* Kecepatan zoom diatur lambat (8 detik) agar terasa sinematik dan mewah */
            transition: transform 8s cubic-bezier(0.16, 1, 0.3, 1);

        }

        .hero-section:hover .hero-visual img {
            transform: scale(1.04);
            /* Membesar perlahan sebanyak 4% */
        }

        @media (max-width: 992px) {
            .hero-section {
                height: auto;
                /* Kembalikan ke auto pada layar HP agar tidak terpotong */
                grid-template-columns: 1fr;
            }

            .hero-visual {
                height: 400px;
                /* Batasi tinggi gambar di HP */
            }

            .hero-visual img {
                position: relative;
                height: 100%;
            }
        }



        /* TRANSISI GRADIENT (FADE OVERLAY) */
        .fade-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 35%;
            height: 100%;
            background: linear-gradient(to right, #ffffff 0%, rgba(255, 255, 255, 0) 100%);
            z-index: 2;
        }

        /* KARTU STATISTIK MELAY.hero-sectionANG */
        .hero-stats-card {
            position: absolute;
            bottom: 50px;
            left: 34%;
            background: #ffffff;
            border-radius: 14px;
            padding: 12px 22px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
            border: 1px solid rgba(0, 0, 0, 0.03);
            z-index: 5;
            cursor: pointer;

            /* Transisi mulus melayang */
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .hero-stats-card:hover {
            transform: translateY(-8px);
            /* Melayang naik */
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
            /* Bayangan melembut dan melebar */
            border-color: rgba(255, 69, 0, 0.1);
            /* Semburat batas oranye halus */
        }

        .hero-stats-card:hover .stat-box:nth-child(1) .stat-icon i {
            transform: translateY(-4px) scale(1.15);
        }

        /* Kolom 2 (Rating) -> Ikon Bintang Membesar & Memancar */
        .hero-stats-card:hover .stat-box:nth-child(3) .stat-icon i {
            transform: scale(1.2) rotate(15deg);
        }

        /* Kolom 3 (Lapangan) -> Ikon Bola Basket Berputar */
        .hero-stats-card:hover .stat-box:nth-child(5) .stat-icon i {
            transform: rotate(180deg) scale(1.15);
        }

        .stat-box {
            text-align: center;
            min-width: 65px;
        }

        .stat-icon {
            font-size: 18px;
            color: var(--orange);
            margin-bottom: 4px;
        }

        .stat-icon i {
            display: inline-block;
            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            /* Efek memantul bouncy */
        }

        .stat-num {
            font-size: 20px;
            font-weight: 800;
            color: #111111;
            line-height: 1.1;
        }

        .stat-label {
            font-size: 10px;
            color: var(--text-muted);
            font-weight: 600;
            margin-top: 1px;
        }

        .stat-divider {
            width: 1px;
            height: 28px;
            background-color: var(--border-color);
        }

        /* FEATURES SECTION (GRID 4) */
        .features-section {
            padding: 80px 8%;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
            background-color: #ffffff;
        }

        .feature-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 30px 24px;
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.04);
            border-color: rgba(255, 69, 0, 0.2);
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            background: #FFF0E9;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .feature-icon i {
            font-size: 20px;
            color: var(--orange);
        }

        .feature-card h4 {
            font-size: 16px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .feature-card p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* SECTION HEADER */
        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-header h2 {
            font-size: 32px;
            font-weight: 800;
            color: var(--dark);
        }

        /* COURT SECTION */
        .court-section {
            padding: 80px 8%;
            background: #FFFFFF;
        }

        .court-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
        }

        .court-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .court-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.06);
        }

        .court-img-container {
            width: 100%;
            height: 220px;
            overflow: hidden;
        }

        .court-img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .court-info {
            padding: 24px;
        }

        .court-info h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 8px;
        }

        .court-info p {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 20px;
            height: 40px;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .court-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 16px;
            border-top: 1px solid var(--border-color);
        }

        .court-price {
            font-size: 14px;
            color: var(--text-muted);
        }

        .court-price span {
            font-size: 18px;
            font-weight: 800;
            color: var(--orange);
        }

        .btn-detail {
            border: 1px solid var(--orange);
            color: var(--orange);
            font-weight: 700;
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 6px;
            background: transparent;
        }

        .btn-detail:hover {
            background: var(--orange);
            color: white;
        }

        /* CARA BOOKING PROCESS */
        .process-section {
            padding: 80px 8%;
            background: var(--bg-light);
            overflow: hidden;
        }

        .process-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            position: relative;
        }

        .process-grid::before {
            content: '';
            position: absolute;
            top: 50px;
            /* Sejajar dengan posisi tengah bulatan angka */
            left: 12%;
            right: 12%;
            height: 2px;
            /* Membuat efek garis putus-putus berwarna abu-abu/oranye halus */
            background-image: linear-gradient(to right, #CBD5E1 50%, rgba(255, 255, 255, 0) 0%);
            background-size: 12px 2px;
            background-repeat: repeat-x;
            z-index: 1;
        }

        .process-card {
            background: #ffffff;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 36px 24px;
            text-align: center;
            position: relative;
            z-index: 2;
            /* Berada di atas garis penghubung */

            /* Transisi super halus dengan kurva cubic-bezier */
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
        }

        .process-card:hover {
            transform: translateY(-10px) scale(1.02);
            /* Kartu naik dan sedikit membesar */
            border-color: var(--orange);
            /* Border berubah menjadi oranye */

            /* Memberikan efek bayangan oranye lembut (warm glow) */
            box-shadow: 0 20px 35px rgba(255, 69, 0, 0.08);
        }

        .process-step {
            width: 36px;
            height: 36px;
            background: var(--orange);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14px;
            margin: 0 auto 24px auto;
            position: relative;
            z-index: 3;

            transition: all 0.3s ease;
        }

        .process-card:hover .process-step {
            transform: scale(1.15);
            /* Bulatan nomor membesar sedikit */
            box-shadow: 0 0 12px rgba(255, 69, 0, 0.4);
            /* Efek berpendar */
        }

        .process-card i {
            font-size: 36px;
            color: #94A3B8;
            /* Warna abu-abu awal */
            margin-bottom: 20px;
            display: inline-block;

            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .process-card:hover i {
            color: var(--orange);
            /* Warna berubah menjadi oranye */
            transform: scale(1.12) rotate(5deg);
            /* Ikon membesar dan miring sedikit agar dinamis */
        }

        .process-card h4 {
            font-size: 16px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
            transition: color 0.3s ease;
        }

        .process-card:hover h4 {
            color: var(--orange);
            /* Judul berubah oranye saat dihover */
        }

        .process-card p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* Penyesuaian Responsif di Layar HP */
        @media (max-width: 992px) {
            .process-grid::before {
                display: none;
                /* Sembunyikan garis penghubung di HP karena grid vertikal */
            }
        }

        /* MEMBERSHIP SECTION */
        .membership-section {
            padding: 100px 8%;
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 60px;
            align-items: center;
            background-color: #ffffff;
        }

        .member-intro h2 {
            font-size: 36px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 16px;
        }

        .member-intro p {
            color: var(--text-muted);
            font-size: 15px;
            margin-bottom: 40px;
            line-height: 1.6;
        }

        .member-benefit-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
            /* Jarak dirapatkan sedikit agar rapi */
        }

        .benefit-item {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            padding: 12px;
            border-radius: 12px;
            cursor: pointer;

            /* Transisi geser horizontal yang mulus */
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .benefit-item:hover {
            transform: translateX(8px);
            /* Bergeser sedikit ke kanan saat diarahkan kursor */
            background-color: var(--bg-light);
            /* Highlight abu-abu tipis di latar belakang */
        }

        .benefit-icon {
            width: 48px;
            height: 48px;
            background: #FFF0E9;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;

            transition: all 0.3s ease;
        }

        .benefit-item:hover .benefit-icon {
            background: var(--orange);
            /* Berubah menjadi oranye solid */
        }


        .benefit-icon i {
            color: var(--orange);
            font-size: 18px;
            transition: all 0.3s ease;
        }

        .benefit-item:hover .benefit-icon i {
            color: #ffffff;
            /* Simbol/Ikon berubah menjadi putih */
            transform: scale(1.1);
            /* Ikon membesar sedikit */
        }

        .benefit-text h4 {
            font-size: 15px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 4px;
            transition: color 0.3s ease;
        }

        .benefit-item:hover .benefit-text h4 {
            color: var(--orange);
            /* Judul benefit berubah oranye */
        }

        .benefit-text p {
            font-size: 13px;
            color: var(--text-muted);
        }

        /* PRICING */
        .pricing-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        .pricing-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 40px 30px;
            position: relative;
            display: flex;
            flex-direction: column;
            cursor: pointer;

            /* Transisi kartu melayang */
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* HOVER KARTU BASIC */
        .pricing-card:not(.premium):hover {
            transform: translateY(-8px);
            /* Terangkat ke atas */
            border-color: var(--orange);
            /* Warna garis tepi menjadi oranye */
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
            /* Bayangan halus */
        }

        /* Otomatis menyorot tombol "Daftar Member" pada kartu Basic saat kartu dihover */
        .pricing-card:not(.premium):hover .btn-price.outline {
            background-color: var(--orange);
            color: white;
        }

        /* HOVER KARTU PREMIUM */
        .pricing-card.premium:hover {
            transform: translateY(-12px);

            /* Warna garis tepi berubah menjadi oranye saat kursor diarahkan */
            border-color: var(--orange);

            box-shadow: 0 24px 48px rgba(255, 69, 0, 0.12);
        }

        .pricing-card.premium:hover .btn-price.outline {
            background-color: var(--orange);
            color: white;
        }

        .pricing-card.premium {
            /* Mengubah warna garis tepi default menjadi abu-abu terang (sama dengan basic) */
            border-color: var(--border-color);

            /* Mengurangi bayangan default agar seragam dengan basic */
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.02);
        }

        .popular-badge {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--orange);
            color: white;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 3;

            transition: all 0.3s ease;
        }

        .pricing-card.premium:hover .popular-badge {
            transform: translateX(-50%) scale(1.05);
            /* Membesar sedikit */
            background-color: var(--orange-hover);
        }

        .price-name {
            font-size: 18px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .price-amount {
            font-size: 28px;
            font-weight: 800;
            color: var(--orange);
            margin-bottom: 30px;
        }

        .price-amount span {
            font-size: 14px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .price-features {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 40px;
        }

        .price-features li {
            font-size: 13px;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .price-features li i {
            color: #22C55E;
            font-size: 14px;
        }

        .btn-price {
            width: 100%;
            padding: 14px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 14px;
            text-align: center;
            margin-top: auto;

            transition: all 0.3s ease;
        }

        .btn-price.outline {
            border: 1px solid var(--orange);
            color: var(--orange);
            background: transparent;
        }

        .btn-price.outline:hover {
            background-color: var(--orange);
            color: white;
        }

        .btn-price.filled {
            background: var(--orange);
            color: white;
            border: none;
        }

        .btn-price.filled:hover {
            background: var(--orange-hover);
            transform: scale(1.02);
            /* Tombol sedikit membesar saat ditekan */
        }

        /* PROMO & TESTIMONIALS */
        .promo-testimonial-section {
            padding: 80px 8%;
            background: var(--bg-light);
            display: grid;
            grid-template-columns: 1fr 2.2fr;
            /* Proporsi disesuaikan agar rapi */
            gap: 30px;
        }

        .promo-card {
            background: linear-gradient(135deg, #FF5400 0%, #FF3D00 60%, #E63900 100%);
            border-radius: 24px;
            padding: 40px 32px;
            color: white;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 380px;
            box-shadow: 0 10px 30px rgba(255, 84, 0, 0.12);

            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
        }

        .promo-badge {
            font-size: 13px;
            font-weight: 750;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            margin-bottom: auto;
            /* Mendorong konten lain ke bawah secara otomatis */
        }

        .promo-content-block {
            margin-top: auto;
            position: relative;
            z-index: 2;
            /* Berada di atas latar belakang */
        }

        /* Judul Persen Diskon (Besar & Tebal) */
        .promo-content-block h3 {
            font-size: 42px;
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }

        /* Sub-Judul Lapangan */
        .promo-content-block p {
            font-size: 15px;
            font-weight: 600;
            opacity: 0.95;
            margin-bottom: 24px;
        }

        .btn-promo-yellow {
            background-color: #FFC107;
            /* Kuning cerah */
            color: #1E293B;
            /* Warna teks gelap */
            font-weight: 700;
            font-size: 13px;
            padding: 10px 24px;
            border-radius: 50px;
            /* Bentuk lonjong/kapsul */
            display: inline-block;
            width: fit-content;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.2);

            transition: all 0.3s ease;
        }

        /* Hover tombol kuning */
        .promo-card:hover .btn-promo-yellow {
            background-color: #FFB300;
            /* Kuning sedikit lebih gelap saat hover */
            transform: scale(1.04);
            box-shadow: 0 6px 15px rgba(255, 193, 7, 0.35);
        }

        .promo-terms {
            font-size: 10px;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 14px;
            font-weight: 500;
        }


        .promo-title h3 {
            font-size: 32px;
            font-weight: 800;
            line-height: 1.2;
            margin: 15px 0 5px 0;
        }

        .promo-title p {
            font-size: 14px;
            opacity: 0.9;
        }

        .btn-promo {
            background: white;
            color: var(--orange);
            font-weight: 800;
            font-size: 13px;
            padding: 12px 24px;
            border-radius: 8px;
            text-align: center;
            width: fit-content;
            margin-top: 30px;

            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .promo-card:hover .btn-promo {
            transform: scale(1.05);
            /* Membesar sedikit */
            background-color: #FFF0E9;
            /* Berubah warna krem tipis */
        }

        .promo-player-img {
            position: absolute;

            /* DIUBAH: Dinaikkan lagi dari 15px menjadi 30px agar gambar lebih ke atas */
            bottom: 35px;

            right: 25px;
            /* Posisi kiri tetap dipertahankan */
            height: 300px;
            object-fit: contain;
            pointer-events: none;
            z-index: 1;

            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Efek Hover gambar atlet melompat sedikit keluar */
        .promo-card:hover .promo-player-img {
            transform: scale(1.04) translate(3px, -3px);
        }

        .promo-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 45px rgba(255, 69, 0, 0.28);
        }



        /* CONTAINER MAP UTAMA */
        .location-map-card {
            position: relative;
            /* Penting untuk menahan posisi kartu melayang di dalamnya */
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.03);
            height: 100%;
            min-height: 380px;

            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Hover Utama Wadah Map */
        .location-map-card:hover {
            transform: translateY(-6px);
            border-color: var(--orange);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.06);
        }

        /* IFRAME MAP DENGAN FILTER MONOKROM MINIMALIS */
        .location-map-card iframe {
            width: 100%;
            height: 100%;
            display: block;

            /* Mengubah warna peta default menjadi abu-abu artistik agar tidak terlalu ramai */
            filter: grayscale(100%) contrast(1.1) brightness(0.95);
            transition: filter 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Ketika kursor diarahkan, warna peta akan kembali normal secara sangat halus */
        .location-map-card:hover iframe {
            filter: grayscale(0%) contrast(1) brightness(1);
        }


        /* KARTU ALAMAT MELAYANG (GLASSMORPHISM CARD) */
        .map-overlay-card {
            position: absolute;
            top: 16px;
            /* Jarak dari atas dirapatkan */
            right: 16px;
            /* Jarak dari kanan dirapatkan */
            left: auto;

            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 12px;
            /* Lengkungan sudut disesuaikan */

            /* Padding diperkecil (Atas-Bawah 14px, Kiri-Kanan 18px) */
            padding: 14px 18px;

            /* Lebar maksimal dipersempit dari 280px menjadi 210px agar ramping */
            max-width: 210px;

            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.8);
            z-index: 10;

            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        /* Hover efek pada kartu melayang */
        .location-map-card:hover .map-overlay-card {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(255, 69, 0, 0.1);
            border-color: rgba(255, 69, 0, 0.2);
        }

        /* BADGE LOKASI */
        .map-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #FFF0E9;
            color: var(--orange);

            /* Ukuran teks badge diturunkan */
            font-size: 8px;
            padding: 3px 8px;

            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
            /* Jarak dirapatkan */
        }

        .map-overlay-card h4 {
            font-size: 13px;
            /* Diturunkan dari 15px */
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 4px;
        }

        .map-overlay-card p {
            font-size: 10px;
            /* Diturunkan dari 11px */
            color: var(--text-muted);
            line-height: 1.4;
            margin-bottom: 12px;
            /* Jarak dirapatkan */
        }

        /* TOMBOL NAVIGASI PETUNJUK ARAH */
        .map-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--orange);
            color: white;

            /* Ukuran teks tombol diturunkan */
            font-size: 10px;

            /* Ukuran padding tombol diperkecil agar mungil */
            padding: 6px 12px;

            border-radius: 6px;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(255, 84, 0, 0.15);

            transition: all 0.3s ease;
        }

        .map-link:hover {
            background: var(--orange-hover);
            box-shadow: 0 6px 12px rgba(255, 84, 0, 0.25);
            transform: translateY(-1px);
        }

        /* Penyesuaian responsif layar kecil / HP */
        @media (max-width: 576px) {
            .map-overlay-card {
                top: 12px;
                right: 12px;
                /* Disesuaikan ke kanan juga pada mobile */
                left: auto;
                padding: 14px;
                max-width: calc(100% - 24px);
            }
        }

        /* BOTTOM BANNER (CTA) */



        /* Efek Hover pada Banner */

        /* DETAKAN ANIMASI MEMANTUL (KEYFRAMES) */
        @keyframes bounceIcon {
            0% {
                transform: translateY(0);
            }

            100% {
                transform: translateY(-4px);
                /* Memantul naik sejauh 4px */
            }
        }

        /* BANNER ALAT BASKET (STORE CTA) */
        .store-cta-section {
            padding: 60px 8%;
            background: #FFFFFF;
        }

        .store-banner {
            background: #1E2530;
            /* Warna gelap minimalis sesuai gambar */
            border-radius: 24px;
            padding: 60px 80px 110px 80px;
            /* Ditambah padding bawah (110px) untuk memberi ruang baris fitur putih */
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;

            transition: transform 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .store-banner:hover {
            transform: scale(1.005);
        }

        /* SISI KIRI: TEKS KONTEN */
        .store-content {
            max-width: 50%;
            position: relative;
            z-index: 2;
        }

        .store-content h2 {
            font-size: 34px;
            font-weight: 800;
            line-height: 1.25;
            margin-bottom: 16px;
        }

        .store-content h2 span {
            color: var(--orange);
            /* Sorotan warna oranye pada teks */
        }

        .store-content p {
            font-size: 14px;
            color: #94A3B8;
            line-height: 1.6;
            margin-bottom: 32px;
            max-width: 480px;
        }

        /* TOMBOL ORANYE DENGAN IKON BELANJA */
        .btn-store-cta {
            background: var(--orange);
            color: white;
            padding: 14px 28px;
            font-weight: 700;
            font-size: 14px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            box-shadow: 0 4px 14px rgba(255, 84, 0, 0.2);

            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-store-cta:hover {
            background-color: var(--orange-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 84, 0, 0.4);
        }

        /* SISI KANAN: DISPLAY PRODUK */
        .store-visual {
            position: absolute;
            right: 0;
            top: 0;
            width: 50%;
            height: calc(100% - 50px);
            /* Dikurangi 50px agar pas berada di atas baris fitur putih */
            z-index: 1;
        }

        .store-gear-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            pointer-events: none;
        }


        /* BARIS FITUR PUTIH DI BAGIAN BAWAH (WHITE SHELF) */
        .store-features-shelf {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 72%;
            /* Menempati 72% lebar kanan bawah */
            height: 50px;
            background-color: #F8FAFC;
            /* Warna putih off-white sesuai gambar */

            /* Membuat lengkungan sudut kiri atas baris */
            border-top-left-radius: 28px;

            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 0 30px;
            z-index: 3;
        }

        /* TRIK INVERTED BORDER RADIUS UNTUK MEMBUAT GELOMBANG "S" YANG SAMA PERSIS */
        .store-features-shelf::before {
            content: '';
            position: absolute;
            top: -28px;
            /* Ukuran disesuaikan dengan radius */
            left: -28px;
            width: 28px;
            height: 28px;
            background: transparent;
            border-bottom-right-radius: 28px;

            /* Menggunakan bayangan melengkung untuk menggambar lekukan S terbalik */
            box-shadow: 14px 14px 0 0 #F8FAFC;
            pointer-events: none;
        }

        /* ITEM FITUR DI DALAM BARIS PUTIH */
        .shelf-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1E293B;
        }

        .shelf-item i {
            color: #475569;
            /* Ikon abu-abu gelap sesuai gambar */
            font-size: 15px;
        }

        .shelf-item span {
            font-size: 11px;
            font-weight: 750;
            letter-spacing: -0.2px;
        }

        /* Penyesuaian Responsif Layar HP */
        @media (max-width: 992px) {
            .store-banner {
                flex-direction: column;
                padding: 40px 30px 40px 30px;
                align-items: flex-start;
            }

            .store-content {
                max-width: 100%;
                margin-bottom: 40px;
            }

            .store-visual {
                position: relative;
                width: 100%;
                height: 250px;
            }

            .store-features-shelf {
                display: none;
                /* Sembunyikan baris putih pada mobile agar tidak menumpuk */
            }
        }

        /* FOOTER */
        footer {
            background: #0F172A;
            color: #94A3B8;
            padding: 80px 8% 40px 8%;
        }

        .footer-grid {
            display: grid;
            grid-template-columns: 1.5fr 1fr 1fr 1fr;
            gap: 40px;
            margin-bottom: 60px;
        }

        .footer-brand .logo {
            color: white;
            margin-bottom: 20px;
        }

        .footer-brand p {
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        .social-links {
            display: flex;
            gap: 16px;
        }

        .social-links a {
            width: 36px;
            height: 36px;
            background: #1E293B;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }

        .social-links a:hover {
            background: var(--orange);
        }

        .footer-col h4 {
            color: white;
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 24px;
        }

        .footer-links {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .footer-links li a {
            color: #94A3B8;
            font-size: 13px;
        }

        .footer-links li a:hover {
            color: white;
        }

        .footer-contact-info {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .contact-item {
            display: flex;
            gap: 12px;
            font-size: 13px;
        }

        .contact-item i {
            color: var(--orange);
            margin-top: 3px;
        }

        .footer-bottom {
            padding-top: 40px;
            border-top: 1px solid #1E293B;
            text-align: center;
            font-size: 12px;
        }

        /* LAYER GRADASI HALUS DI BAGIAN BAWAH GAMBAR */
        .bottom-fade-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 50px;
            background: linear-gradient(to top, #ffffff 0%, rgba(255, 255, 255, 0) 100%);
            z-index: 3;
            pointer-events: none;
        }

        /* RESPONSIVITAS */
        @media (max-width: 992px) {
            .navbar {
                padding: 20px 5%;
            }

            .hero-section {
                display: grid;
                grid-template-columns: 35% 65%;
                height: 620px;
                background-color: #ffffff;
                padding-left: 5%;
                padding-top: 0;
                padding-bottom: 0;
                position: relative;
                overflow: hidden;
            }

            .hero-content {
                padding-right: 20px;
                z-index: 10;
                display: flex;
                flex-direction: column;
                justify-content: center;
                height: 100%;
            }

            .hero-content p {
                margin: 0 auto 40px auto;
            }

            .hero-cta {
                justify-content: center;
            }

            .hero-visual {
                min-height: 450px;
            }

            .fade-overlay {
                width: 100%;
                background: linear-gradient(to bottom, #ffffff 0%, rgba(255, 255, 255, 0) 30%);
            }

            .hero-stats-card {
                left: 50%;
                transform: translateX(-50%);
                bottom: 20px;
                width: 90%;
                justify-content: space-around;
            }

            .process-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .membership-section {
                grid-template-columns: 1fr;
            }

            .pricing-container {
                grid-template-columns: 1fr;
                max-width: 500px;
                margin: 0 auto;
            }

            .promo-testimonial-section {
                grid-template-columns: 1fr;
            }

            .cta-banner {
                flex-direction: column;
                gap: 30px;
                text-align: center;
                padding: 40px;
            }

            .footer-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .nav-menu {
                display: none;
            }

            .process-grid {
                grid-template-columns: 1fr;
            }

            .footer-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 992px) {
            .nav-menu {
                position: relative;
                /* Kembalikan ke posisi semula di HP agar aman */
                left: auto;
                transform: none;
                display: none;
                /* Sembunyikan menu di HP */
            }
        }
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <a href="#" class="logo"><i class="fa-solid fa-basketball"
                style="color: var(--orange)"></i>Hoop<span>Ball</span></a>
        <div class="nav-menu">
            <a href="#beranda">Beranda</a>
            <a href="#lapangan">Lapangan</a>
            <a href="#jadwal">Cara Kerja</a>
            <a href="#member">Langganan</a>
            <a href="#alat-basket">Alat Basket</a>

        </div>
        <div class="nav-btns">
            <a href="login.php" class="btn-login">Login</a>
            <a href="register.php" class="btn-join">Daftar Sekarang</a>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="hero-section" id="beranda">
        <div class="hero-content">
            <h1>Sewa Lapangan<br>Basket Jadi<br><span>Lebih Mudah</span></h1>
            <p>Pemesanan lapangan basket favoritmu secara online, pilih jadwal sesuai keinginan, dan nikmati fasilitas
                terbaik untuk pengalaman bermain yang seru!</p>
            <div class="hero-cta">
                <a href="register.php" class="btn-hero-primary"><i class="fa-solid fa-calendar-days"></i> Pemesanan
                    Lapangan</a>
                <a href="#jadwal" class="btn-hero-secondary"><i class="fa-regular fa-clock"></i> Lihat Jadwal</a>
            </div>
        </div>
        <div class="hero-visual">
            <img src="gambar 1 landing page.png" alt="HoopBall Court">
            <div class="fade-overlay"></div>
            <!-- TAMBAHKAN BARIS BARU INI: Gradasi Bawah -->
            <div class="bottom-fade-overlay"></div>

            <!-- Kartu statistik melayang -->
            <div class="hero-stats-card">...</div>

            <!-- Floating Stats Card -->
            <div class="hero-stats-card">
                <div class="stat-box">
                    <div class="stat-icon"><i class="fa-solid fa-calendar-check"></i></div>
                    <div class="stat-num">1200+</div>
                    <div class="stat-label">Pemesanan</div>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-box">
                    <div class="stat-icon"><i class="fa-regular fa-star"></i></div>
                    <div class="stat-num">4.9</div>
                    <div class="stat-label">Rating</div>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-box">
                    <div class="stat-icon"><i class="fa-solid fa-basketball"></i></div>
                    <div class="stat-num"><?= htmlspecialchars($total_lapangan) ?></div>
                    <div class="stat-label">Lapangan Tersedia</div>
                </div>
            </div>
        </div>
    </section>

    <!-- FEATURES BAR -->
    <section class="features-section">
        <div class="feature-card">
            <div class="feature-icon"><i class="fa-regular fa-calendar-check"></i></div>
            <h4>Pemesanan Daring</h4>
            <p>Pesan lapangan kapan saja dan di mana saja dengan mudah dan cepat.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fa-regular fa-clock"></i></div>
            <h4>Jadwal Waktu-Nyata</h4>
            <p>Cek ketersediaan jadwal secara waktu-nyata dan pilih waktu terbaikmu.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fa-solid fa-basketball"></i></div>
            <h4>Alat Basket</h4>
            <p>Bola basket, jersey, sepatu, dan perlengkapan lainnya tersedia dengan kualitas baik.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fa-regular fa-user"></i></div>
            <h4>Anggota Lebih Hemat</h4>
            <p>Dapatkan harga spesial dan berbagai keuntungan eksklusif untuk anggota.</p>
        </div>
    </section>

    <!-- COURT SELECTION SECTION -->
    <section class="court-section" id="lapangan">
        <div class="section-header">
            <h2>Pilih Lapangan Favoritmu</h2>
        </div>
        <div class="court-grid">
            <div class="court-card">
                <div class="court-img-container">
                    <img src="lapangan1.png" alt="Lapangan A">
                </div>
                <div class="court-info">
                    <h3>Lapangan A</h3>
                    <p>Indoor • Full AC • Lantai Kayu. Cocok untuk latihan & friendly match.</p>
                    <div class="court-footer">
                        <div class="court-price"><span>Rp 250.000</span> / jam</div>
                        <a href="detail.php?id=A" class="btn-detail">Lihat Detail</a>
                    </div>
                </div>
            </div>
            <div class="court-card">
                <div class="court-img-container">
                    <img src="lapangan2.png" alt="Lapangan B">
                </div>
                <div class="court-info">
                    <h3>Lapangan B</h3>
                    <p>Indoor • Full AC • Lantai Vinyl. Ring basket portabel + Scoreboard.</p>
                    <div class="court-footer">
                        <div class="court-price"><span>Rp 200.000</span> / jam</div>
                        <a href="detail.php?id=B" class="btn-detail">Lihat Detail</a>
                    </div>
                </div>
            </div>
            <div class="court-card">
                <div class="court-img-container">
                    <img src="lapangan3.png" alt="Lapangan C">
                </div>
                <div class="court-info">
                    <h3>Lapangan C</h3>
                    <p>Semi Indoor • Ventilasi Alami. Cocok untuk latihan komunitas.</p>
                    <div class="court-footer">
                        <div class="court-price"><span>Rp 150.000</span> / jam</div>
                        <a href="detail.php?id=C" class="btn-detail">Lihat Detail</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- BOOKING PROCESS -->
    <section class="process-section" id="jadwal">
        <div class="section-header">
            <h2>Cara Booking Lapangan</h2>
        </div>
        <div class="process-grid">
            <div class="process-card">
                <div class="process-step">1</div>
                <i class="fa-regular fa-map"></i>
                <h4>Pilih Lapangan</h4>
                <p>Pilih lapangan favoritmu sesuai kebutuhan.</p>
            </div>
            <div class="process-card">
                <div class="process-step">2</div>
                <i class="fa-regular fa-calendar"></i>
                <h4>Tentukan Jadwal</h4>
                <p>Pilih tanggal dan waktu yang tersedia.</p>
            </div>
            <div class="process-card">
                <div class="process-step">3</div>
                <i class="fa-regular fa-credit-card"></i>
                <h4>Lakukan Pembayaran</h4>
                <p>Bayar dengan aman melalui metode pembayaran pilihan.</p>
            </div>
            <div class="process-card">
                <div class="process-step">4</div>
                <i class="fa-solid fa-basketball"></i>
                <h4>Main Sesuai Jadwal</h4>
                <p>Datang tepat waktu dan nikmati permainanmu!</p>
            </div>
        </div>
    </section>

    <!-- MEMBERSHIP SECTION -->
    <section class="membership-section" id="member">
        <div class="member-intro">
            <h2>Gabung Jadi Anggota</h2>
            <p>Nikmati berbagai keuntungan eksklusif dan harga spesial setiap kali pemesanan lapangan. Hemat lebih
                banyak!
            </p>

            <div class="member-benefit-list">
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fa-solid fa-tags"></i></div>
                    <div class="benefit-text">
                        <h4>Lebih Hemat</h4>
                        <p>Harga spesial khusus untuk Anggota.</p>
                    </div>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fa-solid fa-trophy"></i></div>
                    <div class="benefit-text">
                        <h4>Prioritas Pemesanan</h4>
                        <p>Akses awal untuk jadwal prime time.</p>
                    </div>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fa-solid fa-gift"></i></div>
                    <div class="benefit-text">
                        <h4>Benefit Eksklusif</h4>
                        <p>Dapatkan merchandise & promo spesial khusus member baru.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="pricing-container">
            <div class="pricing-card">
                <div class="price-name">Basic</div>
                <div class="price-amount">Rp 99.000 <span>/ bulan</span></div>
                <ul class="price-features">
                    <li><i class="fa-solid fa-circle-check"></i> Diskon 10% setiap pemesanan</li>
                    <li><i class="fa-solid fa-circle-check"></i> Gratis pembatalan 1x / bulan</li>
                    <li><i class="fa-solid fa-circle-check"></i> Poin reward setiap transaksi</li>
                    <li><i class="fa-solid fa-circle-check"></i> Support prioritas</li>
                </ul>
                <a href="register.php?plan=basic" class="btn-price outline">Daftar Anggota</a>
            </div>

            <div class="pricing-card premium">
                <div class="popular-badge">Paling Populer</div>
                <div class="price-name">Premium</div>
                <div class="price-amount">Rp 199.000 <span>/ bulan</span></div>
                <ul class="price-features">
                    <li><i class="fa-solid fa-circle-check"></i> Diskon 20% setiap pemesanan</li>
                    <li><i class="fa-solid fa-circle-check"></i> Gratis pembatalan 3x / bulan</li>
                    <li><i class="fa-solid fa-circle-check"></i> Poin reward 2x lebih banyak</li>
                    <li><i class="fa-solid fa-circle-check"></i> Akses jadwal eksklusif</li>
                    <li><i class="fa-solid fa-circle-check"></i> Merchandise eksklusif</li>
                </ul>
                <a href="register.php?plan=premium" class="btn-price outline">Daftar Anggota</a>
            </div>
        </div>
    </section>

    <!-- PROMO & TESTIMONIALS -->
    <section class="promo-testimonial-section">
        <div class="promo-card">
            <!-- Badge Atas -->
            <div class="promo-badge">Promo Weekend</div>

            <!-- Blok Konten Teks & Tombol -->
            <div class="promo-content-block">
                <h3>Diskon 20%</h3>
                <p>Untuk semua lapangan</p>

                <!-- Tombol Kuning Kapsul/Pill -->
                <div class="btn-promo-yellow">Setiap Sabtu & Minggu</div>

                <!-- Teks Syarat & Ketentuan Tipis -->
                <div class="promo-terms">*Syarat & ketentuan berlaku</div>
            </div>

            <!-- Gambar Atlet Basket Dinamis -->
            <img src="promo1.png" class="promo-player-img" alt="Basketball Player Promo">
        </div>
        </div>

        <!-- KARTU MAP LOKASI GOOGLE MAPS (BARU) -->
        <div class="location-map-card">
            <!-- Peta Google Maps -->
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3965.3545986499857!2d107.14830219999999!3d-6.3481107!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e699b896d7fc649%3A0xe0a940b1f200d008!2sPoliteknik%20Astra!5e0!3m2!1sid!2sid!4v1780735557436!5m2!1sid!2sid"
                style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
            </iframe>

            <!-- KARTU ALAMAT MELAYANG (GLASSMORPHISM OVERLAY) -->
            <div class="map-overlay-card">
                <span class="map-badge"><i class="fa-solid fa-location-dot"></i> Lokasi Utama</span>
                <h4>Politeknik Astra</h4>
                <p>Delta Silicon II, Cibatu, Cikarang Selatan, Bekasi, Jawa Barat 17530</p>
                <a href="https://maps.app.goo.gl/FpzS6FdUWPp6kGvQ9" target="_blank" class="map-link">
                    Petunjuk Arah <i class="fa-solid fa-arrow-turn-up"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- BOTTOM CALL TO ACTION -->
    <section class="store-cta-section" id="alat-basket">
        <div class="store-banner">
            <!-- Sisi Kiri: Teks Konten & Tombol -->
            <div class="store-content">
                <h2>Lengkapi Permainanmu<br>dengan <span>Alat Basket Berkualitas</span></h2>
                <p>Temukan bola basket, jersey, sepatu, handuk, dan perlengkapan terbaik untuk latihan maupun
                    pertandingan.</p>
                <a href="register.php" class="btn-store-cta">
                    <i class="fa-solid fa-bag-shopping"></i> Lihat Alat Basket
                </a>
            </div>

            <!-- Sisi Kanan: Visual Display Produk Basket -->
            <div class="store-visual">
                <!-- Gunakan URL gambar pajangan perlengkapan basket Anda di sini -->
                <img src="alat basket.png" class="store-gear-img" alt="Basketball Gear Layout">
            </div>

            <!-- Baris Fitur Putih di Bagian Bawah (White Features Shelf) -->
            <div class="store-features-shelf">
                <div class="shelf-item">
                    <i class="fa-regular fa-circle-check"></i>
                    <span>Produk Original & Berkualitas</span>
                </div>
                <div class="shelf-item">
                    <i class="fa-solid fa-tags"></i>
                    <span>Harga Terbaik</span>
                </div>
                <div class="shelf-item">
                    <i class="fa-solid fa-truck-fast"></i>
                    <span>Pengiriman Cepat</span>
                </div>
                <div class="shelf-item">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Aman & Terpercaya</span>
                </div>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer id="tentang-kami">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="#" class="logo"><i class="fa-solid fa-basketball"
                        style="color: var(--orange)"></i>Hoop<span>Ball</span></a>
                <p>Platform penyewaan lapangan basket online yang mudah, cepat, dan terpercaya.</p>
                <div class="social-links">
                    <a href="#"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#"><i class="fa-brands fa-youtube"></i></a>
                    <a href="#"><i class="fa-brands fa-tiktok"></i></a>
                </div>
            </div>

            <div class="footer-col">
                <h4>Kontak Kami</h4>
                <div class="footer-contact-info">
                    <div class="contact-item">
                        <i class="fa-solid fa-phone"></i>
                        <span>0812-3456-7890</span>
                    </div>
                    <div class="contact-item">
                        <i class="fa-solid fa-envelope"></i>
                        <span>info@hoopball.id</span>
                    </div>
                    <div class="contact-item">
                        <i class="fa-solid fa-location-dot"></i>
                        <span>Jl. Sunset Road No. 123, Jakarta Selatan, 12050</span>
                    </div>
                </div>
            </div>

            <div class="footer-col">
                <h4>Tautan</h4>
                <ul class="footer-links">
                    <li><a href="#lapangan">Lapangan</a></li>
                    <li><a href="#jadwal">Jadwal</a></li>
                    <li><a href="#alat-basket">Alat Basket</a></li>
                    <li><a href="#tentang-kami">Tentang Kami</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Informasi</h4>
                <ul class="footer-links">
                    <li><a href="#">Cara Pemesanan</a></li>
                    <li><a href="#">Syarat & Ketentuan</a></li>
                    <li><a href="#">Kebijakan Privasi</a></li>
                    <li><a href="#">FAQ</a></li>
                    <li><a href="#">Hubungi Kami</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; 2024 HoopBall. All rights reserved.</p>
        </div>
    </footer>

    <!-- SCRIPT SCROLLSPY OTOMATIS HIGH-END -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const sections = document.querySelectorAll('section[id], footer[id]');
            const navLinks = document.querySelectorAll('.nav-menu a');

            // Fungsi untuk mendeteksi posisi scroll layar
            window.addEventListener('scroll', () => {
                let currentSectionId = '';

                sections.forEach(section => {
                    const sectionTop = section.offsetTop;
                    const sectionHeight = section.clientHeight;

                    // Deteksi jika posisi scroll sudah melewati posisi seksi terkait
                    if (window.pageYOffset >= (sectionTop - 150)) {
                        currentSectionId = section.getAttribute('id');
                    }
                });

                // Hapus kelas 'active' dari semua menu, dan tambahkan hanya pada menu yang aktif
                navLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === `#${currentSectionId}`) {
                        link.classList.add('active');
                    }
                });
            });

            // Tambahkan juga kelas active secara langsung saat menu pertama kali diklik
            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    navLinks.forEach(item => item.classList.remove('active'));
                    link.classList.add('active');
                });
            });
        });
    </script>

</body>

</html>