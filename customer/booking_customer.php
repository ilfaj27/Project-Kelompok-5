<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) { session_start(); }
include '../includes/auth_helper.php';
include '../includes/config.php';

/* ─── HARD DELETE AKUN ─── */
if (isset($_GET['hapus_akun']) && $_GET['hapus_akun'] == '1') {
    $id_customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';
    if (!empty($id_customer)) {
        $modified_by = $_SESSION['nama'] ?? 'CUSTOMER';
        $stmt = sqlsrv_query($conn,
            "UPDATE Customer SET Is_Deleted=1,Status=0,Deleted_By=?,Deleted_Date=GETDATE() WHERE ID_Customer=? AND Is_Deleted=0",
            array($modified_by, $id_customer));
        if ($stmt) {
            $_SESSION = array();
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
            setcookie('remember_me', '', time()-3600, "/");
            ob_end_clean();
            header("Location: ../login/login.php?status=success&msg=Akun Anda telah dihapus permanen. Silakan daftar ulang untuk menggunakan layanan kami.");
            exit();
        } else {
            ob_end_clean();
            header("Location: booking_customer.php?status=error&msg=Gagal menghapus akun. Silakan coba lagi.");
            exit();
        }
    } else {
        ob_end_clean();
        header("Location: ../login/login.php?status=error&msg=Sesi tidak valid. Silakan login kembali.");
        exit();
    }
}

cek_akses('customer');

/* ─── DATA CUSTOMER ─── */
$id_customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';
$nama_customer = 'Pelanggan';
$photo_profile  = '';
if (!empty($id_customer)) {
    $st = sqlsrv_query($conn,"SELECT Nama_Customer,Photo_Profile,Is_Deleted,Status FROM Customer WHERE ID_Customer=?",array($id_customer));
    if ($st) {
        $row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
        if ($row) {
            if ($row['Is_Deleted']==1 || $row['Status']==0) {
                $_SESSION = array(); session_destroy(); setcookie('remember_me','',time()-3600,"/");
                ob_end_clean();
                header("Location: ../login/login.php?status=error&msg=Akun Anda telah dihapus atau dinonaktifkan. Silakan hubungi admin atau daftar ulang.");
                exit();
            }
            $nama_customer = $row['Nama_Customer'] ?? 'Pelanggan';
            $photo_profile  = $row['Photo_Profile'] ?? '';
        }
    }
}

