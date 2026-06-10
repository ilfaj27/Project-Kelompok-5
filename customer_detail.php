<?php
session_start();
include '../includes/config.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard.php';</script>";
    exit();
}

// ═══════════════════════════════════════════
// HELPER: Safe SQLSRV Operations
// ═══════════════════════════════════════════
function safe_sqlsrv_query($conn, $sql, $params = [], $die_on_error = true) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        $errors = sqlsrv_errors();
        $error_details = [];
        if ($errors) {
            foreach ($errors as $error) {
                $error_details[] = "[SQLSTATE: " . $error['SQLSTATE'] . "] [Code: " . $error['code'] . "] " . $error['message'];
            }
        }
        $error_msg = implode(" | ", $error_details);
        error_log("[SQL ERROR] " . $error_msg . " | SQL: " . $sql . " | Params: " . json_encode($params));

        if ($die_on_error) {
            die("<div style='padding:20px;background:#fee;border:1px solid #fcc;border-radius:8px;font-family:sans-serif;margin:20px;'>
                <h3 style='color:#c00;margin:0 0 10px;'><i class='fa-solid fa-circle-exclamation'></i> Database Error</h3>
                <p style='color:#333;margin:0 0 5px;'><strong>Detail Error:</strong></p>
                <pre style='background:#fff;padding:10px;border-radius:4px;overflow-x:auto;font-size:12px;'>" . htmlspecialchars($error_msg) . "</pre>
                <p style='color:#666;font-size:12px;margin:10px 0 0;'>Silakan periksa koneksi database atau hubungi administrator.</p>
            </div>");
        }
        return false;
    }
    return $stmt;
}

function safe_sqlsrv_fetch_array($stmt, $fetch_type = SQLSRV_FETCH_ASSOC) {
    if ($stmt === false || $stmt === null) return false;
    return sqlsrv_fetch_array($stmt, $fetch_type);
}

// Get customer ID from URL
$customer_id = isset($_GET['id']) ? $_GET['id'] : null;

if (!$customer_id) {
    header("Location: customer.php");
    exit();
}

// Fetch customer data (exclude ID_Akun as requested)
$query = safe_sqlsrv_query($conn, 
    "SELECT ID_Customer, Nama_Customer, Jenis_Kelamin, Alamat, No_Telepon, Status, Is_Deleted, 
            Created_By, Created_Date, Modified_By, Modified_Date, Deleted_By, Deleted_Date 
     FROM Customer WHERE ID_Customer = ?", 
    array($customer_id));

$customer = safe_sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC);

if (!$customer) {
    header("Location: customer.php?status=error&msg=Data customer tidak ditemukan!");
    exit();
}

$map_jk = [1 => 'Laki-laki', 2 => 'Perempuan'];
$jk_icon = $customer['Jenis_Kelamin'] == 1 ? 'fa-mars' : 'fa-venus';
$jk_color = $customer['Jenis_Kelamin'] == 1 ? 'var(--blue)' : 'var(--pink)';
$jk_bg = $customer['Jenis_Kelamin'] == 1 ? 'var(--blue-lt)' : 'var(--pink-lt)';

// Status
$is_active = $customer['Status'] == 1;
$status_text = $is_active ? 'Aktif' : 'Nonaktif';
$status_color = $is_active ? 'var(--green)' : 'var(--red)';
$status_bg = $is_active ? 'var(--green-lt)' : 'var(--red-lt)';

// Format dates
function formatDate($date) {
    if (!$date) return '-';
    if ($date instanceof DateTime) return $date->format('d M Y H:i');
    return $date;
}

