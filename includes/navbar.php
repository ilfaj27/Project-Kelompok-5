<?php
// Pastikan prefix folder terdefinisi dengan aman
$prefix = isset($path_prefix) ? $path_prefix : '';

// Deteksi halaman aktif berdasarkan URL saat ini
$current_page = basename($_SERVER['PHP_SELF']);
$current_dir = basename(dirname($_SERVER['PHP_SELF']));

function isActive($page, $dir = '') {
    global $current_page, $current_dir;
    if ($dir !== '') {
        return $current_dir === $dir && $current_page === $page;
    }
    return $current_page === $page;
}
?>
<style>
/* ═══════════════════════════════════════════
   NAVBAR - PENANDA HALAMAN AKTIF
   ═══════════════════════════════════════════ */

.navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 40px;
    height: 70px;
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.logo img {
    height: 70px;
    width: auto;
}

.nav-menu {
    display: flex;
    align-items: center;
    gap: 8px;
}

.nav-menu a {
    position: relative;
    padding: 10px 20px;
    text-decoration: none;
    color: #555;
    font-size: 14px;
    font-weight: 500;
    border-radius: 8px;
    transition: all 0.3s ease;
}

.nav-menu a:hover {
    color: #FF6B35;
    background: rgba(255, 107, 53, 0.08);
}

/* ═══════════════════════════════════════════
   PENANDA HALAMAN AKTIF
   ═══════════════════════════════════════════ */

.nav-menu a.active {
    color: #FF6B35;
    background: rgba(255, 107, 53, 0.12);
    font-weight: 600;
}

/* Garis bawah penanda */
.nav-menu a.active::after {
    content: '';
    position: absolute;
    bottom: 4px;
    left: 50%;
    transform: translateX(-50%);
    width: 20px;
    height: 3px;
    background: #FF6B35;
    border-radius: 2px;
}

/* ═══════════════════════════════════════════
   PROFILE DROPDOWN
   ═══════════════════════════════════════════ */

.nav-btns {
    display: flex;
    align-items: center;
    gap: 12px;
}

.btn-login {
    padding: 10px 24px;
    border: 2px solid #FF6B35;
    color: #FF6B35;
    text-decoration: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-login:hover {
    background: #FF6B35;
    color: #fff;
}

.btn-join {
    padding: 10px 24px;
    background: linear-gradient(135deg, #FF6B35 0%, #FF8C42 100%);
    color: #fff;
    text-decoration: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 600;
    transition: all 0.3s ease;
}

.btn-join:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.profile-dropdown-container {
    position: relative;
}

.btn-profile-trigger {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    background: transparent;
    border: 2px solid rgba(255, 107, 53, 0.2);
    border-radius: 50px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-family: inherit;
}

.btn-profile-trigger:hover {
    border-color: #FF6B35;
}

.profile-dropdown-container.active .btn-profile-trigger {
    border-color: #FF6B35;
    background: rgba(255, 107, 53, 0.1);
}

.profile-icon-orange {
    color: #FF6B35;
    font-size: 24px;
}

.profile-trigger-name {
    font-size: 14px;
    font-weight: 600;
    color: #333;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.chevron-icon {
    color: #999;
    font-size: 12px;
    transition: transform 0.3s ease;
}

.profile-dropdown-container.active .chevron-icon {
    transform: rotate(180deg);
    color: #FF6B35;
}

.profile-dropdown-menu {
    position: absolute;
    top: calc(100% + 10px);
    right: 0;
    width: 260px;
    background: #fff;
    border-radius: 16px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
    border: 1px solid rgba(0, 0, 0, 0.05);
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: all 0.3s ease;
    overflow: hidden;
}

.profile-dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.profile-dropdown-header {
    padding: 20px;
    background: linear-gradient(135deg, #FF6B35 0%, #FF8C42 100%);
}

.user-fullname {
    display: block;
    color: #fff;
    font-size: 15px;
    font-weight: 600;
    margin-bottom: 4px;
}

.user-role {
    display: inline-block;
    color: rgba(255, 255, 255, 0.9);
    font-size: 11px;
    font-weight: 500;
    letter-spacing: 1px;
    text-transform: uppercase;
    background: rgba(255, 255, 255, 0.2);
    padding: 2px 10px;
    border-radius: 20px;
}

.profile-dropdown-divider {
    height: 1px;
    background: rgba(0, 0, 0, 0.06);
}

.profile-dropdown-list {
    list-style: none;
    margin: 0;
    padding: 8px 0;
}

.profile-dropdown-list a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 20px;
    color: #555;
    text-decoration: none;
    font-size: 14px;
    transition: all 0.2s ease;
}

.profile-dropdown-list a:hover {
    background: rgba(255, 107, 53, 0.05);
    color: #FF6B35;
}

.profile-dropdown-list a i {
    width: 20px;
    text-align: center;
    color: #999;
}

.profile-dropdown-list a:hover i {
    color: #FF6B35;
}

/* Spacer untuk navbar fixed */
.navbar-spacer {
    height: 70px;
}

/* Responsive */
@media (max-width: 992px) {
    .navbar {
        padding: 0 20px;
    }
}
</style>

<nav class="navbar">
    <a href="<?= $prefix ?>index.php" class="logo">
        <img src="<?= $prefix ?>asset/image/logo2.png" alt="HoopBall">
    </a>
    <div class="nav-menu">
        <a href="<?= $prefix ?>index.php" class="<?= isActive('index.php') ? 'active' : '' ?>">Beranda</a>
        <a href="<?= $prefix ?>customer/booking_customer.php" class="<?= isActive('booking_customer.php', 'customer') ? 'active' : '' ?>">Booking</a>
        <a href="<?= $prefix ?>customer/pembatalan_customer.php" class="<?= isActive('pembatalan_customer.php', 'customer') ? 'active' : '' ?>">Pembatalan</a>
        <a href="<?= $prefix ?>customer/langganan_customer.php" class="<?= isActive('langganan_customer.php', 'customer') ? 'active' : '' ?>">Member</a>
        <a href="<?= $prefix ?>customer/pembelian_alat.php" class="<?= isActive('pembelian_alat.php', 'customer') ? 'active' : '' ?>">Pembelian</a>
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
            <a href="<?= $prefix ?>login/login.php" class="btn-login">Masuk</a>
            <a href="<?= $prefix ?>login/register.php" class="btn-join">Daftar Sekarang</a>
        <?php endif; ?>
    </div>
</nav>

<div class="navbar-spacer"></div>

<script>
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