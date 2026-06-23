<?php
ob_start();
session_start();
include '../includes/auth_helper.php';
include '../includes/config.php';

if (isset($_GET['hapus_akun']) && $_GET['hapus_akun'] == '1') {
    $id_customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';
    if (!empty($id_customer)) {
        $modified_by = $_SESSION['nama'] ?? 'CUSTOMER';
        $stmt = sqlsrv_query($conn, "UPDATE Customer SET Is_Deleted = 1, Status = 0, Deleted_By = ?, Deleted_Date = GETDATE() WHERE ID_Customer = ? AND Is_Deleted = 0", array($modified_by, $id_customer));
        if ($stmt) {
            $_SESSION = array();
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
            setcookie('remember_me', '', time() - 3600, "/");
            ob_end_clean();
            header("Location: ../login/login.php?status=success&msg=Akun Anda telah dihapus permanen.");
            exit();
        } else {
            ob_end_clean();
            header("Location: view_customer.php?status=error&msg=Gagal menghapus akun.");
            exit();
        }
    } else {
        ob_end_clean();
        header("Location: ../login/login.php?status=error&msg=Sesi tidak valid.");
        exit();
    }
}

cek_akses('customer');

$id_customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';
$nama_customer = 'Pelanggan';
$photo_profile = '';

if (!empty($id_customer)) {
    $cek_deleted = sqlsrv_query($conn, "SELECT Nama_Customer, Photo_Profile, Is_Deleted, Status FROM Customer WHERE ID_Customer = ?", array($id_customer));
    if ($cek_deleted) {
        $row_cust = sqlsrv_fetch_array($cek_deleted, SQLSRV_FETCH_ASSOC);
        if ($row_cust) {
            if ($row_cust['Is_Deleted'] == 1 || $row_cust['Status'] == 0) {
                $_SESSION = array(); session_destroy();
                setcookie('remember_me', '', time() - 3600, "/");
                ob_end_clean();
                header("Location: ../login/login.php?status=error&msg=Akun Anda telah dihapus atau dinonaktifkan.");
                exit();
            }
            $nama_customer = $row_cust['Nama_Customer'];
            $photo_profile = $row_cust['Photo_Profile'];
        }
    }
}

$member_data = null;
$member_check = sqlsrv_query($conn, "SELECT TOP 1 L.*, T.Nama_Tipe, T.Potongan_Harga, T.Harga_Member FROM Langganan L INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe WHERE L.ID_Customer = ? AND L.Status = 1 AND GETDATE() BETWEEN L.Tanggal_Mulai AND L.Tanggal_Selesai ORDER BY L.Tanggal_Selesai DESC", array($id_customer));
if ($member_check) { $member_data = sqlsrv_fetch_array($member_check, SQLSRV_FETCH_ASSOC); }
$has_member = !empty($member_data);
$member_tipe = $has_member ? $member_data['Nama_Tipe'] : '';

$lapangan_list = [];
$query_lapangan = sqlsrv_query($conn, "SELECT TOP 3 ID_Lapangan, Nama_Lapangan, Harga_Sewa, Photo_Lapangan FROM Lapangan WHERE Status = 1 AND Is_Deleted = 0 ORDER BY ID_Lapangan ASC");
if ($query_lapangan) { while ($row = sqlsrv_fetch_array($query_lapangan, SQLSRV_FETCH_ASSOC)) { $lapangan_list[] = $row; } }

$jadwal_list = [];
$query_jadwal = sqlsrv_query($conn, "SELECT TOP 5 J.ID_Jadwal, J.Tanggal, J.Jam_Mulai, J.Jam_Selesai, J.Status, L.ID_Lapangan, L.Nama_Lapangan, L.Harga_Sewa, L.Photo_Lapangan FROM Jadwal J INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan WHERE J.Is_Deleted = 0 AND L.Is_Deleted = 0 AND L.Status = 1 AND J.Status = 1 ORDER BY J.Tanggal ASC, J.Jam_Mulai ASC");
if ($query_jadwal) { while ($row = sqlsrv_fetch_array($query_jadwal, SQLSRV_FETCH_ASSOC)) { $jadwal_list[] = $row; } }

$tipe_member_list = [];
$query_tipe = sqlsrv_query($conn, "SELECT TOP 3 ID_Tipe, Nama_Tipe, Harga_Member, Potongan_Harga FROM Tipe_Member WHERE Status = 1 AND Is_Deleted = 0 ORDER BY Harga_Member ASC");
if ($query_tipe) { while ($row = sqlsrv_fetch_array($query_tipe, SQLSRV_FETCH_ASSOC)) { $tipe_member_list[] = $row; } }

$riwayat_list = [];
$query_riwayat = sqlsrv_query($conn, "SELECT TOP 3 B.ID_Booking, B.Tanggal_Booking, B.Metode_Pembayaran, B.Total_Bayar, B.Status, L.Nama_Lapangan, J.Tanggal, J.Jam_Mulai, J.Jam_Selesai FROM Booking B INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan WHERE B.ID_Customer = ? ORDER BY B.Created_Date DESC", array($id_customer));
if ($query_riwayat) { while ($row = sqlsrv_fetch_array($query_riwayat, SQLSRV_FETCH_ASSOC)) { $riwayat_list[] = $row; } }

