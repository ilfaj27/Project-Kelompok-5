<?php
// ==========================================
// KONFIGURASI AUTO LOGOUT
// ==========================================
$timeout_duration = 600;          // Waktu diam maksimal dalam DETIK (5 detik utk test)
$login_url        = 'http://localhost/Project-Kelompok-5/login/login.php';   

// Mulai session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kondisi 1: Jika dipicu oleh batas waktu JavaScript
if (isset($_GET['action']) && $_GET['action'] == 'auto_logout') {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_unset();
    session_destroy();
    header("Location: " . $login_url . "?msg=timeout");
    exit();
}

// Kondisi 2: Jika dipicu oleh load halaman yg melebih batas durasi PHP
if (isset($_SESSION['LAST_ACTIVITY'])) {
    if ((time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
        $_SESSION = array();
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_unset();
        session_destroy();
        header("Location: " . $login_url . "?msg=timeout");
        exit();
    }
}
$_SESSION['LAST_ACTIVITY'] = time();

// ============================================================================
// BUNGKUS JAVASCRIPT KE DALAM FUNGSI (Agar bisa dipanggil di bawah/footer)
// ============================================================================
function tampilkan_sensor_auto_logout() {
    global $timeout_duration;
    ?>
    <script>
        (function() {
            let idleTimer;
            const timeoutDuration = <?php echo $timeout_duration * 1000; ?>;

            function resetIdleTimer() {
                clearTimeout(idleTimer);
                idleTimer = setTimeout(function() {
                    let currentUrl = window.location.href.split('?')[0]; 
                    let separator = currentUrl.indexOf('?') !== -1 ? '&' : '?';
                    window.location.href = currentUrl + separator + 'action=auto_logout';
                }, timeoutDuration);
            }

            const events = ['mousemove', 'keydown', 'mousedown', 'touchstart', 'scroll'];
            
            events.forEach(function(eventName) {
                window.addEventListener(eventName, resetIdleTimer, true);
            });

            resetIdleTimer();
        })();
    </script>
    <?php
}