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
   LOGIN/JOIN BUTTONS
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

/* ═══════════════════════════════════════════
   PROFILE DROPDOWN - SAMA SEPERTI PEMBELIAN_ALAT.PHP
   ═══════════════════════════════════════════ */

/* CSS Variables */
:root {
    --primary: #FF5200;
    --primary-hover: #E04800;
    --text-gray: #8E8E93;
    --white: #FFFFFF;
    --green: #34C759;
    --green-lt: rgba(52,199,89,0.10);
    --transition-smooth: all 0.3s cubic-bezier(0.4,0,0.2,1);
}

/* Nav User Container */
.profile-dropdown-container {
    position: relative;
    height: 70px;
    display: flex;
    align-items: center;
}

/* Nav User Button */
.btn-profile-trigger {
    background: #F2F2F7;
    border: 1px solid #E5E5EA;
    padding: 8px 16px;
    border-radius: 50px;
    color: #1C1C1E;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: var(--transition-smooth);
    font-family: inherit;
}

.btn-profile-trigger:hover {
    background: #E5E5EA;
    border-color: var(--primary);
    transform: scale(1.02);
    box-shadow: 0 4px 12px rgba(255,82,0,0.15);
}

.profile-dropdown-container.active .btn-profile-trigger {
    border-color: var(--primary);
    background: #E5E5EA;
}

.btn-profile-trigger .profile-icon-orange {
    font-size: 16px;
    color: var(--primary);
    transition: transform 0.3s ease;
}

.btn-profile-trigger:hover .profile-icon-orange {
    transform: scale(1.2);
}

.btn-profile-trigger img.profile-icon-orange {
    width: 24px;
    height: 24px;
    border-radius: 50%;
    object-fit: cover;
    font-size: unset;
}

.btn-profile-trigger:hover img.profile-icon-orange {
    transform: scale(1.15);
}

.btn-profile-trigger .profile-trigger-name {
    font-size: 14px;
    font-weight: 600;
    color: #1C1C1E;
    max-width: 120px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.btn-profile-trigger .chevron-icon {
    font-size: 11px;
    color: #8E8E93;
    transition: 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

.profile-dropdown-container.active .chevron-icon,
.profile-dropdown-container:hover .chevron-icon {
    transform: rotate(180deg);
    color: var(--primary);
}

/* Member Badge */
.member-badge-nav {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--green-lt);
    border: 1px solid var(--green);
    color: var(--green);
    padding: 4px 12px;
    border-radius: 50px;
    font-size: 11px;
    font-weight: 700;
    margin-left: 4px;
    animation: pulse 2s ease-in-out infinite;
}

/* Dropdown Menu */
.profile-dropdown-menu {
    position: absolute;
    top: 85%;
    right: 0;
    background: #16161a;
    min-width: 220px;
    border-radius: 12px;
    border: 1px solid #2d2d33;
    padding: 8px 0;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px) scale(0.95);
    transform-origin: top right;
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 1001;
}

.profile-dropdown-menu.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0) scale(1);
    animation: fadeInUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
}

/* User Info Header */
.profile-dropdown-header {
    padding: 12px 20px;
    border-bottom: 1px solid #2d2d33;
    margin-bottom: 6px;
    background: transparent;
}

.profile-dropdown-header .user-fullname {
    color: var(--white);
    font-size: 14px;
    font-weight: 700;
    display: block;
    margin-bottom: 0;
}

.profile-dropdown-header .user-role {
    color: var(--text-gray);
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 2px;
    display: block;
    background: transparent;
    padding: 0;
    border-radius: 0;
}

/* Dropdown Links */
.profile-dropdown-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.profile-dropdown-list li {
    margin: 0;
    padding: 0;
}

.profile-dropdown-list a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 20px;
    color: #c5c5ca;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    position: relative;
    overflow: hidden;
}

