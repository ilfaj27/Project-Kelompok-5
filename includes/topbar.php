<?php $topbar_title = $topbar_title ?? 'HoopBall'; ?>
<?php $topbar_breadcrumb = $topbar_breadcrumb ?? ''; ?>

<header class="topbar">
    <div class="topbar-left">
        <div class="topbar-title"><?= htmlspecialchars($topbar_title) ?></div>
        <div class="topbar-breadcrumb"><?= htmlspecialchars($topbar_breadcrumb) ?></div>
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
                    <?php if (!empty($sidebar_photo)): ?>
                        <img src="<?= $sidebar_photo ?>" alt="Profile">
                    <?php else: ?>
                        <i class="fa-solid fa-user"></i>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="t-name"><?= strtoupper(htmlspecialchars($nama)) ?></div>
                    <div class="t-role"><?= strtoupper(htmlspecialchars($role)) ?></div>
                </div>
                <i class="fa-solid fa-chevron-down t-chevron"></i>
            </div>
            <div class="dropdown-menu">
                <a href="../profile/profile.php" class="dd-item">
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