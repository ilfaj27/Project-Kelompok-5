<?php
// File ini di-include oleh dashboard.php
$role = $_SESSION['role'];
$nama = $_SESSION['nama'];

// ── Contoh data dinamis (ganti dengan query real) ──
// $q_booking = sqlsrv_query($conn, "SELECT COUNT(*) as t FROM Booking WHERE CAST(Tanggal AS DATE) = CAST(GETDATE() AS DATE)");
// $total_booking = sqlsrv_fetch_array($q_booking)['t'];
$total_booking   = 12;
$pendapatan_hari = 2450000;
$member_aktif    = 85;
$stok_alat       = 36;

$recent_bookings = [
    ['nama'=>'Budi Santoso',  'lapangan'=>'Court A','jam'=>'08:00–10:00','status'=>'confirmed','harga'=>200000],
    ['nama'=>'Siti Rahma',    'lapangan'=>'Court B','jam'=>'10:00–12:00','status'=>'confirmed','harga'=>200000],
    ['nama'=>'Eko Prasetyo',  'lapangan'=>'Court A','jam'=>'13:00–15:00','status'=>'pending',  'harga'=>200000],
    ['nama'=>'Ani Wijaya',    'lapangan'=>'Court C','jam'=>'15:00–17:00','status'=>'confirmed','harga'=>250000],
    ['nama'=>'Doni Kusuma',   'lapangan'=>'Court B','jam'=>'17:00–19:00','status'=>'cancelled','harga'=>200000],
];