$status_labels = [0 => ['label'=>'Menunggu','class'=>'sp-pending','icon'=>'fa-clock'], 1 => ['label'=>'Berhasil','class'=>'sp-active','icon'=>'fa-check-circle'], 2 => ['label'=>'Selesai','class'=>'sp-success','icon'=>'fa-flag-checkered'], 3 => ['label'=>'Dibatalkan','class'=>'sp-inactive','icon'=>'fa-ban']];
function formatTanggal($t) { if(empty($t)) return '-'; if(is_object($t)&&method_exists($t,'format')) return $t->format('d M Y'); return date('d M Y',strtotime($t)); }
function formatJam($j) { if(empty($j)) return '-'; if(is_object($j)&&method_exists($j,'format')) return $j->format('H:i'); return substr($j,0,5); }
function rupiahFormat($n) { return 'Rp '.number_format($n,0,',','.'); }
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>HoopBall | Sewa Lapangan Basket Online</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root { --primary:#FF5200; --primary-hover:#E04800; --dark-bg:#0B0B0C; --card-dark:#121214; --text-gray:#8E8E93; --border-color:#222225; --white:#FFFFFF; --light-bg:#F8F9FA; --green:#34C759; --green-lt:rgba(52,199,89,.10); --yellow:#FFCC00; --yellow-lt:rgba(255,204,0,.10); --red:#FF3B30; --red-lt:rgba(255,59,48,.10); --blue:#007AFF; --blue-lt:rgba(0,122,255,.10); }
* { box-sizing:border-box; margin:0; padding:0; }
body { font-family:'Plus Jakarta Sans',sans-serif; background:var(--white); color:#111; overflow-x:hidden; }

/* ============ KEYFRAMES ============ */
@keyframes fadeInUp { from{opacity:0;transform:translateY(40px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeInDown { from{opacity:0;transform:translateY(-30px)} to{opacity:1;transform:translateY(0)} }
@keyframes fadeInLeft { from{opacity:0;transform:translateX(-40px)} to{opacity:1;transform:translateX(0)} }
@keyframes fadeInRight { from{opacity:0;transform:translateX(40px)} to{opacity:1;transform:translateX(0)} }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }
@keyframes scaleIn { from{opacity:0;transform:scale(0.8)} to{opacity:1;transform:scale(1)} }
@keyframes slideInUp { from{opacity:0;transform:translateY(60px) scale(0.95)} to{opacity:1;transform:translateY(0) scale(1)} }
@keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
@keyframes pulse{0%,100%{transform:scale(1);box-shadow:0 0 0 0 rgba(52,199,89,.4)}50%{transform:scale(1.05);box-shadow:0 0 0 15px rgba(52,199,89,0)}}
@keyframes shimmer { 0%{background-position:-200% 0} 100%{background-position:200% 0} }
@keyframes bounceIn { 0%{opacity:0;transform:scale(0.3)} 50%{opacity:1;transform:scale(1.05)} 70%{transform:scale(0.9)} 100%{transform:scale(1)} }
@keyframes rotateIn { from{opacity:0;transform:rotate(-180deg) scale(0.5)} to{opacity:1;transform:rotate(0) scale(1)} }
@keyframes gradientShift { 0%{background-position:0% 50%} 50%{background-position:100% 50%} 100%{background-position:0% 50%} }
@keyframes ripple { 0%{transform:scale(1);opacity:1} 100%{transform:scale(1.5);opacity:0} }
@keyframes glow { 0%,100%{box-shadow:0 0 5px rgba(255,82,0,0.3)} 50%{box-shadow:0 0 25px rgba(255,82,0,0.6),0 0 50px rgba(255,82,0,0.2)} }
@keyframes drawLine { from{width:0} to{width:60px} }
@keyframes wave { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-15px)} }
@keyframes spinSlow { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
@keyframes countUp { from{opacity:0;transform:translateY(20px)} to{opacity:1;transform:translateY(0)} }
@keyframes shake { 0%,100%{transform:translateX(0)} 25%{transform:translateX(-5px)} 75%{transform:translateX(5px)} }
@keyframes borderGlow { 0%,100%{border-color:rgba(255,82,0,0.1)} 50%{border-color:rgba(255,82,0,0.4)} }
@keyframes textReveal { from{clip-path:inset(0 100% 0 0)} to{clip-path:inset(0 0 0 0)} }
@keyframes iconPop { 0%{transform:scale(0)} 60%{transform:scale(1.2)} 100%{transform:scale(1)} }
@keyframes neonPulse { 0%,100%{text-shadow:0 0 5px rgba(255,82,0,0.5),0 0 10px rgba(255,82,0,0.3)} 50%{text-shadow:0 0 10px rgba(255,82,0,0.8),0 0 20px rgba(255,82,0,0.5),0 0 30px rgba(255,82,0,0.3)} }
@keyframes slideDown { from{transform:translateY(-100%);opacity:0} to{transform:translateY(0);opacity:1} }
@keyframes zoomIn { from{transform:scale(0.5);opacity:0} to{transform:scale(1);opacity:1} }
@keyframes flipX { from{transform:perspective(400px) rotateX(90deg);opacity:0} to{transform:perspective(400px) rotateX(0);opacity:1} }
@keyframes flipY { from{transform:perspective(400px) rotateY(90deg);opacity:0} to{transform:perspective(400px) rotateY(0);opacity:1} }
@keyframes swing { 0%{transform:rotate(0)} 20%{transform:rotate(15deg)} 40%{transform:rotate(-10deg)} 60%{transform:rotate(5deg)} 80%{transform:rotate(-5deg)} 100%{transform:rotate(0)} }
@keyframes rubberBand { 0%{transform:scale(1)} 30%{transform:scale(1.25,0.75)} 40%{transform:scale(0.75,1.25)} 50%{transform:scale(1.15,0.85)} 65%{transform:scale(0.95,1.05)} 75%{transform:scale(1.05,0.95)} 100%{transform:scale(1)} }
@keyframes heartBeat { 0%{transform:scale(1)} 14%{transform:scale(1.3)} 28%{transform:scale(1)} 42%{transform:scale(1.3)} 70%{transform:scale(1)} }
@keyframes jello { 0%,100%{transform:skewX(0) skewY(0)} 22.2%{transform:skewX(-12.5deg) skewY(-12.5deg)} 33.3%{transform:skewX(6.25deg) skewY(6.25deg)} 44.4%{transform:skewX(-3.125deg) skewY(-3.125deg)} 55.5%{transform:skewX(1.5625deg) skewY(1.5625deg)} 66.6%{transform:skewX(-0.78125deg) skewY(-0.78125deg)} 77.7%{transform:skewX(0.390625deg) skewY(0.390625deg)} 88.8%{transform:skewX(-0.1953125deg) skewY(-0.1953125deg)} }
@keyframes rollIn { from{opacity:0;transform:translateX(-100%) rotate(-120deg)} to{opacity:1;transform:translateX(0) rotate(0)} }
@keyframes jackInTheBox { from{opacity:0;transform:scale(0.1) rotate(30deg);transform-origin:center bottom} 50%{transform:rotate(-10deg)} 70%{transform:rotate(3deg)} to{opacity:1;transform:scale(1)} }
@keyframes lightSpeedIn { from{transform:translate3d(100%,0,0) skewX(-30deg);opacity:0} 60%{transform:skewX(20deg);opacity:1} 80%{transform:skewX(-5deg)} to{transform:translate3d(0,0,0)} }

/* ============ ANIMATION CLASSES ============ */
.anim-hidden { opacity:0; }
.anim-fade-up { animation:fadeInUp 0.8s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-fade-down { animation:fadeInDown 0.8s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-fade-left { animation:fadeInLeft 0.8s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-fade-right { animation:fadeInRight 0.8s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-scale-in { animation:scaleIn 0.6s cubic-bezier(0.34,1.56,0.64,1) forwards; }
.anim-slide-up { animation:slideInUp 0.9s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-bounce-in { animation:bounceIn 0.8s cubic-bezier(0.68,-0.55,0.265,1.55) forwards; }
.anim-rotate-in { animation:rotateIn 0.7s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-card-flip { animation:cardFlip 0.8s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-text-reveal { animation:textReveal 1s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-elastic { animation:elastic 0.6s cubic-bezier(0.68,-0.55,0.265,1.55) forwards; }
.anim-zoom-in { animation:zoomIn 0.5s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-flip-x { animation:flipX 0.6s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-flip-y { animation:flipY 0.6s cubic-bezier(0.16,1,0.3,1) forwards; }
.anim-swing { animation:swing 1s ease forwards; }
.anim-rubber { animation:rubberBand 1s ease forwards; }
.anim-heart { animation:heartBeat 1.3s ease-in-out forwards; }
.anim-jello { animation:jello 0.9s ease forwards; }
.anim-roll-in { animation:rollIn 0.6s ease forwards; }
.anim-jack-in { animation:jackInTheBox 0.8s ease forwards; }
.anim-light-speed { animation:lightSpeedIn 0.8s ease forwards; }
.anim-neon { animation:neonPulse 2s ease-in-out infinite; }

.delay-100 { animation-delay:0.1s; }
.delay-200 { animation-delay:0.2s; }
.delay-300 { animation-delay:0.3s; }
.delay-400 { animation-delay:0.4s; }
.delay-500 { animation-delay:0.5s; }
.delay-600 { animation-delay:0.6s; }
.delay-700 { animation-delay:0.7s; }
.delay-800 { animation-delay:0.8s; }
.delay-900 { animation-delay:0.9s; }
.delay-1000 { animation-delay:1.0s; }
.delay-1200 { animation-delay:1.2s; }
.delay-1500 { animation-delay:1.5s; }
.delay-2000 { animation-delay:2.0s; }

/* ============ INTERSECTION OBSERVER ============ */
.reveal { opacity:0; transform:translateY(40px); transition:all 0.8s cubic-bezier(0.16,1,0.3,1); }
.reveal.active { opacity:1; transform:translateY(0); }
.reveal-left { opacity:0; transform:translateX(-50px); transition:all 0.8s cubic-bezier(0.16,1,0.3,1); }
.reveal-left.active { opacity:1; transform:translateX(0); }
.reveal-right { opacity:0; transform:translateX(50px); transition:all 0.8s cubic-bezier(0.16,1,0.3,1); }
.reveal-right.active { opacity:1; transform:translateX(0); }
.reveal-scale { opacity:0; transform:scale(0.9); transition:all 0.7s cubic-bezier(0.16,1,0.3,1); }
.reveal-scale.active { opacity:1; transform:scale(1); }
.reveal-stagger .stagger-item { opacity:0; transform:translateY(30px); transition:all 0.6s cubic-bezier(0.16,1,0.3,1); }
.reveal-stagger.active .stagger-item { opacity:1; transform:translateY(0); }
.reveal-stagger.active .stagger-item:nth-child(1){transition-delay:0s}
.reveal-stagger.active .stagger-item:nth-child(2){transition-delay:0.1s}
.reveal-stagger.active .stagger-item:nth-child(3){transition-delay:0.2s}
.reveal-stagger.active .stagger-item:nth-child(4){transition-delay:0.3s}
.reveal-stagger.active .stagger-item:nth-child(5){transition-delay:0.4s}
.reveal-flip .stagger-item { opacity:0; transform:perspective(1000px) rotateY(90deg); transition:all 0.7s cubic-bezier(0.16,1,0.3,1); }
.reveal-flip.active .stagger-item { opacity:1; transform:perspective(1000px) rotateY(0); }
.reveal-flip.active .stagger-item:nth-child(1){transition-delay:0s}
.reveal-flip.active .stagger-item:nth-child(2){transition-delay:0.15s}
.reveal-flip.active .stagger-item:nth-child(3){transition-delay:0.3s}
.reveal-flip.active .stagger-item:nth-child(4){transition-delay:0.45s}
.reveal-flip.active .stagger-item:nth-child(5){transition-delay:0.6s}
.reveal-zoom .stagger-item { opacity:0; transform:scale(0.5); transition:all 0.6s cubic-bezier(0.34,1.56,0.64,1); }
.reveal-zoom.active .stagger-item { opacity:1; transform:scale(1); }
.reveal-zoom.active .stagger-item:nth-child(1){transition-delay:0s}
.reveal-zoom.active .stagger-item:nth-child(2){transition-delay:0.1s}
.reveal-zoom.active .stagger-item:nth-child(3){transition-delay:0.2s}
.reveal-zoom.active .stagger-item:nth-child(4){transition-delay:0.3s}

/* ============ NAVBAR ============ */
nav { background:var(--white); padding:0 80px; display:flex; justify-content:space-between; align-items:center; height:76px; position:sticky; top:0; z-index:1000; border-bottom:1px solid #E5E5EA; animation:fadeInDown 0.6s ease-out forwards; }
.nav-logo { display:flex; align-items:center; text-decoration:none; gap:10px; transition:transform 0.3s ease; }
.nav-logo:hover { transform:scale(1.05); }
.nav-logo img { height:70px; width:auto; transition:transform 0.5s cubic-bezier(0.34,1.56,0.64,1); }
.nav-logo:hover img { transform:rotate(5deg) scale(1.1); }
.nav-logo span { color:#1C1C1E; font-size:20px; font-weight:800; letter-spacing:-0.5px; }
.nav-links { display:flex; align-items:center; gap:8px; }
.nav-links a { color:#636366; text-decoration:none; font-size:14px; font-weight:500; padding:8px 16px; border-radius:20px; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); position:relative; overflow:hidden; }
.nav-links a::before { content:''; position:absolute; bottom:0; left:50%; width:0; height:2px; background:var(--primary); transition:all 0.3s cubic-bezier(0.16,1,0.3,1); transform:translateX(-50%); }
.nav-links a:hover { color:#1C1C1E; transform:translateY(-2px); }
.nav-links a:hover::before { width:60%; }
.nav-links a.active { color:var(--primary); font-weight:600; }
.nav-links a.active::before { width:60%; }

.nav-user-container { position:relative; height:76px; display:flex; align-items:center; }
.nav-user { background:#F2F2F7; border:1px solid #E5E5EA; padding:8px 16px; border-radius:50px; color:#1C1C1E; font-size:14px; font-weight:600; cursor:pointer; display:flex; align-items:center; gap:10px; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); }
.nav-user:hover { background:#E5E5EA; border-color:var(--primary); transform:scale(1.02); box-shadow:0 4px 12px rgba(255,82,0,0.15); }
.nav-user img.user-avatar { width:24px; height:24px; border-radius:50%; object-fit:cover; transition:transform 0.3s ease; }
.nav-user:hover img.user-avatar { transform:scale(1.15); }
.nav-user i.user-icon { font-size:16px; color:var(--primary); transition:transform 0.3s ease; }
.nav-user:hover i.user-icon { transform:scale(1.2); }
.nav-user i.arrow { font-size:11px; color:#8E8E93; transition:0.3s cubic-bezier(0.34,1.56,0.64,1); }
.nav-user-container:hover i.arrow { transform:rotate(180deg); color:var(--primary); }
.dropdown-menu { position:absolute; top:85%; right:0; background:#16161a; min-width:220px; border-radius:12px; border:1px solid #2d2d33; box-shadow:0 10px 30px rgba(0,0,0,0.5); padding:8px 0; display:none; z-index:1001; transform-origin:top right; }
.nav-user-container:hover .dropdown-menu { display:block; animation:fadeInUp 0.3s cubic-bezier(0.16,1,0.3,1) forwards; }
.dropdown-menu .user-info-header { padding:12px 20px; border-bottom:1px solid #2d2d33; margin-bottom:6px; animation:fadeInDown 0.3s ease-out; }
.dropdown-menu .user-info-header span { display:block; }
.dropdown-menu .user-info-header .u-name { color:var(--white); font-size:14px; font-weight:700; }
.dropdown-menu .user-info-header .u-role { color:var(--text-gray); font-size:11px; text-transform:uppercase; letter-spacing:0.5px; margin-top:2px; }
.dropdown-menu a { display:flex; align-items:center; gap:12px; padding:10px 20px; color:#c5c5ca; text-decoration:none; font-size:13px; font-weight:500; transition:all 0.25s cubic-bezier(0.16,1,0.3,1); position:relative; overflow:hidden; }
.dropdown-menu a::after { content:''; position:absolute; left:0; top:0; width:3px; height:100%; background:var(--primary); transform:scaleY(0); transition:transform 0.25s cubic-bezier(0.16,1,0.3,1); }
.dropdown-menu a i { font-size:14px; width:16px; text-align:center; transition:transform 0.3s ease; }
.dropdown-menu a:hover { background:#222227; color:var(--primary); padding-left:28px; }
.dropdown-menu a:hover::after { transform:scaleY(1); }
.dropdown-menu a:hover i { transform:scale(1.2); }
.dropdown-divider { height:1px; background:#2d2d33; margin:6px 0; }
.dropdown-menu a.logout:hover { color:#ff3b30; }
.dropdown-menu a.logout:hover::after { background:#ff3b30; }

.member-badge-nav { display:inline-flex; align-items:center; gap:6px; background:var(--green-lt); border:1px solid var(--green); color:var(--green); padding:4px 12px; border-radius:50px; font-size:11px; font-weight:700; margin-left:8px; animation:pulse 2s ease-in-out infinite; }

/* ============ HERO SECTION ============ */
.hero { background-color:var(--dark-bg); background-image:linear-gradient(180deg,rgba(11,11,12,0.6) 0%,rgba(11,11,12,0.9) 100%),url('https://images.unsplash.com/photo-1546519638-68e109498ffc?q=80&w=2000'); background-size:cover; background-position:center; min-height:600px; padding:60px 80px; display:flex; align-items:center; justify-content:space-between; gap:40px; position:relative; overflow:hidden; }
.hero::before { content:''; position:absolute; top:0; left:0; right:0; bottom:0; background:radial-gradient(circle at 20% 50%,rgba(255,82,0,0.08) 0%,transparent 50%),radial-gradient(circle at 80% 20%,rgba(255,82,0,0.05) 0%,transparent 40%); pointer-events:none; }
.hero-left { max-width:620px; position:relative; z-index:2; }
.hero-title { font-size:54px; font-weight:800; color:var(--white); line-height:1.15; margin-bottom:20px; animation:fadeInUp 1s cubic-bezier(0.16,1,0.3,1) 0.3s forwards; opacity:0; }
.hero-title span { color:var(--primary); display:inline-block; position:relative; }
.hero-title span::after { content:''; position:absolute; bottom:5px; left:0; width:100%; height:8px; background:rgba(255,82,0,0.3); border-radius:4px; z-index:-1; animation:drawLine 0.8s ease-out 1.2s forwards; width:0; }
.hero-desc { color:#A0A0A5; font-size:16px; line-height:1.6; margin-bottom:36px; animation:fadeInUp 1s cubic-bezier(0.16,1,0.3,1) 0.5s forwards; opacity:0; }
.hero-btns { display:flex; gap:16px; animation:fadeInUp 1s cubic-bezier(0.16,1,0.3,1) 0.7s forwards; opacity:0; }

.btn-primary { background:var(--primary); color:var(--white); border:none; padding:14px 28px; border-radius:8px; font-weight:700; font-size:15px; text-decoration:none; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); position:relative; overflow:hidden; }
.btn-primary::before { content:''; position:absolute; top:50%; left:50%; width:0; height:0; background:rgba(255,255,255,0.2); border-radius:50%; transform:translate(-50%,-50%); transition:width 0.6s,height 0.6s; }
.btn-primary:hover::before { width:300px; height:300px; }
.btn-primary:hover { background:var(--primary-hover); transform:translateY(-3px); box-shadow:0 10px 30px rgba(255,82,0,0.4); }
.btn-primary:active { transform:translateY(-1px) scale(0.98); }
.btn-primary i { transition:transform 0.3s ease; }
.btn-primary:hover i { transform:translateX(4px); }
.btn-outline { background:transparent; color:var(--white); border:1px solid rgba(255,255,255,0.2); padding:14px 28px; border-radius:8px; font-weight:700; font-size:15px; text-decoration:none; cursor:pointer; display:inline-flex; align-items:center; gap:8px; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); position:relative; overflow:hidden; }
.btn-outline::before { content:''; position:absolute; top:0; left:0; width:100%; height:100%; background:linear-gradient(90deg,transparent,rgba(255,255,255,0.1),transparent); transform:translateX(-100%); transition:transform 0.5s; }
.btn-outline:hover::before { transform:translateX(100%); }
.btn-outline:hover { background:rgba(255,255,255,0.05); border-color:var(--white); transform:translateY(-3px); box-shadow:0 10px 30px rgba(255,255,255,0.1); }
.btn-outline:active { transform:translateY(-1px) scale(0.98); }

.member-badge-hero { display:inline-flex; align-items:center; gap:8px; background:var(--green-lt); border:1px solid var(--green); color:var(--green); padding:8px 16px; border-radius:50px; font-size:13px; font-weight:700; margin-bottom:20px; animation:fadeInDown 0.6s cubic-bezier(0.16,1,0.3,1) 0.1s forwards, glow 2s ease-in-out infinite 1.5s; opacity:0; }
.member-badge-hero i { font-size:14px; animation:float 2s ease-in-out infinite; }

.search-widget { background:rgba(18,18,20,0.85); backdrop-filter:blur(12px); border:1px solid rgba(255,255,255,0.08); border-radius:16px; width:440px; padding:28px; box-shadow:0 20px 40px rgba(0,0,0,0.4); animation:slideInUp 1s cubic-bezier(0.16,1,0.3,1) 0.4s forwards; opacity:0; position:relative; z-index:2; transition:transform 0.3s ease,box-shadow 0.3s ease; }
.search-widget:hover { transform:translateY(-5px); box-shadow:0 30px 60px rgba(0,0,0,0.5); }
.widget-title { color:var(--white); font-size:18px; font-weight:700; margin-bottom:24px; display:flex; align-items:center; gap:10px; }
.widget-title i { animation:rotateIn 0.6s ease-out 0.8s backwards; }
.form-group { margin-bottom:16px; position:relative; }
.form-label { display:block; color:#A0A0A5; font-size:12px; font-weight:600; margin-bottom:8px; transition:color 0.3s ease; }
.form-group:focus-within .form-label { color:var(--primary); }
.input-wrapper { position:relative; }
.input-wrapper i { position:absolute; left:14px; top:50%; transform:translateY(-50%); color:#636366; font-size:14px; transition:all 0.3s ease; }
.form-group:focus-within .input-wrapper i { color:var(--primary); transform:translateY(-50%) scale(1.2); }
.form-select,.form-input { width:100%; background:#1C1C1E; border:1px solid #2C2C2E; border-radius:8px; padding:12px 14px 12px 40px; color:var(--white); font-size:14px; font-family:inherit; outline:none; appearance:none; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); }
.form-select:focus,.form-input:focus { border-color:var(--primary); box-shadow:0 0 0 3px rgba(255,82,0,0.15); transform:translateY(-1px); }
.form-select { background-image:url("data:image/svg+xml;utf8,<svg fill='none' height='24' stroke='%238E8E93' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' viewBox='0 0 24 24' width='24' xmlns='http://www.w3.org/2000/svg'><polyline points='6 9 12 15 18 9'/></svg>"); background-repeat:no-repeat; background-position:right 14px center; background-size:16px; }
.form-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.btn-widget { background:var(--primary); color:var(--white); border:none; width:100%; padding:14px; border-radius:8px; font-weight:700; font-size:14px; cursor:pointer; margin-top:10px; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); position:relative; overflow:hidden; text-decoration:none; display:inline-flex; justify-content:center; align-items:center; gap:8px; }
.btn-widget::after { content:''; position:absolute; top:50%; left:50%; width:0; height:0; background:rgba(255,255,255,0.2); border-radius:50%; transform:translate(-50%,-50%); transition:width 0.6s,height 0.6s; }
.btn-widget:hover::after { width:300px; height:300px; }
.btn-widget:hover { background:var(--primary-hover); transform:translateY(-2px); box-shadow:0 8px 25px rgba(255,82,0,0.4); }
.btn-widget:active { transform:translateY(0) scale(0.98); }

/* ============ FEATURES ROW ============ */
.features-row { padding:40px 80px; display:grid; grid-template-columns:repeat(4,1fr); gap:24px; background:var(--white); border-bottom:1px solid #F2F2F7; }
.feature-card { display:flex; gap:16px; align-items:flex-start; padding:16px; border-radius:12px; transition:all 0.4s cubic-bezier(0.16,1,0.3,1); cursor:default; }
.feature-card:hover { background:#FFF8F5; transform:translateY(-5px); box-shadow:0 10px 30px rgba(255,82,0,0.08); }
.feature-card:hover .feature-icon-circle { transform:scale(1.1) rotate(5deg); box-shadow:0 8px 20px rgba(255,82,0,0.2); }
.feature-icon-circle { background:#FFF0E6; width:48px; height:48px; min-width:48px; border-radius:50%; display:flex; align-items:center; justify-content:center; transition:all 0.4s cubic-bezier(0.34,1.56,0.64,1); }
.feature-icon-circle i { color:var(--primary); font-size:20px; transition:transform 0.3s ease; }
.feature-card:hover .feature-icon-circle i { transform:scale(1.15); }
.feature-text h4 { font-size:15px; font-weight:700; margin-bottom:6px; color:#1C1C1E; transition:color 0.3s ease; }
.feature-card:hover .feature-text h4 { color:var(--primary); }
.feature-text p { font-size:13px; color:#636366; line-height:1.5; }

/* ============ MAIN CONTAINER ============ */
.main-container { padding:60px 80px; max-width:1440px; margin:0 auto; }

/* ============ SECTION HEADER ============ */
.section-header { display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:24px; }
.section-title { font-size:24px; font-weight:800; color:#111; position:relative; display:inline-block; }
.section-title::after { content:''; position:absolute; bottom:-4px; left:0; width:40px; height:3px; background:var(--primary); border-radius:2px; transition:width 0.4s cubic-bezier(0.16,1,0.3,1); }
.section-header:hover .section-title::after { width:100%; }
.section-subtitle { font-size:14px; color:#636366; margin-top:4px; }
.section-action { color:var(--primary); text-decoration:none; font-size:14px; font-weight:700; display:flex; align-items:center; gap:6px; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); position:relative; }
.section-action::after { content:''; position:absolute; bottom:-2px; left:0; width:0; height:2px; background:var(--primary); transition:width 0.3s ease; }
.section-action:hover { color:var(--primary-hover); gap:10px; }
.section-action:hover::after { width:100%; }
.section-action i { transition:transform 0.3s ease; }
.section-action:hover i { transform:translateX(4px); }

/* ============ COURT GRID ============ */
.court-grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:24px; margin-bottom:60px; }
.court-card-new { background:var(--white); border:1px solid #E5E5EA; border-radius:12px; overflow:hidden; position:relative; transition:all 0.4s cubic-bezier(0.16,1,0.3,1); transform-style:preserve-3d; }
.court-card-new:hover { transform:translateY(-8px) rotateX(2deg); box-shadow:0 20px 40px rgba(0,0,0,0.12); border-color:rgba(255,82,0,0.2); }
.court-card-new:hover .court-img-new { transform:scale(1.08); }
.court-img-container { height:220px; position:relative; background:#f0f0f0; overflow:hidden; }
.court-img-new { width:100%; height:100%; object-fit:cover; transition:transform 0.6s cubic-bezier(0.16,1,0.3,1); }
.heart-btn { position:absolute; top:14px; right:14px; background:rgba(255,255,255,0.9); border:none; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; color:#636366; font-size:16px; transition:all 0.3s cubic-bezier(0.34,1.56,0.64,1); z-index:2; }
.heart-btn:hover { color:#FF2D55; background:var(--white); transform:scale(1.2); box-shadow:0 4px 12px rgba(255,45,85,0.3); }
.heart-btn:active { transform:scale(0.9); }
.court-body-new { padding:20px; transition:background 0.3s ease; }
.court-card-new:hover .court-body-new { background:#FFFDFB; }
.court-meta { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; }
.court-name-new { font-size:18px; font-weight:700; color:#1C1C1E; transition:color 0.3s ease; }
.court-card-new:hover .court-name-new { color:var(--primary); }
.court-rating { font-size:13px; font-weight:600; color:#1C1C1E; display:flex; align-items:center; gap:4px; }
.court-rating i { color:#FFCC00; transition:transform 0.3s ease; }
.court-card-new:hover .court-rating i { transform:scale(1.2); }
.court-rating span { color:#8E8E93; font-weight:400; }
.court-location { font-size:13px; color:#636366; display:flex; align-items:center; gap:4px; margin-bottom:16px; transition:color 0.3s ease; }
.court-card-new:hover .court-location { color:var(--primary); }
.court-footer { display:flex; justify-content:space-between; align-items:center; border-top:1px solid #E5E5EA; padding-top:16px; }
.court-price-box { font-size:13px; color:#8E8E93; transition:transform 0.3s ease; }
.court-card-new:hover .court-price-box { transform:translateX(5px); }
.court-price-box strong { font-size:18px; color:var(--primary); font-weight:800; transition:color 0.3s ease; }
.court-card-new:hover .court-price-box strong { color:var(--primary-hover); }
.btn-detail { background:var(--white); color:#1C1C1E; border:1px solid #D1D1D6; padding:10px 18px; border-radius:6px; font-size:13px; font-weight:700; cursor:pointer; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); text-decoration:none; display:inline-block; }
.btn-detail:hover { background:var(--primary); color:var(--white); border-color:var(--primary); transform:translateY(-2px); box-shadow:0 6px 15px rgba(255,82,0,0.3); }
.btn-detail:active { transform:translateY(0) scale(0.98); }

/* ============ SCHEDULE BOX ============ */
.schedule-box { border:1px solid #E5E5EA; border-radius:16px; padding:28px; background:var(--white); margin-bottom:60px; transition:all 0.4s cubic-bezier(0.16,1,0.3,1); }
.schedule-box:hover { box-shadow:0 15px 40px rgba(0,0,0,0.06); border-color:rgba(255,82,0,0.15); }
.schedule-layout { display:grid; grid-template-columns:1.6fr 1fr; gap:40px; margin-top:20px; }
.schedule-left { border-right:1px solid #E5E5EA; padding-right:40px; }
.time-slot-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin-top:16px; }
.time-slot { border:1px solid #E5E5EA; border-radius:8px; padding:14px 8px; text-align:center; cursor:pointer; transition:all 0.35s cubic-bezier(0.16,1,0.3,1); position:relative; overflow:hidden; text-decoration:none; color:inherit; display:block; }
.time-slot::before { content:''; position:absolute; top:0; left:0; width:100%; height:3px; background:var(--primary); transform:scaleX(0); transition:transform 0.35s cubic-bezier(0.16,1,0.3,1); }
.time-slot:hover::before { transform:scaleX(1); }
.time-slot.available { background:#F2FDF5; border-color:#D1F2D9; }
.time-slot.available .status-lbl { color:#34C759; }
.time-slot:hover.available { background:#E8FAED; border-color:#34C759; transform:translateY(-3px); box-shadow:0 8px 20px rgba(52,199,89,0.15); }
.time-slot.selected { background:#FFF2EB; border-color:#FFD2BC; box-shadow:0 0 0 2px var(--primary); }
.time-slot.selected .status-lbl { color:var(--primary); }
.time-slot.booked { background:#FFF5F5; border-color:#FFD1D1; cursor:not-allowed; opacity:0.6; }
.time-slot.booked .status-lbl { color:#FF3B30; }
.time-slot .time-lbl { display:block; font-size:14px; font-weight:700; color:#1C1C1E; margin-bottom:4px; transition:transform 0.3s ease; }
.time-slot:hover .time-lbl { transform:scale(1.05); }
.time-slot .status-lbl { display:block; font-size:11px; font-weight:600; transition:color 0.3s ease; }
.schedule-info-row { display:flex; align-items:center; gap:8px; font-size:13px; color:#636366; margin-top:20px; animation:fadeIn 0.5s ease-out; }
.schedule-info-row i { color:#8E8E93; animation:float 2s ease-in-out infinite; }

.selected-summary-card { background:#F8F9FA; border-radius:12px; padding:24px; transition:all 0.4s cubic-bezier(0.16,1,0.3,1); }
.selected-summary-card:hover { background:#FFF8F5; box-shadow:0 10px 30px rgba(255,82,0,0.08); transform:translateY(-3px); }
.summary-title { font-size:15px; font-weight:700; color:#1C1C1E; margin-bottom:16px; display:flex; align-items:center; gap:8px; }
.summary-title i { animation:pulse 2s ease-in-out infinite; }
.summary-item { display:flex; align-items:flex-start; gap:12px; margin-bottom:16px; font-size:14px; color:#1C1C1E; padding:8px; border-radius:8px; transition:all 0.3s ease; }
.summary-item:hover { background:rgba(255,82,0,0.05); transform:translateX(5px); }
.summary-item i { color:#636366; margin-top:3px; font-size:14px; width:16px; transition:all 0.3s ease; }
.summary-item:hover i { color:var(--primary); transform:scale(1.2); }
.summary-item span { color:#8E8E93; font-size:12px; display:block; margin-bottom:2px; }
.btn-booking-submit { background:var(--primary); color:var(--white); border:none; width:100%; padding:14px; border-radius:8px; font-size:15px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; gap:8px; margin-top:8px; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); position:relative; overflow:hidden; text-decoration:none; }
.btn-booking-submit::after { content:''; position:absolute; top:50%; left:50%; width:0; height:0; background:rgba(255,255,255,0.2); border-radius:50%; transform:translate(-50%,-50%); transition:width 0.6s,height 0.6s; }
.btn-booking-submit:hover::after { width:400px; height:400px; }
.btn-booking-submit:hover { background:var(--primary-hover); transform:translateY(-2px); box-shadow:0 10px 30px rgba(255,82,0,0.4); }
.btn-booking-submit:active { transform:translateY(0) scale(0.98); }
.btn-booking-submit i { transition:transform 0.3s ease; }
.btn-booking-submit:hover i { transform:translateX(4px); }

/* ============ RIWAYAT ============ */
.riwayat-section { margin-bottom:60px; }
.riwayat-card { border:1px solid #E5E5EA; border-radius:16px; padding:24px; background:var(--white); transition:all 0.4s cubic-bezier(0.16,1,0.3,1); }
.riwayat-card:hover { box-shadow:0 15px 40px rgba(0,0,0,0.06); }
.riwayat-item { display:flex; align-items:center; gap:16px; padding:16px 0; border-bottom:1px solid #F2F2F7; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); border-radius:8px; padding-left:12px; padding-right:12px; }
.riwayat-item:hover { background:#FFF8F5; transform:translateX(8px); border-bottom-color:transparent; }
.riwayat-item:last-child { border-bottom:none; }
.riwayat-icon { width:48px; height:48px; border-radius:12px; background:#FFF0E6; display:flex; align-items:center; justify-content:center; color:var(--primary); font-size:20px; flex-shrink:0; transition:all 0.4s cubic-bezier(0.34,1.56,0.64,1); }
.riwayat-item:hover .riwayat-icon { transform:scale(1.15) rotate(5deg); box-shadow:0 8px 20px rgba(255,82,0,0.2); }
.riwayat-info { flex:1; }
.riwayat-info h4 { font-size:15px; font-weight:700; color:#1C1C1E; margin-bottom:4px; transition:color 0.3s ease; }
.riwayat-item:hover .riwayat-info h4 { color:var(--primary); }
.riwayat-info p { font-size:13px; color:#636366; }
.riwayat-price { font-size:16px; font-weight:800; color:var(--primary); transition:all 0.3s ease; }
.riwayat-item:hover .riwayat-price { transform:scale(1.1); }
.status-pill { padding:5px 12px; border-radius:20px; font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.3px; display:inline-flex; align-items:center; gap:5px; transition:all 0.3s ease; }
.riwayat-item:hover .status-pill { transform:scale(1.05); }
.sp-active { background:var(--green-lt); color:var(--green); }
.sp-success { background:var(--blue-lt); color:var(--blue); }
.sp-pending { background:var(--yellow-lt); color:#D97706; }
.sp-inactive { background:var(--red-lt); color:var(--red); }

/* ============ MEMBER BANNER ============ */
.member-promo-banner { background:#0B0B0C; border-radius:16px; padding:40px; display:flex; align-items:center; justify-content:space-between; gap:40px; margin-bottom:60px; color:var(--white); overflow:hidden; position:relative; transition:all 0.4s cubic-bezier(0.16,1,0.3,1); }
.member-promo-banner:hover { box-shadow:0 20px 50px rgba(0,0,0,0.3); transform:translateY(-3px); }
.member-promo-banner::before { content:''; position:absolute; top:-50%; right:-20%; width:500px; height:500px; background:radial-gradient(circle,rgba(255,82,0,0.1) 0%,transparent 70%); animation:float 6s ease-in-out infinite; pointer-events:none; }
.member-banner-left { display:flex; align-items:center; gap:30px; max-width:55%; position:relative; z-index:2; }
.member-img-card { background:#1C1C1E; padding:16px; border-radius:12px; border:1px solid #2C2C2E; width:140px; text-align:center; transition:all 0.4s cubic-bezier(0.16,1,0.3,1); animation:float 3s ease-in-out infinite; }
.member-promo-banner:hover .member-img-card { transform:scale(1.05); box-shadow:0 10px 30px rgba(255,82,0,0.2); }
.member-img-card img { width:100%; height:auto; transition:transform 0.4s ease; }
.member-promo-banner:hover .member-img-card img { transform:scale(1.1); }
.member-banner-desc h3 { font-size:24px; font-weight:800; margin-bottom:8px; }
.member-banner-desc h3 span { color:var(--primary); position:relative; }
.member-banner-desc p { color:#A0A0A5; font-size:13px; line-height:1.5; margin-bottom:16px; }
.btn-join-member { background:var(--primary); color:var(--white); border:none; padding:10px 20px; border-radius:6px; font-size:13px; font-weight:700; cursor:pointer; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); position:relative; overflow:hidden; text-decoration:none; display:inline-block; }
.btn-join-member::after { content:''; position:absolute; top:50%; left:50%; width:0; height:0; background:rgba(255,255,255,0.2); border-radius:50%; transform:translate(-50%,-50%); transition:width 0.5s,height 0.5s; }
.btn-join-member:hover::after { width:200px; height:200px; }
.btn-join-member:hover { background:var(--primary-hover); transform:translateY(-2px); box-shadow:0 8px 25px rgba(255,82,0,0.4); }
.btn-join-member:active { transform:translateY(0) scale(0.98); }
.member-banner-right { display:flex; gap:20px; position:relative; z-index:2; }
.member-benefit-item { background:#121214; border:1px solid #1C1C1E; padding:20px; border-radius:12px; text-align:center; width:150px; transition:all 0.4s cubic-bezier(0.16,1,0.3,1); }
.member-benefit-item:hover { transform:translateY(-8px); border-color:rgba(255,82,0,0.3); box-shadow:0 15px 30px rgba(0,0,0,0.3); }
.benefit-icon-circle { background:#FFF0E6; width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 12px; transition:all 0.4s cubic-bezier(0.34,1.56,0.64,1); }
.member-benefit-item:hover .benefit-icon-circle { transform:scale(1.15) rotate(10deg); box-shadow:0 8px 20px rgba(255,82,0,0.2); }
.benefit-icon-circle i { color:var(--primary); font-size:18px; transition:transform 0.3s ease; }
.member-benefit-item:hover .benefit-icon-circle i { transform:scale(1.2); }
.member-benefit-item h5 { font-size:12px; font-weight:700; margin-bottom:4px; transition:color 0.3s ease; }
.member-benefit-item:hover h5 { color:var(--primary); }
.member-benefit-item p { font-size:10px; color:#8E8E93; line-height:1.4; }

/* ============ FACILITIES ============ */
.facilities-section { margin-bottom:60px; }
.fac-grid { display:grid; grid-template-columns:repeat(4,1fr); gap:24px; margin-top:24px; }
.fac-card { border:1px solid #E5E5EA; border-radius:12px; padding:24px; display:flex; align-items:flex-start; gap:16px; transition:all 0.4s cubic-bezier(0.16,1,0.3,1); cursor:default; }
.fac-card:hover { transform:translateY(-5px); box-shadow:0 15px 30px rgba(0,0,0,0.08); border-color:rgba(255,82,0,0.2); background:#FFFDFB; }
.fac-card:hover .fac-icon { transform:scale(1.2) rotate(10deg); color:var(--primary); }
.fac-icon { color:var(--primary); font-size:24px; margin-top:2px; transition:all 0.4s cubic-bezier(0.34,1.56,0.64,1); }
.fac-info h4 { font-size:15px; font-weight:700; color:#1C1C1E; margin-bottom:6px; transition:color 0.3s ease; }
.fac-card:hover .fac-info h4 { color:var(--primary); }
.fac-info p { font-size:13px; color:#636366; line-height:1.5; }

/* ============ CTA BANNER ============ */
.cta-banner { background:linear-gradient(rgba(0,0,0,0.7),rgba(0,0,0,0.7)),url('https://images.unsplash.com/photo-1544919982-b61976f0ba43?q=80&w=1500'); background-size:cover; background-position:center; border-radius:16px; padding:60px; display:flex; justify-content:space-between; align-items:center; color:var(--white); margin-bottom:60px; position:relative; overflow:hidden; transition:all 0.4s cubic-bezier(0.16,1,0.3,1); }
.cta-banner:hover { transform:translateY(-3px); box-shadow:0 20px 50px rgba(0,0,0,0.2); }
.cta-banner::before { content:''; position:absolute; top:0; left:0; right:0; bottom:0; background:linear-gradient(45deg,rgba(255,82,0,0.1),transparent,rgba(255,82,0,0.05)); animation:gradientShift 8s ease infinite; background-size:200% 200%; pointer-events:none; }
.cta-left { max-width:60%; position:relative; z-index:2; }
.cta-title { font-size:32px; font-weight:800; margin-bottom:12px; }
.cta-desc { font-size:15px; color:#D1D1D6; line-height:1.6; }
.btn-cta { background:var(--primary); color:var(--white); border:none; padding:16px 32px; border-radius:8px; font-size:16px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:10px; transition:all 0.3s cubic-bezier(0.16,1,0.3,1); position:relative; overflow:hidden; text-decoration:none; z-index:2; }
.btn-cta::after { content:''; position:absolute; top:50%; left:50%; width:0; height:0; background:rgba(255,255,255,0.2); border-radius:50%; transform:translate(-50%,-50%); transition:width 0.6s,height 0.6s; }
.btn-cta:hover::after { width:400px; height:400px; }
.btn-cta:hover { background:var(--primary-hover); transform:translateY(-3px); box-shadow:0 15px 40px rgba(255,82,0,0.4); }
.btn-cta:active { transform:translateY(-1px) scale(0.98); }
.btn-cta i { transition:transform 0.3s ease; }
.btn-cta:hover i { transform:translateX(6px); }

/* ============ FOOTER ============ */
footer { background:var(--dark-bg); color:#8E8E93; padding:80px 80px 40px; border-top:1px solid #1C1C1E; position:relative; overflow:hidden; }
footer::before { content:''; position:absolute; top:0; left:0; right:0; height:1px; background:linear-gradient(90deg,transparent,var(--primary),transparent); animation:shimmer 3s linear infinite; background-size:200% 100%; }
.footer-grid { display:grid; grid-template-columns:1.5fr 1fr 1fr 1.2fr; gap:40px; margin-bottom:60px; }
.footer-logo { display:flex; align-items:center; gap:10px; margin-bottom:16px; transition:transform 0.3s ease; }
.footer-logo:hover { transform:scale(1.05); }
.footer-logo img { height:70px; transition:transform 0.5s ease; }
.footer-logo:hover img { transform:rotate(5deg); }
.footer-logo span { color:var(--white); font-size:20px; font-weight:800; }
.footer-desc { font-size:13px; line-height:1.6; margin-bottom:24px; }
.social-links { display:flex; gap:12px; }
.social-btn { width:36px; height:36px; border-radius:50%; background:#1C1C1E; color:var(--white); display:flex; align-items:center; justify-content:center; text-decoration:none; transition:all 0.3s cubic-bezier(0.34,1.56,0.64,1); }
.social-btn:hover { background:var(--primary); transform:translateY(-3px) scale(1.1); box-shadow:0 8px 20px rgba(255,82,0,0.3); }
.social-btn:active { transform:scale(0.95); }
.footer-col h4 { color:var(--white); font-size:15px; font-weight:700; margin-bottom:20px; position:relative; display:inline-block; }
.footer-col h4::after { content:''; position:absolute; bottom:-4px; left:0; width:30px; height:2px; background:var(--primary); transition:width 0.3s ease; }
.footer-col:hover h4::after { width:100%; }
.footer-col ul { list-style:none; }
.footer-col ul li { margin-bottom:12px; }
.footer-col ul li a { color:#8E8E93; text-decoration:none; font-size:13px; transition:all 0.3s ease; display:inline-block; position:relative; }
.footer-col ul li a::after { content:''; position:absolute; bottom:-2px; left:0; width:0; height:1px; background:var(--primary); transition:width 0.3s ease; }
.footer-col ul li a:hover { color:var(--white); transform:translateX(5px); }
.footer-col ul li a:hover::after { width:100%; }
.contact-item { display:flex; gap:12px; font-size:13px; line-height:1.5; margin-bottom:16px; transition:all 0.3s ease; padding:4px; border-radius:6px; }
.contact-item:hover { background:rgba(255,82,0,0.05); transform:translateX(5px); }
.contact-item i { color:var(--primary); font-size:14px; margin-top:3px; transition:transform 0.3s ease; }
.contact-item:hover i { transform:scale(1.2); }
.footer-bottom { border-top:1px solid #1C1C1E; padding-top:30px; text-align:center; font-size:13px; position:relative; }

.swal-toast { border-radius:12px !important; font-family:'Plus Jakarta Sans',sans-serif !important; }

/* ============ SCROLL PROGRESS BAR ============ */
.scroll-progress { position:fixed; top:0; left:0; height:3px; background:linear-gradient(90deg,var(--primary),#FF8C42); z-index:9999; transform-origin:left; transform:scaleX(0); transition:transform 0.1s ease-out; }

/* ============ FLOATING PARTICLES ============ */
.particle { position:absolute; border-radius:50%; pointer-events:none; }
.particle-1 { width:6px; height:6px; background:rgba(255,82,0,0.3); animation:float 4s ease-in-out infinite; }
.particle-2 { width:8px; height:8px; background:rgba(255,82,0,0.2); animation:float 5s ease-in-out infinite 1s; }
.particle-3 { width:4px; height:4px; background:rgba(255,82,0,0.4); animation:float 3s ease-in-out infinite 0.5s; }

/* ============ CARD SHINE ============ */
.card-shine { position:relative; overflow:hidden; }
.card-shine::before { content:''; position:absolute; top:0; left:-100%; width:100%; height:100%; background:linear-gradient(90deg,transparent,rgba(255,255,255,0.2),transparent); transition:left 0.6s ease; z-index:10; pointer-events:none; }
.card-shine:hover::before { left:100%; }

/* ============ MAGNETIC EFFECT ============ */
.magnetic { transition:transform 0.2s ease-out; }

/* ============ SKELETON LOADING ============ */
.skeleton { background:linear-gradient(90deg,#f0f0f0 25%,#e0e0e0 50%,#f0f0f0 75%); background-size:200% 100%; animation:shimmer 1.5s infinite; border-radius:4px; }

/* ============ HOVER LIFT ============ */
.hover-lift { transition:all 0.4s cubic-bezier(0.16,1,0.3,1); }
.hover-lift:hover { transform:translateY(-5px); box-shadow:0 15px 30px rgba(0,0,0,0.1); }

/* ============ NEON TEXT ============ */
.neon-text { animation:neonPulse 2s ease-in-out infinite; }

/* ============ BADGE PULSE ============ */
.badge-pulse { animation:pulse 2s ease-in-out infinite; }

/* ============ ROTATING ELEMENT ============ */
.rotate-slow { animation:spinSlow 10s linear infinite; }
.rotate-slow:hover { animation-play-state:paused; }

/* ============ MORPHING BACKGROUND ============ */
.morph-bg { animation:morph 8s ease-in-out infinite; }

/* ============ GRADIENT TEXT ============ */
.gradient-text { background:linear-gradient(90deg,var(--primary),#FF8C42); -webkit-background-clip:text; -webkit-text-fill-color:transparent; background-clip:text; }

/* ============ GLOWING BORDER ============ */
.glow-border { animation:borderGlow 2s ease-in-out infinite; }

/* ============ WAVE ANIMATION ============ */
.wave-anim span { display:inline-block; animation:wave 1s ease-in-out infinite; }
.wave-anim span:nth-child(1){animation-delay:0s}
.wave-anim span:nth-child(2){animation-delay:0.1s}
.wave-anim span:nth-child(3){animation-delay:0.2s}
.wave-anim span:nth-child(4){animation-delay:0.3s}
.wave-anim span:nth-child(5){animation-delay:0.4s}

/* ============ TYPING EFFECT ============ */
.typing-effect { overflow:hidden; white-space:nowrap; animation:typing 2s steps(20) forwards; }

/* ============ COUNTER ANIMATION ============ */
.counter-anim { font-variant-numeric:tabular-nums; display:inline-block; }

/* ============ PARALLAX ============ */
.parallax { will-change:transform; }

/* ============ IMAGE ZOOM ON HOVER ============ */
.img-zoom { overflow:hidden; }
.img-zoom img { transition:transform 0.6s cubic-bezier(0.16,1,0.3,1); }
.img-zoom:hover img { transform:scale(1.1); }

/* ============ FLIP CARD ============ */
.flip-container { perspective:1000px; }
.flipper { transition:transform 0.6s; transform-style:preserve-3d; }
.flip-container:hover .flipper { transform:rotateY(180deg); }

/* ============ RIPPLE EFFECT ============ */
.ripple { position:relative; overflow:hidden; }
.ripple::after { content:''; position:absolute; top:50%; left:50%; width:0; height:0; background:rgba(255,255,255,0.3); border-radius:50%; transform:translate(-50%,-50%); transition:width 0.6s,height 0.6s; }
.ripple:hover::after { width:300px; height:300px; }

/* ============ TOOLTIP ============ */
[data-tooltip] { position:relative; }
[data-tooltip]::before { content:attr(data-tooltip); position:absolute; bottom:100%; left:50%; transform:translateX(-50%) translateY(10px); background:#1C1C1E; color:var(--white); padding:6px 12px; border-radius:6px; font-size:12px; white-space:nowrap; opacity:0; pointer-events:none; transition:all 0.3s ease; z-index:100; }
[data-tooltip]:hover::before { opacity:1; transform:translateX(-50%) translateY(0); }

/* ============ SMOOTH SCROLL ============ */
html { scroll-behavior:smooth; }

/* ============ CUSTOM SCROLLBAR ============ */
::-webkit-scrollbar { width:8px; }
::-webkit-scrollbar-track { background:#f1f1f1; }
::-webkit-scrollbar-thumb { background:var(--primary); border-radius:4px; }
::-webkit-scrollbar-thumb:hover { background:var(--primary-hover); }

/* ============ SELECTION COLOR ============ */
::selection { background:rgba(255,82,0,0.3); color:#1C1C1E; }

/* ============ FOCUS STYLES ============ */
:focus-visible { outline:2px solid var(--primary); outline-offset:2px; }

/* ============ REDUCED MOTION ============ */
@media (prefers-reduced-motion:reduce) {
  *,*::before,*::after { animation-duration:0.01ms !important; animation-iteration-count:1 !important; transition-duration:0.01ms !important; }
}

/* ============ RESPONSIVE ============ */
@media (max-width:1200px) {
  .hero { padding:40px 40px; flex-direction:column; }
  .search-widget { width:100%; max-width:500px; }
  .features-row { grid-template-columns:repeat(2,1fr); padding:40px 40px; }
  .main-container { padding:40px 40px; }
  .court-grid-3 { grid-template-columns:repeat(2,1fr); }
  .schedule-layout { grid-template-columns:1fr; }
  .schedule-left { border-right:none; padding-right:0; border-bottom:1px solid #E5E5EA; padding-bottom:30px; margin-bottom:30px; }
  .member-promo-banner { flex-direction:column; }
  .member-banner-left { max-width:100%; }
  .fac-grid { grid-template-columns:repeat(2,1fr); }
  .cta-banner { flex-direction:column; text-align:center; gap:30px; }
  .cta-left { max-width:100%; }
  .footer-grid { grid-template-columns:repeat(2,1fr); }
  nav { padding:0 40px; }
}
@media (max-width:768px) {
  .hero-title { font-size:36px; }
  .features-row { grid-template-columns:1fr; }
  .court-grid-3 { grid-template-columns:1fr; }
  .time-slot-grid { grid-template-columns:repeat(3,1fr); }
  .member-banner-right { flex-wrap:wrap; justify-content:center; }
  .fac-grid { grid-template-columns:1fr; }
  .footer-grid { grid-template-columns:1fr; }
  nav { padding:0 20px; }
  .nav-links { display:none; }
  .main-container { padding:30px 20px; }
}
</style>
</head>
<body>

<!-- NAVBAR -->
<nav>
    <a href="view_customer.php" class="nav-logo">
        <img src="../asset/image/logo2.png" alt="HoopBall">
    </a>
    <div class="nav-links">
        <a href="view_customer.php" class="active">Beranda</a>
        <a href="booking_customer.php">Booking</a>
        <a href="pembatalan_customer.php">Pembatalan</a>
        <a href="langganan_customer.php">Member</a>
        <a href="pembelian_customer.php">Pembelian</a>
        <a href="#">Tentang</a>
        <a href="#">Kontak</a>
    </div>

    <!-- User Dropdown -->
    <div class="nav-user-container">
        <div class="nav-user">
            <?php if (!empty($photo_profile) && file_exists($photo_profile)): ?>
                <img src="<?php echo htmlspecialchars($photo_profile); ?>" alt="Avatar" class="user-avatar">
            <?php else: ?>
                <i class="fa-solid fa-circle-user user-icon"></i>
            <?php endif; ?>
            <span><?php echo htmlspecialchars($nama_customer); ?></span>
            <?php if ($has_member): ?>
                <span class="member-badge-nav"><i class="fa-solid fa-crown"></i> <?php echo htmlspecialchars($member_tipe); ?></span>
            <?php endif; ?>
            <i class="fa-solid fa-chevron-down arrow"></i>
        </div>
        <div class="dropdown-menu">
            <div class="user-info-header">
                <span class="u-name"><?php echo htmlspecialchars($nama_customer); ?></span>
                <span class="u-role">Customer <?php echo $has_member ? '• Member ' . htmlspecialchars($member_tipe) : ''; ?></span>
            </div>
            <a href="../profile/profile_customer.php"><i class="fa-solid fa-user"></i> Profil Saya</a>
            <a href="../customer/booking_customer.php"><i class="fa-solid fa-calendar-check"></i> Riwayat Booking</a>
            <a href="../customer/langganan_customer.php"><i class="fa-solid fa-crown"></i> Langganan Member</a>
            <div class="dropdown-divider"></div>
            <a href="#" onclick="confirmHapusAkun(event)" style="color: #ff3b30;"><i class="fa-solid fa-trash-can"></i> Hapus Akun</a>
            <a href="../login/logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
        </div>
    </div>
</nav>
<!-- HERO SECTION -->
<header class="hero">
    <div class="hero-left">
        <?php if ($has_member): ?>
        <div class="member-badge-hero">
            <i class="fa-solid fa-crown"></i> Member <?php echo htmlspecialchars($member_tipe); ?> Aktif
        </div>
        <?php endif; ?>
        <h1 class="hero-title">Sewa Lapangan<br>Basket Jadi<br><span>Lebih Mudah</span></h1>
        <p class="hero-desc">Booking lapangan favoritmu secara online dengan cepat, cek jadwal real-time, dan nikmati promo khusus member.</p>
        <div class="hero-btns">
            <a href="booking_customer.php" class="btn-primary">Booking Sekarang <i class="fa-solid fa-arrow-right"></i></a>
            <a href="#jadwal-section" class="btn-outline" onclick="document.getElementById('jadwal-section').scrollIntoView({behavior:'smooth'}); return false;"><i class="fa-solid fa-calendar-days"></i> Lihat Jadwal</a>
        </div>
    </div>

    <!-- WIDGET CARI LAPANGAN -->
    <div class="search-widget">
        <h3 class="widget-title"><i class="fa-solid fa-magnifying-glass"></i> Cari & Booking</h3>

        <div class="form-group">
            <label class="form-label">Pilih Lapangan</label>
            <div class="input-wrapper">
                <i class="fa-solid fa-basketball"></i>
                <select class="form-select" id="heroLapangan" onchange="filterJadwal()">
                    <option value="">Semua Lapangan</option>
                    <?php
                    $q_all_lap = sqlsrv_query($conn, "SELECT ID_Lapangan, Nama_Lapangan FROM Lapangan WHERE Status = 1 AND Is_Deleted = 0 ORDER BY Nama_Lapangan ASC");
                    if ($q_all_lap) {
                        while ($lap = sqlsrv_fetch_array($q_all_lap, SQLSRV_FETCH_ASSOC)) {
                            echo '<option value="'.htmlspecialchars($lap['ID_Lapangan']).'">'.htmlspecialchars($lap['Nama_Lapangan']).'</option>';
                        }
                    }
                    ?>
                </select>
            </div>
        </div>

        <div class="form-row-2">
            <div class="form-group">
                <label class="form-label">Tanggal</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-calendar-days"></i>
                    <input type="date" class="form-input" id="heroTanggal" value="<?php echo date('Y-m-d'); ?>" onchange="filterJadwal()">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Jam</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-clock"></i>
                    <input type="time" class="form-input" id="heroJam" value="18:00" onchange="filterJadwal()">
                </div>
            </div>
        </div>

        <a href="booking_customer.php" class="btn-widget" style="text-decoration: none; display: inline-flex; justify-content: center;">
            <i class="fa-solid fa-calendar-check"></i> Lanjutkan Booking
        </a>
    </div>
</header>

<!-- FITUR ROW -->
<section class="features-row">
    <div class="feature-card">
        <div class="feature-icon-circle"><i class="fa-solid fa-calendar-check"></i></div>
        <div class="feature-text">
            <h4>Booking Online</h4>
            <p>Pesan lapangan kapan saja di mana saja dengan mudah dan cepat.</p>
        </div>
    </div>
    <div class="feature-card">
        <div class="feature-icon-circle"><i class="fa-solid fa-clock"></i></div>
        <div class="feature-text">
            <h4>Jadwal Real-Time</h4>
            <p>Cek ketersediaan lapangan secara real-time dan akurat setiap saat.</p>
        </div>
    </div>
    <div class="feature-card">
        <div class="feature-icon-circle"><i class="fa-solid fa-tags"></i></div>
        <div class="feature-text">
            <h4>Promo Member</h4>
            <p>Dapatkan promo menarik dan diskon eksklusif untuk member setia.</p>
        </div>
    </div>
    <div class="feature-card">
        <div class="feature-icon-circle"><i class="fa-solid fa-award"></i></div>
        <div class="feature-text">
            <h4>Lapangan Berkualitas</h4>
            <p>Lapangan terawat, nyaman, dan standar profesional untuk pengalaman terbaik.</p>
        </div>
    </div>
</section>

<!-- MAIN CONTENT -->
<main class="main-container">

    <!-- RIWAYAT BOOKING TERBARU -->
    <?php if (!empty($riwayat_list)): ?>
    <section class="riwayat-section">
        <div class="section-header">
            <div>
                <h2 class="section-title">Booking Terbaru Saya</h2>
                <p class="section-subtitle">Status booking lapangan yang baru saja Anda lakukan.</p>
            </div>
            <a href="booking_customer.php" class="section-action">Lihat Semua <i class="fa-solid fa-chevron-right"></i></a>
        </div>
        <div class="riwayat-card">
            <?php foreach ($riwayat_list as $rb): 
                $status = $status_labels[$rb['Status']] ?? $status_labels[0];
            ?>
            <div class="riwayat-item">
                <div class="riwayat-icon"><i class="fa-solid fa-basketball"></i></div>
                <div class="riwayat-info">
                    <h4><?php echo htmlspecialchars($rb['Nama_Lapangan']); ?></h4>
                    <p><?php echo formatTanggal($rb['Tanggal']); ?> | <?php echo formatJam($rb['Jam_Mulai']); ?> - <?php echo formatJam($rb['Jam_Selesai']); ?> | <?php echo $rb['Metode_Pembayaran']; ?></p>
                </div>
                <div style="text-align: right;">
                    <div class="riwayat-price"><?php echo rupiahFormat($rb['Total_Bayar']); ?></div>
                    <span class="status-pill <?php echo $status['class']; ?>" style="margin-top: 4px;">
                        <i class="fa-solid <?php echo $status['icon']; ?>"></i> <?php echo $status['label']; ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- LAPANGAN POPULER -->
    <section>
        <div class="section-header">
            <div>
                <h2 class="section-title">Lapangan Populer</h2>
                <p class="section-subtitle">Temukan pilihan lapangan basket terbaik yang sering disewa.</p>
            </div>
            <a href="booking_customer.php" class="section-action">Lihat Semua Lapangan <i class="fa-solid fa-chevron-right"></i></a>
        </div>

        <div class="court-grid-3">
            <?php 
            if (!empty($lapangan_list)):
                $fallback_images = [
                    "https://images.unsplash.com/photo-1546519638-68e109498ffc?q=80&w=800",
                    "https://images.unsplash.com/photo-1504450758481-7338eba7524a?q=80&w=800",
                    "https://images.unsplash.com/photo-1505666287802-931dc83948e9?q=80&w=800"
                ];
                $idx = 0;
                foreach ($lapangan_list as $lapangan): 
                    $img = (!empty($lapangan['Photo_Lapangan']) && file_exists($lapangan['Photo_Lapangan'])) 
                           ? htmlspecialchars($lapangan['Photo_Lapangan']) 
                           : $fallback_images[$idx % 3];
                    $idx++;
            ?>
                <div class="court-card-new">
                    <div class="court-img-container">
                        <img class="court-img-new" src="<?php echo $img; ?>" alt="<?php echo htmlspecialchars($lapangan['Nama_Lapangan']); ?>">
                    </div>
                    <div class="court-body-new">
                        <div class="court-meta">
                            <h3 class="court-name-new"><?php echo htmlspecialchars($lapangan['Nama_Lapangan']); ?></h3>
                        </div>
                        <div class="court-footer">
                            <div class="court-price-box"><strong><?php echo rupiahFormat($lapangan['Harga_Sewa']); ?></strong> / jam</div>
                            <a href="booking_customer.php" class="btn-detail">Booking</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php else: ?>
                <div style="grid-column: span 3; text-align: center; padding: 40px; color: #8E8E93;">
                    <i class="fa-solid fa-circle-info" style="font-size: 32px; margin-bottom: 12px; color: var(--primary);"></i>
                    <p>Tidak ada data lapangan yang aktif saat ini.</p>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- JADWAL TERSEDIA -->
    <section class="schedule-box" id="jadwal-section">
        <div class="section-header" style="margin-bottom: 0;">
            <div>
                <h2 class="section-title">Jadwal Tersedia</h2>
                <p class="section-subtitle">Pilih jadwal dan lanjutkan ke halaman booking.</p>
            </div>
            <a href="booking_customer.php" class="section-action">Lihat Semua Jadwal <i class="fa-solid fa-chevron-right"></i></a>
        </div>

        <div class="schedule-layout">
            <div class="schedule-left">
                <div class="time-slot-grid" id="jadwalGrid">
                    <?php 
                    if (!empty($jadwal_list)):
                        foreach ($jadwal_list as $jadwal):
                            $jam_mulai = $jadwal['Jam_Mulai'] instanceof DateTime ? $jadwal['Jam_Mulai']->format('H:i') : substr($jadwal['Jam_Mulai'], 0, 5);
                            $jam_selesai = $jadwal['Jam_Selesai'] instanceof DateTime ? $jadwal['Jam_Selesai']->format('H:i') : substr($jadwal['Jam_Selesai'], 0, 5);
                            $tanggal_str = $jadwal['Tanggal'] instanceof DateTime ? $jadwal['Tanggal']->format('Y-m-d') : $jadwal['Tanggal'];
                    ?>
                        <a href="booking_customer.php?jadwal=<?php echo $jadwal['ID_Jadwal']; ?>" 
                           class="time-slot available" 
                           style="text-decoration: none; color: inherit;"
                           data-lapangan="<?php echo htmlspecialchars($jadwal['Nama_Lapangan']); ?>"
                           data-tanggal="<?php echo $tanggal_str; ?>"
                           data-jam="<?php echo $jam_mulai; ?> - <?php echo $jam_selesai; ?>">
                            <span class="time-lbl"><?php echo $jam_mulai; ?></span>
                            <span class="status-lbl"><?php echo htmlspecialchars($jadwal['Nama_Lapangan']); ?></span>
                            <span style="font-size: 10px; color: #8E8E93; display: block; margin-top: 2px;"><?php echo formatTanggal($jadwal['Tanggal']); ?></span>
                        </a>
                    <?php endforeach; ?>
                    <?php else: ?>
                        <div style="grid-column: span 5; text-align: center; padding: 20px; color: #8E8E93;">
                            <i class="fa-solid fa-calendar-xmark" style="font-size: 24px; margin-bottom: 8px; color: var(--primary);"></i>
                            <div>Tidak ada jadwal tersedia saat ini.</div>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="schedule-info-row">
                    <i class="fa-solid fa-circle-info"></i>
                    <span>Klik jadwal untuk langsung menuju halaman booking.</span>
                </div>
            </div>

            <div class="schedule-right">
                <div class="selected-summary-card">
                    <h4 class="summary-title"><i class="fa-solid fa-lightbulb" style="color: var(--primary);"></i> Cara Booking</h4>
                    <div class="summary-item">
                        <i class="fa-solid fa-1" style="color: var(--primary);"></i>
                        <div><span>Langkah 1</span><strong>Pilih jadwal lapangan yang tersedia</strong></div>
                    </div>
                    <div class="summary-item">
                        <i class="fa-solid fa-2" style="color: var(--primary);"></i>
                        <div><span>Langkah 2</span><strong>Pilih metode pembayaran (Transfer/QRIS)</strong></div>
                    </div>
                    <div class="summary-item">
                        <i class="fa-solid fa-3" style="color: var(--primary);"></i>
                        <div><span>Langkah 3</span><strong>Lakukan pembayaran dan tunggu konfirmasi</strong></div>
                    </div>
                    <a href="booking_customer.php" class="btn-booking-submit" style="text-decoration: none;">
                        <i class="fa-solid fa-calendar-check"></i> Booking Sekarang
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- BANNER PROMO MEMBER -->
    <section class="member-promo-banner">
        <div class="member-banner-left">
            <div class="member-img-card">
                <img src="../asset/image/card.png" alt="Card Graphic" style="max-height: 100px; object-fit: contain;">
                <div style="color: #fff; font-size: 10px; font-weight: 700; margin-top: 8px; letter-spacing: 1px;">HOOPBALL MEMBER</div>
            </div>
            <div class="member-banner-desc">
                <h3>Jadi Member,<br><span>Main Makin Hemat!</span></h3>
                <p>
                    Tipe member tersedia: 
                    <strong>
                    <?php 
                        $types = [];
                        foreach ($tipe_member_list as $tm) {
                            $types[] = htmlspecialchars($tm['Nama_Tipe']);
                        }
                        echo implode(', ', $types);
                    ?>
                    </strong>.
                    Nikmati potongan harga dan prioritas jadwal.
                </p>
                <a href="../customer/langganan_customer.php" class="btn-join-member" style="text-decoration: none; display: inline-block;">
                    <?php echo $has_member ? 'Kelola Member' : 'Gabung Member'; ?>
                </a>
            </div>
        </div>

        <div class="member-banner-right">
            <div class="member-benefit-item">
                <div class="benefit-icon-circle"><i class="fa-solid fa-percent"></i></div>
                <h5>Potongan Harga</h5>
                <p>Hingga <?php echo !empty($tipe_member_list) ? rupiahFormat($tipe_member_list[count($tipe_member_list)-1]['Potongan_Harga']) : 'Rp 35.000'; ?> per booking.</p>
            </div>
            <div class="member-benefit-item">
                <div class="benefit-icon-circle"><i class="fa-solid fa-calendar-check"></i></div>
                <h5>Prioritas Jadwal</h5>
                <p>Akses lebih awal untuk jadwal prime time.</p>
            </div>
            <div class="member-benefit-item">
                <div class="benefit-icon-circle"><i class="fa-solid fa-gift"></i></div>
                <h5>Promo Eksklusif</h5>
                <p>Dapatkan promo dan penawaran spesial khusus member.</p>
            </div>
        </div>
    </section>

    <!-- FASILITAS UNGGULAN -->
    <section class="facilities-section">
        <div class="section-header">
            <div>
                <h2 class="section-title">Fasilitas Unggulan</h2>
                <p class="section-subtitle">Nikmati fasilitas pendukung terbaik untuk pengalaman bermain yang lebih nyaman.</p>
            </div>
        </div>

        <div class="fac-grid">
            <div class="fac-card">
                <i class="fa-solid fa-door-open fac-icon"></i>
                <div class="fac-info">
                    <h4>Ruang Ganti</h4>
                    <p>Area ganti yang bersih, steril, dan nyaman.</p>
                </div>
            </div>
            <div class="fac-card">
                <i class="fa-solid fa-shower fac-icon"></i>
                <div class="fac-info">
                    <h4>Shower Mandi</h4>
                    <p>Fasilitas mandi air hangat setelah selesai bermain.</p>
                </div>
            </div>
            <div class="fac-card">
                <i class="fa-solid fa-square-parking fac-icon"></i>
                <div class="fac-info">
                    <h4>Parkir Luas</h4>
                    <p>Area parkir terpadu yang aman untuk motor dan mobil.</p>
                </div>
            </div>
            <div class="fac-card">
                <i class="fa-solid fa-wifi fac-icon"></i>
                <div class="fac-info">
                    <h4>WiFi & Lounge</h4>
                    <p>Tunggu giliran bermain dengan nyaman di ruang tunggu.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA BANNER -->
    <section class="cta-banner">
        <div class="cta-left">
            <h2 class="cta-title">Siap Main Bareng?</h2>
            <p class="cta-desc">Booking lapangan favoritmu sekarang dan rasakan pengalaman bermain terbaik bersama teman-temanmu di HoopBall!</p>
        </div>
        <a href="booking_customer.php" class="btn-cta" style="text-decoration: none;">
            Booking Sekarang <i class="fa-solid fa-arrow-right"></i>
        </a>
    </section>

</main>

<!-- FOOTER -->
<footer>
    <div class="footer-grid">
        <div>
            <div class="footer-logo">
                <img src="../asset/image/logo.png" alt="HoopBall">
            </div>
            <p class="footer-desc">HoopBall adalah platform penyewaan lapangan basket online yang mudah, cepat, dan terpercaya.</p>
            <div class="social-links">
                <a href="#" class="social-btn"><i class="fa-brands fa-instagram"></i></a>
                <a href="#" class="social-btn"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" class="social-btn"><i class="fa-brands fa-tiktok"></i></a>
                <a href="#" class="social-btn"><i class="fa-brands fa-youtube"></i></a>
            </div>
        </div>

        <div class="footer-col">
            <h4>Navigasi</h4>
            <ul>
                <li><a href="view_customer.php">Beranda</a></li>
                <li><a href="booking_customer.php">Booking</a></li>
                <li><a href="pembatalan_customer.php">Pembatalan</a></li>
                <li><a href="langganan_customer.php">Member</a></li>
                <li><a href="#">Pembelian</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Informasi</h4>
            <ul>
                <li><a href="#">Cara Booking</a></li>
                <li><a href="#">Syarat & Ketentuan</a></li>
                <li><a href="#">Kebijakan Privasi</a></li>
                <li><a href="#">FAQ</a></li>
            </ul>
        </div>

        <div class="footer-col">
            <h4>Hubungi Kami</h4>
            <div class="contact-item">
                <i class="fa-solid fa-location-dot"></i>
                Jl. Olahraga No. 10, Kebayoran Baru, Jakarta Selatan 12190
            </div>
            <div class="contact-item">
                <i class="fa-solid fa-phone"></i>
                +62 812-3456-7890
            </div>
            <div class="contact-item">
                <i class="fa-solid fa-envelope"></i>
                info@hoopball.id
            </div>
            <div class="contact-item">
                <i class="fa-solid fa-clock"></i>
                Setiap hari 07:00 - 23:00 WIB
            </div>
        </div>
    </div>
    <div class="footer-bottom">
        <p>&copy; 2025 HoopBall. All rights reserved.</p>
    </div>
</footer>

<script>
// ============================================================================
// FILTER JADWAL (Hero Widget)
// ============================================================================
function filterJadwal() {
    // This is a visual filter - in real implementation, 
    // it would filter the displayed jadwal or redirect to booking page with params
    const lapangan = document.getElementById('heroLapangan').value;
    const tanggal = document.getElementById('heroTanggal').value;
    const jam = document.getElementById('heroJam').value;

    // Store in session/localStorage for booking page to pick up
    localStorage.setItem('booking_filter_lapangan', lapangan);
    localStorage.setItem('booking_filter_tanggal', tanggal);
    localStorage.setItem('booking_filter_jam', jam);
}

// ============================================================================
// HARD DELETE AKUN CONFIRMATION
// ============================================================================
function confirmHapusAkun(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Hapus Akun Permanen?',
        html: '<strong style="color:#FF3B30;">PERINGATAN:</strong> Tindakan ini tidak dapat dibatalkan!<br><br>' +
              'Akun Anda akan dihapus dari sistem dan Anda harus mendaftar ulang untuk menggunakan layanan kami.<br><br>' +
              '<span style="color:#8E8E93; font-size:12px;">Data akan dihapus secara permanen.</span>',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#FF3B30',
        cancelButtonColor: '#8E8E93',
        confirmButtonText: 'Ya, Hapus Akun Saya!',
        cancelButtonText: 'Batal',
        reverseButtons: true,
        allowOutsideClick: false,
        allowEscapeKey: false
    }).then((result) => {
        if (result.isConfirmed) {
            let timerInterval;
            Swal.fire({
                title: 'Menghapus Akun...',
                html: 'Mohon tunggu, akun Anda sedang diproses.<br><b></b>',
                timer: 2000,
                timerProgressBar: true,
                allowOutsideClick: false,
                allowEscapeKey: false,
                didOpen: () => {
                    Swal.showLoading();
                    const timer = Swal.getHtmlContainer().querySelector('b');
                    timerInterval = setInterval(() => {
                        timer.textContent = Math.ceil(Swal.getTimerLeft() / 1000) + ' detik';
                    }, 100);
                },
                willClose: () => {
                    clearInterval(timerInterval);
                }
            }).then(() => {
                window.location.href = '?hapus_akun=1';
            });
        }
    });
}

// ============================================================================
// URL PARAMETER NOTIFICATION (TOAST STYLE)
// ============================================================================
const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status');
const msg = urlParams.get('msg');

if (status && msg) {
    const isSuccess = status === 'success';
    Swal.fire({
        icon: isSuccess ? 'success' : 'error',
        title: isSuccess ? 'Berhasil!' : 'Gagal!',
        text: msg,
        timer: 5000,
        showConfirmButton: false,
        toast: true,
        position: 'top-end',
        timerProgressBar: true,
        showCloseButton: true,
        background: '#ffffff',
        color: '#1c1c1e',
        iconColor: isSuccess ? '#34C759' : '#FF3B30',
        customClass: { popup: 'swal-toast' }
    });
    window.history.replaceState({}, document.title, window.location.pathname);
}
</script>

</body>
</html>