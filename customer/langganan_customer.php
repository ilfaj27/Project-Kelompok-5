<?php
// ============================================================================
// BUFFER OUTPUT
// ============================================================================
ob_start();

session_start();
$path_prefix = "../";
include '../includes/config.php';

// ============================================================================
// CEK AKSES
// ============================================================================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
    header("Location: ../login/login.php");
    exit();
}

// ============================================================================
// ⚠️ PANGGIL SENSOR AUTO LOGOUT (Karena ada di dalam folder customer, pakai ../)
// ============================================================================
require_once '../login/auto_logout.php';
// ============================================================================

$id_customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';

// ============================================================================
// AMBIL DATA CUSTOMER
// ============================================================================
$nama_customer = 'Pelanggan';
$photo_profile = '';

if (!empty($id_customer)) {
    $cek_deleted = sqlsrv_query($conn,
        "SELECT Nama_Customer, Photo_Profile, Is_Deleted, Status FROM Customer WHERE ID_Customer = ?",
        array($id_customer)
    );
    if ($cek_deleted) {
        $row_cust = sqlsrv_fetch_array($cek_deleted, SQLSRV_FETCH_ASSOC);
        if ($row_cust) {
            if ($row_cust['Is_Deleted'] == 1 || $row_cust['Status'] == 0) {
                $_SESSION = array();
                session_destroy();
                setcookie('remember_me', '', time() - 3600, "/");
                ob_end_clean();
                header("Location: ../login/login.php?status=error&msg=Akun Anda telah dinonaktifkan.");
                exit();
            }
            $nama_customer = $row_cust['Nama_Customer'] ?? 'Pelanggan';
            $photo_profile = $row_cust['Photo_Profile'] ?? '';
        }
    }
}

// ============================================================================
// CEK STATUS MEMBER AKTIF (menggunakan SP_GetLanggananByCustomer)
// ============================================================================
$member_aktif = null;
$member_pending = null;
$member_check = sqlsrv_query($conn,
    "EXEC SP_GetLanggananByCustomer @ID_Customer = ?, @StatusFilter = NULL",
    array($id_customer)
);
if ($member_check) {
    while ($row = sqlsrv_fetch_array($member_check, SQLSRV_FETCH_ASSOC)) {
        if ($row['Status'] == 1) {
            $member_aktif = $row;
            break;
        }
        if ($row['Status'] == 0 && !$member_pending) {
            $member_pending = $row;
        }
    }
}

$has_member = ($member_aktif && $member_aktif['Status'] == 1);
$member_tipe = $has_member ? $member_aktif['Nama_Tipe'] : '';

// ============================================================================
// AMBIL RIWAYAT LANGGANAN CUSTOMER (menggunakan SP_GetLanggananByCustomer)
// ============================================================================
$riwayat_langganan = [];
$riwayat_query = sqlsrv_query($conn,
    "EXEC SP_GetLanggananByCustomer @ID_Customer = ?, @StatusFilter = NULL",
    array($id_customer)
);
if ($riwayat_query) {
    while ($row = sqlsrv_fetch_array($riwayat_query, SQLSRV_FETCH_ASSOC)) {
        $riwayat_langganan[] = $row;
    }
}

// ============================================================================
// AMBIL DATA TIPE MEMBER (yang aktif)
// ============================================================================
$tipe_member_list = [];
$query_tipe = sqlsrv_query($conn,
    "SELECT ID_Tipe, Nama_Tipe, Harga_Member, Potongan_Harga, Status
     FROM Tipe_Member
     WHERE Status = 1 AND Is_Deleted = 0
     ORDER BY Harga_Member ASC"
);
if ($query_tipe) {
    while ($row = sqlsrv_fetch_array($query_tipe, SQLSRV_FETCH_ASSOC)) {
        $tipe_member_list[] = $row;
    }
}

// ============================================================================
// PROSES PEMBELIAN LANGGANAN - MENGGUNAKAN SP_CreateLangganan
// ============================================================================
$pembelian_msg = '';
$pembelian_error = '';

if (isset($_POST['beli_langganan'])) {
    $id_tipe = $_POST['id_tipe'] ?? '';
    $metode_pembayaran = $_POST['metode_pembayaran'] ?? '';

    if (empty($id_tipe) || empty($metode_pembayaran)) {
        $pembelian_error = 'Pilih tipe member dan metode pembayaran!';
    } else {
        $bukti_pembayaran_db = '';
        if (!isset($_FILES['bukti_pembayaran']) || $_FILES['bukti_pembayaran']['error'] !== UPLOAD_ERR_OK) {
            $pembelian_error = 'Bukti pembayaran wajib diunggah sebelum konfirmasi.';
        } else {
            $allowed_ext = ['jpg', 'jpeg', 'png', 'pdf'];
            $file_tmp = $_FILES['bukti_pembayaran']['tmp_name'];
            $file_name_asli = $_FILES['bukti_pembayaran']['name'];
            $file_ext = strtolower(pathinfo($file_name_asli, PATHINFO_EXTENSION));

            if (!in_array($file_ext, $allowed_ext)) {
                $pembelian_error = 'Format bukti pembayaran harus JPG, PNG, atau PDF.';
            } elseif ($_FILES['bukti_pembayaran']['size'] > 5 * 1024 * 1024) {
                $pembelian_error = 'Ukuran bukti pembayaran maksimal 5MB.';
            } else {
                $upload_dir = '../asset/Bukti_Pembayaran/';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0755, true);
                }
                $new_file_name = 'bukti_langganan_' . $id_customer . '_' . time() . '_' . uniqid() . '.' . $file_ext;
                $target_path = $upload_dir . $new_file_name;

                if (!move_uploaded_file($file_tmp, $target_path)) {
                    $pembelian_error = 'Gagal mengunggah bukti pembayaran. Silakan coba lagi.';
                } else {
                    $bukti_pembayaran_db = 'asset/Bukti_Pembayaran/' . $new_file_name;
                }
            }
        }

        if (empty($pembelian_error)) {
            $stmt_sp = sqlsrv_query($conn,
                "EXEC SP_CreateLangganan @ID_Customer = ?, @ID_Tipe = ?, @Metode_Pembayaran = ?, @Created_By = ?",
                array($id_customer, $id_tipe, $metode_pembayaran, $nama_customer)
            );

            if ($stmt_sp) {
                $result = sqlsrv_fetch_array($stmt_sp, SQLSRV_FETCH_ASSOC);
                if ($result && $result['Status'] === 'SUCCESS') {
                    if (!empty($bukti_pembayaran_db) && !empty($result['ID_Langganan'])) {
                        sqlsrv_query($conn,
                            "UPDATE Langganan SET Bukti_Pembayaran = ? WHERE ID_Langganan = ?",
                            array($bukti_pembayaran_db, $result['ID_Langganan'])
                        );
                    }

                    $stmt_tipe_name = sqlsrv_query($conn,
                        "SELECT Nama_Tipe FROM Tipe_Member WHERE ID_Tipe = ?",
                        array($id_tipe)
                    );
                    $tipe_name = 'Member';
                    if ($stmt_tipe_name) {
                        $row_tipe = sqlsrv_fetch_array($stmt_tipe_name, SQLSRV_FETCH_ASSOC);
                        if ($row_tipe) {
                            $tipe_name = $row_tipe['Nama_Tipe'];
                        }
                    }
                    header("Location: langganan_customer.php?status=success&msg=Pendaftaran member berhasil! Paket: " . urlencode($tipe_name) . ". Menunggu konfirmasi admin.");
                    exit();
                } else {
                    $pembelian_error = $result['Message'] ?? 'Gagal mendaftar. Silakan coba lagi.';
                }
            } else {
                $errors = sqlsrv_errors();
                $pembelian_error = 'Gagal mendaftar. Error: ' . ($errors[0]['message'] ?? 'Unknown');
            }
        }
    }
}

// ============================================================================
// URL PARAMETER NOTIFICATION
// ============================================================================
$notif_status = $_GET['status'] ?? '';
$notif_msg = $_GET['msg'] ?? '';

function rupiahFormat($n) {
    return 'Rp ' . number_format($n, 0, ',', '.');
}

function formatTanggal($tanggal) {
    if (empty($tanggal)) return '-';
    if (is_object($tanggal) && method_exists($tanggal, 'format')) {
        return $tanggal->format('d M Y');
    }
    return date('d M Y', strtotime($tanggal));
}

$status_labels = [
    0 => ['label' => 'Menunggu Konfirmasi', 'class' => 'sp-pending', 'icon' => 'fa-clock', 'color' => '#D97706', 'bg' => '#FEF3C7'],
    1 => ['label' => 'Aktif', 'class' => 'sp-active', 'icon' => 'fa-check-circle', 'color' => '#059669', 'bg' => '#D1FAE5'],
    2 => ['label' => 'Berakhir', 'class' => 'sp-inactive', 'icon' => 'fa-flag-checkered', 'color' => '#6B7280', 'bg' => '#F3F4F6'],
    3 => ['label' => 'Ditolak', 'class' => 'sp-inactive', 'icon' => 'fa-ban', 'color' => '#DC2626', 'bg' => '#FEE2E2']
];

function resolvePhotoPath($photo_path) {
    if (empty($photo_path)) return '';
    if (strpos($photo_path, 'http://') === 0 || strpos($photo_path, 'https://') === 0) {
        return $photo_path;
    }
    if (strpos($photo_path, '../') === 0) {
        return $photo_path;
    }
    if (strpos($photo_path, '/') === 0) {
        return '..' . $photo_path;
    }
    return '../' . ltrim($photo_path, '/');
}

$resolvedPhotoProfile = resolvePhotoPath($photo_profile);

$initials = '';
if (!empty($nama_customer) && $nama_customer !== 'Pelanggan') {
    $parts = explode(' ', $nama_customer);
    $initials = strtoupper(substr($parts[0], 0, 1));
    if (isset($parts[1])) {
        $initials .= strtoupper(substr($parts[1], 0, 1));
    }
}

// Status config untuk riwayat
$status_config = [
    0 => [
        'icon' => 'fa-clock',
        'icon_bg' => '#FEF3C7',
        'icon_color' => '#D97706',
        'badge_bg' => '#FEF3C7',
        'badge_color' => '#D97706',
        'badge_text' => 'Menunggu',
        'timeline_color' => '#D97706',
        'progress' => 25
    ],
    1 => [
        'icon' => 'fa-crown',
        'icon_bg' => '#D1FAE5',
        'icon_color' => '#059669',
        'badge_bg' => '#D1FAE5',
        'badge_color' => '#059669',
        'badge_text' => 'Aktif',
        'timeline_color' => '#059669',
        'progress' => 100
    ],
    2 => [
        'icon' => 'fa-flag-checkered',
        'icon_bg' => '#F3F4F6',
        'icon_color' => '#6B7280',
        'badge_bg' => '#F3F4F6',
        'badge_color' => '#6B7280',
        'badge_text' => 'Berakhir',
        'timeline_color' => '#6B7280',
        'progress' => 100
    ],
    3 => [
        'icon' => 'fa-xmark',
        'icon_bg' => '#FEE2E2',
        'icon_color' => '#DC2626',
        'badge_bg' => '#FEE2E2',
        'badge_color' => '#DC2626',
        'badge_text' => 'Ditolak',
        'timeline_color' => '#DC2626',
        'progress' => 0
    ]
];

