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
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* AKTIFKAN SCROLL HALUS DI SELURUH HALAMAN */
        html {
            scroll-behavior: smooth;
        }

        /* OFFSET SCROLL */
        section[id],
        footer[id] {
            scroll-margin-top: 90px;
        }

        :root {
            --orange: #FF4500;
            --orange-light: #FF6B35;
            --orange-dark: #CC3700;
            --orange-glow: rgba(255, 69, 0, 0.6);
            --dark: #0A0E17;
            --darker: #05070A;
            --text-dark: #1E293B;
            --text-muted: #64748B;
            --bg-light: #F8FAFC;
            --border-color: #E2E8F0;
            --card-bg: #FFFFFF;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Barlow', sans-serif;
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

        /* ═══════════════════════════════════════════════════════════════
           NAVBAR
           ═══════════════════════════════════════════════════════════════ */
        .navbar {
            position: sticky;
            top: 0;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 4%;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.04);
            z-index: 1000;
        }

        .logo {
            font-size: 28px;
            font-weight: 800;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .logo img {
            width: auto;
            height: 64px;
            object-fit: contain;
            display: inline-block;
            margin-right: 10px;
            vertical-align: middle;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,0.2));
        }

        .logo span {
            color: var(--orange);
        }

        .nav-menu {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 32px;
            z-index: 5;
        }

        .nav-menu a {
                color: var(--text-muted);
    
    /* GANTI INI: 
       - Tebal huruf dinaikkan dari 600 menjadi 700 (Bold) agar lebih tegas
       - Ukuran huruf dinaikkan dari 14px menjadi 16px agar pas dengan tinggi logo & tombol */
    font-weight: 700;
    font-size: 16px; 
    
    position: relative;
    padding: 8px 0;
    text-decoration: none;
    transition: color 0.3s ease;
        }

        .nav-menu a::after {
             content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 2.5px; /* Tebal garis bawah disesuaikan sedikit */
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

        .nav-menu a.active::after {
            transform: scaleX(1) !important;
            transform-origin: left !important;
        }

        .nav-btns {
            display: flex;
            align-items: center;
            gap: 16px;
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
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
        }

        .btn-login:hover {
            border-color: var(--orange);
            color: var(--orange);
            background-color: rgba(255, 69, 0, 0.02);
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
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
            border: none;
        }

        .btn-join:hover {
            background-color: var(--orange-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 69, 0, 0.35);
        }

        /* ═══════════════════════════════════════════════════════════════
           HERO SECTION
           ═══════════════════════════════════════════════════════════════ */
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

        .hero-content h1 {
            font-size: 52px;
            font-weight: 800;
            line-height: 1.15;
            color: #111111;
            margin-bottom: 24px;
            letter-spacing: -1px;
            font-family: 'Barlow Condensed', sans-serif;
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
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-hero-primary i {
            transition: transform 0.3s ease;
        }

        .btn-hero-primary:hover {
            background-color: var(--orange-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 69, 0, 0.35);
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
            text-decoration: none;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-hero-secondary i {
            transition: transform 0.4s ease;
        }

        .btn-hero-secondary:hover {
            background-color: var(--bg-light);
            border-color: #CBD5E1;
            transform: translateY(-2px);
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
        }

        .hero-visual video {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    pointer-events: none;
    transition: transform 8s cubic-bezier(0.16, 1, 0.3, 1);
}

.hero-section:hover .hero-visual video {
    transform: scale(1.04);
}

        .fade-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 35%;
            height: 100%;
            background: linear-gradient(to right, #ffffff 0%, rgba(255, 255, 255, 0) 100%);
            z-index: 2;
        }

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
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .hero-stats-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
            border-color: rgba(255, 69, 0, 0.1);
        }

        .hero-stats-card:hover .stat-box:nth-child(1) .stat-icon i { transform: translateY(-4px) scale(1.15); }
        .hero-stats-card:hover .stat-box:nth-child(3) .stat-icon i { transform: scale(1.2) rotate(15deg); }
        .hero-stats-card:hover .stat-box:nth-child(5) .stat-icon i { transform: rotate(180deg) scale(1.15); }

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

        /* ═══════════════════════════════════════════════════════════════
           FEATURES SECTION
           ═══════════════════════════════════════════════════════════════ */
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

        /* ═══════════════════════════════════════════════════════════════
           SECTION HEADER
           ═══════════════════════════════════════════════════════════════ */
        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-header h2 {
            font-size: 32px;
            font-weight: 800;
            color: var(--dark);
            font-family: 'Barlow Condensed', sans-serif;
        }

        /* ═══════════════════════════════════════════════════════════════
           COURT SECTION
           ═══════════════════════════════════════════════════════════════ */
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
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-detail:hover {
            background: var(--orange);
            color: white;
        }

        /* ═══════════════════════════════════════════════════════════════
           PROCESS SECTION
           ═══════════════════════════════════════════════════════════════ */
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
            left: 12%;
            right: 12%;
            height: 2px;
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
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
        }

        .process-card:hover {
            transform: translateY(-10px) scale(1.02);
            border-color: var(--orange);
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
            box-shadow: 0 0 12px rgba(255, 69, 0, 0.4);
        }

        .process-card i {
            font-size: 36px;
            color: #94A3B8;
            margin-bottom: 20px;
            display: inline-block;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .process-card:hover i {
            color: var(--orange);
            transform: scale(1.12) rotate(5deg);
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
        }

        .process-card p {
            font-size: 13px;
            color: var(--text-muted);
            line-height: 1.6;
        }

        /* ═══════════════════════════════════════════════════════════════
           MEMBERSHIP SECTION
           ═══════════════════════════════════════════════════════════════ */
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
            font-family: 'Barlow Condensed', sans-serif;
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
        }

        .benefit-item {
            display: flex;
            gap: 16px;
            align-items: flex-start;
            padding: 12px;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .benefit-item:hover {
            transform: translateX(8px);
            background-color: var(--bg-light);
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
        }

        .benefit-icon i {
            color: var(--orange);
            font-size: 18px;
            transition: all 0.3s ease;
        }

        .benefit-item:hover .benefit-icon i {
            color: #ffffff;
            transform: scale(1.1);
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
        }

        .benefit-text p {
            font-size: 13px;
            color: var(--text-muted);
        }

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
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .pricing-card:not(.premium):hover {
            transform: translateY(-8px);
            border-color: var(--orange);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
        }

        .pricing-card:not(.premium):hover .btn-price.outline {
            background-color: var(--orange);
            color: white;
        }

        .pricing-card.premium:hover {
            transform: translateY(-12px);
            border-color: var(--orange);
            box-shadow: 0 24px 48px rgba(255, 69, 0, 0.12);
        }

        .pricing-card.premium:hover .btn-price.outline {
            background-color: var(--orange);
            color: white;
        }

        .pricing-card.premium {
            border-color: var(--border-color);
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
            background-color: var(--orange-dark);
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
            text-decoration: none;
            display: inline-block;
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
            background: var(--orange-dark);
            transform: scale(1.02);
        }

        /* ═══════════════════════════════════════════════════════════════
           PROMO & TESTIMONIALS
           ═══════════════════════════════════════════════════════════════ */
        .promo-testimonial-section {
            padding: 80px 8%;
            background: var(--bg-light);
            display: grid;
            grid-template-columns: 1fr 2.2fr;
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
        }

        .promo-content-block {
            margin-top: auto;
            position: relative;
            z-index: 2;
        }

        .promo-content-block h3 {
            font-size: 42px;
            font-weight: 900;
            line-height: 1.1;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
            font-family: 'Barlow Condensed', sans-serif;
        }

        .promo-content-block p {
            font-size: 15px;
            font-weight: 600;
            opacity: 0.95;
            margin-bottom: 24px;
        }

        .btn-promo-yellow {
            background-color: #FFC107;
            color: #1E293B;
            font-weight: 700;
            font-size: 13px;
            padding: 10px 24px;
            border-radius: 50px;
            display: inline-block;
            width: fit-content;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.2);
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .promo-card:hover .btn-promo-yellow {
            background-color: #FFB300;
            transform: scale(1.04);
            box-shadow: 0 6px 15px rgba(255, 193, 7, 0.35);
        }

        .promo-terms {
            font-size: 10px;
            color: rgba(255, 255, 255, 0.7);
            margin-top: 14px;
            font-weight: 500;
        }

        .promo-player-img {
            position: absolute;
            bottom: 35px;
            right: 25px;
            height: 300px;
            object-fit: contain;
            pointer-events: none;
            z-index: 1;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .promo-card:hover .promo-player-img {
            transform: scale(1.04) translate(3px, -3px);
        }

        .promo-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 45px rgba(255, 69, 0, 0.28);
        }

        /* ═══════════════════════════════════════════════════════════════
           LOCATION MAP
           ═══════════════════════════════════════════════════════════════ */
        .location-map-card {
            position: relative;
            background: white;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.03);
            height: 100%;
            min-height: 380px;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .location-map-card:hover {
            transform: translateY(-6px);
            border-color: var(--orange);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.06);
        }

        .location-map-card iframe {
            width: 100%;
            height: 100%;
            display: block;
            filter: grayscale(100%) contrast(1.1) brightness(0.95);
            transition: filter 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .location-map-card:hover iframe {
            filter: grayscale(0%) contrast(1) brightness(1);
        }

        .map-overlay-card {
            position: absolute;
            top: 16px;
            right: 16px;
            left: auto;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border-radius: 12px;
            padding: 14px 18px;
            max-width: 210px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.8);
            z-index: 10;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .location-map-card:hover .map-overlay-card {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(255, 69, 0, 0.1);
            border-color: rgba(255, 69, 0, 0.2);
        }

        .map-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: #FFF0E9;
            color: var(--orange);
            font-size: 8px;
            padding: 3px 8px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .map-overlay-card h4 {
            font-size: 13px;
            font-weight: 800;
            color: var(--dark);
            margin-bottom: 4px;
        }

        .map-overlay-card p {
            font-size: 10px;
            color: var(--text-muted);
            line-height: 1.4;
            margin-bottom: 12px;
        }

        .map-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--orange);
            color: white;
            font-size: 10px;
            padding: 6px 12px;
            border-radius: 6px;
            text-decoration: none;
            box-shadow: 0 4px 10px rgba(255, 84, 0, 0.15);
            transition: all 0.3s ease;
        }

        .map-link:hover {
            background: var(--orange-dark);
            box-shadow: 0 6px 12px rgba(255, 84, 0, 0.25);
            transform: translateY(-1px);
        }

        /* ═══════════════════════════════════════════════════════════════
           STORE CTA
           ═══════════════════════════════════════════════════════════════ */
        .store-cta-section {
            padding: 60px 8%;
            background: #FFFFFF;
        }

        .store-banner {
            background: #1E2530;
            border-radius: 24px;
            padding: 60px 80px 110px 80px;
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
            font-family: 'Barlow Condensed', sans-serif;
        }

        .store-content h2 span {
            color: var(--orange);
        }

        .store-content p {
            font-size: 14px;
            color: #94A3B8;
            line-height: 1.6;
            margin-bottom: 32px;
            max-width: 480px;
        }

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
            background-color: var(--orange-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 84, 0, 0.4);
        }

        .store-visual {
            position: absolute;
            right: 0;
            top: 0;
            width: 50%;
            height: calc(100% - 50px);
            z-index: 1;
        }

        .store-gear-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            pointer-events: none;
        }

        .store-features-shelf {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 72%;
            height: 50px;
            background-color: #F8FAFC;
            border-top-left-radius: 28px;
            display: flex;
            justify-content: space-around;
            align-items: center;
            padding: 0 30px;
            z-index: 3;
        }

        .store-features-shelf::before {
            content: '';
            position: absolute;
            top: -28px;
            left: -28px;
            width: 28px;
            height: 28px;
            background: transparent;
            border-bottom-right-radius: 28px;
            box-shadow: 14px 14px 0 0 #F8FAFC;
            pointer-events: none;
        }

        .shelf-item {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #1E293B;
        }

        .shelf-item i {
            color: #475569;
            font-size: 15px;
        }

        .shelf-item span {
            font-size: 11px;
            font-weight: 750;
            letter-spacing: -0.2px;
        }

        /* ═══════════════════════════════════════════════════════════════
           FOOTER
           ═══════════════════════════════════════════════════════════════ */
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
            transition: all 0.3s ease;
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
            transition: color 0.3s ease;
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

        /* ═══════════════════════════════════════════════════════════════
           EXIT OVERLAY — Cinematic Basketball Experience
           ═══════════════════════════════════════════════════════════════ */
        .exit-overlay {
            position: fixed;
            inset: 0;
            z-index: 99999;
            pointer-events: none;
            opacity: 0;
            visibility: hidden;
            background: var(--darker);
        }
        .exit-overlay.active {
            pointer-events: all;
            opacity: 1;
            visibility: visible;
        }

        /* Stage: Basketball Court Floor */
        .exit-stage {
            position: absolute;
            inset: 0;
            background: 
                radial-gradient(ellipse at 50% 100%, rgba(255,69,0,0.15) 0%, transparent 60%),
                linear-gradient(180deg, var(--darker) 0%, #0f1520 40%, #1a2332 70%, var(--darker) 100%);
            opacity: 0;
            transform: scale(1.2);
        }
        .exit-overlay.active .exit-stage {
            animation: stageIn 0.8s cubic-bezier(0.22, 1, 0.36, 1) 0.2s forwards;
        }
        @keyframes stageIn {
            0% { opacity: 0; transform: scale(1.2) translateY(50px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }

        .court-lines {
            position: absolute;
            inset: 0;
            opacity: 0.08;
        }
        .court-lines::before {
            content: '';
            position: absolute;
            bottom: 0; left: 50%;
            transform: translateX(-50%);
            width: 600px; height: 400px;
            border: 3px solid var(--orange);
            border-bottom: none;
            border-radius: 300px 300px 0 0;
        }
        .court-lines::after {
            content: '';
            position: absolute;
            bottom: 0; left: 50%;
            transform: translateX(-50%);
            width: 200px; height: 150px;
            border: 3px solid var(--orange);
            border-bottom: none;
            border-radius: 100px 100px 0 0;
        }

        /* Spotlight */
        .exit-spotlight {
            position: absolute;
            top: -100px; left: 50%;
            transform: translateX(-50%);
            width: 400px; height: 600px;
            background: radial-gradient(ellipse at 50% 0%, rgba(255,69,0,0.3) 0%, transparent 70%);
            opacity: 0;
            pointer-events: none;
            mix-blend-mode: screen;
        }
        .exit-overlay.active .exit-spotlight {
            animation: spotlightIn 1s ease-out 0.5s forwards;
        }
        @keyframes spotlightIn {
            from { opacity: 0; transform: translateX(-50%) translateY(-50px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }

        /* Hoop & Backboard */
        .exit-hoop-container {
            position: absolute;
            top: 15%; left: 50%;
            transform: translateX(-50%);
            width: 200px; height: 200px;
            opacity: 0;
        }
        .exit-overlay.active .exit-hoop-container {
            animation: hoopAppear 0.6s cubic-bezier(0.34, 1.56, 0.64, 1) 0.8s forwards;
        }
        @keyframes hoopAppear {
            from { opacity: 0; transform: translateX(-50%) translateY(-100px) scale(0.5); }
            to { opacity: 1; transform: translateX(-50%) translateY(0) scale(1); }
        }

        .backboard {
            position: absolute;
            top: 0; left: 50%;
            transform: translateX(-50%);
            width: 160px; height: 110px;
            background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
            border: 3px solid rgba(255,255,255,0.2);
            border-radius: 4px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        .backboard::after {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 60px; height: 45px;
            border: 2px solid var(--orange);
            border-radius: 2px;
            opacity: 0.6;
        }

        .rim {
            position: absolute;
            top: 105px; left: 50%;
            transform: translateX(-50%);
            width: 100px; height: 12px;
            border: 4px solid #C0392B;
            border-radius: 50%;
            background: linear-gradient(90deg, #C0392B, #E74C3C, #C0392B);
            box-shadow: 0 4px 20px rgba(192, 57, 43, 0.4), 0 0 30px rgba(255,69,0,0.2);
        }

        .net {
            position: absolute;
            top: 115px; left: 50%;
            transform: translateX(-50%);
            width: 90px; height: 80px;
            overflow: hidden;
        }
        .net::before {
            content: '';
            position: absolute;
            inset: 0;
            background: 
                linear-gradient(90deg, transparent 48%, rgba(255,255,255,0.2) 49%, rgba(255,255,255,0.2) 51%, transparent 52%),
                linear-gradient(60deg, transparent 48%, rgba(255,255,255,0.15) 49%, rgba(255,255,255,0.15) 51%, transparent 52%),
                linear-gradient(-60deg, transparent 48%, rgba(255,255,255,0.15) 49%, rgba(255,255,255,0.15) 51%, transparent 52%);
            clip-path: polygon(10% 0%, 90% 0%, 75% 100%, 25% 100%);
            animation: netSway 2s ease-in-out infinite;
        }
        @keyframes netSway {
            0%, 100% { transform: skewX(-3deg) scaleY(1); }
            50% { transform: skewX(3deg) scaleY(1.05); }
        }

        /* Basketball */
        .exit-ball {
            position: absolute;
            width: 70px; height: 70px;
            left: 50%; bottom: 20%;
            transform: translateX(-50%);
            opacity: 0;
            z-index: 50;
        }
        .exit-ball svg {
            width: 100%; height: 100%;
            filter: drop-shadow(0 15px 30px rgba(0,0,0,0.5));
        }
        .exit-overlay.active .exit-ball {
            animation: ballArc 2.5s cubic-bezier(0.25, 0.46, 0.45, 0.94) 1s forwards;
        }
        @keyframes ballArc {
            0% { 
                opacity: 1; 
                left: 50%; bottom: 20%; 
                transform: translateX(-50%) scale(0.5) rotate(0deg); 
            }
            30% { 
                left: 50%; bottom: 55%; 
                transform: translateX(-50%) scale(1) rotate(360deg); 
            }
            50% { 
                left: 50%; bottom: 62%; 
                transform: translateX(-50%) scale(1.1) rotate(540deg); 
            }
            55% { 
                left: 50%; bottom: 60%; 
                transform: translateX(-50%) scale(1) rotate(600deg); 
            }
            60% { 
                left: 50%; bottom: 58%; 
                transform: translateX(-50%) scale(0.95) rotate(630deg); 
            }
            65% { 
                opacity: 1;
                left: 50%; bottom: 56%; 
                transform: translateX(-50%) scale(0.9) rotate(650deg); 
            }
            70% { 
                opacity: 0.8;
                left: 50%; bottom: 30%; 
                transform: translateX(-50%) scale(0.7) rotate(720deg); 
            }
            100% { 
                opacity: 0; 
                left: 50%; bottom: -10%; 
                transform: translateX(-50%) scale(0.3) rotate(900deg); 
            }
        }

        .ball-shadow-floor {
            position: absolute;
            bottom: 18%; left: 50%;
            transform: translateX(-50%);
            width: 60px; height: 15px;
            background: radial-gradient(ellipse, rgba(0,0,0,0.4) 0%, transparent 70%);
            border-radius: 50%;
            opacity: 0;
            z-index: 49;
        }
        .exit-overlay.active .ball-shadow-floor {
            animation: shadowArc 2.5s ease-out 1s forwards;
        }
        @keyframes shadowArc {
            0% { opacity: 0.4; transform: translateX(-50%) scale(1); }
            30% { opacity: 0.2; transform: translateX(-50%) scale(0.6); }
            50% { opacity: 0; transform: translateX(-50%) scale(0.3); }
            100% { opacity: 0; transform: translateX(-50%) scale(0); }
        }

        /* Swish Effect */
        .swish-effect {
            position: absolute;
            top: 58%; left: 50%;
            transform: translateX(-50%);
            width: 120px; height: 80px;
            opacity: 0;
            pointer-events: none;
            z-index: 45;
        }
        .swish-particles {
            position: absolute;
            inset: 0;
        }
        .swish-particle {
            position: absolute;
            width: 4px; height: 4px;
            background: var(--orange);
            border-radius: 50%;
            opacity: 0;
        }
        .exit-overlay.active .swish-effect {
            animation: swishShow 0.1s ease-out 1.55s forwards;
        }
        .exit-overlay.active .swish-particle:nth-child(1) { animation: swishBurst 0.8s ease-out 1.55s forwards; left: 50%; top: 0; --sx: -30px; --sy: 40px; }
        .exit-overlay.active .swish-particle:nth-child(2) { animation: swishBurst 0.8s ease-out 1.58s forwards; left: 50%; top: 0; --sx: 30px; --sy: 35px; }
        .exit-overlay.active .swish-particle:nth-child(3) { animation: swishBurst 0.8s ease-out 1.6s forwards; left: 50%; top: 0; --sx: -15px; --sy: 50px; }
        .exit-overlay.active .swish-particle:nth-child(4) { animation: swishBurst 0.8s ease-out 1.62s forwards; left: 50%; top: 0; --sx: 15px; --sy: 45px; }
        .exit-overlay.active .swish-particle:nth-child(5) { animation: swishBurst 0.8s ease-out 1.65s forwards; left: 50%; top: 0; --sx: -40px; --sy: 30px; }
        .exit-overlay.active .swish-particle:nth-child(6) { animation: swishBurst 0.8s ease-out 1.68s forwards; left: 50%; top: 0; --sx: 40px; --sy: 25px; }
        @keyframes swishShow { from { opacity: 1; } to { opacity: 1; } }
        @keyframes swishBurst {
            0% { opacity: 1; transform: translate(-50%, 0) scale(1); }
            100% { opacity: 0; transform: translate(calc(-50% + var(--sx)), var(--sy)) scale(0); }
        }

        /* Flash Bulb */
        .flash-bulb {
            position: absolute;
            inset: 0;
            background: #fff;
            opacity: 0;
            pointer-events: none;
            z-index: 100;
        }
        .exit-overlay.active .flash-bulb {
            animation: flashPop 0.3s ease-out 1.5s forwards;
        }
        @keyframes flashPop {
            0% { opacity: 0; }
            10% { opacity: 1; }
            100% { opacity: 0; }
        }

        /* Scoreboard */
        .exit-scoreboard {
            position: absolute;
            top: 8%; left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            border: 3px solid var(--orange);
            border-radius: 12px;
            padding: 16px 40px;
            display: flex;
            align-items: center;
            gap: 24px;
            opacity: 0;
            box-shadow: 0 0 40px rgba(255,69,0,0.2), inset 0 0 20px rgba(255,69,0,0.05);
            z-index: 20;
        }
        .exit-overlay.active .exit-scoreboard {
            animation: scoreboardIn 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) 0.6s forwards;
        }
        @keyframes scoreboardIn {
            from { opacity: 0; transform: translateX(-50%) translateY(-30px) scale(0.8); }
            to { opacity: 1; transform: translateX(-50%) translateY(0) scale(1); }
        }

        .score-team { text-align: center; }
        .score-team-name { font-size: 10px; color: rgba(255,255,255,0.5); font-weight: 700; letter-spacing: 2px; text-transform: uppercase; }
        .score-team-logo { font-size: 24px; color: var(--orange); margin: 4px 0; }
        .score-divider { width: 2px; height: 40px; background: linear-gradient(180deg, transparent, var(--orange), transparent); }
        .score-points { font-family: 'Barlow Condensed', sans-serif; font-size: 42px; font-weight: 900; color: #fff; line-height: 1; }
        .score-points span { font-size: 14px; color: var(--orange); }
        .score-timer { font-size: 11px; color: rgba(255,255,255,0.4); font-weight: 700; letter-spacing: 3px; margin-top: 4px; }

        .exit-overlay.active .score-points {
            animation: scoreTick 0.3s ease-out 1.6s forwards;
        }
        @keyframes scoreTick {
            0% { transform: scale(1); }
            50% { transform: scale(1.3); color: var(--orange); }
            100% { transform: scale(1); }
        }

        /* Confetti */
        .confetti-container {
            position: absolute;
            inset: 0;
            pointer-events: none;
            overflow: hidden;
            z-index: 60;
        }
        .confetti-piece {
            position: absolute;
            width: 10px; height: 10px;
            opacity: 0;
            top: 50%; left: 50%;
        }
        .confetti-piece:nth-child(1) { background: var(--orange); --cx: -200px; --cy: -300px; --cr: 720deg; animation-delay: 1.6s; }
        .confetti-piece:nth-child(2) { background: #FF6B35; --cx: 150px; --cy: -350px; --cr: -540deg; animation-delay: 1.65s; }
        .confetti-piece:nth-child(3) { background: #FFD700; --cx: 250px; --cy: -200px; --cr: 360deg; animation-delay: 1.7s; }
        .confetti-piece:nth-child(4) { background: var(--orange); --cx: -150px; --cy: -400px; --cr: -720deg; animation-delay: 1.75s; }
        .confetti-piece:nth-child(5) { background: #FF8C42; --cx: 100px; --cy: -450px; --cr: 450deg; animation-delay: 1.8s; }
        .confetti-piece:nth-child(6) { background: #FFD700; --cx: -250px; --cy: -250px; --cr: -360deg; animation-delay: 1.85s; }
        .confetti-piece:nth-child(7) { background: var(--orange); --cx: 300px; --cy: -300px; --cr: 630deg; animation-delay: 1.9s; }
        .confetti-piece:nth-child(8) { background: #FF6B35; --cx: -100px; --cy: -350px; --cr: -450deg; animation-delay: 1.95s; }
        .confetti-piece:nth-child(9) { background: #FFD700; --cx: 200px; --cy: -400px; --cr: 540deg; animation-delay: 2s; }
        .confetti-piece:nth-child(10) { background: var(--orange); --cx: -300px; --cy: -200px; --cr: -630deg; animation-delay: 2.05s; }
        .confetti-piece:nth-child(11) { background: #FF8C42; --cx: 50px; --cy: -500px; --cr: 720deg; animation-delay: 2.1s; }
        .confetti-piece:nth-child(12) { background: #FFD700; --cx: -200px; --cy: -450px; --cr: -540deg; animation-delay: 2.15s; }

        .exit-overlay.active .confetti-piece {
            animation: confettiExplode 1.5s ease-out forwards;
        }
        @keyframes confettiExplode {
            0% { opacity: 1; transform: translate(-50%, -50%) scale(1) rotate(0deg); }
            100% { opacity: 0; transform: translate(calc(-50% + var(--cx)), calc(-50% + var(--cy))) scale(0) rotate(var(--cr)); }
        }

        /* Text Reveal */
        .exit-message {
            position: absolute;
            bottom: 15%; left: 50%;
            transform: translateX(-50%);
            text-align: center;
            opacity: 0;
            z-index: 70;
        }
        .exit-overlay.active .exit-message {
            animation: messageIn 0.8s ease-out 2.2s forwards;
        }
        @keyframes messageIn {
            from { opacity: 0; transform: translateX(-50%) translateY(30px); }
            to { opacity: 1; transform: translateX(-50%) translateY(0); }
        }

        .exit-message-tagline {
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 6px;
            text-transform: uppercase;
            color: var(--orange);
            margin-bottom: 12px;
        }

        .exit-message-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 56px;
            font-weight: 900;
            color: #fff;
            letter-spacing: 4px;
            line-height: 1;
        }

        .exit-message-title span {
            color: var(--orange);
            position: relative;
        }

        .exit-message-title span::after {
            content: '';
            position: absolute;
            bottom: 5px; left: 0;
            width: 100%; height: 8px;
            background: var(--orange);
            opacity: 0.3;
            border-radius: 2px;
        }

        .exit-message-sub {
            font-size: 14px;
            color: rgba(255,255,255,0.4);
            margin-top: 16px;
            letter-spacing: 2px;
        }

        /* Loading Bar */
        .exit-loading-bar {
            position: absolute;
            bottom: 8%; left: 50%;
            transform: translateX(-50%);
            width: 200px;
            height: 3px;
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
            overflow: hidden;
            opacity: 0;
            z-index: 70;
        }
        .exit-overlay.active .exit-loading-bar {
            animation: loadingBarIn 0.5s ease-out 2.5s forwards;
        }
        @keyframes loadingBarIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .exit-loading-fill {
            height: 100%;
            width: 0%;
            background: linear-gradient(90deg, var(--orange), #FF6B35);
            border-radius: 3px;
            box-shadow: 0 0 10px var(--orange-glow);
        }
        .exit-overlay.active .exit-loading-fill {
            animation: loadingFill 2s ease-out 2.5s forwards;
        }
        @keyframes loadingFill {
            0% { width: 0%; }
            100% { width: 100%; }
        }

        /* Vignette */
        .exit-vignette {
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at center, transparent 20%, rgba(0,0,0,0.7) 100%);
            opacity: 0;
            pointer-events: none;
            z-index: 90;
        }
        .exit-overlay.active .exit-vignette {
            animation: vignetteIn 1s ease-out 0.3s forwards;
        }
        @keyframes vignetteIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* Page Content Fade */
        body.exit-initiated {
            animation: contentFadeOut 0.6s ease-out forwards;
        }
        @keyframes contentFadeOut {
            to { filter: blur(10px) brightness(0.2) saturate(0); transform: scale(0.92); opacity: 0.3; }
        }

        /* Crowd Particles */
        .crowd-particles {
            position: absolute;
            inset: 0;
            pointer-events: none;
            opacity: 0;
            z-index: 15;
        }
        .exit-overlay.active .crowd-particles {
            animation: crowdIn 2s ease-out 1.5s forwards;
        }
        @keyframes crowdIn {
            from { opacity: 0; }
            to { opacity: 0.3; }
        }

        .crowd-particle {
            position: absolute;
            width: 3px; height: 3px;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            animation: crowdFloat 3s ease-in-out infinite;
        }
        .crowd-particle:nth-child(1) { left: 10%; top: 80%; animation-delay: 0s; }
        .crowd-particle:nth-child(2) { left: 20%; top: 60%; animation-delay: 0.5s; }
        .crowd-particle:nth-child(3) { left: 80%; top: 70%; animation-delay: 1s; }
        .crowd-particle:nth-child(4) { left: 90%; top: 50%; animation-delay: 1.5s; }
        .crowd-particle:nth-child(5) { left: 30%; top: 40%; animation-delay: 0.8s; }
        .crowd-particle:nth-child(6) { left: 70%; top: 30%; animation-delay: 1.2s; }
        @keyframes crowdFloat {
            0%, 100% { transform: translateY(0) scale(1); opacity: 0.3; }
            50% { transform: translateY(-20px) scale(1.5); opacity: 0.6; }
        }

        /* ═══════════════════════════════════════════════════════════════
           TOMBOL KELUAR — Floating Action Button
           ═══════════════════════════════════════════════════════════════ */
        .exit-fab {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--orange) 0%, var(--orange-dark) 100%);
            border: none;
            border-radius: 50%;
            color: #fff;
            font-size: 22px;
            cursor: pointer;
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(255, 69, 0, 0.4), 0 0 0 4px rgba(255, 69, 0, 0.1);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            overflow: hidden;
        }
        .exit-fab::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.3) 0%, transparent 60%);
            opacity: 0.6;
        }
        .exit-fab:hover {
            transform: translateY(-5px) scale(1.15);
            box-shadow: 0 8px 35px rgba(255, 69, 0, 0.5), 0 0 0 6px rgba(255, 69, 0, 0.15);
        }
        .exit-fab:hover i {
            transform: rotate(180deg) scale(1.1);
        }
        .exit-fab i {
            transition: transform 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            z-index: 2;
        }

        .exit-fab-tooltip {
            position: absolute;
            right: 75px;
            top: 50%;
            transform: translateY(-50%) translateX(15px);
            background: var(--dark);
            color: #fff;
            padding: 10px 18px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            white-space: nowrap;
            opacity: 0;
            pointer-events: none;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        }
        .exit-fab:hover .exit-fab-tooltip {
            opacity: 1;
            transform: translateY(-50%) translateX(0);
        }
        .exit-fab-tooltip::after {
            content: '';
            position: absolute;
            right: -6px;
            top: 50%;
            transform: translateY(-50%);
            border: 8px solid transparent;
            border-left-color: var(--dark);
        }

        /* ═══════════════════════════════════════════════════════════════
           RESPONSIVE
           ═══════════════════════════════════════════════════════════════ */
        @media (max-width: 992px) {
            .navbar { padding: 20px 5%; }
            .hero-section {
                height: auto;
                grid-template-columns: 1fr;
            }
            .hero-visual { height: 400px; }
            .hero-visual img { position: relative; height: 100%; }
            .hero-content p { margin: 0 auto 40px auto; }
            .hero-cta { justify-content: center; }
            .fade-overlay { width: 100%; background: linear-gradient(to bottom, #ffffff 0%, rgba(255, 255, 255, 0) 30%); }
            .hero-stats-card {
                left: 50%;
                transform: translateX(-50%);
                bottom: 20px;
                width: 90%;
                justify-content: space-around;
            }
            .process-grid { grid-template-columns: repeat(2, 1fr); }
            .membership-section { grid-template-columns: 1fr; }
            .pricing-container { grid-template-columns: 1fr; max-width: 500px; margin: 0 auto; }
            .promo-testimonial-section { grid-template-columns: 1fr; }
            .store-banner { flex-direction: column; padding: 40px 30px 40px 30px; align-items: flex-start; }
            .store-content { max-width: 100%; margin-bottom: 40px; }
            .store-visual { position: relative; width: 100%; height: 250px; }
            .store-features-shelf { display: none; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .nav-menu { position: relative; left: auto; transform: none; display: none; }
        }

        html {
    scrollbar-width: none; /* Firefox */
    -ms-overflow-style: none; /* IE and Edge */
}

html::-webkit-scrollbar {
    display: none; /* Chrome, Safari, Opera */
}

        @media (max-width: 768px) {
            .exit-message-title { font-size: 36px; }
            .exit-hoop-container { transform: translateX(-50%) scale(0.7); }
            .exit-fab { bottom: 20px; right: 20px; width: 52px; height: 52px; font-size: 18px; }
            .exit-fab-tooltip { display: none; }
            .exit-scoreboard { transform: translateX(-50%) scale(0.8); }
        }

        @media (max-width: 576px) {
            .process-grid { grid-template-columns: 1fr; }
            .process-grid::before { display: none; }
            .footer-grid { grid-template-columns: 1fr; }
            .hero-content h1 { font-size: 36px; }
        }
    </style>
</head>

<body>

    <!-- ═══════════════════════════════════════════════════════════════
         NAVBAR
         ═══════════════════════════════════════════════════════════════ -->
    <nav class="navbar">
        <a href="#" class="logo"><img src="logo2.png" alt="HoopBall"></a>
        <div class="nav-menu">
            <a href="#beranda">Beranda</a>
            <a href="#lapangan">Lapangan</a>
            <a href="#jadwal">Cara Kerja</a>
            <a href="#member">Langganan</a>
            <a href="#alat-basket">Alat Basket</a>
        </div>
        <div class="nav-btns">
            <!-- LOGIN: Langsung ke login.php (TANPA animasi exit) -->
            <a href="login.php" class="btn-login">Login</a>
            <!-- DAFTAR: Langsung ke register.php (TANPA animasi exit) -->
            <a href="register.php" class="btn-join">Daftar Sekarang</a>
        </div>
    </nav>

    <!-- ═══════════════════════════════════════════════════════════════
         HERO SECTION
         ═══════════════════════════════════════════════════════════════ -->
    <section class="hero-section" id="beranda">
        <div class="hero-content">
            <h1>Sewa Lapangan<br>Basket Jadi<br><span>Lebih Mudah</span></h1>
            <p>Pemesanan lapangan basket favoritmu secara online, pilih jadwal sesuai keinginan, dan nikmati fasilitas
                terbaik untuk pengalaman bermain yang seru!</p>
            <div class="hero-cta">
                <!-- Pemesanan: Langsung ke register.php -->
                <a href="register.php" class="btn-hero-primary"><i class="fa-solid fa-calendar-days"></i> Pemesanan
                    Lapangan</a>
                <!-- Lihat Jadwal: Smooth scroll ke section jadwal -->
                <a href="#jadwal" class="btn-hero-secondary"><i class="fa-regular fa-clock"></i> Lihat Jadwal</a>
            </div>
        </div>
        <div class="hero-visual">
             <!-- Mengganti img dengan video -->
    <video autoplay loop muted playsinline>
        <source src="video.mp4" type="video/mp4">
        Browser Anda tidak mendukung pemutaran video.
    </video>
            <div class="fade-overlay"></div>
            <div class="bottom-fade-overlay"></div>

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

    <!-- ═══════════════════════════════════════════════════════════════
         FEATURES BAR
         ═══════════════════════════════════════════════════════════════ -->
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

    <!-- ═══════════════════════════════════════════════════════════════
         COURT SELECTION
         ═══════════════════════════════════════════════════════════════ -->
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

    <!-- ═══════════════════════════════════════════════════════════════
         BOOKING PROCESS
         ═══════════════════════════════════════════════════════════════ -->
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

    <!-- ═══════════════════════════════════════════════════════════════
         MEMBERSHIP SECTION
         ═══════════════════════════════════════════════════════════════ -->
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

    <!-- ═══════════════════════════════════════════════════════════════
         PROMO & LOCATION
         ═══════════════════════════════════════════════════════════════ -->
    <section class="promo-testimonial-section">
        <div class="promo-card">
            <div class="promo-badge">Promo Weekend</div>
            <div class="promo-content-block">
                <h3>Diskon 20%</h3>
                <p>Untuk semua lapangan</p>
                <div class="btn-promo-yellow">Setiap Sabtu & Minggu</div>
                <div class="promo-terms">*Syarat & ketentuan berlaku</div>
            </div>
            <img src="promo1.png" class="promo-player-img" alt="Basketball Player Promo">
        </div>

        <div class="location-map-card">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3965.3545986499857!2d107.14830219999999!3d-6.3481107!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e699b896d7fc649%3A0xe0a940b1f200d008!2sPoliteknik%20Astra!5e0!3m2!1sid!2sid!4v1780735557436!5m2!1sid!2sid"
                style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade">
            </iframe>

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

    <!-- ═══════════════════════════════════════════════════════════════
         STORE CTA
         ═══════════════════════════════════════════════════════════════ -->
    <section class="store-cta-section" id="alat-basket">
        <div class="store-banner">
            <div class="store-content">
                <h2>Lengkapi Permainanmu<br>dengan <span>Alat Basket Berkualitas</span></h2>
                <p>Temukan bola basket, jersey, sepatu, handuk, dan perlengkapan terbaik untuk latihan maupun
                    pertandingan.</p>
                <a href="register.php" class="btn-store-cta">
                    <i class="fa-solid fa-bag-shopping"></i> Lihat Alat Basket
                </a>
            </div>
            <div class="store-visual">
                <img src="alat basket.png" class="store-gear-img" alt="Basketball Gear Layout">
            </div>
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

    <!-- ═══════════════════════════════════════════════════════════════
         FOOTER
         ═══════════════════════════════════════════════════════════════ -->
    <footer id="tentang-kami">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="#" class="logo"><img src="logo2.png" alt="HoopBall"></a>
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

    <!-- ═══════════════════════════════════════════════════════════════
         EXIT ANIMATION OVERLAY — Cinematic Basketball Experience
         ═══════════════════════════════════════════════════════════════ -->
    <div class="exit-overlay" id="exitOverlay">

        <!-- Stage Background -->
        <div class="exit-stage">
            <div class="court-lines"></div>
        </div>

        <!-- Spotlight -->
        <div class="exit-spotlight"></div>

        <!-- Crowd Particles -->
        <div class="crowd-particles">
            <div class="crowd-particle"></div>
            <div class="crowd-particle"></div>
            <div class="crowd-particle"></div>
            <div class="crowd-particle"></div>
            <div class="crowd-particle"></div>
            <div class="crowd-particle"></div>
        </div>

        <!-- Scoreboard -->
        <div class="exit-scoreboard">
            <div class="score-team">
                <div class="score-team-name">HOME</div>
                <div class="score-team-logo"><i class="fa-solid fa-basketball"></i></div>
            </div>
            <div class="score-divider"></div>
            <div class="score-team">
                <div class="score-points" id="scorePoints">98<span> - 96</span></div>
                <div class="score-timer">Q4 00:03</div>
            </div>
            <div class="score-divider"></div>
            <div class="score-team">
                <div class="score-team-name">AWAY</div>
                <div class="score-team-logo"><i class="fa-solid fa-shield-halved"></i></div>
            </div>
        </div>

        <!-- Hoop & Backboard -->
        <div class="exit-hoop-container">
            <div class="backboard"></div>
            <div class="rim"></div>
            <div class="net"></div>
        </div>

        <!-- Swish Effect Particles -->
        <div class="swish-effect">
            <div class="swish-particles">
                <div class="swish-particle"></div>
                <div class="swish-particle"></div>
                <div class="swish-particle"></div>
                <div class="swish-particle"></div>
                <div class="swish-particle"></div>
                <div class="swish-particle"></div>
            </div>
        </div>

        <!-- Basketball -->
        <div class="exit-ball" id="exitBall">
            <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <radialGradient id="ballGrad" cx="30%" cy="30%" r="70%">
                        <stop offset="0%" style="stop-color:#FF8C42"/>
                        <stop offset="40%" style="stop-color:#FF4500"/>
                        <stop offset="100%" style="stop-color:#CC3700"/>
                    </radialGradient>
                    <filter id="ballGlow">
                        <feGaussianBlur stdDeviation="3" result="blur"/>
                        <feMerge>
                            <feMergeNode in="blur"/>
                            <feMergeNode in="SourceGraphic"/>
                        </feMerge>
                    </filter>
                </defs>
                <circle cx="50" cy="50" r="45" fill="url(#ballGrad)" filter="url(#ballGlow)"/>
                <g stroke="rgba(0,0,0,0.3)" stroke-width="2.5" fill="none">
                    <path d="M 8 50 Q 50 58 92 50" stroke-width="3.5"/>
                    <path d="M 50 8 Q 35 50 50 92"/>
                    <path d="M 50 8 Q 65 50 50 92"/>
                    <path d="M 18 18 Q 50 35 82 18"/>
                    <path d="M 18 82 Q 50 65 82 82"/>
                </g>
                <ellipse cx="35" cy="35" rx="18" ry="12" fill="rgba(255,255,255,0.2)" transform="rotate(-30 35 35)"/>
            </svg>
        </div>

        <!-- Ball Shadow on Floor -->
        <div class="ball-shadow-floor"></div>

        <!-- Flash Bulb -->
        <div class="flash-bulb"></div>

        <!-- Confetti -->
        <div class="confetti-container">
            <div class="confetti-piece"></div>
            <div class="confetti-piece"></div>
            <div class="confetti-piece"></div>
            <div class="confetti-piece"></div>
            <div class="confetti-piece"></div>
            <div class="confetti-piece"></div>
            <div class="confetti-piece"></div>
            <div class="confetti-piece"></div>
            <div class="confetti-piece"></div>
            <div class="confetti-piece"></div>
            <div class="confetti-piece"></div>
            <div class="confetti-piece"></div>
        </div>

        <!-- Message -->
        <div class="exit-message">
            <div class="exit-message-tagline">Sampai Jumpa</div>
            <div class="exit-message-title">HOOP<span>BALL</span></div>
            <div class="exit-message-sub">Terima kasih telah bermain</div>
        </div>

        <!-- Loading Bar -->
        <div class="exit-loading-bar">
            <div class="exit-loading-fill"></div>
        </div>

        <!-- Vignette -->
        <div class="exit-vignette"></div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════════
         TOMBOL KELUAR — Floating Action Button
         ═══════════════════════════════════════════════════════════════ -->
    <button class="exit-fab" onclick="window.location.href='exit_intro.php'" title="Keluar">
        <i class="fa-solid fa-right-from-bracket"></i>
        <span class="exit-fab-tooltip">Keluar dari HoopBall</span>
    </button>

    <!-- ═══════════════════════════════════════════════════════════════
         SCRIPTS
         ═══════════════════════════════════════════════════════════════ -->
    <script>
        // SCROLLSPY
        document.addEventListener('DOMContentLoaded', () => {
            const sections = document.querySelectorAll('section[id], footer[id]');
            const navLinks = document.querySelectorAll('.nav-menu a');

            window.addEventListener('scroll', () => {
                let currentSectionId = '';
                sections.forEach(section => {
                    const sectionTop = section.offsetTop;
                    if (window.pageYOffset >= (sectionTop - 150)) {
                        currentSectionId = section.getAttribute('id');
                    }
                });

                navLinks.forEach(link => {
                    link.classList.remove('active');
                    if (link.getAttribute('href') === `#${currentSectionId}`) {
                        link.classList.add('active');
                    }
                });
            });

            navLinks.forEach(link => {
                link.addEventListener('click', () => {
                    navLinks.forEach(item => item.classList.remove('active'));
                    link.classList.add('active');
                });
            });
        });

        /**
         * EXIT ANIMATION CONTROLLER
         * Memicu animasi cinematic basketball sebelum navigasi ke intro.php
         */
        function triggerExitAnimation() {
            const body = document.body;
            const overlay = document.getElementById('exitOverlay');
            const fab = document.getElementById('exitFab');

            // Sembunyikan FAB
            fab.style.transform = 'scale(0) rotate(180deg)';
            fab.style.opacity = '0';

            // Fade konten halaman
            body.classList.add('exit-initiated');

            // Aktifkan overlay
            overlay.classList.add('active');

            // Update score
            setTimeout(() => {
                const scoreEl = document.getElementById('scorePoints');
                if (scoreEl) {
                    scoreEl.innerHTML = '101<span> - 96</span>';
                    scoreEl.style.color = '#10B981';
                }
            }, 1600);

            // Navigasi ke intro.php setelah animasi selesai
            setTimeout(() => {
               // Redirect ke exit animation dulu, baru ke tujuan akhir
                window.location.href = 'exit_intro.php?to=intro.php';
            }, 4500);
        }
    </script>
</body>
</html>