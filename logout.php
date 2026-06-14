<?php
// ============================================================================
// LOGOUT.PHP — Logout biasa (bukan dari hapus akun)
// ============================================================================
ob_start();
session_start();

// Hapus session cookie
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params['path'], $params['domain'],
        $params['secure'], $params['httponly']
    );
}

// Clear dan destroy session
$_SESSION = array();
session_destroy();

ob_end_clean();
header("Location: login.php");
exit();
?>