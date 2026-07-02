<?php
// Pastikan prefix folder terdefinisi dengan aman
$prefix = isset($path_prefix) ? $path_prefix : '';
?>
<nav class="navbar">
    <a href="<?= $prefix ?>index.php" class="logo">
        <img src="<?= $prefix ?>asset/image/logo2.png" alt="HoopBall">
    </a>
    <div class="nav-menu">
        <a href="<?= $prefix ?>index.php" class="active">Beranda</a>
        <a href="<?= $prefix ?>customer/booking_customer.php">Booking</a>
        <a href="<?= $prefix ?>customer/pembatalan_customer.php">Pembatalan</a>
        <a href="<?= $prefix ?>customer/langganan_customer.php">Member</a>
        <a href="<?= $prefix ?>customer/pembelian_alat.php">Pembelian</a>
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
                        <li>
                            <a href="<?= $prefix ?>profile/profile_customer.php">
                                <i class="fa-regular fa-user"></i> Profil Saya
                            </a>
                        </li>
                    </ul>

                    <div class="profile-dropdown-divider"></div>

                    <ul class="profile-dropdown-list">
                        <li>
                            <a href="<?= $prefix ?>login/logout.php">
                                <i class="fa-solid fa-arrow-right-from-bracket"></i> Keluar
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        <?php else: ?>
            <!-- TAMPILAN JIKA BELUM LOGIN -->
            <a href="<?= $prefix ?>login/login.php" class="btn-login">Masuk</a>
            <a href="<?= $prefix ?>login/register.php" class="btn-join">Daftar Sekarang</a>
        <?php endif; ?>
    </div>
</nav>

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