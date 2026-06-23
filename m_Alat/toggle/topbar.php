<header class="topbar">
    <div class="topbar-left">
        <div class="topbar-title">Kelola Alat</div>
        <div class="topbar-breadcrumb">Operasional / Alat</div>
    </div>
    <div class="topbar-right">
        <div id="clock-display">
            <div class="clock-time">
                <span id="clock-h">00</span><span class="clock-colon">:</span>
                <span id="clock-m">00</span><span class="clock-colon">:</span>
                <span id="clock-s">00</span>
            </div>
            <div class="clock-divider"></div>
            <div class="clock-date" id="full-date">MEMUAT...</div>
        </div>
        <a href="#" class="topbar-btn">
            <i class="fa-solid fa-bell"></i>
            <?php if ($total_pending > 0): ?><span class="notif-dot"></span><?php endif; ?>
        </a>
        <div class="dropdown-wrap" id="userDropdown">
            <div class="topbar-user" onclick="toggleUserDropdown()">
                <div class="t-avatar">
                    <?php if (!empty($profile_photo)): ?>
                        <img src="<?= htmlspecialchars($profile_photo) ?>" alt="Profile">
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
                <a href="../profile/profile.php" class="dd-item"><i class="fa-solid fa-id-badge"></i> Profil Saya</a>
                <a href="../login/logout.php" class="dd-item" style="color:var(--red);"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
            </div>
        </div>
    </div>
</header>