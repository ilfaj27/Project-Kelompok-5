<?php
include 'includes/config.php';

// Ambil data statistik dinamis untuk meyakinkan pengunjung
$sql_lap = "SELECT COUNT(*) as total FROM Lapangan WHERE Status = 1";
$q_lap = sqlsrv_query($conn, $sql_lap);
$d_lap = sqlsrv_fetch_array($q_lap, SQLSRV_FETCH_ASSOC);

$sql_prm = "SELECT COUNT(*) as total FROM Promo WHERE Tanggal_Selesai >= GETDATE()";
$q_prm = sqlsrv_query($conn, $sql_prm);
$d_prm = sqlsrv_fetch_array($q_prm, SQLSRV_FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HoopBall - Court Rental & Basketball System</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root { --orange: #FF4500; --dark: #000; --gray: #888; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: #000; color: #fff; scroll-behavior: smooth; }

        /* NAVBAR */
        .navbar { 
            position: fixed; top: 0; width: 100%; padding: 25px 80px; 
            display: flex; justify-content: space-between; align-items: center; 
            z-index: 1000; transition: 0.5s; background: rgba(0,0,0,0.5); backdrop-filter: blur(10px);
        }
        .logo { display: flex; align-items: center; gap: 10px; font-weight: 900; font-size: 24px; color: #fff; text-decoration: none; }
        .logo i { color: var(--orange); transition: 0.8s ease-in-out; display: inline-block; }
        .logo:hover i { transform: rotate(360deg); }

        .nav-menu { display: flex; gap: 30px; }
        .nav-menu a { color: #fff; text-decoration: none; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; }
        .nav-btns { display: flex; gap: 15px; }
        .btn-login { color: #fff; text-decoration: none; font-weight: 800; font-size: 13px; border: 1px solid #333; padding: 10px 25px; border-radius: 5px; }
        .btn-join { background: var(--orange); color: #fff; text-decoration: none; font-weight: 800; font-size: 13px; padding: 10px 25px; border-radius: 5px; }

        /* HERO SECTION */
        .hero { 
            height: 100vh; background: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.9)), url('https://images.unsplash.com/photo-1546519638-68e109498ffc?q=80&w=2000');
            background-size: cover; background-position: center; display: flex; align-items: center; padding: 0 80px;
        }
        .hero-content { max-width: 800px; }
        .hero-tag { color: var(--orange); font-weight: 900; font-size: 14px; letter-spacing: 4px; margin-bottom: 20px; text-transform: uppercase; }
        .hero-content h1 { font-size: 90px; font-weight: 900; line-height: 0.9; margin-bottom: 30px; text-transform: uppercase; }
        .hero-content p { color: var(--gray); font-size: 18px; line-height: 1.6; margin-bottom: 40px; max-width: 600px; }

        /* PROCESS SECTION */
        .process { padding: 100px 80px; background: #080808; }
        .section-title { font-size: 45px; font-weight: 900; text-transform: uppercase; margin-bottom: 60px; text-align: center; }
        .section-title span { color: var(--orange); }
        .grid-process { display: grid; grid-template-columns: repeat(4, 1fr); gap: 30px; }
        .proc-card { text-align: center; padding: 40px 20px; background: #111; border-radius: 10px; border-bottom: 3px solid #222; transition: 0.3s; }
        .proc-card:hover { border-color: var(--orange); transform: translateY(-10px); }
        .proc-card i { font-size: 40px; color: var(--orange); margin-bottom: 20px; }
        .proc-card h3 { margin-bottom: 15px; font-size: 18px; text-transform: uppercase; }
        .proc-card p { color: #555; font-size: 14px; line-height: 1.5; }

        /* FEATURES SECTION */
        .features { padding: 100px 80px; display: flex; gap: 50px; align-items: center; }
        .feat-img { flex: 1; border-radius: 20px; overflow: hidden; height: 500px; }
        .feat-img img { width: 100%; height: 100%; object-fit: cover; }
        .feat-text { flex: 1; }
        .feat-item { display: flex; gap: 20px; margin-bottom: 30px; }
        .feat-item i { color: var(--orange); font-size: 28px; padding-top: 5px; } /* Ukuran logo layanan diperbesar sedikit */
        .feat-item h4 { font-size: 20px; margin-bottom: 5px; }
        .feat-item p { color: var(--gray); font-size: 14px; }

        /* STATS BAR */
        .stats-bar { display: flex; justify-content: space-around; background: var(--orange); padding: 60px 80px; color: #000; }
        .stat-box h2 { font-size: 50px; font-weight: 900; line-height: 1; }
        .stat-box p { font-weight: 800; font-size: 12px; text-transform: uppercase; }

        /* MEMBER SECTION SLIDER */
        .members { padding: 100px 0; background: #000; }
        .member-wrapper {
            display: flex; overflow-x: auto; gap: 30px; padding: 20px 80px 50px 80px;
            scroll-snap-type: x mandatory; scrollbar-width: none;
        }
        .member-wrapper::-webkit-scrollbar { display: none; }
        .member-card { min-width: 300px; background: #111; border-radius: 20px; overflow: hidden; scroll-snap-align: center; border: 1px solid #222; transition: 0.4s; }
        .member-card:hover { border-color: var(--orange); transform: translateY(-10px); }
        .member-img { width: 100%; height: 350px; overflow: hidden; }
        .member-img img { width: 100%; height: 100%; object-fit: cover; filter: grayscale(100%); transition: 0.5s; }
        .member-card:hover .member-img img { filter: grayscale(0%); scale: 1.1; }
        .member-info { padding: 25px; text-align: center; }
        .member-info h4 { font-size: 20px; font-weight: 900; color: #fff; margin-bottom: 5px; }
        .member-info p { color: var(--orange); font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: 2px; }
        .scroll-hint { text-align: center; color: #333; font-size: 12px; font-weight: 800; margin-top: -20px; text-transform: uppercase; letter-spacing: 2px; }
        .scroll-hint i { margin: 0 10px; color: var(--orange); animation: bounce 2s infinite; }
        @keyframes bounce { 0%, 100% { transform: translateX(0); } 50% { transform: translateX(10px); } }

        /* FOOTER */
        footer { padding: 80px; background: #050505; text-align: center; border-top: 1px solid #111; }
        .footer-logo { font-size: 30px; font-weight: 900; margin-bottom: 20px; display: block; text-decoration: none; color: #fff; }
        .socials { display: flex; justify-content: center; gap: 20px; margin-bottom: 30px; }
        .socials a { color: var(--gray); font-size: 20px; transition: 0.3s; }
        .socials a:hover { color: var(--orange); }
        .copy { font-size: 12px; color: #333; }

        /* SECTION LOKASI (GLOBE EARTH VERSION) */
        .globe-section {
            background: #000;
            padding: 120px 80px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            overflow: hidden;
            position: relative;
        }

        /* Container Informasi di Kiri */
        .globe-info {
            flex: 1;
            z-index: 10;
        }

        .globe-info h2 {
            font-size: 60px;
            font-weight: 900;
            line-height: 1;
            text-transform: uppercase;
            margin-bottom: 40px;
        }

        .coordinate-tag {
            font-family: 'Courier New', Courier, monospace;
            color: var(--orange);
            font-size: 14px;
            margin-bottom: 20px;
            display: block;
            letter-spacing: 2px;
        }

        /* Komponen Globe di Kanan */
        .globe-visual {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            position: relative;
        }

        .globe-sphere {
            width: 500px;
            height: 500px;
            background: #000;
            border-radius: 50%;
            position: relative;
            box-shadow: 
                inset -30px -30px 50px rgba(0,0,0,0.9),
                inset 10px 10px 70px rgba(255, 69, 0, 0.2),
                0 0 100px rgba(255, 69, 0, 0.1);
            overflow: hidden;
            border: 2px solid rgba(255, 69, 0, 0.3);
            animation: float 6s ease-in-out infinite;
        }

        /* Overlay Atmosfer Glow */
        .globe-sphere::after {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            box-shadow: inset 0 0 80px rgba(0,0,0,0.8);
            pointer-events: none;
        }

        .globe-sphere iframe {
            width: 150%; /* Diperbesar agar bisa di-masking */
            height: 150%;
            position: absolute;
            top: -25%;
            left: -25%;
            filter: grayscale(100%) contrast(120%) brightness(0.8);
            mix-blend-mode: screen; /* Membuat peta menyatu dengan gelap */
        }

        /* Animasi Mengapung */
        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(2deg); }
        }

        /* Radar Scanner Effect */
        .radar-line {
            position: absolute;
            width: 550px;
            height: 550px;
            border: 1px solid rgba(255, 69, 0, 0.1);
            border-radius: 50%;
            pointer-events: none;
        }

        .radar-line::before {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            width: 100%; height: 2px;
            background: linear-gradient(to right, transparent, var(--orange), transparent);
            transform-origin: left;
            animation: rotateRadar 4s linear infinite;
        }

        @keyframes rotateRadar {
            from { transform: rotate(0deg) translate(-50%, -50%); }
            to { transform: rotate(360deg) translate(-50%, -50%); }
        }
    </style>
</head>
<body>

    <!-- NAVBAR -->
    <nav class="navbar">
        <a href="#" class="logo"><i class="fa-solid fa-basketball"></i>HOOPBALL</a>
        <div class="nav-menu">
            <a href="#beranda">Beranda</a>
            <a href="#probis">Cara Kerja</a>
            <a href="#fitur">Layanan</a>
            <a href="#kontak">Kontak</a>
        </div>
        <div class="nav-btns">
            <a href="login.php" class="btn-login">LOGIN</a>
            <a href="register.php" class="btn-join">JOIN NOW</a>
        </div>
    </nav>

    <!-- HERO -->
    <section class="hero" id="beranda">
        <div class="hero-content">
            <div class="hero-tag">Best Arena in Cikarang</div>
            <h1>ARENA TERBAIK UNTUK <span style="color:var(--orange)">SKILL TERBAIK.</span></h1>
            <p>Sistem manajemen penyewaan lapangan basket terpadu. Booking mudah, pendaftaran member eksklusif, dan penyediaan alat basket berkualitas tinggi dalam satu platform.</p>
            <a href="register.php" class="btn-join" style="padding: 20px 50px; font-size: 16px;">MULAI BERMAIN SEKARANG <i class="fa-solid fa-arrow-right" style="margin-left:10px"></i></a>
        </div>
    </section>

    <!-- STATS -->
    <div class="stats-bar">
        <div class="stat-box"><h2><?= $d_lap['total'] ?></h2><p>Lapangan Ready</p></div>
        <div class="stat-box"><h2><?= $d_prm['total'] ?></h2><p>Promo Aktif</p></div>
        <div class="stat-box"><h2>24/7</h2><p>Sistem Terintegrasi</p></div>
        <div class="stat-box"><h2>4.9</h2><p>Rating Kepuasan</p></div>
    </div>

    <!-- PROCESS -->
    <section class="process" id="probis">
        <h2 class="section-title">PROSES <span>BOOKING</span> MUDAH</h2>
        <div class="grid-process">
            <div class="proc-card">
                <i class="fa-solid fa-user-plus"></i>
                <h3>1. Registrasi</h3>
                <p>Buat akun customer kamu untuk mendapatkan akses penuh ke fitur sistem kami.</p>
            </div>
            <div class="proc-card">
                <i class="fa-solid fa-calendar-day"></i>
                <h3>2. Pilih Jadwal</h3>
                <p>Pilih lapangan favoritmu dan sesuaikan dengan tanggal serta jam yang tersedia.</p>
            </div>
            <div class="proc-card">
                <i class="fa-solid fa-tags"></i>
                <h3>3. Pakai Promo</h3>
                <p>Gunakan kode promo yang aktif untuk mendapatkan potongan harga sewa otomatis.</p>
            </div>
            <div class="proc-card">
                <i class="fa-solid fa-check-double"></i>
                <h3>4. Konfirmasi</h3>
                <p>Dapatkan verifikasi instan dari staf kami dan lapangan siap untuk digunakan.</p>
            </div>
        </div>
    </section>

    <!-- FEATURES -->
    <section class="features" id="fitur">
        <div class="feat-img">
            <img src="https://images.unsplash.com/photo-1504450758481-7338eba7524a?q=80&w=2000" alt="Arena">
        </div>
        <div class="feat-text">
            <h2 class="section-title" style="text-align:left; margin-bottom:40px;">LEBIH DARI SEKEDAR <span>SEWA.</span></h2>
            
            <!-- BAGIAN LANGGANAN DENGAN LOGO MAHKOTA -->
            <div class="feat-item">
                <i class="fa-solid fa-crown"></i> 
                <div>
                    <h4>Langganan Member</h4>
                    <p>Daftar sebagai member untuk menikmati harga khusus, durasi langganan fleksibel, dan akses prioritas pemesanan.</p>
                </div>
            </div>

            <div class="feat-item">
                <i class="fa-solid fa-basket-shopping"></i>
                <div>
                    <h4>Toko Alat Basket</h4>
                    <p>Butuh bola atau jersey? Beli langsung melalui sistem kami. Stok terjamin dan kualitas standar profesional.</p>
                </div>
            </div>

            <div class="feat-item">
                <i class="fa-solid fa-rotate-left"></i>
                <div>
                    <h4>Pembatalan Fleksibel</h4>
                    <p>Ada kendala mendadak? Lakukan pembatalan melalui website. Sistem refund kami jelas, cepat, dan transparan.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- MEMBER SECTION -->
    <section class="members">
        <h2 class="section-title">TIM <span>ELITE</span> KAMI</h2>
        <p class="scroll-hint"><i class="fa-solid fa-arrow-left"></i> GESER UNTUK MELIHAT <i class="fa-solid fa-arrow-right"></i></p>
        <div class="member-wrapper">
            <div class="member-card">
                <div class="member-img"><img src="https://images.unsplash.com/photo-1546519638-68e109498ffc?q=80&w=1000" alt="Member 1"></div>
                <div class="member-info"><h4>MEMBER BRONZE</h4><p>Member Bronze</p></div>
            </div>
            <div class="member-card">
                <div class="member-img"><img src="https://images.unsplash.com/photo-1504450758481-7338eba7524a?q=80&w=1000" alt="Member 2"></div>
                <div class="member-info"><h4>MEMBER SILVER</h4><p>Member Silver</p></div>
            </div>
            <div class="member-card">
                <div class="member-img"><img src="https://images.unsplash.com/photo-1544919982-b61976f0ba43?q=80&w=1000" alt="Member 3"></div>
                <div class="member-info"><h4>MEMBER GOLD</h4><p>Member Gold</p></div>
            </div>
            <div class="member-card">
                <div class="member-img"><img src="https://images.unsplash.com/photo-1574623452334-1e0ac2b3ccb4?q=80&w=1000" alt="Member 4"></div>
                <div class="member-info"><h4>MEMBER PLATINUM</h4><p>Member Platinum</p></div>
            </div>
        </div>
    </section>

    <!-- LOCATION SECTION -->
    <!-- LOCATION SECTION (SATELLITE GLOBE) -->
    <section class="globe-section" id="kontak">
        <!-- Bagian Kiri: Info Teks -->
        <div class="globe-info">
            <span class="coordinate-tag">CIKARANG </span>
            <h2>DOMINASI <br><span style="color:var(--orange)">GLOBAL,</span> <br>LOKASI LOKAL.</h2>
            
            <div class="info-grid" style="margin-top:40px;">
                <div class="info-item">
                    <i class="fa-solid fa-satellite"></i>
                    <div class="info-text">
                        <h4>Titik Arena</h4>
                        <p>Delta Silicon II, Jl. Gaharu Blok F3, Cibatu, Cikarang Sel., Kabupaten Bekasi, Jawa Barat 17530.</p>
                    </div>
                </div>

                <div class="info-item">
                    <i class="fa-solid fa-microchip"></i>
                    <div class="info-text">
                        <h4>Fasilitas Terintegrasi</h4>
                        <p>Booking System, Smart Lighting, & Pro Store.</p>
                    </div>
                </div>
            </div>

            <a href="https://maps.app.goo.gl/FpzS6FdUWPp6kGvQ9" target="_blank" class="btn-join" style="margin-top:30px; padding: 15px 40px; display:inline-block;">
                AKTIFKAN GPS <i class="fa-solid fa-location-arrow" style="margin-left:10px"></i>
            </a>
        </div>

        <!-- Bagian Kanan: Visual Bumi/Globe -->
        <div class="globe-visual">
            <!-- Efek Garis Radar -->
            <div class="radar-line"></div>
            
            <!-- Bola Bumi -->
            <div class="globe-sphere">
                <!-- Peta Satelit (Google Maps Mode Satellite & Inverted) -->
                <iframe 
                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3965.3545986499857!2d107.14830219999999!3d-6.3481107!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x2e699b896d7fc649%3A0xe0a940b1f200d008!2sPoliteknik%20Astra!5e0!3m2!1sid!2sid!4v1780735557436!5m2!1sid!2sid" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen="" 
                    loading="lazy">
                </iframe>
            </div>
        </div>
    </section>

    <!-- FOOTER -->
    <footer id="kontak">
        <a href="#" class="footer-logo"><i class="fa-solid fa-basketball"></i> HOOPBALL</a>
        <div class="socials">
            <a href="#"><i class="fa-brands fa-instagram"></i></a>
            <a href="#"><i class="fa-brands fa-facebook"></i></a>
            <a href="#"><i class="fa-brands fa-whatsapp"></i></a>
        </div>
        <p class="copy">&copy; 2024 HoopBall Kelompok 05. Manajemen Lapangan Profesional. <br> Cikarang, Indonesia.</p>
    </footer>

</body>
</html>