<?php
session_start();
$path_prefix = "../";
include '../includes/config.php';
include '../includes/helpers.php';

if (!isset($_SESSION['role']) || ($_SESSION['role'] !== 'karyawan' && $_SESSION['role'] !== 'pemilik')) {
    echo "<script>alert('Akses Ditolak!'); window.location='../dashboard/dashboard.php';</script>";
    exit();
}

include '../includes/auth_profile.php';

// Pastikan variabel $nama tersedia dari auth_profile.php
if (!isset($nama) || empty($nama)) {
    $nama = $_SESSION['nama'] ?? $_SESSION['username'] ?? 'SYSTEM';
}

$topbar_title = 'Kelola Fasilitas';
$topbar_breadcrumb = 'Operasional / Fasilitas Lapangan';

// ── PROSES AJAX REQUESTS ──
$is_ajax = isset($_GET['action']) || isset($_POST['action']);
if ($is_ajax) {
    header('Content-Type: application/json');
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    // Action: Ambil Detail / Edit data (AJAX)
    if ($action === 'get_detail') {
        $id = intval($_GET['id'] ?? 0);
        $r = safeQuery($conn, "EXEC dbo.sp_GetFasilitasDetail ?", [$id]);
        if ($r && $row = safeFetch($r)) {
            echo json_encode(['success' => true, 'data' => $row]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'Data fasilitas tidak ditemukan.']);
        }
        exit();
    }

    // Action: Simpan Data (Tambah & Edit AJAX)
    if ($action === 'save') {
        $id = isset($_POST['id_fas']) ? intval($_POST['id_fas']) : 0;
        $nama_fasilitas = trim($_POST['nama_fasilitas'] ?? '');
        $detail_fasilitas = trim($_POST['detail_fasilitas'] ?? '');
        $stok_total = isset($_POST['stok_total']) ? intval($_POST['stok_total']) : 0;

        $errors = [];

        // Validasi format nama dan detail
        if (empty($nama_fasilitas)) {
            $errors[] = "Nama fasilitas wajib diisi!";
        } elseif (!preg_match('/^[a-zA-Z0-9\s\-]+$/', $nama_fasilitas)) {
            $errors[] = "Nama fasilitas hanya boleh berisi huruf, angka, spasi, dan tanda strip!";
        }

        if (empty($detail_fasilitas)) {
            $errors[] = "Detail fasilitas wajib diisi!";
        } elseif (!preg_match('/^[a-zA-Z0-9\s\-]+$/', $detail_fasilitas)) {
            $errors[] = "Detail fasilitas hanya boleh berisi huruf, angka, spasi, dan tanda strip!";
        }

        if ($stok_total <= 0) {
            $errors[] = "Stok total harus lebih dari 0!";
        }

        if (empty($errors)) {
            // Cek duplikasi nama fasilitas secara global
            $q_check = safeQuery($conn, "EXEC dbo.sp_CheckFasilitasDuplicate ?, ?", [$nama_fasilitas, $id]);
            if ($q_check) {
                $dup_data = safeFetch($q_check);
                if ($dup_data && ($dup_data['CountDuplicate'] ?? 0) > 0) {
                    $errors[] = "Nama fasilitas sudah terdaftar di sistem!";
                }
            }
        }

        if (!empty($errors)) {
            echo json_encode(['success' => false, 'msg' => implode(' | ', $errors)]);
            exit();
        }

        if (isset($_POST['edit_mode']) && $id > 0) {
            // EXEC sp_UpdateFasilitas
            $result = safeQuery($conn, "EXEC dbo.sp_UpdateFasilitas ?, ?, ?, ?, ?", [$id, $nama_fasilitas, $detail_fasilitas, $stok_total, $nama]);
            if ($result !== false) {
                echo json_encode(['success' => true, 'msg' => 'Fasilitas berhasil diperbarui!']);
            } else {
                $sql_error = '';
                if (($db_errors = sqlsrv_errors()) != null) {
                    foreach ($db_errors as $error) {
                        $sql_error .= $error['message'] . ' ';
                    }
                }
                $error_msg = !empty($sql_error) ? $sql_error : 'Gagal memperbarui fasilitas. Stok baru tidak boleh kurang dari jumlah terpasang!';
                echo json_encode(['success' => false, 'msg' => $error_msg]);
            }
        } else {
            // EXEC sp_CreateFasilitas
            $result = safeQuery($conn, "EXEC dbo.sp_CreateFasilitas ?, ?, ?, ?", [$nama_fasilitas, $detail_fasilitas, $stok_total, $nama]);
            if ($result !== false) {
                echo json_encode(['success' => true, 'msg' => 'Fasilitas baru berhasil ditambahkan!']);
            } else {
                $sql_error = '';
                if (($db_errors = sqlsrv_errors()) != null) {
                    foreach ($db_errors as $error) {
                        $sql_error .= $error['message'] . ' ';
                    }
                }
                $error_msg = !empty($sql_error) ? $sql_error : 'Gagal menambahkan fasilitas!';
                echo json_encode(['success' => false, 'msg' => $error_msg]);
            }
        }
        exit();
    }

    // Action: Toggle Status Aktif / Nonaktif (AJAX)
    if ($action === 'toggle') {
        $id = intval($_GET['id'] ?? 0);
        $current_status = intval($_GET['status'] ?? 0);
        $new_status = $current_status === 1 ? 0 : 1;

        $stmt = safeQuery($conn, "EXEC dbo.sp_UpdateStatusFasilitas ?, ?, ?", [$id, $new_status, $nama]);
        if ($stmt !== false) {
            echo json_encode(['success' => true, 'msg' => 'Status fasilitas berhasil diubah!', 'new_status' => $new_status]);
        } else {
            $sql_error = '';
            if (($db_errors = sqlsrv_errors()) != null) {
                foreach ($db_errors as $error) {
                    $sql_error .= $error['message'] . ' ';
                }
            }
            echo json_encode(['success' => false, 'msg' => $sql_error ?: 'Gagal mengubah status fasilitas.']);
        }
        exit();
    }

    // Action: Soft Delete Fasilitas (AJAX)
    if ($action === 'delete') {
        $id = intval($_GET['id'] ?? 0);
        $stmt = safeQuery($conn, "EXEC dbo.sp_DeleteFasilitas ?, ?", [$id, $nama]);
        if ($stmt !== false) {
            echo json_encode(['success' => true, 'msg' => 'Fasilitas berhasil dihapus!']);
        } else {
            $sql_error = '';
            if (($db_errors = sqlsrv_errors()) != null) {
                foreach ($db_errors as $error) {
                    $sql_error .= $error['message'] . ' ';
                }
            }
            echo json_encode(['success' => false, 'msg' => $sql_error ?: 'Gagal menghapus fasilitas.']);
        }
        exit();
    }

    // Action: Ambil Data Tabel, Pagination & Statistik Utama (Dynamic AJAX Refresh)
    if ($action === 'get_table_data') {
        $f_lapangan = isset($_GET['f_lapangan']) && $_GET['f_lapangan'] !== '' ? $_GET['f_lapangan'] : 'all';
        $f_status = isset($_GET['f_status']) && $_GET['f_status'] !== '' ? $_GET['f_status'] : 'all';
        $f_sort = $_GET['f_sort'] ?? 'nama_asc';
        $search = isset($_GET['src']) ? trim($_GET['src']) : '';
        $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

        $limit = 10;
        $offset = ($page - 1) * $limit;

        // Ambil Statistik Terkini via UDF
        $q_stats = safeQuery($conn, "SELECT Total, Aktif, Nonaktif FROM dbo.fn_GetFasilitasStats()", []);
        $stats = safeFetch($q_stats);
        $total_fasilitas = $stats['Total'] ?? 0;
        $aktif_count = $stats['Aktif'] ?? 0;
        $nonaktif_count = $stats['Nonaktif'] ?? 0;

        // Ambil Daftar Utama (SP sp_ReadFasilitasListWithCount)
        $query_sql = "EXEC dbo.sp_ReadFasilitasListWithCount ?, ?, ?, ?, ?, ?";
        $params_sp = array($f_lapangan, $f_status, $f_sort, intval($offset), intval($limit), $search);
        $query = safeQuery($conn, $query_sql, $params_sp);

        $total_data = 0;
        if ($query) {
            $row_count = safeFetch($query);
            $total_data = intval($row_count['TotalCount'] ?? 0);
            sqlsrv_next_result($query);
        }

        $total_pages = max(1, ceil($total_data / $limit));
        $page = min($page, $total_pages);

        // Render HTML Tabel Body
        ob_start();
        $has_data = false;
        $no = $offset + 1;
        if ($query):
            while ($row = sqlsrv_fetch_array($query, SQLSRV_FETCH_ASSOC)):
                $has_data = true;
                ?>
                <tr>
                    <td class="col-center" style="font-family:'Barlow Condensed', sans-serif; font-weight:700; color:var(--text);">
                        <?= $no++ ?>
                    </td>
                    <td class="col-left">
                        <div class="fas-name"><?= htmlspecialchars($row['Nama_Fasilitas']) ?></div>
                    </td>
                    <td class="col-left">
                        <div class="fas-detail-col">
                            <?= !empty($row['Detail_Fasilitas']) ? htmlspecialchars($row['Detail_Fasilitas']) : '-' ?>
                        </div>
                    </td>
                    <td class="col-center">
                        <div class="stok-badge">
                            <span class="stok-sisa"><?= intval($row['Stok_Tersedia']) ?></span>
                            <span class="stok-pemisah">/</span>
                            <span class="stok-total"><?= intval($row['Stok_Total']) ?></span>
                        </div>
                    </td>
                    <td class="col-center">
                        <span class="status-pill <?= $row['Status'] == 1 ? 'sp-active' : 'sp-inactive' ?>">
                            <span class="sp-dot"></span>
                            <?= $row['Status'] == 1 ? 'AKTIF' : 'NONAKTIF' ?>
                        </span>
                    </td>
                    <td class="col-center">
                        <div class="actions">
                            <button type="button" onclick="showDetail('<?= $row['ID_Fasilitas'] ?>')" class="btn-action btn-view" title="Lihat Detail">
                                <i class="fa-solid fa-eye"></i>
                            </button>
                            <button type="button" onclick="showEditForm('<?= $row['ID_Fasilitas'] ?>')" class="btn-action btn-edit" title="Edit Fasilitas">
                                <i class="fa-solid fa-pen-to-square"></i>
                            </button>
                            <label class="toggle-switch" title="<?= $row['Status'] == 1 ? 'Nonaktifkan' : 'Aktifkan' ?>">
                                <input type="checkbox" <?= $row['Status'] == 1 ? 'checked' : '' ?> onchange="confirmToggle('<?= $row['ID_Fasilitas'] ?>', <?= $row['Status'] ?>, event)">
                                <span class="toggle-slider"></span>
                            </label>
                            <button type="button" onclick="confirmDelete('<?= $row['ID_Fasilitas'] ?>', '<?= htmlspecialchars($row['Nama_Fasilitas'], ENT_QUOTES) ?>')" class="btn-action btn-delete" title="Hapus Fasilitas">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            <?php endwhile; endif;

        if (!$has_data): ?>
            <tr>
                <td colspan="6">
                    <div class="empty-state">
                        <i class="fa-solid fa-list-check"></i>
                        <div>Belum ada data fasilitas</div>
                        <div style="font-size: 12px; font-weight: 500; margin-top: 8px; opacity: .7;">
                            Tambah fasilitas baru untuk memulai</div>
                    </div>
                </td>
            </tr>
        <?php endif;
        $table_html = ob_get_clean();

        // Render HTML Pagination
        ob_start();
        ?>
        <div class="pagination-info">
            <?php if ($total_data > 0): ?>
                Menampilkan <strong><?= (($page - 1) * $limit) + 1 ?></strong> -
                <strong><?= min($page * $limit, $total_data) ?></strong> dari <strong><?= $total_data ?></strong> data
            <?php else: ?>
                Menampilkan <strong>0</strong> data
            <?php endif; ?>
        </div>
        <div class="pagination-nav">
            <button onclick="changePage(1)" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                <i class="fa-solid fa-angles-left"></i>
            </button>
            <button onclick="changePage(<?= max(1, $page - 1) ?>)" class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                <i class="fa-solid fa-angle-left"></i>
            </button>
            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                <button onclick="changePage(<?= $i ?>)" class="page-btn <?= $i == $page ? 'active' : '' ?>">
                    <?= $i ?>
                </button>
            <?php endfor; ?>
            <button onclick="changePage(<?= min($total_pages, $page + 1) ?>)" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <i class="fa-solid fa-angle-right"></i>
            </button>
            <button onclick="changePage(<?= $total_pages ?>)" class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <i class="fa-solid fa-angles-right"></i>
            </button>
        </div>
        <?php
        $pagination_html = ob_get_clean();

        echo json_encode([
            'success' => true,
            'table' => $table_html,
            'pagination' => $pagination_html,
            'stats' => [
                'total' => $total_fasilitas,
                'aktif' => $aktif_count,
                'nonaktif' => $nonaktif_count,
                'total_data' => $total_data
            ]
        ]);
        exit();
    }
}

