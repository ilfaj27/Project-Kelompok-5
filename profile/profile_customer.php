<?php
// ============================================================================
// AJAX HANDLER — Cek Username Duplikat (inline, tanpa file terpisah)
// ============================================================================
if (isset($_GET['ajax_check_username']) && $_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists('../includes/config.php')) {
        include '../includes/config.php';
    } elseif (file_exists('../includes/config.php')) {
        include '../includes/config.php';
    } else {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Config file tidak ditemukan!']);
        exit();
    }

    header('Content-Type: application/json');

    $check_username = isset($_GET['username']) ? trim($_GET['username']) : '';
    $exclude_id = isset($_GET['exclude']) ? trim($_GET['exclude']) : '';

    if (empty($check_username) || strlen($check_username) < 3) {
        echo json_encode(['exists' => false, 'valid' => false, 'message' => 'Username minimal 3 karakter']);
        exit();
    }

    if (!preg_match('/^[a-zA-Z0-9_]+$/', $check_username)) {
        echo json_encode(['exists' => false, 'valid' => false, 'message' => 'Username hanya boleh huruf, angka, dan underscore']);
        exit();
    }

    $params = array($check_username);
    $sql = "SELECT ID_Customer FROM Customer WHERE Username = ? AND Is_Deleted = 0";

    if (!empty($exclude_id)) {
        $sql .= " AND ID_Customer != ?";
        $params[] = $exclude_id;
    }

    $cek = sqlsrv_query($conn, $sql, $params);

    if ($cek === false) {
        echo json_encode(['exists' => false, 'valid' => false, 'message' => 'Database error']);
        exit();
    }

    $exists = sqlsrv_fetch_array($cek, SQLSRV_FETCH_ASSOC) ? true : false;
    sqlsrv_free_stmt($cek);

    echo json_encode([
        'exists' => $exists,
        'valid' => !$exists,
        'message' => $exists ? 'Nama Pengguna sudah digunakan oleh customer lain.' : 'Nama Pengguna tersedia.'
    ]);
    exit();
}

session_start();

include '../includes/auth_helper.php';
cek_akses('customer');

$ID_Customer = $_SESSION['id_customer'] ?? $_SESSION['ID_Customer'] ?? $_SESSION['id_akun'] ?? $_SESSION['ID_Akun'] ?? '';
if (empty($ID_Customer)) {
    header("Location: ../login/login.php");
    exit();
}

if (file_exists('../includes/config.php')) {
    include '../includes/config.php';
} elseif (file_exists('../includes/config.php')) {
    include '../includes/config.php';
} else {
    die("Config file tidak ditemukan!");
}

$swal_status = '';
$swal_msg = '';
$pass_error_field = $_SESSION['pass_error_field'] ?? ''; 
if (!empty($pass_error_field)) {
    unset($_SESSION['pass_error_field']); 
}

// ============================================================================
// UPDATE BIODATA
// ============================================================================
if (isset($_POST['update_biodata'])) {
    $nama = trim($_POST['nama_customer'] ?? '');
    $jk = intval($_POST['jenis_kelamin'] ?? 0);
    $alamat = trim($_POST['alamat'] ?? '');
    $telepon = trim($_POST['no_telepon'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $tgl_lahir = $_POST['tanggal_lahir'] ?? '';
    $tmp_lahir = $_POST['tempat_lahir'] ?? '';
    $username_input = trim($_POST['username'] ?? '');

    $errors = [];
    if (empty($nama) || strlen($nama) < 3 || !preg_match('/^[a-zA-Z\s]+$/', $nama)) {
        $errors[] = 'Nama minimal 3 karakter, hanya huruf dan spasi.';
    }
    if (empty($username_input) || strlen($username_input) < 3 || !preg_match('/^[a-zA-Z0-9_]+$/', $username_input)) {
        $errors[] = 'Nama Pengguna minimal 3 karakter, hanya huruf, angka, dan _.';
    }
    if (empty($tgl_lahir)) {
        $errors[] = 'Tanggal lahir wajib diisi.';
    } else {
        $birthDate = new DateTime($tgl_lahir);
        $today = new DateTime();
        $age = $today->diff($birthDate)->y;
        if ($age < 10) {
            $errors[] = 'Usia minimal 10 tahun.';
        } elseif ($age > 100) {
            $errors[] = 'Tanggal lahir tidak valid.';
        }
    }
    if (empty($tmp_lahir) || strlen($tmp_lahir) < 3 || !preg_match('/^[a-zA-Z\s]+$/', $tmp_lahir)) {
        $errors[] = 'Kota minimal 3 karakter, hanya huruf dan spasi.';
    }
    $alamat_trim = trim($alamat);
    $alamat_normalized = preg_replace('/\s+/', ' ', $alamat_trim);
    $alamat_words = array_filter(explode(' ', $alamat_normalized), function($w) { return strlen(trim($w)) > 0; });
    $word_count = count($alamat_words);
    if (empty($alamat_trim)) {
        $errors[] = 'Alamat tidak boleh kosong.';
    } elseif ($word_count < 3) {
        $errors[] = 'Alamat minimal 3 kata.';
    }
    if (empty($telepon) || !preg_match('/^08[0-9]{8,12}$/', $telepon)) {
        $errors[] = 'Nomor telepon harus berawal 08 dan 10-13 digit angka.';
    }
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Email tidak valid.';
    }

    if (empty($errors)) {
        $cek_username = sqlsrv_query($conn,
            "SELECT ID_Customer FROM Customer WHERE Username = ? AND ID_Customer != ? AND Is_Deleted = 0",
            array($username_input, $ID_Customer)
        );
        if ($cek_username && sqlsrv_fetch_array($cek_username, SQLSRV_FETCH_ASSOC)) {
            $errors[] = 'Nama Pengguna sudah digunakan oleh customer lain.';
        }
        $cek_email = sqlsrv_query($conn,
            "SELECT ID_Customer FROM Customer WHERE Email = ? AND ID_Customer != ? AND Is_Deleted = 0",
            array($email, $ID_Customer)
        );
        if ($cek_email && sqlsrv_fetch_array($cek_email, SQLSRV_FETCH_ASSOC)) {
            $errors[] = 'Email sudah digunakan oleh customer lain.';
        }
        $cek_telp = sqlsrv_query($conn,
            "SELECT ID_Customer FROM Customer WHERE No_Telepon = ? AND ID_Customer != ? AND Is_Deleted = 0",
            array($telepon, $ID_Customer)
        );
        if ($cek_telp && sqlsrv_fetch_array($cek_telp, SQLSRV_FETCH_ASSOC)) {
            $errors[] = 'Nomor telepon sudah digunakan oleh customer lain.';
        }
    }

    if (empty($errors)) {
        $modified_by = $_SESSION['nama'] ?? 'SYSTEM';
        $stmt = sqlsrv_query($conn,
            "UPDATE Customer SET
                Nama_Customer = ?,
                Username = ?,
                Jenis_Kelamin = ?,
                Tanggal_Lahir = ?,
                Tempat_Lahir = ?,
                Alamat = ?,
                No_Telepon = ?,
                Email = ?,
                Modified_By = ?,
                Modified_Date = GETDATE()
             WHERE ID_Customer = ?",
            array($nama, $username_input, $jk, $tgl_lahir, $tmp_lahir, $alamat, $telepon, $email, $modified_by, $ID_Customer)
        );
        if ($stmt) {
            while (sqlsrv_next_result($stmt)) {}
            sqlsrv_free_stmt($stmt);
            $_SESSION['nama'] = $nama;
            $_SESSION['nama_user'] = $nama;
            session_write_close();
            $swal_status = 'success';
            $swal_msg = 'Biodata berhasil diperbarui!';
        } else {
            $swal_status = 'error';
            $swal_msg = 'Gagal memperbarui biodata.';
        }
    } else {
        $swal_status = 'error';
        $swal_msg = implode("\n", $errors);
    }
}

// ============================================================================
// UPDATE PASSWORD
// ============================================================================
if (isset($_POST['update_password'])) {
    $old_pass = trim($_POST['old_password'] ?? '');
    $new_pass = trim($_POST['new_password'] ?? '');
    $confirm_pass = trim($_POST['confirm_password'] ?? '');

    $res = sqlsrv_query($conn, "SELECT Kata_Sandi FROM Customer WHERE ID_Customer = ?", array($ID_Customer));
    $custData = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);

    if ($old_pass !== ($custData['Kata_Sandi'] ?? '')) {
        $swal_status = 'error';
        $swal_msg = 'Kata Sandi lama tidak sesuai.';
        $_SESSION['pass_error_field'] = 'old_password';
    } elseif (strlen($new_pass) < 8) {
        $swal_status = 'error';
        $swal_msg = 'Kata Sandi baru minimal 8 karakter.';
        $_SESSION['pass_error_field'] = 'new_password';
    } elseif ($new_pass !== $confirm_pass) {
        $swal_status = 'error';
        $swal_msg = 'Konfirmasi kata sandi tidak cocok.';
        $_SESSION['pass_error_field'] = 'confirm_password';
    } else {
        $modified_by = $_SESSION['nama'] ?? 'SYSTEM';
        $stmt = sqlsrv_query($conn,
            "UPDATE Customer SET Kata_Sandi = ?, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Customer = ?",
            array($new_pass, $modified_by, $ID_Customer)
        );
        if ($stmt) {
            while (sqlsrv_next_result($stmt)) {}
            sqlsrv_free_stmt($stmt);
            $swal_status = 'success';
            $swal_msg = 'Kata Sandi berhasil diperbarui!';
            unset($_SESSION['pass_error_field']);
        } else {
            $swal_status = 'error';
            $swal_msg = 'Gagal memperbarui kata sandi.';
        }
    }
}

// ============================================================================
// DELETE AKUN (SOFT DELETE)
// ============================================================================
if (isset($_POST['delete_account'])) {
    $confirm_password = trim($_POST['confirm_delete_password'] ?? '');

    // Ambil kata sandi aktif untuk verifikasi keamanan
    $res = sqlsrv_query($conn, "SELECT Kata_Sandi FROM Customer WHERE ID_Customer = ? AND Is_Deleted = 0", array($ID_Customer));
    $custData = sqlsrv_fetch_array($res, SQLSRV_FETCH_ASSOC);

    if (empty($confirm_password)) {
        $swal_status = 'error';
        $swal_msg = 'Kata sandi konfirmasi tidak boleh kosong.';
    } elseif ($confirm_password !== ($custData['Kata_Sandi'] ?? '')) {
        $swal_status = 'error';
        $swal_msg = 'Kata sandi konfirmasi yang Anda masukkan salah.';
    } else {
        $modified_by = $_SESSION['nama'] ?? 'SYSTEM';
        $stmt = sqlsrv_query($conn,
            "UPDATE Customer SET Is_Deleted = 1, Modified_By = ?, Modified_Date = GETDATE() WHERE ID_Customer = ?",
            array($modified_by, $ID_Customer)
        );

        if ($stmt) {
            while (sqlsrv_next_result($stmt)) {}
            sqlsrv_free_stmt($stmt);
            
            // Hancurkan session dan arahkan kembali ke login
            session_unset();
            session_destroy();
            header("Location: ../login/login.php?status=deleted");
            exit();
        } else {
            $swal_status = 'error';
            $swal_msg = 'Gagal menghapus akun. Silakan coba beberapa saat lagi.';
        }
    }
}

