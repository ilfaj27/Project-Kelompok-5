<?php
session_start();

if (isset($_GET['load']) && $_GET['load'] === 'done') {
    $_SESSION['intro_done'] = true;
}
if (!isset($_SESSION['intro_done'])) {
    header("Location: intro.php");
    exit();
}

// ========================================================
// PANGGIL AUTO LOGOUT - HANYA UNTUK USER YANG SUDAH LOGIN
// ========================================================
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Karena index.php di luar dan file di dalam folder 'login'
    require_once 'login/auto_logout.php';
}

// Inisialisasi variabel
$total_lapangan = 0;
$total_member = 0;
$total_booking = 0;
$lapangan_list = [];
$tipe_member_list = [];
$promo_list = [];

if (file_exists('includes/config.php')) {
    include 'includes/config.php';
    if (isset($conn)) {
        // --- Total Lapangan Aktif menggunakan Scalar UDF ---
        $sql_lap = "SELECT dbo.fn_GetTotalLapanganAktif() as total";
        $q_lap = sqlsrv_query($conn, $sql_lap);
        if ($q_lap) {
            $d_lap = sqlsrv_fetch_array($q_lap, SQLSRV_FETCH_ASSOC);
            $total_lapangan = $d_lap['total'] ?? 0;
        }

        // --- Total Member Aktif menggunakan Scalar UDF ---
        $sql_member = "SELECT dbo.fn_GetTotalMemberAktif() as total";
        $q_member = sqlsrv_query($conn, $sql_member);
        if ($q_member) {
            $d_member = sqlsrv_fetch_array($q_member, SQLSRV_FETCH_ASSOC);
            $total_member = $d_member['total'] ?? 0;
        }

        // --- Total Booking Berhasil/Selesai menggunakan Scalar UDF ---
        $sql_booking = "SELECT dbo.fn_GetTotalBookingBerhasil() as total";
        $q_booking = sqlsrv_query($conn, $sql_booking);
        if ($q_booking) {
            $d_booking = sqlsrv_fetch_array($q_booking, SQLSRV_FETCH_ASSOC);
            $total_booking = $d_booking['total'] ?? 0;
        }

        // --- Daftar Lapangan Aktif menggunakan Table-Valued UDF (MAKSIMAL 3) ---
        $sql_lapangan = "SELECT * FROM dbo.fn_GetTopLapanganAktif(3)";
        $q_lapangan = sqlsrv_query($conn, $sql_lapangan);
        if ($q_lapangan) {
            while ($row = sqlsrv_fetch_array($q_lapangan, SQLSRV_FETCH_ASSOC)) {
                $lapangan_list[] = $row;
            }
        }

        // --- Daftar Tipe Member Aktif menggunakan Table-Valued UDF (MAKSIMAL 2) ---
        $sql_tipe = "SELECT * FROM dbo.fn_GetTopTipeMemberAktif(2)";
        $q_tipe = sqlsrv_query($conn, $sql_tipe);
        if ($q_tipe) {
            while ($row = sqlsrv_fetch_array($q_tipe, SQLSRV_FETCH_ASSOC)) {
                $tipe_member_list[] = $row;
            }
        }

        // --- Daftar Promo Aktif menggunakan Table-Valued UDF (MAKSIMAL 1) ---
        $sql_promo = "SELECT * FROM dbo.fn_GetTopPromoAktif(1)";
        $q_promo = sqlsrv_query($conn, $sql_promo);
        if ($q_promo) {
            while ($row = sqlsrv_fetch_array($q_promo, SQLSRV_FETCH_ASSOC)) {
                if ($row['Tanggal_Mulai'] instanceof DateTime) {
                    $row['Tanggal_Mulai'] = $row['Tanggal_Mulai']->format('Y-m-d');
                } else {
                    $row['Tanggal_Mulai'] = date('Y-m-d', strtotime($row['Tanggal_Mulai']));
                }
                if ($row['Tanggal_Selesai'] instanceof DateTime) {
                    $row['Tanggal_Selesai'] = $row['Tanggal_Selesai']->format('Y-m-d');
                } else {
                    $row['Tanggal_Selesai'] = date('Y-m-d', strtotime($row['Tanggal_Selesai']));
                }
                $promo_list[] = $row;
            }
        }
    }
}

