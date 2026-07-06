<?php
/**
 * Halaman Booking Lapangan - Customer
 * Tema visual disamakan dengan landing page (index.php): warna oranye,
 * font Barlow / Barlow Condensed, dan konvensi radius/shadow yang sama.
 */
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include '../includes/auth_helper.php';
include '../includes/config.php';

$current_page = basename($_SERVER['PHP_SELF']);

// =========================================================================
// Hapus Akun (soft delete)
// =========================================================================
if (isset($_GET['hapus_akun']) && $_GET['hapus_akun'] == '1') {
    $id_customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';

    if (!empty($id_customer)) {
        $modified_by = $_SESSION['nama'] ?? 'CUSTOMER';
        $stmt = sqlsrv_query(
            $conn,
            "UPDATE Customer SET Is_Deleted=1,Status=0,Deleted_By=?,Deleted_Date=GETDATE() WHERE ID_Customer=? AND Is_Deleted=0",
            array($modified_by, $id_customer)
        );

        if ($stmt) {
            $_SESSION = array();
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
            setcookie('remember_me', '', time() - 3600, "/");
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

// =========================================================================
// Data Profil Customer
// =========================================================================
$id_customer   = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? '';
$nama_customer = 'Pelanggan';
$photo_profile = '';

if (!empty($id_customer)) {
    $st = sqlsrv_query($conn, "SELECT Nama_Customer,Photo_Profile,Is_Deleted,Status FROM Customer WHERE ID_Customer=?", array($id_customer));
    if ($st) {
        $row = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC);
        if ($row) {
            if ($row['Is_Deleted'] == 1 || $row['Status'] == 0) {
                $_SESSION = array();
                session_destroy();
                setcookie('remember_me', '', time() - 3600, "/");
                ob_end_clean();
                header("Location: ../login/login.php?status=error&msg=Akun Anda telah dihapus atau dinonaktifkan. Silakan hubungi admin atau daftar ulang.");
                exit();
            }
            $nama_customer = $row['Nama_Customer'] ?? 'Pelanggan';
            $photo_profile = $row['Photo_Profile'] ?? '';
        }
    }
}

// =========================================================================
// Data Membership Aktif
// =========================================================================
$member_data = null;
$mc = sqlsrv_query(
    $conn,
    "SELECT TOP 1 L.*,T.Nama_Tipe,T.Potongan_Harga,T.Harga_Member FROM Langganan L
     INNER JOIN Tipe_Member T ON L.ID_Tipe=T.ID_Tipe
     WHERE L.ID_Customer=? AND L.Status=1 AND GETDATE() BETWEEN L.Tanggal_Mulai AND L.Tanggal_Selesai
     ORDER BY L.Tanggal_Selesai DESC",
    array($id_customer)
);
if ($mc) {
    $member_data = sqlsrv_fetch_array($mc, SQLSRV_FETCH_ASSOC);
}
$has_member     = !empty($member_data);
$member_tipe    = $has_member ? $member_data['Nama_Tipe'] : '';
$member_discount = $has_member ? floatval($member_data['Potongan_Harga']) : 0;

/**
 * Menyusun path foto agar selalu valid diakses dari folder customer/.
 */
function resolvePhotoPath($photo_path) {
    if (empty($photo_path)) return '';
    if (strpos($photo_path, 'http://') === 0 || strpos($photo_path, 'https://') === 0) return $photo_path;
    if (strpos($photo_path, '../') === 0) return $photo_path;
    if (strpos($photo_path, '/') === 0) return '..' . $photo_path;
    return '../' . ltrim($photo_path, '/');
}

/**
 * Generate jadwal 7 hari ke depan (07:00 - 23:00) untuk seluruh lapangan aktif,
 * hanya menyisipkan slot yang belum ada di tabel Jadwal.
 */
function generateJadwalOtomatis($conn) {
    $q = sqlsrv_query($conn, "SELECT ID_Lapangan FROM Lapangan WHERE Status=1 AND Is_Deleted=0");
    if (!$q) return;

    $list = [];
    while ($r = sqlsrv_fetch_array($q, SQLSRV_FETCH_ASSOC)) {
        $list[] = $r['ID_Lapangan'];
    }

    $slots = [
        ['07:00:00', '08:00:00'], ['08:00:00', '09:00:00'], ['09:00:00', '10:00:00'], ['10:00:00', '11:00:00'],
        ['11:00:00', '12:00:00'], ['12:00:00', '13:00:00'], ['13:00:00', '14:00:00'], ['14:00:00', '15:00:00'],
        ['15:00:00', '16:00:00'], ['16:00:00', '17:00:00'], ['17:00:00', '18:00:00'], ['18:00:00', '19:00:00'],
        ['19:00:00', '20:00:00'], ['20:00:00', '21:00:00'], ['21:00:00', '22:00:00'], ['22:00:00', '23:00:00'],
    ];

    for ($i = 0; $i < 7; $i++) {
        $d = date('Y-m-d', strtotime("+$i days"));
        foreach ($list as $id_lap) {
            foreach ($slots as $s) {
                $cek = sqlsrv_query($conn, "SELECT ID_Jadwal FROM Jadwal WHERE ID_Lapangan=? AND Tanggal=? AND Jam_Mulai=? AND Jam_Selesai=?", array($id_lap, $d, $s[0], $s[1]));
                if ($cek && !sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC)) {
                    sqlsrv_query($conn, "INSERT INTO Jadwal(ID_Lapangan,Tanggal,Jam_Mulai,Jam_Selesai,Status,Is_Deleted,Created_By,Created_Date) VALUES(?,?,?,?,1,0,'SYSTEM_AUTO',GETDATE())", array($id_lap, $d, $s[0], $s[1]));
                }
            }
        }
    }
}
generateJadwalOtomatis($conn);

// =========================================================================
// Endpoint AJAX (JSON)
// =========================================================================
if (isset($_GET['action'])) {
    header('Content-Type: application/json');

    // --- Ambil slot 1 jam yang tersedia ---
    if ($_GET['action'] == 'get_slots' && isset($_GET['court_id']) && isset($_GET['tanggal'])) {
        $cid     = intval($_GET['court_id']);
        $tanggal = $_GET['tanggal'];

        $st = sqlsrv_query($conn,
            "SELECT ID_Jadwal,Tanggal,Jam_Mulai,Jam_Selesai FROM Jadwal
             WHERE ID_Lapangan=? AND Status=1 AND Is_Deleted=0 AND Tanggal=?
             AND ID_Jadwal NOT IN (SELECT ID_Jadwal FROM Booking)
             AND (Tanggal>CAST(GETDATE() AS DATE) OR (Tanggal=CAST(GETDATE() AS DATE) AND Jam_Mulai>CAST(GETDATE() AS TIME)))
             ORDER BY Jam_Mulai ASC", array($cid, $tanggal));

        $slots = [];
        if ($st) {
            while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
                $tgl     = ($r['Tanggal'] instanceof DateTime) ? $r['Tanggal']->format('Y-m-d') : $r['Tanggal'];
                $mulai   = ($r['Jam_Mulai'] instanceof DateTime) ? $r['Jam_Mulai']->format('H:i') : substr($r['Jam_Mulai'], 0, 5);
                $selesai = ($r['Jam_Selesai'] instanceof DateTime) ? $r['Jam_Selesai']->format('H:i') : substr($r['Jam_Selesai'], 0, 5);
                $slots[] = ['ID_Jadwal' => $r['ID_Jadwal'], 'Tanggal' => $tgl, 'Jam_Mulai' => $mulai, 'Jam_Selesai' => $selesai];
            }
        }
        echo json_encode($slots);
        exit();
    }

    // --- Ambil blok 3 jam berurutan yang tersedia ---
    if ($_GET['action'] == 'get_3h_blocks' && isset($_GET['court_id']) && isset($_GET['tanggal'])) {
        $cid     = intval($_GET['court_id']);
        $tanggal = $_GET['tanggal'];

        $st = sqlsrv_query($conn,
            "SELECT ID_Jadwal,Jam_Mulai,Jam_Selesai FROM Jadwal
             WHERE ID_Lapangan=? AND Status=1 AND Is_Deleted=0 AND Tanggal=?
             AND ID_Jadwal NOT IN (SELECT ID_Jadwal FROM Booking)
             AND (Tanggal>CAST(GETDATE() AS DATE) OR (Tanggal=CAST(GETDATE() AS DATE) AND Jam_Mulai>CAST(GETDATE() AS TIME)))
             ORDER BY Jam_Mulai ASC", array($cid, $tanggal));

        $slots = [];
        if ($st) {
            while ($r = sqlsrv_fetch_array($st, SQLSRV_FETCH_ASSOC)) {
                $mulai   = ($r['Jam_Mulai'] instanceof DateTime) ? $r['Jam_Mulai']->format('H:i:s') : $r['Jam_Mulai'];
                $selesai = ($r['Jam_Selesai'] instanceof DateTime) ? $r['Jam_Selesai']->format('H:i:s') : $r['Jam_Selesai'];
                $slots[] = ['ID_Jadwal' => $r['ID_Jadwal'], 'Jam_Mulai' => $mulai, 'Jam_Selesai' => $selesai];
            }
        }

        $blocks = [];
        for ($i = 0; $i <= count($slots) - 3; $i++) {
            $s1 = $slots[$i]; $s2 = $slots[$i + 1]; $s3 = $slots[$i + 2];
            $end1 = strtotime($s1['Jam_Selesai']); $start2 = strtotime($s2['Jam_Mulai']);
            $end2 = strtotime($s2['Jam_Selesai']); $start3 = strtotime($s3['Jam_Mulai']);

            if ($end1 == $start2 && $end2 == $start3) {
                $blocks[] = [
                    'ID_Jadwal_1' => $s1['ID_Jadwal'], 'ID_Jadwal_2' => $s2['ID_Jadwal'], 'ID_Jadwal_3' => $s3['ID_Jadwal'],
                    'Jam_Mulai'   => substr($s1['Jam_Mulai'], 0, 5), 'Jam_Selesai' => substr($s3['Jam_Selesai'], 0, 5), 'Duration' => 3,
                ];
            }
        }
        echo json_encode($blocks);
        exit();
    }

    // --- Proses checkout / pembuatan booking ---
    if ($_GET['action'] == 'checkout' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) $input = $_POST;

        $id_jadwal_list = $input['id_jadwal_list'] ?? [];
        $id_promo       = !empty($input['id_promo']) ? intval($input['id_promo']) : null;
        $metode         = htmlspecialchars($input['metode_pembayaran'] ?? '');
        $total          = floatval($input['total_bayar'] ?? 0);

        if (empty($id_jadwal_list) || empty($metode) || $total <= 0) {
            echo json_encode(['success' => false, 'message' => 'Parameter input tidak valid.']);
            exit();
        }

        if (sqlsrv_begin_transaction($conn) === false) {
            echo json_encode(['success' => false, 'message' => 'Gagal menginisiasi transaksi database.']);
            exit();
        }

        try {
            foreach ($id_jadwal_list as $jid) {
                $chk    = sqlsrv_query($conn, "SELECT Status,ID_Lapangan FROM Jadwal WHERE ID_Jadwal=?", array($jid));
                $jadwal = null;
                if ($chk) $jadwal = sqlsrv_fetch_array($chk, SQLSRV_FETCH_ASSOC);
                if (!$jadwal || $jadwal['Status'] != 1) {
                    throw new Exception("Maaf, salah satu slot jadwal sudah terbooking atau tidak tersedia.");
                }
            }

            $kq = sqlsrv_query($conn, "SELECT TOP 1 ID_Karyawan FROM Karyawan WHERE Status=1 AND Is_Deleted=0 ORDER BY ID_Karyawan ASC");
            $id_karyawan = 1;
            if ($kq) {
                $kd = sqlsrv_fetch_array($kq, SQLSRV_FETCH_ASSOC);
                if ($kd) $id_karyawan = $kd['ID_Karyawan'];
            }

            $by       = $_SESSION['nama'] ?? 'CUSTOMER';
            $first_jid = $id_jadwal_list[0];

            $ins = sqlsrv_query($conn, "INSERT INTO Booking(ID_Customer,ID_Karyawan,ID_Jadwal,ID_Promo,Tanggal_Booking,Metode_Pembayaran,Total_Bayar,Status,Created_By,Created_Date) VALUES(?,?,?,?,CAST(GETDATE() AS DATE),?,?,0,?,GETDATE())",
                array($id_customer, $id_karyawan, $first_jid, $id_promo, $metode, $total, $by));

            if ($ins === false) {
                $e = sqlsrv_errors();
                throw new Exception("Terjadi kendala koneksi database (Kode: " . ($e[0]['code'] ?? 0) . "). Silakan hubungi operator.");
            }

            foreach ($id_jadwal_list as $jid) {
                $upd = sqlsrv_query($conn, "UPDATE Jadwal SET Status=0,Modified_By=?,Modified_Date=GETDATE() WHERE ID_Jadwal=?", array($by, $jid));
                if ($upd === false) {
                    throw new Exception("Gagal memperbarui status jadwal.");
                }
            }

            sqlsrv_commit($conn);
            echo json_encode(['success' => true, 'message' => 'Pemesanan berhasil dibuat!']);
        } catch (Exception $e) {
            sqlsrv_rollback($conn);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
}

// =========================================================================
// Data untuk Tampilan Halaman
// =========================================================================
$lapanganList = [];
$ql = sqlsrv_query($conn, "SELECT ID_Lapangan,Nama_Lapangan,Harga_Sewa,Photo_Lapangan FROM Lapangan WHERE Status=1 AND Is_Deleted=0");
if ($ql) {
    while ($r = sqlsrv_fetch_array($ql, SQLSRV_FETCH_ASSOC)) {
        $lapanganList[] = $r;
    }
}

$lapanganFasilitas = [];
$qf = sqlsrv_query($conn, "SELECT ID_Lapangan,Nama_Fasilitas FROM Fasilitas_Lapangan WHERE Status=1 AND Is_Deleted=0");
if ($qf) {
    while ($r = sqlsrv_fetch_array($qf, SQLSRV_FETCH_ASSOC)) {
        $lapanganFasilitas[$r['ID_Lapangan']][] = $r['Nama_Fasilitas'];
    }
}

$promos = [];
if (!$has_member) {
    $qp = sqlsrv_query($conn, "SELECT ID_Promo,Nama_Promo,Diskon FROM Promo WHERE Status=1 AND Is_Deleted=0 AND CAST(GETDATE() AS DATE) BETWEEN Tanggal_Mulai AND Tanggal_Selesai");
    if ($qp) {
        while ($r = sqlsrv_fetch_array($qp, SQLSRV_FETCH_ASSOC)) {
            $promos[] = $r;
        }
    }
}

$dateList  = [];
$hariIndo  = ['Min', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab'];
$bulanIndo = ['', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
for ($i = 0; $i < 7; $i++) {
    $ts = strtotime("+$i days");
    $dateList[] = [
        'value'   => date('Y-m-d', $ts),
        'hari'    => $hariIndo[date('w', $ts)],
        'tgl'     => date('j', $ts),
        'bulan'   => $bulanIndo[date('n', $ts)],
        'full'    => date('d M Y', $ts),
        'isToday' => $i === 0,
    ];
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Booking Lapangan | HoopBall Arena</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../asset/css/navbar_footer.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
/* Variabel warna disamakan dengan tema oranye pada landing page (index.php).
   Nilai fallback ini didefinisikan ulang agar halaman tetap konsisten
   walau navbar_footer.css belum memuat variabel yang sama. */
:root{
    --orange:#FF5400;
    --orange-dark:#E63900;
    --orange-light:rgba(255,84,0,0.08);
    --orange-glow:rgba(255,84,0,0.15);
    --dark:#1E293B;
    --text-dark:#1E293B;
    --text-secondary:#64748B;
    --text-muted:#94A3B8;
    --border-color:#E2E8F0;
    --border-light:#F1F5F9;
    --bg-light:#F8FAFC;
    --card-bg:#FFFFFF;
    --green:#22C55E;
    --green-light:rgba(34,197,94,0.1);
    --red:#EF4444;
    --red-light:rgba(239,68,68,0.1);
    --radius-sm:10px;
    --radius-md:14px;
    --radius-lg:16px;
    --shadow-sm:0 1px 3px rgba(0,0,0,0.04);
    --shadow-md:0 4px 20px rgba(0,0,0,0.06);
    --shadow-lg:0 12px 30px rgba(0,0,0,0.08);
    --transition:all 0.3s cubic-bezier(0.4,0,0.2,1);
}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Barlow',sans-serif;background:var(--bg-light);color:var(--text-dark);-webkit-font-smoothing:antialiased}
::-webkit-scrollbar{width:6px;height:6px}
::-webkit-scrollbar-track{background:transparent}
::-webkit-scrollbar-thumb{background:var(--border-color);border-radius:3px}
.booking-container{max-width:900px;margin:0 auto;padding:24px 20px 100px}
.booking-header{margin-bottom:28px}
.booking-header h1{font-family:'Barlow Condensed',sans-serif;font-size:26px;font-weight:800;color:var(--dark);display:flex;align-items:center;gap:10px}
.booking-header h1 i{color:var(--orange);font-size:22px}
.booking-header p{font-size:13px;color:var(--text-muted);margin-top:4px;font-weight:500}
.date-section{margin-bottom:28px}
.date-section-label{font-size:13px;font-weight:700;color:var(--dark);margin-bottom:12px;display:flex;align-items:center;gap:6px}
.date-section-label i{color:var(--orange)}
.date-scroll{display:flex;gap:8px;overflow-x:auto;padding-bottom:4px;scrollbar-width:none}
.date-scroll::-webkit-scrollbar{display:none}
.date-chip{flex-shrink:0;min-width:64px;padding:12px 8px;border-radius:var(--radius-md);border:1.5px solid var(--border-color);background:var(--card-bg);cursor:pointer;text-align:center;transition:var(--transition);position:relative}
.date-chip:hover{border-color:var(--orange);transform:translateY(-2px);box-shadow:var(--shadow-md)}
.date-chip.active{background:var(--orange);border-color:var(--orange);color:#fff;box-shadow:0 4px 16px rgba(255,84,0,0.3)}
.date-chip .day-name{font-size:11px;font-weight:600;text-transform:uppercase;margin-bottom:2px}
.date-chip.active .day-name{color:rgba(255,255,255,0.85)}
.date-chip .day-num{font-size:20px;font-weight:800;line-height:1}
.date-chip .month-name{font-size:10px;font-weight:600;margin-top:2px}
.date-chip.active .month-name{color:rgba(255,255,255,0.85)}
.date-chip .today-badge{position:absolute;top:-6px;right:-6px;background:var(--orange);color:#fff;font-size:8px;font-weight:800;padding:2px 6px;border-radius:10px}
.court-section{margin-bottom:28px}
.court-section-label{font-size:13px;font-weight:700;color:var(--dark);margin-bottom:14px;display:flex;align-items:center;gap:6px}
.court-section-label i{color:var(--orange)}
.court-card{background:var(--card-bg);border-radius:var(--radius-lg);border:1px solid var(--border-color);overflow:hidden;display:flex;margin-bottom:16px;transition:var(--transition);cursor:pointer}
.court-card:hover{border-color:var(--orange);box-shadow:var(--shadow-lg);transform:translateY(-2px)}
.court-card.selected{border-color:var(--orange);box-shadow:0 0 0 3px var(--orange-glow),var(--shadow-md)}
.court-img-wrap{width:200px;min-height:160px;flex-shrink:0;position:relative;overflow:hidden;background:linear-gradient(135deg,#FFF7ED 0%,#FFEDD5 100%)}
.court-img-wrap img{width:100%;height:100%;object-fit:cover;transition:transform 0.5s ease}
.court-card:hover .court-img-wrap img{transform:scale(1.05)}
.court-img-badge{position:absolute;bottom:10px;left:10px;background:var(--green);color:#fff;font-size:10px;font-weight:700;padding:4px 10px;border-radius:20px}
.court-body{flex:1;padding:20px;display:flex;flex-direction:column;justify-content:space-between}
.court-name{font-size:16px;font-weight:700;color:var(--dark);margin-bottom:6px}
.court-desc{font-size:12px;color:var(--text-muted);line-height:1.5;margin-bottom:12px}
.court-meta{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:14px}
.court-meta-item{display:flex;align-items:center;gap:5px;font-size:12px;color:var(--text-muted);font-weight:500}
.court-meta-item i{font-size:11px;color:var(--orange)}
.court-footer{display:flex;align-items:center;justify-content:space-between;gap:12px}
.court-price{font-size:18px;font-weight:800;color:var(--orange)}
.court-price span{font-size:12px;font-weight:600;color:var(--text-muted)}
.court-btn{background:var(--orange);color:#fff;border:none;padding:10px 24px;border-radius:10px;font-family:inherit;font-size:13px;font-weight:700;cursor:pointer;transition:var(--transition);display:flex;align-items:center;gap:6px}
.court-btn:hover{background:var(--orange-dark);transform:translateY(-1px);box-shadow:0 4px 16px rgba(255,84,0,0.3)}
.slots-section{display:none;margin-bottom:28px;animation:fadeInUp 0.4s ease-out}
.slots-section.active{display:block}
.slots-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:12px}
.slots-title{font-size:14px;font-weight:700;color:var(--dark)}
.slots-subtitle{font-size:12px;color:var(--text-muted);font-weight:500}
.duration-toggle{display:flex;gap:4px;background:var(--border-light);padding:4px;border-radius:10px}
.duration-btn{padding:8px 16px;border:none;border-radius:8px;font-family:inherit;font-size:12px;font-weight:700;cursor:pointer;background:transparent;color:var(--text-secondary);transition:var(--transition)}
.duration-btn.active{background:var(--card-bg);color:var(--orange);box-shadow:var(--shadow-sm)}
.slots-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:10px}
.slot-item{padding:14px 8px;border-radius:var(--radius-md);border:1.5px solid var(--border-color);background:var(--card-bg);text-align:center;cursor:pointer;transition:var(--transition);position:relative}
.slot-item:hover{border-color:var(--orange);transform:translateY(-2px);box-shadow:var(--shadow-md)}
.slot-item.selected{background:var(--orange);border-color:var(--orange);color:#fff;box-shadow:0 4px 16px rgba(255,84,0,0.3)}
.slot-time{font-size:13px;font-weight:700}
.slot-status{font-size:10px;font-weight:600;margin-top:4px;padding:2px 8px;border-radius:10px;display:inline-block}
.slot-item:not(.selected) .slot-status.available{background:var(--green-light);color:var(--green)}
.slot-item.selected .slot-status{background:rgba(255,255,255,0.2);color:#fff}
.slot-price{font-size:11px;font-weight:600;margin-top:4px;color:var(--text-muted)}
.slot-item.selected .slot-price{color:rgba(255,255,255,0.8)}
.summary-bar{position:fixed;bottom:0;left:0;right:0;background:var(--card-bg);border-top:1px solid var(--border-color);padding:16px 20px;display:none;align-items:center;justify-content:space-between;gap:16px;z-index:100;box-shadow:0 -4px 20px rgba(0,0,0,0.08)}
.summary-bar.active{display:flex}
.summary-info{flex:1}
.summary-info-label{font-size:11px;color:var(--text-muted);font-weight:600}
.summary-info-value{font-size:14px;font-weight:700;color:var(--dark)}
.summary-price{text-align:right}
.summary-price-label{font-size:11px;color:var(--text-muted);font-weight:600}
.summary-price-value{font-size:20px;font-weight:800;color:var(--orange)}
.summary-btn{background:var(--orange);color:#fff;border:none;padding:12px 28px;border-radius:12px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;transition:var(--transition);white-space:nowrap}
.summary-btn:hover{background:var(--orange-dark);transform:translateY(-1px);box-shadow:0 4px 16px rgba(255,84,0,0.3)}
.modal-overlay{position:fixed;top:0;left:0;right:0;bottom:0;background:rgba(15,23,42,0.5);backdrop-filter:blur(4px);display:none;align-items:center;justify-content:center;z-index:1000;padding:20px}
.modal-overlay.active{display:flex;animation:fadeIn 0.2s ease-out}
.modal-card{background:var(--card-bg);border-radius:var(--radius-lg);width:100%;max-width:480px;max-height:90vh;overflow-y:auto;box-shadow:var(--shadow-lg);animation:slideUp 0.3s ease-out}
@keyframes slideUp{from{opacity:0;transform:translateY(30px)}to{opacity:1;transform:translateY(0)}}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}
.modal-header{padding:24px;border-bottom:1px solid var(--border-color);display:flex;align-items:center;justify-content:space-between}
.modal-title{font-size:16px;font-weight:700;color:var(--dark)}
.modal-close{width:32px;height:32px;border-radius:50%;border:none;background:var(--border-light);color:var(--text-muted);cursor:pointer;font-size:14px;transition:var(--transition)}
.modal-close:hover{background:var(--red-light);color:var(--red)}
.modal-body{padding:24px}
.modal-footer{padding:16px 24px 24px;display:flex;gap:10px}
.modal-footer .btn-full{flex:1}
.detail-row{display:flex;justify-content:space-between;align-items:center;padding:12px 0;border-bottom:1px solid var(--border-light)}
.detail-row:last-child{border-bottom:none}
.detail-label{font-size:13px;color:var(--text-secondary);font-weight:500}
.detail-value{font-size:13px;font-weight:700;color:var(--dark)}
.detail-value.price{color:var(--orange);font-size:15px}
.detail-value.discount{color:var(--green)}
.detail-total{background:var(--orange-light);border-radius:var(--radius-md);padding:16px;margin-top:16px;display:flex;justify-content:space-between;align-items:center}
.detail-total-label{font-size:14px;font-weight:700;color:var(--dark)}
.detail-total-value{font-size:22px;font-weight:800;color:var(--orange)}
.payment-options{display:flex;flex-direction:column;gap:10px;margin-top:16px}
.payment-option{display:flex;align-items:center;gap:12px;padding:14px;border:1.5px solid var(--border-color);border-radius:var(--radius-md);cursor:pointer;transition:var(--transition);background:var(--card-bg)}
.payment-option:hover{border-color:var(--orange)}
.payment-option.selected{border-color:var(--orange);background:var(--orange-light)}
.payment-radio{width:20px;height:20px;border-radius:50%;border:2px solid var(--border-color);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:var(--transition)}
.payment-option.selected .payment-radio{border-color:var(--orange)}
.payment-radio::after{content:'';width:10px;height:10px;border-radius:50%;background:var(--orange);display:none}
.payment-option.selected .payment-radio::after{display:block}
.payment-info{flex:1}
.payment-name{font-size:13px;font-weight:700;color:var(--dark)}
.payment-desc{font-size:11px;color:var(--text-muted);margin-top:2px}
.payment-icon{width:40px;height:40px;border-radius:10px;background:var(--orange-light);display:flex;align-items:center;justify-content:center;color:var(--orange);font-size:16px}
.promo-section{margin-top:16px;padding-top:16px;border-top:1px solid var(--border-light)}
.promo-label{font-size:12px;font-weight:700;color:var(--dark);margin-bottom:8px}
.promo-select{width:100%;padding:12px 14px;border:1.5px solid var(--border-color);border-radius:var(--radius-md);font-family:inherit;font-size:13px;color:var(--dark);background:var(--card-bg);outline:none;transition:var(--transition);cursor:pointer}
.promo-select:focus{border-color:var(--orange);box-shadow:0 0 0 3px var(--orange-glow)}
.promo-locked{padding:12px 14px;background:var(--border-light);border-radius:var(--radius-md);font-size:13px;color:var(--text-muted);display:flex;align-items:center;gap:8px}
.promo-locked i{color:#F59E0B}
.btn-primary{background:var(--orange);color:#fff;border:none;padding:14px 24px;border-radius:12px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;transition:var(--transition);display:flex;align-items:center;justify-content:center;gap:8px;width:100%}
.btn-primary:hover{background:var(--orange-dark);transform:translateY(-1px);box-shadow:0 4px 16px rgba(255,84,0,0.3)}
.btn-primary:disabled{background:var(--text-muted);cursor:not-allowed;transform:none;box-shadow:none}
.btn-secondary{background:var(--border-light);color:var(--dark);border:none;padding:14px 24px;border-radius:12px;font-family:inherit;font-size:14px;font-weight:700;cursor:pointer;transition:var(--transition)}
.btn-secondary:hover{background:var(--border-color)}
.payment-instr-tabs{display:flex;gap:4px;background:var(--border-light);padding:4px;border-radius:10px;margin-bottom:20px}
.instr-tab{flex:1;padding:10px;border:none;border-radius:8px;font-family:inherit;font-size:12px;font-weight:700;cursor:pointer;background:transparent;color:var(--text-secondary);transition:var(--transition)}
.instr-tab.active{background:var(--card-bg);color:var(--orange);box-shadow:var(--shadow-sm)}
.va-box{background:var(--bg-light);border:1.5px solid var(--border-color);border-radius:var(--radius-md);padding:20px;text-align:center;margin-bottom:16px}
.va-label{font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.va-number{font-size:22px;font-weight:800;color:var(--dark);letter-spacing:2px;font-family:'Courier New',monospace}
.va-copy-btn{margin-top:12px;background:var(--orange);color:#fff;border:none;padding:8px 20px;border-radius:8px;font-family:inherit;font-size:12px;font-weight:700;cursor:pointer;transition:var(--transition)}
.va-copy-btn:hover{background:var(--orange-dark)}
.qris-box{text-align:center;padding:20px}
.qris-img{width:180px;height:180px;object-fit:contain;margin:0 auto 16px;display:block}
.countdown-box{background:var(--orange-light);border:1.5px solid var(--orange-glow);border-radius:var(--radius-md);padding:12px 16px;display:flex;align-items:center;justify-content:center;gap:8px;margin-bottom:16px}
.countdown-box i{color:var(--orange)}
.countdown-text{font-size:12px;font-weight:700;color:var(--orange)}
.total-box{background:var(--border-light);border-radius:var(--radius-md);padding:16px;text-align:center;margin-bottom:20px}
.total-label{font-size:11px;font-weight:700;color:var(--text-muted);text-transform:uppercase}
.total-amount{font-size:28px;font-weight:800;color:var(--orange);margin-top:4px}
.loading-spinner{display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,0.3);border-top-color:#fff;border-radius:50%;animation:spin 0.8s linear infinite}
@keyframes spin{to{transform:rotate(360deg)}}
.empty-state{text-align:center;padding:40px;color:var(--text-muted)}
.empty-state i{font-size:40px;margin-bottom:12px;opacity:0.5}
.empty-state p{font-size:14px;font-weight:600}
@media(max-width:768px){
.booking-container{padding:16px 12px 100px}
.court-card{flex-direction:column}
.court-img-wrap{width:100%;height:180px}
.slots-grid{grid-template-columns:repeat(3,1fr)}
.summary-bar{flex-direction:column;gap:10px;padding:12px 16px}
.summary-info,.summary-price{text-align:center;width:100%}
.summary-btn{width:100%}
.slots-header{flex-direction:column;align-items:flex-start}
}
@media(max-width:480px){
.slots-grid{grid-template-columns:repeat(2,1fr)}
}
</style>
</head>
<body>
<?php $path_prefix = '../'; include '../includes/navbar.php'; ?>
<div class="booking-container">
<div class="booking-header">
<h1><i class="fa-solid fa-basketball"></i> Pilih Lapangan</h1>
<p>Pilih tanggal, lapangan, dan jam yang tersedia untuk booking.</p>
</div>
<div class="date-section">
<div class="date-section-label"><i class="fa-solid fa-calendar-days"></i> Pilih Tanggal</div>
<div class="date-scroll" id="dateScroll">
<?php foreach ($dateList as $idx => $d): ?>
<div class="date-chip <?= $idx === 0 ? 'active' : '' ?>" data-value="<?= $d['value'] ?>" onclick="selectDate(this,'<?= $d['value'] ?>')">
<?php if ($d['isToday']): ?><span class="today-badge">Hari Ini</span><?php endif; ?>
<div class="day-name"><?= $d['hari'] ?></div>
<div class="day-num"><?= $d['tgl'] ?></div>
<div class="month-name"><?= $d['bulan'] ?></div>
</div>
<?php endforeach; ?>
</div>
</div>
<div class="court-section">
<div class="court-section-label"><i class="fa-solid fa-layer-group"></i> Pilih Lapangan</div>
<?php if (!empty($lapanganList)): ?>
<?php foreach ($lapanganList as $idx => $lap):
    $cId = $lap['ID_Lapangan'];
    $cName = htmlspecialchars($lap['Nama_Lapangan']);
    $cPrice = floatval($lap['Harga_Sewa']);
    $rawPhoto = $lap['Photo_Lapangan'] ?? '';
    $resolvedPhoto = resolvePhotoPath($rawPhoto);
    $img = !empty($resolvedPhoto) ? htmlspecialchars($resolvedPhoto) : '';
    $fasilitas = $lapanganFasilitas[$cId] ?? [];
?>
<div class="court-card" id="court-<?= $cId ?>" data-id="<?= $cId ?>" data-price="<?= $cPrice ?>" data-name="<?= $cName ?>" data-img="<?= $img ?>" onclick="selectCourt(<?= $cId ?>)">
<div class="court-img-wrap">
<?php if ($img): ?><img src="<?= $img ?>" alt="<?= $cName ?>" loading="lazy">
<?php else: ?><div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center"><i class="fa-solid fa-basketball" style="font-size:40px;color:var(--orange);opacity:0.4"></i></div><?php endif; ?>
<span class="court-img-badge">Tersedia</span>
</div>
<div class="court-body">
<div>
<div class="court-name"><?= $cName ?></div>
<div class="court-desc">Lapangan basket indoor dengan fasilitas lengkap dan pencahayaan optimal untuk pengalaman bermain terbaik.</div>
<div class="court-meta">
<?php foreach (array_slice($fasilitas, 0, 3) as $f): ?>
<div class="court-meta-item"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($f) ?></div>
<?php endforeach; ?>
<?php if (empty($fasilitas)): ?>
<div class="court-meta-item"><i class="fa-solid fa-basketball"></i> Bola Standar</div>
<div class="court-meta-item"><i class="fa-solid fa-lightbulb"></i> Pencahayaan LED</div>
<div class="court-meta-item"><i class="fa-solid fa-wind"></i> AC</div>
<?php endif; ?>
</div>
</div>
<div class="court-footer">
<div class="court-price">Rp <?= number_format($cPrice, 0, ',', '.') ?> <span>/ jam</span></div>
<button class="court-btn" id="btn-court-<?= $cId ?>" onclick="event.stopPropagation();selectCourt(<?= $cId ?>)"><i class="fa-solid fa-calendar-check"></i> Pilih Jadwal</button>
</div>
</div>
</div>
<?php endforeach; ?>
<?php else: ?>
<div class="empty-state"><i class="fa-solid fa-inbox"></i><p>Tidak ada lapangan aktif saat ini.</p></div>
<?php endif; ?>
</div>
<div class="slots-section" id="slotsSection">
<div class="slots-header">
<div>
<div class="slots-title" id="slotsTitle">Pilih Jam Bermain</div>
<div class="slots-subtitle" id="slotsSubtitle">Pilih durasi dan slot yang tersedia</div>
</div>
<div class="duration-toggle">
<button class="duration-btn active" data-duration="1" onclick="setDuration(1)">1 Jam</button>
<button class="duration-btn" data-duration="2" onclick="setDuration(2)">2 Jam</button>
<button class="duration-btn" data-duration="3" onclick="setDuration(3)">3 Jam</button>
</div>
</div>
<div class="slots-grid" id="slotsGrid"></div>
</div>
</div>
<div class="summary-bar" id="summaryBar">
<div class="summary-info">
<div class="summary-info-label">Booking Detail</div>
<div class="summary-info-value" id="summaryDetail">-</div>
</div>
<div class="summary-price">
<div class="summary-price-label">Total</div>
<div class="summary-price-value" id="summaryTotal">Rp 0</div>
</div>
<button class="summary-btn" onclick="openBookingModal()"><i class="fa-solid fa-arrow-right"></i> Lanjutkan</button>
</div>
<div class="modal-overlay" id="bookingModal">
<div class="modal-card">
<div class="modal-header">
<div class="modal-title">Ringkasan Booking</div>
<button class="modal-close" onclick="closeModal('bookingModal')"><i class="fa-solid fa-xmark"></i></button>
</div>
<div class="modal-body">
<div class="detail-row"><span class="detail-label">Lapangan</span><span class="detail-value" id="modalCourt">-</span></div>
<div class="detail-row"><span class="detail-label">Tanggal</span><span class="detail-value" id="modalDate">-</span></div>
<div class="detail-row"><span class="detail-label">Waktu</span><span class="detail-value" id="modalTime">-</span></div>
<div class="detail-row"><span class="detail-label">Durasi</span><span class="detail-value" id="modalDuration">-</span></div>
<div class="detail-row"><span class="detail-label">Harga Sewa</span><span class="detail-value price" id="modalBasePrice">Rp 0</span></div>
<?php if ($has_member): ?>
<div class="detail-row"><span class="detail-label">Diskon Member (<?= htmlspecialchars($member_tipe) ?>)</span><span class="detail-value discount" id="modalDiscount">-Rp <?= number_format($member_discount, 0, ',', '.') ?></span></div>
<div class="promo-section"><div class="promo-locked"><i class="fa-solid fa-lock"></i> Promo tidak dapat digunakan karena member aktif</div></div>
<?php else: ?>
<div class="detail-row"><span class="detail-label">Potongan Promo</span><span class="detail-value discount" id="modalPromoDiscount">-Rp 0</span></div>
<div class="promo-section">
<div class="promo-label">Gunakan Promo</div>
<select class="promo-select" id="modalPromoSelect">
<option value="0" data-discount="0">-- Pilih Promo --</option>
<?php foreach ($promos as $p): ?>
<option value="<?= $p['ID_Promo'] ?>" data-discount="<?= floatval($p['Diskon']) ?>"><?= htmlspecialchars($p['Nama_Promo']) ?> (-Rp <?= number_format($p['Diskon'], 0, ',', '.') ?>)</option>
<?php endforeach; ?>
</select>
</div>
<?php endif; ?>
<div class="detail-total"><span class="detail-total-label">Total Pembayaran</span><span class="detail-total-value" id="modalTotal">Rp 0</span></div>
<div style="margin-top:20px">
<div class="promo-label">Metode Pembayaran</div>
<div class="payment-options">
<div class="payment-option selected" data-method="Transfer Bank" onclick="selectPayment(this)">
<div class="payment-radio"></div>
<div class="payment-icon"><i class="fa-solid fa-building-columns"></i></div>
<div class="payment-info"><div class="payment-name">Transfer Bank</div><div class="payment-desc">Virtual Account</div></div>
</div>
<div class="payment-option" data-method="QRIS" onclick="selectPayment(this)">
<div class="payment-radio"></div>
<div class="payment-icon"><i class="fa-solid fa-qrcode"></i></div>
<div class="payment-info"><div class="payment-name">QRIS</div><div class="payment-desc">Scan & Bayar</div></div>
</div>
</div>
</div>
</div>
<div class="modal-footer">
<button class="btn-secondary" onclick="closeModal('bookingModal')">Batal</button>
<button class="btn-primary btn-full" id="btnConfirmBooking" onclick="confirmBooking()"><i class="fa-solid fa-lock"></i> Bayar Sekarang</button>
</div>
</div>
</div>
<div class="modal-overlay" id="paymentModal">
<div class="modal-card">
<div class="modal-header">
<div class="modal-title">Instruksi Pembayaran</div>
<button class="modal-close" onclick="closeModal('paymentModal')"><i class="fa-solid fa-xmark"></i></button>
</div>
<div class="modal-body">
<div class="countdown-box"><i class="fa-solid fa-clock"></i><span class="countdown-text">Selesaikan dalam <span id="countdown">15:00</span></span></div>
<div class="total-box"><div class="total-label">Total Tagihan</div><div class="total-amount" id="paymentTotal">Rp 0</div></div>
<div class="payment-instr-tabs">
<button class="instr-tab active" id="tabVA" onclick="showPaymentTab('va')"><i class="fa-solid fa-university"></i> Virtual Account</button>
<button class="instr-tab" id="tabQRIS" onclick="showPaymentTab('qris')"><i class="fa-solid fa-qrcode"></i> QRIS</button>
</div>
<div id="instrVA">
<div class="va-box"><div class="va-label">Nomor Virtual Account</div><div class="va-number" id="vaNumber">8801281234567890</div><button class="va-copy-btn" onclick="copyVA()"><i class="fa-regular fa-copy"></i> Salin Nomor</button></div>
<div style="font-size:12px;color:var(--text-secondary);line-height:1.8">
<p style="margin-bottom:8px;font-weight:700;color:var(--dark)">Cara Pembayaran:</p>
<p>1. Buka aplikasi M-Banking atau ATM Anda</p>
<p>2. Pilih menu <strong>Transfer > Virtual Account</strong></p>
<p>3. Masukkan nomor VA di atas</p>
<p>4. Konfirmasi pembayaran</p>
</div>
</div>
<div id="instrQRIS" style="display:none">
<div class="qris-box">
<img id="qrisImage" src="" alt="QRIS Code" class="qris-img">
<p style="font-size:12px;color:var(--text-secondary);line-height:1.6">Buka aplikasi e-wallet (GoPay, OVO, Dana, LinkAja) atau Mobile Banking,<br>pilih scan QRIS, dan arahkan kamera ke kode di atas.</p>
</div>
</div>
</div>
<div class="modal-footer">
<button class="btn-primary btn-full" onclick="finishPayment()"><i class="fa-solid fa-circle-check"></i> Saya Sudah Bayar</button>
</div>
</div>
</div>
<script>
let selectedDate = '<?= $dateList[0]['value'] ?>';
let selectedCourtId = null, selectedCourtPrice = 0, selectedCourtName = '', selectedCourtImg = '';
let selectedDuration = 1;
let selectedSlots = [];
let selectedSlotTime = '';
let selectedPaymentMethod = 'Transfer Bank';
let isMember = <?= $has_member ? 'true' : 'false' ?>;
let memberDiscount = <?= $member_discount ?>;
let countdownInterval;

function formatRupiah(n) {
    return 'Rp ' + Math.max(0, n).toLocaleString('id-ID');
}

function selectDate(el, dateVal) {
    document.querySelectorAll('.date-chip').forEach(c => c.classList.remove('active'));
    el.classList.add('active');
    selectedDate = dateVal;
    if (selectedCourtId) loadSlots(selectedCourtId, selectedDate);
}

function selectCourt(courtId) {
    document.querySelectorAll('.court-card').forEach(c => c.classList.remove('selected'));
    document.querySelectorAll('.court-btn').forEach(b => { b.innerHTML = '<i class="fa-solid fa-calendar-check"></i> Pilih Jadwal'; });
    const card = document.getElementById('court-' + courtId);
    card.classList.add('selected');
    document.getElementById('btn-court-' + courtId).innerHTML = '<i class="fa-solid fa-check"></i> Terpilih';
    selectedCourtId = courtId;
    selectedCourtPrice = parseFloat(card.dataset.price);
    selectedCourtName = card.dataset.name;
    selectedCourtImg = card.dataset.img;
    const slotsSection = document.getElementById('slotsSection');
    slotsSection.classList.add('active');
    document.getElementById('slotsTitle').innerText = 'Pilih Jam - ' + selectedCourtName;
    slotsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
    loadSlots(courtId, selectedDate);
}

function loadSlots(courtId, tanggal) {
    const grid = document.getElementById('slotsGrid');
    grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><div class="loading-spinner" style="border-color:var(--border-color);border-top-color:var(--orange)"></div><p style="margin-top:12px">Memuat jadwal...</p></div>';
    selectedSlots = [];
    updateSummaryBar();
    const endpoint = selectedDuration === 3
        ? `booking_customer.php?action=get_3h_blocks&court_id=${courtId}&tanggal=${tanggal}`
        : `booking_customer.php?action=get_slots&court_id=${courtId}&tanggal=${tanggal}`;
    fetch(endpoint)
        .then(r => r.json())
        .then(data => { renderSlots(data); })
        .catch(() => {
            grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><i class="fa-solid fa-triangle-exclamation"></i><p>Gagal memuat jadwal. Silakan coba lagi.</p></div>';
        });
}

function renderSlots(slots) {
    const grid = document.getElementById('slotsGrid');
    if (slots.length === 0) {
        grid.innerHTML = '<div class="empty-state" style="grid-column:1/-1"><i class="fa-solid fa-calendar-xmark"></i><p>Tidak ada slot tersedia untuk tanggal ini.</p></div>';
        return;
    }
    grid.innerHTML = '';
    slots.forEach((slot, idx) => {
        const el = document.createElement('div');
        el.className = 'slot-item';
        el.dataset.index = idx;
        let timeLabel, priceLabel;
        if (selectedDuration === 3 && slot.Jam_Mulai && slot.Jam_Selesai) {
            timeLabel = `${slot.Jam_Mulai} - ${slot.Jam_Selesai}`;
            priceLabel = formatRupiah(selectedCourtPrice * 3);
        } else {
            timeLabel = `${slot.Jam_Mulai} - ${slot.Jam_Selesai}`;
            priceLabel = formatRupiah(selectedCourtPrice * selectedDuration);
        }
        el.innerHTML = `<div class="slot-time">${timeLabel}</div><div class="slot-status available">Tersedia</div><div class="slot-price">${priceLabel}</div>`;
        el.addEventListener('click', () => selectSlot(idx, slot));
        grid.appendChild(el);
    });
}

function setDuration(dur) {
    selectedDuration = dur;
    document.querySelectorAll('.duration-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`.duration-btn[data-duration="${dur}"]`).classList.add('active');
    if (selectedCourtId) loadSlots(selectedCourtId, selectedDate);
}

function selectSlot(index, slot) {
    document.querySelectorAll('.slot-item').forEach(s => s.classList.remove('selected'));
    const items = document.querySelectorAll('.slot-item');
    if (items[index]) items[index].classList.add('selected');
    if (selectedDuration === 3 && slot.ID_Jadwal_1) {
        selectedSlots = [slot.ID_Jadwal_1, slot.ID_Jadwal_2, slot.ID_Jadwal_3];
        selectedSlotTime = `${slot.Jam_Mulai} - ${slot.Jam_Selesai}`;
    } else {
        selectedSlots = [slot.ID_Jadwal];
        selectedSlotTime = `${slot.Jam_Mulai} - ${slot.Jam_Selesai}`;
    }
    updateSummaryBar();
}

function updateSummaryBar() {
    const bar = document.getElementById('summaryBar');
    if (selectedSlots.length === 0) { bar.classList.remove('active'); return; }
    bar.classList.add('active');
    const basePrice = selectedCourtPrice * selectedDuration;
    let discount = 0;
    if (isMember) discount = memberDiscount;
    const total = Math.max(0, basePrice - discount);
    document.getElementById('summaryDetail').innerText = `${selectedCourtName} | ${selectedSlotTime} | ${selectedDuration} jam`;
    document.getElementById('summaryTotal').innerText = formatRupiah(total);
}

function openModal(id) {
    document.getElementById(id).classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
    document.body.style.overflow = '';
    if (id === 'paymentModal') clearInterval(countdownInterval);
}

document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function (e) { if (e.target === this) closeModal(this.id); });
});

function openBookingModal() {
    if (selectedSlots.length === 0) return;
    const basePrice = selectedCourtPrice * selectedDuration;
    let discount = 0;
    if (isMember) discount = memberDiscount;
    const total = Math.max(0, basePrice - discount);
    const dateChip = document.querySelector('.date-chip.active');
    const dateText = dateChip
        ? `${dateChip.querySelector('.day-name').innerText}, ${dateChip.querySelector('.day-num').innerText} ${dateChip.querySelector('.month-name').innerText}`
        : selectedDate;
    document.getElementById('modalCourt').innerText = selectedCourtName;
    document.getElementById('modalDate').innerText = dateText;
    document.getElementById('modalTime').innerText = selectedSlotTime;
    document.getElementById('modalDuration').innerText = selectedDuration + ' Jam';
    document.getElementById('modalBasePrice').innerText = formatRupiah(basePrice);
    if (!isMember) document.getElementById('modalPromoDiscount').innerText = '-Rp 0';
    document.getElementById('modalTotal').innerText = formatRupiah(total);
    openModal('bookingModal');
}

function selectPayment(el) {
    document.querySelectorAll('.payment-option').forEach(p => p.classList.remove('selected'));
    el.classList.add('selected');
    selectedPaymentMethod = el.dataset.method;
}

const promoSelect = document.getElementById('modalPromoSelect');
if (promoSelect) {
    promoSelect.addEventListener('change', function () {
        const opt = this.options[this.selectedIndex];
        const discount = parseFloat(opt.getAttribute('data-discount') || 0);
        const basePrice = selectedCourtPrice * selectedDuration;
        const total = Math.max(0, basePrice - discount);
        document.getElementById('modalPromoDiscount').innerText = '-Rp ' + discount.toLocaleString('id-ID');
        document.getElementById('modalTotal').innerText = formatRupiah(total);
    });
}

function confirmBooking() {
    const basePrice = selectedCourtPrice * selectedDuration;
    let discount = 0, idPromo = null;
    if (isMember) {
        discount = memberDiscount;
    } else if (promoSelect) {
        const opt = promoSelect.options[promoSelect.selectedIndex];
        if (opt && opt.value !== '0') {
            discount = parseFloat(opt.getAttribute('data-discount') || 0);
            idPromo = opt.value;
        }
    }
    const total = Math.max(0, basePrice - discount);
    closeModal('bookingModal');
    document.getElementById('paymentTotal').innerText = formatRupiah(total);
    showPaymentTab('va');
    openModal('paymentModal');
    startCountdown(15 * 60);
}

function showPaymentTab(tab) {
    document.getElementById('instrVA').style.display = tab === 'va' ? 'block' : 'none';
    document.getElementById('instrQRIS').style.display = tab === 'qris' ? 'block' : 'none';
    document.getElementById('tabVA').classList.toggle('active', tab === 'va');
    document.getElementById('tabQRIS').classList.toggle('active', tab === 'qris');
    if (tab === 'qris') {
        const totalText = document.getElementById('paymentTotal').innerText;
        const totalNum = parseInt(totalText.replace(/[^0-9]/g, ''));
        document.getElementById('qrisImage').src = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent('HOOPBALL-PAYMENT-' + selectedSlots[0] + '-' + totalNum)}`;
    }
}

function startCountdown(seconds) {
    clearInterval(countdownInterval);
    let remaining = seconds;
    const display = document.getElementById('countdown');
    countdownInterval = setInterval(() => {
        const m = String(Math.floor(remaining / 60)).padStart(2, '0');
        const s = String(remaining % 60).padStart(2, '0');
        display.innerText = `${m}:${s}`;
        if (--remaining < 0) { clearInterval(countdownInterval); display.innerText = 'Waktu Habis'; }
    }, 1000);
}

function copyVA() {
    const vaNum = document.getElementById('vaNumber').innerText;
    navigator.clipboard.writeText(vaNum).then(() => {
        Swal.fire({ icon: 'success', title: 'Berhasil Disalin!', text: 'Nomor VA telah disalin ke clipboard.', confirmButtonColor: 'var(--orange)', confirmButtonText: 'OK' });
    });
}

function finishPayment() {
    clearInterval(countdownInterval);
    closeModal('paymentModal');
    const basePrice = selectedCourtPrice * selectedDuration;
    let discount = 0, idPromo = null;
    if (isMember) {
        discount = memberDiscount;
    } else if (promoSelect) {
        const opt = promoSelect.options[promoSelect.selectedIndex];
        if (opt && opt.value !== '0') {
            discount = parseFloat(opt.getAttribute('data-discount') || 0);
            idPromo = opt.value;
        }
    }
    const total = Math.max(0, basePrice - discount);
    Swal.fire({ title: 'Memproses...', text: 'Sedang memverifikasi pembayaran Anda', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => { Swal.showLoading(); } });
    fetch('booking_customer.php?action=checkout', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id_jadwal_list: selectedSlots, id_promo: idPromo, metode_pembayaran: selectedPaymentMethod, total_bayar: total, duration: selectedDuration })
    })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                Swal.fire({ icon: 'success', title: 'Booking Berhasil!', text: 'Pembayaran Anda sedang diverifikasi. Silakan cek riwayat booking di profil Anda.', confirmButtonColor: 'var(--orange)', confirmButtonText: 'Selesai' })
                    .then(() => { location.reload(); });
            } else {
                Swal.fire({ icon: 'warning', title: 'Booking Gagal', text: result.message, confirmButtonColor: 'var(--orange)', confirmButtonText: 'Pilih Ulang' });
            }
        })
        .catch(() => {
            Swal.fire({ icon: 'error', title: 'Koneksi Terputus', text: 'Gagal terhubung ke server. Periksa koneksi internet Anda.', confirmButtonColor: 'var(--orange)', confirmButtonText: 'Coba Lagi' });
        });
}

const urlParams = new URLSearchParams(window.location.search);
const status = urlParams.get('status'), msg = urlParams.get('msg');
if (status && msg) {
    const ok = status === 'success';
    Swal.fire({ icon: ok ? 'success' : 'error', title: ok ? 'Berhasil' : 'Gagal', text: msg, confirmButtonColor: 'var(--orange)', confirmButtonText: 'OK' });
    window.history.replaceState({}, document.title, window.location.pathname);
}
</script>
</body>
</html>