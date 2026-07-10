<aside class="sidebar">
    <a href="<?= ($sidebar_folder === 'dashboard') ? 'view_admin.php' : '../dashboard/view_admin.php' ?>" class="sb-brand">
        <div class="sb-icon"><i class="fa-solid fa-basketball"></i></div>
        <div>
            <div class="sb-brand-name">HOOP BALL</div>
            <div class="sb-brand-sub">SISTEM MANAGEMEN</div>
        </div>
    </a>

    <div class="sb-section-label">Operasional</div>
    <nav>
        <a href="<?= ($sidebar_folder === 'dashboard') ? 'view_admin.php' : '../dashboard/view_admin.php' ?>" 
           class="sb-link <?= ($current_page === 'dashboard') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-house"></i></div>
            Dashboard
        </a>
        <a href="<?= ($sidebar_folder === 'master') ? 'tipe_member.php' : '../master/tipe_member.php' ?>" 
           class="sb-link <?= ($current_page === 'tipe_member') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-card"></i></div>
            Kelola Tipe Member
        </a>
        <a href="<?= ($sidebar_folder === 'master') ? 'fasilitas_lapangan.php' : '../master/fasilitas_lapangan.php' ?>" 
           class="sb-link <?= ($current_page === 'fasilitas') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-list-check"></i></div>
            Kelola Fasilitas
        </a>
        <a href="<?= ($sidebar_folder === 'master') ? 'alat.php' : '../master/alat.php' ?>" 
           class="sb-link <?= ($current_page === 'alat') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-toolbox"></i></div>
            Kelola Alat
        </a>
        <a href="<?= ($sidebar_folder === 'master') ? 'promo.php' : '../master/promo.php' ?>" 
           class="sb-link <?= ($current_page === 'promo') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-tags"></i></div>
            Kelola Promo
        </a>
        <a href="<?= ($sidebar_folder === 'master') ? 'customer.php' : '../master/customer.php' ?>" 
           class="sb-link <?= ($current_page === 'customer') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-users"></i></div>
            Kelola Customer
        </a>
        <a href="<?= ($sidebar_folder === 'master') ? 'lapangan.php' : '../master/lapangan.php' ?>" 
           class="sb-link <?= ($current_page === 'lapangan') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-layer-group"></i></div>
            Kelola Lapangan
        </a>
        <a href="<?= ($sidebar_folder === 'master') ? 'jadwal.php' : '../master/jadwal.php' ?>" 
           class="sb-link <?= ($current_page === 'jadwal') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-days"></i></div>
            Kelola Jadwal
        </a>
    </nav>

    <div class="sb-section-label">Transaksi</div>
    <nav>
        <a href="<?= ($sidebar_folder === 'transaksi') ? 'booking.php' : '../transaksi/booking.php' ?>" 
           class="sb-link <?= ($current_page === 'booking') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-calendar-check"></i></div>
            Kelola Booking
        </a>
        <a href="<?= ($sidebar_folder === 'transaksi') ? 'langganan.php' : '../transaksi/langganan.php' ?>" 
           class="sb-link <?= ($current_page === 'langganan') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-crown"></i></div>
            Kelola Langganan
        </a>
        <a href="<?= ($sidebar_folder === 'transaksi') ? 'pembelian.php' : '../transaksi/pembelian.php' ?>" 
           class="sb-link <?= ($current_page === 'pembelian_alat') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-cart-shopping"></i></div>
            Kelola Pembelian Alat
        </a>
        <a href="<?= ($sidebar_folder === 'transaksi') ? 'pembatalan.php' : '../transaksi/pembatalan.php' ?>" 
           class="sb-link <?= ($current_page === 'pembatalan') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-ban"></i></div>
            Kelola Pembatalan
        </a>
    </nav>

    <div class="sb-section-label">Akun</div>
    <nav>
        <a href="<?= ($sidebar_folder === 'profile') ? 'profile.php' : '../profile/profile.php' ?>" 
           class="sb-link <?= ($current_page === 'profile') ? 'active' : '' ?>">
            <div class="sb-icon-wrap"><i class="fa-solid fa-id-badge"></i></div>
            Profil Saya
        </a>
    </nav>

    <div class="sb-bottom">
        <div class="sb-user">
            <div class="sb-avatar">
                <?php if (!empty($sidebar_photo)): ?>
                    <img src="<?= $sidebar_photo ?>" alt="Profile">
                <?php else: ?>
                    <i class="fa-solid fa-user"></i>
                <?php endif; ?>
            </div>
            <div>
                <div class="sb-user-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                <div class="sb-user-role"><?= strtoupper(htmlspecialchars($role)) ?></div>
            </div>
            <a href="../login/logout.php" class="sb-logout" title="Keluar">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
    </div>
</aside>