// ============================================================================
// UPLOAD FOTO
// ============================================================================
if (isset($_POST['update_photo']) && isset($_FILES['photo'])) {
    $file = $_FILES['photo'];
    $allowed = ['image/jpeg', 'image/png', 'image/jpg'];
    $max_size = 2 * 1024 * 1024;

    if ($file['error'] === 0) {
        if (in_array($file['type'], $allowed) && $file['size'] <= $max_size) {
            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $ID_Customer . '_' . time() . '.' . $ext;
            $upload_dir = 'uploads/profiles/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $upload_path = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                sqlsrv_query($conn, "UPDATE Customer SET Photo_Profile = ? WHERE ID_Customer = ?", array($upload_path, $ID_Customer));
                $_SESSION['Profile_Photo'] = $upload_path;
                $swal_status = 'success';
                $swal_msg = 'Foto profil berhasil diperbarui!';
            } else {
                $swal_status = 'error';
                $swal_msg = 'Gagal mengunggah foto.';
            }
        } else {
            $swal_status = 'error';
            $swal_msg = 'File harus JPG/PNG dan maksimal 2MB.';
        }
    }
}

// ============================================================================
// AMBIL DATA CUSTOMER DARI DATABASE
// ============================================================================
$res_cust = sqlsrv_query($conn, "SELECT * FROM Customer WHERE ID_Customer = ? AND Is_Deleted = 0", array($ID_Customer));
$biodata = sqlsrv_fetch_array($res_cust, SQLSRV_FETCH_ASSOC);

$profile_photo = $biodata['Photo_Profile'] ?? $_SESSION['Profile_Photo'] ?? '';
if (empty($profile_photo) || !file_exists($profile_photo)) {
    $profile_photo = '';
}

$nama = $biodata['Nama_Customer'] ?? $_SESSION['nama'] ?? 'Customer';
$username = $biodata['Username'] ?? '-';
$email = $biodata['Email'] ?? '-';
$telepon = $biodata['No_Telepon'] ?? '-';
$alamat = $biodata['Alamat'] ?? '-';
$jk = $biodata['Jenis_Kelamin'] ?? 0;
$tgl_lahir = $biodata['Tanggal_Lahir'] ?? '';
$tmp_lahir = $biodata['Tempat_Lahir'] ?? '';

// ============================================================================
// QUERY METRIK STATISTIK RILL SESUAI DATABASE HOOPBALL
// ============================================================================

// 1. Booking Selesai (Status = 2)
$sql_selesai = "SELECT COUNT(*) AS total FROM Booking WHERE ID_Customer = ? AND Status = 2";
$stmt_selesai = sqlsrv_query($conn, $sql_selesai, array($ID_Customer));
$count_selesai = 0;
if ($stmt_selesai && $row = sqlsrv_fetch_array($stmt_selesai, SQLSRV_FETCH_ASSOC)) {
    $count_selesai = intval($row['total']);
}

// 2. Booking Mendatang (Status = 1 / Berhasil & Tanggal >= Hari Ini)
$sql_mendatang = "SELECT COUNT(*) AS total FROM Booking B 
                  INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal 
                  WHERE B.ID_Customer = ? AND B.Status = 1 AND J.Tanggal >= CAST(GETDATE() AS DATE)";
$stmt_mendatang = sqlsrv_query($conn, $sql_mendatang, array($ID_Customer));
$count_mendatang = 0;
if ($stmt_mendatang && $row = sqlsrv_fetch_array($stmt_mendatang, SQLSRV_FETCH_ASSOC)) {
    $count_mendatang = intval($row['total']);
}

// 3. Pesanan Alat (Status = 1 dari pembelian alat sukses)
$sql_alat = "SELECT COUNT(*) AS total FROM Beli_Alat WHERE ID_Customer = ? AND Status = 1";
$stmt_alat = sqlsrv_query($conn, $sql_alat, array($ID_Customer));
$count_alat = 0;
if ($stmt_alat && $row = sqlsrv_fetch_array($stmt_alat, SQLSRV_FETCH_ASSOC)) {
    $count_alat = intval($row['total']);
}

// 4. Total Transaksi (Akumulasi Nilai Transaksi Selesai/Aktif dari Booking, Alat, & Langganan)
$sql_spend = "SELECT (
    ISNULL((SELECT SUM(Total_Bayar) FROM Booking WHERE ID_Customer = ? AND Status IN (1, 2)), 0) +
    ISNULL((SELECT SUM(Total_Bayar) FROM Beli_Alat WHERE ID_Customer = ? AND Status = 1), 0) +
    ISNULL((SELECT SUM(Total_Bayar) FROM Langganan WHERE ID_Customer = ? AND Status IN (1, 2)), 0)
) AS total_spending";
$stmt_spend = sqlsrv_query($conn, $sql_spend, array($ID_Customer, $ID_Customer, $ID_Customer));
$total_spending = 0;
if ($stmt_spend && $row = sqlsrv_fetch_array($stmt_spend, SQLSRV_FETCH_ASSOC)) {
    $total_spending = floatval($row['total_spending']);
}

// 5. Informasi Langganan Aktif (Masa Member)
$sql_member = "SELECT TOP 1 L.Tanggal_Selesai, T.Nama_Tipe 
               FROM Langganan L
               INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe
               WHERE L.ID_Customer = ? AND L.Status = 1 AND L.Tanggal_Selesai >= CAST(GETDATE() AS DATE)
               ORDER BY L.Tanggal_Selesai DESC";
$stmt_member = sqlsrv_query($conn, $sql_member, array($ID_Customer));
$has_member = false;
$member_tipe = 'Bukan Member'; // Diubah dari 'Regular' agar sesuai jika tidak memiliki langganan aktif
$member_expiry = null;
if ($stmt_member && $row = sqlsrv_fetch_array($stmt_member, SQLSRV_FETCH_ASSOC)) {
    $has_member = true;
    $member_tipe = $row['Nama_Tipe']; // Berisi 'Silver', 'Gold', atau 'Platinum' sesuai database
    $member_expiry = $row['Tanggal_Selesai']; 
}

// 6. Booking Berikutnya Riil
$sql_next_booking = "SELECT TOP 1 J.Tanggal, J.Jam_Mulai, J.Jam_Selesai, L.Nama_Lapangan, L.Harga_Sewa, B.Status
                     FROM Booking B
                     INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
                     INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
                     WHERE B.ID_Customer = ? AND B.Status = 1 AND J.Tanggal >= CAST(GETDATE() AS DATE)
                     ORDER BY J.Tanggal ASC, J.Jam_Mulai ASC";
$stmt_next_booking = sqlsrv_query($conn, $sql_next_booking, array($ID_Customer));
$next_booking = null;
if ($stmt_next_booking && $row = sqlsrv_fetch_array($stmt_next_booking, SQLSRV_FETCH_ASSOC)) {
    $next_booking = $row;
}

// ============================================================================
// QUERY DATA TRANSAKSI DETAIL SESUAI REQUEST USER & DATABASE
// ============================================================================

// 1. Riwayat Booking Lengkap
$bookings = [];
$sql_booking_list = "SELECT B.ID_Booking, B.Tanggal_Booking, B.Metode_Pembayaran, B.Total_Bayar, B.Status AS BookingStatus, 
                            J.Tanggal, J.Jam_Mulai, J.Jam_Selesai, L.Nama_Lapangan, L.Harga_Sewa, P.Nama_Promo, P.Diskon
                     FROM Booking B
                     INNER JOIN Jadwal J ON B.ID_Jadwal = J.ID_Jadwal
                     INNER JOIN Lapangan L ON J.ID_Lapangan = L.ID_Lapangan
                     LEFT JOIN Promo P ON B.ID_Promo = P.ID_Promo
                     WHERE B.ID_Customer = ?
                     ORDER BY J.Tanggal DESC, J.Jam_Mulai DESC";
$stmt_booking_list = sqlsrv_query($conn, $sql_booking_list, array($ID_Customer));
if ($stmt_booking_list) {
    while ($row = sqlsrv_fetch_array($stmt_booking_list, SQLSRV_FETCH_ASSOC)) {
        $bookings[] = $row;
    }
    sqlsrv_free_stmt($stmt_booking_list);
}

// 2. Riwayat Langganan Member Lengkap
$memberships = [];
$sql_member_list = "SELECT L.ID_Langganan, L.Tanggal_Mulai, L.Tanggal_Selesai, L.Total_Bayar, L.Metode_Pembayaran, L.Status AS MemberStatus,
                           T.Nama_Tipe, T.Harga_Member, T.Potongan_Harga
                    FROM Langganan L
                    INNER JOIN Tipe_Member T ON L.ID_Tipe = T.ID_Tipe
                    WHERE L.ID_Customer = ?
                    ORDER BY L.Tanggal_Mulai DESC";
$stmt_member_list = sqlsrv_query($conn, $sql_member_list, array($ID_Customer));
if ($stmt_member_list) {
    while ($row = sqlsrv_fetch_array($stmt_member_list, SQLSRV_FETCH_ASSOC)) {
        $memberships[] = $row;
    }
    sqlsrv_free_stmt($stmt_member_list);
}

// 3. Riwayat Pembelian Alat Lengkap beserta Sub Detail item
$purchases = [];
$sql_purchase_list = "SELECT BA.ID_Beli, BA.Tanggal_Beli, BA.Metode_Pembayaran, BA.Total_Bayar, BA.Status AS PurchaseStatus
                      FROM Beli_Alat BA
                      WHERE BA.ID_Customer = ?
                      ORDER BY BA.Tanggal_Beli DESC";