$nama = $_SESSION['nama'];
$role = $_SESSION['role'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Detail Customer | <?= htmlspecialchars($customer['Nama_Customer']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<style>
:root {
    --orange: #FF4500; --orange-lt: rgba(255,69,0,.10); --orange-dk: #E03E00;
    --green: #10B981; --green-lt: rgba(16,185,129,.10); --green-dk: #059669;
    --blue: #3B82F6; --blue-lt: rgba(59,130,246,.10);
    --pink: #EC4899; --pink-lt: rgba(236,72,153,.10);
    --red: #EF4444; --red-lt: rgba(239,68,68,.10); --red-dk: #DC2626;
    --yellow: #F59E0B; --yellow-lt: rgba(245,158,11,.10);
    --purple: #8B5CF6; --purple-lt: rgba(139,92,246,.10);
    --sidebar: #0D1117; --sidebar-w: 260px; --topbar-h: 70px;
    --card-bg: #FFFFFF; --border: #E5E7EB; --border-lt: #F3F4F6;
    --text: #111827; --text-md: #374151; --muted: #6B7280; --bg: #F3F4F6;
}
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
html { scroll-behavior: smooth; }
body { 
    font-family: 'Barlow', sans-serif; 
    background: var(--bg); 
    min-height: 100vh; 
    color: var(--text);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

/* ═══ MODAL OVERLAY ═══ */
.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.55);
    backdrop-filter: blur(8px);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 2000;
    padding: 20px;
    animation: fadeIn 0.3s ease;
}
@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

/* ═══ MODAL BOX ═══ */
.modal-box {
    background: #fff;
    border-radius: 24px;
    width: 560px;
    max-width: 95vw;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 25px 60px rgba(0,0,0,.2), 0 0 0 1px rgba(0,0,0,.05);
    position: relative;
    animation: slideUp 0.4s cubic-bezier(.16,1,.3,1);
}
@keyframes slideUp {
    from { transform: translateY(30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

/* ═══ HEADER ═══ */
.modal-header {
    padding: 32px 32px 24px;
    border-bottom: 1px solid var(--border-lt);
    position: relative;
}
.header-bg {
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 100px;
    background: linear-gradient(135deg, var(--orange) 0%, var(--orange-dk) 100%);
    border-radius: 24px 24px 0 0;
}
.header-content {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: flex-end;
    gap: 20px;
    margin-top: 20px;
}

/* ═══ AVATAR ═══ */
.avatar-wrap {
    width: 90px;
    height: 90px;
    border-radius: 50%;
    background: #fff;
    padding: 4px;
    box-shadow: 0 4px 20px rgba(0,0,0,.15);
    flex-shrink: 0;
}
.avatar {
    width: 100%;
    height: 100%;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    color: #fff;
    background: linear-gradient(135deg, var(--orange) 0%, var(--orange-dk) 100%);
}

.header-text { flex: 1; }
.header-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .5px;
    margin-bottom: 8px;
}
.header-tag.active {
    background: var(--green-lt);
    color: var(--green);
}
.header-tag.inactive {
    background: var(--red-lt);
    color: var(--red);
}
.header-title {
    font-family: 'Barlow Condensed', sans-serif;
    font-size: 28px;
    font-weight: 900;
    color: var(--text);
    line-height: 1.1;
    letter-spacing: -.5px;
}
.header-id {
    font-size: 13px;
    color: var(--muted);
    font-weight: 600;
    margin-top: 4px;
    font-family: monospace;
}

/* ═══ CLOSE BUTTON ═══ */
.btn-close {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    border: none;
    background: rgba(255,255,255,.2);
    backdrop-filter: blur(4px);
    color: #fff;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    transition: all .2s;
    z-index: 10;
}
.btn-close:hover {
    background: rgba(255,255,255,.3);
    transform: rotate(90deg);
}

/* ═══ BODY ═══ */
.modal-body {
    padding: 24px 32px 32px;
}

/* ═══ INFO GRID ═══ */
.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 24px;
}
.info-card {
    background: var(--bg);
    border-radius: 16px;
    padding: 20px;
    border: 1px solid var(--border-lt);
    transition: all .2s;
}
.info-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(0,0,0,.06);
    border-color: var(--orange);
}
.info-card.full-width {
    grid-column: span 2;
}
.info-icon {
    width: 40px;
    height: 40px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    margin-bottom: 12px;
}
.info-label {
    font-size: 10px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: var(--muted);
    margin-bottom: 6px;
}
.info-value {
    font-size: 15px;
    font-weight: 700;
    color: var(--text);
    line-height: 1.4;
}
.info-value.small {
    font-size: 13px;
}

/* ═══ GENDER BADGE ═══ */
.gender-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
}

