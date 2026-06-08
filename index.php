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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
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
            padding: 20px 8%;
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
        }

        .logo span {
            color: var(--orange);
        }

        .nav-menu {
            display: flex;
            gap: 32px;
        }

        .nav-menu a {
            color: var(--text-muted);
            font-weight: 600;
            font-size: 14px;
        }

        .nav-menu a:hover {
            color: var(--orange);
        }

        .nav-btns {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .btn-login {
            color: var(--text-dark);
            font-weight: 700;
            font-size: 14px;
            padding: 10px 24px;
            border-radius: 8px;
            border: 1px solid var(--border-color);
            background: transparent;
        }

        .btn-login:hover {
            background: var(--bg-light);
        }

        .btn-join {
            background: var(--orange);
            color: #fff;
            font-weight: 700;
            font-size: 14px;
            padding: 12px 24px;
            border-radius: 8px;
            box-shadow: 0 4px 14px rgba(255, 84, 0, 0.2);
        }

        .btn-join:hover {
            background: var(--orange-hover);
            transform: translateY(-2px);
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
            box-shadow: 0 4px 15px rgba(255, 69, 0, 0.2);
        }

        .btn-hero-primary:hover {
            background-color: var(--orange-hover);
            transform: translateY(-1px);
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
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
        }

        .btn-hero-secondary:hover {
            background-color: #F8FAFC;
            border-color: #CBD5E1;
        }

        .hero-visual {
            position: relative;
    
    /* Diwajibkan menggunakan height: 100% (sekarang bekerja sempurna karena parent memiliki height pasti) */
    height: 100%; 
    width: 100%;
    overflow: hidden;
        }

        .hero-visual img {
     position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%; /* Sekarang meregang penuh hingga ke pixel terakhir */
    object-fit: cover;
    display: block;
    pointer-events: none;
            
        }

        @media (max-width: 992px) {
    .hero-section {
        height: auto; /* Kembalikan ke auto pada layar HP agar tidak terpotong */
        grid-template-columns: 1fr;
    }
    .hero-visual {
        height: 400px; /* Batasi tinggi gambar di HP */
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
            width: 30%; 
            height: 100%;
            background: linear-gradient(to right, #ffffff 0%, rgba(255, 255, 255, 0) 100%);
            z-index: 2;
        }

        /* KARTU STATISTIK MELAY.hero-sectionANG */
        .hero-stats-card {
            position: absolute;
            bottom: 35px;
            left: 29%; 
            background: #ffffff;
            border-radius: 20px;
            padding: 24px 32px;
            display: flex;
            align-items: center;
            gap: 28px;
            box-shadow: 0 10px 35px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(0, 0, 0, 0.03);
            z-index: 5;
        }

        .stat-box {
            text-align: center;
            min-width: 80px;
        }

        .stat-icon {
            font-size: 22px;
            color: var(--orange);
            margin-bottom: 8px;
        }

        .stat-num {
            font-size: 24px;
            font-weight: 800;
            color: #111111;
            line-height: 1.1;
        }

        .stat-label {
            font-size: 11px;
            color: var(--text-muted);
            font-weight: 600;
            margin-top: 4px;
        }

        .stat-divider {
            width: 1px;
            height: 40px;
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
        }

        .process-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            position: relative;
        }

        .process-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 32px 24px;
            text-align: center;
            position: relative;
            z-index: 2;
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
            margin: 0 auto 20px auto;
        }

        .process-card i {
            font-size: 36px;
            color: var(--text-muted);
            margin-bottom: 20px;
        }

        .process-card h4 {
            font-size: 16px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .process-card p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* MEMBERSHIP SECTION */
        .membership-section {
            padding: 100px 8%;
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 60px;
            align-items: center;
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
            gap: 24px;
        }

        .benefit-item {
            display: flex;
            gap: 16px;
            align-items: flex-start;
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
        }

        .benefit-icon i {
            color: var(--orange);
            font-size: 18px;
        }

        .benefit-text h4 {
            font-size: 15px;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 4px;
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
        }

        .pricing-card.premium {
            border-color: var(--orange);
            box-shadow: 0 10px 30px rgba(255, 84, 0, 0.08);
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
        }

        .btn-price.outline {
            border: 1px solid var(--orange);
            color: var(--orange);
            background: transparent;
        }

        .btn-price.outline:hover {
            background: #FFF0E9;
        }

        .btn-price.filled {
            background: var(--orange);
            color: white;
            border: none;
        }

        .btn-price.filled:hover {
            background: var(--orange-hover);
        }

        /* PROMO & TESTIMONIALS */
        .promo-testimonial-section {
            padding: 80px 8%;
            background: var(--bg-light);
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 40px;
        }

        .promo-card {
            background: linear-gradient(135deg, #FF6B35 0%, #FF4500 100%);
            border-radius: 24px;
            padding: 40px 30px;
            color: white;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 380px;
            box-shadow: 0 15px 35px rgba(255, 69, 0, 0.2);
        }

        .promo-badge {
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
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
        }

        .promo-player-img {
            position: absolute;
            bottom: 0;
            right: -20px;
            height: 200px;
            object-fit: contain;
            pointer-events: none;
        }

        /* TESTIMONIAL CARDS */
        .testimonials-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }

        .testi-card {
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .testi-stars {
            color: #FBBF24;
            margin-bottom: 16px;
            font-size: 12px;
        }

        .testi-text {
            font-size: 13px;
            color: var(--text-dark);
            line-height: 1.6;
            margin-bottom: 24px;
            font-style: italic;
        }

        .testi-user {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .testi-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
        }

        .testi-user-info h5 {
            font-size: 13px;
            font-weight: 700;
            color: var(--dark);
        }

        .testi-user-info p {
            font-size: 11px;
            color: var(--text-muted);
        }

        /* BOTTOM BANNER (CTA) */
        .bottom-cta-section {
            padding: 60px 8%;
            background: #FFFFFF;
        }

        .cta-banner {
            background: radial-gradient(circle at right, #2C3539 0%, #111827 100%);
            border-radius: 24px;
            padding: 60px 80px;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .cta-content {
            max-width: 550px;
            position: relative;
            z-index: 2;
        }

        .cta-content h2 {
            font-size: 36px;
            font-weight: 800;
            margin-bottom: 16px;
        }

        .cta-content p {
            font-size: 15px;
            opacity: 0.8;
            line-height: 1.6;
        }

        .btn-cta-booking {
            background: var(--orange);
            color: white;
            padding: 16px 32px;
            font-weight: 700;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 20px rgba(255, 84, 0, 0.3);
            position: relative;
            z-index: 2;
            flex-shrink: 0;
        }

        .btn-cta-booking:hover {
            background: var(--orange-hover);
        }

        .cta-basketball-img {
            position: absolute;
            right: 0;
            bottom: -50px;
            height: 250px;
            opacity: 0.15;
            pointer-events: none;
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
    
    /* Mengatur tinggi kelembutan gradasi bawah (bisa diubah sesuai selera, misal 40px - 80px) */
    height: 30px; 
    
    /* Membuat efek memudar dari putih solid di bawah ke transparan ke atas */
    background: linear-gradient(to top, #ffffff 0%, rgba(255, 255, 255, 0) 100%);
    
    z-index: 3; /* Berada di atas gambar */
    pointer-events: none; /* Mencegah bentrok klik pada tombol/gambar */
}

        /* RESPONSIVITAS */
        @media (max-width: 992px) {
            .navbar { padding: 20px 5%; }
            .hero-section { grid-template-columns: 1fr; text-align: center; padding-left: 5%; padding-right: 5%; padding-bottom: 140px; }
            .hero-content { padding-right: 0; }
            .hero-content p { margin: 0 auto 40px auto; }
            .hero-cta { justify-content: center; }
            .hero-visual { min-height: 450px; }
            .fade-overlay { width: 100%; background: linear-gradient(to bottom, #ffffff 0%, rgba(255, 255, 255, 0) 30%); }
            .hero-stats-card { left: 50%; transform: translateX(-50%); bottom: 20px; width: 90%; justify-content: space-around; }
            .process-grid { grid-template-columns: repeat(2, 1fr); }
            .membership-section { grid-template-columns: 1fr; }
            .pricing-container { grid-template-columns: 1fr; max-width: 500px; margin: 0 auto; }
            .promo-testimonial-section { grid-template-columns: 1fr; }
            .testimonials-grid { grid-template-columns: 1fr; }
            .cta-banner { flex-direction: column; gap: 30px; text-align: center; padding: 40px; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
        }

        @media (max-width: 576px) {
            .nav-menu { display: none; }
            .process-grid { grid-template-columns: 1fr; }
            .footer-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <a href="#" class="logo"><i class="fa-solid fa-basketball" style="color: var(--orange)"></i>Hoop<span>Ball</span></a>
        <div class="nav-menu">
            <a href="#lapangan">Lapangan</a>
            <a href="#jadwal">Jadwal</a>
            <a href="#member">Member</a>
            <a href="#alat-basket">Alat Basket</a>
            <a href="#tentang-kami">Tentang Kami</a>
        </div>
        <div class="nav-btns">
            <a href="login.php" class="btn-login">Login</a>
            <a href="register.php" class="btn-join">Booking Sekarang</a>
        </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="hero-section">
        <div class="hero-content">
            <h1>Sewa Lapangan<br>Basket Jadi<br><span>Lebih Mudah</span></h1>
            <p>Booking lapangan basket favoritmu secara online, pilih jadwal sesuai keinginan, dan nikmati fasilitas terbaik untuk pengalaman bermain yang seru!</p>
            <div class="hero-cta">
                <a href="register.php" class="btn-hero-primary"><i class="fa-solid fa-calendar-days"></i> Booking Lapangan</a>
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
                    <div class="stat-label">Booking</div>
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
            <h4>Booking Online</h4>
            <p>Pesan lapangan kapan saja dan di mana saja dengan mudah dan cepat.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fa-regular fa-clock"></i></div>
            <h4>Jadwal Real-Time</h4>
            <p>Cek ketersediaan jadwal secara real-time dan pilih waktu terbaikmu.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fa-solid fa-dumbbell"></i></div>
            <h4>Fasilitas Lengkap</h4>
            <p>Lapangan berkualitas dengan fasilitas lengkap untuk kenyamanan maksimal.</p>
        </div>
        <div class="feature-card">
            <div class="feature-icon"><i class="fa-regular fa-user"></i></div>
            <h4>Member Lebih Hemat</h4>
            <p>Dapatkan harga spesial dan berbagai keuntungan eksklusif untuk member.</p>
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
                    <img src="https://images.unsplash.com/photo-1504450758481-7338eba7524a?q=80&w=800" alt="Lapangan A">
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
                    <img src="https://images.unsplash.com/photo-1544919982-b61976f0ba43?q=80&w=800" alt="Lapangan B">
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
                    <img src="https://images.unsplash.com/photo-1574623452334-1e0ac2b3ccb4?q=80&w=800" alt="Lapangan C">
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
            <h2>Cara Booking</h2>
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
            <h2>Gabung Jadi Member</h2>
            <p>Nikmati berbagai keuntungan eksklusif dan harga spesial setiap kali booking lapangan. Hemat lebih banyak!</p>
            
            <div class="member-benefit-list">
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fa-solid fa-tags"></i></div>
                    <div class="benefit-text">
                        <h4>Lebih Hemat</h4>
                        <p>Harga spesial khusus untuk member.</p>
                    </div>
                </div>
                <div class="benefit-item">
                    <div class="benefit-icon"><i class="fa-solid fa-trophy"></i></div>
                    <div class="benefit-text">
                        <h4>Prioritas Booking</h4>
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
                    <li><i class="fa-solid fa-circle-check"></i> Diskon 10% setiap booking</li>
                    <li><i class="fa-solid fa-circle-check"></i> Gratis pembatalan 1x / bulan</li>
                    <li><i class="fa-solid fa-circle-check"></i> Poin reward setiap transaksi</li>
                    <li><i class="fa-solid fa-circle-check"></i> Support prioritas</li>
                </ul>
                <a href="register.php?plan=basic" class="btn-price outline">Daftar Member</a>
            </div>
            
            <div class="pricing-card premium">
                <div class="popular-badge">Paling Populer</div>
                <div class="price-name">Premium</div>
                <div class="price-amount">Rp 199.000 <span>/ bulan</span></div>
                <ul class="price-features">
                    <li><i class="fa-solid fa-circle-check"></i> Diskon 20% setiap booking</li>
                    <li><i class="fa-solid fa-circle-check"></i> Gratis pembatalan 3x / bulan</li>
                    <li><i class="fa-solid fa-circle-check"></i> Poin reward 2x lebih banyak</li>
                    <li><i class="fa-solid fa-circle-check"></i> Akses jadwal eksklusif</li>
                    <li><i class="fa-solid fa-circle-check"></i> Merchandise eksklusif</li>
                </ul>
                <a href="register.php?plan=premium" class="btn-price filled">Daftar Member</a>
            </div>
        </div>
    </section>

    <!-- PROMO & TESTIMONIALS -->
    <section class="promo-testimonial-section">
        <div class="promo-card">
            <div class="promo-badge">Promo Weekend</div>
            <div class="promo-title">
                <h3>Diskon 20%</h3>
                <p>Untuk semua lapangan</p>
                <div class="btn-promo">Setiap Sabtu-Minggu</div>
            </div>
            <img src="https://images.unsplash.com/photo-1519766304817-4f37bda74a27?q=80&w=400" class="promo-player-img" alt="Basketball Player Promo">
        </div>

        <div class="testimonials-grid">
            <div class="testi-card">
                <div>
                    <div class="testi-stars">
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                    </div>
                    <p class="testi-text">"Lapangan bersih, kualitas lengkap, booking mudah dan cepat. Pasti jadi langganan!"</p>
                </div>
                <div class="testi-user">
                    <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?q=80&w=100" class="testi-avatar" alt="Brian Pratama">
                    <div class="testi-user-info">
                        <h5>Brian Pratama</h5>
                        <p>Pendiri Komunitas</p>
                    </div>
                </div>
            </div>
            <div class="testi-card">
                <div>
                    <div class="testi-stars">
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                    </div>
                    <p class="testi-text">"Jadwal real-time nya akurat, nggak perlu takut bentrok. Pelayanannya top!"</p>
                </div>
                <div class="testi-user">
                    <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?q=80&w=100" class="testi-avatar" alt="Andi Wijaya">
                    <div class="testi-user-info">
                        <h5>Andi Wijaya</h5>
                        <p>Member Premium</p>
                    </div>
                </div>
            </div>
            <div class="testi-card">
                <div>
                    <div class="testi-stars">
                        <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                    </div>
                    <p class="testi-text">"Harga terjangkau, kualitas lapangan juara. Recommended buat semua!"</p>
                </div>
                <div class="testi-user">
                    <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?q=80&w=100" class="testi-avatar" alt="Rizky Maulana">
                    <div class="testi-user-info">
                        <h5>Rizky Maulana</h5>
                        <p>Mahasiswa</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- BOTTOM CALL TO ACTION -->
    <section class="bottom-cta-section">
        <div class="cta-banner">
            <div class="cta-content">
                <h2>Siap Main Basket Hari Ini?</h2>
                <p>Booking sekarang dan rasakan pengalaman bermain basket terbaik bersama HoopBall!</p>
            </div>
            <a href="register.php" class="btn-cta-booking"><i class="fa-regular fa-calendar-check"></i> Booking Sekarang</a>
            <img src="https://images.unsplash.com/photo-1519766304817-4f37bda74a27?q=80&w=400" class="cta-basketball-img" alt="Basketball">
        </div>
    </section>

    <!-- FOOTER -->
    <footer id="tentang-kami">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="#" class="logo"><i class="fa-solid fa-basketball" style="color: var(--orange)"></i>Hoop<span>Ball</span></a>
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
                    <li><a href="#">Cara Booking</a></li>
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

</body>
</html>