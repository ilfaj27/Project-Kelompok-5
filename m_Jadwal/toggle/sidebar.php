<aside class="sidebar">
    <a href="../dashboard_karyawan.php" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">MANAGEMENT SYSTEM</div>
        </div>
    </a>

    <div class="sb-section-label">Menu Utama</div>
    <nav>
        <a href="../view_admin.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>
            Dashboard
        </a>
        <a href="../booking.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>
            Booking
        </a>
        <a href="../master/lapangan.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>
            Lapangan
        </a>
        <a href="../master/customer.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>
            Customer
        </a>
        <a href="../master/promo.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tag"></i></div>
            Promo
        </a>
        <a href="../m_Alat/index.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-boxes-stacked"></i></div>
            Alat
        </a>
        <!-- MENU JADWAL AKTIF -->
        <a href="index.php" class="sb-link active">
            <div class="sb-icon-wrap"><i class="fa-solid fa-clock"></i></div>
            Jadwal
        </a>
    </nav>

    <div class="sb-section-label">Akun</div>
    <nav>
        <a href="../profile.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>
            Profil Saya
        </a>
        <a href="../riwayat.php" class="sb-link">
            <div class="sb-icon-wrap"><i class="fa-solid fa-clock-rotate-left"></i></div>
            Riwayat
        </a>
    </nav>

    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar"><i class="fa-solid fa-user"></i></div>
            <div>
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($_SESSION['nama'] ?? 'USER')) ?></div>
                <div class="sb-user-role">KARYAWAN</div>
            </div>
            <a href="../logout.php" class="sb-logout" title="Keluar"><i class="fa-solid fa-right-from-bracket"></i></a>
        </div>
    </div>
</aside>