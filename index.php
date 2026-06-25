<?php
session_start();
if (isset($_GET['load']) && $_GET['load'] === 'done') {
    $_SESSION['intro_done'] = true;
}
if (!isset($_SESSION['intro_done'])) {
    header("Location: intro.php");
    exit();
}

$total_lapangan = 15;
$total_promo = 3;

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
    <link href= "https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ─── SCROLLBAR HIDDEN ─────────────────────── */
        html { 
            scroll-behavior: smooth; 
            scrollbar-width: none; 
            -ms-overflow-style: none; 
            background-color: #FFFFFF !important;
        }
            
        html::-webkit-scrollbar { display: none; }

        /* ─── SCROLL-MARGIN ───────────────────────── */
        section[id], footer[id] { scroll-margin-top: 90px; }

        /* ─── CSS VARIABLES ───────────────────────── */
        :root {
            --orange: #FF4500;
            --orange-light: #FF6B35;
            --orange-dark: #CC3700;
            --orange-glow: rgba(255,69,0,0.55);
            --dark: #0A0E17;
            --text-dark: #1E293B;
            --text-muted: #64748B;
            --bg-light: #F8FAFC;
            --border-color: #E2E8F0;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Barlow', sans-serif; }

        body {
            background-color: #FFFFFF !important;
            color: var(--text-dark);
            overflow-x: hidden;
            line-height: 1.5;
        }

        a { text-decoration: none; transition: all 0.3s ease; }

        /* TAMBAHKAN/GANTI KEYFRAMES INI */
@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-30px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

        /* ═══════════════════════════════════════════
           SCROLL REVEAL
           ═══════════════════════════════════════════ */
        .reveal {
            opacity: 0;
            transform: translateY(40px);
            transition: all 0.8s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .reveal-left {
            opacity: 0;
            transform: translateX(-36px);
            transition: opacity 0.65s cubic-bezier(0.22,1,0.36,1),
                        transform 0.65s cubic-bezier(0.22,1,0.36,1);
        }
        .reveal-left.visible {
            opacity: 1;
            transform: translateX(0);
        }
        .reveal-right {
            opacity: 0;
            transform: translateX(36px);
            transition: opacity 0.65s cubic-bezier(0.22,1,0.36,1),
                        transform 0.65s cubic-bezier(0.22,1,0.36,1);
        }
        .reveal-right.visible {
            opacity: 1;
            transform: translateX(0);
        }

        /* Stagger delays */
        .delay-1 { transition-delay: 0.08s !important; }
        .delay-2 { transition-delay: 0.16s !important; }
        .delay-3 { transition-delay: 0.24s !important; }
        .delay-4 { transition-delay: 0.32s !important; }

        /* ═══════════════════════════════════════════
           NAVBAR
           ═══════════════════════════════════════════ */
        .navbar {
            position: sticky;
            top: 0;
            background: #FFFFFF;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 76px;
            padding: 0 80px;
            border-bottom: 1px solid #E5E5EA;
            box-shadow: none;
            z-index: 1000;
            font-family: 'Plus Jakarta Sans', sans-serif;
            animation: fadeInDown 0.6s ease-out forwards !important;
        }
        @keyframes navSlide {
            from { opacity: 0; transform: translateY(-24px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            gap: 10px;
            transition: transform 0.3s ease;
        }
        .logo:hover {
            transform: scale(1.05);
        }
        .logo img {
            height: 70px;
            width: auto;
            object-fit: contain;
            filter: none;
            transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .logo:hover img {
            transform: rotate(5deg) scale(1.1);
        }

        .nav-menu {
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 8px;
            z-index: 5;
        }
        .nav-menu a {
            color: #636366;
            text-decoration: none;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }
        .nav-menu a::after {
            content: '';
            position: absolute;
            bottom: 0; 
            left: 50%;
            width: 0; 
            height: 2px;
            background: var(--orange);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
            transform: translateX(-50%);
        }
        .nav-menu a:hover { 
            color: #1C1C1E; 
            transform: translateY(-2px);
        }
        .nav-menu a:hover::after { 
            width: 60%; 
        }
        .nav-menu a.active { 
            color: var(--orange) !important; 
            font-weight: 600;
        }
        .nav-menu a.active::after { 
            width: 60% !important; 
        }

        .nav-btns { 
            display: flex; 
            align-items: center; 
            gap: 16px; 
        }

        .btn-login {
            color: #1C1C1E;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 600;
            font-size: 14px;
            padding: 10px 24px;
            border-radius: 50px;
            border: 1px solid #E5E5EA;
            background: #F2F2F7;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .btn-login:hover {
            background: #E5E5EA;
            border-color: var(--orange);
            color: #1C1C1E;
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(255, 82, 0, 0.15);
        }
        .btn-join {
            background: var(--orange);
            color: #fff;
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-weight: 750;
            font-size: 14px;
            padding: 10px 24px;
            border-radius: 50px;
            box-shadow: 0 4px 14px rgba(255, 82, 0, 0.15);
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
        }
        .btn-join:hover {
            background: var(--orange-dark);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 69, 0, 0.35);
        }

        /* ═══════════════════════════════════════════
           HERO
           ═══════════════════════════════════════════ */
        .hero-section {
            display: grid;
            grid-template-columns: 35% 65%;
            height: 620px;
            background: #fff;
            padding-left: 5%;
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
            /* hero text enters after page-enter fades */
            animation: heroText 0.9s cubic-bezier(0.22,1,0.36,1) 0.55s both;
        }
        @keyframes heroText {
            from { opacity: 0; transform: translateX(-30px); }
            to   { opacity: 1; transform: translateX(0); }
        }

        .hero-content h1 {
            font-size: 52px;
            font-weight: 800;
            line-height: 1.15;
            color: #111;
            margin-bottom: 24px;
            letter-spacing: -1px;
            font-family: 'Barlow Condensed', sans-serif;
        }
        .hero-content h1 span { color: var(--orange); }

        .hero-content p {
            font-size: 15px;
            color: var(--text-muted);
            line-height: 1.65;
            margin-bottom: 40px;
            max-width: 485px;
        }

        .hero-cta { display: flex; gap: 16px; }

        .btn-hero-primary {
            background: var(--orange);
            color: #fff;
            padding: 14px 28px;
            font-weight: 700;
            font-size: 14px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: none;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
        }
        .btn-hero-primary i { transition: transform 0.3s ease; }
        .btn-hero-primary:hover { background: var(--orange-dark); transform: translateY(-2px); box-shadow: 0 8px 25px rgba(255,69,0,0.35); }
        .btn-hero-primary:hover i { transform: rotate(-10deg) scale(1.1); }

        .btn-hero-secondary {
            background: #F1F5F9;
            color: #334155;
            padding: 14px 28px;
            font-weight: 700;
            font-size: 14px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
            border: 1.5px solid #CBD5E1;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
        }
        .btn-hero-secondary i { color: #475569; transition: transform 0.4s ease, color 0.3s ease; }
        .btn-hero-secondary:hover { background: #E2E8F0; border-color: #94A3B8; color: #0F172A; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.05); }
        .btn-hero-secondary:hover i { transform: rotate(30deg); color: var(--orange); }

        .hero-visual {
            position: relative;
            height: 100%;
            overflow: hidden;
            /* video slides in from right */
            animation: heroVid 1s cubic-bezier(0.22,1,0.36,1) 0.4s both;
        }
        @keyframes heroVid {
            from { opacity: 0; transform: translateX(40px) scale(1.04); }
            to   { opacity: 1; transform: translateX(0) scale(1); }
        }

        .hero-visual video {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            object-fit: cover;
            pointer-events: none;
            transition: transform 8s cubic-bezier(0.16,1,0.3,1);
        }
        .hero-section:hover .hero-visual video { transform: scale(1.04); }

        .fade-overlay {
            position: absolute;
            top: 0; left: 0;
            width: 35%; height: 100%;
            background: linear-gradient(to right, #fff 0%, transparent 100%);
            z-index: 2;
        }
        .bottom-fade-overlay {
            position: absolute;
            bottom: 0; left: 0;
            width: 100%; height: 50px;
            background: linear-gradient(to top, #fff 0%, transparent 100%);
            z-index: 3;
            pointer-events: none;
        }

        /* Stats card */
        .hero-stats-card {
            position: absolute;
            bottom: 50px; left: 34%;
            background: #fff;
            border-radius: 14px;
            padding: 12px 22px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.06);
            border: 1px solid rgba(0,0,0,0.03);
            z-index: 5;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.16,1,0.3,1);
            animation: statsCard 0.8s cubic-bezier(0.22,1,0.36,1) 0.85s both;
        }
        @keyframes statsCard {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .hero-stats-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
            border-color: rgba(255,69,0,0.1);
        }
        .hero-stats-card:hover .stat-box:nth-child(1) .stat-icon i { transform: translateY(-4px) scale(1.15); }
        .hero-stats-card:hover .stat-box:nth-child(3) .stat-icon i { transform: scale(1.2) rotate(15deg); }
        .hero-stats-card:hover .stat-box:nth-child(5) .stat-icon i { transform: rotate(180deg) scale(1.15); }

        .stat-box { text-align: center; min-width: 65px; }
        .stat-icon { font-size: 18px; color: var(--orange); margin-bottom: 4px; }
        .stat-icon i { display: inline-block; transition: transform 0.4s cubic-bezier(0.175,0.885,0.32,1.275); }
        .stat-num { font-size: 20px; font-weight: 800; color: #111; line-height: 1.1; }
        .stat-label { font-size: 10px; color: var(--text-muted); font-weight: 600; margin-top: 1px; }
        .stat-divider { width: 1px; height: 28px; background: var(--border-color); }

        /* ═══════════════════════════════════════════
           FEATURES SECTION
           ═══════════════════════════════════════════ */
        .features-section {
            padding: 80px 8%;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 24px;
        }

        .feature-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 30px 24px;
            transition: all 0.35s ease;
        }
        .feature-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.05);
            border-color: rgba(255,69,0,0.2);
        }
        .feature-icon {
            width: 48px; height: 48px;
            background: #FFF0E9;
            border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }
        .feature-card:hover .feature-icon { background: var(--orange); }
        .feature-icon i { font-size: 20px; color: var(--orange); transition: color 0.3s ease; }
        .feature-card:hover .feature-icon i { color: #fff; }
        .feature-card h4 { font-size: 16px; font-weight: 700; color: var(--dark); margin-bottom: 10px; }
        .feature-card p  { font-size: 13px; color: var(--text-muted); line-height: 1.6; }

        /* ═══════════════════════════════════════════
           SECTION HEADER
           ═══════════════════════════════════════════ */
        .section-header { text-align: center; margin-bottom: 50px; }
        .section-header h2 {
            font-size: 32px;
            font-weight: 800;
            color: var(--dark);
            font-family: 'Barlow Condensed', sans-serif;
            position: relative;
            display: inline-block;
        }
        /* orange accent underline on h2 */
        .section-header h2::after {
            content: '';
            position: absolute;
            bottom: -8px; left: 50%;
            transform: translateX(-50%);
            width: 48px; height: 3px;
            background: var(--orange);
            border-radius: 2px;
        }

        /* ═══════════════════════════════════════════
           COURT SECTION
           ═══════════════════════════════════════════ */
        .court-section { padding: 80px 8%; background: #fff; }
        .court-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 30px;
        }
        .court-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.35s ease;
        }
        .court-card:hover { transform: translateY(-6px); box-shadow: 0 14px 35px rgba(0,0,0,0.07); }
        .court-img-container { width: 100%; height: 220px; overflow: hidden; }
        .court-img-container img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s ease; }
        .court-card:hover .court-img-container img { transform: scale(1.05); }
        .court-info { padding: 24px; }
        .court-info h3 { font-size: 18px; font-weight: 700; color: var(--dark); margin-bottom: 8px; }
        .court-info p { font-size: 13px; color: var(--text-muted); margin-bottom: 20px; height: 40px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .court-footer { display: flex; justify-content: space-between; align-items: center; padding-top: 16px; border-top: 1px solid var(--border-color); }
        .court-price { font-size: 14px; color: var(--text-muted); }
        .court-price span { font-size: 18px; font-weight: 800; color: var(--orange); }
        .btn-detail {
            border: 1px solid var(--orange);
            color: var(--orange);
            font-weight: 700;
            font-size: 13px;
            padding: 8px 16px;
            border-radius: 6px;
            background: transparent;
            transition: all 0.3s ease;
        }
        .btn-detail:hover { background: var(--orange); color: #fff; }

        /* ═══════════════════════════════════════════
           PROCESS SECTION
           ═══════════════════════════════════════════ */
        .process-section { padding: 80px 8%; background: var(--bg-light); overflow: hidden; }
        .process-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 24px;
            position: relative;
        }
        .process-grid::before {
            content: '';
            position: absolute;
            top: 50px; left: 12%; right: 12%;
            height: 2px;
            background-image: linear-gradient(to right, #CBD5E1 50%, rgba(255,255,255,0) 0%);
            background-size: 12px 2px;
            background-repeat: repeat-x;
            z-index: 1;
        }
        .process-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 36px 24px;
            text-align: center;
            position: relative;
            z-index: 2;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.16,1,0.3,1);
        }
        .process-card:hover { transform: translateY(-10px) scale(1.02); border-color: var(--orange); box-shadow: 0 20px 35px rgba(255,69,0,0.08); }
        .process-step {
            width: 36px; height: 36px;
            background: var(--orange);
            color: #fff;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 14px;
            margin: 0 auto 24px;
            position: relative; z-index: 3;
            transition: all 0.3s ease;
        }
        .process-card:hover .process-step { transform: scale(1.15); box-shadow: 0 0 12px rgba(255,69,0,0.4); }
        .process-card i { font-size: 36px; color: #94A3B8; margin-bottom: 20px; display: inline-block; transition: all 0.4s cubic-bezier(0.16,1,0.3,1); }
        .process-card:hover i { color: var(--orange); transform: scale(1.12) rotate(5deg); }
        .process-card h4 { font-size: 16px; font-weight: 700; color: var(--dark); margin-bottom: 10px; transition: color 0.3s ease; }
        .process-card:hover h4 { color: var(--orange); }
        .process-card p { font-size: 13px; color: var(--text-muted); line-height: 1.6; }

        /* ═══════════════════════════════════════════
           MEMBERSHIP
           ═══════════════════════════════════════════ */
        .membership-section {
            padding: 100px 8%;
            display: grid;
            grid-template-columns: 1fr 1.2fr;
            gap: 60px;
            align-items: center;
        }
        .member-intro h2 { font-size: 36px; font-weight: 800; color: var(--dark); margin-bottom: 16px; font-family: 'Barlow Condensed', sans-serif; }
        .member-intro p { color: var(--text-muted); font-size: 15px; margin-bottom: 40px; line-height: 1.6; }
        .member-benefit-list { display: flex; flex-direction: column; gap: 16px; }
        .benefit-item { display: flex; gap: 16px; align-items: flex-start; padding: 12px; border-radius: 12px; cursor: pointer; transition: all 0.3s cubic-bezier(0.16,1,0.3,1); }
        .benefit-item:hover { transform: translateX(8px); background: var(--bg-light); }
        .benefit-icon { width: 48px; height: 48px; background: #FFF0E9; border-radius: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; transition: all 0.3s ease; }
        .benefit-item:hover .benefit-icon { background: var(--orange); }
        .benefit-icon i { color: var(--orange); font-size: 18px; transition: all 0.3s ease; }
        .benefit-item:hover .benefit-icon i { color: #fff; transform: scale(1.1); }
        .benefit-text h4 { font-size: 15px; font-weight: 700; color: var(--dark); margin-bottom: 4px; transition: color 0.3s ease; }
        .benefit-item:hover .benefit-text h4 { color: var(--orange); }
        .benefit-text p { font-size: 13px; color: var(--text-muted); }

        .pricing-container { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .pricing-card {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 40px 30px;
            position: relative;
            display: flex;
            flex-direction: column;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.16,1,0.3,1);
        }
        .pricing-card:hover { transform: translateY(-8px); border-color: var(--orange); box-shadow: 0 20px 40px rgba(0,0,0,0.05); }
        .pricing-card.premium:hover { transform: translateY(-12px); box-shadow: 0 24px 48px rgba(255,69,0,0.12); }
        .popular-badge {
            position: absolute;
            top: -15px; left: 50%; transform: translateX(-50%);
            background: var(--orange);
            color: #fff;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 11px; font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            z-index: 3;
            transition: all 0.3s ease;
        }
        .pricing-card.premium:hover .popular-badge { transform: translateX(-50%) scale(1.05); background: var(--orange-dark); }
        .price-name { font-size: 18px; font-weight: 700; color: var(--dark); margin-bottom: 10px; }
        .price-amount { font-size: 28px; font-weight: 800; color: var(--orange); margin-bottom: 30px; }
        .price-amount span { font-size: 14px; color: var(--text-muted); font-weight: 500; }
        .price-features { list-style: none; display: flex; flex-direction: column; gap: 16px; margin-bottom: 40px; }
        .price-features li { font-size: 13px; color: var(--text-dark); display: flex; align-items: center; gap: 10px; }
        .price-features li i { color: #22C55E; font-size: 14px; }
        .btn-price { width: 100%; padding: 14px; border-radius: 8px; font-weight: 700; font-size: 14px; text-align: center; margin-top: auto; transition: all 0.3s ease; display: inline-block; }
        .btn-price.outline { border: 1px solid var(--orange); color: var(--orange); background: transparent; }
        .btn-price.outline:hover { background: var(--orange); color: #fff; }
        .btn-price.filled { background: var(--orange); color: #fff; border: none; }
        .btn-price.filled:hover { background: var(--orange-dark); transform: scale(1.02); }

        /* ═══════════════════════════════════════════
           PROMO & LOCATION
           ═══════════════════════════════════════════ */
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
            color: #fff;
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 380px;
            box-shadow: 0 10px 30px rgba(255,84,0,0.12);
            transition: all 0.4s cubic-bezier(0.16,1,0.3,1);
            cursor: pointer;
        }
        .promo-card:hover { transform: translateY(-8px); box-shadow: 0 20px 45px rgba(255,69,0,0.28); }
        .promo-badge { font-size: 13px; font-weight: 750; text-transform: uppercase; letter-spacing: 1.5px; margin-bottom: auto; }
        .promo-content-block { margin-top: auto; position: relative; z-index: 2; }
        .promo-content-block h3 { font-size: 42px; font-weight: 900; line-height: 1.1; letter-spacing: -0.5px; margin-bottom: 4px; font-family: 'Barlow Condensed', sans-serif; }
        .promo-content-block p { font-size: 15px; font-weight: 600; opacity: 0.95; margin-bottom: 24px; }
        .btn-promo-yellow {
            background: #FFC107;
            color: #1E293B;
            font-weight: 700;
            font-size: 13px;
            padding: 10px 24px;
            border-radius: 50px;
            display: inline-block;
            width: fit-content;
            box-shadow: 0 4px 12px rgba(255,193,7,0.2);
            transition: all 0.3s ease;
        }
        .promo-card:hover .btn-promo-yellow { background: #FFB300; transform: scale(1.04); }
        .promo-terms { font-size: 10px; color: rgba(255,255,255,0.7); margin-top: 14px; font-weight: 500; }
        .promo-player-img {
            position: absolute;
            bottom: 35px; right: 25px;
            height: 300px;
            object-fit: contain;
            pointer-events: none;
            z-index: 1;
            transition: all 0.4s cubic-bezier(0.16,1,0.3,1);
        }
        .promo-card:hover .promo-player-img { transform: scale(1.04) translate(3px,-3px); }

        .location-map-card {
            position: relative;
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0,0,0,0.03);
            height: 100%;
            min-height: 380px;
            transition: all 0.4s cubic-bezier(0.16,1,0.3,1);
        }
        .location-map-card:hover { transform: translateY(-6px); border-color: var(--orange); box-shadow: 0 20px 40px rgba(0,0,0,0.06); }
        .location-map-card iframe { width: 100%; height: 100%; display: block; filter: grayscale(100%) contrast(1.1) brightness(0.95); transition: filter 0.6s ease; }
        .location-map-card:hover iframe { filter: grayscale(0%); }

        .map-overlay-card {
            position: absolute;
            top: 16px; right: 16px;
            background: rgba(255,255,255,0.9);
            backdrop-filter: blur(8px);
            border-radius: 12px;
            padding: 14px 18px;
            max-width: 210px;
            box-shadow: 0 8px 20px rgba(0,0,0,0.08);
            border: 1px solid rgba(255,255,255,0.8);
            z-index: 10;
            transition: all 0.4s ease;
        }
        .location-map-card:hover .map-overlay-card { transform: translateY(-2px); box-shadow: 0 12px 25px rgba(255,69,0,0.1); border-color: rgba(255,69,0,0.2); }
        .map-badge { display: inline-flex; align-items: center; gap: 4px; background: #FFF0E9; color: var(--orange); font-size: 8px; padding: 3px 8px; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
        .map-overlay-card h4 { font-size: 13px; font-weight: 800; color: var(--dark); margin-bottom: 4px; }
        .map-overlay-card p { font-size: 10px; color: var(--text-muted); line-height: 1.4; margin-bottom: 12px; }
        .map-link { display: inline-flex; align-items: center; gap: 6px; background: var(--orange); color: #fff; font-size: 10px; padding: 6px 12px; border-radius: 6px; transition: all 0.3s ease; }
        .map-link:hover { background: var(--orange-dark); transform: translateY(-1px); }

        /* ═══════════════════════════════════════════
           STORE CTA
           ═══════════════════════════════════════════ */
        .store-cta-section { padding: 60px 8%; background: #fff; }
        .store-banner {
            background: #1E2530;
            border-radius: 24px;
            padding: 60px 80px 110px;
            color: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
            transition: transform 0.4s cubic-bezier(0.16,1,0.3,1);
        }
        .store-banner:hover { transform: scale(1.005); }
        .store-content { max-width: 50%; position: relative; z-index: 2; }
        .store-content h2 { font-size: 34px; font-weight: 800; line-height: 1.25; margin-bottom: 16px; font-family: 'Barlow Condensed', sans-serif; }
        .store-content h2 span { color: var(--orange); }
        .store-content p { font-size: 14px; color: #94A3B8; line-height: 1.6; margin-bottom: 32px; max-width: 480px; }
        .btn-store-cta {
            background: var(--orange);
            color: #fff;
            padding: 14px 28px;
            font-weight: 700;
            font-size: 14px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 14px rgba(255,84,0,0.2);
            transition: all 0.3s cubic-bezier(0.16,1,0.3,1);
        }
        .btn-store-cta:hover { background: var(--orange-dark); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(255,84,0,0.4); }
        .store-visual { position: absolute; right: 0; top: 0; width: 50%; height: calc(100% - 50px); z-index: 1; }
        .store-gear-img { width: 100%; height: 100%; object-fit: cover; pointer-events: none; }
        .store-features-shelf {
            position: absolute;
            bottom: 0; right: 0;
            width: 72%; height: 50px;
            background: #F8FAFC;
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
            top: -28px; left: -28px;
            width: 28px; height: 28px;
            background: transparent;
            border-bottom-right-radius: 28px;
            box-shadow: 14px 14px 0 0 #F8FAFC;
        }
        .shelf-item { display: flex; align-items: center; gap: 8px; color: #1E293B; }
        .shelf-item i { color: #475569; font-size: 15px; }
        .shelf-item span { font-size: 11px; font-weight: 750; letter-spacing: -0.2px; }

        /* ═══════════════════════════════════════════
           FOOTER
           ═══════════════════════════════════════════ */
        footer { background: #0F172A; color: #94A3B8; padding: 80px 8% 40px; }
        .footer-grid { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr; gap: 40px; margin-bottom: 60px; }
        .footer-brand .logo { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
        .footer-brand .logo img { width: auto; height: 90px; object-fit: contain; filter: drop-shadow(0 3px 6px rgba(0,0,0,0.3)); transition: transform 0.3s ease; }
        .footer-brand .logo:hover img { transform: scale(1.05); }
        .footer-brand p { font-size: 13px; line-height: 1.6; margin-bottom: 24px; }
        .social-links { display: flex; gap: 16px; }
        .social-links a { width: 36px; height: 36px; background: #1E293B; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; transition: all 0.3s ease; }
        .social-links a:hover { background: var(--orange); }
        .footer-col h4 { color: #fff; font-size: 15px; font-weight: 700; margin-bottom: 24px; }
        .footer-links { list-style: none; display: flex; flex-direction: column; gap: 12px; }
        .footer-links li a { color: #94A3B8; font-size: 13px; transition: color 0.3s ease; }
        .footer-links li a:hover { color: #fff; }
        .footer-contact-info { display: flex; flex-direction: column; gap: 16px; }
        .contact-item { display: flex; gap: 12px; font-size: 13px; }
        .contact-item i { color: var(--orange); margin-top: 3px; }
        .footer-bottom { padding-top: 40px; border-top: 1px solid #1E293B; text-align: center; font-size: 12px; }

        /* ═══════════════════════════════════════════
           EXIT OVERLAY
           ═══════════════════════════════════════════ */
        .exit-overlay {
            position: fixed; inset: 0;
            z-index: 99999;
            pointer-events: none;
            opacity: 0; visibility: hidden;
            background: #05070A;
        }
        .exit-overlay.active { pointer-events: all; opacity: 1; visibility: visible; }

        .exit-stage {
            position: absolute; inset: 0;
            background: radial-gradient(ellipse at 50% 100%, rgba(255,69,0,0.15) 0%, transparent 60%),
                        linear-gradient(180deg, #05070A 0%, #0f1520 40%, #1a2332 70%, #05070A 100%);
            opacity: 0; transform: scale(1.2);
        }
        .exit-overlay.active .exit-stage { animation: stageIn 0.8s cubic-bezier(0.22,1,0.36,1) 0.2s forwards; }
        @keyframes stageIn { from { opacity:0; transform:scale(1.2) translateY(50px); } to { opacity:1; transform:scale(1) translateY(0); } }

        .court-lines { position:absolute; inset:0; opacity:0.08; }
        .court-lines::before { content:''; position:absolute; bottom:0; left:50%; transform:translateX(-50%); width:600px; height:400px; border:3px solid var(--orange); border-bottom:none; border-radius:300px 300px 0 0; }
        .court-lines::after { content:''; position:absolute; bottom:0; left:50%; transform:translateX(-50%); width:200px; height:150px; border:3px solid var(--orange); border-bottom:none; border-radius:100px 100px 0 0; }

        .exit-spotlight { position:absolute; top:-100px; left:50%; transform:translateX(-50%); width:400px; height:600px; background:radial-gradient(ellipse at 50% 0%, rgba(255,69,0,0.3) 0%, transparent 70%); opacity:0; mix-blend-mode:screen; pointer-events:none; }
        .exit-overlay.active .exit-spotlight { animation:spotlightIn 1s ease-out 0.5s forwards; }
        @keyframes spotlightIn { from{opacity:0;transform:translateX(-50%) translateY(-50px);}to{opacity:1;transform:translateX(-50%) translateY(0);} }

        .exit-scoreboard {
            position:absolute; top:8%; left:50%; transform:translateX(-50%);
            background:linear-gradient(180deg, #1a1a2e 0%, #16213e 100%);
            border:3px solid var(--orange); border-radius:12px; padding:16px 40px;
            display:flex; align-items:center; gap:24px; opacity:0;
            box-shadow:0 0 40px rgba(255,69,0,0.2); z-index:20;
        }
        .exit-overlay.active .exit-scoreboard { animation:scoreboardIn 0.5s cubic-bezier(0.34,1.56,0.64,1) 0.6s forwards; }
        @keyframes scoreboardIn { from{opacity:0;transform:translateX(-50%) translateY(-30px) scale(0.8);}to{opacity:1;transform:translateX(-50%) translateY(0) scale(1);} }

        .score-team { text-align:center; }
        .score-team-name { font-size:10px; color:rgba(255,255,255,0.5); font-weight:700; letter-spacing:2px; text-transform:uppercase; }
        .score-team-logo { font-size:24px; color:var(--orange); margin:4px 0; }
        .score-divider { width:2px; height:40px; background:linear-gradient(180deg, transparent, var(--orange), transparent); }
        .score-points { font-family:'Barlow Condensed', sans-serif; font-size:42px; font-weight:900; color:#fff; line-height:1; }
        .score-points em { font-size:14px; color:var(--orange); font-style:normal; }
        .score-timer { font-size:11px; color:rgba(255,255,255,0.4); font-weight:700; letter-spacing:3px; margin-top:4px; }

        .exit-hoop-container { position:absolute; top:15%; left:50%; transform:translateX(-50%); width:200px; height:200px; opacity:0; }
        .exit-overlay.active .exit-hoop-container { animation:hoopAppear 0.6s cubic-bezier(0.34,1.56,0.64,1) 0.8s forwards; }
        @keyframes hoopAppear { from{opacity:0;transform:translateX(-50%) translateY(-100px) scale(0.5);}to{opacity:1;transform:translateX(-50%) translateY(0) scale(1);} }

        .backboard { position:absolute; top:0; left:50%; transform:translateX(-50%); width:160px; height:110px; background:linear-gradient(135deg,rgba(255,255,255,0.1),rgba(255,255,255,0.05)); border:3px solid rgba(255,255,255,0.2); border-radius:4px; }
        .backboard::after { content:''; position:absolute; top:50%; left:50%; transform:translate(-50%,-50%); width:60px; height:45px; border:2px solid var(--orange); border-radius:2px; opacity:0.6; }
        .rim { position:absolute; top:105px; left:50%; transform:translateX(-50%); width:100px; height:12px; border:4px solid #C0392B; border-radius:50%; background:linear-gradient(90deg,#C0392B,#E74C3C,#C0392B); box-shadow:0 4px 20px rgba(192,57,43,0.4); }
        .net { position:absolute; top:115px; left:50%; transform:translateX(-50%); width:90px; height:80px; overflow:hidden; }
        .net::before { content:''; position:absolute; inset:0; background:linear-gradient(90deg,transparent 48%,rgba(255,255,255,0.2) 49%,rgba(255,255,255,0.2) 51%,transparent 52%),linear-gradient(60deg,transparent 48%,rgba(255,255,255,0.15) 49%,rgba(255,255,255,0.15) 51%,transparent 52%),linear-gradient(-60deg,transparent 48%,rgba(255,255,255,0.15) 49%,rgba(255,255,255,0.15) 51%,transparent 52%); clip-path:polygon(10% 0%,90% 0%,75% 100%,25% 100%); animation:netSway 2s ease-in-out infinite; }
        @keyframes netSway { 0%,100%{transform:skewX(-3deg);}50%{transform:skewX(3deg) scaleY(1.05);} }

        .exit-ball { position:absolute; width:70px; height:70px; left:50%; bottom:20%; transform:translateX(-50%); opacity:0; z-index:50; }
        .exit-ball svg { width:100%; height:100%; filter:drop-shadow(0 15px 30px rgba(0,0,0,0.5)); }
        .exit-overlay.active .exit-ball { animation:ballArc 2.5s cubic-bezier(0.25,0.46,0.45,0.94) 1s forwards; }
        @keyframes ballArc {
            0%  { opacity:1; left:50%; bottom:20%; transform:translateX(-50%) scale(0.5) rotate(0deg); }
            30% { left:50%; bottom:55%; transform:translateX(-50%) scale(1) rotate(360deg); }
            50% { left:50%; bottom:62%; transform:translateX(-50%) scale(1.1) rotate(540deg); }
            55% { left:50%; bottom:60%; transform:translateX(-50%) scale(1) rotate(600deg); }
            60% { left:50%; bottom:58%; transform:translateX(-50%) scale(0.95) rotate(630deg); }
            65% { opacity:1; left:50%; bottom:56%; transform:translateX(-50%) scale(0.9) rotate(650deg); }
            70% { opacity:0.8; left:50%; bottom:30%; transform:translateX(-50%) scale(0.7) rotate(720deg); }
            100%{ opacity:0; left:50%; bottom:-10%; transform:translateX(-50%) scale(0.3) rotate(900deg); }
        }

        .flash-bulb { position:absolute; inset:0; background:#fff; opacity:0; pointer-events:none; z-index:100; }
        .exit-overlay.active .flash-bulb { animation:flashPop 0.3s ease-out 1.5s forwards; }
        @keyframes flashPop { 0%{opacity:0;}10%{opacity:1;}100%{opacity:0;} }

        .confetti-container { position:absolute; inset:0; pointer-events:none; overflow:hidden; z-index:60; }
        .confetti-piece { position:absolute; width:10px; height:10px; opacity:0; top:50%; left:50%; }
        .confetti-piece:nth-child(1){background:var(--orange);--cx:-200px;--cy:-300px;--cr:720deg;animation-delay:1.6s}
        .confetti-piece:nth-child(2){background:#FF6B35;--cx:150px;--cy:-350px;--cr:-540deg;animation-delay:1.65s}
        .confetti-piece:nth-child(3){background:#FFD700;--cx:250px;--cy:-200px;--cr:360deg;animation-delay:1.7s}
        .confetti-piece:nth-child(4){background:var(--orange);--cx:-150px;--cy:-400px;--cr:-720deg;animation-delay:1.75s}
        .confetti-piece:nth-child(5){background:#FF8C42;--cx:100px;--cy:-450px;--cr:450deg;animation-delay:1.8s}
        .confetti-piece:nth-child(6){background:#FFD700;--cx:-250px;--cy:-250px;--cr:-360deg;animation-delay:1.85s}
        .confetti-piece:nth-child(7){background:var(--orange);--cx:300px;--cy:-300px;--cr:630deg;animation-delay:1.9s}
        .confetti-piece:nth-child(8){background:#FF6B35;--cx:-100px;--cy:-350px;--cr:-450deg;animation-delay:1.95s}
        .confetti-piece:nth-child(9){background:#FFD700;--cx:200px;--cy:-400px;--cr:540deg;animation-delay:2s}
        .confetti-piece:nth-child(10){background:var(--orange);--cx:-300px;--cy:-200px;--cr:-630deg;animation-delay:2.05s}
        .exit-overlay.active .confetti-piece { animation:confettiExplode 1.5s ease-out forwards; }
        @keyframes confettiExplode { 0%{opacity:1;transform:translate(-50%,-50%) scale(1) rotate(0deg);}100%{opacity:0;transform:translate(calc(-50% + var(--cx)),calc(-50% + var(--cy))) scale(0) rotate(var(--cr));} }

        .exit-message { position:absolute; bottom:15%; left:50%; transform:translateX(-50%); text-align:center; opacity:0; z-index:70; }
        .exit-overlay.active .exit-message { animation:messageIn 0.8s ease-out 2.2s forwards; }
        @keyframes messageIn { from{opacity:0;transform:translateX(-50%) translateY(30px);}to{opacity:1;transform:translateX(-50%) translateY(0);} }
        .exit-message-tagline { font-size:12px; font-weight:700; letter-spacing:6px; text-transform:uppercase; color:var(--orange); margin-bottom:12px; }
        .exit-message-title { font-family:'Barlow Condensed',sans-serif; font-size:56px; font-weight:900; color:#fff; letter-spacing:4px; line-height:1; }
        .exit-message-title em { color:var(--orange); font-style:normal; }
        .exit-message-sub { font-size:14px; color:rgba(255,255,255,0.4); margin-top:16px; letter-spacing:2px; }

        .exit-loading-bar { position:absolute; bottom:8%; left:50%; transform:translateX(-50%); width:200px; height:3px; background:rgba(255,255,255,0.1); border-radius:3px; overflow:hidden; opacity:0; z-index:70; }
        .exit-overlay.active .exit-loading-bar { animation:lbIn 0.5s ease-out 2.5s forwards; }
        @keyframes lbIn { to{opacity:1;} }
        .exit-loading-fill { height:100%; width:0%; background:linear-gradient(90deg,var(--orange),#FF6B35); border-radius:3px; box-shadow:0 0 10px var(--orange-glow); }
        .exit-overlay.active .exit-loading-fill { animation:lbFill 2s ease-out 2.5s forwards; }
        @keyframes lbFill { 0%{width:0%;}100%{width:100%;} }

        .exit-vignette { position:absolute; inset:0; background:radial-gradient(ellipse at center,transparent 20%,rgba(0,0,0,0.7) 100%); opacity:0; pointer-events:none; z-index:90; }
        .exit-overlay.active .exit-vignette { animation:vigIn 1s ease-out 0.3s forwards; }
        @keyframes vigIn { to{opacity:1;} }

        body.exit-initiated { animation:bodyFade 0.6s ease-out forwards; }
        @keyframes bodyFade { to{filter:blur(10px) brightness(0.2) saturate(0);transform:scale(0.92);opacity:0.3;} }

        
        /* ═══════════════════════════════════════════
           DRIBBLING PLAYER ANIMATION (ICON)
           ═══════════════════════════════════════════ */
        .dribble-player {
            position: absolute;
            bottom: 15px;
            left: -120px;
            z-index: 6;
            animation: playerRunAcross 6s linear infinite;
            pointer-events: none;
            display: flex;
            align-items: flex-end;
            gap: 4px;
        }
        .dribble-player .fa-person-running {
            font-size: 64px;
            color: var(--orange);
            opacity: 0.9;
            filter: drop-shadow(0 4px 8px rgba(255,69,0,0.3));
        }
        .dribble-player .player-ball {
            font-size: 20px;
            color: var(--orange);
            animation: ballBounce 0.35s ease-in-out infinite alternate;
            margin-bottom: 4px;
            filter: drop-shadow(0 2px 4px rgba(255,69,0,0.4));
        }
        @keyframes playerRunAcross {
            0% { left: -120px; transform: scaleX(1); }
            48% { left: calc(100% + 30px); transform: scaleX(1); }
            50% { left: calc(100% + 30px); transform: scaleX(-1); }
            98% { left: -120px; transform: scaleX(-1); }
            100% { left: -120px; transform: scaleX(1); }
        }
        @keyframes ballBounce {
            0% { transform: translateY(0) scale(1); }
            100% { transform: translateY(-18px) scale(0.95); }
        }
        /* Speed lines behind player */
        .speed-line {
            position: absolute;
            bottom: 20px;
            height: 2px;
            background: linear-gradient(90deg, transparent, rgba(255,69,0,0.4), transparent);
            border-radius: 2px;
            animation: speedLine 6s linear infinite;
            pointer-events: none;
        }
        .speed-line:nth-child(3) { left: -120px; width: 40px; animation-delay: 0s; }
        .speed-line:nth-child(4) { left: -120px; width: 60px; animation-delay: 0.15s; }
        .speed-line:nth-child(5) { left: -120px; width: 30px; animation-delay: 0.3s; }
        @keyframes speedLine {
            0% { left: -120px; opacity: 0; }
            5% { opacity: 1; }
            45% { left: calc(100% + 20px); opacity: 0; }
            50% { left: calc(100% + 20px); opacity: 0; }
            55% { opacity: 1; }
            95% { left: -120px; opacity: 0; }
            100% { opacity: 0; }
        }

        /* ─── CSS TAMBAHAN UNTUK PROFILE DROPDOWN ─── */
        .profile-dropdown-container {
            position: relative;
            display: inline-block;
        }
        .btn-profile-trigger {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #F2F2F7;
            border: 1px solid #E5E5EA;
            padding: 8px 16px;
            border-radius: 50px;
            cursor: pointer;
            font-family: 'Plus Jakarta Sans', sans-serif;
            transition: all 0.3s ease;
        }
        .btn-profile-trigger:hover {
            background: #E5E5EA;
            border-color: var(--orange);
            transform: scale(1.02);
            box-shadow: 0 4px 12px rgba(255, 82, 0, 0.15);
        }
        .profile-icon-orange {
            font-size: 20px;
            color: var(--orange);
        }
        .profile-trigger-name {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 14px;
            font-weight: 600;
            color: #1C1C1E;
        }
        .chevron-icon {
            font-size: 11px;
            color: var(--orange);
            transition: transform 0.3s ease;
        }
        .profile-dropdown-container.active .chevron-icon {
            transform: rotate(180deg);
        }
        .profile-dropdown-menu {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 240px;
            background: #18191E;
            border-radius: 14px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.25);
            padding: 16px;
            display: none;
            z-index: 1010;
        }
        .profile-dropdown-menu.show {
            display: block;
        }
        .profile-dropdown-header {
            display: flex;
            flex-direction: column;
            padding: 4px 10px 8px;
        }
        .profile-dropdown-header .user-fullname {
            font-size: 15px;
            font-weight: 700;
            color: #FFFFFF;
        }
        .profile-dropdown-header .user-role {
            font-size: 10px;
            font-weight: 600;
            color: #64748B;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .profile-dropdown-divider {
            height: 1px;
            background: #2D3139;
            margin: 10px 0;
        }
        .profile-dropdown-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .profile-dropdown-list li a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            color: #E2E8F0;
            font-size: 13px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s ease;
        }
        .profile-dropdown-list li a:hover {
            background: rgba(255, 255, 255, 0.06);
            color: #FFFFFF;
        }
        .profile-dropdown-list li a i {
            font-size: 14px;
            width: 20px;
            text-align: center;
            color: #94A3B8;
        }
        .profile-dropdown-list li a:hover i {
            color: var(--orange);
        }
        .profile-dropdown-list li a.text-danger {
            color: #EF4444;
        }
        .profile-dropdown-list li a.text-danger i {
            color: #EF4444;
        }
        .profile-dropdown-list li a.text-danger:hover {
            background: rgba(239, 68, 68, 0.1);
        }
        /* ═══════════════════════════════════════════
           RESPONSIVE
           ═══════════════════════════════════════════ */
        @media (max-width:992px) {
            .hero-section { height:auto; grid-template-columns:1fr; }
            .hero-visual { height:400px; }
            .fade-overlay { width:100%; background:linear-gradient(to bottom,#fff 0%,transparent 30%); }
            .hero-stats-card { left:50%; transform:translateX(-50%); bottom:20px; width:90%; justify-content:space-around; }
            .process-grid { grid-template-columns:repeat(2,1fr); }
            .membership-section { grid-template-columns:1fr; }
            .pricing-container { grid-template-columns:1fr; max-width:500px; margin:0 auto; }
            .promo-testimonial-section { grid-template-columns:1fr; }
            .store-banner { flex-direction:column; padding:40px 30px; }
            .store-content { max-width:100%; margin-bottom:40px; }
            .store-visual { position:relative; width:100%; height:250px; }
            .store-features-shelf { display:none; }
            .footer-grid { grid-template-columns:1fr 1fr; }
            .nav-menu { display:none; }
        }
        @media (max-width:576px) {
            .process-grid { grid-template-columns:1fr; }
            .process-grid::before { display:none; }
            .footer-grid { grid-template-columns:1fr; }
            .hero-content h1 { font-size:36px; }
        }
    </style>
</head>
<body>

<!-- ═══════════════════════════════════════════
     NAVBAR
     ═══════════════════════════════════════════ -->
<nav class="navbar">
    <a href="#" class="logo"><img src="asset/image/logo2.png" alt="HoopBall"></a>
    <div class="nav-menu">
        <a href="customer/booking_customer.php">Booking</a>
        <a href="customer/pembatalan_customer.php">Pembatalan</a>
        <a href="customer/langganan_customer.php">Member</a>
        <a href="customer/pembelian_alat.php">Pembelian</a>
    </div>
    <div class="nav-btns">
    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
        <!-- TAMPILAN JIKA SUDAH LOGIN (DROPDOWN PROFIL) -->
        <div class="profile-dropdown-container">
            <button class="btn-profile-trigger" id="profileTrigger">
                <i class="fa-solid fa-circle-user profile-icon-orange"></i>
                <span class="profile-trigger-name"><?= htmlspecialchars($_SESSION['Nama_Customer'] ?? 'User') ?></span>
                <i class="fa-solid fa-chevron-down chevron-icon"></i>
            </button>
            
            <div class="profile-dropdown-menu" id="profileDropdownMenu">
                <div class="profile-dropdown-header">
                    <span class="user-fullname"><?= htmlspecialchars($_SESSION['Nama_Customer'] ?? 'User') ?></span>
                    <span class="user-role">CUSTOMER</span>
                </div>
                
                <div class="profile-dropdown-divider"></div>
                
                <ul class="profile-dropdown-list">
                    <li><a href="profile/profile_customer.php"><i class="fa-regular fa-user"></i> Profil Saya</a></li>
                </ul>
                
                <div class="profile-dropdown-divider"></div>
                
                <ul class="profile-dropdown-list">
                    <li><a href="login/logout.php"><i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar</a></li>
                </ul>
            </div>
        </div>
    <?php else: ?>
        <!-- TAMPILAN JIKA BELUM LOGIN -->
        <a href="login/login.php" class="btn-login">Masuk</a>
        <a href="login/register.php" class="btn-join">Daftar Sekarang</a>
    <?php endif; ?>
</div>
</nav>

<!-- ═══════════════════════════════════════════
     HERO
     ═══════════════════════════════════════════ -->
<section class="hero-section" id="beranda">
    <div class="hero-content">
        <h1>Sewa Lapangan<br>Basket Jadi<br><span>Lebih Mudah</span></h1>
        <p>Pemesanan lapangan basket favoritmu secara online, pilih jadwal sesuai keinginan, dan nikmati fasilitas terbaik untuk pengalaman bermain yang seru!</p>
        <div class="hero-cta">
            <a href="login/login.php" class="btn-hero-primary"><i class="fa-solid fa-calendar-days"></i> Pemesanan Lapangan</a>
            <a href="login/login.php" class="btn-hero-secondary"><i class="fa-regular fa-clock"></i> Lihat Jadwal</a>
        </div>
    </div>
    <div class="hero-visual">
        <video autoplay loop muted playsinline>
            <source src="asset/video/video.mp4" type="video/mp4">
        </video>
        <div class="fade-overlay"></div>
        <div class="bottom-fade-overlay"></div>
        
        <!-- Dribbling Player Animation -->
        <div class="dribble-player">
            <i class="fa-solid fa-person-running"></i>
            <i class="fa-solid fa-basketball player-ball"></i>
        </div>
        <div class="speed-line"></div>
        <div class="speed-line"></div>
        <div class="speed-line"></div>
        <div class="hero-stats-card">
            <div class="stat-box">
                <div class="stat-icon"><i class="fa-solid fa-calendar-check"></i></div>
                <div class="stat-num">1200+</div>
                <div class="stat-label">Pemesanan</div>
            </div>
            <div class="stat-divider"></div>
            <div class="stat-box">
                <div class="stat-icon"><i class="fa-regular fa-star"></i></div>
                <div class="stat-num">100+</div>
                <div class="stat-label">Member</div>
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

<!-- ═══════════════════════════════════════════
     FEATURES
     ═══════════════════════════════════════════ -->
<section class="features-section">
    <div class="feature-card reveal delay-1">
        <div class="feature-icon"><i class="fa-regular fa-calendar-check"></i></div>
        <h4>Pemesanan Daring</h4>
        <p>Pesan lapangan kapan saja dan di mana saja dengan mudah dan cepat.</p>
    </div>
    <div class="feature-card reveal delay-2">
        <div class="feature-icon"><i class="fa-regular fa-clock"></i></div>
        <h4>Jadwal Waktu-Nyata</h4>
        <p>Cek ketersediaan jadwal secara waktu-nyata dan pilih waktu terbaikmu.</p>
    </div>
    <div class="feature-card reveal delay-3">
        <div class="feature-icon"><i class="fa-solid fa-basketball"></i></div>
        <h4>Alat Basket</h4>
        <p>Bola basket, jersey, sepatu, dan perlengkapan lainnya tersedia dengan kualitas baik.</p>
    </div>
    <div class="feature-card reveal delay-4">
        <div class="feature-icon"><i class="fa-regular fa-user"></i></div>
        <h4>Anggota Lebih Hemat</h4>
        <p>Dapatkan harga spesial dan berbagai keuntungan eksklusif untuk anggota.</p>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     COURT SECTION
     ═══════════════════════════════════════════ -->
<section class="court-section" id="lapangan">
    <div class="section-header reveal">
        <h2>Pilih Lapangan Favoritmu</h2>
    </div>
    <div class="court-grid">
        <div class="court-card reveal delay-1">
            <div class="court-img-container">
                <img src="asset/image/lapangan1.png" alt="Lapangan A">
            </div>
            <div class="court-info">
                <h3>Lapangan A</h3>
                <p>Indoor • Full AC • Lantai Kayu. Cocok untuk latihan & friendly match.</p>
                <div class="court-footer">
                    <div class="court-price"><span>Rp 250.000</span> / jam</div>
                    <a href="login/login.php?id=A" class="btn-detail">Lihat</a>
                </div>
            </div>
        </div>
        <div class="court-card reveal delay-2">
            <div class="court-img-container">
                <img src="asset/image/lapangan2.png" alt="Lapangan B">
            </div>
            <div class="court-info">
                <h3>Lapangan B</h3>
                <p>Indoor • Full AC • Lantai Vinyl. Ring basket portabel + Scoreboard.</p>
                <div class="court-footer">
                    <div class="court-price"><span>Rp 200.000</span> / jam</div>
                    <a href="login/login.php?id=B" class="btn-detail">Lihat</a>
                </div>
            </div>
        </div>
        <div class="court-card reveal delay-3">
            <div class="court-img-container">
                <img src="asset/image/lapangan3.png" alt="Lapangan C">
            </div>
            <div class="court-info">
                <h3>Lapangan C</h3>
                <p>Semi Indoor • Ventilasi Alami. Cocok untuk latihan komunitas.</p>
                <div class="court-footer">
                    <div class="court-price"><span>Rp 150.000</span> / jam</div>
                    <a href="login/login.php?id=C" class="btn-detail">Lihat</a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     PROCESS
     ═══════════════════════════════════════════ -->
<section class="process-section" id="jadwal">
    <div class="section-header reveal">
        <h2>Cara Booking Lapangan</h2>
    </div>
    <div class="process-grid">
        <div class="process-card reveal delay-1">
            <div class="process-step">1</div>
            <i class="fa-regular fa-map"></i>
            <h4>Pilih Lapangan</h4>
            <p>Pilih lapangan favoritmu sesuai kebutuhan.</p>
        </div>
        <div class="process-card reveal delay-2">
            <div class="process-step">2</div>
            <i class="fa-regular fa-calendar"></i>
            <h4>Tentukan Jadwal</h4>
            <p>Pilih tanggal dan waktu yang tersedia.</p>
        </div>
        <div class="process-card reveal delay-3">
            <div class="process-step">3</div>
            <i class="fa-regular fa-credit-card"></i>
            <h4>Lakukan Pembayaran</h4>
            <p>Bayar dengan aman melalui metode pembayaran pilihan.</p>
        </div>
        <div class="process-card reveal delay-4">
            <div class="process-step">4</div>
            <i class="fa-solid fa-basketball"></i>
            <h4>Main Sesuai Jadwal</h4>
            <p>Datang tepat waktu dan nikmati permainanmu!</p>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     MEMBERSHIP
     ═══════════════════════════════════════════ -->
<section class="membership-section" id="member">
    <div class="member-intro reveal-left">
        <h2>Gabung Jadi Anggota</h2>
        <p>Nikmati berbagai keuntungan eksklusif dan harga spesial setiap kali pemesanan lapangan. Hemat lebih banyak!</p>
        <div class="member-benefit-list">
            <div class="benefit-item">
                <div class="benefit-icon"><i class="fa-solid fa-tags"></i></div>
                <div class="benefit-text"><h4>Lebih Hemat</h4><p>Harga spesial khusus untuk Anggota.</p></div>
            </div>
            <div class="benefit-item">
                <div class="benefit-icon"><i class="fa-solid fa-trophy"></i></div>
                <div class="benefit-text"><h4>Prioritas Pemesanan</h4><p>Akses awal untuk jadwal prime time.</p></div>
            </div>
            <div class="benefit-item">
                <div class="benefit-icon"><i class="fa-solid fa-gift"></i></div>
                <div class="benefit-text"><h4>Benefit Eksklusif</h4><p>Dapatkan merchandise & promo spesial khusus member baru.</p></div>
            </div>
        </div>
    </div>
    <div class="pricing-container reveal-right">
        <div class="pricing-card">
            <div class="price-name">Silver</div>
            <div class="price-amount">Rp. 100.000 <span>/30hari</span></div>
            <ul class="price-features">
                <li><i class="fa-solid fa-circle-check"></i> Potongan Rp. 10.000 per booking</li>
                <li><i class="fa-solid fa-circle-check"></i> Masa aktif 30 hari</li>
            </ul>
            <a href="login/login.php?plan=basic" class="btn-price outline">Daftar Anggota</a>
        </div>
        <div class="pricing-card premium">
            <div class="popular-badge">Paling Populer</div>
            <div class="price-name">Platinum</div>
            <div class="price-amount">Rp 350.000 <span>/30 hari</span></div>
            <ul class="price-features">
                <li><i class="fa-solid fa-circle-check"></i> Potongan Rp. 35.000 per booking</li>
                <li><i class="fa-solid fa-circle-check"></i> Masa aktif 30 hari</li>
            </ul>
            <a href="login/login.php?plan=premium" class="btn-price outline">Daftar Anggota</a>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     PROMO & LOCATION
     ═══════════════════════════════════════════ -->
<section class="promo-testimonial-section">
    <div class="promo-card reveal-left">
        <div class="promo-badge">Promo Weekend</div>
        <div class="promo-content-block">
            <h3>Diskon 20%</h3>
            <p>Untuk semua lapangan</p>
            <div class="btn-promo-yellow">Setiap Sabtu & Minggu</div>
            <div class="promo-terms">*Syarat & ketentuan berlaku</div>
        </div>
        <img src="asset/image/promo1.png" class="promo-player-img" alt="Basketball Player Promo">
    </div>
    <div class="location-map-card reveal-right">
        <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3965.3545986499857!2d107.14830219999999!3d-6.3481107!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e699b896d7fc649%3A0xe0a940b1f200d008!2sPoliteknik%20Astra!5e0!3m2!1sid!2sid!4v1780735557436!5m2!1sid!2sid" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
        <div class="map-overlay-card">
            <span class="map-badge"><i class="fa-solid fa-location-dot"></i> Lokasi Utama</span>
            <h4>Politeknik Astra</h4>
            <p>Delta Silicon II, Cibatu, Cikarang Selatan, Bekasi, Jawa Barat 17530</p>
            <a href="https://maps.app.goo.gl/FpzS6FdUWPp6kGvQ9" target="_blank" class="map-link">Petunjuk Arah <i class="fa-solid fa-arrow-turn-up"></i></a>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     STORE CTA
     ═══════════════════════════════════════════ -->
<section class="store-cta-section" id="alat-basket">
    <div class="store-banner reveal">
        <div class="store-content">
            <h2>Lengkapi Permainanmu<br>dengan <span>Alat Basket Berkualitas</span></h2>
            <p>Temukan bola basket, jersey, sepatu, handuk, dan perlengkapan terbaik untuk latihan maupun pertandingan.</p>
            <a href="login/login.php" class="btn-store-cta"><i class="fa-solid fa-bag-shopping"></i> Lihat Alat Basket</a>
        </div>
        <div class="store-visual">
            <img src="asset/image/alat basket.png" class="store-gear-img" alt="Basketball Gear">
        </div>
        <div class="store-features-shelf">
            <div class="shelf-item"><i class="fa-regular fa-circle-check"></i><span>Produk Original & Berkualitas</span></div>
            <div class="shelf-item"><i class="fa-solid fa-tags"></i><span>Harga Terbaik</span></div>
            <div class="shelf-item"><i class="fa-solid fa-bolt"></i><span>Pelayanan Cepat</span></div>
            <div class="shelf-item"><i class="fa-solid fa-circle-check"></i><span>Aman & Terpercaya</span></div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     FOOTER
     ═══════════════════════════════════════════ -->
<footer id="tentang-kami">
    <div class="footer-grid">
        <div class="footer-brand">
            <a href="#" class="logo"><img src="asset/image/logo2.png" alt="HoopBall"></a>
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
                <div class="contact-item"><i class="fa-solid fa-phone"></i><span>0812-3456-7890</span></div>
                <div class="contact-item"><i class="fa-solid fa-envelope"></i><span>info@hoopball.id</span></div>
                <div class="contact-item"><i class="fa-solid fa-location-dot"></i><span>
Politeknik Astra, Delta Silicon II, Cibatu, Cikarang Selatan, Bekasi, Jawa Barat 17530</span></div>
            </div>
        </div>
        <div class="footer-col">
            <h4>Tautan</h4>
            <ul class="footer-links">
                <li><a href="#lapangan">Lapangan</a></li>
                <li><a href="#jadwal">Jadwal</a></li>
                <li><a href="#alat-basket">Alat Basket</a></li>
                <li><a href="#tentang-kami">Member</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Informasi</h4>
            <ul class="footer-links">
                <li><a href="#">Cara Pemesanan</a></li>
                <li><a href="syarat_ketentuan.php">Syarat & Ketentuan</a></li>
                <li><a href="kebijakan_privasi.php">Kebijakan Privasi</a></li>
                <li><a href="faq.php">FAQ</a></li>
                <li><a href="#">Hubungi Kami</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom"><p>&copy; 2024 HoopBall. All rights reserved.</p></div>
</footer>

<!-- ═══════════════════════════════════════════
     EXIT OVERLAY
     ═══════════════════════════════════════════ -->
<div class="exit-overlay" id="exitOverlay">
    <div class="exit-stage"><div class="court-lines"></div></div>
    <div class="exit-spotlight"></div>
    <div class="exit-scoreboard">
        <div class="score-team"><div class="score-team-name">HOME</div><div class="score-team-logo"><i class="fa-solid fa-basketball"></i></div></div>
        <div class="score-divider"></div>
        <div class="score-team">
            <div class="score-points" id="scorePoints">98<em> - 96</em></div>
            <div class="score-timer">Q4 00:03</div>
        </div>
        <div class="score-divider"></div>
        <div class="score-team"><div class="score-team-name">AWAY</div><div class="score-team-logo"><i class="fa-solid fa-shield-halved"></i></div></div>
    </div>
    <div class="exit-hoop-container">
        <div class="backboard"></div>
        <div class="rim"></div>
        <div class="net"></div>
    </div>
    <div class="exit-ball" id="exitBall">
        <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <radialGradient id="ballG" cx="30%" cy="30%" r="70%">
                    <stop offset="0%" stop-color="#FF8C42"/><stop offset="40%" stop-color="#FF4500"/><stop offset="100%" stop-color="#CC3700"/>
                </radialGradient>
            </defs>
            <circle cx="50" cy="50" r="45" fill="url(#ballG)"/>
            <g stroke="rgba(0,0,0,0.3)" stroke-width="2.5" fill="none">
                <path d="M 8 50 Q 50 58 92 50" stroke-width="3.5"/>
                <path d="M 50 8 Q 35 50 50 92"/><path d="M 50 8 Q 65 50 50 92"/>
                <path d="M 18 18 Q 50 35 82 18"/><path d="M 18 82 Q 50 65 82 82"/>
            </g>
            <ellipse cx="35" cy="35" rx="18" ry="12" fill="rgba(255,255,255,0.2)" transform="rotate(-30 35 35)"/>
        </svg>
    </div>
    <div class="flash-bulb"></div>
    <div class="confetti-container">
        <div class="confetti-piece"></div><div class="confetti-piece"></div>
        <div class="confetti-piece"></div><div class="confetti-piece"></div>
        <div class="confetti-piece"></div><div class="confetti-piece"></div>
        <div class="confetti-piece"></div><div class="confetti-piece"></div>
        <div class="confetti-piece"></div><div class="confetti-piece"></div>
    </div>
    <div class="exit-message">
        <div class="exit-message-tagline">Sampai Jumpa</div>
        <div class="exit-message-title">HOOP<em>BALL</em></div>
        <div class="exit-message-sub">Terima kasih telah bermain</div>
    </div>
    <div class="exit-loading-bar"><div class="exit-loading-fill"></div></div>
    <div class="exit-vignette"></div>
</div>

<!-- ═══════════════════════════════════════════
     SCRIPTS
     ═══════════════════════════════════════════ -->
<script>
// ── Clean URL ──────────────────────────────────────────────────────
if (window.history.replaceState) {
    const clean = location.protocol + '//' + location.host + location.pathname;
    window.history.replaceState({}, '', clean);
}

// ── Scroll Reveal ─────────────────────────────────────────────────
const revealEls = document.querySelectorAll('.reveal, .reveal-left, .reveal-right');
const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => {
        if (e.isIntersecting) {
            e.target.classList.add('visible');
            observer.unobserve(e.target);
        }
    });
}, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
revealEls.forEach(el => observer.observe(el));

// ── Scroll Spy ────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const sections = document.querySelectorAll('section[id], footer[id]');
    const navLinks = document.querySelectorAll('.nav-menu a');

    window.addEventListener('scroll', () => {
        let current = '';
        sections.forEach(s => {
            if (window.pageYOffset >= s.offsetTop - 150) current = s.id;
        });
        navLinks.forEach(a => {
            a.classList.remove('active');
            if (a.getAttribute('href') === `#${current}`) a.classList.add('active');
        });
    }, { passive: true });
});

// ── Exit Animation ────────────────────────────────────────────────
function triggerExitAnimation() {
    document.body.classList.add('exit-initiated');
    const overlay = document.getElementById('exitOverlay');
    overlay.classList.add('active');

    setTimeout(() => {
        const sc = document.getElementById('scorePoints');
        if (sc) { sc.innerHTML = '101<em> - 96</em>'; sc.style.color = '#10B981'; }
    }, 1600);

    setTimeout(() => {
        window.location.href = 'exit_intro.php?to=intro.php';
    }, 4500);
}

// ── Dropdown Profil Toggle ─────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    const profileTrigger = document.getElementById('profileTrigger');
    const profileDropdownMenu = document.getElementById('profileDropdownMenu');
    const dropdownContainer = document.querySelector('.profile-dropdown-container');

    if (profileTrigger && profileDropdownMenu) {
        profileTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdownMenu.classList.toggle('show');
            dropdownContainer.classList.toggle('active');
        });

        document.addEventListener('click', (e) => {
            if (!dropdownContainer.contains(e.target)) {
                profileDropdownMenu.classList.remove('show');
                dropdownContainer.classList.remove('active');
            }
        });
    }
});

</script>
</body>
</html>