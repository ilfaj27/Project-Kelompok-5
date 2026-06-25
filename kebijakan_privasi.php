<?php
session_start();
// Menjaga status intro agar tetap konsisten dengan alur halaman utama
if (!isset($_SESSION['intro_done'])) {
    header("Location: intro.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kebijakan Privasi - HoopBall</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
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

        @keyframes fadeInDown {
            from { opacity: 0; transform: translateY(-30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ═══════════════════════════════════════════
           NAVBAR (Sama dengan Index)
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

        .logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            gap: 10px;
            transition: transform 0.3s ease;
        }
        .logo:hover { transform: scale(1.05); }
        .logo img {
            height: 70px;
            width: auto;
            object-fit: contain;
            transition: transform 0.5s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .logo:hover img { transform: rotate(5deg) scale(1.1); }

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
        }
        .nav-menu a:hover { color: #1C1C1E; transform: translateY(-2px); }

        .nav-btns { display: flex; align-items: center; gap: 16px; }

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

        /* PROFILE DROPDOWN */
        .profile-dropdown-container { position: relative; display: inline-block; }
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
        }
        .profile-icon-orange { font-size: 20px; color: var(--orange); }
        .profile-trigger-name { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; font-weight: 600; color: #1C1C1E; }
        .chevron-icon { font-size: 11px; color: var(--orange); transition: transform 0.3s ease; }
        .profile-dropdown-container.active .chevron-icon { transform: rotate(180deg); }
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
        .profile-dropdown-menu.show { display: block; }
        .profile-dropdown-header { display: flex; flex-direction: column; padding: 4px 10px 8px; }
        .profile-dropdown-header .user-fullname { font-size: 15px; font-weight: 700; color: #FFFFFF; }
        .profile-dropdown-header .user-role { font-size: 10px; font-weight: 600; color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; }
        .profile-dropdown-divider { height: 1px; background: #2D3139; margin: 10px 0; }
        .profile-dropdown-list { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 4px; }
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
        .profile-dropdown-list li a:hover { background: rgba(255, 255, 255, 0.06); color: #FFFFFF; }
        .profile-dropdown-list li a i { font-size: 14px; width: 20px; text-align: center; color: #94A3B8; }
        .profile-dropdown-list li a:hover i { color: var(--orange); }
        .profile-dropdown-list li a.text-danger { color: #EF4444; }
        .profile-dropdown-list li a.text-danger i { color: #EF4444; }
        .profile-dropdown-list li a.text-danger:hover { background: rgba(239, 68, 68, 0.1); }

        /* ═══════════════════════════════════════════
           CONTENT AREA (Kebijakan Privasi)
           ═══════════════════════════════════════════ */
        .privacy-section {
            padding: 80px 8%;
            background-color: var(--bg-light);
        }

        .privacy-wrapper {
            max-width: 900px;
            margin: 0 auto;
            background: #FFFFFF;
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 50px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.03);
        }

        .privacy-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .privacy-header h1 {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 42px;
            font-weight: 900;
            color: var(--dark);
            margin-bottom: 12px;
            text-transform: uppercase;
            letter-spacing: -0.5px;
        }

        .privacy-header p {
            color: var(--text-muted);
            font-size: 14px;
        }

        .privacy-content {
            font-size: 15px;
            color: var(--text-dark);
            line-height: 1.8;
        }

        .privacy-group {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .privacy-group:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .privacy-group h3 {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 18px;
            font-weight: 700;
            color: var(--orange);
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .privacy-group p {
            margin-bottom: 15px;
        }

        .privacy-group ul {
            padding-left: 20px;
            margin-bottom: 15px;
        }

        .privacy-group li {
            margin-bottom: 8px;
        }

        /* ═══════════════════════════════════════════
           FOOTER (Sama dengan Index)
           ═══════════════════════════════════════════ */
        footer { background: #0F172A; color: #94A3B8; padding: 80px 8% 40px; }
        .footer-grid { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr; gap: 40px; margin-bottom: 60px; }
        .footer-brand .logo { display: flex; align-items: center; gap: 12px; margin-bottom: 24px; }
        .footer-brand .logo img { width: auto; height: 90px; object-fit: contain; filter: drop-shadow(0 3px 6px rgba(0,0,0,0.3)); }
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

        /* RESPONSIVE */
        @media (max-width: 992px) {
            .navbar { padding: 0 40px; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
            .nav-menu { display: none; }
            .privacy-wrapper { padding: 30px; }
        }
        @media (max-width: 576px) {
            .footer-grid { grid-template-columns: 1fr; }
            .privacy-header h1 { font-size: 32px; }
        }
    </style>
</head>
<body>

<!-- ═══════════════════════════════════════════
     NAVBAR
     ═══════════════════════════════════════════ -->
<nav class="navbar">
    <a href="index.php" class="logo"><img src="asset/image/logo2.png" alt="HoopBall"></a>
    <div class="nav-menu">
        <a href="customer/booking_customer.php">Booking</a>
        <a href="customer/pembatalan_customer.php">Pembatalan</a>
        <a href="customer/langganan_customer.php">Member</a>
        <a href="customer/pembelian_alat.php">Pembelian</a>
    </div>
    <div class="nav-btns">
    <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true): ?>
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
        <a href="login/login.php" class="btn-login">Masuk</a>
        <a href="login/register.php" class="btn-join">Daftar Sekarang</a>
    <?php endif; ?>
</div>
</nav>

<!-- ═══════════════════════════════════════════
     CONTENT
     ═══════════════════════════════════════════ -->
<section class="privacy-section">
    <div class="privacy-wrapper">
        <div class="privacy-header">
            <h1>Kebijakan Privasi</h1>
            <p>Bagaimana HoopBall mengumpulkan, melindungi, dan memperlakukan informasi pribadi Anda.</p>
        </div>
        
        <div class="privacy-content">
            <div class="privacy-group">
                <h3><i class="fa-solid fa-user-shield"></i> 1. Pengumpulan Informasi</h3>
                <p>Kami mengumpulkan beberapa informasi pribadi Anda ketika Anda mendaftar akun atau melakukan reservasi lapangan di HoopBall:</p>
                <ul>
                    <li><strong>Informasi Identitas:</strong> Nama lengkap, alamat email, serta nomor telepon aktif.</li>
                    <li><strong>Riwayat Pemesanan:</strong> Detail pemesanan lapangan, langganan member, pembelian alat basket, serta nominal transaksi pembayaran Anda.</li>
                </ul>
            </div>

            <div class="privacy-group">
                <h3><i class="fa-solid fa-key"></i> 2. Penggunaan Informasi Pribadi</h3>
                <p>Informasi yang kami peroleh dari Anda digunakan semata-mata untuk meningkatkan kualitas layanan HoopBall, meliputi:</p>
                <ul>
                    <li>Memproses dan melacak status pemesanan lapangan serta transaksi pembayaran Anda secara akurat.</li> 
                    <li>Menyediakan layanan bantuan yang cepat ketika Anda menghubungi tim layanan pelanggan HoopBall.</li>
                </ul>
            </div>

            <div class="privacy-group">
                <h3><i class="fa-solid fa-lock"></i> 3. Perlindungan Keamanan Data</h3>
                <p>Kami menerapkan prosedur keamanan standar untuk melindungi informasi pribadi Anda dari akses yang tidak sah, penyalahgunaan, kehilangan, atau pengubahan data:</p>
                <ul>
                    <li>Database sistem disimpan pada server yang aman dan hanya dapat diakses oleh administrator sistem yang memiliki otorisasi khusus.</li>
                    <li>Kami tidak pernah menyimpan informasi kartu kredit atau detail perbankan Anda dalam database sistem kami; seluruh transaksi pembayaran diproses melalui gerbang pembayaran (payment gateway) pihak ketiga yang terenkripsi aman.</li>
                </ul>
            </div>

            <div class="privacy-group">
                <h3><i class="fa-solid fa-handshake-slash"></i> 4. Pengungkapan Kepada Pihak Ketiga</h3>
                <p>Kami sangat menghargai privasi Anda. HoopBall berkomitmen tidak akan menjual, menyewakan, membagikan, atau memperdagangkan informasi identitas pribadi Anda kepada pihak eksternal mana pun di luar sistem aplikasi kami.</p>
            </div>

            <div class="privacy-group">
                <h3><i class="fa-solid fa-circle-check"></i> 5. Persetujuan Pengguna</h3>
                <p>Dengan tetap mengakses, mendaftar, dan memesan lapangan melalui platform HoopBall, Anda menyatakan telah membaca, memahami, serta memberikan persetujuan penuh terhadap ketentuan Kebijakan Privasi yang tercantum di halaman ini.</p>
            </div>
        </div>
    </div>
</section>

<!-- ═══════════════════════════════════════════
     FOOTER
     ═══════════════════════════════════════════ -->
<footer id="tentang-kami">
    <div class="footer-grid">
        <div class="footer-brand">
            <a href="index.php" class="logo"><img src="asset/image/logo2.png" alt="HoopBall"></a>
            <p>Platform penyewaan lapangan basket online yang mudah, cepat, dan terpercaya untuk mempermudah hobi olahraga Anda.</p>
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
                <div class="contact-item"><i class="fa-solid fa-phone"></i><span>021-8997-xxxx</span></div>
                <div class="contact-item"><i class="fa-solid fa-envelope"></i><span>info@hoopball.id</span></div>
                <div class="contact-item"><i class="fa-solid fa-location-dot"></i><span>Politeknik Astra, Delta Silicon II, Cibatu, Cikarang Selatan, Bekasi, Jawa Barat 17530</span></div>
            </div>
        </div>
        <div class="footer-col">
            <h4>Tautan</h4>
            <ul class="footer-links">
                <li><a href="index.php#beranda">Beranda</a></li>
                <li><a href="index.php#lapangan">Lapangan</a></li>
                <li><a href="index.php#jadwal">Jadwal</a></li>
                <li><a href="index.php#alat-basket">Alat Basket</a></li>
            </ul>
        </div>
        <div class="footer-col">
            <h4>Informasi</h4>
            <ul class="footer-links">
                <li><a href="#">Cara Pemesanan</a></li>
                <li><a href="syarat_ketentuan.php">Syarat & Ketentuan</a></li>
                <li><a href="kebijakan_privasi.php">Kebijakan Privasi</a></li>
                <li><a href="#">FAQ</a></li>
            </ul>
        </div>
    </div>
    <div class="footer-bottom"><p>&copy; <?php echo date('Y'); ?> HoopBall. All rights reserved.</p></div>
</footer>

<!-- ═══════════════════════════════════════════
     SCRIPTS
     ═══════════════════════════════════════════ -->
<script>
// Dropdown Profil Toggle
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
            if (dropdownContainer && !dropdownContainer.contains(e.target)) {
                profileDropdownMenu.classList.remove('show');
                dropdownContainer.classList.remove('active');
            }
        });
    }
});
</script>
</body>
</html>