$stmt_purchase_list = sqlsrv_query($conn, $sql_purchase_list, array($ID_Customer));
if ($stmt_purchase_list) {
    while ($row = sqlsrv_fetch_array($stmt_purchase_list, SQLSRV_FETCH_ASSOC)) {
        $items = [];
        $sql_items = "SELECT D.Jumlah, D.SubTotal, A.Nama_Alat, A.Harga_Alat
                      FROM Detail_Beli_Alat D
                      INNER JOIN Alat A ON D.ID_Alat = A.ID_Alat
                      WHERE D.ID_Beli = ?";
        $stmt_items = sqlsrv_query($conn, $sql_items, array($row['ID_Beli']));
        if ($stmt_items) {
            while ($item = sqlsrv_fetch_array($stmt_items, SQLSRV_FETCH_ASSOC)) {
                $items[] = $item;
            }
            sqlsrv_free_stmt($stmt_items);
        }
        $row['items'] = $items;
        $purchases[] = $row;
    }
    sqlsrv_free_stmt($stmt_purchase_list);
}

function jk_label($jk) {
    return $jk == 1 ? 'Laki-laki' : ($jk == 0 ? 'Perempuan' : '-');
}

function format_date_input($date) {
    if (empty($date)) return '';
    if ($date instanceof DateTime) {
        return $date->format('Y-m-d');
    }
    return $date;
}

