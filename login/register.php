<?php
session_start();
$path_prefix = "../";
include '../includes/config.php';

$res_status = "";
$res_msg = "";

// Tambahkan inisialisasi ini:
$nama = "";
$username = "";
$email = "";
$telp = "";
$jk_input = ""; // <-- DIUBAH: kosongkan default, biarkan user memilih
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

    // --- DIUBAH MENGGUNAKAN SP: Cek duplikat Username, Email, atau Nomor Telepon ---
    $sql_check = "EXEC dbo.sp_CheckCustomerDuplicate ?, ?, ?";
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

        // --- DIUBAH MENGGUNAKAN SP: Simpan data customer baru ---
        $sql_customer = "EXEC dbo.sp_CreateCustomer ?, ?, ?, ?, ?, ?, ?, ?, ?";

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

// --- HITUNG TANGGAL MAX UNTUK USIA MINIMAL 10 TAHUN ---
// Saat ini 2026-07-03, jadi max date = 2016-07-03 (usia minimal 10 tahun)
$max_date = date('Y-m-d'); // Max date is today, age validation handled in JS
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
    <link rel="stylesheet" href="tampilan.css">
    <link rel="stylesheet" href="../asset/css/navbar_footer.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>

        .auth-info {
        align-self: start !important; /* <-- Menempelkan posisi ke bagian atas */
        margin-top: 130px !important;  /* <-- Mengatur jarak turunnya dari atas agar pas */
        }

        .form-step {
            display: none;
            animation: fadeStep 0.4s ease-out forwards;
        }

        .form-step.active {
            display: block;
        }

        @keyframes fadeStep {
            from {
                opacity: 0;
                transform: translateY(8px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 16px;
            margin-bottom: 24px;
        }

        .input-wrapper select {
            appearance: none;
            -webkit-appearance: none;
            cursor: pointer;
        }

        .footer-brand .logo span {
            color: var(--orange);
        }

        /* ====== STYLE BARU UNTUK JENIS KELAMIN ====== */
        .radio-group-container {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .radio-group-container .radio-card {
            flex: 1;
            min-width: 120px;
            cursor: pointer;
        }

        .radio-group-container .radio-card input[type="radio"] {
            display: none;
        }

        .radio-group-container .radio-card .radio-custom-box {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            background: #f8fafc;
            color: #94a3b8; /* abu-abu */
            font-weight: 600;
            font-size: 14px;
            transition: all 0.25s ease;
            cursor: pointer;
        }

        .radio-group-container .radio-card .radio-custom-box i {
            font-size: 16px;
        }

        /* Hover state */
        .radio-group-container .radio-card:hover .radio-custom-box {
            border-color: #cbd5e1;
            background: #f1f5f9;
        }

        /* Checked state - warna aktif */
        .radio-group-container .radio-card input[type="radio"]:checked + .radio-custom-box {
            border-color: #FF5400;
            background: #fff7ed;
            color: #FF5400;
            box-shadow: 0 0 0 3px rgba(255, 84, 0, 0.15);
        }

        /* Placeholder / belum dipilih state */
        .radio-group-container .radio-card input[type="radio"]:not(:checked) + .radio-custom-box {
            color: #94a3b8; /* abu-abu */
        }

        /* Error state */
        .radio-group-container.error .radio-custom-box {
            border-color: #ef4444;
            background: #fef2f2;
        }

        .radio-group-container.error-active ~ .error-text {
            display: block;
        }

        @media (max-width: 992px) {
            .auth-hero-wrapper {
                grid-template-columns: 1fr;
                padding-top: 140px;
                text-align: center;
            }

            .auth-info .intro-p {
                margin: 0 auto 40px auto;
            }

            .info-list {
                align-items: center;
                margin-bottom: 40px;
            }

            .auth-card-container {
                justify-content: center;
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
                                <div class="radio-group-container" id="jkContainer">
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
                                        value="<?= htmlspecialchars($tgl_lahir) ?>"
                                        max="<?= $max_date ?>"
                                        min="1900-01-01"
                                        onfocus="this.showPicker()">
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

                        <button type="button" class="btn-submit" id="btnNext"
                            style="margin-top: 24px;">Lanjutkan</button>
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

    <?php include 'features_bar.php'; ?>

    <?php include '../includes/footer.php'; ?>

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
            // Tanggal Lahir picker - no auto-fill, calendar opens freely
            const tglLahirField = document.getElementById('tglLahirField');

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
            const jkContainer = document.getElementById('jkContainer');
            const jkRadios = document.querySelectorAll('input[name="jk"]');

            const username = document.getElementById('usernameField');
            const email = document.getElementById('emailField');
            const password = document.getElementById('passwordInput');
            const passwordConfirm = document.getElementById('passwordConfirmInput');

            const namaError = document.getElementById('namaError');
            const telpError = document.getElementById('telpError');
            const tglLahirError = document.getElementById('tglLahirError');
            const tmpLahirError = document.getElementById('tmpLahirError');
            const alamatError = document.getElementById('alamatError');
            const jkError = document.getElementById('jkError');
            const usernameError = document.getElementById('usernameError');
            const emailError = document.getElementById('emailError');
            const passwordError = document.getElementById('passwordError');
            const passwordConfirmError = document.getElementById('passwordConfirmError');

            function setValidationError(inputEl, errorEl, message) {
                if (inputEl.classList.contains('radio-group-container')) {
                    inputEl.classList.add('error');
                    inputEl.classList.add('error-active');
                } else {
                    inputEl.parentElement.classList.add('error');
                    inputEl.parentElement.parentElement.classList.add('error-active');
                }
                errorEl.textContent = message;
                errorEl.style.display = 'block';
            }

            function clearValidationError(inputEl, errorEl) {
                if (inputEl.classList.contains('radio-group-container')) {
                    inputEl.classList.remove('error');
                    inputEl.classList.remove('error-active');
                } else {
                    inputEl.parentElement.classList.remove('error');
                    inputEl.parentElement.parentElement.classList.remove('error-active');
                }
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

            // Clear jk error saat user memilih salah satu radio
            jkRadios.forEach(radio => {
                radio.addEventListener('change', () => {
                    clearValidationError(jkContainer, jkError);
                });
            });

            btnNext.addEventListener('click', () => {
                let isStep1Valid = true;

                if (nama.value.trim() === '') {
                    setValidationError(nama, namaError, 'Nama lengkap wajib diisi.');
                    isStep1Valid = false;
                } else {
                    clearValidationError(nama, namaError);
                }

                // Validasi Jenis Kelamin - belum dipilih?
                const jkSelected = document.querySelector('input[name="jk"]:checked');
                if (!jkSelected) {
                    setValidationError(jkContainer, jkError, 'Jenis kelamin wajib dipilih.');
                    isStep1Valid = false;
                } else {
                    clearValidationError(jkContainer, jkError);
                }

                const tglVal = tglLahir.value.trim();
                if (tglVal === '') {
                    setValidationError(tglLahir, tglLahirError, 'Tanggal lahir wajib diisi.');
                    isStep1Valid = false;
                } else {
                    // Validasi usia minimal 10 tahun
                    const birthDate = new Date(tglVal);
                    const today = new Date();
                    const minBirthDate = new Date();
                    minBirthDate.setFullYear(today.getFullYear() - 10);

                    const age = today.getFullYear() - birthDate.getFullYear();
                    const monthDiff = today.getMonth() - birthDate.getMonth();
                    const actualAge = (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) ? age - 1 : age;

                    if (birthDate > minBirthDate) {
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