/* ─── MEMBER STATUS ─── */
$member_data  = null;
$mc = sqlsrv_query($conn,
    "SELECT TOP 1 L.*,T.Nama_Tipe,T.Potongan_Harga,T.Harga_Member FROM Langganan L
     INNER JOIN Tipe_Member T ON L.ID_Tipe=T.ID_Tipe
     WHERE L.ID_Customer=? AND L.Status=1 AND GETDATE() BETWEEN L.Tanggal_Mulai AND L.Tanggal_Selesai
     ORDER BY L.Tanggal_Selesai DESC", array($id_customer));
if ($mc) $member_data = sqlsrv_fetch_array($mc, SQLSRV_FETCH_ASSOC);
$has_member    = !empty($member_data);
$member_tipe   = $has_member ? $member_data['Nama_Tipe'] : '';
$member_discount = $has_member ? floatval($member_data['Potongan_Harga']) : 0;

/* ─── HELPER: RESOLVE PHOTO PATH ─── */
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

/* ─── GENERATE JADWAL ─── */
function generateJadwalOtomatis($conn) {
    $q = sqlsrv_query($conn,"SELECT ID_Lapangan FROM Lapangan WHERE Status=1 AND Is_Deleted=0");
    if (!$q) return;
    $list=[];
    while ($r=sqlsrv_fetch_array($q,SQLSRV_FETCH_ASSOC)) $list[]=$r['ID_Lapangan'];
    $slots=[['07:00:00','08:00:00'],['08:00:00','09:00:00'],['09:00:00','10:00:00'],
            ['10:00:00','11:00:00'],['11:00:00','12:00:00'],['12:00:00','13:00:00'],
            ['13:00:00','14:00:00'],['14:00:00','15:00:00'],['15:00:00','16:00:00'],
            ['16:00:00','17:00:00'],['17:00:00','18:00:00'],['18:00:00','19:00:00'],
            ['19:00:00','20:00:00'],['20:00:00','21:00:00'],['21:00:00','22:00:00'],['22:00:00','23:00:00']];
    for ($i=0;$i<7;$i++) {
        $d=date('Y-m-d',strtotime("+$i days"));
        foreach ($list as $id_lap) {
            foreach ($slots as $s) {
                $cek=sqlsrv_query($conn,"SELECT ID_Jadwal FROM Jadwal WHERE ID_Lapangan=? AND Tanggal=? AND Jam_Mulai=? AND Jam_Selesai=?",
                    array($id_lap,$d,$s[0],$s[1]));
                if ($cek && !sqlsrv_fetch_array($cek,SQLSRV_FETCH_ASSOC))
                    sqlsrv_query($conn,"INSERT INTO Jadwal(ID_Lapangan,Tanggal,Jam_Mulai,Jam_Selesai,Status,Is_Deleted,Created_By,Created_Date) VALUES(?,?,?,?,1,0,'SYSTEM_AUTO',GETDATE())",
                        array($id_lap,$d,$s[0],$s[1]));
            }
        }
    }
}
generateJadwalOtomatis($conn);

/* ─── AJAX ─── */
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if ($_GET['action']=='get_slots' && isset($_GET['court_id'])) {
        $cid=intval($_GET['court_id']);
        $st=sqlsrv_query($conn,
            "SELECT ID_Jadwal,Tanggal,Jam_Mulai,Jam_Selesai FROM Jadwal
             WHERE ID_Lapangan=? AND Status=1 AND Is_Deleted=0
             AND ID_Jadwal NOT IN (SELECT ID_Jadwal FROM Booking)
             AND (Tanggal>CAST(GETDATE() AS DATE) OR (Tanggal=CAST(GETDATE() AS DATE) AND Jam_Mulai>CAST(GETDATE() AS TIME)))
             ORDER BY Tanggal ASC,Jam_Mulai ASC", array($cid));
        $slots=[];
        if ($st) {
            while ($r=sqlsrv_fetch_array($st,SQLSRV_FETCH_ASSOC)) {
                $tgl=($r['Tanggal'] instanceof DateTime)?$r['Tanggal']->format('Y-m-d'):$r['Tanggal'];
                $mulai=($r['Jam_Mulai'] instanceof DateTime)?$r['Jam_Mulai']->format('H:i'):substr($r['Jam_Mulai'],0,5);
                $selesai=($r['Jam_Selesai'] instanceof DateTime)?$r['Jam_Selesai']->format('H:i'):substr($r['Jam_Selesai'],0,5);
                $slots[]=['ID_Jadwal'=>$r['ID_Jadwal'],'Tanggal'=>$tgl,'Jam_Mulai'=>$mulai,'Jam_Selesai'=>$selesai,'Tanggal_Formatted'=>date('d M Y',strtotime($tgl))];
            }
        }
        echo json_encode($slots); exit();
    }
    if ($_GET['action']=='checkout' && $_SERVER['REQUEST_METHOD']=='POST') {
        $input=json_decode(file_get_contents('php://input'),true);
        if (!$input) $input=$_POST;
        $id_jadwal=intval($input['id_jadwal']??0);
        $id_promo=!empty($input['id_promo'])?intval($input['id_promo']):null;
        $metode=htmlspecialchars($input['metode_pembayaran']??'');
        $total=floatval($input['total_bayar']??0);
        if ($id_jadwal<=0 || empty($metode) || $total<=0) { echo json_encode(['success'=>false,'message'=>'Parameter input tidak valid.']); exit(); }
        if (sqlsrv_begin_transaction($conn)===false) { echo json_encode(['success'=>false,'message'=>'Gagal menginisiasi transaksi database.']); exit(); }
        try {
            $chk=sqlsrv_query($conn,"SELECT Status,ID_Lapangan FROM Jadwal WHERE ID_Jadwal=?",array($id_jadwal));
            $jadwal=null; if ($chk) $jadwal=sqlsrv_fetch_array($chk,SQLSRV_FETCH_ASSOC);
            if (!$jadwal || $jadwal['Status']!=1) throw new Exception("Maaf, slot jadwal ini sudah terbooking atau tidak tersedia.");
            $kq=sqlsrv_query($conn,"SELECT TOP 1 ID_Karyawan FROM Karyawan WHERE Status=1 AND Is_Deleted=0 ORDER BY ID_Karyawan ASC");
            $id_karyawan=1; if ($kq) { $kd=sqlsrv_fetch_array($kq,SQLSRV_FETCH_ASSOC); if ($kd) $id_karyawan=$kd['ID_Karyawan']; }
            $by=$_SESSION['nama']??'CUSTOMER';
            $ins=sqlsrv_query($conn,
                "INSERT INTO Booking(ID_Customer,ID_Karyawan,ID_Jadwal,ID_Promo,Tanggal_Booking,Metode_Pembayaran,Total_Bayar,Status,Created_By,Created_Date) VALUES(?,?,?,?,CAST(GETDATE() AS DATE),?,?,0,?,GETDATE())",
                array($id_customer,$id_karyawan,$id_jadwal,$id_promo,$metode,$total,$by));
            if ($ins===false) {
                $e=sqlsrv_errors();
                throw new Exception("Terjadi kendala koneksi database (Kode: ".($e[0]['code']??0)."). Silakan hubungi operator.");
            }
            $upd=sqlsrv_query($conn,"UPDATE Jadwal SET Status=0,Modified_By=?,Modified_Date=GETDATE() WHERE ID_Jadwal=?",array($by,$id_jadwal));
            if ($upd===false) throw new Exception("Gagal memperbarui status jadwal.");
            sqlsrv_commit($conn);
            echo json_encode(['success'=>true,'message'=>'Pemesanan berhasil dibuat!']);
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
        }
        exit();
    }
}

/* ─── DATA LAPANGAN ─── */
$lapanganList=[];
$ql=sqlsrv_query($conn,"SELECT ID_Lapangan,Nama_Lapangan,Harga_Sewa,Photo_Lapangan FROM Lapangan WHERE Status=1 AND Is_Deleted=0");
if ($ql) while ($r=sqlsrv_fetch_array($ql,SQLSRV_FETCH_ASSOC)) $lapanganList[]=$r;

$lapanganFasilitas=[];
$qf=sqlsrv_query($conn,"SELECT ID_Lapangan,Nama_Fasilitas FROM Fasilitas_Lapangan WHERE Status=1 AND Is_Deleted=0");
if ($qf) while ($r=sqlsrv_fetch_array($qf,SQLSRV_FETCH_ASSOC)) $lapanganFasilitas[$r['ID_Lapangan']][]=$r['Nama_Fasilitas'];

$promos=[];
if (!$has_member) {
    $qp=sqlsrv_query($conn,"SELECT ID_Promo,Nama_Promo,Diskon FROM Promo WHERE Status=1 AND Is_Deleted=0 AND CAST(GETDATE() AS DATE) BETWEEN Tanggal_Mulai AND Tanggal_Selesai");
    if ($qp) while ($r=sqlsrv_fetch_array($qp,SQLSRV_FETCH_ASSOC)) $promos[]=$r;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Booking Lapangan | HoopBall Arena</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Barlow+Condensed:wght@700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* ═══ SHARED ROOT VARIABLES ═══ */
:root {
    --primary: #FF5200; --primary-hover: #E04800; --primary-light: rgba(255,82,0,0.1);
    --dark-bg: #0B0B0C; --card-dark: #121214; --text-gray: #8E8E93;
    --white: #FFFFFF; --light-bg: #F8F9FA;
    --green: #34C759; --green-lt: rgba(52,199,89,.10);
    --yellow: #FFCC00; --yellow-lt: rgba(255,204,0,.10);
    --red: #FF3B30; --red-lt: rgba(255,59,48,.10);
    --blue: #007AFF; --blue-lt: rgba(0,122,255,.10);
    --orange: #FF5A1F; --orange-hover: #E0440E;
    --orange-lt: rgba(255,90,31,0.06); --orange-glow: rgba(255,90,31,0.15);
    --border: #E2E8F0; --border-lt: #F1F5F9;
    --text-primary: #0F172A; --text-secondary: #475569; --muted: #94A3B8;
    --bg: #F8FAFC; --transition-smooth: all 0.3s cubic-bezier(0.4,0,0.2,1);
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Plus Jakarta Sans',sans-serif;background:var(--bg);color:var(--text-primary);-webkit-font-smoothing:antialiased;overflow-x:hidden}
::-webkit-scrollbar{display:none} html,body{-ms-overflow-style:none;scrollbar-width:none}
::selection{background:rgba(255,82,0,0.3);color:#1C1C1E}

/* ═══ ANIMATIONS ═══ */
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
@keyframes drawLine{from{width:0}to{width:60px}}

/* ═══ REVEAL ═══ */
.reveal{opacity:0;transform:translateY(40px);transition:all 0.8s cubic-bezier(0.16,1,0.3,1)}
.reveal.active{opacity:1;transform:translateY(0)}
.reveal-stagger .stagger-item{opacity:0;transform:translateY(30px);transition:all 0.6s cubic-bezier(0.16,1,0.3,1)}
.reveal-stagger.active .stagger-item{opacity:1;transform:translateY(0)}
.reveal-stagger.active .stagger-item:nth-child(1){transition-delay:0s}
.reveal-stagger.active .stagger-item:nth-child(2){transition-delay:.1s}
.reveal-stagger.active .stagger-item:nth-child(3){transition-delay:.2s}
.reveal-stagger.active .stagger-item:nth-child(4){transition-delay:.3s}
.reveal-stagger.active .stagger-item:nth-child(5){transition-delay:.4s}

/* ═══ SCROLL PROGRESS ═══ */
.scroll-progress{position:fixed;top:0;left:0;height:3px;background:linear-gradient(90deg,var(--primary),#FF8C42);z-index:9999;transform-origin:left;transform:scaleX(0);transition:transform 0.1s ease-out}

/* ═══ NAVBAR ═══ */
nav{background:var(--white);padding:0 80px;display:flex;justify-content:space-between;align-items:center;height:76px;position:sticky;top:0;z-index:1000;border-bottom:1px solid #E5E5EA;animation:fadeInDown .6s ease-out forwards}
.nav-logo{display:flex;align-items:center;text-decoration:none;gap:10px;transition:transform .3s ease}
.nav-logo:hover{transform:scale(1.05)}
.nav-logo img{height:70px;width:auto;transition:transform .5s cubic-bezier(.34,1.56,.64,1)}
.nav-logo:hover img{transform:rotate(5deg) scale(1.1)}
.nav-links{display:flex;align-items:center;gap:8px}
.nav-links a{color:#636366;text-decoration:none;font-size:14px;font-weight:500;padding:8px 16px;border-radius:20px;transition:var(--transition-smooth);position:relative;overflow:hidden}
.nav-links a::before{content:'';position:absolute;bottom:0;left:50%;width:0;height:2px;background:var(--primary);transition:var(--transition-smooth);transform:translateX(-50%)}
.nav-links a:hover{color:#1C1C1E;transform:translateY(-2px)}
.nav-links a:hover::before{width:60%}
.nav-links a.active{color:var(--primary);font-weight:600}
.nav-links a.active::before{width:60%}
.nav-user-container{position:relative;height:76px;display:flex;align-items:center}
.nav-user{background:#F2F2F7;border:1px solid #E5E5EA;padding:8px 16px;border-radius:50px;color:#1C1C1E;font-size:14px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:10px;transition:var(--transition-smooth)}
.nav-user:hover{background:#E5E5EA;border-color:var(--primary);transform:scale(1.02);box-shadow:0 4px 12px rgba(255,82,0,.15)}
.nav-user img.user-avatar{width:24px;height:24px;border-radius:50%;object-fit:cover;transition:transform .3s ease}
.nav-user:hover img.user-avatar{transform:scale(1.15)}
.nav-user i.user-icon{font-size:16px;color:var(--primary);transition:transform .3s ease}
.nav-user:hover i.user-icon{transform:scale(1.2)}
.nav-user i.arrow{font-size:11px;color:#8E8E93;transition:.3s cubic-bezier(.34,1.56,.64,1)}
.nav-user-container:hover i.arrow{transform:rotate(180deg);color:var(--primary)}
.dropdown-menu{position:absolute;top:85%;right:0;background:#16161a;min-width:220px;border-radius:12px;border:1px solid #2d2d33;box-shadow:0 10px 30px rgba(0,0,0,.5);padding:8px 0;display:none;z-index:1001;transform-origin:top right}
.nav-user-container:hover .dropdown-menu{display:block;animation:fadeInUp .3s cubic-bezier(.16,1,.3,1) forwards}
.dropdown-menu .user-info-header{padding:12px 20px;border-bottom:1px solid #2d2d33;margin-bottom:6px}
.dropdown-menu .user-info-header .u-name{color:var(--white);font-size:14px;font-weight:700;display:block}
.dropdown-menu .user-info-header .u-role{color:var(--text-gray);font-size:11px;text-transform:uppercase;letter-spacing:.5px;margin-top:2px;display:block}
.dropdown-menu a{display:flex;align-items:center;gap:12px;padding:10px 20px;color:#c5c5ca;text-decoration:none;font-size:13px;font-weight:500;transition:all .25s cubic-bezier(.16,1,.3,1);position:relative;overflow:hidden}
.dropdown-menu a::after{content:'';position:absolute;left:0;top:0;width:3px;height:100%;background:var(--primary);transform:scaleY(0);transition:transform .25s cubic-bezier(.16,1,.3,1)}
.dropdown-menu a i{font-size:14px;width:16px;text-align:center;transition:transform .3s ease}
.dropdown-menu a:hover{background:#222227;color:var(--primary);padding-left:28px}
.dropdown-menu a:hover::after{transform:scaleY(1)}
.dropdown-menu a:hover i{transform:scale(1.2)}
.dropdown-divider{height:1px;background:#2d2d33;margin:6px 0}
.dropdown-menu a.logout:hover{color:#ff3b30}
.dropdown-menu a.logout:hover::after{background:#ff3b30}
.member-badge-nav{display:inline-flex;align-items:center;gap:6px;background:var(--green-lt);border:1px solid var(--green);color:var(--green);padding:4px 12px;border-radius:50px;font-size:11px;font-weight:700;margin-left:8px;animation:pulse 2s ease-in-out infinite}

/* ═══ PAGE CONTAINER ═══ */
.container{width:100%;max-width:95%;margin:40px auto;padding:0 20px;display:flex;flex-direction:column;gap:24px}
.section-header{margin-bottom:20px}
.section-title{font-size:16px;font-weight:800;color:var(--text-primary);display:flex;align-items:center;gap:8px}
.section-subtitle{font-size:12px;color:var(--muted);margin-top:4px;font-weight:500}

/* ═══ COURT GRID ═══ */
.court-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:24px;margin-bottom:30px}
.court-card{border:1px solid var(--border);border-radius:14px;overflow:hidden;background:#fff;cursor:pointer;position:relative;transition:var(--transition-smooth)}
.court-card:hover{border-color:var(--orange);box-shadow:0 4px 12px var(--orange-glow);transform:translateY(-5px)}
.court-card.selected{border-color:var(--orange);box-shadow:0 0 0 2px var(--orange)}
.court-img-wrapper{position:relative;height:200px;background:#cbd5e1;overflow:hidden}
.court-img{width:100%;height:100%;object-fit:cover;transition:transform .6s cubic-bezier(.16,1,.3,1)}
.court-card:hover .court-img{transform:scale(1.08)}
.badge-available{position:absolute;bottom:12px;left:12px;background:var(--green-lt);color:#34C759;font-size:10px;font-weight:700;padding:4px 10px;border-radius:20px;border:1px solid rgba(52,199,89,.2)}
.court-info{padding:16px}
.court-name{font-size:15px;font-weight:700;color:var(--text-primary)}
.court-price{font-size:14px;font-weight:700;color:var(--orange);margin:6px 0 12px}
.court-perk-list{list-style:none;display:flex;flex-direction:column;gap:6px}
.court-perk-item{display:flex;align-items:center;gap:8px;font-size:11.5px;color:var(--text-secondary)}
.court-perk-item i{color:var(--muted);width:14px;text-align:center}

/* ═══ SHARED MODAL OVERLAY ═══ */
.booking-modal-overlay{position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(15,23,42,.6);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;z-index:2000;padding:20px;animation:fadeInModal .25s ease-out forwards}
.summary-card{background:#fff;border-radius:20px;padding:30px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;position:relative;box-shadow:0 20px 40px rgba(0,0,0,.15);animation:slideInModal .3s cubic-bezier(.16,1,.3,1) forwards;-ms-overflow-style:none;scrollbar-width:none}
.summary-card::-webkit-scrollbar{display:none}
.booking-modal-close{position:absolute;top:20px;right:20px;background:var(--border-lt);border:none;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text-secondary);transition:var(--transition-smooth);z-index:10}
.booking-modal-close:hover{background:var(--red-lt);color:var(--red);transform:rotate(90deg)}

/* ═══ SCHEDULE MODAL INTERNALS ═══ */
.schedule-controls{display:flex;flex-direction:column;gap:16px;align-items:stretch;margin-bottom:16px}
.input-group{flex:1;min-width:250px;display:flex;flex-direction:column;gap:6px}
.input-label{font-size:11.5px;font-weight:700;color:var(--text-primary)}
.input-wrapper{position:relative;display:flex;align-items:center}
.input-wrapper i{position:absolute;left:14px;color:var(--muted);font-size:14px;pointer-events:none}
.form-control{width:100%;padding:11px 40px 11px 40px;border:1px solid var(--border);border-radius:10px;font-family:inherit;font-size:13px;color:var(--text-primary);background-color:#fff;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2394A3B8' stroke-width='2.5'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19.5 8.25l-7.5 7.5-7.5-7.5'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;background-size:14px;outline:none;appearance:none;transition:var(--transition-smooth)}
.form-control:focus{border-color:var(--orange);box-shadow:0 0 0 3px var(--orange-glow)}
.status-availability-box{background:var(--green-lt);border:1px solid rgba(52,199,89,.15);border-radius:12px;padding:10px 20px;display:flex;align-items:center;gap:12px;min-height:48px;transition:var(--transition-smooth)}
.status-availability-box.empty{background:var(--red-lt);border-color:rgba(255,59,48,.15)}
.status-avail-icon{color:var(--green);font-size:18px;transition:transform .3s ease}
.status-availability-box.empty .status-avail-icon{color:var(--red)}
.status-avail-title{font-size:12px;font-weight:700;color:#065F46}
.status-availability-box.empty .status-avail-title{color:#991B1B}
.status-avail-desc{font-size:10px;color:#047857;margin-top:2px}
.status-availability-box.empty .status-avail-desc{color:#B91C1C}
.alert-banner{background:#EFF6FF;border:1px solid rgba(0,122,255,.15);border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:12px;margin-top:16px;animation:fadeInUp .5s ease-out}
.alert-banner i{color:var(--blue);font-size:16px;animation:float 2s ease-in-out infinite}
.alert-banner-text{font-size:11.5px;color:#1E40AF;line-height:1.5}
.btn-trigger-modal{display:flex;width:100%;margin:24px 0 0;background:var(--orange);color:#fff;border:none;border-radius:10px;padding:12px 20px;font-family:inherit;font-size:13.5px;font-weight:700;cursor:pointer;align-items:center;justify-content:center;gap:8px;transition:var(--transition-smooth);position:relative;overflow:hidden}
.btn-trigger-modal::before{content:'';position:absolute;top:50%;left:50%;width:0;height:0;background:rgba(255,255,255,.2);border-radius:50%;transform:translate(-50%,-50%);transition:width .6s,height .6s}
.btn-trigger-modal:hover::before{width:300px;height:300px}
.btn-trigger-modal:hover:not(:disabled){background:var(--orange-hover);transform:translateY(-2px);box-shadow:0 8px 25px rgba(255,90,31,.4)}
.btn-trigger-modal:disabled{background:var(--muted);cursor:not-allowed}

/* ═══ BOOKING SUMMARY MODAL ═══ */
.summary-title{font-family:'Barlow Condensed',sans-serif;font-size:16px;font-weight:800;letter-spacing:.5px;color:var(--muted);margin-bottom:20px;text-transform:uppercase}
.summary-item-info{display:flex;gap:14px;margin-bottom:20px}
.summary-thumb{width:70px;height:70px;border-radius:10px;overflow:hidden;background:#e2e8f0;flex-shrink:0}
.summary-thumb img{width:100%;height:100%;object-fit:cover;transition:transform .3s ease}
.summary-thumb:hover img{transform:scale(1.1)}
.summary-details{display:flex;flex-direction:column;justify-content:center}
.summary-court-name{font-size:15px;font-weight:700;color:var(--text-primary)}
.summary-venue{font-size:11px;color:var(--muted);margin-bottom:6px;font-weight:500}
.summary-meta{font-size:11.5px;color:var(--text-secondary);display:flex;align-items:center;gap:6px;margin-top:2px;font-weight:500}
.summary-meta i{font-size:11px;color:var(--muted);width:14px}
.member-block{border-top:1px solid var(--border-lt);padding:16px 0}
.member-status-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:6px}
.member-status-label{font-size:12.5px;font-weight:700;color:var(--text-primary)}
.badge-member-active{background:var(--green-lt);color:var(--green);font-size:10px;font-weight:800;padding:4px 10px;border-radius:20px;display:inline-flex;align-items:center;gap:4px;animation:pulse 2s ease-in-out infinite}
.badge-member-inactive{background:#FFF3CD;color:#D97706;font-size:10px;font-weight:800;padding:4px 10px;border-radius:20px;display:inline-flex;align-items:center;gap:4px}
.member-text-congratulations{font-size:11px;color:var(--muted);margin-bottom:12px}
.discount-row{display:flex;justify-content:space-between;font-size:12.5px}
.discount-label{color:var(--text-secondary);font-weight:500}
.discount-val{color:var(--green);font-weight:700}
.promo-warning-box{background:#FFF3CD;border:1px solid rgba(245,158,11,.2);border-radius:10px;padding:10px 14px;display:flex;align-items:center;gap:10px;margin-bottom:16px;animation:fadeInUp .4s ease-out}
.promo-warning-box i{color:#D97706;font-size:14px}
.promo-warning-text{font-size:11px;color:#B45309;line-height:1.4;font-weight:500}
.promo-input-group{display:flex;flex-direction:column;gap:6px;margin-bottom:20px}
.promo-input-wrapper{position:relative;display:flex;align-items:center}
.promo-input-wrapper i.prefix-icon{position:absolute;left:14px;color:var(--muted);font-size:13px}
.promo-input-wrapper i.lock-icon{position:absolute;right:14px;color:var(--muted);font-size:13px}
.promo-input{width:100%;padding:10px 36px;background:#fff;border:1px solid var(--border);border-radius:8px;font-size:12.5px;color:var(--text-primary);outline:none;font-weight:500;transition:var(--transition-smooth)}
.promo-input:focus{border-color:var(--orange);box-shadow:0 0 0 3px var(--orange-glow)}
.promo-input:disabled{background:#F8FAFC;color:var(--muted);cursor:not-allowed}
.pricing-breakdown{border-top:1px solid var(--border-lt);padding:16px 0;display:flex;flex-direction:column;gap:10px}
.price-row{display:flex;justify-content:space-between;font-size:12.5px;color:var(--text-secondary);font-weight:500;transition:var(--transition-smooth)}
.price-row:hover{transform:translateX(5px)}
.price-row.total-row{margin-top:6px;font-size:14px;color:var(--text-primary);font-weight:800;align-items:center}
.price-row.total-row .total-amount{font-size:20px;color:var(--orange);font-weight:900;animation:countUp .5s ease-out}

/* ═══ PAYMENT CARDS ═══ */
.payment-section{border-top:1px solid var(--border-lt);padding:20px 0 10px}
.payment-header{font-size:12.5px;font-weight:700;color:var(--text-primary);margin-bottom:12px;display:flex;align-items:center;gap:6px}
.payment-header i{color:var(--muted)}
.payment-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.payment-card{border:1px solid var(--border);border-radius:10px;padding:12px;cursor:pointer;display:flex;align-items:center;gap:10px;transition:var(--transition-smooth);user-select:none;position:relative;overflow:hidden}
.payment-card::before{content:'';position:absolute;top:50%;left:50%;width:0;height:0;background:rgba(255,90,31,.1);border-radius:50%;transform:translate(-50%,-50%);transition:width .4s,height .4s}
.payment-card:hover::before{width:200px;height:200px}
.payment-card:hover{border-color:var(--orange);transform:translateY(-2px);box-shadow:0 4px 12px rgba(255,90,31,.1)}
.payment-card.selected{border-color:var(--orange);background:var(--orange-lt)}
.custom-radio{width:16px;height:16px;border-radius:50%;border:1.5px solid var(--muted);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:.2s}
.payment-card.selected .custom-radio{border-color:var(--orange)}
.custom-radio::after{content:'';width:8px;height:8px;border-radius:50%;background:var(--orange);display:none}
.payment-card.selected .custom-radio::after{display:block;animation:scaleIn .2s ease-out}
.payment-card-content{display:flex;flex-direction:column;justify-content:center}
.payment-name{font-size:11px;font-weight:700;color:var(--text-primary);line-height:1.3}
.payment-sub{font-size:9px;color:var(--muted);margin-top:1px;font-weight:500}
.qris-logo{font-family:'Barlow Condensed',sans-serif;font-weight:900;font-size:14px;color:#000;letter-spacing:-.5px}
.btn-booking{width:100%;background:var(--orange);color:#fff;border:none;border-radius:12px;padding:14px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;margin-top:16px;transition:var(--transition-smooth);position:relative;overflow:hidden}
.btn-booking::before{content:'';position:absolute;top:50%;left:50%;width:0;height:0;background:rgba(255,255,255,.2);border-radius:50%;transform:translate(-50%,-50%);transition:width .6s,height .6s}
.btn-booking:hover::before{width:400px;height:400px}
.btn-booking:hover:not(:disabled){background:var(--orange-hover);transform:translateY(-2px);box-shadow:0 10px 30px rgba(255,90,31,.4)}
.btn-booking:disabled{background:var(--muted);cursor:not-allowed}
.booking-disclaimer{display:flex;align-items:center;justify-content:center;gap:6px;font-size:11px;color:var(--muted);margin-top:10px;font-weight:500}
.booking-disclaimer i{color:var(--green);animation:pulse 2s ease-in-out infinite}

/* ═══ PAYMENT INSTRUCTION MODAL ═══ */
.instruction-title{font-family:'Barlow Condensed',sans-serif;font-size:16px;font-weight:800;letter-spacing:.5px;color:var(--muted);margin-bottom:20px;text-transform:uppercase;text-align:center}
.switch-tabs-row{display:flex;gap:6px;margin-bottom:16px;background:var(--border-lt);padding:4px;border-radius:10px;border:1px solid var(--border)}
.switch-tab-btn{flex:1;padding:10px;border:none;border-radius:8px;font-family:inherit;font-size:12px;font-weight:700;cursor:pointer;transition:var(--transition-smooth);background:transparent;color:var(--text-secondary)}
.switch-tab-btn.active{background:#fff;color:var(--orange);box-shadow:0 2px 6px rgba(0,0,0,.05)}
.countdown-box{background:var(--orange-lt);border:1px solid rgba(255,90,31,.15);border-radius:10px;padding:12px 16px;display:flex;align-items:center;justify-content:center;gap:12px;margin-bottom:20px}
.countdown-box i{color:var(--orange);animation:pulse 2s ease-in-out infinite}
.countdown-text{color:var(--orange-hover);font-weight:700;font-size:12px}
.total-display-box{background:var(--bg);padding:14px 18px;border-radius:12px;margin-bottom:20px;border:1px solid var(--border);text-align:center}
.total-display-label{font-size:11px;color:var(--text-secondary);font-weight:600;text-transform:uppercase}
.total-display-amount{font-size:24px;color:var(--orange);font-weight:900;margin-top:4px}
.instr-va-box{text-align:left}
.instr-qris-box{display:none;align-items:center;flex-direction:column}
.instr-qris-box.active{display:flex}
.bank-info-card{background:linear-gradient(135deg,#f8fafc 0%,#f1f5f9 100%);border:1px solid var(--border);border-radius:14px;padding:20px;margin-bottom:20px;text-align:left;position:relative;overflow:hidden}
.bank-info-card::before{content:'';position:absolute;top:-20px;right:-20px;width:80px;height:80px;background:rgba(255,82,0,.05);border-radius:50%}
.bank-header{display:flex;align-items:center;gap:12px;margin-bottom:16px}
.bank-icon{width:44px;height:44px;background:var(--primary);border-radius:12px;display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;box-shadow:0 4px 12px rgba(255,82,0,.3)}
.bank-title{font-size:13px;font-weight:800;color:var(--text-primary)}
.bank-sub{font-size:11px;color:var(--muted);font-weight:500}
.va-section-label{font-size:11.5px;font-weight:700;color:var(--text-secondary);margin-bottom:10px;text-transform:uppercase;letter-spacing:.5px}
.va-input-row{display:flex;gap:8px}
.va-input-wrap{flex:1;background:#fff;border:2px solid var(--border);border-radius:12px;padding:14px 16px;display:flex;align-items:center;gap:10px;transition:var(--transition-smooth)}
.va-input-wrap:hover{border-color:var(--orange);box-shadow:0 4px 16px rgba(255,82,0,.1)}
.va-input-wrap i{color:var(--orange);font-size:14px}
.va-input-wrap input{border:none;background:transparent;font-weight:800;text-align:center;font-size:18px;letter-spacing:2px;color:var(--text-primary);font-family:'Plus Jakarta Sans',monospace;width:100%;outline:none}
.btn-copy-va{border-radius:12px;font-size:13px;padding:14px 18px;display:flex;align-items:center;gap:6px;white-space:nowrap;background:var(--primary);color:#fff;border:none;font-weight:700;box-shadow:0 4px 12px rgba(255,82,0,.3);cursor:pointer;transition:var(--transition-smooth);font-family:inherit}
.btn-copy-va:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(255,82,0,.4)}
.steps-label{font-size:11.5px;font-weight:700;color:var(--text-secondary);margin-bottom:14px;text-transform:uppercase;letter-spacing:.5px;margin-top:20px}
.step-item{display:flex;gap:14px;align-items:flex-start;padding:14px 16px;background:#fafafa;border-radius:12px;border:1px solid var(--border-lt);transition:var(--transition-smooth);margin-bottom:12px}
.step-item:hover{background:#fff;border-color:var(--orange);transform:translateX(4px)}
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

/* ═══ SHARED TOAST ═══ */
.swal-toast{border-radius:12px !important;font-family:'Plus Jakarta Sans',sans-serif !important}

/* ═══ FOOTER ═══ */
footer{background:var(--dark-bg);color:#8E8E93;padding:80px 80px 40px;border-top:1px solid #1C1C1E;position:relative;overflow:hidden}
footer::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent,var(--primary),transparent);animation:shimmer 3s linear infinite;background-size:200% 100%}
.footer-grid{display:grid;grid-template-columns:1.5fr 1fr 1fr 1.2fr;gap:40px;margin-bottom:60px}
.footer-logo{display:flex;align-items:center;gap:10px;margin-bottom:16px;transition:transform .3s ease}
.footer-logo:hover{transform:scale(1.05)}
.footer-logo img{height:70px;transition:transform .5s ease}
.footer-logo:hover img{transform:rotate(5deg)}
.footer-logo span{color:var(--white);font-size:20px;font-weight:800}
.footer-desc{font-size:13px;line-height:1.6;margin-bottom:24px}
.social-links{display:flex;gap:12px}
.social-btn{width:36px;height:36px;border-radius:50%;background:#1C1C1E;color:var(--white);display:flex;align-items:center;justify-content:center;text-decoration:none;transition:all .3s cubic-bezier(.34,1.56,.64,1)}
.social-btn:hover{background:var(--primary);transform:translateY(-3px) scale(1.1);box-shadow:0 8px 20px rgba(255,82,0,.3)}
.footer-col h4{color:var(--white);font-size:15px;font-weight:700;margin-bottom:20px;position:relative;display:inline-block}
.footer-col h4::after{content:'';position:absolute;bottom:-4px;left:0;width:30px;height:2px;background:var(--primary);transition:width .3s ease}
.footer-col:hover h4::after{width:100%}
.footer-col ul{list-style:none}
.footer-col ul li{margin-bottom:12px}
.footer-col ul li a{color:#8E8E93;text-decoration:none;font-size:13px;transition:all .3s ease;display:inline-block;position:relative}
.footer-col ul li a:hover{color:var(--white);transform:translateX(5px)}
.contact-item{display:flex;gap:12px;font-size:13px;line-height:1.5;margin-bottom:16px;transition:var(--transition-smooth);padding:4px;border-radius:6px}
.contact-item:hover{background:rgba(255,82,0,.05);transform:translateX(5px)}
.contact-item i{color:var(--primary);font-size:14px;margin-top:3px;transition:transform .3s ease}
.contact-item:hover i{transform:scale(1.2)}
.footer-bottom{border-top:1px solid #1C1C1E;padding-top:30px;text-align:center;font-size:13px}

@media(max-width:1200px){nav{padding:0 40px}.footer-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:768px){nav{flex-direction:column;height:auto;padding:15px 20px;gap:15px}.nav-links{flex-wrap:wrap;justify-content:center;gap:4px}.footer-grid{grid-template-columns:1fr}.court-grid{grid-template-columns:1fr}}
</style>
</head>
<body>

<div class="scroll-progress" id="scrollProgress"></div>

<!-- NAVBAR -->
<nav>
    <a href="view_customer.php" class="nav-logo">
        <img src="../asset/image/logo2.png" alt="HoopBall">
    </a>
    <div class="nav-links">
        <a href="view_customer.php">Beranda</a>
        <a href="booking_customer.php" class="active">Booking</a>
        <a href="pembatalan_customer.php">Pembatalan</a>
        <a href="langganan_customer.php">Member</a>
        <a href="pembelian_alat.php">Pembelian</a>
        <a href="#">Tentang</a>
        <a href="#">Kontak</a>
    </div>
    <div class="nav-user-container">
        <div class="nav-user">
            <?php if (!empty($photo_profile) && file_exists($photo_profile)): ?>
                <img src="<?= htmlspecialchars($photo_profile) ?>" alt="Avatar" class="user-avatar">
            <?php else: ?>
                <i class="fa-solid fa-circle-user user-icon"></i>
            <?php endif; ?>
            <span><?= htmlspecialchars($nama_customer) ?></span>
            <?php if ($has_member): ?>
                <span class="member-badge-nav"><i class="fa-solid fa-crown"></i> <?= htmlspecialchars($member_tipe) ?></span>
            <?php endif; ?>
            <i class="fa-solid fa-chevron-down arrow"></i>
        </div>
        <div class="dropdown-menu">
            <div class="user-info-header">
                <span class="u-name"><?= htmlspecialchars($nama_customer) ?></span>
                <span class="u-role">Customer <?= $has_member ? '• Member '.htmlspecialchars($member_tipe) : '' ?></span>
            </div>
            <a href="../profile/profile_customer.php"><i class="fa-solid fa-user"></i> Profil Saya</a>
            <a href="booking_customer.php"><i class="fa-solid fa-calendar-check"></i> Riwayat Booking</a>
            <a href="langganan_customer.php"><i class="fa-solid fa-crown"></i> Langganan Member</a>
            <a href="pembelian_alat.php"><i class="fa-solid fa-cart-shopping"></i> Pembelian Alat</a>
            <div class="dropdown-divider"></div>
            <a href="#" onclick="confirmHapusAkun(event)" style="color:#ff3b30"><i class="fa-solid fa-trash-can"></i> Hapus Akun</a>
            <a href="../login/logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
        </div>
    </div>
</nav>

<div class="container">
    <div class="section-header reveal">
        <h2 class="section-title"><i class="fa-solid fa-basketball" style="color:var(--primary)"></i> Pilih Lapangan</h2>
        <p class="section-subtitle">Klik lapangan untuk memilih, klik lagi untuk membuka pilihan jadwal.</p>
    </div>

    <div class="court-grid reveal-stagger">
        <?php if (!empty($lapanganList)): ?>
            <?php foreach ($lapanganList as $idx => $lap):
                $cId   = $lap['ID_Lapangan'];
                $cName = htmlspecialchars($lap['Nama_Lapangan']);
                $cPrice= floatval($lap['Harga_Sewa']);
                $sel   = $idx===0 ? 'selected' : '';
                $rawPhoto = $lap['Photo_Lapangan'] ?? '';
                $resolvedPhoto = resolvePhotoPath($rawPhoto);
                $img = !empty($resolvedPhoto) ? htmlspecialchars($resolvedPhoto) : 'https://images.unsplash.com/photo-1544698310-74ea9d1c8258?q=80&w=600&auto=format&fit=crop';
            ?>
            <div class="court-card stagger-item <?= $sel ?>"
                 data-id="<?= $cId ?>" data-price="<?= $cPrice ?>"
                 data-name="<?= $cName ?>" data-img="<?= $img ?>">
                <div class="court-img-wrapper">
                    <img src="<?= $img ?>" alt="<?= $cName ?>" class="court-img" onerror="this.src='https://images.unsplash.com/photo-1544698310-74ea9d1c8258?q=80&w=600&auto=format&fit=crop'">
                    <span class="badge-available">Tersedia</span>
                </div>
                <div class="court-info">
                    <h3 class="court-name"><?= $cName ?></h3>
                    <p class="court-price">Rp <?= number_format($cPrice,0,',','.') ?> / jam</p>
                    <ul class="court-perk-list">
                        <?php if (isset($lapanganFasilitas[$cId])): ?>
                            <?php foreach ($lapanganFasilitas[$cId] as $f): ?>
                                <li class="court-perk-item"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($f) ?></li>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <li class="court-perk-item"><i class="fa-solid fa-basketball"></i> Bola Basket Standar</li>
                            <li class="court-perk-item"><i class="fa-solid fa-lightbulb"></i> Pencahayaan Terang</li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="grid-column:span 3;text-align:center;color:var(--muted);padding:40px">Tidak ada lapangan aktif saat ini.</p>
        <?php endif; ?>
    </div>
</div>

<!-- MODAL 1: PILIH JADWAL -->
<div class="booking-modal-overlay" id="scheduleModal">
    <div class="summary-card" style="max-width:600px">
        <button class="booking-modal-close" id="btnCloseSchedule"><i class="fa-solid fa-xmark"></i></button>
        <div class="section-header">
            <h2 class="section-title"><i class="fa-solid fa-calendar-days" style="color:var(--primary)"></i> Pilih Jadwal Bermain</h2>
            <p class="section-subtitle">Tentukan waktu bermain berdasarkan ketersediaan slot lapangan.</p>
        </div>
        <div class="schedule-controls">
            <div class="input-group">
                <label class="input-label">Pilih Tanggal Bermain</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-calendar-days"></i>
                    <select id="dateSelect" class="form-control"><option value="">Silakan pilih tanggal...</option></select>
                </div>
            </div>
            <div class="input-group">
                <label class="input-label">Pilih Jam Bermain</label>
                <div class="input-wrapper">
                    <i class="fa-solid fa-clock"></i>
                    <select id="timeSelect" class="form-control" disabled><option value="">Pilih tanggal terlebih dahulu...</option></select>
                </div>
            </div>
            <div class="status-availability-box" id="availabilityBox">
                <div class="status-avail-icon" id="availIcon"><i class="fa-solid fa-circle-check"></i></div>
                <div>
                    <div class="status-avail-title" id="availTitle">Memuat slot...</div>
                    <div class="status-avail-desc" id="lblDuration">Durasi: -</div>
                </div>
            </div>
        </div>
        <div class="alert-banner">
            <i class="fa-solid fa-circle-info"></i>
            <p class="alert-banner-text">Semua slot jadwal disesuaikan dengan daftar yang dibuat oleh operator HoopBall Arena.</p>
        </div>
        <button class="btn-trigger-modal" id="btnGoToSummary" disabled>
            Lanjut ke Tinjau &amp; Pembayaran <i class="fa-solid fa-arrow-right"></i>
        </button>
    </div>
</div>

<!-- MODAL 2: RINGKASAN BOOKING -->
<div class="booking-modal-overlay" id="bookingModal">
    <div class="summary-card">
        <button class="booking-modal-close" id="btnCloseSummary"><i class="fa-solid fa-xmark"></i></button>
        <h2 class="summary-title">Ringkasan Booking</h2>
        <div class="summary-item-info">
            <div class="summary-thumb"><img id="sumImg" src="" alt="Thumbnail"></div>
            <div class="summary-details">
                <div class="summary-court-name" id="sumCourtName">-</div>
                <div class="summary-venue">HoopBall Arena</div>
                <div class="summary-meta"><i class="fa-solid fa-calendar"></i><span id="sumPlayDate">-</span></div>
                <div class="summary-meta"><i class="fa-solid fa-clock"></i><span id="sumTimeLabel">-</span></div>
            </div>
        </div>
        <div class="member-block">
            <div class="member-status-header">
                <span class="member-status-label">Status Member</span>
                <?php if ($has_member): ?>
                    <span class="badge-member-active">Member <?= htmlspecialchars($member_tipe) ?> <i class="fa-solid fa-crown"></i></span>
                <?php else: ?>
                    <span class="badge-member-inactive">Bukan Member <i class="fa-solid fa-user"></i></span>
                <?php endif; ?>
            </div>
            <?php if ($has_member): ?>
                <p class="member-text-congratulations">Selamat! Anda berhak mendapatkan potongan harga member aktif.</p>
                <div class="discount-row">
                    <span class="discount-label">Diskon Member (<?= htmlspecialchars($member_tipe) ?>)</span>
                    <span class="discount-val" id="lblDiscountPercent">-Rp <?= number_format($member_discount,0,',','.') ?></span>
                </div>
            <?php else: ?>
                <p class="member-text-congratulations">Gunakan kode promo aktif jika tersedia.</p>
            <?php endif; ?>
        </div>

        <?php if ($has_member): ?>
            <div class="promo-warning-box">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <div class="promo-warning-text">Promo dinonaktifkan — potongan member otomatis diterapkan.</div>
            </div>
            <div class="promo-input-group">
                <label class="input-label">Promo</label>
                <div class="promo-input-wrapper">
                    <i class="fa-solid fa-ticket prefix-icon"></i>
                    <input type="text" class="promo-input" value="Tidak dapat digunakan" readonly disabled>
                    <i class="fa-solid fa-lock lock-icon"></i>
                </div>
            </div>
        <?php else: ?>
            <div class="promo-input-group">
                <label class="input-label">Gunakan Promo Aktif</label>
                <div class="promo-input-wrapper">
                    <i class="fa-solid fa-ticket prefix-icon"></i>
                    <select id="promoSelect" class="form-control" style="padding-left:36px;padding-right:14px">
                        <option value="0" data-discount="0">-- Pilih Promo Tersedia --</option>
                        <?php foreach ($promos as $p): ?>
                            <option value="<?= $p['ID_Promo'] ?>" data-discount="<?= floatval($p['Diskon']) ?>">
                                <?= htmlspecialchars($p['Nama_Promo']) ?> (-Rp <?= number_format($p['Diskon'],0,',','.') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        <?php endif; ?>

        <div class="pricing-breakdown">
            <div class="price-row"><span id="lblNormalPriceLabel">Harga Sewa</span><span id="lblNormalPrice">Rp 0</span></div>
            <?php if ($has_member): ?>
                <div class="price-row"><span>Potongan Member</span><span class="discount-val" id="lblDiscountBreakdown">-Rp <?= number_format($member_discount,0,',','.') ?></span></div>
            <?php else: ?>
                <div class="price-row"><span>Potongan Promo</span><span class="discount-val" id="lblPromoBreakdown">-Rp 0</span></div>
            <?php endif; ?>
            <div class="price-row total-row"><span>Total Pembayaran</span><span class="total-amount" id="lblTotalPrice">Rp 0</span></div>
        </div>

        <div class="payment-section">
            <div class="payment-header"><i class="fa-solid fa-wallet"></i> Metode Pembayaran</div>
            <div class="payment-grid">
                <div class="payment-card selected" data-method="Transfer Bank">
                    <div class="custom-radio"></div>
                    <div class="payment-card-content"><span class="payment-name">Transfer Bank</span><span class="payment-sub">Virtual Account</span></div>
                </div>
                <div class="payment-card" data-method="QRIS">
                    <div class="custom-radio"></div>
                    <div class="payment-card-content"><span class="payment-name qris-logo">QRIS</span><span class="payment-sub">Scan &amp; Bayar Instan</span></div>
                </div>
            </div>
        </div>
        <button class="btn-booking" id="btnSubmit" disabled><i class="fa-solid fa-lock"></i> Selesaikan Booking</button>
        <div class="booking-disclaimer"><i class="fa-solid fa-circle-check"></i> Enkripsi data aman terverifikasi</div>
    </div>
</div>

<!-- MODAL 3: INSTRUKSI PEMBAYARAN -->
<div class="booking-modal-overlay" id="paymentInstructionModal">
    <div class="summary-card" style="max-width:460px;text-align:center">
        <button class="booking-modal-close" onclick="tutupInstructionModal()"><i class="fa-solid fa-xmark"></i></button>
        <p class="instruction-title">Instruksi Pembayaran</p>
        <div class="switch-tabs-row">
            <button id="btnSwitchVA" class="switch-tab-btn active" onclick="showPaymentMethodInstructions('Transfer Bank')">
                <i class="fa-solid fa-university" style="margin-right:4px"></i> Virtual Account
            </button>
            <button id="btnSwitchQRIS" class="switch-tab-btn" onclick="showPaymentMethodInstructions('QRIS')">
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
                    <div><div class="bank-title">Virtual Account</div><div class="bank-sub">Mandiri / BCA / BNI / BRI</div></div>
                </div>
                <div class="va-section-label">Nomor Virtual Account</div>
                <div class="va-input-row">
                    <div class="va-input-wrap"><i class="fa-solid fa-hashtag"></i><input type="text" id="vaNumber" value="8801281234567890" readonly></div>
                    <button class="btn-copy-va" id="btnCopyVA"><i class="fa-regular fa-copy"></i> Salin</button>
                </div>
            </div>
            <div class="steps-label">Cara Pembayaran</div>
            <div class="step-item"><div class="step-num">1</div><div><div class="step-title">Buka Aplikasi Banking</div><div class="step-desc">Pilih menu <strong style="color:var(--primary)">Transfer &gt; Virtual Account</strong> pada M-Banking atau ATM Anda.</div></div></div>
            <div class="step-item"><div class="step-num">2</div><div><div class="step-title">Masukkan Nomor VA</div><div class="step-desc">Masukkan nomor Virtual Account <strong style="color:var(--primary)">8801281234567890</strong>.</div></div></div>
            <div class="step-item" style="margin-bottom:0"><div class="step-num">3</div><div><div class="step-title">Konfirmasi Pembayaran</div><div class="step-desc">Nominal akan otomatis muncul sesuai total tagihan. Konfirmasi dan selesaikan.</div></div></div>
        </div>

        <div id="instruksiQRIS" class="instr-qris-box">
            <div class="qris-title">Pindai Kode QRIS Resmi HoopBall</div>
            <div class="qris-img-wrap"><img id="qrisImage" src="" alt="QRIS Code" class="qris-img"></div>
            <ul class="qris-steps-list">
                <li>Buka aplikasi e-wallet (GoPay, OVO, Dana, LinkAja) atau Mobile Banking.</li>
                <li>Pilih opsi <strong>Scan / Bayar QRIS</strong>.</li>
                <li>Arahkan kamera ke kode QR, lalu selesaikan pembayaran.</li>
            </ul>
        </div>

        <hr class="modal-divider">
        <button class="btn-done-pay" id="btnDonePayment">Saya Sudah Bayar <i class="fa-solid fa-circle-check"></i></button>
    </div>
</div>

<!-- FOOTER -->
<footer>
    <div class="footer-grid">
        <div>
            <div class="footer-logo"><img src="../asset/image/logo.png" alt="HoopBall"></div>
            <p class="footer-desc">HoopBall adalah platform penyewaan lapangan basket online yang mudah, cepat, dan terpercaya.</p>
            <div class="social-links">
                <a href="#" class="social-btn"><i class="fa-brands fa-instagram"></i></a>
                <a href="#" class="social-btn"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" class="social-btn"><i class="fa-brands fa-tiktok"></i></a>
                <a href="#" class="social-btn"><i class="fa-brands fa-youtube"></i></a>
            </div>
        </div>
        <div class="footer-col"><h4>Navigasi</h4><ul><li><a href="view_customer.php">Beranda</a></li><li><a href="booking_customer.php">Booking</a></li><li><a href="pembatalan_customer.php">Pembatalan</a></li><li><a href="langganan_customer.php">Member</a></li><li><a href="pembelian_alat.php">Pembelian</a></li></ul></div>
        <div class="footer-col"><h4>Informasi</h4><ul><li><a href="#">Cara Booking</a></li><li><a href="#">Syarat &amp; Ketentuan</a></li><li><a href="#">Kebijakan Privasi</a></li><li><a href="#">FAQ</a></li></ul></div>
        <div class="footer-col"><h4>Hubungi Kami</h4>
            <div class="contact-item"><i class="fa-solid fa-location-dot"></i>Jl. Olahraga No. 10, Kebayoran Baru, Jakarta Selatan 12190</div>
            <div class="contact-item"><i class="fa-solid fa-phone"></i>+62 812-3456-7890</div>
            <div class="contact-item"><i class="fa-solid fa-envelope"></i>info@hoopball.id</div>
            <div class="contact-item"><i class="fa-solid fa-clock"></i>Setiap hari 07:00 – 23:00 WIB</div>
        </div>
    </div>
    <div class="footer-bottom"><p>&copy; 2025 HoopBall. All rights reserved.</p></div>
</footer>

<script>
/* ─── SCROLL PROGRESS ─── */
window.addEventListener('scroll',()=>{
    const st=document.documentElement.scrollTop||document.body.scrollTop;
    const sh=document.documentElement.scrollHeight-document.documentElement.clientHeight;
    document.getElementById('scrollProgress').style.transform=`scaleX(${st/sh})`;
});

/* ─── INTERSECTION OBSERVER ─── */
const observer=new IntersectionObserver((entries)=>{
    entries.forEach(e=>{if(e.isIntersecting)e.target.classList.add('active');});
},{threshold:.1,rootMargin:'0px 0px -50px 0px'});
document.querySelectorAll('.reveal,.reveal-left,.reveal-right,.reveal-scale,.reveal-stagger,.reveal-flip,.reveal-zoom').forEach(el=>observer.observe(el));

/* ─── STATE ─── */
let selectedCourtId=null,selectedCourtPrice=0,selectedCourtName='',selectedCourtImg='';
let isMember=<?= $has_member?'true':'false' ?>;
let memberDiscount=<?= $member_discount ?>;
let selectedSlotId=null,selectedSlotDuration=0,selectedSlotDateFormatted='',selectedSlotTimeFormatted='';
let selectedPaymentMethod='Transfer Bank';
let currentCourtSlots=[];
let countdownInterval;

const courts=document.querySelectorAll('.court-card');
const dateSelect=document.getElementById('dateSelect');
const timeSelect=document.getElementById('timeSelect');
const promoSelect=document.getElementById('promoSelect');
const payments=document.querySelectorAll('.payment-card');
const scheduleModal=document.getElementById('scheduleModal');
const bookingModal=document.getElementById('bookingModal');
const btnGoToSummary=document.getElementById('btnGoToSummary');
const btnSubmit=document.getElementById('btnSubmit');

function formatRupiah(n){return'Rp '+Math.max(0,n).toLocaleString('id-ID')}

/* ─── MODAL CONTROLS ─── */
document.getElementById('btnCloseSchedule').addEventListener('click',()=>{scheduleModal.style.display='none'});
document.getElementById('btnCloseSummary').addEventListener('click',()=>{bookingModal.style.display='none'});
btnGoToSummary.addEventListener('click',()=>{scheduleModal.style.display='none';bookingModal.style.display='flex'});
window.addEventListener('click',e=>{
    if(e.target===scheduleModal)scheduleModal.style.display='none';
    if(e.target===bookingModal)bookingModal.style.display='none';
    if(e.target===document.getElementById('paymentInstructionModal'))tutupInstructionModal();
});

function tutupInstructionModal(){
    clearInterval(countdownInterval);
    document.getElementById('paymentInstructionModal').style.display='none';
    document.body.style.overflow='';
}

/* ─── LOAD SLOTS ─── */
function loadSlots(courtId){
    dateSelect.innerHTML='<option value="">Memuat tanggal...</option>';
    timeSelect.innerHTML='<option value="">Menunggu tanggal...</option>';
    timeSelect.disabled=true;
    btnSubmit.disabled=true;
    btnGoToSummary.disabled=true;
    currentCourtSlots=[];
    return fetch(`booking_customer.php?action=get_slots&court_id=${courtId}`)
        .then(r=>r.json())
        .then(slots=>{
            currentCourtSlots=slots;
            dateSelect.innerHTML='';
            if(slots.length===0){
                dateSelect.innerHTML='<option value="">Tidak ada jadwal kosong</option>';
                showSlotStatus(false,'Tidak ada jadwal','Semua slot sudah terbooking');
                return;
            }
            const uniq=[];
            slots.forEach(s=>{if(!uniq.includes(s.Tanggal_Formatted))uniq.push(s.Tanggal_Formatted)});
            const def=document.createElement('option');def.value='';def.innerText='-- Pilih Tanggal Bermain --';
            dateSelect.appendChild(def);
            uniq.forEach(d=>{const o=document.createElement('option');o.value=d;o.innerText=d;dateSelect.appendChild(o)});
            if(uniq.length>0){dateSelect.selectedIndex=1;dateSelect.dispatchEvent(new Event('change'))}
        })
        .catch(()=>{dateSelect.innerHTML='<option value="">Gagal memuat jadwal</option>'});
}

function showSlotStatus(ok,title,desc){
    const box=document.getElementById('availabilityBox');
    document.getElementById('availIcon').innerHTML=ok?'<i class="fa-solid fa-circle-check"></i>':'<i class="fa-solid fa-circle-xmark"></i>';
    document.getElementById('availTitle').innerText=title;
    document.getElementById('lblDuration').innerText=desc;
    ok?box.classList.remove('empty'):box.classList.add('empty');
}

function calculatePrices(){
    if(!selectedSlotId){
        document.getElementById('lblTotalPrice').innerText='Rp 0';
        btnSubmit.disabled=true;btnGoToSummary.disabled=true;return;
    }
    const base=selectedCourtPrice*selectedSlotDuration;
    let disc=0;
    if(isMember){disc=memberDiscount;}
    else if(promoSelect){const o=promoSelect.options[promoSelect.selectedIndex];if(o)disc=parseFloat(o.getAttribute('data-discount')||0)}
    const total=Math.max(0,base-disc);
    document.getElementById('lblNormalPriceLabel').innerText=`Harga Sewa (${selectedSlotDuration} jam)`;
    document.getElementById('lblNormalPrice').innerText=formatRupiah(base);
    if(!isMember&&document.getElementById('lblPromoBreakdown'))document.getElementById('lblPromoBreakdown').innerText=`-Rp ${disc.toLocaleString('id-ID')}`;
    document.getElementById('lblTotalPrice').innerText=formatRupiah(total);
    document.getElementById('sumCourtName').innerText=selectedCourtName;
    document.getElementById('sumImg').src=selectedCourtImg;
    document.getElementById('sumPlayDate').innerText=selectedSlotDateFormatted;
    document.getElementById('sumTimeLabel').innerText=`${selectedSlotTimeFormatted} (${selectedSlotDuration} jam)`;
    btnSubmit.disabled=false;btnGoToSummary.disabled=false;
}

/* ─── COURT CLICK ─── */
courts.forEach(c=>{
    c.addEventListener('click',function(){
        const alreadySel=this.classList.contains('selected');
        if(!alreadySel){
            courts.forEach(x=>x.classList.remove('selected'));
            this.classList.add('selected');
            selectedCourtId=this.getAttribute('data-id');
            selectedCourtPrice=parseFloat(this.getAttribute('data-price'));
            selectedCourtName=this.getAttribute('data-name');
            selectedCourtImg=this.getAttribute('data-img');
            if(selectedCourtId)loadSlots(selectedCourtId);
        } else {
            if(selectedCourtId)scheduleModal.style.display='flex';
        }
    });
});

/* ─── DATE/TIME SELECT ─── */
dateSelect.addEventListener('change',function(){
    const sel=this.value;
    timeSelect.innerHTML='';
    if(!sel){timeSelect.innerHTML='<option value="">Pilih tanggal terlebih dahulu...</option>';timeSelect.disabled=true;selectedSlotId=null;showSlotStatus(false,'Tanggal belum dipilih','Silakan pilih tanggal');calculatePrices();return;}
    const filtered=currentCourtSlots.filter(s=>s.Tanggal_Formatted===sel);
    const def=document.createElement('option');def.value='';def.innerText='-- Pilih Jam Bermain --';timeSelect.appendChild(def);
    filtered.forEach(s=>{const o=document.createElement('option');o.value=s.ID_Jadwal;o.setAttribute('data-tanggal',s.Tanggal_Formatted);o.setAttribute('data-mulai',s.Jam_Mulai);o.setAttribute('data-selesai',s.Jam_Selesai);o.innerText=`${s.Jam_Mulai} - ${s.Jam_Selesai}`;timeSelect.appendChild(o)});
    timeSelect.disabled=false;
    if(filtered.length>0){timeSelect.selectedIndex=1;timeSelect.dispatchEvent(new Event('change'))}
});

timeSelect.addEventListener('change',function(){
    const opt=this.options[this.selectedIndex];
    if(!opt||!opt.value){selectedSlotId=null;showSlotStatus(false,'Jam belum dipilih','Silakan pilih jam bermain');calculatePrices();return;}
    selectedSlotId=opt.value;
    const sh=parseInt(opt.getAttribute('data-mulai').split(':')[0]);
    const eh=parseInt(opt.getAttribute('data-selesai').split(':')[0]);
    selectedSlotDuration=Math.max(1,eh-sh);
    selectedSlotDateFormatted=opt.getAttribute('data-tanggal');
    selectedSlotTimeFormatted=`${opt.getAttribute('data-mulai')} - ${opt.getAttribute('data-selesai')}`;
    showSlotStatus(true,'Slot Terkonfirmasi',`Durasi: ${selectedSlotDuration} jam`);
    calculatePrices();
});

if(promoSelect)promoSelect.addEventListener('change',calculatePrices);

payments.forEach(p=>{
    p.addEventListener('click',function(){
        payments.forEach(x=>x.classList.remove('selected'));
        this.classList.add('selected');
        selectedPaymentMethod=this.getAttribute('data-method');
    });
});

/* ─── SUBMIT → SHOW INSTRUKSI ─── */
btnSubmit.addEventListener('click',function(){
    if(!selectedSlotId)return;
    bookingModal.style.display='none';
    const base=selectedCourtPrice*selectedSlotDuration;
    let disc=0,idPromo=null;
    if(isMember){disc=memberDiscount;}
    else if(promoSelect){const o=promoSelect.options[promoSelect.selectedIndex];if(o&&o.value!=='0'){disc=parseFloat(o.getAttribute('data-discount')||0);idPromo=o.value}}
    const final=Math.max(0,base-disc);
    document.getElementById('paymentTotalAmount').innerText=formatRupiah(final);
    showPaymentMethodInstructions(selectedPaymentMethod);
    document.getElementById('paymentInstructionModal').style.display='flex';
    startPaymentCountdown(15*60);
});

/* ─── DONE PAYMENT → CHECKOUT ─── */
document.getElementById('btnDonePayment').addEventListener('click',function(){
    tutupInstructionModal();
    const base=selectedCourtPrice*selectedSlotDuration;
    let disc=0,idPromo=null;
    if(isMember){disc=memberDiscount;}
    else if(promoSelect){const o=promoSelect.options[promoSelect.selectedIndex];if(o&&o.value!=='0'){disc=parseFloat(o.getAttribute('data-discount')||0);idPromo=o.value}}
    const final=Math.max(0,base-disc);
    fetch('booking_customer.php?action=checkout',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({id_jadwal:selectedSlotId,id_promo:idPromo,metode_pembayaran:selectedPaymentMethod,total_bayar:final})})
        .then(r=>r.json())
        .then(result=>{
            if(result.success){
                Swal.fire({icon:'success',title:'Pembayaran Diterima!',text:'Terima kasih, kami sedang memverifikasi transaksi pembayaran anda harap ditunggu.',confirmButtonColor:'var(--orange)',confirmButtonText:'Selesai'}).then(()=>location.reload());
            } else {
                Swal.fire({icon:'warning',title:'Jadwal Tidak Tersedia',text:result.message,confirmButtonColor:'var(--orange)',confirmButtonText:'Pilih Jadwal Lain'});
            }
        })
        .catch(()=>Swal.fire({icon:'error',title:'Koneksi Terputus',text:'Gagal terhubung ke server. Periksa koneksi internet Anda.',confirmButtonColor:'var(--orange)',confirmButtonText:'Coba Lagi'}));
});

/* ─── PAYMENT METHOD DISPLAY ─── */
function showPaymentMethodInstructions(method){
    selectedPaymentMethod=method;
    const va=document.getElementById('instruksiTransfer'),qris=document.getElementById('instruksiQRIS');
    const btnVA=document.getElementById('btnSwitchVA'),btnQR=document.getElementById('btnSwitchQRIS');
    if(method==='Transfer Bank'){
        btnVA.classList.add('active');btnQR.classList.remove('active');
        va.style.display='block';qris.style.display='none';
    } else {
        btnQR.classList.add('active');btnVA.classList.remove('active');
        va.style.display='none';qris.style.display='flex';
        const total=document.getElementById('paymentTotalAmount').innerText.replace(/[^0-9]/g,'');
        document.getElementById('qrisImage').src=`https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent('HOOPBALL-PAYMENT-'+selectedSlotId+'-'+total)}`;
    }
}

/* ─── COUNTDOWN ─── */
function startPaymentCountdown(duration){
    clearInterval(countdownInterval);
    let timer=duration;
    const display=document.getElementById('paymentCountdown');
    countdownInterval=setInterval(()=>{
        const m=String(Math.floor(timer/60)).padStart(2,'0');
        const s=String(timer%60).padStart(2,'0');
        display.textContent=`${m}:${s}`;
        if(--timer<0){clearInterval(countdownInterval);display.textContent='Waktu Habis';document.getElementById('btnDonePayment').disabled=true;}
    },1000);
}

/* ─── COPY VA ─── */
document.getElementById('btnCopyVA').addEventListener('click',()=>{
    const v=document.getElementById('vaNumber');v.select();v.setSelectionRange(0,99999);
    navigator.clipboard.writeText(v.value).then(()=>{
        Swal.fire({icon:'success',title:'Berhasil Disalin!',text:'Nomor VA disalin ke papan klip.',confirmButtonColor:'var(--orange)',confirmButtonText:'OK'});
    });
});

/* ─── HAPUS AKUN ─── */
function confirmHapusAkun(e){
    e.preventDefault();
    Swal.fire({title:'Hapus Akun Permanen?',html:'<strong style="color:#FF3B30">PERINGATAN:</strong> Tindakan ini tidak dapat dibatalkan!<br><br>Data Anda akan dihapus dari sistem.',icon:'warning',showCancelButton:true,confirmButtonColor:'#FF3B30',cancelButtonColor:'#8E8E93',confirmButtonText:'Ya, Hapus Akun Saya',cancelButtonText:'Batal',reverseButtons:true,allowOutsideClick:false})
    .then(r=>{
        if(r.isConfirmed){
            let ti;
            Swal.fire({title:'Menghapus Akun...',html:'Mohon tunggu...<b></b>',timer:2000,timerProgressBar:true,allowOutsideClick:false,didOpen:()=>{Swal.showLoading();const b=Swal.getHtmlContainer().querySelector('b');ti=setInterval(()=>{b.textContent=Math.ceil(Swal.getTimerLeft()/1000)+' detik'},100)},willClose:()=>clearInterval(ti)}).then(()=>window.location.href='?hapus_akun=1');
        }
    });
}

/* ─── URL NOTIFICATION ─── */
const urlParams=new URLSearchParams(window.location.search);
const status=urlParams.get('status'),msg=urlParams.get('msg');
if(status&&msg){
    const ok=status==='success';
    Swal.fire({icon:ok?'success':'error',title:ok?'Berhasil':'Gagal',text:msg,confirmButtonColor:'var(--orange)',confirmButtonText:'OK'});
    window.history.replaceState({},document.title,window.location.pathname);
}

/* ─── INIT ─── */
document.addEventListener('DOMContentLoaded',()=>{
    const active=document.querySelector('.court-card.selected');
    if(active){
        selectedCourtId=active.getAttribute('data-id');
        selectedCourtPrice=parseFloat(active.getAttribute('data-price'));
        selectedCourtName=active.getAttribute('data-name');
        selectedCourtImg=active.getAttribute('data-img');
        loadSlots(selectedCourtId);
    }
});
</script>
</body>
</html>