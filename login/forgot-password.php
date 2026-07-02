<?php
session_start();
$path_prefix = "../";
include '../includes/config.php';

$res_status = "";
$res_msg = "";
$is_verified = isset($_SESSION['reset_id_customer']);

$username_input = "";
$tanggal_input = "";
$nominal_input = "";

// TAHAP 1: VERIFIKASI KEAMANAN DATA AKUN & RIWAYAT TRANSAKSI
if (isset($_POST['verify_account'])) {
    $username_input = trim($_POST['username_input'] ?? '');
    $nominal_input = trim($_POST['nominal_input'] ?? '');
    $tanggal_input = trim($_POST['tanggal_input'] ?? '');

    $sql = "SELECT ID_Customer FROM Customer 
            WHERE Username = ? AND Is_Deleted = 0";
    $params = array($username_input);
    $stmt = sqlsrv_query($conn, $sql, $params);

    if ($stmt === false) {
        $res_status = "error";
        $res_msg = "Terjadi kesalahan sistem koneksi database.";
    } else {
        $row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
        if ($row) {
            $id_customer = $row['ID_Customer'];

            $sql_booking = "SELECT TOP 1 Tanggal_Booking, Total_Bayar 
                            FROM Booking 
                            WHERE ID_Customer = ? 
                            ORDER BY Tanggal_Booking DESC";
            $stmt_booking = sqlsrv_query($conn, $sql_booking, array($id_customer));

            if ($stmt_booking === false) {
                $res_status = "error";
                $res_msg = "Terjadi kesalahan saat memverifikasi riwayat transaksi.";
            } else {
                $row_booking = sqlsrv_fetch_array($stmt_booking, SQLSRV_FETCH_ASSOC);

                if ($row_booking) {
                    $db_tanggal = $row_booking['Tanggal_Booking'];
                    if ($db_tanggal instanceof DateTime) {
                        $db_tanggal_str = $db_tanggal->format('Y-m-d');
                    } else {
                        $db_tanggal_str = date('Y-m-d', strtotime($db_tanggal));
                    }

                    $db_nominal = (int) $row_booking['Total_Bayar'];
                    $clean_nominal_input = (int) preg_replace('/[^0-9]/', '', $nominal_input);

                    if ($db_tanggal_str === $tanggal_input && $db_nominal === $clean_nominal_input) {
                        $is_verified = true;
                        $_SESSION['reset_id_customer'] = $id_customer;
                    } else {
                        $res_status = "error";
                        $res_msg = "Data verifikasi salah. Detail riwayat transaksi terakhir tidak cocok!";
                    }
                } else {
                    $clean_nominal_input = (int) preg_replace('/[^0-9]/', '', $nominal_input);
                    if (empty($tanggal_input) && $clean_nominal_input === 0) {
                        $is_verified = true;
                        $_SESSION['reset_id_customer'] = $id_customer;
                    } else {
                        $res_status = "error";
                        $res_msg = "Data verifikasi salah. Akun Anda belum memiliki riwayat transaksi.";
                    }
                }
            }
        } else {
            $res_status = "error";
            $res_msg = "Data verifikasi salah. Nama pengguna tidak ditemukan!";
        }
    }
}