// Tipe member config untuk icon dan warna
$tipe_config = [
    'Silver' => ['icon' => 'fa-medal', 'gradient' => 'linear-gradient(135deg, #E8E8E8 0%, #C0C0C0 100%)', 'text_color' => '#4B5563'],
    'Gold' => ['icon' => 'fa-trophy', 'gradient' => 'linear-gradient(135deg, #FDE68A 0%, #F59E0B 100%)', 'text_color' => '#92400E'],
    'Platinum' => ['icon' => 'fa-gem', 'gradient' => 'linear-gradient(135deg, #E0E7FF 0%, #6366F1 100%)', 'text_color' => '#3730A3']
];
?>
<!DOCTYPE html>
<html lang="id" style="scroll-behavior: smooth;">
<head>
  <?php include '../includes/favicon.php'; ?>
    <title>Langganan Member | HoopBall</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Barlow+Condensed:wght@700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../asset/css/navbar_footer.css">
    <link rel="stylesheet" href="../asset/css/responsive_langganan.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
        :root {
            --primary: #FF5200;
            --primary-hover: #E04800;
            --primary-light: rgba(255,82,0,0.1);
            --dark-bg: #0B0B0C;
            --card-dark: #121214;
            --text-gray: #8E8E93;
            --border-color: #222225;
            --white: #FFFFFF;
            --light-bg: #F8F9FA;
            --green: #34C759;
            --green-lt: rgba(52,199,89,.10);
            --yellow: #FFCC00;
            --yellow-lt: rgba(255,204,0,.10);
            --red: #FF3B30;
            --red-lt: rgba(255,59,48,.10);
            --blue: #007AFF;
            --blue-lt: rgba(0,122,255,.10);
            --purple: #AF52DE;
            --purple-lt: rgba(175,82,222,.10);
            --orange: #FF5A1F;
            --orange-hover: #E0440E;
            --orange-lt: rgba(255,90,31,0.06);
            --orange-glow: rgba(255,90,31,0.15);
            --border: #E2E8F0;
            --border-lt: #F1F5F9;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --muted: #94A3B8;
            --bg: #F8FAFC;
            --transition-smooth: all 0.3s cubic-bezier(0.4,0,0.2,1);
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--light-bg);color:#111;overflow-x:hidden;animation: fadeIn 0.5s ease-out}
        ::-webkit-scrollbar{display:none}
        html,body{-ms-overflow-style:none;scrollbar-width:none}
        ::selection{background:rgba(255,82,0,0.3);color:#1C1C1E}

        /* ANIMATIONS */
        @keyframes fadeInUp{from{opacity:0;transform:translateY(40px)}to{opacity:1;transform:translateY(0)}}
        @keyframes fadeInDown{from{opacity:0;transform:translateY(-30px)}to{opacity:1;transform:translateY(0)}}
        @keyframes fadeIn{from{opacity:0}to{opacity:1}}
        @keyframes scaleIn{from{opacity:0;transform:scale(0.8)}to{opacity:1;transform:scale(1)}}
        @keyframes float{0%,100%{transform:translateY(0)}50%{transform:translateY(-10px)}}
        @keyframes pulse{0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(52,199,89,.4)}50%{transform:scale(1.05);box-shadow:0 0 0 15px rgba(52,199,89,0)}}
        @keyframes shimmer{0%{background-position:-200% 0}100%{background-position:200% 0}}
        @keyframes slideInModal{from{transform:translateY(30px);opacity:0}to{transform:translateY(0);opacity:1}}
        @keyframes fadeInModal{from{opacity:0}to{opacity:1}}
        @keyframes countUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
        @keyframes slideInLeft{from{opacity:0;transform:translateX(-30px)}to{opacity:1;transform:translateX(0)}}
        @keyframes slideInRight{from{opacity:0;transform:translateX(30px)}to{opacity:1;transform:translateX(0)}}
        @keyframes cardEnter{from{opacity:0;transform:translateY(30px) scale(0.95)}to{opacity:1;transform:translateY(0) scale(1)}}
        @keyframes iconBounce{0%,100%{transform:scale(1)}50%{transform:scale(1.15)}}
        @keyframes borderGlow{0%,100%{border-color:rgba(255,82,0,0.3)}50%{border-color:rgba(255,82,0,0.8)}}
        @keyframes heroGradient{0%{background-position:0% 50%}50%{background-position:100% 50%}100%{background-position:0% 50%}}
        @keyframes ballFloat1{0%,100%{transform:translate(0,0) rotate(0deg)}25%{transform:translate(10px,-20px) rotate(5deg)}50%{transform:translate(-5px,10px) rotate(-3deg)}75%{transform:translate(15px,5px) rotate(2deg)}}
        @keyframes ballFloat2{0%,100%{transform:translate(0,0) rotate(0deg)}25%{transform:translate(-15px,10px) rotate(-5deg)}50%{transform:translate(10px,-15px) rotate(3deg)}75%{transform:translate(-10px,-5px) rotate(-2deg)}}
        @keyframes ballFloat3{0%,100%{transform:translate(0,0) rotate(0deg)}25%{transform:translate(20px,5px) rotate(3deg)}50%{transform:translate(-15px,-20px) rotate(-5deg)}75%{transform:translate(5px,15px) rotate(2deg)}}
        @keyframes glowPulse{0%,100%{box-shadow:0 0 20px rgba(255,82,0,0.3)}50%{box-shadow:0 0 40px rgba(255,82,0,0.6)}}
        @keyframes progressFill{from{width:0%}to{width:var(--progress-width)}}
        @keyframes timelineDotPulse{0%,100%{box-shadow:0 0 0 0 rgba(255,82,0,0.4)}50%{box-shadow:0 0 0 8px rgba(255,82,0,0)}}
        @keyframes loaderBounce{from{transform:translateY(0)}to{transform:translateY(-20px)}}

        .reveal{opacity:0;transform:translateY(40px);transition:all 0.8s cubic-bezier(0.16,1,0.3,1)}
        .reveal.active{opacity:1;transform:translateY(0)}
        .reveal-stagger .stagger-item{opacity:0;transform:translateY(30px);transition:all 0.6s cubic-bezier(0.16,1,0.3,1)}
        .reveal-stagger.active .stagger-item{opacity:1;transform:translateY(0)}
        .reveal-stagger.active .stagger-item:nth-child(1){transition-delay:0s}
        .reveal-stagger.active .stagger-item:nth-child(2){transition-delay:.1s}
        .reveal-stagger.active .stagger-item:nth-child(3){transition-delay:.2s}

        .scroll-progress{position:fixed;top:0;left:0;height:3px;background:linear-gradient(90deg,var(--primary),#FF8C42);z-index:9999;transform-origin:left;transform:scaleX(0);transition:transform 0.1s ease-out}
        .profile-avatar-initials{width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--orange-hover));color:#fff;font-size:10px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0}

        /* HERO */
        .hero{background:linear-gradient(135deg,#0B0B0C 0%,#1a1a2e 50%,#0d0d1a 100%);background-size:200% 200%;animation:heroGradient 15s ease infinite;padding:60px 80px;display:flex;align-items:center;justify-content:space-between;gap:40px;position:relative;overflow:hidden;min-height:400px}
        .hero::after{content:'';position:absolute;bottom:-50px;left:-50px;width:200px;height:200px;border-radius:50%;background:radial-gradient(circle,rgba(255,82,0,0.08) 0%,transparent 70%);pointer-events:none;animation:ballFloat3 15s ease-in-out infinite}
        .hero::before{content:'';position:absolute;right:-100px;top:-100px;width:400px;height:400px;border-radius:50%;background:radial-gradient(circle,rgba(255,82,0,.15) 0%,transparent 70%)}
        .hero-left{max-width:600px;position:relative;z-index:1;animation:fadeInUp 0.8s ease-out forwards}
        .hero-left .hero-badge{animation:slideInLeft 0.6s ease-out 0.2s both}
        .hero-left .hero-title{animation:fadeInUp 0.8s ease-out 0.4s both}
        .hero-left .hero-desc{animation:fadeInUp 0.8s ease-out 0.6s both}
        .hero-badge{display:inline-flex;align-items:center;gap:8px;background:var(--primary);color:var(--white);padding:8px 16px;border-radius:50px;font-size:13px;font-weight:700;margin-bottom:20px;transition:all 0.3s ease;cursor:default}
        .hero-badge:hover{transform:scale(1.05);box-shadow:0 4px 15px rgba(255,82,0,0.4)}
        .hero-badge i{animation:iconBounce 2s ease-in-out infinite}
        .hero-title{font-size:42px;font-weight:800;color:var(--white);line-height:1.2;margin-bottom:16px;text-shadow:0 2px 10px rgba(0,0,0,0.3)}
        .hero-title span{color:var(--primary);display:inline-block;transition:transform 0.3s ease}
        .hero-left:hover .hero-title span{transform:scale(1.02)}
        .hero-desc{color:#A0A0A5;font-size:16px;line-height:1.6;margin-bottom:24px;transition:color 0.3s ease}
        .hero-left:hover .hero-desc{color:#C0C0C5}

        .floating-ball{position:absolute;border-radius:50%;background:radial-gradient(circle at 30% 30%,rgba(255,82,0,0.4),rgba(255,82,0,0.1));filter:blur(1px);pointer-events:none;z-index:0}
        .ball-1{width:80px;height:80px;top:10%;right:15%;animation:ballFloat1 8s ease-in-out infinite;background:radial-gradient(circle at 30% 30%,rgba(255,82,0,0.35),rgba(255,82,0,0.05))}
        .ball-2{width:50px;height:50px;top:60%;right:25%;animation:ballFloat2 10s ease-in-out infinite;background:radial-gradient(circle at 30% 30%,rgba(255,140,66,0.3),rgba(255,140,66,0.05))}
        .ball-3{width:120px;height:120px;bottom:10%;right:5%;animation:ballFloat3 12s ease-in-out infinite;background:radial-gradient(circle at 30% 30%,rgba(255,82,0,0.2),rgba(255,82,0,0.02))}
        .ball-4{width:40px;height:40px;top:30%;right:40%;animation:ballFloat1 7s ease-in-out infinite reverse;background:radial-gradient(circle at 30% 30%,rgba(255,200,100,0.3),rgba(255,200,100,0.05))}
        .ball-5{width:60px;height:60px;bottom:30%;right:30%;animation:ballFloat2 9s ease-in-out infinite reverse;background:radial-gradient(circle at 30% 30%,rgba(255,82,0,0.25),rgba(255,82,0,0.03))}
        .hero-glow{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:600px;height:600px;background:radial-gradient(circle,rgba(255,82,0,0.08) 0%,transparent 70%);pointer-events:none;z-index:0;animation:glowPulse 4s ease-in-out infinite}

        /* MEMBER STATUS CARD */
        .member-status-card{background:var(--white);border-radius:16px;padding:28px;border:1px solid #E5E5EA;position:relative;z-index:1;min-width:340px;animation:fadeInUp 0.8s ease-out 0.2s forwards,cardEnter 0.6s ease-out 0.2s both;opacity:0;transition:all 0.4s cubic-bezier(0.34,1.56,0.64,1)}
        .member-status-card:hover{transform:translateY(-6px) scale(1.02);box-shadow:0 20px 40px rgba(0,0,0,0.15);border-color:rgba(255,82,0,0.3)}
        .member-status-icon{animation:scaleIn 0.5s ease-out 0.4s both}
        .member-status-text h3{animation:fadeInUp 0.5s ease-out 0.5s both}
        .member-status-text p{animation:fadeInUp 0.5s ease-out 0.6s both}
        .member-detail-row{animation:fadeInUp 0.4s ease-out both;opacity:0}
        .member-detail-row:nth-child(2){animation-delay:0.7s}
        .member-detail-row:nth-child(3){animation-delay:0.8s}
        .member-detail-row:nth-child(4){animation-delay:0.9s}
        .member-detail-row:nth-child(5){animation-delay:1.0s}
        .member-detail-row:nth-child(6){animation-delay:1.1s}
        .member-status-header{display:flex;align-items:center;gap:16px;margin-bottom:20px;padding-bottom:20px;border-bottom:1px solid #F2F2F7}
        .member-status-icon{width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:24px;transition:all 0.3s cubic-bezier(0.34,1.56,0.64,1)}
        .member-status-card:hover .member-status-icon{transform:scale(1.1) rotate(5deg)}
        .member-status-icon.active{background:var(--green-lt);color:var(--green);animation:pulse 2s ease-in-out infinite}
        .member-status-icon.inactive{background:var(--red-lt);color:var(--red)}
        .member-status-icon.pending{background:var(--yellow-lt);color:#D97706;animation:float 3s ease-in-out infinite}
        .member-status-text h3{font-size:18px;font-weight:800;color:#1C1C1E}
        .member-status-text p{font-size:13px;color:#8E8E93;margin-top:2px}
        .member-detail-row{display:flex;justify-content:space-between;align-items:center;padding:10px 0;border-bottom:1px solid #F2F2F7;transition:all 0.3s ease}
        .member-detail-row:hover{background:rgba(255,82,0,0.02);margin:0 -10px;padding:10px;border-radius:8px}
        .member-detail-row:last-child{border-bottom:none}
        .member-detail-label{font-size:13px;color:#8E8E93;font-weight:500}
        .member-detail-value{font-size:14px;font-weight:700;color:#1C1C1E}
        .member-detail-value.green{color:var(--green)}
        .member-detail-value.primary{color:var(--primary)}
        .member-detail-value.yellow{color:#D97706}

        /* MAIN CONTAINER */
        .main-container{padding:60px 80px;max-width:1440px;margin:0 auto;position:relative}
        .main-container::before{content:'';position:absolute;top:0;left:50%;transform:translateX(-50%);width:80%;height:1px;background:linear-gradient(90deg,transparent,rgba(255,82,0,0.1),transparent)}

        /* SECTION HEADER */
        .section-header{display:flex;justify-content:space-between;align-items:flex-end;margin-bottom:28px;animation:fadeInUp 0.6s ease-out both}
        .section-title{font-size:24px;font-weight:800;color:#111;transition:color 0.3s ease}
        .section-title i{display:inline-block;transition:transform 0.3s ease}
        .section-header:hover .section-title i{transform:scale(1.2) rotate(10deg)}
        .section-subtitle{font-size:14px;color:#636366;margin-top:4px}

        /* PRICING CARDS */
        .pricing-grid{display:flex;gap:24px;padding:8px 4px;scroll-behavior:smooth;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;scrollbar-width:none;-ms-overflow-style:none}
        .pricing-grid::-webkit-scrollbar{display:none}
        .pricing-scroll-wrapper{position:relative;overflow:hidden;margin-bottom:60px}
        .pricing-scroll-wrapper::before,.pricing-scroll-wrapper::after{content:'';position:absolute;top:0;bottom:0;width:40px;pointer-events:none;z-index:2;transition:opacity 0.3s ease}
        .pricing-scroll-wrapper::before{left:0;background:linear-gradient(90deg,var(--light-bg),transparent)}
        .pricing-scroll-wrapper::after{right:0;background:linear-gradient(-90deg,var(--light-bg),transparent)}
        .pricing-card{background:var(--white);border:2px solid #E5E5EA;border-radius:16px;padding:32px;position:relative;transition:all 0.4s cubic-bezier(0.34,1.56,0.64,1);animation:cardEnter 0.6s ease-out both;opacity:0}
        .pricing-card:nth-child(1){animation-delay:0.1s}
        .pricing-card:nth-child(2){animation-delay:0.2s}
        .pricing-card:nth-child(3){animation-delay:0.3s}
        .pricing-card:hover{transform:translateY(-8px) scale(1.02);box-shadow:0 20px 40px rgba(0,0,0,0.12);border-color:rgba(255,82,0,0.4)}
        .pricing-card:hover .pricing-icon{animation:iconBounce 0.6s ease}
        .pricing-card:hover .pricing-name{color:var(--primary);transition:color 0.3s ease}
        .pricing-card.recommended{border-color:var(--primary);box-shadow:0 4px 20px rgba(255,82,0,.1);animation:cardEnter 0.6s ease-out 0.2s both,borderGlow 3s ease-in-out infinite;position:relative;overflow:visible;padding-top:42px}
        .pricing-card.recommended::after{content:'';position:absolute;top:-50%;left:-50%;width:200%;height:200%;background:radial-gradient(circle,rgba(255,82,0,0.03) 0%,transparent 70%);pointer-events:none;animation:shimmer 4s linear infinite;background-size:200% 100%}
        .pricing-card.recommended:hover{box-shadow:0 20px 40px rgba(255,82,0,0.2);border-color:var(--primary)}
        .popular-badge{position:absolute;top:-12px;left:50%;transform:translateX(-50%);background:var(--primary);color:var(--white);padding:6px 16px;border-radius:20px;font-size:11px;font-weight:800;letter-spacing:1px;animation:shimmer 2s linear infinite;background-size:200% 100%;background-image:linear-gradient(90deg,var(--primary),#FF8C42,var(--primary));z-index:10;box-shadow:0 4px 12px rgba(255,82,0,0.3);white-space:nowrap}
        .pricing-icon{width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:24px;margin-bottom:20px;transition:all 0.3s cubic-bezier(0.34,1.56,0.64,1)}
        .pricing-icon:hover{transform:scale(1.1) rotate(5deg)}
        .pricing-icon.silver{background:var(--blue-lt);color:var(--blue)}
        .pricing-icon.gold{background:var(--orange-lt);color:var(--orange)}
        .pricing-icon.platinum{background:var(--purple-lt);color:var(--purple)}
        .pricing-name{font-size:22px;font-weight:800;color:#1C1C1E;margin-bottom:4px;transition:color 0.3s ease}
        .pricing-desc{font-size:13px;color:#8E8E93;margin-bottom:20px;transition:color 0.3s ease}
        .pricing-card:hover .pricing-desc{color:#636366}
        .pricing-price{font-size:36px;font-weight:800;color:var(--primary);margin-bottom:4px;transition:all 0.3s ease}
        .pricing-card:hover .pricing-price{transform:scale(1.05);text-shadow:0 2px 10px rgba(255,82,0,0.2)}
        .pricing-price span{font-size:14px;color:#8E8E93;font-weight:500}
        .pricing-potongan{display:inline-flex;align-items:center;gap:6px;background:var(--green-lt);color:var(--green);padding:6px 12px;border-radius:20px;font-size:12px;font-weight:700;margin-bottom:24px;transition:all 0.3s ease}
        .pricing-card:hover .pricing-potongan{transform:scale(1.05);box-shadow:0 2px 8px rgba(52,199,89,0.2)}
        .pricing-features{list-style:none;margin-bottom:24px}
        .pricing-features li{display:flex;align-items:center;gap:10px;padding:10px 0;font-size:14px;color:#1C1C1E;border-bottom:1px solid #F2F2F7;transition:all 0.3s ease;animation:fadeInUp 0.4s ease-out both;opacity:0}
        .pricing-features li:nth-child(1){animation-delay:0.3s}
        .pricing-features li:nth-child(2){animation-delay:0.4s}
        .pricing-features li:nth-child(3){animation-delay:0.5s}
        .pricing-features li:nth-child(4){animation-delay:0.6s}
        .pricing-features li:nth-child(5){animation-delay:0.7s}
        .pricing-features li:last-child{border-bottom:none}
        .pricing-features li i{color:var(--green);font-size:14px;transition:transform 0.3s ease}
        .pricing-card:hover .pricing-features li i{transform:scale(1.2)}
        .btn-pilih{width:100%;background:var(--primary);color:var(--white);border:none;padding:14px;border-radius:10px;font-size:14px;font-weight:700;cursor:pointer;transition:all 0.3s cubic-bezier(0.34,1.56,0.64,1);display:flex;align-items:center;justify-content:center;gap:8px;position:relative;overflow:hidden}
        .btn-pilih::before{content:'';position:absolute;top:50%;left:50%;width:0;height:0;background:rgba(255,255,255,0.2);border-radius:50%;transform:translate(-50%,-50%);transition:width 0.6s,height 0.6s}
        .btn-pilih:hover::before{width:300px;height:300px}
        .btn-pilih:hover{background:var(--primary-hover);transform:translateY(-2px);box-shadow:0 8px 20px rgba(255,82,0,0.3)}
        .btn-pilih:active{transform:translateY(0) scale(0.98)}
        .btn-pilih:disabled{background:#C7C7CC;cursor:not-allowed;transform:none;box-shadow:none}
        .btn-pilih:disabled::before{display:none}

        /* ================================================================
           RIWAYAT LANGGANAN - TAMPILAN BARU & MODERN
           ================================================================ */

        .riwayat-section { margin-top: 40px; padding-bottom: 40px; }

        /* Timeline Container */
        .timeline-container { position: relative; padding-left: 0; }
        .timeline-container::before {
            content: '';
            position: absolute;
            left: 28px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg, var(--border) 0%, var(--border) 100%);
            z-index: 0;
        }

        /* Timeline Item */
        .timeline-item {
            position: relative;
            padding-left: 72px;
            margin-bottom: 24px;
            animation: fadeInUp 0.6s ease-out both;
        }
        .timeline-item:nth-child(1) { animation-delay: 0.1s; }
        .timeline-item:nth-child(2) { animation-delay: 0.2s; }
        .timeline-item:nth-child(3) { animation-delay: 0.3s; }
        .timeline-item:nth-child(4) { animation-delay: 0.4s; }
        .timeline-item:nth-child(5) { animation-delay: 0.5s; }

        /* Timeline Dot */
        .timeline-dot {
            position: absolute;
            left: 14px;
            top: 20px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            z-index: 2;
            border: 3px solid var(--white);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .timeline-item:hover .timeline-dot {
            transform: scale(1.15);
            box-shadow: 0 4px 16px rgba(0,0,0,0.15);
        }
        .timeline-dot.active { animation: timelineDotPulse 2s ease-in-out infinite; }

        /* Timeline Card */
        .timeline-card {
            background: var(--white);
            border: 1.5px solid #E5E5EA;
            border-radius: 20px;
            padding: 24px;
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
        }
        .timeline-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--card-accent, var(--primary));
            border-radius: 20px 20px 0 0;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .timeline-card:hover {
            transform: translateY(-4px) scale(1.01);
            box-shadow: 0 16px 40px rgba(0,0,0,0.1);
            border-color: rgba(255,82,0,0.2);
        }
        .timeline-card:hover::before { opacity: 1; }

        /* Card Header */
        .timeline-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            gap: 16px;
        }
        .timeline-card-title-area {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
        }
        .timeline-type-icon {
            width: 52px;
            height: 52px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .timeline-card:hover .timeline-type-icon { transform: scale(1.1) rotate(5deg); }
        .timeline-type-info h4 {
            font-size: 17px;
            font-weight: 800;
            color: #1C1C1E;
            margin-bottom: 2px;
            line-height: 1.3;
        }
        .timeline-type-info .timeline-type-sub {
            font-size: 12px;
            color: #8E8E93;
            font-weight: 500;
        }

        /* Status Badge */
        .timeline-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 7px 14px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.3px;
            white-space: nowrap;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        .timeline-card:hover .timeline-status-badge { transform: scale(1.05); }

        /* Progress Bar */
        .timeline-progress-wrap { margin-bottom: 20px; }
        .timeline-progress-label {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .timeline-progress-label span:first-child {
            font-size: 12px;
            font-weight: 600;
            color: #636366;
        }
        .timeline-progress-label span:last-child {
            font-size: 12px;
            font-weight: 700;
        }
        .timeline-progress-bar {
            width: 100%;
            height: 8px;
            background: #F2F2F7;
            border-radius: 50px;
            overflow: hidden;
            position: relative;
        }
        .timeline-progress-fill {
            height: 100%;
            border-radius: 50px;
            transition: width 1s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
        }
        .timeline-progress-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: shimmer 2s infinite;
        }

        /* Card Details Grid */
        .timeline-details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 12px;
        }
        .timeline-detail-item {
            background: #FAFAFA;
            border: 1px solid #F2F2F7;
            border-radius: 12px;
            padding: 14px 16px;
            transition: all 0.3s ease;
        }
        .timeline-detail-item:hover {
            background: #FFF;
            border-color: rgba(255,82,0,0.15);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
        }
        .timeline-detail-icon {
            width: 32px;
            height: 32px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            margin-bottom: 8px;
        }
        .timeline-detail-label {
            font-size: 11px;
            color: #8E8E93;
            font-weight: 500;
            margin-bottom: 3px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .timeline-detail-value {
            font-size: 14px;
            font-weight: 700;
            color: #1C1C1E;
            line-height: 1.3;
        }

        /* Empty State */
        .riwayat-empty {
            background: var(--white);
            border: 2px dashed #E5E5EA;
            border-radius: 24px;
            padding: 60px 40px;
            text-align: center;
            animation: fadeInUp 0.6s ease-out both;
            transition: all 0.3s ease;
        }
        .riwayat-empty:hover {
            border-color: rgba(255,82,0,0.3);
            background: linear-gradient(135deg, #FFF 0%, #FFF8F5 100%);
        }
        .riwayat-empty-icon {
            width: 80px;
            height: 80px;
            border-radius: 24px;
            background: linear-gradient(135deg, #FFF5F0 0%, #FFE8E0 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 32px;
            color: var(--primary);
            animation: float 4s ease-in-out infinite;
        }
        .riwayat-empty h3 {
            font-size: 18px;
            font-weight: 800;
            color: #1C1C1E;
            margin-bottom: 6px;
        }
        .riwayat-empty p {
            font-size: 14px;
            color: #8E8E93;
            line-height: 1.5;
        }
        .riwayat-empty-cta {
            margin-top: 20px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: var(--primary);
            color: #FFF;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.3s ease;
        }
        .riwayat-empty-cta:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255,82,0,0.3);
        }

        /* Responsive Timeline */
        @media(max-width: 768px) {
            .timeline-container::before { left: 20px; }
            .timeline-item { padding-left: 56px; }
            .timeline-dot {
                left: 8px;
                width: 24px;
                height: 24px;
                font-size: 10px;
            }
            .timeline-card-header { flex-direction: column; gap: 12px; }
            .timeline-details-grid { grid-template-columns: repeat(2, 1fr); }
            .timeline-card { padding: 18px; }
        }
        @media(max-width: 480px) {
            .timeline-details-grid { grid-template-columns: 1fr; }
        }

        /* ---- RESPONSIVE ---- */
        @media(max-width: 1100px) {
            .pricing-grid { gap: 16px; }
            .pricing-card { min-width: 280px !important; }
            .hero { flex-direction: column; padding: 40px; }
            .member-status-card { min-width: auto; width: 100%; }
            .main-container { padding: 40px; }
        }
        @media(max-width: 768px) {
            .nav-links { display: none; }
            .main-container { padding: 20px; }
            .hero { padding: 30px 20px; }
            .hero-title { font-size: 28px; }
        }

        /* PAYMENT MODAL */
        .booking-modal-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;z-index:2000;padding:20px;animation:fadeInModal .25s ease-out forwards}
        .booking-modal-overlay.active{display:flex}
        .booking-modal-overlay.active .summary-card{animation:slideInModal 0.4s cubic-bezier(0.16,1,0.3,1) forwards}
        .summary-card{background:#fff;border-radius:20px;padding:30px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 20px 40px rgba(0,0,0,.15);animation:slideInModal .3s cubic-bezier(.16,1,.3,1) forwards;-ms-overflow-style:none;scrollbar-width:none;transition:transform 0.3s ease}
        .summary-card:hover{transform:translateY(-2px)}
        .summary-card::-webkit-scrollbar{display:none}
        .booking-modal-close{position:absolute;top:20px;right:20px;background:var(--border-lt);border:none;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text-secondary);transition:var(--transition-smooth);z-index:10}
        .booking-modal-close:hover{background:var(--red-lt);color:var(--red);transform:rotate(90deg)}
        .summary-title{font-family:'Barlow Condensed',sans-serif;font-size:16px;font-weight:800;letter-spacing:.5px;color:var(--muted);margin-bottom:20px;text-transform:uppercase;text-align:center}
        .summary-item-info{display:flex;gap:14px;margin-bottom:20px;padding:16px;background:var(--bg);border-radius:12px;border:1px solid var(--border)}
        .summary-icon{width:48px;height:48px;border-radius:12px;background:var(--orange-lt);color:var(--orange);display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0}
        .summary-details{display:flex;flex-direction:column;justify-content:center}
        .summary-item-name{font-size:15px;font-weight:700;color:var(--text-primary)}
        .summary-item-sub{font-size:12px;color:var(--muted);margin-top:2px}
        .pricing-breakdown{border-top:1px solid var(--border-lt);padding:16px 0;display:flex;flex-direction:column;gap:10px}
        .price-row{display:flex;justify-content:space-between;font-size:12.5px;color:var(--text-secondary);font-weight:500}
        .price-row.total-row{margin-top:6px;font-size:14px;color:var(--text-primary);font-weight:800;align-items:center}
        .price-row.total-row .total-amount{font-size:24px;color:var(--orange);font-weight:900;animation:countUp .5s ease-out}
        .payment-section{border-top:1px solid var(--border-lt);padding:20px 0 10px}
        .payment-header{font-size:12.5px;font-weight:700;color:var(--text-primary);margin-bottom:12px;display:flex;align-items:center;gap:6px}
        .payment-header i{color:var(--muted)}
        .payment-options{display:flex;flex-direction:column;gap:10px;margin-top:16px}
        .payment-option{display:flex;align-items:center;gap:12px;padding:14px;border:1.5px solid var(--border);border-radius:10px;cursor:pointer;transition:var(--transition-smooth);background:var(--white);user-select:none}
        .payment-option:hover{border-color:var(--orange);transform:translateY(-2px);box-shadow:0 4px 12px rgba(255,90,31,.1)}
        .payment-option.selected{border-color:var(--orange);background:var(--orange-lt);animation:scaleIn 0.3s ease-out}
        .payment-radio{width:20px;height:20px;border-radius:50%;border:2px solid var(--muted);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:var(--transition-smooth)}
        .payment-option.selected .payment-radio{border-color:var(--orange)}
        .payment-radio::after{content:'';width:10px;height:10px;border-radius:50%;background:var(--orange);display:none}
        .payment-option.selected .payment-radio::after{display:block;animation:scaleIn 0.2s ease-out}
        .payment-info{flex:1}
        .payment-name{font-size:13px;font-weight:700;color:var(--text-primary)}
        .payment-desc{font-size:11px;color:var(--muted);margin-top:2px}
        .payment-icon{width:40px;height:40px;border-radius:10px;background:var(--orange-lt);display:flex;align-items:center;justify-content:center;color:var(--orange);font-size:16px;flex-shrink:0}
        .btn-booking{width:100%;background:var(--orange);color:#fff;border:none;border-radius:12px;padding:14px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:16px;transition:var(--transition-smooth);position:relative;overflow:hidden}
        .btn-booking::before{content:'';position:absolute;top:50%;left:50%;width:0;height:0;background:rgba(255,255,255,.2);border-radius:50%;transform:translate(-50%,-50%);transition:width .6s,height .6s}
        .btn-booking:hover::before{width:400px;height:400px}
        .btn-booking:hover:not(:disabled){background:var(--orange-hover);transform:translateY(-2px);box-shadow:0 10px 30px rgba(255,90,31,.4)}
        .btn-booking:disabled{background:var(--muted);cursor:not-allowed}
        .btn-booking i{transition:transform 0.3s ease}
        .btn-booking:hover i{transform:translateX(3px)}
        .booking-disclaimer{display:flex;align-items:center;justify-content:center;gap:6px;font-size:11px;color:var(--muted);margin-top:10px;font-weight:500}
        .booking-disclaimer i{color:var(--green);animation:pulse 2s ease-in-out infinite}

        /* PAYMENT INSTRUCTION MODAL */
        .instruction-title{font-family:'Barlow Condensed',sans-serif;font-size:16px;font-weight:800;letter-spacing:.5px;color:var(--muted);margin-bottom:20px;text-transform:uppercase;text-align:center}
        .switch-tabs-row{display:flex;gap:6px;margin-bottom:16px;background:var(--border-lt);padding:4px;border-radius:10px;border:1px solid var(--border)}
        .switch-tab-btn{flex:1;padding:10px;border:none;border-radius:8px;font-family:inherit;font-size:12px;font-weight:700;cursor:pointer;transition:var(--transition-smooth);background:transparent;color:var(--text-secondary)}
        .switch-tab-btn.active{background:#fff;color:var(--orange);box-shadow:0 2px 6px rgba(0,0,0,.05)}
        .countdown-box{background:var(--orange-lt);border:1px solid rgba(255,90,31,.15);border-radius:10px;padding:12px 16px;display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:20px}
        .countdown-box i{color:var(--orange);animation:pulse 2s infinite}
        .countdown-text{color:var(--orange-hover);font-weight:700;font-size:12px}
        .total-display-box{background:var(--bg);padding:14px 18px;border-radius:12px;margin-bottom:20px;border:1px solid var(--border);text-align:center}
        .total-display-label{font-size:11px;color:var(--text-secondary);font-weight:600;text-transform:uppercase}
        .total-display-amount{font-size:24px;color:var(--orange);font-weight:900;margin-top:4px}
        .instr-va-box{text-align:left}
        .instr-qris-box{display:none;align-items:center;flex-direction:column}
        .instr-qris-box.active{display:flex}
        .bank-info-card{background:linear-gradient(135deg,#f8fafc 0%,#f1f5f9 100%);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:20px;text-align:left;position:relative;overflow:hidden}
        .bank-info-card::before{content:'';position:absolute;top:-20px;right:-20px;width:80px;height:80px;background:rgba(255,90,31,.05);border-radius:50%}
        .bank-header{display:flex;align-items:center;gap:12px;margin-bottom:16px}
        .bank-icon{width:44px;height:44px;background:var(--orange);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;box-shadow:0 4px 12px rgba(255,90,31,.3)}
        .bank-title{font-size:13px;font-weight:800;color:var(--text-primary)}
        .bank-sub{font-size:11px;color:var(--muted);font-weight:500}
        .va-section-label{font-size:11.5px;font-weight:700;color:var(--text-secondary);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px}
        .va-input-row{display:flex;gap:8px}
        .va-input-wrap{flex:1;background:#fff;border:2px solid var(--border);border-radius:12px;padding:14px 16px;display:flex;align-items:center;gap:10px;transition:var(--transition-smooth)}
        .va-input-wrap:hover{border-color:var(--orange);box-shadow:0 4px 16px rgba(255,90,31,.1)}
        .va-input-wrap i{color:var(--orange);font-size:14px}
        .va-input-wrap input{border:none;background:transparent;font-weight:800;text-align:center;font-size:18px;letter-spacing:2px;color:var(--text-primary);font-family:'Plus Jakarta Sans',monospace;width:100%;outline:none}
        .btn-copy-va{border-radius:12px;font-size:13px;padding:14px 18px;display:flex;align-items:center;gap:6px;white-space:nowrap;background:var(--orange);color:#fff;border:none;font-weight:700;box-shadow:0 4px 12px rgba(255,90,31,.3);cursor:pointer;transition:var(--transition-smooth);font-family:inherit}
        .btn-copy-va:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(255,90,31,.4)}
        .steps-label{font-size:11.5px;font-weight:700;color:var(--text-secondary);margin-bottom:14px;text-transform:uppercase;letter-spacing:.5px;margin-top:20px}
        .step-item{display:flex;gap:14px;align-items:flex-start;padding:14px 16px;background:#fafafa;border-radius:12px;border:1px solid var(--border-lt);transition:var(--transition-smooth);margin-bottom:12px}
        .step-item:hover{background:#fff;border-color:var(--orange);transform:translateX(4px)}
        .step-item:last-child{margin-bottom:0}
        .step-num{width:28px;height:28px;background:var(--orange-lt);color:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;flex-shrink:0;margin-top:2px}
        .step-title{font-size:13px;font-weight:700;color:var(--text-primary);margin-bottom:2px}
        .step-desc{font-size:12px;color:var(--text-secondary);line-height:1.5}
        .qris-title{font-size:12.5px;font-weight:700;color:var(--text-primary);margin-bottom:12px}
        .qris-img-wrap{background:#fff;padding:12px;border:1px solid var(--border);border-radius:12px;width:fit-content;margin-bottom:16px;box-shadow:0 4px 12px rgba(0,0,0,.05)}
        .qris-img{display:block;width:170px;height:180px;object-fit:contain}
        .qris-steps-list{text-align:left;font-size:11.5px;color:var(--text-secondary);padding-left:20px;line-height:1.6;display:flex;flex-direction:column;gap:6px;width:100%}
        .modal-divider{border:none;height:1px;background:var(--border-lt);margin:20px 0}
        .btn-done-pay{width:100%;background:var(--orange);color:#fff;border:none;border-radius:12px;padding:14px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:var(--transition-smooth);position:relative;overflow:hidden}
        .btn-done-pay::before{content:'';position:absolute;top:50%;left:50%;width:0;height:0;background:rgba(255,255,255,.2);border-radius:50%;transform:translate(-50%,-50%);transition:width .6s,height .6s}
        .btn-done-pay:hover::before{width:400px;height:400px}
        .btn-done-pay:hover{background:var(--orange-hover);transform:translateY(-2px);box-shadow:0 10px 30px rgba(255,90,31,.4)}
        .btn-done-pay i{transition:transform 0.3s ease}
        .btn-done-pay:hover i{transform:scale(1.2)}

        /* PAGE LOADER */
        .page-loader{position:fixed;top:0;left:0;width:100%;height:100%;background:#0B0B0C;display:flex;align-items:center;justify-content:center;gap:8px;z-index:99999;transition:opacity 0.5s ease,visibility 0.5s ease}
        .page-loader.hidden{opacity:0;visibility:hidden;pointer-events:none}
        .loader-ball{width:12px;height:12px;border-radius:50%;background:var(--primary);animation:loaderBounce 0.6s ease-in-out infinite alternate}
        .loader-ball:nth-child(2){animation-delay:0.2s}
        .loader-ball:nth-child(3){animation-delay:0.4s}

        /* UPLOAD BUKTI PEMBAYARAN */
        .bukti-upload-box{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:8px;padding:22px;border:2px dashed var(--border);border-radius:12px;cursor:pointer;text-align:center;color:var(--muted);font-size:12px;font-weight:600;transition:var(--transition-smooth);margin-top:12px}
        .bukti-upload-box:hover{border-color:var(--orange);background:var(--orange-lt);color:var(--orange)}
        .bukti-upload-box i{font-size:22px;color:var(--orange)}
        .bukti-upload-box.filled{border-style:solid;border-color:var(--green);color:var(--green);background:var(--green-lt)}
        .bukti-upload-box.filled i{color:var(--green)}
        .bukti-preview-wrap{display:none;margin-top:10px;text-align:center}
        .bukti-preview-wrap img{max-width:100%;max-height:180px;border-radius:12px;border:1.5px solid var(--border)}

         /* ============================================
   MATIKAN SEMUA ANIMASI SWEETALERT2 
   ============================================ */
        .swal2-popup {
            animation: none !important;
            transition: none !important;
        }

        .swal2-icon {
            animation: none !important;
        }

        .swal2-icon.swal2-success .swal2-success-ring,
        .swal2-icon.swal2-success [class^="swal2-success-line"],
        .swal2-icon.swal2-error [class^="swal2-x-mark-line"],
        .swal2-icon.swal2-warning {
            animation: none !important;
        }

        /* cegah body/html digeser oleh kompensasi scrollbar SweetAlert */
        html.swal2-shown,
        body.swal2-shown,
        body.swal2-height-auto {
            padding-right: 0 !important;
   }
</style>
</head>
<body>

<div class="page-loader" id="pageLoader">
    <div class="loader-ball"></div>
    <div class="loader-ball"></div>
    <div class="loader-ball"></div>
</div>

<div class="scroll-progress" id="scrollProgress"></div>

<?php $path_prefix = '../'; include '../includes/navbar.php'; ?>

<!-- HERO SECTION -->
<section class="hero">
    <div class="floating-ball ball-1"></div>
    <div class="floating-ball ball-2"></div>
    <div class="floating-ball ball-3"></div>
    <div class="floating-ball ball-4"></div>
    <div class="floating-ball ball-5"></div>
    <div class="hero-glow"></div>

    <div class="hero-left">
        <div class="hero-badge">
            <i class="fa-solid fa-crown"></i> LANGGANAN MEMBER
        </div>
        <h1 class="hero-title">Jadi Member,<br><span>Main Makin Hemat!</span></h1>
        <p class="hero-desc">Dapatkan potongan harga khusus, prioritas jadwal, dan promo eksklusif dengan berlangganan member HoopBall.</p>
    </div>

    <!-- MEMBER STATUS CARD -->
    <div class="member-status-card">
        <div class="member-status-header">
            <div class="member-status-icon <?php echo $has_member ? 'active' : ($member_pending ? 'pending' : 'inactive'); ?>">
                <i class="fa-solid <?php echo $has_member ? 'fa-crown' : ($member_pending ? 'fa-clock' : 'fa-user'); ?>"></i>
            </div>
            <div class="member-status-text">
                <h3>
                    <?php
                    if ($has_member) {
                        echo 'Member ' . htmlspecialchars($member_tipe) . ' Aktif';
                    } elseif ($member_pending) {
                        echo 'Menunggu Konfirmasi';
                    } else {
                        echo 'Belum Berlangganan';
                    }
                    ?>
                </h3>
                <p>
                    <?php
                    if ($has_member) {
                        echo 'Nikmati keuntungan member Anda';
                    } elseif ($member_pending) {
                        echo 'Pendaftaran member sedang diproses oleh admin';
                    } else {
                        echo 'Daftar sekarang untuk mendapatkan keuntungan';
                    }
                    ?>
                </p>
            </div>
        </div>

        <?php if ($has_member): ?>
        <div class="member-detail-row">
            <span class="member-detail-label">Tipe Member</span>
            <span class="member-detail-value primary"><?php echo htmlspecialchars($member_tipe); ?></span>
        </div>
        <div class="member-detail-row">
            <span class="member-detail-label">Potongan Harga</span>
            <span class="member-detail-value green"><?php echo rupiahFormat($member_aktif['Potongan_Harga']); ?> /booking</span>
        </div>
        <div class="member-detail-row">
            <span class="member-detail-label">Tanggal Mulai</span>
            <span class="member-detail-value"><?php echo formatTanggal($member_aktif['Tanggal_Mulai']); ?></span>
        </div>
        <div class="member-detail-row">
            <span class="member-detail-label">Berlaku Sampai</span>
            <span class="member-detail-value"><?php echo formatTanggal($member_aktif['Tanggal_Selesai']); ?></span>
        </div>
        <div class="member-detail-row">
            <span class="member-detail-label">Sisa Hari</span>
            <span class="member-detail-value green">
               <?php
                    $tgl_selesai = $member_aktif['Tanggal_Selesai'];
                    if (is_object($tgl_selesai) && method_exists($tgl_selesai, 'format')) {
                        $timestamp_selesai = $tgl_selesai->getTimestamp();
                    } else {
                        $timestamp_selesai = strtotime($tgl_selesai);
                    }
                    $sisa = ceil(($timestamp_selesai - time()) / 86400);
                    echo $sisa > 0 ? $sisa . ' hari' : 'Hari ini berakhir';
                ?>
            </span>
        </div>
        <?php elseif ($member_pending): ?>
        <div class="member-detail-row">
            <span class="member-detail-label">Tipe Member</span>
            <span class="member-detail-value primary"><?php echo htmlspecialchars($member_pending['Nama_Tipe']); ?></span>
        </div>
        <div class="member-detail-row">
            <span class="member-detail-label">Total Bayar</span>
            <span class="member-detail-value"><?php echo rupiahFormat($member_pending['Total_Bayar']); ?></span>
        </div>
        <div class="member-detail-row">
            <span class="member-detail-label">Tanggal Daftar</span>
            <span class="member-detail-value"><?php echo formatTanggal($member_pending['Created_Date']); ?></span>
        </div>
        <div class="member-detail-row">
            <span class="member-detail-label">Status</span>
            <span class="member-detail-value yellow">Menunggu Konfirmasi Admin</span>
        </div>
        <?php else: ?>
        <div class="member-detail-row">
            <span class="member-detail-label">Status</span>
            <span class="member-detail-value" style="color: var(--red);">Non-Member</span>
        </div>
        <div class="member-detail-row">
            <span class="member-detail-label">Potongan Harga</span>
            <span class="member-detail-value">-</span>
        </div>
        <div class="member-detail-row">
            <span class="member-detail-label">Prioritas Jadwal</span>
            <span class="member-detail-value">-</span>
        </div>
        <p style="font-size: 13px; color: #8E8E93; margin-top: 16px; text-align: center; line-height: 1.5;">
            Pilih paket member di bawah untuk mulai berlangganan
        </p>
        <?php endif; ?>
    </div>
</section>

<!-- MAIN CONTENT -->
<main class="main-container">

    <!-- PILIH PAKET MEMBER -->
    <section>
        <div class="section-header reveal">
            <div>
                <h2 class="section-title"><i class="fa-solid fa-crown" style="color:var(--primary)"></i> Pilih Paket Member</h2>
                <p class="section-subtitle">Pilih tipe member yang sesuai dengan kebutuhan Anda.</p>
            </div>
        </div>

        <!-- Search & Scroll Controls -->
        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px; animation: fadeInUp 0.6s ease-out both;">
            <div style="position: relative; flex: 1; max-width: 400px;">
                <i class="fa-solid fa-magnifying-glass" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--muted); font-size: 14px;"></i>
                <input type="text" id="searchTipeMember" placeholder="Cari tipe member..."
                    style="width: 100%; padding: 12px 16px 12px 42px; border: 2px solid var(--border); border-radius: 12px; font-family: inherit; font-size: 14px; font-weight: 500; color: var(--text-primary); background: #fff; outline: none; transition: all 0.3s ease;"
                    onfocus="this.style.borderColor='var(--orange)'; this.style.boxShadow='0 0 0 3px var(--orange-glow)';"
                    onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none';"
                    oninput="filterTipeMember(this.value)">
            </div>
            <div style="display: flex; gap: 8px; flex-shrink: 0;">
                <button type="button" onclick="scrollPricing('left')" class="scroll-arrow" style="width: 40px; height: 40px; border-radius: 50%; border: 2px solid var(--border); background: #fff; color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);" onmouseover="this.style.borderColor='var(--orange)'; this.style.color='var(--orange)'; this.style.transform='scale(1.1)'; this.style.boxShadow='0 4px 12px rgba(255,90,31,0.2)';" onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--text-secondary)'; this.style.transform='scale(1)'; this.style.boxShadow='none';">
                    <i class="fa-solid fa-chevron-left"></i>
                </button>
                <button type="button" onclick="scrollPricing('right')" class="scroll-arrow" style="width: 40px; height: 40px; border-radius: 50%; border: 2px solid var(--border); background: #fff; color: var(--text-secondary); cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 14px; transition: all 0.3s cubic-bezier(0.34,1.56,0.64,1);" onmouseover="this.style.borderColor='var(--orange)'; this.style.color='var(--orange)'; this.style.transform='scale(1.1)'; this.style.boxShadow='0 4px 12px rgba(255,90,31,0.2)';" onmouseout="this.style.borderColor='var(--border)'; this.style.color='var(--text-secondary)'; this.style.transform='scale(1)'; this.style.boxShadow='none';">
                    <i class="fa-solid fa-chevron-right"></i>
                </button>
            </div>
        </div>

        <div class="pricing-scroll-wrapper" style="position: relative; overflow: hidden; margin-bottom: 60px;">
            <div class="pricing-grid reveal-stagger" id="pricingGrid" style="display: flex; gap: 24px; overflow-x: auto; scroll-behavior: smooth; padding: 8px 4px; scroll-snap-type: x mandatory; -webkit-overflow-scrolling: touch; scrollbar-width: none; -ms-overflow-style: none;">
                <?php
                $icon_map = ['Silver' => 'fa-medal', 'Gold' => 'fa-trophy', 'Platinum' => 'fa-crown'];
                $class_map = ['Silver' => 'silver', 'Gold' => 'gold', 'Platinum' => 'platinum'];

                $is_blocked = ($has_member || $member_pending);

                foreach ($tipe_member_list as $tipe):
                    $is_recommended = ($tipe['Nama_Tipe'] === 'Gold');
                    $icon = $icon_map[$tipe['Nama_Tipe']] ?? 'fa-star';
                    $cls = $class_map[$tipe['Nama_Tipe']] ?? 'silver';
                ?>
            <div class="pricing-card stagger-item <?php echo $is_recommended ? 'recommended' : ''; ?>" data-nama="<?php echo strtolower(htmlspecialchars($tipe['Nama_Tipe'])); ?>" data-harga="<?php echo $tipe['Harga_Member']; ?>" style="min-width: 320px; flex-shrink: 0; scroll-snap-align: start;">
                <?php if ($is_recommended): ?>
                <div class="popular-badge">POPULER</div>
                <?php endif; ?>
                <div class="pricing-icon <?php echo $cls; ?>">
                    <i class="fa-solid <?php echo $icon; ?>"></i>
                </div>
                <h3 class="pricing-name"><?php echo htmlspecialchars($tipe['Nama_Tipe']); ?></h3>
                <p class="pricing-desc">Paket member <?php echo htmlspecialchars($tipe['Nama_Tipe']); ?> 30 hari</p>
                <div class="pricing-price">
                    <?php echo rupiahFormat($tipe['Harga_Member']); ?>
                    <span>/ 30 hari</span>
                </div>
                <div class="pricing-potongan">
                    <i class="fa-solid fa-tag"></i> Hemat <?php echo rupiahFormat($tipe['Potongan_Harga']); ?> per booking
                </div>
                <ul class="pricing-features">
                    <li><i class="fa-solid fa-check"></i> Potongan <?php echo rupiahFormat($tipe['Potongan_Harga']); ?> per booking</li>
                    <li><i class="fa-solid fa-check"></i> Masa aktif 30 hari</li>
                    <li><i class="fa-solid fa-check"></i> Prioritas jadwal</li>
                    <li><i class="fa-solid fa-check"></i> Promo eksklusif member</li>
                    <?php if ($tipe['Nama_Tipe'] === 'Platinum'): ?>
                    <li><i class="fa-solid fa-check"></i> Diskon pembelian alat 5%</li>
                    <?php endif; ?>
                </ul>
                <button class="btn-pilih"
                        onclick="bukaModal(<?php echo $tipe['ID_Tipe']; ?>, '<?php echo htmlspecialchars($tipe['Nama_Tipe']); ?>', <?php echo $tipe['Harga_Member']; ?>)"
                        <?php echo $is_blocked ? 'disabled' : ''; ?>>
                    <?php
                    if ($has_member) {
                        echo 'Sudah Aktif';
                    } elseif ($member_pending) {
                        echo 'Menunggu Konfirmasi';
                    } else {
                        echo '<i class="fa-solid fa-crown"></i> Pilih Paket Ini';
                    }
                    ?>
                </button>
            </div>
            <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- ================================================================
         RIWAYAT LANGGANAN - TAMPILAN BARU & MODERN (TIMELINE)
         ================================================================ -->
    <section class="riwayat-section">
        <div class="section-header reveal">
            <div>
                <h2 class="section-title"><i class="fa-solid fa-clock-rotate-left" style="color:var(--primary)"></i> Riwayat Langganan</h2>
                <p class="section-subtitle">Lihat riwayat langganan member Anda.</p>
            </div>
        </div>

        <?php if (count($riwayat_langganan) > 0): ?>
        <div class="timeline-container">
            <?php foreach ($riwayat_langganan as $idx => $r):
                $cfg = $status_config[$r['Status']] ?? $status_config[0];
                $tipe_cfg = $tipe_config[$r['Nama_Tipe']] ?? ['icon' => 'fa-star', 'gradient' => 'linear-gradient(135deg, #F3F4F6 0%, #D1D5DB 100%)', 'text_color' => '#374151'];
                $r_tgl_mulai = formatTanggal($r['Tanggal_Mulai']);
                $r_tgl_selesai = formatTanggal($r['Tanggal_Selesai']);
                $r_sisa_hari = isset($r['Sisa_Hari']) ? $r['Sisa_Hari'] : 0;

                // Hitung progress berdasarkan sisa hari untuk status aktif
                $progress = $cfg['progress'];
                if ($r['Status'] == 1 && $r_sisa_hari > 0) {
                    $progress = max(10, min(100, round((30 - $r_sisa_hari) / 30 * 100)));
                }
            ?>
            <div class="timeline-item">
                <!-- Timeline Dot -->
                <div class="timeline-dot <?php echo $r['Status'] == 1 ? 'active' : ''; ?>"
                     style="background: <?php echo $cfg['icon_bg']; ?>; color: <?php echo $cfg['icon_color']; ?>;">
                    <i class="fa-solid <?php echo $cfg['icon']; ?>"></i>
                </div>

                <!-- Timeline Card -->
                <div class="timeline-card" style="--card-accent: <?php echo $cfg['timeline_color']; ?>">
                    <!-- Card Header -->
                    <div class="timeline-card-header">
                        <div class="timeline-card-title-area">
                            <div class="timeline-type-icon" style="background: <?php echo $tipe_cfg['gradient']; ?>; color: <?php echo $tipe_cfg['text_color']; ?>;">
                                <i class="fa-solid <?php echo $tipe_cfg['icon']; ?>"></i>
                            </div>
                            <div class="timeline-type-info">
                                <h4>Member <?php echo htmlspecialchars($r['Nama_Tipe']); ?></h4>
                                <div class="timeline-type-sub">
                                    <i class="fa-regular fa-calendar" style="margin-right: 4px;"></i>
                                    <?php echo $r_tgl_mulai; ?> &ndash; <?php echo $r_tgl_selesai; ?>
                                </div>
                            </div>
                        </div>
                        <div class="timeline-status-badge" style="background: <?php echo $cfg['badge_bg']; ?>; color: <?php echo $cfg['badge_color']; ?>;">
                            <i class="fa-solid <?php echo $cfg['icon']; ?>"></i>
                            <?php echo $cfg['badge_text']; ?>
                        </div>
                    </div>

                    <!-- Progress Bar (hanya untuk aktif) -->
                    <?php if ($r['Status'] == 1): ?>
                    <div class="timeline-progress-wrap">
                        <div class="timeline-progress-label">
                            <span>Progress Langganan</span>
                            <span style="color: <?php echo $cfg['timeline_color']; ?>"><?php echo $r_sisa_hari; ?> hari tersisa</span>
                        </div>
                        <div class="timeline-progress-bar">
                            <div class="timeline-progress-fill" style="width: <?php echo $progress; ?>%; background: linear-gradient(90deg, <?php echo $cfg['timeline_color']; ?>, <?php echo $cfg['timeline_color']; ?>cc);"></div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Details Grid -->
                    <div class="timeline-details-grid">
                        <div class="timeline-detail-item">
                            <div class="timeline-detail-icon" style="background: <?php echo $cfg['icon_bg']; ?>; color: <?php echo $cfg['icon_color']; ?>;">
                                <i class="fa-solid fa-money-bill-wave"></i>
                            </div>
                            <div class="timeline-detail-label">Total Bayar</div>
                            <div class="timeline-detail-value"><?php echo rupiahFormat($r['Total_Bayar']); ?></div>
                        </div>
                        <div class="timeline-detail-item">
                            <div class="timeline-detail-icon" style="background: var(--blue-lt); color: var(--blue);">
                                <i class="fa-solid fa-credit-card"></i>
                            </div>
                            <div class="timeline-detail-label">Metode</div>
                            <div class="timeline-detail-value"><?php echo htmlspecialchars($r['Metode_Pembayaran']); ?></div>
                        </div>
                        <div class="timeline-detail-item">
                            <div class="timeline-detail-icon" style="background: var(--green-lt); color: var(--green);">
                                <i class="fa-solid fa-tag"></i>
                            </div>
                            <div class="timeline-detail-label">Potongan</div>
                            <div class="timeline-detail-value"><?php echo rupiahFormat($r['Potongan_Harga']); ?>/booking</div>
                        </div>
                        <?php if ($r['Status'] == 1 && $r_sisa_hari > 0): ?>
                        <div class="timeline-detail-item">
                            <div class="timeline-detail-icon" style="background: var(--orange-lt); color: var(--orange);">
                                <i class="fa-solid fa-hourglass-half"></i>
                            </div>
                            <div class="timeline-detail-label">Sisa Hari</div>
                            <div class="timeline-detail-value" style="color: <?php echo $cfg['timeline_color']; ?>"><?php echo $r_sisa_hari; ?> hari</div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <!-- Empty State -->
        <div class="riwayat-empty">
            <div class="riwayat-empty-icon">
                <i class="fa-solid fa-inbox"></i>
            </div>
            <h3>Belum Ada Riwayat Langganan</h3>
            <p>Daftar langganan Anda akan muncul di sini setelah Anda berlangganan member.</p>
            <a href="#pricingGrid" class="riwayat-empty-cta" onclick="document.getElementById('pricingGrid').scrollIntoView({behavior:'smooth'});">
                <i class="fa-solid fa-crown"></i> Lihat Paket Member
            </a>
        </div>
        <?php endif; ?>
    </section>

</main>

<!-- FOOTER -->
<?php include '../includes/footer.php'; ?>

<!-- MODAL 1: CHECKOUT -->
<div class="booking-modal-overlay" id="checkoutModal">
    <div class="summary-card">
        <button class="booking-modal-close" onclick="tutupCheckoutModal()"><i class="fa-solid fa-xmark"></i></button>
        <p class="summary-title">Ringkasan Pembelian</p>
        <div class="summary-item-info">
            <div class="summary-icon"><i class="fa-solid fa-crown"></i></div>
            <div class="summary-details">
                <span class="summary-item-name" id="summaryNamaTipe">Member</span>
                <span class="summary-item-sub">Paket langganan 30 hari</span>
            </div>
        </div>
        <div class="pricing-breakdown">
            <div class="price-row">
                <span>Harga Paket</span>
                <span id="summaryHarga">Rp 0</span>
            </div>
            <div class="price-row total-row">
                <span>Total Bayar</span>
                <span class="total-amount" id="summaryTotal">Rp 0</span>
            </div>
        </div>
        <div class="payment-section">
            <div class="payment-header"><i class="fa-solid fa-wallet"></i> Pilih Metode Pembayaran</div>
            <div class="payment-options">
                <div class="payment-option selected" data-method="Transfer Bank" onclick="selectPayment(this)">
                    <div class="payment-radio"></div>
                    <div class="payment-icon"><i class="fa-solid fa-building-columns"></i></div>
                    <div class="payment-info">
                        <div class="payment-name">Transfer Bank</div>
                        <div class="payment-desc">Virtual Account</div>
                    </div>
                </div>
                <div class="payment-option" data-method="QRIS" onclick="selectPayment(this)">
                    <div class="payment-radio"></div>
                    <div class="payment-icon"><i class="fa-solid fa-qrcode"></i></div>
                    <div class="payment-info">
                        <div class="payment-name">QRIS</div>
                        <div class="payment-desc">Scan & Pay</div>
                    </div>
                </div>
            </div>
        </div>
        <button class="btn-booking" id="btnLanjutBayar" onclick="lanjutKePembayaran()" disabled>
            Lanjutkan Pembayaran <i class="fa-solid fa-arrow-right"></i>
        </button>
        <div class="booking-disclaimer">
            <i class="fa-solid fa-shield-halved"></i> Pembayaran aman & terenkripsi
        </div>
    </div>
</div>

<!-- MODAL 2: INSTRUKSI PEMBAYARAN -->
<div class="booking-modal-overlay" id="paymentInstructionModal">
    <div class="summary-card" style="max-width:460px;text-align:center">
        <button class="booking-modal-close" onclick="tutupInstructionModal()"><i class="fa-solid fa-xmark"></i></button>
        <p class="instruction-title">Instruksi Pembayaran</p>
        <div class="switch-tabs-row">
            <button id="btnSwitchVA" class="switch-tab-btn active" onclick="switchPaymentMethod('Transfer Bank')">
                <i class="fa-solid fa-university" style="margin-right:4px"></i> Virtual Account
            </button>
            <button id="btnSwitchQRIS" class="switch-tab-btn" onclick="switchPaymentMethod('QRIS')">
                <i class="fa-solid fa-qrcode" style="margin-right:4px"></i> QRIS Scan
            </button>
        </div>
        <div class="countdown-box">
            <i class="fa-solid fa-clock"></i>
            <p class="countdown-text">Selesaikan pembayaran dalam <span id="paymentCountdown">15:00</span></p>
        </div>
        <div class="total-display-box">
            <div class="total-display-label">Total Tagihan</div>
            <div class="total-display-amount" id="paymentTotalAmount">Rp 0</div>
        </div>

        <div id="instruksiTransfer" class="instr-va-box">
            <div class="bank-info-card">
                <div class="bank-header">
                    <div class="bank-icon"><i class="fa-solid fa-building-columns"></i></div>
                    <div>
                        <div class="bank-title">Virtual Account</div>
                        <div class="bank-sub">Mandiri / BCA / BNI / BRI</div>
                    </div>
                </div>
                <div class="va-section-label">Nomor Virtual Account</div>
                <div class="va-input-row">
                    <div class="va-input-wrap">
                        <i class="fa-solid fa-hashtag"></i>
                        <input type="text" id="vaNumber" value="8801281234567890" readonly>
                    </div>
                    <button class="btn-copy-va" id="btnCopyVA" onclick="copyVA()">
                        <i class="fa-regular fa-copy"></i> Salin
                    </button>
                </div>
            </div>
            <div class="steps-label">Cara Pembayaran</div>
            <div class="step-item">
                <div class="step-num">1</div>
                <div>
                    <div class="step-title">Buka Aplikasi Banking</div>
                    <div class="step-desc">Pilih menu <strong style="color:var(--primary)">Transfer > Virtual Account</strong> pada M-Banking atau ATM Anda.</div>
                </div>
            </div>
            <div class="step-item">
                <div class="step-num">2</div>
                <div>
                    <div class="step-title">Masukkan Nomor VA</div>
                    <div class="step-desc">Masukkan nomor Virtual Account <strong style="color:var(--primary)">8801281234567890</strong>.</div>
                </div>
            </div>
            <div class="step-item" style="margin-bottom:0">
                <div class="step-num">3</div>
                <div>
                    <div class="step-title">Konfirmasi Pembayaran</div>
                    <div class="step-desc">Nominal akan otomatis muncul sesuai total tagihan. Konfirmasi dan selesaikan transaksi.</div>
                </div>
            </div>
        </div>

        <div id="instruksiQRIS" class="instr-qris-box">
            <div class="qris-title">Pindai Kode QRIS Resmi HoopBall</div>
            <div class="qris-img-wrap">
                <img id="qrisImage" src="" alt="QRIS Code" class="qris-img">
            </div>
            <ul class="qris-steps-list">
                <li>Buka aplikasi e-wallet (GoPay, OVO, Dana, LinkAja) atau Mobile Banking.</li>
                <li>Pilih opsi <strong>Scan / Bayar QRIS</strong>.</li>
                <li>Arahkan kamera ke kode QR, lalu selesaikan pembayaran.</li>
            </ul>
        </div>

        <hr class="modal-divider">
        <form method="POST" action="" id="formPembayaran" enctype="multipart/form-data">
            <input type="hidden" name="id_tipe" id="formIdTipe">
            <input type="hidden" name="metode_pembayaran" id="formMetode">

            <!-- Upload Bukti Pembayaran -->
            <div style="margin: 20px 0;">
                <div class="payment-header" style="margin-bottom: 8px;">
                    <i class="fa-solid fa-receipt"></i> Unggah Bukti Pembayaran <span style="color: var(--red);">*</span>
                </div>
                <label for="buktiPembayaranInput" class="bukti-upload-box" id="buktiUploadBox">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <span id="buktiUploadText">Klik untuk pilih file (JPG, PNG, atau PDF, maks 5MB)</span>
                </label>
                <input type="file" name="bukti_pembayaran" id="buktiPembayaranInput" accept=".jpg,.jpeg,.png,.pdf" style="display:none" onchange="handleBuktiFileChange(this)">
                <div class="bukti-preview-wrap" id="buktiPreviewWrap">
                    <img id="buktiPreviewImg" src="" alt="Preview Bukti Pembayaran">
                </div>
            </div>

            <button type="submit" name="beli_langganan" class="btn-done-pay" id="btnDonePayment">
                Saya Sudah Bayar <i class="fa-solid fa-circle-check"></i>
            </button>
        </form>
    </div>
</div>

<script>
/* BUKTI PEMBAYARAN HANDLER */
let buktiFile = null;

function handleBuktiFileChange(input) {
    const file = input.files[0];
    const box = document.getElementById('buktiUploadBox');
    const textEl = document.getElementById('buktiUploadText');
    const previewWrap = document.getElementById('buktiPreviewWrap');
    const previewImg = document.getElementById('buktiPreviewImg');

    if (!file) { buktiFile = null; return; }

    const allowed = ['image/jpeg', 'image/png', 'application/pdf'];
    if (!allowed.includes(file.type)) {
        Swal.fire({
            icon: 'warning',
            title: 'Format Tidak Didukung',
            text: 'Gunakan file JPG, PNG, atau PDF.',
            confirmButtonColor: '#FF5A1F',
            confirmButtonText: 'OK'
        });
        input.value = '';
        buktiFile = null;
        return;
    }
    if (file.size > 5 * 1024 * 1024) {
        Swal.fire({
            icon: 'warning',
            title: 'Ukuran Terlalu Besar',
            text: 'Ukuran file maksimal 5MB.',
            confirmButtonColor: '#FF5A1F',
            confirmButtonText: 'OK'
        });
        input.value = '';
        buktiFile = null;
        return;
    }

    buktiFile = file;
    if (box) box.classList.add('filled');
    if (textEl) textEl.innerText = file.name;

    if (file.type.startsWith('image/')) {
        const reader = new FileReader();
        reader.onload = e => {
            if (previewImg) previewImg.src = e.target.result;
            if (previewWrap) previewWrap.style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        if (previewWrap) previewWrap.style.display = 'none';
    }
}

function resetBuktiUpload() {
    buktiFile = null;
    const inputEl = document.getElementById('buktiPembayaranInput');
    if (inputEl) inputEl.value = '';
    const box = document.getElementById('buktiUploadBox');
    if (box) box.classList.remove('filled');
    const textEl = document.getElementById('buktiUploadText');
    if (textEl) textEl.innerText = 'Klik untuk pilih file (JPG, PNG, atau PDF, maks 5MB)';
    const previewWrap = document.getElementById('buktiPreviewWrap');
    if (previewWrap) previewWrap.style.display = 'none';
}

/* ENHANCED ANIMATIONS */
document.querySelectorAll('.pricing-card').forEach(card => {
    card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        const centerX = rect.width / 2;
        const centerY = rect.height / 2;
        const rotateX = (y - centerY) / 20;
        const rotateY = (centerX - x) / 20;
        card.style.transform = `perspective(1000px) rotateX(${rotateX}deg) rotateY(${rotateY}deg) translateY(-8px) scale(1.02)`;
    });
    card.addEventListener('mouseleave', () => {
        card.style.transform = '';
    });
});

document.querySelector('.hero').addEventListener('mousemove', (e) => {
    const balls = document.querySelectorAll('.floating-ball');
    const x = (e.clientX / window.innerWidth - 0.5) * 20;
    const y = (e.clientY / window.innerHeight - 0.5) * 20;
    balls.forEach((ball, i) => {
        const speed = (i + 1) * 0.5;
        ball.style.transform = `translate(${x * speed}px, ${y * speed}px)`;
    });
});

const pricingObserver = new IntersectionObserver((entries) => {
    entries.forEach((entry, index) => {
        if (entry.isIntersecting) {
            setTimeout(() => {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0) scale(1)';
            }, index * 100);
            pricingObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.pricing-card').forEach(card => {
    card.style.opacity = '0';
    card.style.transform = 'translateY(30px) scale(0.95)';
    card.style.transition = 'all 0.6s cubic-bezier(0.34, 1.56, 0.64, 1)';
    pricingObserver.observe(card);
});

const memberObserver = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.querySelectorAll('.member-detail-row').forEach((row, i) => {
                setTimeout(() => {
                    row.style.opacity = '1';
                    row.style.transform = 'translateY(0)';
                }, i * 100);
            });
            memberObserver.unobserve(entry.target);
        }
    });
}, { threshold: 0.2 });

const memberCard = document.querySelector('.member-status-card');
if (memberCard) {
    memberCard.querySelectorAll('.member-detail-row').forEach(row => {
        row.style.opacity = '0';
        row.style.transform = 'translateY(10px)';
        row.style.transition = 'all 0.4s ease';
    });
    memberObserver.observe(memberCard);
}

window.addEventListener('DOMContentLoaded', () => {
    const loader = document.getElementById('pageLoader');
    if (loader) {
        setTimeout(() => {
            loader.classList.add('hidden');
        }, 500);
    }
    updateScrollArrows();
});

/* HORIZONTAL SCROLL */
function scrollPricing(direction) {
    const grid = document.getElementById('pricingGrid');
    if (!grid) return;
    const scrollAmount = 340;
    if (direction === 'left') {
        grid.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
    } else {
        grid.scrollBy({ left: scrollAmount, behavior: 'smooth' });
    }
}

function updateScrollArrows() {
    const grid = document.getElementById('pricingGrid');
    if (!grid) return;

    const arrows = document.querySelectorAll('.scroll-arrow');
    if (arrows.length >= 2) {
        arrows[0].style.opacity = grid.scrollLeft > 10 ? '1' : '0.4';
        arrows[0].style.pointerEvents = grid.scrollLeft > 10 ? 'auto' : 'none';
        const canScrollRight = grid.scrollWidth > grid.clientWidth + grid.scrollLeft + 10;
        arrows[1].style.opacity = canScrollRight ? '1' : '0.4';
        arrows[1].style.pointerEvents = canScrollRight ? 'auto' : 'none';
    }
}

const pricingGrid = document.getElementById('pricingGrid');
if (pricingGrid) {
    pricingGrid.addEventListener('scroll', updateScrollArrows);
    window.addEventListener('resize', updateScrollArrows);
}

/* SEARCH FILTER */
function filterTipeMember(query) {
    const cards = document.querySelectorAll('.pricing-card');
    const grid = document.getElementById('pricingGrid');
    query = query.toLowerCase().trim();

    let visibleCount = 0;
    let firstVisible = null;

    cards.forEach(card => {
        const nama = card.getAttribute('data-nama') || '';
        const harga = card.getAttribute('data-harga') || '';

        if (nama.includes(query) || harga.includes(query)) {
            card.style.display = 'block';
            card.style.animation = 'cardEnter 0.5s cubic-bezier(0.34, 1.56, 0.64, 1) forwards';
            card.style.opacity = '0';
            card.style.transform = 'translateY(30px) scale(0.95)';

            setTimeout(() => {
                card.style.opacity = '1';
                card.style.transform = 'translateY(0) scale(1)';
            }, visibleCount * 100);

            if (!firstVisible) firstVisible = card;
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });

    if (firstVisible && grid) {
        setTimeout(() => {
            firstVisible.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'start' });
        }, 100);
    }

    let noResults = document.getElementById('noResultsMsg');
    if (visibleCount === 0) {
        if (!noResults) {
            noResults = document.createElement('div');
            noResults.id = 'noResultsMsg';
            noResults.style.cssText = 'width: 100%; text-align: center; padding: 40px; color: var(--muted); font-size: 14px; animation: fadeInUp 0.5s ease-out;';
            noResults.innerHTML = '<i class="fa-solid fa-magnifying-glass" style="font-size: 32px; margin-bottom: 12px; display: block; opacity: 0.5;"></i>Tidak ada tipe member yang cocok dengan pencarian Anda.';
            grid.appendChild(noResults);
        }
        noResults.style.display = 'block';
    } else if (noResults) {
        noResults.style.display = 'none';
    }
}

/* KEYBOARD NAVIGATION */
document.addEventListener('keydown', (e) => {
    const grid = document.getElementById('pricingGrid');
    if (!grid) return;
    if (e.key === 'ArrowLeft') {
        e.preventDefault();
        scrollPricing('left');
    } else if (e.key === 'ArrowRight') {
        e.preventDefault();
        scrollPricing('right');
    }
});

/* SCROLL PROGRESS */
window.addEventListener('scroll', () => {
    const st = document.documentElement.scrollTop || document.body.scrollTop;
    const sh = document.documentElement.scrollHeight - document.documentElement.clientHeight;
    document.getElementById('scrollProgress').style.transform = `scaleX(${st / sh})`;
});

/* INTERSECTION OBSERVER (REVEAL) */
const observer = new IntersectionObserver((entries) => {
    entries.forEach(e => { if (e.isIntersecting) e.target.classList.add('active'); });
}, { threshold: .1, rootMargin: '0px 0px -50px 0px' });
document.querySelectorAll('.reveal, .reveal-stagger').forEach(el => observer.observe(el));

/* STATE */
let selectedIdTipe = null;
let selectedNamaTipe = '';
let selectedHarga = 0;
let selectedPaymentMethod = 'Transfer Bank';
let countdownInterval;

function formatRupiah(n) {
    return 'Rp ' + Math.max(0, n).toLocaleString('id-ID');
}

/* MODAL 1: CHECKOUT */
function bukaModal(idTipe, namaTipe, harga) {
    selectedIdTipe = idTipe;
    selectedNamaTipe = namaTipe;
    selectedHarga = harga;

    document.getElementById('summaryNamaTipe').textContent = 'Member ' + namaTipe;
    document.getElementById('summaryHarga').textContent = formatRupiah(harga);
    document.getElementById('summaryTotal').textContent = formatRupiah(harga);

    document.getElementById('checkoutModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}

function tutupCheckoutModal() {
    document.getElementById('checkoutModal').style.display = 'none';
    document.body.style.overflow = '';
}

function selectPayment(el) {
    document.querySelectorAll('.payment-option').forEach(p => p.classList.remove('selected'));
    el.classList.add('selected');
    selectedPaymentMethod = el.dataset.method;
    document.getElementById('btnLanjutBayar').disabled = false;
}

/* MODAL 2: INSTRUKSI PEMBAYARAN */
function lanjutKePembayaran() {
    tutupCheckoutModal();
    document.getElementById('paymentTotalAmount').textContent = formatRupiah(selectedHarga);
    document.getElementById('formIdTipe').value = selectedIdTipe;
    document.getElementById('formMetode').value = selectedPaymentMethod;
    switchPaymentMethod(selectedPaymentMethod);

    document.getElementById('paymentInstructionModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
    startPaymentCountdown(15 * 60);
}

function tutupInstructionModal() {
    clearInterval(countdownInterval);
    document.getElementById('paymentInstructionModal').style.display = 'none';
    document.body.style.overflow = '';
    resetBuktiUpload();
}

function switchPaymentMethod(method) {
    selectedPaymentMethod = method;
    const va = document.getElementById('instruksiTransfer');
    const qris = document.getElementById('instruksiQRIS');
    const btnVA = document.getElementById('btnSwitchVA');
    const btnQR = document.getElementById('btnSwitchQRIS');

    if (method === 'Transfer Bank') {
        btnVA.classList.add('active');
        btnQR.classList.remove('active');
        va.style.display = 'block';
        qris.style.display = 'none';
    } else {
        btnQR.classList.add('active');
        btnVA.classList.remove('active');
        va.style.display = 'none';
        qris.style.display = 'flex';
        const total = document.getElementById('paymentTotalAmount').innerText.replace(/[^0-9]/g, '');
        document.getElementById('qrisImage').src = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent('HOOPBALL-MEMBER-' + selectedIdTipe + '-' + total)}`;
    }
}

function startPaymentCountdown(duration) {
    clearInterval(countdownInterval);
    let timer = duration;
    const display = document.getElementById('paymentCountdown');
    countdownInterval = setInterval(() => {
        const m = String(Math.floor(timer / 60)).padStart(2, '0');
        const s = String(timer % 60).padStart(2, '0');
        display.textContent = `${m}:${s}`;
        if (--timer < 0) {
            clearInterval(countdownInterval);
            display.textContent = 'Waktu Habis';
            document.getElementById('btnDonePayment').disabled = true;
        }
    }, 1000);
}

function copyVA() {
    const v = document.getElementById('vaNumber');
    v.select();
    v.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(v.value).then(() => {
        Swal.fire({
            icon: 'success',
            title: 'Berhasil Disalin!',
            text: 'Nomor VA disalin ke papan klip.',
            confirmButtonColor: '#FF5A1F',
            confirmButtonText: 'OK'
        });
    });
}

/* FORM VALIDATION */
document.getElementById('formPembayaran').addEventListener('submit', function(e) {
    const fileInput = document.getElementById('buktiPembayaranInput');
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        e.preventDefault();
        Swal.fire({
            icon: 'warning',
            title: 'Bukti Pembayaran Wajib',
            text: 'Silakan unggah bukti pembayaran terlebih dahulu sebelum konfirmasi.',
            confirmButtonColor: '#FF5A1F',
            confirmButtonText: 'OK'
        });
        return false;
    }
});

/* CLOSE MODAL ON OUTSIDE CLICK */
window.addEventListener('click', function(e) {
    const cModal = document.getElementById('checkoutModal');
    const pModal = document.getElementById('paymentInstructionModal');
    if (e.target === cModal) tutupCheckoutModal();
    if (e.target === pModal) tutupInstructionModal();
});

/* URL PARAMETER NOTIFICATION */
const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
const msg = urlParams.get('msg');

if (status && msg) {
    const isSuccess = status === 'success';
    Swal.fire({
        icon: isSuccess ? 'success' : 'error',
        title: isSuccess ? 'Berhasil!' : 'Gagal!',
        text: msg,
        confirmButtonColor: '#FF5A1F',
        confirmButtonText: 'OK'
    });
    window.history.replaceState({}, document.title, window.location.pathname);
}

/* KONFIRMASI LOGOUT */
(function () {
    const SWAL_CDN = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
    let swalLoading = null;

    function ensureSwal() {
        if (typeof Swal !== 'undefined') return Promise.resolve();
        if (swalLoading) return swalLoading;
        swalLoading = new Promise(function (resolve, reject) {
            const s = document.createElement('script');
            s.src = SWAL_CDN;
            s.onload = resolve;
            s.onerror = reject;
            document.head.appendChild(s);
        });
        return swalLoading;
    }

    function showLogoutDialog(url) {
        Swal.fire({
            title: 'Keluar dari HoopBall?',
            html: 'Apakah Anda yakin ingin keluar?<br>' +
                  '<span style="font-size:12px;color:#6B7280;">Sesi Anda akan diakhiri dan Anda perlu masuk kembali.</span>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<i class="fa-solid fa-right-from-bracket"></i> Ya, Keluar',
            cancelButtonText: 'Batal',
            confirmButtonColor: '#EF4444',
            cancelButtonColor: '#6B7280',
            reverseButtons: true,
            focusCancel: true,
            allowOutsideClick: false
        }).then(function (result) {
            if (!result.isConfirmed) return;
            Swal.fire({
                title: 'Sedang keluar...',
                text: 'Mohon tunggu sebentar.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: function () { Swal.showLoading(); }
            });
            setTimeout(function () { window.location.href = url; }, 500);
        });
    }

    document.addEventListener('click', function (e) {
        const link = e.target.closest('a[href*="logout.php"]');
        if (!link) return;
        e.preventDefault();
        const url = link.getAttribute('href');
        ensureSwal()
            .then(function () { showLogoutDialog(url); })
            .catch(function () {
                if (confirm('Apakah Anda yakin ingin keluar?')) window.location.href = url;
            });
    });
})();
            window.Swal = Swal.mixin({
            scrollbarPadding: false
        });
</script>
        <?php if (function_exists('tampilkan_sensor_auto_logout')) tampilkan_sensor_auto_logout(); ?>
</body>
</html>