/* ═══ AUDIT TIMELINE ═══ */
.audit-section {
    margin-top: 24px;
    padding-top: 24px;
    border-top: 2px solid var(--border-lt);
}
.audit-title {
    font-size: 12px;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .8px;
    color: var(--muted);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.audit-timeline {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
.audit-item {
    display: flex;
    gap: 12px;
    align-items: flex-start;
    padding: 12px 16px;
    border-radius: 12px;
    background: var(--bg);
    border: 1px solid var(--border-lt);
}
.audit-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-top: 4px;
    flex-shrink: 0;
}
.audit-dot.created { background: var(--green); box-shadow: 0 0 0 3px var(--green-lt); }
.audit-dot.modified { background: var(--blue); box-shadow: 0 0 0 3px var(--blue-lt); }
.audit-dot.deleted { background: var(--red); box-shadow: 0 0 0 3px var(--red-lt); }
.audit-content { flex: 1; }
.audit-action {
    font-size: 12px;
    font-weight: 700;
    color: var(--text);
    margin-bottom: 2px;
}
.audit-user {
    font-size: 11px;
    color: var(--muted);
    font-weight: 600;
}
.audit-user strong { color: var(--text-md); }
.audit-time {
    font-size: 11px;
    color: var(--muted);
    font-family: monospace;
    margin-top: 2px;
}

/* ═══ FOOTER ACTIONS ═══ */
.modal-footer {
    padding: 20px 32px 32px;
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 800;
    text-decoration: none;
    cursor: pointer;
    transition: all .2s;
    border: none;
    font-family: 'Barlow', sans-serif;
    text-transform: uppercase;
    letter-spacing: .5px;
}
.btn-back {
    background: var(--text);
    color: #fff;
}
.btn-back:hover {
    background: var(--orange);
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(255,69,0,.3);
}
.btn-delete {
    background: var(--red-lt);
    color: var(--red);
    border: 1.5px solid var(--red);
}
.btn-delete:hover {
    background: var(--red);
    color: #fff;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(239,68,68,.3);
}

/* ═══ SCROLLBAR ═══ */
.modal-box::-webkit-scrollbar { width: 6px; }
.modal-box::-webkit-scrollbar-track { background: transparent; }
.modal-box::-webkit-scrollbar-thumb { background: var(--border); border-radius: 3px; }
.modal-box::-webkit-scrollbar-thumb:hover { background: var(--muted); }

/* ═══ RESPONSIVE ═══ */
@media(max-width: 640px) {
    .info-grid { grid-template-columns: 1fr; }
    .info-card.full-width { grid-column: span 1; }
    .header-content { flex-direction: column; align-items: center; text-align: center; }
    .modal-header { padding: 24px 20px 20px; }
    .modal-body { padding: 20px; }
    .modal-footer { padding: 16px 20px 24px; flex-direction: column; }
    .btn { justify-content: center; }
}
</style>
</head>
<body>

<div class="modal-overlay" onclick="if(event.target === this) window.location.href='customer.php'">
    <div class="modal-box">

        <!-- ═══ HEADER ═══ -->
        <div class="modal-header">
            <div class="header-bg"></div>
            <button class="btn-close" onclick="window.location.href='customer.php'" title="Tutup">
                <i class="fa-solid fa-xmark"></i>
            </button>

            <div class="header-content">
                <div class="avatar-wrap">
                    <div class="avatar">
                        <i class="fa-solid <?= $jk_icon ?>"></i>
                    </div>
                </div>
                <div class="header-text">
                    <div class="header-tag <?= $is_active ? 'active' : 'inactive' ?>">
                        <i class="fa-solid fa-circle" style="font-size:6px;"></i>
                        <?= $status_text ?>
                    </div>
                    <div class="header-title"><?= htmlspecialchars($customer['Nama_Customer']) ?></div>
                    <div class="header-id">
                        <i class="fa-solid fa-fingerprint" style="margin-right:4px;"></i>
                        <?= htmlspecialchars($customer['ID_Customer']) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ BODY ═══ -->
        <div class="modal-body">

            <!-- Info Grid -->
            <div class="info-grid">
                <!-- Gender -->
                <div class="info-card">
                    <div class="info-icon" style="background: <?= $jk_bg ?>; color: <?= $jk_color ?>;">
                        <i class="fa-solid <?= $jk_icon ?>"></i>
                    </div>
                    <div class="info-label">Jenis Kelamin</div>
                    <div class="info-value">
                        <span class="gender-badge" style="background: <?= $jk_bg ?>; color: <?= $jk_color ?>;">
                            <i class="fa-solid <?= $jk_icon ?>"></i>
                            <?= $map_jk[$customer['Jenis_Kelamin']] ?? 'Tidak Diketahui' ?>
                        </span>
                    </div>
                </div>

                <!-- Phone -->
                <div class="info-card">
                    <div class="info-icon" style="background: var(--purple-lt); color: var(--purple);">
                        <i class="fa-solid fa-phone"></i>
                    </div>
                    <div class="info-label">No. Telepon</div>
                    <div class="info-value"><?= htmlspecialchars($customer['No_Telepon']) ?></div>
                </div>

                <!-- Address (Full Width) -->
                <div class="info-card full-width">
                    <div class="info-icon" style="background: var(--yellow-lt); color: var(--yellow);">
                        <i class="fa-solid fa-location-dot"></i>
                    </div>
                    <div class="info-label">Alamat Lengkap</div>
                    <div class="info-value small"><?= htmlspecialchars($customer['Alamat']) ?></div>
                </div>
            </div>

            <!-- Audit Timeline -->
            <div class="audit-section">
                <div class="audit-title">
                    <i class="fa-solid fa-clock-rotate-left" style="color: var(--orange);"></i>
                    Riwayat Audit
                </div>
                <div class="audit-timeline">

                    <!-- Created -->
                    <div class="audit-item">
                        <div class="audit-dot created"></div>
                        <div class="audit-content">
                            <div class="audit-action">Data Dibuat</div>
                            <div class="audit-user">oleh <strong><?= htmlspecialchars($customer['Created_By'] ?? 'SYSTEM') ?></strong></div>
                            <div class="audit-time">
                                <i class="fa-regular fa-calendar" style="margin-right:4px;"></i>
                                <?= formatDate($customer['Created_Date']) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Modified (if exists) -->
                    <?php if (!empty($customer['Modified_By'])): ?>
                    <div class="audit-item">
                        <div class="audit-dot modified"></div>
                        <div class="audit-content">
                            <div class="audit-action">Data Diubah</div>
                            <div class="audit-user">oleh <strong><?= htmlspecialchars($customer['Modified_By']) ?></strong></div>
                            <div class="audit-time">
                                <i class="fa-regular fa-calendar" style="margin-right:4px;"></i>
                                <?= formatDate($customer['Modified_Date']) ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Deleted (if exists) -->
                    <?php if (!empty($customer['Deleted_By'])): ?>
                    <div class="audit-item" style="border-color: var(--red-lt); background: var(--red-lt);">
                        <div class="audit-dot deleted"></div>
                        <div class="audit-content">
                            <div class="audit-action" style="color: var(--red);">Data Dihapus</div>
                            <div class="audit-user">oleh <strong><?= htmlspecialchars($customer['Deleted_By']) ?></strong></div>
                            <div class="audit-time">
                                <i class="fa-regular fa-calendar" style="margin-right:4px;"></i>
                                <?= formatDate($customer['Deleted_Date']) ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- ═══ FOOTER ═══ -->
        <div class="modal-footer">
            <button class="btn btn-delete" onclick="confirmDelete('<?= $customer['ID_Customer'] ?>', '<?= htmlspecialchars($customer['Nama_Customer']) ?>')">
                <i class="fa-solid fa-trash-can"></i>
                Hapus
            </button>
            <a href="customer.php" class="btn btn-back">
                <i class="fa-solid fa-arrow-left"></i>
                Kembali
            </a>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    Swal.fire({
        title: 'Hapus Customer?',
        html: `Anda akan menghapus data <strong style="color:var(--orange);">${name}</strong><br>Data akan dihapus <strong style="color:var(--red);">permanen</strong>!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'customer.php?delete_id=' + id;
        }
    });
}

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        window.location.href = 'customer.php';
    }
});
</script>

</body>
</html>