// TAHAP 2: RESET/UPDATE KATA SANDI BARU
if (isset($_POST['reset_password'])) {
    if (isset($_SESSION['reset_id_customer'])) {
        $id_customer = $_SESSION['reset_id_customer'];
        $new_pass = trim($_POST['new_password']);

        $sql_old = "SELECT Kata_Sandi FROM Customer WHERE ID_Customer = ?";
        $q_old = sqlsrv_query($conn, $sql_old, array($id_customer));
        $d_old = sqlsrv_fetch_array($q_old, SQLSRV_FETCH_ASSOC);
        $old_pass = $d_old['Kata_Sandi'] ?? '';

        if (empty($new_pass)) {
            $res_status = "error";
            $res_msg = "Kata sandi baru tidak boleh kosong!";
        } else if (strlen($new_pass) < 8) {
            $res_status = "error";
            $res_msg = "Kata sandi baru minimal harus berisi 8 karakter!";
        } else if ($new_pass === $old_pass) {
            $res_status = "error";
            $res_msg = "Kata sandi baru tidak boleh sama dengan kata sandi lama Anda!";
        } else {
            $sql = "UPDATE Customer SET Kata_Sandi = ? WHERE ID_Customer = ?";
            $stmt = sqlsrv_query($conn, $sql, array($new_pass, $id_customer));

            if ($stmt !== false) {
                unset($_SESSION['reset_id_customer']);
                $res_status = "success";
                $res_msg = "Kata Sandi Berhasil Diperbarui! Silakan Login Kembali.";
            } else {
                $res_status = "error";
                $res_msg = "Gagal memperbarui kata sandi di sistem database.";
            }
        }
    } else {
        $res_status = "error";
        $res_msg = "Sesi verifikasi telah kedaluwarsa. Silakan verifikasi ulang.";
    }
}
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Kata Sandi | HoopBall BasketPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="tampilan.css">
    <link rel="stylesheet" href="../asset/css/navbar_footer.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>

        .auth-info {
        align-self: start !important; /* <-- Menempelkan posisi ke bagian atas */
        margin-top: 150px !important;  /* <-- Mengatur jarak turunnya dari atas agar pas */
        }

        .feat-bar-item:nth-child(1) {
            animation: slideUp 0.5s ease-out 1s both;
        }

        .feat-bar-item:nth-child(2) {
            animation: slideUp 0.5s ease-out 1.15s both;
        }

        .feat-bar-item:nth-child(3) {
            animation: slideUp 0.5s ease-out 1.3s both;
        }

        .footer-brand .logo span {
            color: var(--orange);
        }

        .float-ball:nth-child(1) {
            left: 10%;
            top: 20%;
            animation-delay: 0s;
            width: 30px;
            height: 30px;
        }

        .float-ball:nth-child(2) {
            left: 85%;
            top: 15%;
            animation-delay: 2s;
            width: 15px;
            height: 15px;
        }

        .float-ball:nth-child(3) {
            left: 70%;
            top: 60%;
            animation-delay: 4s;
            width: 25px;
            height: 25px;
        }

        .float-ball:nth-child(4) {
            left: 20%;
            top: 70%;
            animation-delay: 6s;
            width: 18px;
            height: 18px;
        }

        .float-ball:nth-child(5) {
            left: 50%;
            top: 40%;
            animation-delay: 8s;
            width: 22px;
            height: 22px;
        }

        .float-ball:nth-child(6) {
            left: 90%;
            top: 80%;
            animation-delay: 10s;
            width: 28px;
            height: 28px;
        }

        .float-ball:nth-child(7) {
            left: 5%;
            top: 50%;
            animation-delay: 12s;
            width: 16px;
            height: 16px;
        }

        .float-ball:nth-child(8) {
            left: 40%;
            top: 10%;
            animation-delay: 14s;
            width: 20px;
            height: 20px;
        }

        @media (max-width: 992px) {
            .auth-hero-wrapper {
                grid-template-columns: 1fr;
                padding-top: 140px;
                text-align: center;
            }

            .footer-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 576px) {
            .footer-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>

<body>

    <?php include 'floating_balls.php'; ?>

    <!-- TOMBOL SILANG (X) KEMBALI KE LOGIN -->
    <a href="login.php" class="btn-close-auth" title="Batal & Kembali">
        <i class="fa-solid fa-xmark"></i>
    </a>

    <!-- AUTH HERO SECTION -->
    <div class="auth-hero-wrapper">
        <div class="auth-info">
            <h2>Atur Ulang<br><span>Kata Sandi Anda</span></h2>
            <p class="intro-p">Verifikasi identitas kepemilikan akun terdaftar Anda terlebih dahulu untuk membuat kata
                sandi baru.</p>
        </div>

        <div class="auth-card-container">
            <div class="auth-card">
                <div class="card-header-decoration">
                    <div class="card-icon-wrapper">
                        <i class="fa-solid fa-key"></i>
                    </div>
                </div>
                <h3>Lupa Kata Sandi</h3>

                <?php if (!$is_verified): ?>
                    <span class="card-subtitle">Silakan isi data keamanan akun Anda.</span>
                    <form method="POST" id="verifyForm" novalidate>
                        <!-- USERNAME -->
                        <div class="input-group">
                            <label>Nama Pengguna Terdaftar</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-signature icon-left"></i>
                                <input type="text" name="username_input" id="usernameField" placeholder="budi_hoops"
                                    value="<?= htmlspecialchars($username_input) ?>">
                            </div>
                            <span class="error-text" id="usernameError"></span>
                        </div>

                        <!-- TANGGAL BOOKING TERAKHIR -->
                        <div class="input-group">
                            <label>Tanggal Booking Terakhir</label>
                            <div class="input-wrapper">
                                <i class="fa-regular fa-calendar-days icon-left"></i>
                                <input type="date" name="tanggal_input" id="tanggalField"
                                    value="<?= htmlspecialchars($tanggal_input) ?>">
                            </div>
                            <span class="error-text" id="tanggalError"></span>
                        </div>

                        <!-- NOMINAL PEMBAYARAN TERAKHIR -->
                        <div class="input-group">
                            <label>Nominal Pembayaran Terakhir (Rupiah)</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-money-bill-wave icon-left"></i>
                                <input type="text" name="nominal_input" id="nominalField" placeholder="Contoh: 150000"
                                    maxlength="10" value="<?= htmlspecialchars($nominal_input) ?>">
                            </div>
                            <span class="error-text" id="nominalError"></span>
                        </div>

                        <button type="submit" name="verify_account" class="btn-submit" style="margin-top: 10px;">Verifikasi
                            Akun</button>
                        <p class="card-footer">Kembali ke halaman <a href="login.php">Masuk</a></p>
                    </form>
                <?php else: ?>
                    <span class="card-subtitle" style="color:var(--orange);"><b>Akun Terverifikasi!</b> Tulis Kata Sandi
                        baru.</span>
                    <form method="POST" id="resetForm" novalidate>
                        <div class="input-group">
                            <label>Kata Sandi Baru</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-lock icon-left"></i>
                                <input type="password" name="new_password" id="passwordInput"
                                    placeholder="Min. 8 Karakter (Huruf & Angka)" maxlength="50">
                                <i class="fa-solid fa-eye icon-right" id="togglePass" onclick="togglePassword()"></i>
                            </div>
                            <span class="error-text" id="passwordError"></span>
                        </div>

                        <div class="input-group">
                            <label>Konfirmasi Kata Sandi Baru</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-lock icon-left"></i>
                                <input type="password" name="password_confirm" id="passwordConfirmInput"
                                    placeholder="Ulangi Kata Sandi" maxlength="50">
                                <i class="fa-solid fa-eye icon-right" id="toggleConfirmPass"
                                    onclick="toggleConfirmPassword()"></i>
                            </div>
                            <span class="error-text" id="passwordConfirmError"></span>
                        </div>

                        <button type="submit" name="reset_password" class="btn-submit" style="margin-top: 30px;">Reset Kata
                            Sandi</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php include 'features_bar.php'; ?>

    <?php include '../includes/footer.php'; ?>

    <!-- RESPONSE SWEETALERT -->
    <script>
        <?php if ($res_status !== ""): ?>
            Swal.fire({
                icon: '<?= $res_status ?>',
                title: '<?= ($res_status == "success") ? "Berhasil!" : "Gagal!" ?>',
                text: '<?= $res_msg ?>',
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

    <!-- VALIDASI JAVASCRIPT & EVENT HANDLERS -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {

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

            const verifyForm = document.getElementById('verifyForm');
            if (verifyForm) {
                const username = document.getElementById('usernameField');
                const tanggal = document.getElementById('tanggalField');
                const nominal = document.getElementById('nominalField');

                const usernameError = document.getElementById('usernameError');
                const tanggalError = document.getElementById('tanggalError');
                const nominalError = document.getElementById('nominalError');

                nominal.addEventListener('input', () => {
                    nominal.value = nominal.value.replace(/[^0-9]/g, '');
                });

                verifyForm.addEventListener('submit', function (e) {
                    let isValid = true;

                    const usernameVal = username.value.trim();
                    const usernamePattern = /^[a-zA-Z0-9\._]+$/;

                    if (usernameVal === '') {
                        setValidationError(username, usernameError, 'Nama Pengguna wajib diisi.');
                        isValid = false;
                    } else if (usernameVal.length < 3 || usernameVal.length > 30) {
                        setValidationError(username, usernameError, 'Nama Pengguna minimal 3 karakter dan maksimal 30 karakter.');
                        isValid = false;
                    } else if (username.value.includes(' ')) {
                        setValidationError(username, usernameError, 'Nama Pengguna tidak boleh menggunakan spasi.');
                        isValid = false;
                    } else if (!usernamePattern.test(usernameVal)) {
                        setValidationError(username, usernameError, 'Nama Pengguna hanya boleh berisi huruf, angka, titik (.), dan underscore (_).');
                        isValid = false;
                    } else {
                        clearValidationError(username, usernameError);
                    }

                    if (tanggal.value === '') {
                        setValidationError(tanggal, tanggalError, 'Tanggal booking terakhir wajib diisi.');
                        isValid = false;
                    } else {
                        clearValidationError(tanggal, tanggalError);
                    }

                    const nominalVal = nominal.value.trim();
                    if (nominalVal === '') {
                        setValidationError(nominal, nominalError, 'Nominal pembayaran terakhir wajib diisi.');
                        isValid = false;
                    } else {
                        clearValidationError(nominal, nominalError);
                    }

                    if (!isValid) {
                        e.preventDefault();
                        const card = document.querySelector('.auth-card');
                        card.classList.remove('shake');
                        void card.offsetWidth;
                        card.classList.add('shake');
                    }
                });

                const fieldsVerify = [
                    { el: username, err: usernameError },
                    { el: tanggal, err: tanggalError },
                    { el: nominal, err: nominalError }
                ];
                fieldsVerify.forEach(field => {
                    field.el.addEventListener('input', () => {
                        clearValidationError(field.el, field.err);
                    });
                });
            }

            const resetForm = document.getElementById('resetForm');
            if (resetForm) {
                const password = document.getElementById('passwordInput');
                const passwordConfirm = document.getElementById('passwordConfirmInput');

                const passwordError = document.getElementById('passwordError');
                const passwordConfirmError = document.getElementById('passwordConfirmError');

                resetForm.addEventListener('submit', function (e) {
                    let isValid = true;
                    const hasLetter = /[a-zA-Z]/;
                    const hasNumber = /[0-9]/;
                    const simplePasswords = ['12345678', '87654321', 'password', 'qwertyui', '1234567890', 'password123'];

                    const passwordVal = password.value.trim();
                    if (passwordVal === '') {
                        setValidationError(password, passwordError, 'Kata sandi baru wajib diisi.');
                        isValid = false;
                    } else if (passwordVal.length < 8 || passwordVal.length > 50) {
                        setValidationError(password, passwordError, 'Kata sandi baru minimal 8 karakter dan maksimal 50 karakter.');
                        isValid = false;
                    } else if (!hasLetter.test(passwordVal) || !hasNumber.test(passwordVal)) {
                        setValidationError(password, passwordError, 'Kata sandi baru harus berisi kombinasi huruf dan angka.');
                        isValid = false;
                    } else if (simplePasswords.includes(passwordVal.toLowerCase())) {
                        setValidationError(password, passwordError, 'Kata sandi terlalu mudah ditebak (seperti 12345678 atau password123). Gunakan kombinasi lain.');
                        isValid = false;
                    } else {
                        clearValidationError(password, passwordError);
                    }

                    const passwordConfirmVal = passwordConfirm.value.trim();
                    if (passwordConfirmVal === '') {
                        setValidationError(passwordConfirm, passwordConfirmError, 'Konfirmasi kata sandi wajib diisi.');
                        isValid = false;
                    } else if (passwordConfirmVal !== passwordVal) {
                        setValidationError(passwordConfirm, passwordConfirmError, 'Konfirmasi kata sandi tidak cocok.');
                        isValid = false;
                    } else {
                        clearValidationError(passwordConfirm, passwordConfirmError);
                    }

                    if (!isValid) {
                        e.preventDefault();
                        const card = document.querySelector('.auth-card');
                        card.classList.remove('shake');
                        void card.offsetWidth;
                        card.classList.add('shake');
                    }
                });

                const fieldsReset = [
                    { el: password, err: passwordError },
                    { el: passwordConfirm, err: passwordConfirmError }
                ];
                fieldsReset.forEach(field => {
                    field.el.addEventListener('input', () => {
                        clearValidationError(field.el, field.err);
                    });
                });
            }
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