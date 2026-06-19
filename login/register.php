<?php
session_start();
include '../includes/config.php';

$res_status = "";
$res_msg = "";

// Tambahkan inisialisasi ini:
$nama = "";
$username = "";
$email = "";
$telp = "";
$jk_input = "1"; // Default Laki-laki
$alamat = "";
$tgl_lahir = "";
$tmp_lahir = "";

if (isset($_POST['register'])) {
    $nama = trim($_POST['nama']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $telp = trim($_POST['telp']);
    $jk_input = $_POST['jk']; // 0 = Perempuan, 1 = Laki-laki (sesuai database)
    $password = $_POST['password'];
    $alamat = trim($_POST['alamat']);
    $tgl_lahir = $_POST['tanggal_lahir'] ?? '';
    $tmp_lahir = trim($_POST['tempat_lahir'] ?? '');

    // Validasi Jenis Kelamin sesuai database (0 atau 1)
    $jk = (int) $jk_input;
    if ($jk !== 0 && $jk !== 1) {
        $jk = 1; // Default Laki-laki
    }

    // Cek duplikat Username, Email, atau Nomor Telepon di tabel Customer
    $sql_check = "SELECT Username, Email, No_Telepon FROM Customer WHERE Username = ? OR Email = ? OR No_Telepon = ?";
    $stmt_check = sqlsrv_query($conn, $sql_check, array($username, $email, $telp));

    if ($stmt_check === false) {
        $res_status = "error";
        $res_msg = "Terjadi kesalahan koneksi database.";
    } else if (sqlsrv_has_rows($stmt_check)) {
        $res_status = "error";

        // Memeriksa kolom mana yang menyebabkan duplikasi agar notifikasi lebih spesifik
        $exist_user = false;
        $exist_email = false;
        $exist_telp = false;

        while ($row_check = sqlsrv_fetch_array($stmt_check, SQLSRV_FETCH_ASSOC)) {
            if (strtolower($row_check['Username']) === strtolower($username)) {
                $exist_user = true;
            }
            if (strtolower($row_check['Email']) === strtolower($email)) {
                $exist_email = true;
            }
            if ($row_check['No_Telepon'] === $telp) {
                $exist_telp = true;
            }
        }

        if ($exist_telp) {
            $res_msg = "Nomor telepon sudah terdaftar! Gunakan nomor lain.";
        } else if ($exist_user) {
            $res_msg = "Nama Pengguna sudah terdaftar! Gunakan Nama Pengguna lain.";
        } else if ($exist_email) {
            $res_msg = "Email sudah terdaftar! Gunakan email lain.";
        } else {
            $res_msg = "Data akun sudah terdaftar!";
        }
    } else {
        sqlsrv_begin_transaction($conn);

        // Insert ke tabel Customer (ID_Customer dilewati karena otomatis diisi oleh IDENTITY di database)
        $sql_customer = "INSERT INTO Customer 
            (Nama_Customer, Tanggal_Lahir, Tempat_Lahir, Jenis_Kelamin, Alamat, No_Telepon, Email, Username, Kata_Sandi, Status, Created_By, Created_Date) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 'System', GETDATE())";

        $stmt_customer = sqlsrv_query($conn, $sql_customer, array(
            $nama,
            $tgl_lahir,
            $tmp_lahir,
            $jk,
            $alamat,
            $telp,
            $email,
            $username,
            $password
        ));

        if ($stmt_customer) {
            sqlsrv_commit($conn);
            $res_status = "success";
            $res_msg = "Pendaftaran Berhasil! Silahkan Login.";
        } else {
            sqlsrv_rollback($conn);
            $errors = sqlsrv_errors();
            $error_detail = "";
            if ($errors) {
                foreach ($errors as $error) {
                    $error_detail .= $error['message'] . " ";
                }
            }
            $res_status = "error";
            $res_msg = "Terjadi kesalahan sistem saat mendaftar: " . $error_detail;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi | HoopBall BasketPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --orange: #FF5400;
            --orange-hover: #E04600;
            --dark-blue: #0F172A;
            --text-dark: #1E293B;
            --text-muted: #64748B;
            --border-color: #E2E8F0;
            --bg-light: #F8FAFC;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Plus Jakarta Sans', sans-serif; }
        body { background-color: #FFFFFF; color: var(--text-dark); overflow-x: hidden; }

        .auth-hero-wrapper {
            background: linear-gradient(rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.85)), 
                url('../asset/image/login.png') no-repeat center center;
            background-size: cover;
            background-attachment: fixed;
            min-height: 100vh; 
            padding: 80px 8% 80px 8%; 
            display: grid;
            grid-template-columns: 1fr 1.3fr;
            gap: 50px;
            align-items: center;
            position: relative;
        }

        .auth-info {
            align-self: start;
            margin-top: 100px;
            animation: slideInLeft 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.2s both;
        }

        .auth-info h2 {
            font-size: 48px;
            font-weight: 900;
            color: #ffffff;
            line-height: 1.15;
            margin-bottom: 16px;
            animation: bounceIn 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.1s both;
        }
        .auth-info h2 span { color: var(--orange); }
        .auth-info .intro-p {
            font-size: 15px;
            color: #94A3B8;
            line-height: 1.6;
            margin-bottom: 48px;
            max-width: 500px;
        }

        .info-list { display: flex; flex-direction: column; gap: 24px; }
        .info-item {
            display: flex;
            align-items: center;
            gap: 16px;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .info-item:hover { transform: translateX(8px); }
        .info-icon {
            width: 44px; height: 44px;
            background: rgba(255, 255, 255, 0.08);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: var(--orange);
            font-size: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s ease;
        }
        .info-item:hover .info-icon {
            background: var(--orange);
            border-color: var(--orange);
            color: #ffffff;
            box-shadow: 0 0 12px rgba(255, 84, 0, 0.3);
        }
        .info-text h4 { font-size: 14px; font-weight: 700; color: #ffffff; margin-bottom: 2px; }
        .info-text p { font-size: 12px; color: #94A3B8; }

        .info-item:nth-child(1) { animation: fadeInUp 0.5s ease-out 0.6s both; }
        .info-item:nth-child(2) { animation: fadeInUp 0.5s ease-out 0.75s both; }
        .info-item:nth-child(3) { animation: fadeInUp 0.5s ease-out 0.9s both; }

        .auth-card-container { display: flex; justify-content: flex-end; }
        .auth-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            width: 100%;
            max-width: 450px;
            padding: 44px 36px;
            box-shadow: 
                0 20px 50px rgba(0, 0, 0, 0.3),
                0 0 0 1px rgba(255, 255, 255, 0.2) inset,
                0 0 80px rgba(255, 84, 0, 0.08) inset;
            text-align: center;
            position: relative;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            transform-style: preserve-3d;
            animation: cardPopIn 0.8s cubic-bezier(0.34, 1.56, 0.64, 1) 0.3s both;
        }
        .auth-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(
                45deg,
                transparent 40%,
                rgba(255, 84, 0, 0.03) 50%,
                transparent 60%
            );
            animation: cardShine 4s ease-in-out infinite;
            pointer-events: none;
        }
        @keyframes cardShine {
            0% { transform: translateX(-100%) rotate(45deg); }
            100% { transform: translateX(100%) rotate(45deg); }
        }
        .auth-card:hover {
            transform: translateY(-5px) rotateX(2deg) rotateY(-2deg);
            box-shadow: 
                0 30px 60px rgba(0, 0, 0, 0.35),
                0 0 0 1px rgba(255, 255, 255, 0.3) inset,
                0 0 100px rgba(255, 84, 0, 0.12) inset;
        }
        .auth-card h3 { font-size: 28px; font-weight: 900; color: var(--dark-blue); margin-bottom: 6px; letter-spacing: -0.5px; }
        .auth-card .card-subtitle { font-size: 13px; color: var(--text-muted); margin-bottom: 32px; display: block; }

        .card-header-decoration {
            display: flex;
            justify-content: center;
            margin-bottom: 16px;
        }
        .card-icon-wrapper {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, var(--orange) 0%, #FF6B35 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 8px 25px rgba(255, 84, 0, 0.3);
            animation: iconPulse 2s ease-in-out infinite;
            position: relative;
        }
        .card-icon-wrapper::after {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            border: 2px solid rgba(255, 84, 0, 0.2);
            animation: ringExpand 2s ease-out infinite;
        }
        .card-icon-wrapper i {
            font-size: 28px;
            color: #fff;
            animation: ballSpin 3s linear infinite;
        }
        @keyframes iconPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        @keyframes ringExpand {
            0% { transform: scale(1); opacity: 1; }
            100% { transform: scale(1.5); opacity: 0; }
        }
        @keyframes ballSpin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-bottom: 32px;
        }
        .step-dot {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: var(--bg-light);
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .step-dot.active {
            background: var(--orange);
            border-color: var(--orange);
            color: #ffffff;
            box-shadow: 0 0 10px rgba(255, 84, 0, 0.25);
        }
        .step-line {
            width: 60px;
            height: 2px;
            background: var(--border-color);
            transition: all 0.3s ease;
        }
        .step-line.active {
            background: var(--orange);
        }

        .form-step {
            display: none;
            animation: fadeStep 0.4s ease-out forwards;
        }
        .form-step.active {
            display: block;
        }
        @keyframes fadeStep {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .input-group { 
            margin-bottom: 20px; 
            text-align: left; 
            transition: all 0.3s ease;
            position: relative;
        }
        .input-group label { 
            font-size: 12px; 
            font-weight: 700; 
            color: var(--text-dark); 
            margin-bottom: 8px; 
            display: block; 
            transition: all 0.3s ease;
            position: relative;
        }
        .input-group label::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--orange);
            transition: width 0.3s ease;
        }
        .input-group:focus-within label { 
            color: var(--orange);
            transform: translateX(4px);
        }
        .input-group:focus-within label::after {
            width: 30px;
        }
        .input-group:focus-within .input-wrapper i.icon-left { 
            color: var(--orange);
            transform: scale(1.2);
        }
        .input-wrapper { 
            position: relative;
            transition: all 0.3s ease;
        }
        .input-wrapper i.icon-left {
            position: absolute; left: 16px; top: 50%; transform: translateY(-50%);
            color: #94A3B8; font-size: 14px; pointer-events: none; 
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .input-wrapper input,
        .input-wrapper select {
            width: 100%; padding: 14px 44px 14px 44px; 
            background: linear-gradient(135deg, #FFFFFF 0%, #FAFBFC 100%);
            border: 2px solid var(--border-color); 
            color: var(--text-dark);
            border-radius: 14px; 
            font-size: 14px; 
            outline: none; 
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .input-wrapper select {
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
        }
        .input-wrapper input:focus,
        .input-wrapper select:focus { 
            border-color: var(--orange); 
            box-shadow: 
                0 0 0 4px rgba(255, 84, 0, 0.08),
                0 4px 20px rgba(255, 84, 0, 0.1);
            transform: translateY(-2px);
            background: #FFFFFF;
        }
        .input-wrapper input::placeholder {
            color: #CBD5E1;
            transition: all 0.3s ease;
        }
        .input-wrapper input:focus::placeholder {
            color: #E2E8F0;
            transform: translateX(5px);
        }
        .input-wrapper i.icon-right {
            position: absolute; right: 16px; top: 50%; transform: translateY(-50%);
            color: #94A3B8; font-size: 14px; cursor: pointer; padding: 4px;
            transition: all 0.3s ease;
            border-radius: 50%;
        }
        .input-wrapper i.icon-right:hover {
            color: var(--orange);
            background: rgba(255, 84, 0, 0.08);
            transform: translateY(-50%) scale(1.1);
        }

        .input-wrapper.error input,
        .input-wrapper.error select { border-color: #EF4444 !important; background-color: #FEF2F2 !important; }
        .input-wrapper.error i.icon-left { color: #EF4444 !important; }
        .input-group.error-active label { color: #EF4444 !important; }
        .error-text { font-size: 11px; color: #EF4444; font-weight: 600; margin-top: 6px; display: none; animation: fadeInError 0.2s ease-out; }
        @keyframes fadeInError { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }

        .btn-submit {
            width: 100%; padding: 16px; 
            background: linear-gradient(135deg, var(--orange) 0%, #FF6B35 50%, var(--orange) 100%);
            background-size: 200% 200%;
            color: #ffffff;
            border: none; 
            border-radius: 14px; 
            font-weight: 800; 
            font-size: 15px;
            cursor: pointer; 
            transition: all 0.4s cubic-bezier(0.34, 1.56, 0.64, 1);
            position: relative;
            overflow: hidden;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .btn-submit::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 100%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: left 0.6s ease;
        }
        .btn-submit:hover::before {
            left: 100%;
        }
        .btn-submit:hover { 
            background-position: 100% 0;
            transform: translateY(-3px) scale(1.02); 
            box-shadow: 
                0 12px 30px rgba(255, 84, 0, 0.4),
                0 0 40px rgba(255, 84, 0, 0.15);
        }
        .btn-submit:active {
            transform: translateY(-1px) scale(0.98);
        }
        .btn-submit::after {
            content: '';
            position: absolute;
            top: 50%; left: 50%;
            width: 0; height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }
        .btn-submit:active::after {
            width: 300px; height: 300px;
        }

        .step-buttons {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .btn-back-step {
            background: #ffffff;
            color: var(--text-dark);
            border: 1px solid var(--border-color);
            padding: 15px;
            border-radius: 14px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            flex: 1;
            transition: all 0.3s ease;
        }
        .btn-back-step:hover {
            background: var(--bg-light);
            border-color: #CBD5E1;
        }
        .step-buttons .btn-submit {
            flex: 2;
        }

        .card-footer { 
            margin-top: 24px; 
            font-size: 13px; 
            color: var(--text-muted); 
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
            position: relative;
        }
        .card-footer::before {
            content: '';
            position: absolute;
            top: -1px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--orange), transparent);
        }
        .card-footer a { 
            color: var(--orange); 
            text-decoration: none; 
            font-weight: 800;
            position: relative;
            transition: all 0.3s ease;
        }
        .card-footer a::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--orange);
            transition: width 0.3s ease;
        }
        .card-footer a:hover { 
            color: var(--orange-hover);
        }
        .card-footer a:hover::after {
            width: 100%;
        }

        .features-bar {
            padding: 40px 8%; background: #FFFFFF;
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px;
            border-bottom: 1px solid var(--border-color);
        }
        .feat-bar-item {
            display: flex; align-items: center; gap: 16px; padding: 12px 16px;
            border-radius: 12px; cursor: pointer; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .feat-bar-item:hover { transform: translateY(-5px); background-color: var(--bg-light); }
        .feat-bar-icon {
            width: 52px; height: 52px; background: #FFF0E9; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; flex-shrink: 0;
            color: var(--orange); font-size: 18px; transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .feat-bar-item:hover .feat-bar-icon {
            background: var(--orange); color: #ffffff; transform: scale(1.08);
            box-shadow: 0 8px 16px rgba(255, 84, 0, 0.2);
        }
        .feat-bar-text h4 { font-size: 14px; font-weight: 700; color: var(--dark-blue); margin-bottom: 2px; transition: color 0.3s ease; }
        .feat-bar-item:hover .feat-bar-text h4 { color: var(--orange); }
        .feat-bar-text p { font-size: 12px; color: var(--text-muted); }

        .feat-bar-item:nth-child(1) { animation: slideUp 0.5s ease-out 1s both; }
        .feat-bar-item:nth-child(2) { animation: slideUp 0.5s ease-out 1.15s both; }
        .feat-bar-item:nth-child(3) { animation: slideUp 0.5s ease-out 1.3s both; }

        footer { background: #0F172A; color: #94A3B8; padding: 80px 8% 40px 8%; animation: fadeIn 0.6s ease-out 1.2s both; }
        .footer-grid { display: grid; grid-template-columns: 1.5fr 1fr 1fr 1fr; gap: 40px; margin-bottom: 60px; }
        .footer-brand .logo {
            color: white; margin-bottom: 20px; font-size: 24px; font-weight: 800;
            display: flex; align-items: center; gap: 8px; text-decoration: none;
        }
        .footer-brand .logo span { color: var(--orange); }
        .footer-brand p { font-size: 13px; line-height: 1.6; margin-bottom: 24px; }
        .social-links { display: flex; gap: 16px; }
        .social-links a {
            width: 36px; height: 36px; background: #1E293B; border-radius: 50%;
            display: flex; align-items: center; justify-content: center; color: white;
            text-decoration: none !important; transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .social-links a:hover { background: var(--orange); transform: translateY(-5px) scale(1.1); }
        .footer-col h4 { color: white; font-size: 15px; font-weight: 700; margin-bottom: 24px; }
        .footer-links { list-style: none; display: flex; flex-direction: column; gap: 12px; }
        .footer-links li a { color: #94A3B8; font-size: 13px; text-decoration: none; transition: all 0.3s ease; }
        .footer-links li a:hover { color: white; }
        .footer-contact-info { display: flex; flex-direction: column; gap: 16px; }
        .contact-item { display: flex; gap: 12px; font-size: 13px; }
        .contact-item i { color: var(--orange); margin-top: 3px; }
        .footer-bottom { padding-top: 40px; border-top: 1px solid #1E293B; text-align: center; font-size: 12px; }

        .btn-close-auth {
            position: absolute; top: 30px; left: 40px;
            width: 44px; height: 44px; background: rgba(255, 255, 255, 0.08);
            backdrop-filter: blur(10px); border: 1px solid rgba(255, 255, 255, 0.1);
            color: #ffffff; border-radius: 50%; display: flex;
            align-items: center; justify-content: center; font-size: 18px;
            text-decoration: none; z-index: 100; cursor: pointer;
            transition: all 0.4s cubic-bezier(0.16, 1, 0.3, 1);
            animation: pulseGlow 2s ease-in-out infinite;
        }
        .btn-close-auth:hover {
            background: var(--orange); border-color: var(--orange); color: #ffffff;
            transform: scale(1.08) rotate(90deg); box-shadow: 0 8px 20px rgba(255, 84, 0, 0.3);
        }

        .radio-group-container {
            display: flex;
            gap: 12px;
            width: 100%;
            margin-top: 4px;
        }
        .radio-card {
            flex: 1;
            position: relative;
            cursor: pointer;
        }
        .radio-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            width: 0;
            height: 0;
        }
        .radio-custom-box {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #FFFFFF 0%, #FAFBFC 100%);
            border: 2px solid var(--border-color);
            border-radius: 14px;
            font-size: 13px;
            font-weight: 700;
            color: var(--text-dark);
            transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .radio-card:hover .radio-custom-box {
            border-color: #CBD5E1;
            background-color: var(--bg-light);
        }
        .radio-card input[type="radio"]:checked+.radio-custom-box {
            border-color: var(--orange);
            background-color: rgba(255, 84, 0, 0.02);
            color: var(--orange);
            box-shadow: 0 0 12px rgba(255, 84, 0, 0.08);
        }
        .radio-custom-box i { font-size: 15px; }

        .input-wrapper input[type="date"] {
            padding: 14px 16px 14px 44px;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }
        .input-wrapper input[type="date"]::-webkit-calendar-picker-indicator {
            filter: invert(0.5);
            cursor: pointer;
        }

        /* Animations */
        @keyframes cardPopIn {
            0% { opacity: 0; transform: scale(0.8) translateY(40px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }
        @keyframes slideInLeft {
            0% { opacity: 0; transform: translateX(-50px); }
            100% { opacity: 1; transform: translateX(0); }
        }
        @keyframes fadeInUp {
            0% { opacity: 0; transform: translateY(20px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideUp {
            0% { opacity: 0; transform: translateY(30px); }
            100% { opacity: 1; transform: translateY(0); }
        }
        @keyframes fadeIn {
            0% { opacity: 0; }
            100% { opacity: 1; }
        }
        @keyframes bounceIn {
            0% { opacity: 0; transform: scale(0.3); }
            50% { transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255, 84, 0, 0); }
            50% { box-shadow: 0 0 0 8px rgba(255, 84, 0, 0.15); }
        }
        .shake { animation: shake 0.5s cubic-bezier(.36,.07,.19,.97) both; }
        @keyframes shake {
            10%, 90% { transform: translate3d(-1px, 0, 0); }
            20%, 80% { transform: translate3d(2px, 0, 0); }
            30%, 50%, 70% { transform: translate3d(-4px, 0, 0); }
            40%, 60% { transform: translate3d(4px, 0, 0); }
        }

        /* Floating basketball particles */
        .floating-balls {
            position: fixed;
            inset: 0;
            pointer-events: none;
            z-index: 1;
            overflow: hidden;
        }
        .float-ball {
            position: absolute;
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background: linear-gradient(135deg, #FF6B35, #CC3700);
            opacity: 0.08;
            animation: floatBall 15s ease-in-out infinite;
        }
        .float-ball::before {
            content: '';
            position: absolute;
            inset: 2px;
            border-radius: 50%;
            border: 1px solid rgba(0,0,0,0.2);
        }
        .float-ball:nth-child(1) { left: 10%; top: 20%; animation-delay: 0s; width: 30px; height: 30px; }
        .float-ball:nth-child(2) { left: 85%; top: 15%; animation-delay: 2s; width: 15px; height: 15px; }
        .float-ball:nth-child(3) { left: 70%; top: 60%; animation-delay: 4s; width: 25px; height: 25px; }
        .float-ball:nth-child(4) { left: 20%; top: 70%; animation-delay: 6s; width: 18px; height: 18px; }
        .float-ball:nth-child(5) { left: 50%; top: 40%; animation-delay: 8s; width: 22px; height: 22px; }
        .float-ball:nth-child(6) { left: 90%; top: 80%; animation-delay: 10s; width: 28px; height: 28px; }
        .float-ball:nth-child(7) { left: 5%; top: 50%; animation-delay: 12s; width: 16px; height: 16px; }
        .float-ball:nth-child(8) { left: 40%; top: 10%; animation-delay: 14s; width: 20px; height: 20px; }

        @keyframes floatBall {
            0%, 100% { transform: translateY(0) rotate(0deg) scale(1); }
            25% { transform: translateY(-30px) rotate(90deg) scale(1.1); }
            50% { transform: translateY(-15px) rotate(180deg) scale(0.95); }
            75% { transform: translateY(-40px) rotate(270deg) scale(1.05); }
        }

        body.swal2-shown, html.swal2-shown { padding-right: 0px !important; }

        @media (max-width: 992px) {
            .auth-hero-wrapper { grid-template-columns: 1fr; padding-top: 140px; text-align: center; }
            .auth-info .intro-p { margin: 0 auto 40px auto; }
            .info-list { align-items: center; margin-bottom: 40px; }
            .auth-card-container { justify-content: center; }
            .features-bar { grid-template-columns: 1fr; gap: 20px; }
            .footer-grid { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 576px) { .footer-grid { grid-template-columns: 1fr; } }

        html { scrollbar-width: none; -ms-overflow-style: none; }
        html::-webkit-scrollbar { display: none; }
    </style>
</head>

<body>

<!-- Floating basketball particles -->
<div class="floating-balls">
    <div class="float-ball"></div>
    <div class="float-ball"></div>
    <div class="float-ball"></div>
    <div class="float-ball"></div>
    <div class="float-ball"></div>
    <div class="float-ball"></div>
    <div class="float-ball"></div>
    <div class="float-ball"></div>
</div>

    <a href="login.php" class="btn-close-auth" title="Kembali ke Login">
        <i class="fa-solid fa-xmark"></i>
    </a>

    <div class="auth-hero-wrapper">
        <div class="auth-info">
            <h2>Gabung<br>Sekarang di <span>HoopBall</span></h2>
            <p class="intro-p">Buat akun tim kamu, mulai dominasi lapangan, dan nikmati berbagai kemudahan booking dalam
                satu genggaman.</p>

            <div class="info-list">
                <div class="info-item">
                    <div class="info-icon"><i class="fa-solid fa-bolt"></i></div>
                    <div class="info-text">
                        <h4>Daftar gratis & cepat</h4>
                        <p>Proses pendaftaran cepat kurang dari 1 menit.</p>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon"><i class="fa-solid fa-tags"></i></div>
                    <div class="info-text">
                        <h4>Promo anggota baru</h4>
                        <p>Dapatkan diskon sewa perdana setelah registrasi.</p>
                    </div>
                </div>
                <div class="info-item">
                    <div class="info-icon"><i class="fa-regular fa-star"></i></div>
                    <div class="info-text">
                        <h4>Akses prioritas</h4>
                        <p>Booking lapangan favorit dengan jadwal lebih awal.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="auth-card-container">
            <div class="auth-card">
                <div class="card-header-decoration">
                    <div class="card-icon-wrapper">
                        <i class="fa-solid fa-basketball"></i>
                    </div>
                </div>
                <h3>Daftar Akun</h3>
                <span class="card-subtitle">Mulai buat akun tim kamu sekarang.</span>

                <div class="step-indicator">
                    <div class="step-dot active" id="dot1">1</div>
                    <div class="step-line <?= ($res_status === 'error') ? 'active' : ''; ?>" id="line1"></div>
                    <div class="step-dot <?= ($res_status === 'error') ? 'active' : ''; ?>" id="dot2">2</div>
                </div>

                <form method="POST" id="registerForm" novalidate>

                    <!-- LANGKAH 1: DATA PRIBADI -->
                    <div class="form-step <?= ($res_status !== 'error') ? 'active' : ''; ?>" id="step1">
                        <div class="form-grid">
                            <div class="input-group">
                                <label>Nama Lengkap<span style="color: red;">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-user icon-left"></i>
                                    <input type="text" name="nama" id="namaField" placeholder="Budi Santoso"
                                        autocomplete="name" value="<?= htmlspecialchars($nama) ?>">
                                </div>
                                <span class="error-text" id="namaError"></span>
                            </div>

                            <div class="input-group">
                                <label>Nomor Telepon<span style="color: red;">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-phone icon-left"></i>
                                    <input type="text" name="telp" id="telpField" placeholder="0812xxxxxxxx"
                                        autocomplete="tel" maxlength="13" value="<?= htmlspecialchars($telp) ?>">
                                </div>
                                <span class="error-text" id="telpError"></span>
                            </div>

                            <div class="input-group">
                                <label>Jenis Kelamin<span style="color: red;">*</span></label>
                                <div class="radio-group-container">
                                    <label class="radio-card">
                                        <input type="radio" name="jk" value="1" <?= ($jk_input === '1' || $jk_input === 1) ? 'checked' : '' ?>>
                                        <span class="radio-custom-box">
                                            <i class="fa-solid fa-mars"></i> Laki-laki
                                        </span>
                                    </label>
                                    <label class="radio-card">
                                        <input type="radio" name="jk" value="0" <?= ($jk_input === '0' || $jk_input === 0) ? 'checked' : '' ?>>
                                        <span class="radio-custom-box">
                                            <i class="fa-solid fa-venus"></i> Perempuan
                                        </span>
                                    </label>
                                </div>
                                <span class="error-text" id="jkError"></span>
                            </div>

                            <div class="input-group">
                                <label>Tanggal Lahir<span style="color: red;">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-cake-candles icon-left"></i>
                                    <input type="date" name="tanggal_lahir" id="tglLahirField"
                                        value="<?= htmlspecialchars($tgl_lahir) ?>">
                                </div>
                                <span class="error-text" id="tglLahirError"></span>
                            </div>

                            <div class="input-group">
                                <label>Tempat Lahir<span style="color: red;">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-location-dot icon-left"></i>
                                    <input type="text" name="tempat_lahir" id="tmpLahirField"
                                        placeholder="Contoh: Jakarta, Bekasi, Bandung" autocomplete="off"
                                        value="<?= htmlspecialchars($tmp_lahir) ?>">
                                </div>
                                <span class="error-text" id="tmpLahirError"></span>
                            </div>

                            <div class="input-group">
                                <label>Alamat<span style="color: red;">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-location-dot icon-left"></i>
                                    <input type="text" name="alamat" id="alamatField"
                                        placeholder="Jl. Raya Cikarang No. 123" autocomplete="off" maxlength="100"
                                        value="<?= htmlspecialchars($alamat) ?>">
                                </div>
                                <span class="error-text" id="alamatError"></span>
                            </div>
                        </div>

                        <button type="button" class="btn-submit" id="btnNext" style="margin-top: 24px;">Lanjutkan</button>
                    </div>

                    <!-- LANGKAH 2: DATA AKUN -->
                    <div class="form-step <?= ($res_status === 'error') ? 'active' : ''; ?>" id="step2">
                        <div class="form-grid">
                            <div class="input-group">
                                <label>Nama Pengguna<span style="color: red;">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-signature icon-left"></i>
                                    <input type="text" name="username" id="usernameField" placeholder="budi_hoops"
                                        autocomplete="username" value="<?= htmlspecialchars($username) ?>">
                                </div>
                                <span class="error-text" id="usernameError"></span>
                            </div>

                            <div class="input-group">
                                <label>Email<span style="color: red;">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fa-regular fa-envelope icon-left"></i>
                                    <input type="email" name="email" id="emailField" placeholder="budi@gmail.com"
                                        autocomplete="email" value="<?= htmlspecialchars($email) ?>">
                                </div>
                                <span class="error-text" id="emailError"></span>
                            </div>

                            <div class="input-group">
                                <label>Kata Sandi<span style="color: red;">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-lock icon-left"></i>
                                    <input type="password" name="password" id="passwordInput"
                                        placeholder="Min. 8 Karakter" autocomplete="new-password">
                                    <i class="fa-solid fa-eye icon-right" id="togglePass"
                                        onclick="togglePassword()"></i>
                                </div>
                                <span class="error-text" id="passwordError"></span>
                            </div>

                            <div class="input-group">
                                <label>Konfirmasi Kata Sandi<span style="color: red;">*</span></label>
                                <div class="input-wrapper">
                                    <i class="fa-solid fa-lock icon-left"></i>
                                    <input type="password" name="password_confirm" id="passwordConfirmInput"
                                        placeholder="Ulangi Kata Sandi" autocomplete="new-password">
                                    <i class="fa-solid fa-eye icon-right" id="toggleConfirmPass"
                                        onclick="toggleConfirmPassword()"></i>
                                </div>
                                <span class="error-text" id="passwordConfirmError"></span>
                            </div>
                        </div>

                        <div class="step-buttons">
                            <button type="button" class="btn-back-step" id="btnBack">Sebelumnya</button>
                            <button type="submit" name="register" class="btn-submit">Daftar Sekarang</button>
                        </div>
                    </div>

                    <p class="card-footer">Sudah memiliki akun? <a href="login.php">Masuk Sekarang</a></p>
                </form>
            </div>
        </div>
    </div>

    <section class="features-bar">
        <div class="feat-bar-item">
            <div class="feat-bar-icon"><i class="fa-regular fa-circle-check"></i></div>
            <div class="feat-bar-text">
                <h4>Aman & Terpercaya</h4>
                <p>Data pribadi Anda aman dan kami lindungi.</p>
            </div>
        </div>
        <div class="feat-bar-item">
            <div class="feat-bar-icon"><i class="fa-regular fa-calendar"></i></div>
            <div class="feat-bar-text">
                <h4>Kelola dengan Mudah</h4>
                <p>Riwayat booking dan jadwal tersimpan rapi.</p>
            </div>
        </div>
        <div class="feat-bar-item">
            <div class="feat-bar-icon"><i class="fa-regular fa-star"></i></div>
            <div class="feat-bar-text">
                <h4>Pengalaman Premium</h4>
                <p>Nikmati fitur eksklusif khusus untuk member.</p>
            </div>
        </div>
    </section>

    <footer>
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="#" class="logo"><i class="fa-solid fa-basketball"
                        style="color: var(--orange)"></i>Hoop<span>Ball</span></a>
                <p>Platform penyewaan lapangan basket online yang mudah, cepat, dan terpercaya.</p>
                <div class="social-links">
                    <a href="#"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#"><i class="fa-brands fa-facebook"></i></a>
                    <a href="#"><i class="fa-brands fa-youtube"></i></a>
                    <a href="#"><i class="fa-brands fa-tiktok"></i></a>
                </div>
            </div>

            <div class="footer-col">
                <h4>Kontak Kami</h4>
                <div class="footer-contact-info">
                    <div class="contact-item"><i class="fa-solid fa-phone"></i><span>0812-3456-7890</span></div>
                    <div class="contact-item"><i class="fa-solid fa-envelope"></i><span>info@hoopball.id</span></div>
                    <div class="contact-item"><i class="fa-solid fa-location-dot"></i><span>Jl. Sunset Road No. 123,
                            Jakarta Selatan, 12050</span></div>
                </div>
            </div>

            <div class="footer-col">
                <h4>Tautan</h4>
                <ul class="footer-links">
                    <li><a href="../index.php#lapangan">Lapangan</a></li>
                    <li><a href="../index.php#jadwal">Jadwal</a></li>
                    <li><a href="../index.php#member">Alat Basket</a></li>
                    <li><a href="../index.php#tentang-kami">Tentang Kami</a></li>
                </ul>
            </div>

            <div class="footer-col">
                <h4>Informasi</h4>
                <ul class="footer-links">
                    <li><a href="#">Cara Pemesanan</a></li>
                    <li><a href="#">Syarat & Ketentuan</a></li>
                    <li><a href="#">Kebijakan Privasi</a></li>
                    <li><a href="#">FAQ</a></li>
                    <li><a href="#">Hubungi Kami</a></li>
                </ul>
            </div>
        </div>

        <div class="footer-bottom">
            <p>&copy; 2024 HoopBall. All rights reserved.</p>
        </div>
    </footer>

    <script>
        <?php if ($res_status !== ""): ?>
            Swal.fire({
                icon: '<?= $res_status ?>',
                title: '<?= ($res_status == "success") ? "Berhasil!" : "Gagal!" ?>',
                text: '<?= addslashes($res_msg) ?>',
                background: '#ffffff',
                color: '#1e293b',
                confirmButtonColor: '#FF5400'
            }).then(() => {
                <?php if ($res_status == "success"): ?>
                    window.location.href = 'login.php';
                <?php endif; ?>
            });
        <?php endif; ?>
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const step1 = document.getElementById('step1');
            const step2 = document.getElementById('step2');
            const btnNext = document.getElementById('btnNext');
            const btnBack = document.getElementById('btnBack');

            const dot1 = document.getElementById('dot1');
            const dot2 = document.getElementById('dot2');
            const line1 = document.getElementById('line1');

            const nama = document.getElementById('namaField');
            const telp = document.getElementById('telpField');
            const tglLahir = document.getElementById('tglLahirField');
            const tmpLahir = document.getElementById('tmpLahirField');
            const alamat = document.getElementById('alamatField');

            const username = document.getElementById('usernameField');
            const email = document.getElementById('emailField');
            const password = document.getElementById('passwordInput');
            const passwordConfirm = document.getElementById('passwordConfirmInput');

            const namaError = document.getElementById('namaError');
            const telpError = document.getElementById('telpError');
            const tglLahirError = document.getElementById('tglLahirError');
            const tmpLahirError = document.getElementById('tmpLahirError');
            const alamatError = document.getElementById('alamatError');
            const usernameError = document.getElementById('usernameError');
            const emailError = document.getElementById('emailError');
            const passwordError = document.getElementById('passwordError');
            const passwordConfirmError = document.getElementById('passwordConfirmError');

            function setValidationError(inputEl, errorEl, message) {
                inputEl.parentElement.classList.add('error');
                inputEl.parentElement.parentElement.classList.add('error-active');
                errorEl.textContent = message;
                errorEl.style.display = 'block';
            }

            function clearValidationError(inputEl, errorEl) {
                inputEl.parentElement.classList.remove('error');
                inputEl.parentElement.parentElement.classList.remove('error-active');
                errorEl.style.display = 'none';
            }

            nama.addEventListener('input', () => {
                nama.value = nama.value.replace(/[^a-zA-Z\s]/g, '');
            });

            tmpLahir.addEventListener('input', () => {
                tmpLahir.value = tmpLahir.value.replace(/[^a-zA-Z\s]/g, '');
            });

            telp.addEventListener('input', () => {
                telp.value = telp.value.replace(/[^0-9]/g, '');
            });

            btnNext.addEventListener('click', () => {
                let isStep1Valid = true;

                if (nama.value.trim() === '') {
                    setValidationError(nama, namaError, 'Nama lengkap wajib diisi.');
                    isStep1Valid = false;
                } else {
                    clearValidationError(nama, namaError);
                }

                const tglVal = tglLahir.value.trim();
                if (tglVal === '') {
                    setValidationError(tglLahir, tglLahirError, 'Tanggal lahir wajib diisi.');
                    isStep1Valid = false;
                } else {
                    const birthDate = new Date(tglVal);
                    const today = new Date();
                    const age = today.getFullYear() - birthDate.getFullYear();
                    const monthDiff = today.getMonth() - birthDate.getMonth();
                    const actualAge = (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) ? age - 1 : age;

                    if (actualAge < 10) {
                        setValidationError(tglLahir, tglLahirError, 'Usia minimal 10 tahun.');
                        isStep1Valid = false;
                    } else if (actualAge > 100) {
                        setValidationError(tglLahir, tglLahirError, 'Tanggal lahir tidak valid.');
                        isStep1Valid = false;
                    } else {
                        clearValidationError(tglLahir, tglLahirError);
                    }
                }

                const tmpVal = tmpLahir.value.trim();
                if (tmpVal === '') {
                    setValidationError(tmpLahir, tmpLahirError, 'Tempat lahir wajib diisi.');
                    isStep1Valid = false;
                } else if (tmpVal.length < 3) {
                    setValidationError(tmpLahir, tmpLahirError, 'Tempat lahir minimal 3 karakter.');
                    isStep1Valid = false;
                } else if (!/^[a-zA-Z\s]+$/.test(tmpVal)) {
                    setValidationError(tmpLahir, tmpLahirError, 'Tempat lahir hanya boleh huruf dan spasi.');
                    isStep1Valid = false;
                } else {
                    clearValidationError(tmpLahir, tmpLahirError);
                }

                const phonePattern = /^08[0-9]{8,11}$/;
                if (telp.value.trim() === '') {
                    setValidationError(telp, telpError, 'Nomor telepon wajib diisi.');
                    isStep1Valid = false;
                } else if (!phonePattern.test(telp.value.trim())) {
                    setValidationError(telp, telpError, 'Nomor telepon wajib berupa angka, diawali 08, dan panjang 10-13 digit.');
                    isStep1Valid = false;
                } else {
                    clearValidationError(telp, telpError);
                }

                const alamatValue = alamat.value.trim();
                const allowedCharsPattern = /^[a-zA-Z0-9\s,\.\/\-]+$/;
                const onlyNumbersPattern = /^[0-9\s]+$/;
                const onlySymbolsPattern = /^[^a-zA-Z0-9]+$/;

                if (alamatValue === '') {
                    setValidationError(alamat, alamatError, 'Alamat rumah wajib diisi.');
                    isStep1Valid = false;
                } else if (alamatValue.length < 10 || alamatValue.length > 100) {
                    setValidationError(alamat, alamatError, 'Alamat minimal 10 karakter dan maksimal 100 karakter.');
                    isStep1Valid = false;
                } else if (!allowedCharsPattern.test(alamatValue)) {
                    setValidationError(alamat, alamatError, 'Alamat hanya boleh menggunakan huruf, angka, spasi, koma (,), titik (.), garis miring (/), dan tanda strip (-).');
                    isStep1Valid = false;
                } else if (onlyNumbersPattern.test(alamatValue)) {
                    setValidationError(alamat, alamatError, 'Alamat tidak boleh hanya berupa angka murni.');
                    isStep1Valid = false;
                } else if (onlySymbolsPattern.test(alamatValue)) {
                    setValidationError(alamat, alamatError, 'Alamat tidak boleh hanya berupa simbol murni.');
                    isStep1Valid = false;
                } else {
                    clearValidationError(alamat, alamatError);
                }

                if (isStep1Valid) {
                    step1.classList.remove('active');
                    step2.classList.add('active');
                    dot2.classList.add('active');
                    line1.classList.add('active');
                }
            });

            btnBack.addEventListener('click', () => {
                step2.classList.remove('active');
                step1.classList.add('active');
                dot2.classList.remove('active');
                line1.classList.remove('active');
            });

            const form = document.getElementById('registerForm');
            form.addEventListener('submit', function (e) {
                let isStep2Valid = true;

                const usernameVal = username.value.trim();
                const usernamePattern = /^[a-zA-Z0-9\._]+$/;

                if (usernameVal === '') {
                    setValidationError(username, usernameError, 'Nama Pengguna wajib diisi.');
                    isStep2Valid = false;
                } else if (usernameVal.length < 3 || usernameVal.length > 20) {
                    setValidationError(username, usernameError, 'Nama Pengguna minimal 3 karakter dan maksimal 20 karakter.');
                    isStep2Valid = false;
                } else if (username.value.includes(' ')) {
                    setValidationError(username, usernameError, 'Nama Pengguna tidak boleh mengandung spasi.');
                    isStep2Valid = false;
                } else if (!usernamePattern.test(usernameVal)) {
                    setValidationError(username, usernameError, 'Nama Pengguna hanya boleh menggunakan huruf, angka, titik (.), dan underscore (_).');
                    isStep2Valid = false;
                } else {
                    clearValidationError(username, usernameError);
                }

                const emailVal = email.value.trim();
                const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

                if (emailVal === '') {
                    setValidationError(email, emailError, 'Email wajib diisi.');
                    isStep2Valid = false;
                } else if (!emailPattern.test(emailVal)) {
                    setValidationError(email, emailError, 'Format email yang dimasukkan tidak valid.');
                    isStep2Valid = false;
                } else if (!emailVal.toLowerCase().endsWith('@gmail.com')) {
                    setValidationError(email, emailError, 'Email wajib menggunakan domain @gmail.com.');
                    isStep2Valid = false;
                } else {
                    clearValidationError(email, emailError);
                }

                const passwordVal = password.value.trim();
                const hasLetter = /[a-zA-Z]/;
                const hasNumber = /[0-9]/;
                const simplePasswords = ['12345678', '87654321', 'password', 'qwertyui', '1234567890', 'hoopball', 'hoopball123'];

                if (passwordVal === '') {
                    setValidationError(password, passwordError, 'Kata sandi wajib diisi.');
                    isStep2Valid = false;
                } else if (passwordVal.length < 8) {
                    setValidationError(password, passwordError, 'Kata sandi minimal berisi 8 karakter.');
                    isStep2Valid = false;
                } else if (!hasLetter.test(passwordVal) || !hasNumber.test(passwordVal)) {
                    setValidationError(password, passwordError, 'Kata sandi harus berisi kombinasi huruf dan angka.');
                    isStep2Valid = false;
                } else if (simplePasswords.includes(passwordVal.toLowerCase())) {
                    setValidationError(password, passwordError, 'Kata sandi terlalu mudah ditebak. Silakan gunakan kombinasi lain.');
                    isStep2Valid = false;
                } else {
                    clearValidationError(password, passwordError);
                }

                const passwordConfirmVal = passwordConfirm.value.trim();
                if (passwordConfirmVal === '') {
                    setValidationError(passwordConfirm, passwordConfirmError, 'Konfirmasi kata sandi wajib diisi.');
                    isStep2Valid = false;
                } else if (passwordConfirmVal !== passwordVal) {
                    setValidationError(passwordConfirm, passwordConfirmError, 'Konfirmasi kata sandi tidak cocok.');
                    isStep2Valid = false;
                } else {
                    clearValidationError(passwordConfirm, passwordConfirmError);
                }

                if (!isStep2Valid) {
                    e.preventDefault();
                    const card = document.querySelector('.auth-card');
                    card.classList.remove('shake');
                    void card.offsetWidth;
                    card.classList.add('shake');
                }
            });

            const fields = [
                { el: nama, err: namaError },
                { el: telp, err: telpError },
                { el: tmpLahir, err: tmpLahirError },
                { el: alamat, err: alamatError },
                { el: username, err: usernameError },
                { el: email, err: emailError },
                { el: password, err: passwordError },
                { el: passwordConfirm, err: passwordConfirmError }
            ];

            fields.forEach(field => {
                field.el.addEventListener('input', () => {
                    clearValidationError(field.el, field.err);
                });
            });

            tglLahir.addEventListener('change', () => {
                clearValidationError(tglLahir, tglLahirError);
            });
        });

        function togglePassword() {
            const passInput = document.getElementById('passwordInput');
            const toggleIcon = document.getElementById('togglePass');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        function toggleConfirmPassword() {
            const passInput = document.getElementById('passwordConfirmInput');
            const toggleIcon = document.getElementById('toggleConfirmPass');
            if (passInput.type === 'password') {
                passInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }
    </script>
</body>

</html>