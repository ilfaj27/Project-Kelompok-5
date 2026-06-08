<?php
session_start();
include 'includes/config.php';

$res_status = "";
$res_msg = "";

if (isset($_POST['register'])) {
    $nama     = $_POST['nama'];
    $username = $_POST['username'];
    $email    = $_POST['email'];
    $telp     = $_POST['telp'];
    $jk       = $_POST['jk'];
    $password = $_POST['password'];
    $alamat   = $_POST['alamat'];

    $sql_check = "SELECT Username, Email FROM Akun WHERE Username = ? OR Email = ?";
    $stmt_check = sqlsrv_query($conn, $sql_check, array($username, $email));

    if (sqlsrv_has_rows($stmt_check)) {
        $res_status = "error";
        $res_msg = "Username atau Email sudah terdaftar!";
    } else {
        sqlsrv_begin_transaction($conn);

        $q_akn = sqlsrv_query($conn, "SELECT MAX(ID_Akun) as max_id FROM Akun");
        $d_akn = sqlsrv_fetch_array($q_akn, SQLSRV_FETCH_ASSOC);
        $num_akn = ($d_akn['max_id']) ? (int) substr($d_akn['max_id'], 3) + 1 : 1;
        $id_akun_baru = "AKN" . sprintf("%03d", $num_akn);

        $sql_akun = "INSERT INTO Akun (ID_Akun, Username, Email, Kata_Sandi, Role, Status_Akun) VALUES (?,?,?,?,3,1)";
        $stmt_akun = sqlsrv_query($conn, $sql_akun, array($id_akun_baru, $username, $email, $password));

        $q_cus = sqlsrv_query($conn, "SELECT MAX(ID_Customer) as max_id FROM Customer");
        $d_cus = sqlsrv_fetch_array($q_cus, SQLSRV_FETCH_ASSOC);
        $num_cus = ($d_cus['max_id']) ? (int) substr($d_cus['max_id'], 3) + 1 : 1;
        $id_cus_baru = "CUS" . sprintf("%03d", $num_cus);

        $sql_customer = "INSERT INTO Customer (ID_Customer, ID_Akun, Nama_Customer, Jenis_Kelamin, Alamat, No_Telepon) VALUES (?,?,?,?,?,?)";
        $stmt_customer = sqlsrv_query($conn, $sql_customer, array($id_cus_baru, $id_akun_baru, $nama, $jk, $alamat, $telp));

        if ($stmt_akun && $stmt_customer) {
            sqlsrv_commit($conn);
            $res_status = "success";
            $res_msg = "Pendaftaran Berhasil! Silahkan Login.";
        } else {
            sqlsrv_rollback($conn);
            $res_status = "error";
            $res_msg = "Terjadi kesalahan sistem saat mendaftar.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Registrasi | HoopBall BasketPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { --orange: #FF4500; --input-bg: #18181B; }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { background: #000; overflow-x: hidden; }

        .auth-page { 
            min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 50px 20px;
            background: linear-gradient(rgba(0,0,0,0.8), rgba(0,0,0,0.95)), 
                        url('https://images.unsplash.com/photo-1504450758481-7338eba7524a?q=80&w=2000');
            background-size: cover; background-position: center; background-attachment: fixed;
        }

        .auth-box { 
            background: rgba(10, 10, 10, 0.98); padding: 45px; border-radius: 20px; width: 100%; max-width: 750px; 
            border: 1px solid #222; box-shadow: 0 25px 50px rgba(0, 0, 0, 0.8);
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp { from { opacity: 0; transform: translateY(30px); } to { opacity: 1; transform: translateY(0); } }

        h1 { font-size: 32px; font-weight: 900; color: #fff; text-transform: uppercase; text-align: center; letter-spacing: -1px; }
        .subtitle { color: #666; font-size: 14px; margin-bottom: 40px; display: block; text-align: center; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .full-width { grid-column: span 2; }

        .input-group label { 
            display: block; 
            font-size: 11px; 
            font-weight: 800; 
            color: #555; 
            text-transform: uppercase; 
            margin-bottom: 8px; 
            letter-spacing: 1px; 
        }

        /* ===== INPUT GROUP KONSISTEN ===== */
        .input-group { position: relative; }
        .input-group .icon-right {
            position: absolute;
            right: 15px;
            bottom: 14px;
            color: #666;
            font-size: 14px;
            cursor: pointer;
            transition: 0.3s;
            z-index: 2;
            padding: 5px;
        }
        .input-group .icon-right:hover { color: var(--orange); }

        .input-group input,
        .input-group select {
            width: 100%;
            padding: 14px 45px 14px 14px;
            background: var(--input-bg);
            border: 1px solid #222;
            color: #fff;
            border-radius: 10px;
            font-size: 14px;
            transition: 0.3s;
        }
        .input-group input:focus,
        .input-group select:focus {
            border-color: var(--orange);
            outline: none;
            background: #000;
        }
        /* ================================== */

        .btn-reg { width: 100%; padding: 18px; background: var(--orange); color: #fff; border: none; border-radius: 10px; font-weight: 900; font-size: 15px; cursor: pointer; text-transform: uppercase; margin-top: 30px; transition: 0.3s; box-shadow: 0 10px 20px rgba(255, 69, 0, 0.2); }
        .btn-reg:hover { background: #ff5722; transform: translateY(-2px); }

        .footer-text { margin-top: 25px; text-align: center; font-size: 13px; color: #555; }
        .footer-text a { color: var(--orange); text-decoration: none; font-weight: 800; }

        .back-link { position: absolute; top: 30px; left: 40px; color: #fff; text-decoration: none; font-size: 14px; font-weight: 700; display: flex; align-items: center; gap: 10px; }
    </style>
</head>
<body class="auth-page">

    <a href="login.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Kembali</a>

    <div class="auth-box">
        <h1>GABUNG SEKARANG</h1>
        <span class="subtitle">Buat akun tim kamu dan mulai dominasi lapangan.</span>

        <form method="POST">
            <div class="form-grid">
                <div class="input-group">
                    <label>Nama Lengkap</label>
                    <input type="text" name="nama" placeholder="Budi Santoso" required>
                </div>
                <div class="input-group">
                    <label>Username</label>
                    <input type="text" name="username" placeholder="budi_hoops" required>
                </div>
                <div class="input-group">
                    <label>Email Gmail</label>
                    <input type="email" name="email" placeholder="budi@gmail.com" required>
                </div>
                <div class="input-group">
                    <label>Nomor Telepon</label>
                    <input type="text" name="telp" placeholder="0812xxxxxxxx" required pattern="[0-9]{10,14}">
                </div>
                <div class="input-group">
                    <label>Jenis Kelamin</label>
                    <select name="jk">
                        <option value="1">Laki-laki</option>
                        <option value="2">Perempuan</option>
                    </select>
                </div>
                <div class="input-group">
                    <label>Kata Sandi</label>
                    <input type="password" name="password" id="passwordInput" placeholder="Min. 6 Karakter" required minlength="6">
                    <i class="fa-solid fa-eye icon-right" id="togglePass" onclick="togglePassword()"></i>
                </div>
                <div class="input-group full-width">
                    <label>Alamat Rumah</label>
                    <input type="text" name="alamat" placeholder="Jl. Raya Cikarang No. 123" required>
                </div>
            </div>

            <button type="submit" name="register" class="btn-reg">Daftar Akun Sekarang</button>
        </form>

        <p class="footer-text">Sudah memiliki akun? <a href="login.php">Masuk Tim</a></p>
    </div>

    <script>
        <?php if ($res_status !== ""): ?>
            Swal.fire({
                icon: '<?= $res_status ?>',
                title: '<?= ($res_status == "success") ? "Berhasil!" : "Gagal!" ?>',
                text: '<?= $res_msg ?>',
                background: '#111',
                color: '#fff',
                confirmButtonColor: '#FF4500'
            }).then(() => {
                <?php if ($res_status == "success"): ?>
                    window.location.href = 'login.php';
                <?php endif; ?>
            });
        <?php endif; ?>
    </script>

    <script>
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
    </script>
</body>
</html>