function rupiah($n)
{
    return 'Rp ' . number_format($n, 0, ',', '.');
}

function getPhotoUrl($photo_path)
{
    if (empty($photo_path))
        return '';
    $path = str_replace('../', '', $photo_path);
    $path = ltrim($path, '/');
    return $path;
}
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <?php include 'includes/favicon.php'; ?>
    <title>HoopBall - Sewa Lapangan Basket Jadi Lebih Mudah</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link
        href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="asset/css/navbar_footer.css">
    <link rel="stylesheet" href="asset/css/responsive.css?v=1.1">
    <style>
        html {
            scroll-behavior: smooth;
            scrollbar-width: none;
            -ms-overflow-style: none;
            background-color: #FFFFFF !important;
        }

        html::-webkit-scrollbar {
            display: none;
        }

        section[id],
        footer[id] {
            scroll-margin-top: 90px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Barlow', sans-serif;
        }

        body {
            background-color: #FFFFFF !important;
            color: var(--text-dark);
            overflow-x: hidden;
            line-height: 1.5;
        }

        a {
            text-decoration: none;
            transition: all 0.3s ease;
        }

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
            transition: opacity 0.65s cubic-bezier(0.22, 1, 0.36, 1), transform 0.65s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .reveal-left.visible {
            opacity: 1;
            transform: translateX(0);
        }

        .reveal-right {
            opacity: 0;
            transform: translateX(36px);
            transition: opacity 0.65s cubic-bezier(0.22, 1, 0.36, 1), transform 0.65s cubic-bezier(0.22, 1, 0.36, 1);
        }

        .reveal-right.visible {
            opacity: 1;
            transform: translateX(0);
        }

        .delay-1 {
            transition-delay: 0.08s !important;
        }

        .delay-2 {
            transition-delay: 0.16s !important;
        }

        .delay-3 {
            transition-delay: 0.24s !important;
        }

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
            animation: heroText 0.9s cubic-bezier(0.22, 1, 0.36, 1) 0.55s both;
        }

        @keyframes heroText {
            from {
                opacity: 0;
                transform: translateX(-30px);
            }

            to {
                opacity: 1;
                transform: translateX(0);
            }
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
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-hero-primary i {
            transition: transform 0.3s ease;
        }

        .btn-hero-primary:hover {
            background: var(--orange-dark);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 69, 0, 0.35);
        }

        .btn-hero-primary:hover i {
            transform: rotate(-10deg) scale(1.1);
        }

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
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-hero-secondary i {
            color: #475569;
            transition: transform 0.4s ease, color 0.3s ease;
        }

        .btn-hero-secondary:hover {
            background: #E2E8F0;
            border-color: #94A3B8;
            color: #0F172A;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
        }

        .btn-hero-secondary:hover i {
            transform: rotate(30deg);
            color: var(--orange);
        }

        .hero-visual {
            position: relative;
            height: 100%;
            overflow: hidden;
            animation: heroVid 1s cubic-bezier(0.22, 1, 0.36, 1) 0.4s both;
        }

        @keyframes heroVid {
            from {
                opacity: 0;
                transform: translateX(40px) scale(1.04);
            }

            to {
                opacity: 1;
                transform: translateX(0) scale(1);
            }
        }

        .hero-visual video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
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
            background: linear-gradient(to right, #fff 0%, transparent 100%);
            z-index: 2;
        }

        .bottom-fade-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 50px;
            background: linear-gradient(to top, #fff 0%, transparent 100%);
            z-index: 3;
            pointer-events: none;
        }

        .hero-stats-card {
            position: absolute;
            bottom: 50px;
            left: 34%;
            background: #fff;
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
            animation: statsCard 0.8s cubic-bezier(0.22, 1, 0.36, 1) 0.85s both;
        }

        @keyframes statsCard {
            from {
                opacity: 0;
                transform: translateY(20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-stats-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
            border-color: rgba(255, 69, 0, 0.1);
        }

        .hero-stats-card:hover .stat-box:nth-child(1) .stat-icon i {
            transform: translateY(-4px) scale(1.15);
        }

        .hero-stats-card:hover .stat-box:nth-child(3) .stat-icon i {
            transform: scale(1.2) rotate(15deg);
        }

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
        }

        .stat-num {
            font-size: 20px;
            font-weight: 800;
            color: #111;
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
            background: var(--border-color);
        }

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
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.05);
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
            transition: all 0.3s ease;
        }

        .feature-card:hover .feature-icon {
            background: var(--orange);
        }

        .feature-icon i {
            font-size: 20px;
            color: var(--orange);
            transition: color 0.3s ease;
        }

        .feature-card:hover .feature-icon i {
            color: #fff;
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

        .section-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .section-header h2 {
            font-size: 32px;
            font-weight: 800;
            color: var(--dark);
            font-family: 'Barlow Condensed', sans-serif;
            position: relative;
            display: inline-block;
        }

        .section-header h2::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            width: 48px;
            height: 3px;
            background: var(--orange);
            border-radius: 2px;
        }

        .court-section {
            padding: 80px 8%;
            background: #fff;
        }

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

        .court-card:hover {
            transform: translateY(-6px);
            box-shadow: 0 14px 35px rgba(0, 0, 0, 0.07);
        }

        .court-img-container {
            width: 100%;
            height: 220px;
            overflow: hidden;
            position: relative;
            background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%);
        }

        .court-img-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .court-card:hover .court-img-container img {
            transform: scale(1.05);
        }

        .court-img-placeholder {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .court-img-placeholder i {
            font-size: 48px;
            color: var(--orange);
            opacity: 0.4;
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
            transition: all 0.3s ease;
        }

        .btn-detail:hover {
            background: var(--orange);
            color: #fff;
        }

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
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 36px 24px;
            text-align: center;
            position: relative;
            z-index: 2;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
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
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 14px;
            margin: 0 auto 24px;
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
            background: var(--bg-light);
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
            color: #fff;
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
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 40px 30px;
            position: relative;
            display: flex;
            flex-direction: column;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .pricing-card:hover {
            transform: translateY(-8px);
            border-color: var(--orange);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.05);
        }

        .pricing-card.premium:hover {
            transform: translateY(-12px);
            box-shadow: 0 24px 48px rgba(255, 69, 0, 0.12);
        }

        .popular-badge {
            position: absolute;
            top: -15px;
            left: 50%;
            transform: translateX(-50%);
            background: var(--orange);
            color: #fff;
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
            background: var(--orange-dark);
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
            display: inline-block;
        }

        .btn-price.outline {
            border: 1px solid var(--orange);
            color: var(--orange);
            background: transparent;
        }

        .btn-price.outline:hover {
            background: var(--orange);
            color: #fff;
        }

        .btn-price.filled {
            background: var(--orange);
            color: #fff;
            border: none;
        }

        .btn-price.filled:hover {
            background: var(--orange-dark);
            transform: scale(1.02);
        }

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
            box-shadow: 0 10px 30px rgba(255, 84, 0, 0.12);
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            cursor: pointer;
        }

        .promo-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 45px rgba(255, 69, 0, 0.28);
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
            background: #FFC107;
            color: #1E293B;
            font-weight: 700;
            font-size: 13px;
            padding: 10px 24px;
            border-radius: 50px;
            display: inline-block;
            width: fit-content;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.2);
            transition: all 0.3s ease;
        }

        .promo-card:hover .btn-promo-yellow {
            background: #FFB300;
            transform: scale(1.04);
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

        .location-map-card {
            position: relative;
            background: #fff;
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
            transition: filter 0.6s ease;
        }

        .location-map-card:hover iframe {
            filter: grayscale(0%);
        }

        .map-overlay-card {
            position: absolute;
            top: 16px;
            right: 16px;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(8px);
            border-radius: 12px;
            padding: 14px 18px;
            max-width: 210px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.8);
            z-index: 10;
            transition: all 0.4s ease;
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
            color: #fff;
            font-size: 10px;
            padding: 6px 12px;
            border-radius: 6px;
            transition: all 0.3s ease;
        }

        .map-link:hover {
            background: var(--orange-dark);
            transform: translateY(-1px);
        }

        .store-cta-section {
            padding: 60px 8%;
            background: #fff;
        }

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
            color: #fff;
            padding: 14px 28px;
            font-weight: 700;
            font-size: 14px;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 14px rgba(255, 84, 0, 0.2);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }

        .btn-store-cta:hover {
            background: var(--orange-dark);
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
            pointer-events: none;
        }

        .store-features-shelf {
            position: absolute;
            bottom: 0;
            right: 0;
            width: 72%;
            height: 50px;
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
            top: -28px;
            left: -28px;
            width: 28px;
            height: 28px;
            background: transparent;
            border-bottom-right-radius: 28px;
            box-shadow: 14px 14px 0 0 #F8FAFC;
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

        .empty-court,
        .empty-member,
        .empty-promo {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-muted);
            grid-column: 1 / -1;
        }

        .empty-court i,
        .empty-member i,
        .empty-promo i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.3;
            display: block;
        }

        .empty-court div,
        .empty-member div,
        .empty-promo div {
            font-size: 14px;
            font-weight: 700;
        }

        @media (max-width:992px) {
            .hero-section {
                height: auto;
                grid-template-columns: 1fr;
            }

            .hero-visual {
                height: 400px;
            }

            .fade-overlay {
                width: 100%;
                background: linear-gradient(to bottom, #fff 0%, transparent 30%);
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

            .store-banner {
                flex-direction: column;
                padding: 40px 30px;
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

            .hero-section {
                padding-top: 80px !important; /* Memberikan ruang atas agar teks turun aman */
                height: auto !important;
            }
            .hero-content {
                margin-top: 20px !important;  /* Dorong teks h1 & p ke bawah */
                height: auto !important;      /* Reset tinggi desktop */
            }
            /* Merampingkan tombol masuk/daftar di HP agar sejajar dengan logo */
            .nav-btns .btn-login, 
            .nav-btns .btn-join {
                padding: 8px 12px !important;
                font-size: 11px !important;
                width: auto !important;
                white-space: nowrap !important;
            }

            .store-features-shelf {
                display: none;
            }
        }

        @media (max-width:576px) {
            .process-grid {
                grid-template-columns: 1fr;
            }

            .process-grid::before {
                display: none;
            }

            .hero-content h1 {
                font-size: 36px;
            }
        }

        

        
    </style>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <section class="hero-section" id="beranda">
        <div class="hero-content">
            <h1>Sewa Lapangan<br>Basket Jadi<br><span>Lebih Mudah</span></h1>
            <p>Pemesanan lapangan basket favoritmu secara online, pilih jadwal sesuai keinginan, dan nikmati fasilitas
                terbaik untuk pengalaman bermain yang seru!</p>
            <div class="hero-cta">
                <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                    <a href="customer/booking_customer.php" class="btn-hero-primary">
                        <i class="fa-solid fa-calendar-days"></i> Pemesanan Lapangan
                    </a>
                    <a href="customer/booking_customer.php" class="btn-hero-secondary">
                        <i class="fa-regular fa-clock"></i> Lihat Jadwal
                    </a>
                <?php else: ?>
                    <a href="login/login.php" class="btn-hero-primary">
                        <i class="fa-solid fa-calendar-days"></i> Pemesanan Lapangan
                    </a>
                    <a href="login/login.php" class="btn-hero-secondary">
                        <i class="fa-regular fa-clock"></i> Lihat Jadwal
                    </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="hero-visual">
            <video autoplay loop muted playsinline>
                <source src="asset/video/video.mp4" type="video/mp4">
            </video>
            <div class="fade-overlay"></div>
            <div class="bottom-fade-overlay"></div>
            <div class="hero-stats-card">
                <div class="stat-box">
                    <div class="stat-icon"><i class="fa-solid fa-calendar-check"></i></div>
                    <div class="stat-num"><?= number_format($total_booking, 0, ',', '.') ?>+</div>
                    <div class="stat-label">Pemesanan</div>
                </div>
                <div class="stat-divider"></div>
                <div class="stat-box">
                    <div class="stat-icon"><i class="fa-regular fa-star"></i></div>
                    <div class="stat-num"><?= number_format($total_member, 0, ',', '.') ?>+</div>
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

    <section class="court-section" id="lapangan">
        <div class="section-header reveal">
            <h2>Pilih Lapangan Favoritmu</h2>
        </div>
        <div class="court-grid">
            <?php if (!empty($lapangan_list)): ?>
                <?php foreach ($lapangan_list as $index => $lap): ?>
                    <div class="court-card reveal delay-<?= ($index % 3) + 1 ?>">
                        <div class="court-img-container">
                            <?php
                            $photo_url = getPhotoUrl($lap['Photo_Lapangan'] ?? '');
                            if (!empty($photo_url)):
                                ?>
                                <img src="<?= htmlspecialchars($photo_url) ?>" alt="<?= htmlspecialchars($lap['Nama_Lapangan']) ?>"
                                    onerror="this.style.display='none'; this.parentElement.querySelector('.court-img-placeholder').style.display='flex';">
                                <div class="court-img-placeholder" style="display:none; position:absolute; top:0; left:0;">
                                    <i class="fa-solid fa-layer-group"></i>
                                </div>
                            <?php else: ?>
                                <div class="court-img-placeholder">
                                    <i class="fa-solid fa-layer-group"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="court-info">
                            <h3><?= htmlspecialchars($lap['Nama_Lapangan']) ?></h3>
                            <p>Lapangan basket dengan fasilitas terbaik untuk pengalaman bermain yang maksimal.</p>
                            <div class="court-footer">
                                <div class="court-price"><span><?= rupiah($lap['Harga_Sewa']) ?></span> / jam</div>
                                <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                                    <a href="customer/booking_customer.php?id=<?= intval($lap['ID_Lapangan']) ?>"
                                        class="btn-detail">Lihat</a>
                                <?php else: ?>
                                    <a href="login/login.php?id=<?= intval($lap['ID_Lapangan']) ?>" class="btn-detail">Lihat</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-court">
                    <i class="fa-solid fa-layer-group"></i>
                    <div>Belum ada lapangan tersedia</div>
                </div>
            <?php endif; ?>
        </div>
    </section>

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

    <section class="membership-section" id="member">
        <div class="member-intro reveal-left">
            <h2>Gabung Jadi Anggota</h2>
            <p>Nikmati berbagai keuntungan eksklusif dan harga spesial setiap kali pemesanan lapangan. Hemat lebih
                banyak!</p>
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
        <div class="pricing-container reveal-right">
            <?php if (!empty($tipe_member_list)): ?>
                <?php foreach ($tipe_member_list as $index => $tipe): ?>
                    <div class="pricing-card <?= $index === count($tipe_member_list) - 1 ? 'premium' : '' ?>">
                        <?php if ($index === count($tipe_member_list) - 1): ?>
                            <div class="popular-badge">Paling Populer</div>
                        <?php endif; ?>
                        <div class="price-name"><?= htmlspecialchars($tipe['Nama_Tipe']) ?></div>
                        <div class="price-amount"><?= rupiah($tipe['Harga_Member']) ?> <span>/30 hari</span></div>
                        <ul class="price-features">
                            <li><i class="fa-solid fa-circle-check"></i> Potongan <?= rupiah($tipe['Potongan_Harga']) ?> per
                                booking</li>
                            <li><i class="fa-solid fa-circle-check"></i> Masa aktif 30 hari</li>
                        </ul>
                        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                            <a href="customer/langganan_customer.php?plan=<?= urlencode(strtolower($tipe['Nama_Tipe'])) ?>"
                                class="btn-price outline">Daftar Anggota</a>
                        <?php else: ?>
                            <a href="login/login.php?plan=<?= urlencode(strtolower($tipe['Nama_Tipe'])) ?>"
                                class="btn-price outline">Daftar Anggota</a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-member">
                    <i class="fa-solid fa-star"></i>
                    <div>Belum ada tipe member tersedia</div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="promo-testimonial-section">
        <?php if (!empty($promo_list)): ?>
            <?php $promo = $promo_list[0];
            $diskon = intval($promo['Diskon']); ?>
            <div class="promo-card reveal-left">
                <div class="promo-badge">Promo Aktif</div>
                <div class="promo-content-block">
                    <h3>Diskon <?= $diskon ?>%</h3>
                    <p><?= htmlspecialchars($promo['Nama_Promo']) ?></p>
                    <div class="btn-promo-yellow">
                        <?= date('d M Y', strtotime($promo['Tanggal_Mulai'])) ?> -
                        <?= date('d M Y', strtotime($promo['Tanggal_Selesai'])) ?>
                    </div>
                    <div class="promo-terms">*Syarat & ketentuan berlaku</div>
                </div>
                <img src="asset/image/promo1.png" class="promo-player-img" alt="Basketball Player Promo">
            </div>
        <?php else: ?>
            <div class="promo-card reveal-left" style="justify-content: center; align-items: center; text-align: center;">
                <div class="promo-content-block">
                    <h3>Tidak Ada Promo</h3>
                    <p>Nantikan promo menarik dari kami</p>
                </div>
            </div>
        <?php endif; ?>
        <div class="location-map-card reveal-right">
            <iframe
                src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3965.3545986499857!2d107.14830219999999!3d-6.3481107!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e699b896d7fc649%3A0xe0a940b1f200d008!2sPoliteknik%20Astra!5e0!3m2!1sid!2sid!4v1780735557436!5m2!1sid!2sid"
                style="border:0;" allowfullscreen="" loading="lazy"></iframe>
            <div class="map-overlay-card">
                <span class="map-badge"><i class="fa-solid fa-location-dot"></i> Lokasi Utama</span>
                <h4>Politeknik Astra</h4>
                <p>Delta Silicon II, Cibatu, Cikarang Selatan, Bekasi, Jawa Barat 17530</p>
                <a href="https://maps.app.goo.gl/FpzS6FdUWPp6kGvQ9" target="_blank" class="map-link">Petunjuk Arah <i
                        class="fa-solid fa-arrow-turn-up"></i></a>
            </div>
        </div>
    </section>

    <section class="store-cta-section" id="alat-basket">
        <div class="store-banner reveal">
            <div class="store-content">
                <h2>Lengkapi Permainanmu<br>dengan <span>Alat Basket Berkualitas</span></h2>
                <p>Temukan bola basket, jersey, sepatu, handuk, dan perlengkapan terbaik untuk latihan maupun
                    pertandingan.</p>
                <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
                    <a href="customer/pembelian_alat.php" class="btn-store-cta">
                        <i class="fa-solid fa-bag-shopping"></i> Lihat Alat Basket
                    </a>
                <?php else: ?>
                    <a href="login/login.php" class="btn-store-cta">
                        <i class="fa-solid fa-bag-shopping"></i> Lihat Alat Basket
                    </a>
                <?php endif; ?>
            </div>
            <div class="store-visual">
                <img src="asset/image/alat basket.png" class="store-gear-img" alt="Basketball Gear">
            </div>
            <div class="store-features-shelf">
                <div class="shelf-item"><i class="fa-regular fa-circle-check"></i><span>Produk Original &
                        Berkualitas</span></div>
                <div class="shelf-item"><i class="fa-solid fa-tags"></i><span>Harga Terbaik</span></div>
                <div class="shelf-item"><i class="fa-solid fa-bolt"></i><span>Pelayanan Cepat</span></div>
                <div class="shelf-item"><i class="fa-solid fa-circle-check"></i><span>Aman & Terpercaya</span></div>
            </div>
        </div>
    </section>

    <?php include 'includes/footer.php'; ?>

    <script>
        if (window.history.replaceState) {
            const clean = location.protocol + '//' + location.host + location.pathname;
            window.history.replaceState({}, '', clean);
        }

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

        document.addEventListener('DOMContentLoaded', () => {
            const sections = document.querySelectorAll('section[id], footer[id]');
            const navLinks = document.querySelectorAll('.nav-menu a');

            // Mapping section ID ke nav link yang sesuai
            const sectionToPage = {
                'beranda': 'index.php',
                'lapangan': 'booking_customer.php',
                'jadwal': 'booking_customer.php',
                'member': 'langganan_customer.php',
                'alat-basket': 'pembelian_alat.php'
            };

            function updateActiveNav() {
                let currentSection = '';
                const scrollPos = window.pageYOffset + 150;

                // Cari section yang sedang di viewport (dari atas ke bawah)
                sections.forEach(s => {
                    if (scrollPos >= s.offsetTop) {
                        currentSection = s.id;
                    }
                });

                // Reset semua active
                navLinks.forEach(a => a.classList.remove('active'));

                // Jika ada section yang aktif, tandai link yang sesuai
                if (currentSection && sectionToPage[currentSection]) {
                    const targetPage = sectionToPage[currentSection];
                    navLinks.forEach(a => {
                        const href = a.getAttribute('href');
                        if (href && href.includes(targetPage)) {
                            a.classList.add('active');
                        }
                    });
                }

                // Fallback: jika tidak ada yang aktif, default ke Beranda (index.php)
                const hasActive = Array.from(navLinks).some(a => a.classList.contains('active'));
                if (!hasActive) {
                    navLinks.forEach(a => {
                        const href = a.getAttribute('href');
                        if (href && href.includes('index.php')) {
                            a.classList.add('active');
                        }
                    });
                }
            }

            window.addEventListener('scroll', updateActiveNav, { passive: true });
            // Jalankan sekali saat load untuk set initial state
            updateActiveNav();
        });


        /* ============================================================
       KONFIRMASI SEBELUM KELUAR (LOGOUT)
       Berlaku untuk semua link yang mengarah ke logout.php,
       di sidebar maupun di dropdown topbar, pada SEMUA halaman.
       ============================================================ */
        (function () {
            const SWAL_CDN = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
            let swalLoading = null;

            // Muat SweetAlert2 secara otomatis bila halaman belum memuatnya
            // (mis. dashboard/view_admin.php) supaya tampilan dialog seragam.
            function ensureSwal() {
                if (typeof Swal !== 'undefined') return Promise.resolve();
                if (swalLoading) return swalLoading;

                swalLoading = new Promise(function (resolve, reject) {
                    const s = document.createElement('script');
                    s.src = SWAL_CDN;
                    s.onload = resolve;
                    s.onerror = reject;
                    document.head.appendChild(s);
                });
                return swalLoading;
            }

            function showLogoutDialog(url) {
                Swal.fire({
                    title: 'Keluar dari HoopBall?',
                    html: 'Apakah Anda yakin ingin keluar?<br>' +
                        '<span style="font-size:12px;color:#6B7280;">Sesi Anda akan diakhiri dan Anda perlu masuk kembali.</span>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fa-solid fa-right-from-bracket"></i> Ya, Keluar',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#EF4444',
                    cancelButtonColor: '#6B7280',
                    reverseButtons: true,
                    focusCancel: true,
                    allowOutsideClick: false
                }).then(function (result) {
                    if (!result.isConfirmed) return;

                    Swal.fire({
                        title: 'Sedang keluar...',
                        text: 'Mohon tunggu sebentar.',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        didOpen: function () { Swal.showLoading(); }
                    });

                    setTimeout(function () { window.location.href = url; }, 500);
                });
            }

            document.addEventListener('click', function (e) {
                const link = e.target.closest('a[href*="logout.php"]');
                if (!link) return;

                e.preventDefault();
                const url = link.getAttribute('href');

                ensureSwal()
                    .then(function () { showLogoutDialog(url); })
                    .catch(function () {
                        // CDN tidak bisa diakses -> jangan biarkan logout tanpa konfirmasi
                        if (confirm('Apakah Anda yakin ingin keluar?')) window.location.href = url;
                    });
            });
        })();
    </script>
    <?php if (function_exists('tampilkan_sensor_auto_logout')) tampilkan_sensor_auto_logout(); ?>
</body>

</html>