function format_date_display($date) {
    if (empty($date)) return '-';
    if ($date instanceof DateTime) {
        return $date->format('d F Y');
    }
    return $date;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil Saya | HoopBall</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --primary: #FF5200;
            --primary-hover: #E04800;
            --bg-light: #F8F9FA;
            --white: #FFFFFF;
            --dark-text: #1C1C1E;
            --muted-text: #636366;
            --border-color: #E5E5EA;
            --red: #FF3B30;
            --green: #34C759;
            --dark-bg: #0B0B0C;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: 'Plus Jakarta Sans', sans-serif; 
            background: var(--bg-light); 
            color: var(--dark-text); 
            overflow-x: hidden; 
            min-height: 100vh; 
        }

        /* ---- NAVBAR (PUTIH - TIDAK DIUBAH) ---- */
        nav {
            background: var(--white);
            padding: 0 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 76px;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid #E5E5EA;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.02);
        }
        .nav-logo {
            display: flex;
            align-items: center;
            text-decoration: none;
            gap: 10px;
        }
        .nav-logo img {
            height: 70px;
            width: auto;
        }
        .nav-logo span {
            color: var(--dark-text);
            font-size: 20px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nav-links a {
            color: #636366;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 20px;
            transition: all 0.2s ease;
        }
        .nav-links a:hover { 
            color: var(--dark-text); 
        }
        .nav-links a.active { 
            color: var(--primary); 
            font-weight: 600;
        }

        /* ---- USER DROPDOWN (Sesi Terintegrasi) ---- */
        .nav-user-container {
            position: relative;
            height: 76px;
            display: flex;
            align-items: center;
        }
        .nav-user {
            background: #F2F2F7;
            border: 1px solid #E5E5EA;
            padding: 8px 16px;
            border-radius: 50px;
            color: var(--dark-text);
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: 0.2s;
        }
        .nav-user:hover { 
            background: #E5E5EA; 
            border-color: var(--primary);
        }
        .nav-user img.user-avatar {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            object-fit: cover;
        }
        .nav-user i.user-icon {
            font-size: 16px;
            color: var(--primary);
        }
        .nav-user i.arrow { 
            font-size: 11px; 
            color: #8E8E93; 
            transition: 0.3s; 
        }
        .nav-user-container:hover i.arrow { 
            transform: rotate(180deg); 
            color: var(--primary); 
        }
        .dropdown-menu {
            position: absolute;
            top: 85%;
            right: 0;
            background: #16161a;
            min-width: 220px;
            border-radius: 12px;
            border: 1px solid #2d2d33;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            padding: 8px 0;
            display: none; 
            z-index: 1001;
            animation: fadeIn 0.2s ease-out;
        }
        .nav-user-container:hover .dropdown-menu {
            display: block;
        }
        .dropdown-menu .user-info-header {
            padding: 12px 20px;
            border-bottom: 1px solid #2d2d33;
            margin-bottom: 6px;
        }
        .dropdown-menu .user-info-header span {
            display: block;
        }
        .dropdown-menu .user-info-header .u-name {
            color: var(--white);
            font-size: 14px;
            font-weight: 700;
        }
        .dropdown-menu .user-info-header .u-role {
            color: #8E8E93;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
        }
        .dropdown-menu a {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 20px;
            color: #c5c5ca;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: 0.2s;
        }
        .dropdown-menu a i { 
            font-size: 14px; 
            width: 16px; 
            text-align: center; 
        }
        .dropdown-menu a:hover {
            background: #222227;
            color: var(--primary);
        }
        .dropdown-divider {
            height: 1px;
            background: #2d2d33;
            margin: 6px 0;
        }
        .dropdown-menu a.logout:hover { 
            color: #ff3b30; 
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* ---- HERO BANNER ---- */
        .hero-banner {
            background: linear-gradient(to right, rgba(0, 0, 0, 0.85), rgba(0, 0, 0, 0.4)), url('https://images.unsplash.com/photo-1546519638-68e109498ffc?q=80&w=1200&auto=format&fit=crop');
            background-size: cover;
            background-position: center;
            padding: 70px 80px 100px 80px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: var(--white);
            position: relative;
        }
        .hero-left h2 {
            font-size: 36px;
            font-weight: 800;
            color: var(--white);
            line-height: 1.1;
        }
        .hero-left h1 {
            font-size: 48px;
            font-weight: 800;
            color: var(--primary);
            margin-top: 5px;
            text-transform: none;
        }
        .hero-left p {
            color: #E5E5EA;
            font-size: 14px;
            margin-top: 15px;
            max-width: 500px;
        }

        /* CARD RINGKASAN AKUN */
        .ringkasan-akun-card {
            background: rgba(18, 18, 18, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            width: 380px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            backdrop-filter: blur(10px);
        }
        .ringkasan-title {
            font-size: 14px;
            font-weight: 700;
            color: #AEAEB2;
            margin-bottom: 16px;
        }
        .ringkasan-user {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 20px;
        }
        .ringkasan-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
            cursor: pointer;
            position: relative;
        }
        .ringkasan-info h4 {
            font-size: 16px;
            font-weight: 700;
            color: var(--white);
        }
        .ringkasan-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: var(--primary);
            color: var(--white);
            font-size: 11px;
            font-weight: 700;
            padding: 3px 10px;
            border-radius: 12px;
            margin-top: 4px;
        }
        .ringkasan-stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 16px;
            margin-bottom: 20px;
        }
        .r-stat-item {
            text-align: center;
        }
        .r-stat-header {
            font-size: 10px;
            color: #AEAEB2;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            margin-bottom: 4px;
        }
        .r-stat-header i {
            font-size: 11px;
        }
        .r-stat-val {
            font-size: 14px;
            font-weight: 800;
            color: var(--white);
        }
        .btn-edit-profil {
            background: var(--primary);
            color: var(--white);
            border: none;
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: 0.2s;
        }
        .btn-edit-profil:hover {
            background: var(--primary-hover);
        }

        /* ---- STATS ROW (KARTU INDIKATOR) ---- */
        .stats-indicator-container {
            max-width: 1440px;
            margin: -30px auto 40px auto;
            padding: 0 80px;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            position: relative;
            z-index: 10;
        }
        .stat-indicator-card {
            background: var(--white);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
        }
        .stat-ind-icon {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .stat-ind-icon.orange-icon {
            background: rgba(255, 82, 0, 0.1);
            color: var(--primary);
        }
        .stat-ind-info span {
            display: block;
        }
        .stat-ind-label {
            font-size: 12px;
            font-weight: 700;
            color: var(--muted-text);
        }
        .stat-ind-val {
            font-size: 20px;
            font-weight: 800;
            color: var(--dark-text);
            margin-top: 2px;
        }

        /* ---- LAYOUT KOTAK SEJAJAR (MENGHINDARI GAP VERTikal) ---- */
        .main-content {
            max-width: 1440px;
            margin: 0 auto;
            padding: 0 80px;
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        /* BARIS 1: Grid Kiri & Kanan yang Sejajar */
        .profile-row-1 {
            display: grid;
            grid-template-columns: 280px 1fr;
            gap: 30px;
            align-items: stretch; /* Memaksa kolom kiri & kanan memiliki tinggi yang sama */
        }

        /* SIDEBAR WRAPPER */
        .sidebar-aside-container {
            display: flex;
            flex-direction: column;
            gap: 20px;
            height: 100%;
        }

        /* MENU AKUN SIDEBAR */
        .sidebar-menu-card {
            background: var(--white);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.01);
            flex: 1; /* Fleksibel membagi ruang */
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .sidebar-menu-card h3 {
            font-size: 15px;
            font-weight: 800;
            color: var(--dark-text);
            margin-bottom: 12px;
        }
        .menu-list {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }
        .menu-btn {
            background: none;
            border: none;
            outline: none;
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 14px;
            color: var(--muted-text);
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            border-radius: 8px;
            cursor: pointer;
            transition: 0.2s;
            width: 100%;
            text-align: left;
        }
        .menu-btn:hover {
            background: #F2F2F7;
            color: var(--dark-text);
        }
        .menu-btn.active {
            background: #FFF0E6;
            color: var(--primary);
        }
        .menu-btn i {
            font-size: 15px;
            width: 18px;
            text-align: center;
        }
        .menu-btn-divider {
            height: 1px;
            background: var(--border-color);
            margin: 8px 0;
        }

        /* FORM CARDS (TINGGI DINAMIS / MENGIKUTI STRETCH BARIS 1) */
        .form-card {
            background: var(--white);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 32px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.01);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .form-card-title {
            font-size: 18px;
            font-weight: 800;
            color: var(--dark-text);
            margin-bottom: 24px;
        }

        /* FORM GROUPS */
        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        .form-group {
            margin-bottom: 18px;
        }
        .form-label {
            display: block;
            font-size: 12px;
            font-weight: 700;
            color: var(--dark-text);
            margin-bottom: 6px;
        }
        .form-label .required {
            color: var(--red);
        }
        .form-input {
            width: 100%;
            padding: 11px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 13px;
            color: var(--dark-text);
            font-family: inherit;
            outline: none;
            transition: 0.2s;
            background: var(--white);
        }
        .form-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(255, 82, 0, 0.1);
        }
        select.form-input {
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%238E8E93' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
        }

        /* ERROR MESSAGE & VALIDATION STATE STYLES */
        .error-msg {
            display: none;
            color: var(--red);
            font-size: 11px;
            font-weight: 600;
            margin-top: 6px;
        }
        .error-msg.show {
            display: block;
        }
        .form-input.error {
            border-color: var(--red) !important;
            background-color: #FFF5F5 !important;
        }
        .form-input.valid {
            border-color: var(--green) !important;
            background-color: #F4FBF6 !important;
        }

        /* BUTTONS */
        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 10px;
        }
        .btn-cancel {
            background: var(--white);
            color: var(--dark-text);
            border: 1px solid var(--border-color);
            padding: 11px 26px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-cancel:hover {
            background: #F2F2F7;
        }
        .btn-submit {
            background: var(--primary);
            color: var(--white);
            border: none;
            padding: 11px 26px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-submit:hover {
            background: var(--primary-hover);
        }

        /* ---- BARIS 2: LOWER PANEL GRID (BOOKING & MEMBER AKTIF) ---- */
        .lower-panel-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            align-items: stretch;
        }
        .lower-card {
            background: var(--white);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.01);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 180px;
        }
        .lower-card-title {
            font-size: 15px;
            font-weight: 800;
            color: var(--dark-text);
            margin-bottom: 16px;
        }
        
        /* BOOKING BERIKUTNYA */
        .booking-row {
            display: flex;
            gap: 16px;
            width: 100%;
        }
        .booking-img-wrapper {
            width: 130px;
            height: 90px;
            border-radius: 8px;
            overflow: hidden;
        }
        .booking-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .booking-details-box {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .b-title {
            font-size: 14px;
            font-weight: 800;
            color: var(--dark-text);
        }
        .b-meta-list {
            list-style: none;
            margin-top: 4px;
        }
        .b-meta-item {
            font-size: 12px;
            color: var(--muted-text);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 2px;
        }
        .b-meta-item i {
            color: #8E8E93;
            width: 14px;
        }
        .booking-actions-box {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: space-between;
        }

        /* MEMBER AKTIF */
        .member-row {
            display: flex;
            gap: 16px;
            align-items: center;
            width: 100%;
        }
        .member-badge-logo {
            background: #111112;
            color: #FF9500;
            border-radius: 8px;
            width: 76px;
            height: 76px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 10px;
            letter-spacing: 0.5px;
            border: 1px solid #FF9500;
        }
        .member-badge-logo i {
            font-size: 20px;
            margin-bottom: 4px;
        }
        .member-info-box {
            flex: 1;
        }
        .member-name-label {
            font-size: 14px;
            font-weight: 800;
            color: var(--dark-text);
        }
        .member-benefit-list {
            list-style: none;
            margin-top: 4px;
        }
        .member-benefit-item {
            font-size: 11px;
            color: var(--muted-text);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 2px;
        }
        .member-benefit-item i {
            color: var(--green);
            font-size: 10px;
        }
        .member-renew-box {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            justify-content: space-between;
            height: 76px;
        }

        /* ---- BARIS 3: AKTIVITAS TERBARU (FULL WIDTH) ---- */
        .activity-section-card {
            background: var(--white);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 24px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.01);
            width: 100%;
        }
        .activity-grid-3 {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
        }
        .activity-item-card {
            background: #FCFCFD;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            padding: 16px;
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .activity-item-icon {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            background: rgba(255, 82, 0, 0.1);
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }
        .activity-item-details {
            flex: 1;
        }

        /* UTILITY CLASSES */
        .badge-status-green {
            background: #E8F5E9;
            color: var(--green);
            font-size: 10px;
            font-weight: 700;
            padding: 4px 10px;
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        .btn-outline-detail {
            background: var(--white);
            border: 1px solid var(--primary);
            color: var(--primary);
            padding: 6px 14px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-outline-detail:hover {
            background: #FFF0E6;
        }
        .renew-expiry-label {
            font-size: 10px;
            color: var(--muted-text);
            text-align: right;
        }
        .renew-expiry-val {
            font-size: 12px;
            font-weight: 800;
            color: var(--primary);
        }
        .btn-solid-renew {
            background: var(--primary);
            color: var(--white);
            border: none;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-solid-renew:hover {
            background: var(--primary-hover);
        }
        .badge-act-status {
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 12px;
        }
        .badge-act-status.green {
            background: #E8F5E9;
            color: var(--green);
        }
        .badge-act-status.blue {
            background: #E3F2FD;
            color: #007AFF;
        }

        /* ---- FOOTER STYLES (DIPERBAIKI) ---- */
        footer {
            background: var(--dark-bg);
            color: var(--white);
            padding: 60px 80px 30px 80px;
            margin-top: 60px;
            border-top: 1px solid #2d2d33;
        }
        .footer-grid-4 {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr 2fr;
            gap: 40px;
            max-width: 1440px;
            margin: 0 auto;
        }
        .footer-brand h3 {
            font-size: 20px;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 16px;
            color: var(--white);
        }
        .footer-brand h3 img {
            height: 70px;
            width: auto;
        }
        .footer-brand p {
            color: #AEAEB2;
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 20px;
        }
        .social-row {
            display: flex;
            gap: 12px;
        }
        .social-circle {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #222227;
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            transition: 0.2s;
        }
        .social-circle:hover {
            background: var(--primary);
            color: var(--white);
        }
        .footer-col h4 {
            font-size: 14px;
            font-weight: 700;
            margin-bottom: 20px;
            color: var(--white);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .footer-links-list {
            list-style: none;
        }
        .footer-links-list li {
            margin-bottom: 12px;
        }
        .footer-links-list a {
            color: #AEAEB2;
            text-decoration: none;
            font-size: 13px;
            transition: 0.2s;
        }
        .footer-links-list a:hover {
            color: var(--primary);
        }
        .contact-item-box {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            margin-bottom: 14px;
            font-size: 13px;
            color: #AEAEB2;
        }
        .contact-item-box i {
            color: var(--primary);
            font-size: 14px;
            margin-top: 2px;
        }
        .footer-bottom-copyright {
            border-top: 1px solid #2d2d33;
            padding-top: 20px;
            margin-top: 40px;
            text-align: center;
            font-size: 12px;
            color: #8E8E93;
        }


/* ---- STYLE UNTUK TEKS AKTIVITAS TERBARU ---- */
.act-title {
    font-size: 13px;
    font-weight: 700;
    color: var(--dark-text);
    line-height: 1.4;
}

.act-subtitle {
    font-size: 11px;
    font-weight: 500;
    color: var(--muted-text);
    margin-top: 2px;
}

.act-time {
    font-size: 11px;
    font-weight: 500;
    color: #8E8E93;
    margin-top: 4px;
}

/* ---- STYLE UNTUK HISTORY CARDS (TABS BARU) ---- */
.list-container {
    display: flex;
    flex-direction: column;
    gap: 16px;
    width: 100%;
    margin-top: 10px;
}
.history-item {
    background: #FCFCFD;
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.history-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #F2F2F7;
    padding-bottom: 10px;
}
.history-date {
    font-size: 11px;
    color: var(--muted-text);
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 6px;
}
.history-body {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}
.h-details {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.h-title {
    font-size: 14px;
    font-weight: 800;
    color: var(--dark-text);
}
.h-meta {
    font-size: 12px;
    color: var(--muted-text);
    display: flex;
    align-items: center;
    gap: 6px;
}
.h-meta i {
    color: #8E8E93;
    width: 14px;
    text-align: center;
}
.text-primary {
    color: var(--primary) !important;
    font-weight: 600;
}
.h-price {
    text-align: right;
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.h-price span:first-child {
    font-size: 11px;
    color: var(--muted-text);
    font-weight: 600;
}
.price-val {
    font-size: 15px;
    font-weight: 800;
    color: var(--primary);
}
.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--muted-text);
    font-size: 13px;
}
.empty-state i {
    font-size: 36px;
    color: #AEAEB2;
    margin-bottom: 12px;
    display: block;
}

/* BADGES STATUS */
.badge-status-orange {
    background: #FFF9E6;
    color: #FF9500;
    font-size: 10px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 12px;
}
.badge-status-green {
    background: #E8F5E9;
    color: var(--green);
    font-size: 10px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 12px;
}
.badge-status-blue {
    background: #E3F2FD;
    color: #007AFF;
    font-size: 10px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 12px;
}
.badge-status-red {
    background: #FFEBEA;
    color: var(--red);
    font-size: 10px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 12px;
}
.badge-status-grey {
    background: #F2F2F7;
    color: #8E8E93;
    font-size: 10px;
    font-weight: 700;
    padding: 4px 10px;
    border-radius: 12px;
}
        /* RESPONSIVE DESIGN */
        @media(max-width: 991px) {
            .hero-banner { flex-direction: column; gap: 30px; padding: 40px 20px; }
            .stats-indicator-container { grid-template-columns: repeat(2, 1fr); padding: 0 20px; margin-top: -20px; }
            .profile-row-1 { grid-template-columns: 1fr; }
            .main-content { padding: 0 20px; }
            .lower-panel-grid { grid-template-columns: 1fr; }
            .activity-grid-3 { grid-template-columns: 1fr; }
            .footer-grid-4 { grid-template-columns: 1fr 1fr; gap: 30px; }
            footer { padding: 40px 20px; }
        }
        @media(max-width: 576px) {
            .footer-grid-4 { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- NAVBAR (PUTIH - TIDAK DIUBAH) -->
<nav>
    <a href="../dashboard/view_customer.php" class="nav-logo">
        <img src="../asset/image/logo2.png" alt="HoopBall">
    </a>
    <div class="nav-links">
        <a href="../dashboard/view_customer.php" class="active">Beranda</a>
        <a href="#">Booking</a>
        <a href="#">Jadwal</a>
        <a href="#">Member</a>
        <a href="#">Pembelian</a>
        <a href="#">Tentang</a>
        <a href="#">Kontak</a>
    </div>

    <div class="nav-user-container">
        <div class="nav-user">
            <?php if (!empty($profile_photo)): ?>
                <img src="<?php echo htmlspecialchars($profile_photo); ?>" alt="Avatar" class="user-avatar">
            <?php else: ?>
                <i class="fa-solid fa-circle-user user-icon"></i>
            <?php endif; ?>
            <span><?php echo htmlspecialchars($nama); ?></span>
            <i class="fa-solid fa-chevron-down arrow"></i>
        </div>
        <div class="dropdown-menu">
            <div class="user-info-header">
                <span class="u-name"><?php echo htmlspecialchars($nama); ?></span>
                <span class="u-role">Customer</span>
            </div>
            <a href="../profile/profile_customer.php"><i class="fa-solid fa-user"></i> Profil Saya</a>
            <a href="#"><i class="fa-solid fa-calendar-check"></i> Riwayat Booking</a>
            <a href="#"><i class="fa-solid fa-gear"></i> Pengaturan</a>
            <div class="dropdown-divider"></div>
            <a href="../login/logout.php" class="logout"><i class="fa-solid fa-right-from-bracket"></i> Keluar</a>
        </div>
    </div>
</nav>

<!-- HERO BANNER -->
<section class="hero-banner">
    <div class="hero-left">
        <h2>Profil Customer</h2>
        <h1>Kelola Akunmu</h1>
        <p>Lihat informasi akun, riwayat aktivitas, dan update data pribadi dengan mudah.</p>
    </div>

    <!-- Ringkasan Akun Card -->
    <div class="ringkasan-akun-card">
        <div class="ringkasan-title">Ringkasan Akun</div>
        
        <div class="ringkasan-user">
            <div class="photo-section" style="position: relative;">
                <form method="POST" enctype="multipart/form-data" id="photoForm">
                    <div class="photo-wrapper-ringkasan" onclick="document.getElementById('profilePhotoInput').click();" style="position: relative; width: 60px; height: 60px; border-radius: 50%; overflow: hidden; border: 2px solid var(--primary); cursor: pointer;">
                        <?php if ($profile_photo): ?>
                            <img src="<?= $profile_photo ?>" alt="Profile" style="width:100%; height:100%; object-fit:cover;">
                        <?php else: ?>
                            <div style="background:#FFF0E6; color:var(--primary); font-size:24px; font-weight:800; width:100%; height:100%; display:flex; align-items:center; justify-content:center;"><?= strtoupper(substr($nama, 0, 1)) ?></div>
                        <?php endif; ?>
                    </div>
                    <input type="file" name="photo" id="profilePhotoInput" class="photo-input" style="display:none;" accept="image/jpeg,image/png,image/jpg" onchange="document.getElementById('photoForm').submit();">
                    <input type="hidden" name="update_photo" value="1">
                </form>
            </div>
            <div class="ringkasan-info">
                <h4><?= htmlspecialchars($nama) ?></h4>
                <?php if ($has_member): ?>
                    <span class="ringkasan-badge"><i class="fa-solid fa-crown"></i> Member <?= htmlspecialchars($member_tipe) ?></span>
                <?php else: ?>
                    <span class="ringkasan-badge" style="background: #8e8e93;"><i class="fa-solid fa-user"></i> Bukan Member</span>
                <?php endif; ?>
            </div>
        </div> <!-- Penutup .ringkasan-user -->

        <div class="ringkasan-stats-grid">
            <div class="r-stat-item">
                <span class="r-stat-header"><i class="fa-regular fa-calendar" style="color: var(--primary);"></i> Booking</span>
                <span class="r-stat-val"><?= ($count_selesai + $count_mendatang) ?></span>
            </div>
            <div class="r-stat-item">
                <span class="r-stat-header"><i class="fa-solid fa-wallet" style="color: #FF9500;"></i> Transaksi</span>
                <span class="r-stat-val">Rp <?= number_format($total_spending, 0, ',', '.') ?></span>
            </div>
            <div class="r-stat-item">
                <span class="r-stat-header"><i class="fa-regular fa-clock" style="color: var(--green);"></i> Masa Member</span>
                <span class="r-stat-val" style="color: var(--green); font-size: 11px;">
                    <?= $has_member ? $member_expiry->format('d/m/Y') : 'Tidak Aktif' ?>
                </span>
            </div>
        </div>
        
        <button class="btn-edit-profil" onclick="document.getElementById('profilePhotoInput').click();">
            <i class="fa-solid fa-pencil"></i> Edit Profil
        </button>
    </div> <!-- Penutup .ringkasan-akun-card -->
</section> <!-- TAG PENUTUP </section> UNTUK .hero-banner YANG SEBELUMNYA TERHAPUS -->

<!-- STATS CARDS ROW (INDIKATOR ATAS) -->
<section class="stats-indicator-container">
    <div class="stat-indicator-card">
        <div class="stat-ind-icon orange-icon"><i class="fa-regular fa-calendar-check"></i></div>
        <div class="stat-ind-info">
            <span class="stat-ind-label">Booking Selesai</span>
            <span class="stat-ind-val"><?= $count_selesai ?></span>
        </div>
    </div>
    <div class="stat-indicator-card">
        <div class="stat-ind-icon orange-icon"><i class="fa-regular fa-clock"></i></div>
        <div class="stat-ind-info">
            <span class="stat-ind-label">Booking Mendatang</span>
            <span class="stat-ind-val"><?= $count_mendatang ?></span>
        </div>
    </div>
    <div class="stat-indicator-card">
        <div class="stat-ind-icon orange-icon"><i class="fa-solid fa-bag-shopping"></i></div>
        <div class="stat-ind-info">
            <span class="stat-ind-label">Pesanan Alat</span>
            <span class="stat-ind-val"><?= $count_alat ?></span>
        </div>
    </div>
    <div class="stat-indicator-card">
        <div class="stat-ind-icon orange-icon"><i class="fa-solid fa-wallet"></i></div>
        <div class="stat-ind-info">
            <span class="stat-ind-label">Total Transaksi</span>
            <span class="stat-ind-val" style="font-size: 15px; font-weight: 800; color: var(--dark-text);">
                Rp <?= number_format($total_spending, 0, ',', '.') ?>
            </span>
        </div>
    </div>
</section>

<!-- MAIN CONTENT LAYOUT -->
<main class="main-content">
    
    <!-- BARIS 1: SIDEBAR MENU & FORM BIODATA (TINGGI SEJAJAR) -->
    <div class="profile-row-1">
        
        <!-- SISI KIRI: SIDEBAR KELOMPOK (Menu Navigasi & Detail Akun Pendukung) -->
        <aside class="sidebar-aside-container">
            
            <!-- Menu Akun (Bagian Atas) -->
            <div class="sidebar-menu-card">
                <h3>Menu Akun</h3>
                <div class="menu-list">
                    <button class="menu-btn active" id="menu-profile" onclick="switchTab('profile')">
                        <i class="fa-regular fa-user"></i> Profil Saya
                    </button>
                    <button class="menu-btn" id="menu-booking" onclick="switchTab('booking')">
                        <i class="fa-regular fa-calendar"></i> Riwayat Booking
                    </button>
                    <button class="menu-btn" id="menu-member" onclick="switchTab('member')">
                        <i class="fa-solid fa-award"></i> Langganan Member
                    </button>
                    <button class="menu-btn" id="menu-purchase" onclick="switchTab('purchase')">
                        <i class="fa-solid fa-bag-shopping"></i> Riwayat Pembelian
                    </button>
                    <button class="menu-btn" id="menu-password" onclick="switchTab('password')">
                        <i class="fa-solid fa-lock"></i> Ganti Password
                    </button>
                    <button class="menu-btn" id="menu-delete" onclick="switchTab('delete')" style="color: var(--red);">
    <i class="fa-solid fa-user-minus"></i> Hapus Akun
</button>
                    <div class="menu-btn-divider"></div>
                    <a href="../login/logout.php" class="menu-btn" style="color: var(--primary);">
                        <i class="fa-solid fa-right-from-bracket"></i> Keluar
                    </a>
                </div>
            </div>

            <!-- Ringkasan Info (Bagian Bawah Sidebar agar Sejajar Tinggi) -->
            <div class="sidebar-menu-card" style="padding: 20px;">
                <h3 style="font-size: 14px; font-weight: 800; color: var(--dark-text); margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                    <i class="fa-solid fa-address-card" style="color: var(--primary); font-size: 14px;"></i> Ringkasan Akun
                </h3>
                <div style="display: flex; flex-direction: column; gap: 10px; font-size: 11px;">
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #F2F2F7; padding-bottom: 6px;">
                        <span style="color: var(--muted-text); font-weight: 600;">Nama Pengguna</span>
                        <span style="color: var(--dark-text); font-weight: 700;"><?= htmlspecialchars($username) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #F2F2F7; padding-bottom: 6px;">
                        <span style="color: var(--muted-text); font-weight: 600;">No. Telepon</span>
                        <span style="color: var(--dark-text); font-weight: 700;"><?= htmlspecialchars($telepon) ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span style="color: var(--muted-text); font-weight: 600;">Jenis Kelamin</span>
                        <span style="color: var(--dark-text); font-weight: 700;"><?= jk_label($jk) ?></span>
                    </div>
                </div>
            </div>

        </aside>

        <!-- SISI KANAN: FORM EDIT BIODATA (Tinggi menyesuaikan stretch secara otomatis) -->
        <div class="form-card" id="profile-form-card" style="justify-content: flex-start;">
            <div class="form-card-title">Informasi Pribadi</div>
            <form method="POST" id="formBiodata" style="display: flex; flex-direction: column; flex: 1; justify-content: space-between;">
                
                <div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">Nama Lengkap <span class="required">*</span></label>
                            <input type="text" name="nama_customer" id="nama_customer" class="form-input"
                                   value="<?= htmlspecialchars($nama) ?>"
                                   placeholder="Nama lengkap sesuai identitas" autocomplete="off">
                            <div class="error-msg" id="namaError">Nama minimal 3 karakter (hanya huruf & spasi).</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" name="email" id="email" class="form-input"
                                   value="<?= htmlspecialchars($email) ?>"
                                   placeholder="email@domain.com" autocomplete="off">
                            <div class="error-msg" id="emailError">Format email tidak valid.</div>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">No. HP <span class="required">*</span></label>
                            <input type="tel" name="no_telepon" id="no_telepon" class="form-input"
                                   value="<?= htmlspecialchars($telepon) ?>"
                                   maxlength="14" placeholder="Contoh: 08123456789">
                            <div class="error-msg" id="teleponError">Nomor telepon harus diawali 08 dan berjumlah 10-13 digit.</div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Tanggal Lahir <span class="required">*</span></label>
                            <input type="date" name="tanggal_lahir" id="tanggal_lahir" class="form-input"
                                   value="<?= format_date_input($tgl_lahir) ?>">
                            <div class="error-msg" id="tglLahirError">Tanggal lahir wajib diisi (usia minimal 10 tahun).</div>
                        </div>
                    </div>

                    <div class="form-row-2">
                        <div class="form-group">
    <label class="form-label">Jenis Kelamin <span class="required">*</span></label>
    <select name="jenis_kelamin" id="jenis_kelamin" class="form-input">
        <option value="1" <?= ($jk == 1) ? 'selected' : '' ?>>Laki-laki</option>
        <option value="0" <?= ($jk == 0) ? 'selected' : '' ?>>Perempuan</option>
    </select>
</div>

                        <div class="form-group">
                            <label class="form-label">Kota <span class="required">*</span></label>
                            <input type="text" name="tempat_lahir" id="tempat_lahir" class="form-input"
                                   value="<?= htmlspecialchars($tmp_lahir) ?>"
                                   placeholder="Kota tempat tinggal">
                            <div class="error-msg" id="tmpLahirError">Kota minimal 3 karakter (hanya huruf & spasi).</div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Alamat Lengkap <span class="required">*</span></label>
                        <input type="text" name="alamat" id="alamat" class="form-input" value="<?= htmlspecialchars($alamat) ?>" placeholder="Tuliskan alamat lengkap Anda">
                        <div class="error-msg" id="alamatError">Alamat tidak boleh kosong (minimal 3 kata).</div>
                    </div>

                    <input type="hidden" name="username" id="username" value="<?= htmlspecialchars($username) ?>">
                </div>

                <div class="form-buttons">
                    <button type="button" class="btn-cancel" onclick="window.location.reload();">Batal</button>
                    <button type="submit" name="update_biodata" class="btn-submit">Simpan Perubahan</button>
                </div>
            </form>
        </div>

        <!-- FORM KATA SANDI (TAMPIL KONDISIONAL VIA TAB) -->
        <div class="form-card" id="password-form-card" style="display: none; align-self: start;">
            <div class="form-card-title">Keamanan & Ubah Password</div>
            <form method="POST" id="formPassword" style="display: flex; flex-direction: column; flex: 1; justify-content: space-between;">
                <div>
                    <div class="form-group">
                        <label class="form-label">Kata Sandi Lama <span class="required">*</span></label>
                        <input type="password" name="old_password" id="old_password" class="form-input <?= ($pass_error_field === 'old_password') ? 'error' : '' ?>" placeholder="Sandi saat ini">
                        <div class="error-msg" id="oldPassError">Kata Sandi lama wajib diisi.</div>
                    </div>
                    <div class="form-row-2">
                        <div class="form-group">
                            <label class="form-label">Kata Sandi Baru <span class="required">*</span></label>
                            <input type="password" name="new_password" id="new_password" class="form-input <?= ($pass_error_field === 'new_password') ? 'error' : '' ?>" placeholder="Minimal 8 karakter">
                            <div class="error-msg" id="newPassError">Kata Sandi baru minimal 8 karakter.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Konfirmasi Sandi Baru <span class="required">*</span></label>
                            <input type="password" name="confirm_password" id="confirm_password" class="form-input <?= ($pass_error_field === 'confirm_password') ? 'error' : '' ?>" placeholder="Ulangi sandi baru">
                            <div class="error-msg" id="confirmPassError">Konfirmasi tidak cocok.</div>
                        </div>

                    </div>
                </div> <!-- <-- TAG PENUTUP pembungkus input yang sebelumnya hilang -->
                <div class="form-buttons" style="margin-top: auto;">
                    <button type="submit" name="update_password" class="btn-submit">Perbarui Password</button>
                </div>
            </form>
        </div>

        <!-- TAB HAPUS AKUN -->
<div class="form-card" id="delete-form-card" style="display: none; align-self: start;">
    <div class="form-card-title" style="color: var(--red);">Hapus Akun Permanen</div>
    <form method="POST" id="formDeleteAccount" style="display: flex; flex-direction: column; flex: 1; justify-content: space-between;">
        
        <!-- TAMBAHKAN BARIS INI -->
        <input type="hidden" name="delete_account" value="1">
        
        <div>
            <div style="background-color: #FFEBEA; border: 1px solid rgba(255, 59, 48, 0.2); padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                <h4 style="color: var(--red); font-size: 14px; font-weight: 800; margin-bottom: 6px;"><i class="fa-solid fa-triangle-exclamation"></i> Peringatan Penting</h4>
                <p style="font-size: 12px; color: #555; line-height: 1.5; margin-bottom: 8px;">
                    Menghapus akun akan mengakibatkan hal-hal berikut:
                </p>
                <ul style="font-size: 12px; color: #555; margin-left: 20px; line-height: 1.6;">
                    <li>Anda tidak akan dapat login kembali menggunakan akun ini.</li>
                    <li>Semua riwayat transaksi dan data keanggotaan aktif Anda akan ditangguhkan.</li>
                    <li>Semua jadwal booking aktif/mendatang akan otomatis dibatalkan.</li>
                </ul>
            </div>
            <div class="form-group">
                <label class="form-label">Masukkan Kata Sandi Anda <span class="required">*</span></label>
                <input type="password" name="confirm_delete_password" id="confirm_delete_password" class="form-input" placeholder="Ketik kata sandi saat ini untuk konfirmasi" required>
                <div class="error-msg" id="deletePassError">Wajib memasukkan kata sandi konfirmasi.</div>
            </div>
        </div> 
        <div class="form-buttons" style="margin-top: auto;">
            <!-- Hapus atribut name="delete_account" pada button agar tidak membingungkan -->
            <button type="submit" class="btn-submit" style="background: var(--red);">Konfirmasi Hapus Akun</button>
        </div>
    </form>
</div>

        <!-- TAB 1: KARTU RIWAYAT BOOKING -->
        <div class="form-card" id="booking-list-card" style="display: none; align-self: start;">
            <div class="form-card-title">Riwayat Booking Lapangan</div>
            <div class="list-container">
                <?php if (empty($bookings)): ?>
                    <div class="empty-state">
                        <i class="fa-regular fa-calendar-times"></i>
                        Belum ada riwayat booking lapangan.
                    </div>
                <?php else: ?>
                    <?php foreach ($bookings as $b): ?>
                        <div class="history-item">
                            <div class="history-header">
                                <span class="history-date"><i class="fa-regular fa-clock"></i> <?= format_date_display($b['Tanggal_Booking']) ?></span>
                                <?php 
                                $status_class = ''; $status_label = '';
                                switch ($b['BookingStatus']) {
                                    case 0: $status_class = 'orange'; $status_label = 'Menunggu Konfirmasi'; break;
                                    case 1: $status_class = 'green'; $status_label = 'Terkonfirmasi'; break;
                                    case 2: $status_class = 'blue'; $status_label = 'Selesai'; break;
                                    case 3: $status_class = 'red'; $status_label = 'Dibatalkan'; break;
                                }
                                ?>
                                <span class="badge-status-<?= $status_class ?>"><?= $status_label ?></span>
                            </div>
                            <div class="history-body">
                                <div class="h-details">
                                    <div class="h-title"><?= htmlspecialchars($b['Nama_Lapangan']) ?></div>
                                    <div class="h-meta"><i class="fa-regular fa-calendar"></i> Jadwal: <?= format_date_display($b['Tanggal']) ?> | <?= $b['Jam_Mulai']->format('H:i') ?> - <?= $b['Jam_Selesai']->format('H:i') ?> WIB</div>
                                    <div class="h-meta"><i class="fa-solid fa-credit-card"></i> Metode: <?= htmlspecialchars($b['Metode_Pembayaran']) ?></div>
                                    <?php if ($b['Nama_Promo']): ?>
                                        <div class="h-meta text-primary"><i class="fa-solid fa-tags"></i> Promo: <?= htmlspecialchars($b['Nama_Promo']) ?> (Diskon Rp <?= number_format($b['Diskon'], 0, ',', '.') ?>)</div>
                                    <?php endif; ?>
                                </div>
                                <div class="h-price">
                                    <span>Total Bayar</span>
                                    <span class="price-val">Rp <?= number_format($b['Total_Bayar'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 2: KARTU RIWAYAT LANGGANAN MEMBER -->
        <div class="form-card" id="member-list-card" style="display: none; align-self: start;">
            <div class="form-card-title">Riwayat Langganan Member</div>
            <div class="list-container">
                <?php if (empty($memberships)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-award"></i>
                        Belum ada riwayat langganan member.
                    </div>
                <?php else: ?>
                    <?php foreach ($memberships as $m): ?>
                        <div class="history-item">
                            <div class="history-header">
                                <span class="history-date"><i class="fa-solid fa-crown"></i> Paket: <?= htmlspecialchars($m['Nama_Tipe']) ?></span>
                                <?php 
                                $m_class = ''; $m_label = '';
                                switch ($m['MemberStatus']) {
                                    case 0: $m_class = 'orange'; $m_label = 'Menunggu Konfirmasi'; break;
                                    case 1: $m_class = 'green'; $m_label = 'Aktif'; break;
                                    case 2: $m_class = 'grey'; $m_label = 'Berakhir'; break;
                                    case 3: $m_class = 'red'; $m_label = 'Ditolak'; break;
                                }
                                ?>
                                <span class="badge-status-<?= $m_class ?>"><?= $m_label ?></span>
                            </div>
                            <div class="history-body">
                                <div class="h-details">
                                    <div class="h-title">Member Paket <?= htmlspecialchars($m['Nama_Tipe']) ?></div>
                                    <div class="h-meta"><i class="fa-regular fa-calendar-check"></i> Periode: <?= format_date_display($m['Tanggal_Mulai']) ?> s/d <?= format_date_display($m['Tanggal_Selesai']) ?></div>
                                    <div class="h-meta"><i class="fa-solid fa-tags"></i> Potongan Booking: Rp <?= number_format($m['Potongan_Harga'], 0, ',', '.') ?> / transaksi</div>
                                    <div class="h-meta"><i class="fa-solid fa-credit-card"></i> Metode: <?= htmlspecialchars($m['Metode_Pembayaran']) ?></div>
                                </div>
                                <div class="h-price">
                                    <span>Biaya Daftar</span>
                                    <span class="price-val">Rp <?= number_format($m['Total_Bayar'], 0, ',', '.') ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 3: KARTU RIWAYAT PEMBELIAN ALAT -->
        <div class="form-card" id="purchase-list-card" style="display: none; align-self: start;">
            <div class="form-card-title">Riwayat Pembelian Perlengkapan</div>
            <div class="list-container">
                <?php if (empty($purchases)): ?>
                    <div class="empty-state">
                        <i class="fa-solid fa-bag-shopping"></i>
                        Belum ada riwayat pembelian perlengkapan.
                    </div>
                <?php else: ?>
                    <?php foreach ($purchases as $p): ?>
                        <div class="history-item">
                            <div class="history-header">
                                <span class="history-date"><i class="fa-regular fa-clock"></i> <?= format_date_display($p['Tanggal_Beli']) ?></span>
                                <?php 
                                $p_class = $p['PurchaseStatus'] == 1 ? 'green' : 'orange';
                                $p_label = $p['PurchaseStatus'] == 1 ? 'Selesai / Terkirim' : 'Diproses';
                                ?>
                                <span class="badge-status-<?= $p_class ?>"><?= $p_label ?></span>
                            </div>
                            <div class="history-body" style="align-items: flex-start; flex-direction: column; gap: 12px; width: 100%;">
                                <div style="width: 100%; display: flex; flex-direction: column; gap: 8px; border-bottom: 1px solid #F2F2F7; padding-bottom: 12px;">
                                    <?php foreach ($p['items'] as $item): ?>
                                        <div style="display: flex; justify-content: space-between; font-size: 13px;">
                                            <span style="color: var(--dark-text); font-weight: 600;">
                                                <?= htmlspecialchars($item['Nama_Alat']) ?> <span style="color: var(--muted-text); font-weight: 500;">(x<?= $item['Jumlah'] ?>)</span>
                                            </span>
                                            <span style="color: var(--muted-text);">Rp <?= number_format($item['SubTotal'], 0, ',', '.') ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div style="display: flex; justify-content: space-between; width: 100%; align-items: center; margin-top: 4px;">
                                    <div class="h-meta"><i class="fa-solid fa-credit-card"></i> Metode: <?= htmlspecialchars($p['Metode_Pembayaran']) ?></div>
                                    <div class="h-price">
                                        <span>Total Belanja</span>
                                        <span class="price-val">Rp <?= number_format($p['Total_Bayar'], 0, ',', '.') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- BARIS 2: DETAIL BOOKING BERIKUTNYA & MEMBER AKTIF (LEBAR PENUH) -->
    <div class="lower-panel-grid" id="lower-dashboard-section">
        
        <!-- Booking Berikutnya -->
        <div class="lower-card">
            <div class="lower-card-title">Booking Berikutnya</div>
            <?php if ($next_booking): ?>
                <div class="booking-row">
                    <div class="booking-img-wrapper">
                        <img src="https://images.unsplash.com/photo-1519766304817-4f37bda74a27?q=80&w=400&auto=format&fit=crop" alt="Lapangan">
                    </div>
                    <div class="booking-details-box">
                        <div class="b-title"><?= htmlspecialchars($next_booking['Nama_Lapangan']) ?></div>
                        <ul class="b-meta-list">
                            <li class="b-meta-item"><i class="fa-solid fa-location-dot"></i> Jakarta Selatan</li>
                            <li class="b-meta-item"><i class="fa-regular fa-calendar"></i> <?= format_date_display($next_booking['Tanggal']) ?></li>
                            <li class="b-meta-item"><i class="fa-regular fa-clock"></i> <?= $next_booking['Jam_Mulai']->format('H:i') . ' - ' . $next_booking['Jam_Selesai']->format('H:i') ?> WIB</li>
                        </ul>
                    </div>
                    <div class="booking-actions-box">
                        <span class="badge-status-green"><i class="fa-regular fa-circle-check"></i> Terkonfirmasi</span>
                        <button type="button" class="btn-outline-detail">Lihat Detail</button>
                    </div>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 20px; color: var(--muted-text); font-size: 13px; margin: auto 0;">
                    <i class="fa-regular fa-calendar-times" style="font-size: 24px; margin-bottom: 8px; display: block; color: #AEAEB2;"></i>
                    Tidak ada jadwal booking mendatang.
                </div>
            <?php endif; ?>
        </div>

        <!-- Member Aktif -->
        <div class="lower-card">
    <div class="lower-card-title">Member Aktif</div>
    <?php if ($has_member): ?>
        <div class="member-row">
            <div class="member-badge-logo" style="border-color: #FF9500; color: #FF9500;">
                <i class="fa-solid fa-crown"></i>
                <span><?= strtoupper($member_tipe) ?></span>
            </div>
            <div class="member-info-box">
                <div class="member-name-label">Paket <?= htmlspecialchars($member_tipe) ?></div>
                <ul class="member-benefit-list">
                    <li class="member-benefit-item"><i class="fa-solid fa-circle-check"></i> Diskon booking sesuai paket</li>
                    <li class="member-benefit-item"><i class="fa-solid fa-circle-check"></i> Prioritas reservasi lapangan</li>
                    <li class="member-benefit-item"><i class="fa-solid fa-circle-check"></i> Benefit merchant Hoopball</li>
                </ul>
            </div>
            <div class="member-renew-box">
                <div class="renew-expiry-label">
                    <span>Aktif sampai</span>
                    <span class="renew-expiry-val" style="display:block; margin-top:2px; color: var(--primary);">
                        <?= $member_expiry ? $member_expiry->format('d F Y') : '-' ?>
                    </span>
                </div>
                <button class="btn-solid-renew">Perpanjang Member</button>
            </div>
        </div>
    <?php else: ?>
        <div class="member-row">
            <div class="member-badge-logo" style="border-color: #8E8E93; color: #8E8E93; background: none;">
                <i class="fa-solid fa-user-slash"></i>
                <span style="font-size: 9px;">BASIC</span>
            </div>
            <div class="member-info-box">
                <div class="member-name-label" style="color: var(--muted-text);">Belum Berlangganan Member</div>
                <div style="font-size: 11px; color: var(--muted-text); margin-top: 4px; line-height: 1.4;">
                    Bergabunglah menjadi member Silver, Gold, atau Platinum untuk mendapatkan potongan harga langsung pada reservasi lapangan Anda!
                </div>
            </div>
            <div class="member-renew-box" style="justify-content: center; height: auto;">
                    <button class="btn-solid-renew">Daftar Member</button>
                </div>
            </div>
        <?php endif; ?>
    </div> <!-- Menutup .lower-card -->
</div> <!-- <-- TAMBAHKAN TAG PENUTUP INI UNTUK MENUTUP .lower-panel-grid YANG HILANG -->

    <!-- BARIS 3: AKTIVITAS TERBARU (LEBAR PENUH) -->
    <div class="activity-section-card" id="activity-section">
        <div class="form-card-title" style="margin-bottom: 16px;">Aktivitas Terbaru</div>
        <div class="activity-grid-3">
            
            <div class="activity-item-card">
                <div class="activity-item-icon"><i class="fa-regular fa-calendar-check"></i></div>
                <div class="activity-item-details">
                    <div class="act-title">Booking Lapangan</div>
                    <div class="act-subtitle">Reservasi Lapangan Hoopball</div>
                    <div class="act-time">Mei 2025</div>
                </div>
                <span class="badge-act-status green">Selesai</span>
            </div>

            <div class="activity-item-card">
                <div class="activity-item-icon"><i class="fa-solid fa-bag-shopping"></i></div>
                <div class="activity-item-details">
                    <div class="act-title">Pembelian Perlengkapan</div>
                    <div class="act-subtitle">Alat Pendukung Olahraga</div>
                    <div class="act-time">Mei 2025</div>
                </div>
                <span class="badge-act-status blue">Terkirim</span>
            </div>

            <div class="activity-item-card">
                <div class="activity-item-icon"><i class="fa-regular fa-user"></i></div>
                <div class="activity-item-details">
                    <div class="act-title">Update Profil</div>
                    <div class="act-subtitle">Perubahan informasi profil</div>
                    <div class="act-time">Mei 2025</div>
                </div>
                <span class="badge-act-status green">Selesai</span>
            </div>

        </div>
    </div>

</main>

<!-- FOOTER -->
<footer>
    <div class="footer-grid-4">
        
        <!-- Kolom 1 -->
        <div class="footer-brand">
            <h3><img src="../asset/image/logo.png" alt="HoopBall"></h3>
            <p>HoopBall adalah platform penyewaan lapangan basket online yang mudah, cepat, dan terpercaya.</p>
            <div class="social-row">
                <a href="#" class="social-circle"><i class="fa-brands fa-instagram"></i></a>
                <a href="#" class="social-circle"><i class="fa-brands fa-facebook-f"></i></a>
                <a href="#" class="social-circle"><i class="fa-brands fa-tiktok"></i></a>
                <a href="#" class="social-circle"><i class="fa-brands fa-youtube"></i></a>
            </div>
        </div>

        <!-- Kolom 2 -->
        <div class="footer-col">
            <h4>Navigasi</h4>
            <ul class="footer-links-list">
                <li><a href="../dashboard/view_customer.php">Beranda</a></li>
                <li><a href="#">Lapangan</a></li>
                <li><a href="#">Jadwal</a></li>
                <li><a href="#">Member</a></li>
                <li><a href="#">Pembelian</a></li>
                <li><a href="#">Tentang</a></li>
                <li><a href="#">Kontak</a></li>
            </ul>
        </div>

        <!-- Kolom 3 -->
        <div class="footer-col">
            <h4>Informasi</h4>
            <ul class="footer-links-list">
                <li><a href="#">Cara Booking</a></li>
                <li><a href="#">Syarat & Ketentuan</a></li>
                <li><a href="#">Kebijakan Privasi</a></li>
                <li><a href="#">FAQ</a></li>
                <li><a href="#">Blog</a></li>
            </ul>
        </div>

        <!-- Kolom 4 -->
        <div class="footer-col">
            <h4>Hubungi Kami</h4>
            <div class="contact-item-box">
                <i class="fa-solid fa-location-dot"></i>
                <span>Jl. Olahraga No. 10, Kebayoran Baru, Jakarta Selatan 12190</span>
            </div>
            <div class="contact-item-box">
                <i class="fa-solid fa-phone"></i>
                <span>+62 812-3456-7890</span>
            </div>
            <div class="contact-item-box">
                <i class="fa-solid fa-envelope"></i>
                <span>info@hoopball.id</span>
            </div>
            <div class="contact-item-box">
                <i class="fa-solid fa-clock"></i>
                <span>Setiap hari 07:00 - 23:00 WIB</span>
            </div>
        </div>

    </div>

    <div class="footer-bottom-copyright">
        <p>© 2025 HoopBall. All rights reserved.</p>
    </div>
</footer>

<script>
// Tab Switcher Controller
function switchTab(tab) {
     document.querySelectorAll('.menu-btn').forEach(btn => btn.classList.remove('active'));
    
    const profileForm = document.getElementById('profile-form-card');
    const passwordForm = document.getElementById('password-form-card');
    const deleteForm = document.getElementById('delete-form-card'); // Ditambahkan
    const bookingList = document.getElementById('booking-list-card');
    const memberList = document.getElementById('member-list-card');
    const purchaseList = document.getElementById('purchase-list-card');
    const lowerSection = document.getElementById('lower-dashboard-section');
    const activitySection = document.getElementById('activity-section');

    profileForm.style.display = 'none';
    passwordForm.style.display = 'none';
    if (deleteForm) deleteForm.style.display = 'none'; // Ditambahkan
    if (bookingList) bookingList.style.display = 'none';
    if (memberList) memberList.style.display = 'none';
    if (purchaseList) purchaseList.style.display = 'none';

    if (tab === 'profile') {
        document.getElementById('menu-profile').classList.add('active');
        profileForm.style.display = 'flex';
        lowerSection.style.display = 'grid';
        activitySection.style.display = 'block';
    } else if (tab === 'password') {
        document.getElementById('menu-password').classList.add('active');
        passwordForm.style.display = 'flex';
        lowerSection.style.display = 'none';
        activitySection.style.display = 'none';
    } else if (tab === 'delete') { // Ditambahkan
        document.getElementById('menu-delete').classList.add('active');
        if (deleteForm) deleteForm.style.display = 'flex';
        lowerSection.style.display = 'none';
        activitySection.style.display = 'none';
    } else if (tab === 'booking') {
        document.getElementById('menu-booking').classList.add('active');
        if (bookingList) bookingList.style.display = 'flex';
        lowerSection.style.display = 'none';
        activitySection.style.display = 'none';
    } else if (tab === 'member') {
        document.getElementById('menu-member').classList.add('active');
        if (memberList) memberList.style.display = 'flex';
        lowerSection.style.display = 'none';
        activitySection.style.display = 'none';
    } else if (tab === 'purchase') {
        document.getElementById('menu-purchase').classList.add('active');
        if (purchaseList) purchaseList.style.display = 'flex';
        lowerSection.style.display = 'none';
        activitySection.style.display = 'none';
    }
}

// Notification handler (SweetAlert)
<?php if ($swal_status && $swal_msg): ?>
Swal.fire({
    icon: '<?= $swal_status ?>',
    title: '<?= $swal_status === 'success' ? 'Berhasil!' : 'Gagal!' ?>',
    text: '<?= addslashes($swal_msg) ?>',
    timer: 4000,
    showConfirmButton: false,
    toast: true,
    position: 'top-end',
    timerProgressBar: true,
    background: '#ffffff',
    color: '#1C1C1E',
    iconColor: '<?= $swal_status === 'success' ? '#34C759' : '#FF3B30' ?>',
    customClass: { popup: 'swal-light' }
});
<?php endif; ?>

// Real-time Field Validation
const namaInput = document.getElementById('nama_customer');
const tglLahirInput = document.getElementById('tanggal_lahir');
const tmpLahirInput = document.getElementById('tempat_lahir');
const teleponInput = document.getElementById('no_telepon');
const alamatInput = document.getElementById('alamat');
const emailInput = document.getElementById('email');

if (namaInput) {
    namaInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
        validateNama();
    });
    namaInput.addEventListener('blur', validateNama);
}

function validateNama() {
    if (!namaInput) return true;
    const val = namaInput.value.trim();
    const error = document.getElementById('namaError');
    if (val.length < 3 || !/^[a-zA-Z\s]+$/.test(val)) {
        namaInput.classList.add('error'); namaInput.classList.remove('valid'); error.classList.add('show'); return false;
    } else {
        namaInput.classList.remove('error'); namaInput.classList.add('valid'); error.classList.remove('show'); return true;
    }
}

if (tmpLahirInput) {
    tmpLahirInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^a-zA-Z\s]/g, '');
        validateTmpLahir();
    });
    tmpLahirInput.addEventListener('blur', validateTmpLahir);
}

function validateTmpLahir() {
    if (!tmpLahirInput) return true;
    const val = tmpLahirInput.value.trim();
    const error = document.getElementById('tmpLahirError');
    if (val === '' || val.length < 3 || !/^[a-zA-Z\s]+$/.test(val)) {
        tmpLahirInput.classList.add('error'); tmpLahirInput.classList.remove('valid'); error.classList.add('show'); return false;
    } else {
        tmpLahirInput.classList.remove('error'); tmpLahirInput.classList.add('valid'); error.classList.remove('show'); return true;
    }
}

if (tglLahirInput) {
    tglLahirInput.addEventListener('change', validateTglLahir);
    tglLahirInput.addEventListener('blur', validateTglLahir);
}

function validateTglLahir() {
    if (!tglLahirInput) return true;
    const val = tglLahirInput.value;
    const error = document.getElementById('tglLahirError');
    if (val === '') {
        tglLahirInput.classList.add('error'); tglLahirInput.classList.remove('valid'); error.classList.add('show'); return false;
    }
    const birthDate = new Date(val);
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) age--;

    if (age < 10) {
        error.textContent = 'Usia minimal 10 tahun.';
        tglLahirInput.classList.add('error'); tglLahirInput.classList.remove('valid'); error.classList.add('show'); return false;
    } else if (age > 100) {
        error.textContent = 'Tanggal lahir tidak valid.';
        tglLahirInput.classList.add('error'); tglLahirInput.classList.remove('valid'); error.classList.add('show'); return false;
    } else {
        tglLahirInput.classList.remove('error'); tglLahirInput.classList.add('valid'); error.classList.remove('show'); return true;
    }
}

if (emailInput) {
    emailInput.addEventListener('input', validateEmail);
    emailInput.addEventListener('blur', validateEmail);
}

function validateEmail() {
    if (!emailInput) return true;
    const val = emailInput.value.trim();
    const error = document.getElementById('emailError');
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (val === '' || !emailRegex.test(val)) {
        emailInput.classList.add('error'); emailInput.classList.remove('valid'); error.classList.add('show'); return false;
    } else {
        emailInput.classList.remove('error'); emailInput.classList.add('valid'); error.classList.remove('show'); return true;
    }
}

if (teleponInput) {
    teleponInput.addEventListener('input', function() {
        this.value = this.value.replace(/[^0-9]/g, '');
        validateTelepon();
    });
    teleponInput.addEventListener('blur', validateTelepon);
}

function validateTelepon() {
    if (!teleponInput) return true;
    const val = teleponInput.value.trim();
    const error = document.getElementById('teleponError');
    if (!/^08[0-9]{8,12}$/.test(val)) {
        error.textContent = 'Nomor telepon harus berawal 08 dan 10-13 digit.';
        teleponInput.classList.add('error'); teleponInput.classList.remove('valid'); error.classList.add('show'); return false;
    } else {
        teleponInput.classList.remove('error'); teleponInput.classList.add('valid'); error.classList.remove('show'); return true;
    }
}

if (alamatInput) {
    alamatInput.addEventListener('input', validateAlamat);
    alamatInput.addEventListener('blur', validateAlamat);
}

function validateAlamat() {
    if (!alamatInput) return true;
    const val = alamatInput.value.trim();
    const error = document.getElementById('alamatError');
    const normalized = val.replace(/\s+/g, ' ').trim();
    const words = normalized.split(' ').filter(function(w) { return w.length > 0; });
    if (val === '') {
        error.textContent = 'Alamat tidak boleh kosong.';
        alamatInput.classList.add('error'); alamatInput.classList.remove('valid'); error.classList.add('show'); return false;
    } else if (words.length < 3) {
        error.textContent = 'Alamat minimal 3 kata (saat ini: ' + words.length + ' kata).';
        alamatInput.classList.add('error'); alamatInput.classList.remove('valid'); error.classList.add('show'); return false;
    } else {
        alamatInput.classList.remove('error'); alamatInput.classList.add('valid'); error.classList.remove('show'); return true;
    }
}

// Form Submit Validation (Biodata)
const formBiodata = document.getElementById('formBiodata');
if (formBiodata) {
    formBiodata.addEventListener('submit', function(e) {
        let valid = true;
        if (!validateNama()) valid = false;
        if (!validateTglLahir()) valid = false;
        if (!validateTmpLahir()) valid = false;
        if (!validateAlamat()) valid = false;
        if (!validateTelepon()) valid = false;
        if (!validateEmail()) valid = false;
        if (!valid) {
            e.preventDefault();
            Swal.fire({
                icon: 'error', 
                title: 'Validasi Gagal', 
                text: 'Mohon periksa kembali data yang diisi dengan benar.',
                confirmButtonColor: '#FF5200'
            });
        }
    });
}

// Password Form Validation
const oldPass = document.getElementById('old_password');
const newPass = document.getElementById('new_password');
const confirmPass = document.getElementById('confirm_password');

function validateOldPass() {
    if (!oldPass) return true;
    const error = document.getElementById('oldPassError');
    if (!oldPass.value) { oldPass.classList.add('error'); error.classList.add('show'); return false; }
    else { oldPass.classList.remove('error'); error.classList.remove('show'); return true; }
}
function validateNewPass() {
    if (!newPass) return true;
    const val = newPass.value;
    const error = document.getElementById('newPassError');
    if (val.length < 8) { newPass.classList.add('error'); error.classList.add('show'); return false; }
    else { newPass.classList.remove('error'); error.classList.remove('show'); return true; }
}
function validateConfirm() {
    if (!confirmPass || !newPass) return true;
    const error = document.getElementById('confirmPassError');
    if (confirmPass.value !== newPass.value || !confirmPass.value) { confirmPass.classList.add('error'); error.classList.add('show'); return false; }
    else { confirmPass.classList.remove('error'); error.classList.remove('show'); return true; }
}

if (oldPass) oldPass.addEventListener('blur', validateOldPass);
if (newPass) { newPass.addEventListener('input', validateNewPass); newPass.addEventListener('blur', validateNewPass); }
if (confirmPass) { confirmPass.addEventListener('input', validateConfirm); confirmPass.addEventListener('blur', validateConfirm); }

const formPassword = document.getElementById('formPassword');
if (formPassword) {
    formPassword.addEventListener('submit', function(e) {
        let valid = true;
        if (!validateOldPass()) valid = false;
        if (!validateNewPass()) valid = false;
        if (!validateConfirm()) valid = false;
        if (!valid) {
            e.preventDefault();
            Swal.fire({
                icon: 'error', 
                title: 'Validasi Gagal', 
                text: 'Mohon periksa kembali input Kata Sandi Anda.',
                confirmButtonColor: '#FF5200'
            });
        }
    });
}

const formDeleteAccount = document.getElementById('formDeleteAccount');
const deletePass = document.getElementById('confirm_delete_password');

function validateDeletePass() {
    if (!deletePass) return true;
    const error = document.getElementById('deletePassError');
    if (!deletePass.value) { 
        deletePass.classList.add('error'); 
        error.classList.add('show'); 
        return false; 
    } else { 
        deletePass.classList.remove('error'); 
        error.classList.remove('show'); 
        return true; 
    }
}

if (deletePass) deletePass.addEventListener('blur', validateDeletePass);

if (formDeleteAccount) {
    formDeleteAccount.addEventListener('submit', function(e) {
        e.preventDefault();
        
        if (!validateDeletePass()) {
            return;
        }

        Swal.fire({
            title: 'Apakah Anda Yakin?',
            text: "Akun Anda akan dinonaktifkan secara permanen. Tindakan ini tidak dapat dibatalkan!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#FF3B30',
            cancelButtonColor: '#8E8E93',
            confirmButtonText: 'Ya, Hapus Akun!',
            cancelButtonText: 'Batal',
            customClass: {
                popup: 'swal-light'
            }
        }).then((result) => {
            if (result.isConfirmed) {
                formDeleteAccount.submit();
            }
        });
    });
}

</script>
</body>
</html>