function rupiahFormat($n){ return 'Rp '.number_format($n,0,',','.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<title>Dashboard Admin | HoopBall</title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* ═══════════════════════════════
   DESIGN TOKENS
═══════════════════════════════ */
:root {
    --orange:    #FF4500;
    --orange-lt: rgba(255,69,0,.12);
    --green:     #10B981;
    --blue:      #3B82F6;
    --purple:    #8B5CF6;
    --red:       #EF4444;
    --sidebar:   #0D1117;
    --sidebar-w: 260px;
    --topbar-h:  70px;
    --card-bg:   #fff;
    --border:    #E5E7EB;
    --text:      #111827;
    --muted:     #6B7280;
    --bg:        #F3F4F6;
}

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { font-family: 'Barlow', sans-serif; background: var(--bg); display: flex; min-height: 100vh; color: var(--text); }

/* ═══════════════════════════════
   SIDEBAR
═══════════════════════════════ */
.sidebar {
    width: var(--sidebar-w);
    background: var(--sidebar);
    height: 100vh;
    position: fixed;
    top: 0; left: 0;
    display: flex;
    flex-direction: column;
    padding: 28px 18px;
    border-right: 1px solid rgba(255,255,255,.04);
    z-index: 200;
}

.sb-brand {
    display: flex; align-items: center; gap: 12px;
    padding: 0 8px; margin-bottom: 36px; text-decoration: none;
}
.sb-icon {
    width: 40px; height: 40px; background: var(--orange); border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #fff; font-size: 18px; flex-shrink: 0;
    box-shadow: 0 4px 14px rgba(255,69,0,.4);
}
.sb-brand-name {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 20px; font-weight: 900; color: #fff; letter-spacing: 1px;
}
.sb-brand-sub { font-size: 9px; color: #4B5563; font-weight: 700; text-transform: uppercase; }

.sb-section-label {
    font-size: 10px; font-weight: 800; text-transform: uppercase;
    color: #374151; letter-spacing: .8px; padding: 0 10px; margin: 22px 0 8px;
}
.sb-link {
    display: flex; align-items: center; gap: 12px;
    color: #6B7280; text-decoration: none;
    padding: 10px 12px; border-radius: 10px; margin-bottom: 2px;
    font-size: 13px; font-weight: 600;
    transition: background .2s, color .2s;
    position: relative;
}
.sb-link .sb-icon-wrap {
    width: 32px; height: 32px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 13px; transition: .2s; flex-shrink: 0;
    background: rgba(255,255,255,.04);
}
.sb-link:hover { color: #E5E7EB; }
.sb-link:hover .sb-icon-wrap { background: rgba(255,255,255,.08); }
.sb-link.active { color: #fff; background: rgba(255,69,0,.1); }
.sb-link.active .sb-icon-wrap { background: var(--orange); color: #fff; }
.sb-link .badge {
    margin-left: auto; background: var(--orange); color: #fff;
    font-size: 10px; font-weight: 800; padding: 2px 7px; border-radius: 20px;
}

.sb-bottom { margin-top: auto; }
.sb-user {
    display: flex; align-items: center; gap: 10px;
    background: rgba(255,255,255,.04);
    border-radius: 12px; padding: 12px;
    border: 1px solid rgba(255,255,255,.06);
}
.sb-avatar {
    width: 36px; height: 36px; background: var(--orange); border-radius: 50%;
    display: flex; align-items: center; justify-content: center; color: #fff; font-size: 14px;
    flex-shrink: 0;
}
.sb-user-name { font-size: 13px; font-weight: 800; color: #E5E7EB; line-height: 1.1; }
.sb-user-role { font-size: 10px; color: var(--orange); font-weight: 700; }
.sb-logout {
    margin-left: auto; color: #4B5563; font-size: 13px; transition: .2s; cursor: pointer; text-decoration: none;
}
.sb-logout:hover { color: var(--red); }

/* ═══════════════════════════════
   MAIN
═══════════════════════════════ */
.main { margin-left: var(--sidebar-w); flex: 1; display: flex; flex-direction: column; min-height: 100vh; }

/* ═══════════════════════════════
   TOPBAR
═══════════════════════════════ */
.topbar {
    background: var(--card-bg);
    height: var(--topbar-h);
    padding: 0 40px;
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid var(--border);
    position: sticky; top: 0; z-index: 100;
    box-shadow: 0 1px 0 rgba(0,0,0,.04);
}
.topbar-left { display: flex; flex-direction: column; }
.topbar-title { font-family: 'Barlow Condensed', sans-serif; font-size: 26px; font-weight: 900; color: var(--text); letter-spacing: -.5px; line-height: 1; }
.topbar-date { font-size: 12px; color: var(--muted); font-weight: 600; margin-top: 2px; }
.topbar-right { display: flex; align-items: center; gap: 16px; }

.topbar-btn {
    width: 38px; height: 38px; border-radius: 10px; background: var(--bg);
    border: 1px solid var(--border); display: flex; align-items: center; justify-content: center;
    color: var(--muted); cursor: pointer; font-size: 14px; text-decoration: none; transition: .2s;
    position: relative;
}
.topbar-btn:hover { border-color: var(--orange); color: var(--orange); }
.notif-dot { position: absolute; top: 7px; right: 7px; width: 7px; height: 7px; background: var(--orange); border-radius: 50%; border: 2px solid #fff; }

.dropdown-wrap { position: relative; }
.topbar-user {
    display: flex; align-items: center; gap: 10px;
    background: var(--bg); border: 1px solid var(--border);
    padding: 6px 14px 6px 8px; border-radius: 12px; cursor: pointer; transition: .2s;
}
.topbar-user:hover { border-color: var(--orange); }
.t-avatar { width: 32px; height: 32px; background: var(--orange); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 13px; }
.t-name { font-size: 13px; font-weight: 800; color: var(--text); line-height: 1.1; }
.t-role { font-size: 10px; color: var(--orange); font-weight: 700; }
.t-chevron { color: var(--muted); font-size: 10px; margin-left: 4px; }

.dropdown-menu {
    display: none; position: absolute; right: 0; top: calc(100% + 8px);
    background: #fff; min-width: 180px; border-radius: 12px;
    border: 1px solid var(--border); box-shadow: 0 15px 40px rgba(0,0,0,.12);
    overflow: hidden; padding: 8px 0; z-index: 999;
}
.dropdown-wrap:hover .dropdown-menu { display: block; }
.dd-item {
    display: flex; align-items: center; gap: 10px;
    padding: 11px 16px; color: #444; text-decoration: none;
    font-size: 13px; font-weight: 700; transition: .15s;
}
.dd-item:hover { background: #FFF7ED; color: var(--orange); }
.dd-item i { font-size: 14px; width: 18px; text-align: center; }
.dd-divider { border: none; border-top: 1px solid #F3F4F6; margin: 4px 0; }

/* ═══════════════════════════════
   CONTENT
═══════════════════════════════ */
.content { padding: 32px 40px; flex: 1; }

/* ── WELCOME BANNER ── */
.welcome-banner {
    background: linear-gradient(135deg, #0D1117 0%, #1a1a2e 100%);
    border-radius: 20px; padding: 32px 36px;
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 28px; overflow: hidden; position: relative;
    border: 1px solid rgba(255,69,0,.15);
}
.wb-deco {
    position: absolute; right: -30px; top: -30px;
    width: 220px; height: 220px; border-radius: 50%;
    background: radial-gradient(circle, rgba(255,69,0,.18) 0%, transparent 70%);
}
.wb-deco2 {
    position: absolute; right: 120px; bottom: -40px;
    width: 160px; height: 160px; border-radius: 50%;
    background: radial-gradient(circle, rgba(255,69,0,.08) 0%, transparent 70%);
}
.wb-text { position: relative; z-index: 1; }
.wb-greeting { font-size: 13px; color: #6B7280; font-weight: 700; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .8px; }
.wb-name { font-family: 'Barlow Condensed', sans-serif; font-size: 32px; font-weight: 900; color: #fff; letter-spacing: -.5px; }
.wb-sub { font-size: 14px; color: #6B7280; margin-top: 4px; }
.wb-icon { position: relative; z-index: 1; }
.wb-icon i { font-size: 64px; color: rgba(255,69,0,.25); }

/* ── STAT GRID ── */
.stat-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 18px; margin-bottom: 28px; }

.stat-card {
    background: var(--card-bg); border-radius: 16px; padding: 22px 24px;
    border: 1px solid var(--border); position: relative; overflow: hidden;
    transition: transform .2s, box-shadow .2s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.08); }
.stat-card::before {
    content: ''; position: absolute; top: 0; left: 0;
    width: 4px; height: 100%; border-radius: 4px 0 0 4px;
}
.sc-blue::before   { background: var(--blue); }
.sc-green::before  { background: var(--green); }
.sc-orange::before { background: var(--orange); }
.sc-purple::before { background: var(--purple); }

.stat-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.stat-icon-wrap {
    width: 42px; height: 42px; border-radius: 11px;
    display: flex; align-items: center; justify-content: center; font-size: 17px;
}
.si-blue   { background: rgba(59,130,246,.1);  color: var(--blue); }
.si-green  { background: rgba(16,185,129,.1);  color: var(--green); }
.si-orange { background: rgba(255,69,0,.1);    color: var(--orange); }
.si-purple { background: rgba(139,92,246,.1);  color: var(--purple); }

.stat-trend { font-size: 11px; font-weight: 800; display: flex; align-items: center; gap: 3px; }
.trend-up   { color: var(--green); }
.trend-down { color: var(--red); }

.stat-value { font-family: 'Barlow Condensed', sans-serif; font-size: 32px; font-weight: 900; color: var(--text); line-height: 1; margin-bottom: 4px; }
.stat-label { font-size: 12px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .5px; }

/* ── GRID LAYOUT ── */
.dashboard-grid { display: grid; grid-template-columns: 1fr 320px; gap: 22px; }
@media(max-width:1100px){ .dashboard-grid { grid-template-columns: 1fr; } }

/* ── CARD ── */
.card {
    background: var(--card-bg); border-radius: 18px; border: 1px solid var(--border);
    overflow: hidden;
}
.card-header { padding: 22px 26px; border-bottom: 1px solid var(--border); display: flex; align-items: center; justify-content: space-between; }
.card-title { font-size: 15px; font-weight: 800; color: var(--text); display: flex; align-items: center; gap: 8px; }
.card-title i { color: var(--orange); font-size: 14px; }
.card-badge { background: var(--orange-lt); color: var(--orange); font-size: 11px; font-weight: 800; padding: 4px 10px; border-radius: 20px; }
.card-link { font-size: 12px; font-weight: 700; color: var(--orange); text-decoration: none; display: flex; align-items: center; gap: 4px; }
.card-link:hover { text-decoration: underline; }
.card-body { padding: 22px 26px; }

/* ── BOOKING TABLE ── */
.booking-table { width: 100%; border-collapse: collapse; }
.booking-table th { padding: 10px 12px; font-size: 10px; font-weight: 800; color: var(--muted); text-transform: uppercase; letter-spacing: .6px; border-bottom: 2px solid var(--bg); text-align: left; }
.booking-table td { padding: 13px 12px; font-size: 13px; border-bottom: 1px solid var(--bg); vertical-align: middle; }
.booking-table tr:last-child td { border-bottom: none; }
.booking-table tbody tr { transition: background .15s; }
.booking-table tbody tr:hover td { background: #FAFAFA; }

.cust-name { font-weight: 700; color: var(--text); }
.cust-detail { font-size: 11px; color: var(--muted); font-weight: 600; }

.status-pill { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .3px; }
.sp-confirmed { background: rgba(16,185,129,.1); color: var(--green); }
.sp-pending   { background: rgba(245,158,11,.1); color: #D97706; }
.sp-cancelled { background: rgba(239,68,68,.1);  color: var(--red); }

.price-col { font-weight: 800; font-size: 13px; color: var(--text); }

/* ── QUICK LINKS ── */
.quick-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
.quick-card {
    background: var(--bg); border: 1px solid var(--border); border-radius: 12px;
    padding: 16px; text-decoration: none; text-align: center; transition: .2s;
    display: flex; flex-direction: column; align-items: center; gap: 8px;
}
.quick-card:hover { border-color: var(--orange); background: var(--orange-lt); transform: translateY(-2px); }
.quick-card i { font-size: 22px; }
.quick-card span { font-size: 11px; font-weight: 800; text-transform: uppercase; letter-spacing: .4px; }

/* ── ACTIVITY ── */
.activity-item { display: flex; align-items: flex-start; gap: 12px; padding: 14px 0; border-bottom: 1px solid var(--bg); }
.activity-item:last-child { border-bottom: none; }
.act-dot-wrap { display: flex; flex-direction: column; align-items: center; flex-shrink: 0; }
.act-dot { width: 10px; height: 10px; border-radius: 50%; margin-top: 3px; }
.act-line { width: 1px; flex: 1; background: var(--border); margin-top: 4px; min-height: 20px; }
.act-text { font-size: 13px; color: var(--text); font-weight: 600; line-height: 1.4; }
.act-time { font-size: 11px; color: var(--muted); margin-top: 3px; font-weight: 600; }

/* Gaya Jam Digital Elegan */
#clock-display {
    display: flex;
    align-items: center;
    gap: 20px; /* Jarak antara jam dan tanggal */
    padding: 5px 10px;
}

.clock-time {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 28px; /* Ukuran angka lebih mantap */
    font-weight: 900;
    color: #FF4500; /* Oranye murni */
    display: flex;
    align-items: center;
    gap: 8px;
    line-height: 1;
}

.clock-colon {
    color: #FF4500;
    opacity: 0.5; /* Titik dua sedikit lebih tipis/transparan seperti di gambar */
    animation: blink 1s infinite;
}

.clock-divider {
    width: 1.5px;
    height: 30px;
    background-color: #E5E7EB; /* Garis tipis abu-abu */
}

.clock-date {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 16px;
    font-weight: 700;
    color: #6B7280; /* Abu-abu profesional */
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
</style>
</head>
<body>

<!-- ═══ SIDEBAR ═══ -->
<aside class="sidebar">
    <a href="dashboard.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">Management System</div>
        </div>
    </a>

    <div class="sb-section-label">Menu Utama</div>
    <nav>
        <a href="dashboard.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>
            Dashboard
        </a>
        <a href="#" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>
            Booking
            <span class="badge">3</span>
        </a>

        <?php if ($role === 'karyawan'): ?>
        <a href="master/lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>
            Lapangan
        </a>
        <a href="master/customer.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>
            Customer
        </a>
        <a href="master/promo.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div>
            Promo
        </a>
        <?php endif; ?>

        <?php if ($role === 'pemilik'): ?>
        <div class="sb-section-label">Owner Only</div>
        <a href="master/akun.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-user-shield"></i></div>
            Kelola Data Akun
        </a>
        <a href="master/karyawan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-user-tie"></i></div>
            Kelola Data Karyawan
        </a>
        <a href="master/supplier.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-truck-fast"></i></div>
            Kelola Alat
        </a>
        <a href="laporan/omzet.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-chart-line"></i></div>
            Laporan
        </a>
        <?php endif; ?>
    </nav>

    <div class="sb-section-label">Akun</div>
    <a href="profile.php" class="sb-link">
        <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>
        Profil Saya
    </a>

    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar"><i class="fa-solid fa-user"></i></div>
            <div>
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                <div class="sb-user-role"><?= ($role === 'pemilik') ? 'PEMILIK' : strtoupper($role) ?></div>
            </div>
            <a href="logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>

<!-- ═══ MAIN ═══ -->
<main class="main">

    <!-- TOPBAR -->
    <header class="topbar">
        <div class="topbar-date" id="clock-display">
            <div class="clock-time">
                <span id="h">00</span>
                <span class="clock-colon">:</span>
                <span id="m">00</span>
                <span class="clock-colon">:</span>
                <span id="s">00</span>
            </div>
            <div class="clock-divider"></div>
            <div class="clock-date" id="full-date">
                MEMUAT TANGGAL...
            </div>
        </div>
        <div class="topbar-right">
            <a href="#" class="topbar-btn"><i class="fa-solid fa-magnifying-glass"></i></a>
            <a href="#" class="topbar-btn">
                <i class="fa-solid fa-bell"></i>
                <span class="notif-dot"></span>
            </a>
            <div class="dropdown-wrap">
                <div class="topbar-user">
                    <div class="t-avatar"><i class="fa-solid fa-user"></i></div>
                    <div>
                        <div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                        <div class="t-role"><?= ($role === 'pemilik') ? 'PEMILIK' : strtoupper($role) ?></div>
                    </div>
                    <i class="fa-solid fa-chevron-down t-chevron"></i>
                </div>
                <div class="dropdown-menu">
                    <a href="profile.php" class="dd-item"><i class="fa-solid fa-id-badge"></i> Profil Saya</a>
                    <hr class="dd-divider">
                    <a href="logout.php" class="dd-item" style="color:var(--red);"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
                </div>
            </div>
        </div>
    </header>

    <!-- CONTENT -->
    <div class="content">

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="wb-deco"></div>
            <div class="wb-deco2"></div>
            <div class="wb-text">
                <div class="wb-greeting">Selamat Datang Kembali</div>
                <div class="wb-name"><?= strtoupper(htmlspecialchars($nama)) ?> 👋</div>
                <div class="wb-sub">Kendalikan sistem HoopBall dengan efisien hari ini.</div>
            </div>
            <div class="wb-icon"><i class="fa-solid fa-basketball"></i></div>
        </div>

        <!-- Stats -->
        <div class="stat-grid">
            <div class="stat-card sc-blue">
                <div class="stat-header">
                    <div class="stat-icon-wrap si-blue"><i class="fa-solid fa-calendar-day"></i></div>
                    <div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> 12%</div>
                </div>
                <div class="stat-value"><?= $total_booking ?></div>
                <div class="stat-label">Booking Hari Ini</div>
            </div>
            <div class="stat-card sc-green">
                <div class="stat-header">
                    <div class="stat-icon-wrap si-green"><i class="fa-solid fa-money-bill-wave"></i></div>
                    <div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> 8%</div>
                </div>
                <div class="stat-value" style="font-size:22px;"><?= rupiahFormat($pendapatan_hari) ?></div>
                <div class="stat-label">Pendapatan Hari Ini</div>
            </div>
            <div class="stat-card sc-orange">
                <div class="stat-header">
                    <div class="stat-icon-wrap si-orange"><i class="fa-solid fa-users"></i></div>
                    <div class="stat-trend trend-up"><i class="fa-solid fa-arrow-up"></i> 3%</div>
                </div>
                <div class="stat-value"><?= $member_aktif ?></div>
                <div class="stat-label">Member Aktif</div>
            </div>
            <div class="stat-card sc-purple">
                <div class="stat-header">
                    <div class="stat-icon-wrap si-purple"><i class="fa-solid fa-box"></i></div>
                    <div class="stat-trend trend-down"><i class="fa-solid fa-arrow-down"></i> 2</div>
                </div>
                <div class="stat-value"><?= $stok_alat ?></div>
                <div class="stat-label">Stok Alat</div>
            </div>
        </div>

        <!-- Main Grid -->
        <div class="dashboard-grid">

            <!-- Booking Table -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-list-check"></i> Booking Terbaru</div>
                    <div style="display:flex; align-items:center; gap:12px;">
                        <span class="card-badge"><?= count($recent_bookings) ?> data</span>
                        <a href="booking.php" class="card-link">Lihat Semua <i class="fa-solid fa-arrow-right" style="font-size:10px;"></i></a>
                    </div>
                </div>
                <div style="overflow-x:auto;">
                    <table class="booking-table">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Lapangan & Jam</th>
                                <th>Status</th>
                                <th>Harga</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($recent_bookings as $b): ?>
                            <tr>
                                <td>
                                    <div class="cust-name"><?= htmlspecialchars($b['nama']) ?></div>
                                </td>
                                <td>
                                    <div class="cust-name"><?= $b['lapangan'] ?></div>
                                    <div class="cust-detail"><i class="fa-regular fa-clock"></i> <?= $b['jam'] ?></div>
                                </td>
                                <td>
                                    <?php
                                    $cls = ['confirmed'=>'sp-confirmed','pending'=>'sp-pending','cancelled'=>'sp-cancelled'];
                                    $lbl = ['confirmed'=>'Terkonfirmasi','pending'=>'Menunggu','cancelled'=>'Dibatalkan'];
                                    ?>
                                    <span class="status-pill <?= $cls[$b['status']] ?>">
                                        <?= $lbl[$b['status']] ?>
                                    </span>
                                </td>
                                <td class="price-col"><?= rupiahFormat($b['harga']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Right Column -->
            <div style="display:flex; flex-direction:column; gap:20px;">

                <!-- Quick Access -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-bolt"></i> Akses Cepat</div>
                    </div>
                    <div class="card-body">
                        <div class="quick-grid">
                            <a href="booking.php?action=new" class="quick-card" style="color:var(--blue);">
                                <i class="fa-solid fa-plus-circle"></i>
                                <span>Booking Baru</span>
                            </a>
                            <a href="master/lapangan.php" class="quick-card" style="color:var(--green);">
                                <i class="fa-solid fa-layer-group"></i>
                                <span>Lapangan</span>
                            </a>
                            <a href="master/promo.php" class="quick-card" style="color:var(--orange);">
                                <i class="fa-solid fa-tags"></i>
                                <span>Promo</span>
                            </a>
                            <a href="laporan/omzet.php" class="quick-card" style="color:var(--purple);">
                                <i class="fa-solid fa-chart-line"></i>
                                <span>Laporan</span>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Aktivitas Terkini -->
                <div class="card">
                    <div class="card-header">
                        <div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Aktivitas</div>
                    </div>
                    <div class="card-body">
                        <?php
                        $activities = [
                            ['dot'=>'var(--green)', 'text'=>'Booking #BK-042 dikonfirmasi','time'=>'2 menit lalu'],
                            ['dot'=>'var(--orange)','text'=>'Karyawan baru ditambahkan: Rina','time'=>'15 menit lalu'],
                            ['dot'=>'var(--blue)',  'text'=>'Pembayaran diterima Rp 200.000','time'=>'1 jam lalu'],
                            ['dot'=>'var(--red)',   'text'=>'Booking #BK-039 dibatalkan','time'=>'2 jam lalu'],
                            ['dot'=>'var(--green)', 'text'=>'Promo WEEKEND10 diaktifkan','time'=>'3 jam lalu'],
                        ];
                        foreach($activities as $i => $act):
                        ?>
                        <div class="activity-item">
                            <div class="act-dot-wrap">
                                <div class="act-dot" style="background:<?= $act['dot'] ?>;"></div>
                                <?php if($i < count($activities)-1): ?><div class="act-line"></div><?php endif; ?>
                            </div>
                            <div>
                                <div class="act-text"><?= $act['text'] ?></div>
                                <div class="act-time"><i class="fa-regular fa-clock"></i> <?= $act['time'] ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div><!-- /right col -->
        </div><!-- /dashboard-grid -->
    </div><!-- /content -->
</main>

<script>
function updateClock() {
    const now = new Date();
    
    // Ambil Elemen
    const hDisp = document.getElementById('h');
    const mDisp = document.getElementById('m');
    const sDisp = document.getElementById('s');
    const dDisp = document.getElementById('full-date');
    
    // Format Jam
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    const s = String(now.getSeconds()).padStart(2, '0');
    
    // Update Jam (Hanya jika angka berubah untuk performa)
    if(hDisp.innerText !== h) hDisp.innerText = h;
    if(mDisp.innerText !== m) mDisp.innerText = m;
    sDisp.innerText = s;

    // Format Tanggal Indonesia
    const days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    const months = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    
    const dayName = days[now.getDay()];
    const date = now.getDate();
    const monthName = months[now.getMonth()];
    const year = now.getFullYear();
    
    const dateString = `${dayName}, ${date} ${monthName} ${year}`;
    if(dDisp.innerText !== dateString) dDisp.innerText = dateString;
}

// Jalankan realtime
setInterval(updateClock, 1000);
updateClock();
</script>

</body>
</html>