.profile-dropdown-list a::after {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 3px;
    height: 100%;
    background: var(--primary);
    transform: scaleY(0);
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.profile-dropdown-list a i {
    font-size: 14px;
    width: 16px;
    text-align: center;
    color: inherit;
    transition: transform 0.3s ease;
}

.profile-dropdown-list a:hover {
    background: #222227;
    color: var(--primary);
    padding-left: 28px;
}

.profile-dropdown-list a:hover::after {
    transform: scaleY(1);
}

.profile-dropdown-list a:hover i {
    transform: scale(1.2);
    color: inherit;
}

/* Logout special styling */
.profile-dropdown-list a.logout:hover {
    color: #ff3b30;
}

.profile-dropdown-list a.logout:hover::after {
    background: #ff3b30;
}

/* Dropdown Divider */
.profile-dropdown-divider {
    height: 1px;
    background: #2d2d33;
    margin: 6px 0;
}

/* Animation keyframes */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes pulse {
    0%, 100% { transform: scale(1); box-shadow: 0 0 0 0 rgba(52,199,89,0.4); }
    50% { transform: scale(1.05); box-shadow: 0 0 0 15px rgba(52,199,89,0); }
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
            <?php
            // Ambil data customer untuk photo profile dan member
            $nav_photo = '';
            $nav_nama = $_SESSION['Nama_Customer'] ?? $_SESSION['nama'] ?? 'User';
            $nav_has_member = false;
            $nav_member_tipe = '';

            if (isset($_SESSION['id_customer']) || isset($_SESSION['ID_Customer'])) {
                $nav_id = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? '';
                if (!empty($nav_id) && isset($conn)) {
                    $nav_stmt = sqlsrv_query($conn, "SELECT Photo_Profile FROM Customer WHERE ID_Customer = ? AND Is_Deleted = 0", array($nav_id));
                    if ($nav_stmt) {
                        $nav_data = sqlsrv_fetch_array($nav_stmt, SQLSRV_FETCH_ASSOC);
                        $nav_photo = $nav_data['Photo_Profile'] ?? '';
                    }
                    // Cek member aktif
                    $nav_member_check = sqlsrv_query($conn, 
                        "SELECT TOP 1 T.Nama_Tipe FROM Langganan L INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe WHERE L.ID_Customer = ? AND L.Status = 1 AND GETDATE() BETWEEN L.Tanggal_Mulai AND L.Tanggal_Selesai ORDER BY L.Tanggal_Selesai DESC", 
                        array($nav_id)
                    );
                    if ($nav_member_check) {
                        $nav_member_data = sqlsrv_fetch_array($nav_member_check, SQLSRV_FETCH_ASSOC);
                        if ($nav_member_data) {
                            $nav_has_member = true;
                            $nav_member_tipe = $nav_member_data['Nama_Tipe'];
                        }
                    }
                }
            }

            function navResolvePhoto($photo_path) {
                if (empty($photo_path)) return '';
                if (strpos($photo_path, 'http://') === 0 || strpos($photo_path, 'https://') === 0) return $photo_path;
                if (strpos($photo_path, '../') === 0) return $photo_path;
                if (strpos($photo_path, '/') === 0) return '..' . $photo_path;
                return '../' . ltrim($photo_path, '/');
            }
            ?>
            <div class="profile-dropdown-container">
                <button class="btn-profile-trigger" id="profileTrigger">
                    <?php if (!empty($nav_photo) && file_exists(navResolvePhoto($nav_photo))): ?>
                        <img src="<?= htmlspecialchars(navResolvePhoto($nav_photo)) ?>" alt="Avatar" class="profile-icon-orange">
                    <?php else: ?>
                        <i class="fa-solid fa-circle-user profile-icon-orange"></i>
                    <?php endif; ?>
                    <span class="profile-trigger-name"><?= htmlspecialchars($nav_nama) ?></span>
                    <?php if ($nav_has_member): ?>
                        <span class="member-badge-nav"><i class="fa-solid fa-crown"></i> <?= htmlspecialchars($nav_member_tipe) ?></span>
                    <?php endif; ?>
                    <i class="fa-solid fa-chevron-down chevron-icon"></i>
                </button>

                <div class="profile-dropdown-menu" id="profileDropdownMenu">
                    <div class="profile-dropdown-header">
                        <span class="user-fullname"><?= htmlspecialchars($nav_nama) ?></span>
                        <span class="user-role">Customer <?= $nav_has_member ? '• Member ' . htmlspecialchars($nav_member_tipe) : '' ?></span>
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
                            <a href="<?= $prefix ?>login/logout.php" class="logout">
                                <i class="fa-solid fa-right-from-bracket"></i> Keluar
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