// ── BIND LAPANGAN DROP-DOWN (PAGE LOAD PERTAMA) ──
$lapangan_list = [];
$q_lap = safeQuery($conn, "EXEC dbo.sp_GetActiveLapanganList", []);
if ($q_lap) {
    while ($row = sqlsrv_fetch_array($q_lap, SQLSRV_FETCH_ASSOC)) {
        $lapangan_list[] = $row;
    }
}

$current_page = 'fasilitas';
$sidebar_folder = 'master';
$sidebar_photo = $profile_photo;
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <?php include '../includes/favicon.php'; ?>
    <title>Kelola Fasilitas Lapangan | HoopBall</title>
    <link
        href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@700;800;900&family=Barlow:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="../asset/css/global.css">
    <link rel="stylesheet" href="../asset/css/responsive_tipe_member.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* CSS Tambahan khusus memaksa SweetAlert2 berada di atas modal bootstrap */
        .swal2-container {
            z-index: 3000 !important;
        }

        .page-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .page-title-tag {
            width: 36px;
            height: 4px;
            background: var(--orange);
            border-radius: 2px;
            margin-bottom: 8px;
        }

        .page-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 30px;
            font-weight: 900;
            color: var(--text);
            text-transform: uppercase;
        }

        .stat-chips {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .stat-chip {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 18px;
            border-radius: 10px;
            font-size: 12px;
            font-weight: 700;
            transition: all .2s;
        }

        .stat-chip:hover {
            transform: translateY(-2px);
        }

        .chip-green {
            background: var(--green-lt);
            color: var(--green);
        }

        .chip-red {
            background: var(--red-lt);
            color: var(--red);
        }

        .chip-blue {
            background: var(--blue-lt);
            color: var(--blue);
        }

        .chip-val {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 20px;
            font-weight: 900;
        }

        .action-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .search-box {
            position: relative;
            width: 300px;
        }

        .search-box i {
            position: absolute;
            left: 14px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--muted);
            font-size: 13px;
        }

        .search-box input {
            width: 100%;
            padding: 10px 14px 10px 40px;
            background: var(--card-bg);
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 13px;
            font-family: 'Barlow', sans-serif;
            outline: none;
            transition: all .2s;
            color: var(--text);
        }

        .search-box input:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px var(--orange-lt);
        }

        .search-box input::placeholder {
            color: #9CA3AF;
        }

        /* ═══ CARD & TABLE (PENYELARASAN LAYOUT) ═══ */
        .card {
            background: var(--card-bg);
            border-radius: 16px;
            border: 1px solid var(--border);
            overflow: hidden;
            transition: all .2s ease;
            background-color: #FFFFFF !important;
        }

        .card:hover {
            box-shadow: 0 8px 24px rgba(0, 0, 0, .06);
        }

        .card-header { 
            padding: 20px 24px; 
            border-bottom: 1px solid var(--border); 
            display: flex; 
            align-items: center; 
            justify-content: space-between; 
        }

        .card-title { 
            font-size: 15px; 
            font-weight: 800; 
            color: var(--text); 
            display: flex; 
            align-items: center; 
            gap: 8px; 
        }

        .card-title i { color: var(--orange); font-size: 14px; }

        .card-badge { 
            background: var(--orange-lt); 
            color: var(--orange); 
            font-size: 11px; 
            font-weight: 800; 
            padding: 4px 10px; 
            border-radius: 20px; 
        }

        .table-wrap {
            overflow-x: auto;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }
        .table-wrap::-webkit-scrollbar { display: none; }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table th {
            font-family: 'Barlow Condensed', sans-serif !important; 
            font-size: 13.5px !important; 
            font-weight: 900 !important; 
            color: #FFFFFF !important; 
            text-transform: uppercase !important; 
            letter-spacing: 0.8px !important; 
            padding: 16px 20px; 
            border-bottom: 2px solid var(--border-lt);
            background: #ff6f00 !important;
        }

        /* Kelas Penjajaran Khusus */
        .col-left {
            text-align: left !important;
            padding-left: 24px !important;
        }

        .col-center {
            text-align: center !important;
        }

        .data-table td {
            padding: 14px 20px;
            vertical-align: middle;
            font-size: 13.5px;
        }

        .data-table tbody tr { 
            height: 72px; 
            border-bottom: 1px solid var(--border-lt);
        }

        .data-table tbody tr:last-child {
            border-bottom: none;
        }

        .fas-name {
            font-weight: 800;
            color: var(--text);
            font-size: 14px;
            line-height: 1.4;
        }

        .fas-detail-col {
            font-size: 13px;
            color: var(--text-md);
            font-weight: 500;
            line-height: 1.4;
        }

        /* ═══ STOK BADGE (PRO) ═══ */
        .stok-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
            font-family: 'Barlow Condensed', sans-serif;
            font-weight: 800;
            font-size: 15px;
            background: #F3F4F6;
            padding: 6px 14px;
            border-radius: 10px;
            border: 1px solid var(--border-lt);
            color: var(--text);
            min-width: 75px;
        }

        .stok-sisa {
            color: var(--green);
        }

        .stok-pemisah {
            color: #9CA3AF;
            font-size: 12px;
            font-weight: 400;
        }

        .stok-total {
            color: var(--text);
        }

        /* ═══ STATUS PILL ═══ */
        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 11.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .3px;
        }

        .sp-active {
            background: var(--green-lt);
            color: var(--green);
        }

        .sp-inactive {
            background: var(--red-lt);
            color: var(--red);
        }

        .sp-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            display: inline-block;
        }

        .sp-active .sp-dot {
            background: var(--green);
        }

        .sp-inactive .sp-dot {
            background: var(--red);
        }

        /* ═══ ACTIONS & SWITCH ═══ */
        .actions {
            display: inline-flex;
            gap: 10px;
            align-items: center;
            justify-content: center;
        }

        .btn-action {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            transition: all .2s ease;
            border: 1.5px solid transparent;
            cursor: pointer;
        }

        .btn-view {
            background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
            color: #1E40AF;
            border-color: #BFDBFE;
        }

        .btn-view:hover {
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            color: #fff;
            border-color: #3B82F6;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, .2);
        }

        .btn-edit {
            background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
            color: #1E40AF;
            border-color: #BFDBFE;
        }

        .btn-edit:hover {
            background: linear-gradient(135deg, #3B82F6 0%, #2563EB 100%);
            color: #fff;
            border-color: #3B82F6;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, .2);
        }

        .btn-delete {
            background: linear-gradient(135deg, #FEF2F2 0%, #FEE2E2 100%);
            color: #DC2626;
            border-color: #FECACA;
        }

        .btn-delete:hover {
            background: linear-gradient(135deg, #EF4444 0%, #DC2626 100%);
            color: #fff;
            border-color: #EF4444;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, .2);
        }

        .toggle-switch {
            position: relative;
            display: inline-flex;
            align-items: center;
            width: 44px;
            height: 24px;
            cursor: pointer;
            margin: 0 4px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: var(--red);
            transition: .3s;
            border-radius: 24px;
        }

        .toggle-slider::before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
            box-shadow: 0 1px 3px rgba(0, 0, 0, .2);
        }

        .toggle-switch input:checked+.toggle-slider {
            background-color: var(--green);
        }

        .toggle-switch input:checked+.toggle-slider::before {
            transform: translateX(20px);
        }

        /* ═══ ZEBRA STRIPING ═══ */
        .data-table tbody tr:nth-child(odd) {
            background-color: #FFF7ED;
        }

        .data-table tbody tr:nth-child(even) {
            background-color: #FFFFFF;
        }

        .data-table tbody tr:hover td {
            background-color: #FFEDD5 !important;
        }

        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, .55);
            backdrop-filter: blur(6px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        .modal-overlay.open {
            display: flex;
        }

        .modal-box {
            background: #fff;
            border-radius: 20px;
            width: 480px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0, 0, 0, .2);
            position: relative;
        }

        .modal-header {
            padding: 28px 32px 20px;
            border-bottom: 1px solid var(--border);
        }

        .modal-subtitle {
            font-size: 10px;
            font-weight: 800;
            color: var(--orange);
            text-transform: uppercase;
            margin-bottom: 6px;
            letter-spacing: .8px;
        }

        .modal-title {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 22px;
            font-weight: 900;
            color: var(--text);
        }

        .modal-body {
            padding: 24px 32px 32px;
        }

        .modal-label {
            display: block;
            font-size: 11px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 8px;
        }

        .modal-label .required {
            color: var(--red);
            margin-left: 2px;
            font-size: 14px;
            font-weight: 900;
        }

        .modal-input {
            width: 100%;
            padding: 12px 14px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 13px;
            font-family: 'Barlow', sans-serif;
            margin-bottom: 16px;
            outline: none;
            transition: all .2s;
            color: var(--text);
        }

        .modal-input:focus {
            border-color: var(--orange);
            box-shadow: 0 0 0 3px var(--orange-lt);
        }

        .modal-input::placeholder {
            color: #9CA3AF;
        }

        .modal-input.error {
            border-color: var(--red);
            box-shadow: 0 0 0 3px var(--red-lt);
        }

        .btn-submit {
            width: 100%;
            background: var(--orange);
            color: #fff;
            border: none;
            padding: 14px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 13px;
            cursor: pointer;
            transition: all .2s;
            text-transform: uppercase;
            letter-spacing: .5px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-submit:hover {
            background: var(--orange-dk);
            transform: translateY(-1px);
            box-shadow: 0 8px 20px rgba(255, 69, 0, .3);
        }

        .btn-cancel {
            display: block;
            text-align: center;
            margin-top: 16px;
            color: var(--muted);
            text-decoration: none;
            font-size: 13px;
            font-weight: 700;
            transition: .2s;
            cursor: pointer;
        }

        .btn-cancel:hover {
            color: var(--orange);
        }

        .modal-close {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 36px;
            height: 36px;
            border: none;
            background: var(--border-lt);
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--muted);
            font-size: 16px;
            transition: all .2s;
        }

        .modal-close:hover {
            background: var(--red-lt);
            color: var(--red);
        }

        .val-msg {
            font-size: 11px;
            color: var(--red);
            font-weight: 600;
            margin-bottom: 10px;
            display: none;
            min-height: 16px;
        }

        .val-msg.show {
            display: block;
        }

        .val-msg i {
            margin-right: 4px;
        }

        .pagination-wrap {
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-top: none;
            border-radius: 0 0 16px 16px;
            padding: 16px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 32px;
        }

        .pagination-info {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
        }

        .pagination-info strong {
            color: var(--text);
            font-weight: 800;
        }

        .pagination-nav {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .page-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
            padding: 0 10px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 700;
            font-family: 'Barlow', sans-serif;
            text-decoration: none;
            cursor: pointer;
            transition: all .2s ease;
            border: 1.5px solid var(--border);
            color: var(--text-md);
            background: #fff;
        }

        .page-btn:hover:not(.disabled):not(.active) {
            border-color: var(--orange);
            color: var(--orange);
            background: var(--orange-lt);
            transform: translateY(-1px);
        }

        .page-btn.active {
            background: var(--orange);
            color: #fff;
            border-color: var(--orange);
            box-shadow: 0 4px 12px rgba(255, 69, 0, .3);
            font-weight: 800;
        }

        .page-btn.disabled {
            opacity: 0.4;
            cursor: not-allowed;
            pointer-events: none;
        }

        .page-btn i {
            font-size: 11px;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            color: var(--muted);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: .3;
            display: block;
        }

        .empty-state div {
            font-size: 14px;
            font-weight: 700;
        }

        .filter-dropdown-wrap {
            position: relative;
            display: inline-block;
        }

        .btn-filter {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: var(--orange);
            color: #ffffff;
            padding: 11px 20px;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 800;
            text-transform: uppercase;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            box-shadow: 0 4px 12px rgba(255, 69, 0, 0.2);
        }

        .btn-filter:hover {
            background-color: var(--orange-dk);
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(255, 69, 0, 0.35);
        }

        .btn-filter i.arrow-icon {
            font-size: 10px;
            transition: transform 0.3s;
        }

        .filter-card {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid var(--border);
            padding: 24px;
            width: 300px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.12);
            z-index: 50;
            display: none;
        }

        .filter-card.open {
            display: block;
            animation: slideFilter 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }

        @keyframes slideFilter {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .filter-card h4 {
            font-size: 15px;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 20px;
            text-align: left;
        }

        .filter-group {
            margin-bottom: 16px;
            text-align: left;
        }

        .filter-group label {
            display: block;
            font-size: 11px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 8px;
        }

        .filter-input {
            width: 100%;
            padding: 10px 14px;
            border: 1.5px solid var(--border);
            border-radius: 10px;
            font-size: 13px;
            font-family: 'Barlow', sans-serif;
            outline: none;
            transition: all .2s;
            color: var(--text);
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%236B7280' stroke-width='2'%3E%3Cpath d='M6 9l6 6 6-6'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
            padding-right: 40px;
        }

        .filter-input:focus {
            border-color: var(--orange);
        }

        .filter-buttons {
            display: flex;
            gap: 10px;
            margin-top: 24px;
        }

        .btn-filter-apply {
            flex: 1.2;
            background: var(--orange);
            color: white;
            border: none;
            padding: 12px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 12px;
            text-transform: uppercase;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all .2s;
        }

        .btn-filter-apply:hover {
            background: var(--orange-dk);
        }

        .btn-filter-reset {
            flex: 1;
            background: var(--border-lt);
            color: var(--text-md);
            border: 1px solid var(--border);
            padding: 12px;
            border-radius: 10px;
            font-weight: 800;
            font-size: 12px;
            text-transform: uppercase;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: all .2s;
        }

        .btn-filter-reset:hover {
            background: #E5E7EB;
        }

        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: var(--text) !important;
            color: #fff;
            padding: 11px 22px !important;
            border-radius: 10px !important;
            font-size: 13px;
            font-weight: 800;
            text-decoration: none;
            text-transform: uppercase;
            transition: all .2s ease !important;
            border: none !important;
            cursor: pointer;
        }

        .btn-add:hover {
            background-color: var(--orange);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 69, 0, .3);
        }

        .btn-add i {
            font-size: 14px;
        }

        .detail-icon-wrap {
            width: 80px;
            height: 80px;
            background: var(--orange-lt);
            color: var(--orange);
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            margin-bottom: 16px;
            box-shadow: 0 8px 20px rgba(255, 69, 0, 0.15);
        }

        .detail-main-name {
            font-family: 'Barlow Condensed', sans-serif;
            font-size: 24px;
            font-weight: 900;
            color: var(--text);
            text-transform: uppercase;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 0;
            border-bottom: 1px solid var(--border-lt);
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-key {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 13px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .info-key i {
            color: var(--orange);
            font-size: 14px;
            width: 18px;
            text-align: center;
        }

        .info-val {
            font-size: 14px;
            font-weight: 700;
            color: var(--text);
        }

        .btn-clear-search {
            position: absolute;
            right: 5px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            font-size: 14px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        @media(max-width: 768px) {
            .sidebar {
                width: 0;
                overflow: hidden;
                padding: 0;
            }

            .main {
                margin-left: 0;
            }

            .content {
                padding: 20px;
            }

            .modal-box {
                width: 90%;
                margin: 20px;
            }
        }
    </style>
</head>

<body>
    <?php include '../includes/sidebar.php'; ?>
    <!-- MODAL FORM -->
    <div class="modal-overlay" id="modalFasilitas">
        <div class="modal-box">
            <button type="button" class="modal-close" onclick="closeModalDirect('modalFasilitas')"><i
                    class="fa-solid fa-xmark"></i></button>
            <div class="modal-header">
                <div class="modal-subtitle">Kelola Fasilitas</div>
                <div class="modal-title" id="formModalTitle">Tambah Fasilitas Baru</div>
            </div>
            <div class="modal-body">
                <form id="formFasilitas" onsubmit="handleFormSubmit(event)" novalidate>
                    <!-- Hidden inputs untuk edit mode -->
                    <div id="hiddenInputsArea"></div>

                    <label class="modal-label">Stok Total <span class="required">*</span></label>
                    <input type="number" name="stok_total" id="stok_total" class="modal-input" required min="1"
                        placeholder="Contoh: 15" autocomplete="off">
                    <div class="val-msg" id="val-stok_total"></div>

                    <label class="modal-label">Nama Fasilitas <span class="required">*</span></label>
                    <input type="text" name="nama_fasilitas" id="nama_fasilitas" class="modal-input" required
                        minlength="3" maxlength="50" placeholder="Contoh: Bola Basket Spalding" autocomplete="off">
                    <div class="val-msg" id="val-nama_fasilitas"></div>

                    <label class="modal-label">Detail Fasilitas <span class="required">*</span></label>
                    <input type="text" name="detail_fasilitas" id="detail_fasilitas" class="modal-input" required
                        maxlength="50" placeholder="Contoh: Bola basket standar SNI" autocomplete="off">
                    <div class="val-msg" id="val-detail_fasilitas"></div>

                    <button type="submit" class="btn-submit" id="btnSubmitForm">
                        <i class="fa-solid fa-plus"></i> Tambah Fasilitas
                    </button>
                    <a onclick="closeModalDirect('modalFasilitas')" class="btn-cancel">Batal</a>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL DETAIL -->
    <div class="modal-overlay" id="modalDetail">
        <div class="modal-box" style="width: 440px;">
            <button type="button" class="modal-close" onclick="closeModalDirect('modalDetail')"><i
                    class="fa-solid fa-xmark"></i></button>
            <div class="modal-header" style="border-bottom: none; padding-bottom: 0;">
                <div class="modal-subtitle">Informasi Fasilitas</div>
                <div class="modal-title">Detail Fasilitas</div>
            </div>
            <div class="modal-body" style="padding-top: 10px;">
                <div
                    style="text-align: center; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1.5px dashed var(--border);">
                    <div class="detail-icon-wrap"><i class="fa-solid fa-list-check"></i></div>
                    <div class="detail-main-name" id="det_nama_title">-</div>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-layer-group"></i> Stok Total</span>
                    <span class="info-val" id="det_stok_total" style="font-weight:800; color:var(--text);">-</span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-box-archive"></i> Stok Tersedia</span>
                    <span class="info-val" id="det_stok_tersedia" style="font-weight:800; color:var(--green);">-</span>
                </div>
                <div class="info-row">
                    <span class="info-key"><i class="fa-solid fa-circle-info"></i> Detail Fasilitas</span>
                    <span class="info-val" id="det_detail" style="font-weight:700;">-</span>
                </div>
                <div class="info-row" style="border-bottom:none;">
                    <span class="info-key"><i class="fa-solid fa-circle-check"></i> Status Fasilitas</span>
                    <span class="info-val" id="det_status_pill_wrap">
                        <span class="status-pill sp-active" id="det_status_pill">
                            <span class="sp-dot"></span>
                            <span id="det_status_text">AKTIF</span>
                        </span>
                    </span>
                </div>
                <button type="button" onclick="closeModalDirect('modalDetail')" class="btn-submit"
                    style="margin-top: 24px; background: #0D1117;">
                    <i class="fa-solid fa-arrow-left"></i> Kembali Ke List
                </button>
            </div>
        </div>
    </div>

    <!-- MAIN -->
    <main class="main">
        <?php include '../includes/topbar.php'; ?>
        <div class="content">
            <div class="page-header">
                <div>
                    <div class="page-title-tag"></div>
                    <div class="page-title">Kelola Fasilitas</div>
                </div>
                <div class="stat-chips">
                    <div class="stat-chip chip-green"><i class="fa-solid fa-circle-check"></i> AKTIF <span
                            class="chip-val" id="stat-aktif">0</span></div>
                    <div class="stat-chip chip-red"><i class="fa-solid fa-circle-xmark"></i> NONAKTIF <span
                            class="chip-val" id="stat-nonaktif">0</span></div>
                    <div class="stat-chip chip-blue"><i class="fa-solid fa-list"></i> TOTAL <span
                            class="chip-val" id="stat-total">0</span></div>
                </div>
            </div>

            <div class="action-bar">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="src" placeholder="Cari fasilitas... (Tekan Enter)"
                        onkeypress="handleSearch(event)" value="">
                    <button type="button" onclick="clearSearch()" class="btn-clear-search" id="btnClearSearch" style="display: none;">
                        <i class="fa-solid fa-circle-xmark"></i>
                    </button>
                </div>
                <div style="display: flex; gap: 12px; align-items: center;">
                    <div class="filter-dropdown-wrap">
                        <!-- Perbaikan ID custom dan onclick khusus untuk bypass tabrakan event global.js -->
                        <button type="button" class="btn-filter" id="btnFilterToggleCustom" onclick="toggleCustomFilterCard(event)">
                            <i class="fa-solid fa-filter"></i> Filter <i
                                class="fa-solid fa-chevron-down arrow-icon"></i>
                        </button>
                        <div class="filter-card" id="filterCardCustom" onclick="event.stopPropagation()">
                            <h4>Filter Data</h4>
                            <form id="formFilter" onsubmit="handleFilterSubmit(event)">
                                <div class="filter-group">
                                    <label>Lapangan</label>
                                    <select name="f_lapangan" id="f_lapangan" class="filter-input">
                                        <option value="">Semua Lapangan</option>
                                        <?php foreach ($lapangan_list as $lap): ?>
                                            <option value="<?= $lap['ID_Lapangan'] ?>">
                                                <?= htmlspecialchars($lap['Nama_Lapangan']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Status</label>
                                    <select name="f_status" id="f_status" class="filter-input">
                                        <option value="">Semua Status</option>
                                        <option value="1">AKTIF</option>
                                        <option value="0">NONAKTIF</option>
                                    </select>
                                </div>
                                <div class="filter-group">
                                    <label>Urut Berdasarkan</label>
                                    <select name="f_sort" id="f_sort" class="filter-input">
                                        <option value="nama_asc">Nama Fasilitas (A - Z)</option>
                                        <option value="nama_desc">Nama Fasilitas (Z - A)</option>
                                        <option value="stok_desc">Stok Terbanyak</option>
                                        <option value="stok_asc">Stok Tersedikit</option>
                                    </select>
                                </div>
                                <div class="filter-buttons">
                                    <button type="submit" class="btn-filter-apply"><i class="fa-solid fa-check"></i>
                                        Terapkan</button>
                                    <button type="button" class="btn-filter-reset" onclick="resetFilter()"><i
                                            class="fa-solid fa-rotate-left"></i> Reset</button>
                                </div>
                            </form>
                        </div>
                    </div>
                    <button type="button" onclick="showAddForm()" class="btn-add"><i
                            class="fa-solid fa-plus"></i>Tambah</button>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title"><i class="fa-solid fa-list-check"></i> Data Fasilitas</div>
                    <span class="card-badge" id="header-badge">0 total</span>
                </div>
                <div class="table-wrap">
                    <table class="data-table" id="tbl">
                        <thead>
                            <tr>
                                <th style="width: 80px;" class="col-center">No</th>
                                <th style="width: 250px;" class="col-left">Nama Fasilitas</th>
                                <th class="col-left">Detail Fasilitas</th>
                                <th style="width: 180px;" class="col-center">Stok (Sisa/Total)</th>
                                <th style="width: 150px;" class="col-center">Status</th>
                                <th style="width: 220px;" class="col-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Dinamis diisi lewat AJAX Javascript -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- PAGINATION -->
            <div class="pagination-wrap">
                <!-- Dinamis diisi lewat AJAX Javascript -->
            </div>
        </div>
    </main>
    <script src="../asset/js/global.js"></script>
    <script>
        // State management untuk Filter & Pagination
        let currentPage = 1;
        let currentSort = 'nama_asc';
        let currentStatus = '';
        let currentLapangan = '';
        let currentSearch = '';

        // ============================================
        // GET DATA TABEL (AJAX REFRESH)
        // ============================================
        async function loadTableData() {
            const url = `fasilitas_lapangan.php?action=get_table_data&page=${currentPage}&f_sort=${currentSort}&f_status=${currentStatus}&f_lapangan=${currentLapangan}&src=${encodeURIComponent(currentSearch)}`;
            try {
                const response = await fetch(url);
                const data = await response.json();
                if (data.success) {
                    // Update stats
                    document.getElementById('stat-aktif').textContent = data.stats.aktif;
                    document.getElementById('stat-nonaktif').textContent = data.stats.nonaktif;
                    document.getElementById('stat-total').textContent = data.stats.total;
                    document.getElementById('header-badge').textContent = `${data.stats.total_data} total`;

                    // Update Table & Pagination
                    document.querySelector('#tbl tbody').innerHTML = data.table;
                    document.querySelector('.pagination-wrap').innerHTML = data.pagination;

                    // Update Clear Search Button visibility
                    const btnClear = document.getElementById('btnClearSearch');
                    if (currentSearch !== '') {
                        btnClear.style.display = 'block';
                    } else {
                        btnClear.style.display = 'none';
                    }
                }
            } catch (error) {
                console.error("Gagal memuat data tabel:", error);
            }
        }

        function changePage(page) {
            currentPage = page;
            loadTableData();
        }

        // ============================================
        // VALIDASI FORM - REAL TIME & SUBMIT
        // ============================================
        function validateField(fieldId, valId, rules) {
            const field = document.getElementById(fieldId);
            const valMsg = document.getElementById(valId);
            const value = field.value.trim();

            field.classList.remove('error');
            valMsg.classList.remove('show');

            if (rules.required && value === '') {
                field.classList.add('error');
                valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + rules.label + ' wajib diisi';
                valMsg.classList.add('show');
                return false;
            }

            if (rules.minLength && value.length < rules.minLength) {
                field.classList.add('error');
                valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Minimal ' + rules.minLength + ' karakter';
                valMsg.classList.add('show');
                return false;
            }

            if (rules.maxLength && value.length > rules.maxLength) {
                field.classList.add('error');
                valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Maksimal ' + rules.maxLength + ' karakter';
                valMsg.classList.add('show');
                return false;
            }

            if (rules.pattern && value !== '') {
                const regex = /^[a-zA-Z0-9\s\-]+$/;
                if (!regex.test(value)) {
                    field.classList.add('error');
                    valMsg.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> ' + rules.label + ' hanya boleh berisi huruf, angka, spasi, dan strip';
                    valMsg.classList.add('show');
                    return false;
                }
            }

            return true;
        }

        function validateForm() {
            let valid = true;

            if (!validateField('stok_total', 'val-stok_total', {
                required: true,
                label: 'Stok total'
            })) valid = false;

            if (!validateField('nama_fasilitas', 'val-nama_fasilitas', {
                required: true,
                minLength: 3,
                maxLength: 50,
                pattern: true,
                label: 'Nama fasilitas'
            })) valid = false;

            if (!validateField('detail_fasilitas', 'val-detail_fasilitas', {
                required: true,
                maxLength: 50,
                pattern: true,
                label: 'Detail fasilitas'
            })) valid = false;

            return valid;
        }

        // ============================================
        // ALERT HELPER FUNCTIONS (PREVENTS STUCK MODAL)
        // ============================================
        function showSuccess(title, message) {
            Swal.close(); // Tutup modal loading terlebih dahulu
            Swal.fire({
                icon: 'success',
                title: title,
                text: message,
                confirmButtonColor: '#10B981'
            });
        }

        function showError(title, message) {
            Swal.close(); // Tutup modal loading terlebih dahulu
            Swal.fire({
                icon: 'error',
                title: title,
                text: message,
                confirmButtonColor: '#EF4444'
            });
        }

        // ============================================
        // AJAX SUBMIT FORM (TAMBAH / EDIT)
        // ============================================
        async function handleFormSubmit(event) {
            event.preventDefault();
            if (!validateForm()) return;

            const form = document.getElementById('formFasilitas');
            const formData = new FormData(form);
            formData.append('action', 'save');

            Swal.fire({
                title: 'Memproses...',
                text: 'Menyimpan data fasilitas',
                allowOutsideClick: false,
                didOpen: () => { Swal.showLoading(); }
            });

            try {
                const response = await fetch('fasilitas_lapangan.php', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();
                if (res.success) {
                    closeModalDirect('modalFasilitas');
                    showSuccess('Berhasil!', res.msg);
                    loadTableData();
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (error) {
                showError('Gagal!', 'Gagal memproses data.');
            }
        }

        // ============================================
        // TOGGLE STATUS
        // ============================================
        async function confirmToggle(id, status, event) {
            const checkbox = event.target;
            const action = status == 1 ? 'nonaktifkan' : 'aktifkan';
            const iconType = status == 1 ? 'warning' : 'question';

            const result = await Swal.fire({
                title: 'Konfirmasi Perubahan Status',
                text: 'Apakah Anda yakin ingin ' + action + ' fasilitas ini?',
                icon: iconType,
                showCancelButton: true,
                confirmButtonColor: '#FF4500',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Ya, ' + action + '!',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                allowOutsideClick: false
            });

            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Mengubah status fasilitas',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                try {
                    const response = await fetch(`fasilitas_lapangan.php?action=toggle&id=${id}&status=${status}`);
                    const res = await response.json();
                    if (res.success) {
                        showSuccess('Berhasil!', res.msg);
                        loadTableData();
                    } else {
                        checkbox.checked = !checkbox.checked;
                        showError('Gagal!', res.msg);
                    }
                } catch (error) {
                    checkbox.checked = !checkbox.checked;
                    showError('Gagal!', 'Terjadi kesalahan saat mengubah status.');
                }
            } else {
                checkbox.checked = !checkbox.checked;
            }
        }

        // ============================================
        // DELETE CONFIRMATION
        // ============================================
        async function confirmDelete(id, name) {
            const result = await Swal.fire({
                title: 'Hapus Fasilitas?',
                html: 'Anda akan menghapus fasilitas <strong style="color:var(--orange);">' + name + '</strong><br><span style="font-size:12px;color:var(--muted);">Data akan dihapus secara Permanen</span>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#EF4444',
                cancelButtonColor: '#6B7280',
                confirmButtonText: 'Ya, Hapus!',
                cancelButtonText: 'Batal',
                reverseButtons: true,
                allowOutsideClick: false
            });

            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memproses...',
                    text: 'Menghapus data fasilitas',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                try {
                    const response = await fetch(`fasilitas_lapangan.php?action=delete&id=${id}`);
                    const res = await response.json();
                    if (res.success) {
                        showSuccess('Terhapus!', res.msg);
                        loadTableData();
                    } else {
                        showError('Gagal!', res.msg);
                    }
                } catch (error) {
                    showError('Gagal!', 'Terjadi kesalahan saat menghapus data.');
                }
            }
        }

        // ============================================
        // SEARCH & FILTER SYSTEM
        // ============================================
        function handleSearch(event) {
            if (event.key === 'Enter') {
                currentSearch = document.getElementById('src').value.trim();
                currentPage = 1;
                loadTableData();
            }
        }

        function clearSearch() {
            document.getElementById('src').value = '';
            currentSearch = '';
            currentPage = 1;
            loadTableData();
        }

        function handleFilterSubmit(event) {
            event.preventDefault();
            const form = event.target;
            currentLapangan = form.elements['f_lapangan'].value;
            currentStatus = form.elements['f_status'].value;
            currentSort = form.elements['f_sort'].value;
            currentPage = 1;
            loadTableData();

            document.getElementById('btnFilterToggleCustom').classList.remove('active');
            document.getElementById('filterCardCustom').classList.remove('open');
        }

        function resetFilter() {
            document.getElementById('formFilter').reset();
            currentLapangan = '';
            currentStatus = '';
            currentSort = 'nama_asc';
            currentSearch = '';
            document.getElementById('src').value = '';
            currentPage = 1;
            loadTableData();

            document.getElementById('btnFilterToggleCustom').classList.remove('active');
            document.getElementById('filterCardCustom').classList.remove('open');
        }

        // Fungsi Buka Form Tambah Baru
        function showAddForm() {
            document.getElementById('formFasilitas').reset();
            document.getElementById('hiddenInputsArea').innerHTML = '';

            document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));
            document.querySelectorAll('.val-msg').forEach(el => el.classList.remove('show'));

            document.getElementById('formModalTitle').innerText = 'Tambah Fasilitas Baru';
            document.getElementById('btnSubmitForm').innerHTML = '<i class="fa-solid fa-plus"></i> Tambah Fasilitas';

            document.getElementById('modalFasilitas').classList.add('open');
        }

        // Fungsi Buka Form Edit Data (AJAX)
        async function showEditForm(id) {
            document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));
            document.querySelectorAll('.val-msg').forEach(el => el.classList.remove('show'));

            try {
                const response = await fetch(`fasilitas_lapangan.php?action=get_detail&id=${id}`);
                const res = await response.json();
                if (res.success) {
                    const data = res.data;

                    document.getElementById('stok_total').value = data.Stok_Total;
                    document.getElementById('nama_fasilitas').value = data.Nama_Fasilitas;
                    document.getElementById('detail_fasilitas').value = data.Detail_Fasilitas;

                    document.getElementById('hiddenInputsArea').innerHTML = `
                        <input type="hidden" name="edit_mode" value="1">
                        <input type="hidden" name="id_fas" id="id_fas" value="${data.ID_Fasilitas}">
                    `;

                    document.getElementById('formModalTitle').innerText = 'Edit Fasilitas';
                    document.getElementById('btnSubmitForm').innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Simpan Perubahan';

                    document.getElementById('modalFasilitas').classList.add('open');
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (error) {
                showError('Gagal!', 'Gagal mengambil data fasilitas.');
            }
        }

        // Fungsi Tampilkan Detail Fasilitas (AJAX)
        async function showDetail(id) {
            try {
                const response = await fetch(`fasilitas_lapangan.php?action=get_detail&id=${id}`);
                const res = await response.json();
                if (res.success) {
                    const data = res.data;

                    document.getElementById('det_nama_title').innerText = data.Nama_Fasilitas;
                    document.getElementById('det_detail').innerText = data.Detail_Fasilitas ? data.Detail_Fasilitas : '-';
                    document.getElementById('det_stok_total').innerText = data.Stok_Total + ' unit';
                    document.getElementById('det_stok_tersedia').innerText = data.Stok_Tersedia + ' unit';

                    const pill = document.getElementById('det_status_pill');
                    const text = document.getElementById('det_status_text');
                    if (data.Status == 1) {
                        pill.className = 'status-pill sp-active';
                        text.innerText = 'AKTIF';
                    } else {
                        pill.className = 'status-pill sp-inactive';
                        text.innerText = 'NONAKTIF';
                    }

                    document.getElementById('modalDetail').classList.add('open');
                } else {
                    showError('Gagal!', res.msg);
                }
            } catch (error) {
                showError('Gagal!', 'Gagal memproses data.');
            }
        }

        // Fungsi Menutup Modal Secara Langsung
        function closeModalDirect(modalId) {
            document.getElementById(modalId).classList.remove('open');
        }

        // ============================================
        // PENGENDALI EVENT KLIK FILTER (INLINE FALLBACK)
        // ============================================
        function toggleCustomFilterCard(e) {
            e.stopPropagation();
            const btn = document.getElementById('btnFilterToggleCustom');
            const card = document.getElementById('filterCardCustom');
            if (btn && card) {
                btn.classList.toggle('active');
                card.classList.toggle('open');
            }
        }

        // Tutup filter card saat pengguna klik di luar area filter
        window.addEventListener('click', function (e) {
            const btn = document.getElementById('btnFilterToggleCustom');
            const card = document.getElementById('filterCardCustom');
            if (btn && card) {
                if (!btn.contains(e.target) && !card.contains(e.target)) {
                    btn.classList.remove('active');
                    card.classList.remove('open');
                }
            }
        });

        // ============================================
        // INITIAL LOAD & REAL-TIME VALIDATIONS
        // ============================================
        document.addEventListener('DOMContentLoaded', function () {
            // Jalankan load data tabel utama
            loadTableData();

            // Real-time validations
            const namaFas = document.getElementById('nama_fasilitas');
            if (namaFas) {
                namaFas.addEventListener('blur', function () {
                    validateField('nama_fasilitas', 'val-nama_fasilitas', {
                        required: true,
                        minLength: 3,
                        maxLength: 50,
                        pattern: true,
                        label: 'Nama fasilitas'
                    });
                });
                namaFas.addEventListener('input', function () {
                    if (this.classList.contains('error')) {
                        validateField('nama_fasilitas', 'val-nama_fasilitas', {
                            required: true,
                            minLength: 3,
                            maxLength: 50,
                            pattern: true,
                            label: 'Nama fasilitas'
                        });
                    }
                });
            }

            const detailFas = document.getElementById('detail_fasilitas');
            if (detailFas) {
                detailFas.addEventListener('blur', function () {
                    validateField('detail_fasilitas', 'val-detail_fasilitas', {
                        required: true,
                        maxLength: 50,
                        pattern: true,
                        label: 'Detail fasilitas'
                    });
                });
                detailFas.addEventListener('input', function () {
                    if (this.classList.contains('error')) {
                        validateField('detail_fasilitas', 'val-detail_fasilitas', {
                            required: true,
                            maxLength: 50,
                            pattern: true,
                            label: 'Detail fasilitas'
                        });
                    }
                });
            }
        });
    </script>
</body>

</html>