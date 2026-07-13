<?php
// ============================================================
// TOPBAR UNIFIED - UNTUK PEMILIK & KARYAWAN (1 FILE)
// ============================================================
// Include dengan: include '../includes/topbar.php';
//
// PASTIKAN variabel ini sudah tersedia sebelum include:
//   - $nama   (dari session)
//   - $role   (dari session: 'pemilik' atau 'karyawan')
//   - $profile_photo  (opsional)
//
// Variabel opsional:
//   - $topbar_title      = 'Judul Halaman';
//   - $topbar_breadcrumb = 'Master / Karyawan';
// ============================================================

$topbar_title = $topbar_title ?? 'HoopBall';
$topbar_breadcrumb = $topbar_breadcrumb ?? '';

// Deteksi role otomatis
$is_pemilik = (isset($role) && strtolower($role) === 'pemilik');

// Set variabel berdasarkan role
if ($is_pemilik) {
    $display_role = 'MANAJER';
    $profile_link = '../profile/profile_pemilik.php';
    if ($topbar_title === 'HoopBall') {
        $topbar_title = 'Dashboard Manajer';
    }
} else {
    $display_role = strtoupper(htmlspecialchars($role ?? 'KARYAWAN'));
    $profile_link = '../profile/profile.php';
}

// Profile photo
$topbar_photo = '';
if (!empty($profile_photo) && file_exists($profile_photo)) {
    $topbar_photo = $profile_photo;
} elseif (!empty($_SESSION['Photo_Profile']) && file_exists($_SESSION['Photo_Profile'])) {
    $topbar_photo = $_SESSION['Photo_Profile'];
}
?>

<header class="topbar">
    <div class="topbar-left">
        <div class="topbar-title"><?= htmlspecialchars($topbar_title) ?></div>
        <?php if (!empty($topbar_breadcrumb)): ?>
        <div class="topbar-breadcrumb"><?= htmlspecialchars($topbar_breadcrumb) ?></div>
        <?php endif; ?>
    </div>
    <div class="topbar-right">
        <div id="clock-display">
            <div class="clock-time">
                <span id="h">00</span><span class="clock-colon">:</span>
                <span id="m">00</span><span class="clock-colon">:</span>
                <span id="s">00</span>
            </div>
            <div class="clock-divider"></div>
            <div class="clock-date" id="full-date">MEMUAT...</div>
        </div>
        <div class="dropdown-wrap" id="userDropdown">
            <div class="topbar-user">
                <div class="t-avatar">
                    <?php if (!empty($topbar_photo)): ?>
                        <img src="<?= $topbar_photo ?>" alt="Profile">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="t-name"><?= strtoupper(htmlspecialchars($nama ?? 'USER')) ?></div>
                    <div class="t-role"><?= $display_role ?></div>
                </div>
                <i class="fa-solid fa-chevron-down t-chevron"></i>
            </div>
            <div class="dropdown-menu">
                <a href="<?= $profile_link ?>" class="dd-item">
                    <i class="fa-solid fa-id-badge"></i> Profil Saya
                </a>
                <hr class="dd-divider">
                <a href="../login/logout.php" class="dd-item" style="color:var(--red);">
                    <i class="fa-solid fa-right-from-bracket"></i> Keluar
                </a>
            </div>
        </div>
    </div>
</header>

<script>
(function() {
    function updateClock() {
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        const s = String(now.getSeconds()).padStart(2, '0');
        if (document.getElementById('h')) document.getElementById('h').innerText = h;
        if (document.getElementById('m')) document.getElementById('m').innerText = m;
        if (document.getElementById('s')) document.getElementById('s').innerText = s;
        const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        const dEl = document.getElementById('full-date');
        if (dEl) dEl.innerText = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
    }
    setInterval(updateClock, 1000);
    updateClock();

    const userDropdown = document.getElementById('userDropdown');
    if (userDropdown) {
        userDropdown.addEventListener('click', function (e) {
            e.stopPropagation();
            this.classList.toggle('active');
        });
    }
    document.addEventListener('click', function () {
        if (userDropdown) userDropdown.classList.remove('active');
    });
})();
</script>