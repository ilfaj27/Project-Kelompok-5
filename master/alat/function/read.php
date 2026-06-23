<?php
/**
 * ============================================================
 * ALAT - READ FUNCTIONS
 * ============================================================
 * File ini berisi semua fungsi untuk READ (SELECT) data alat
 * - getEditData: Ambil 1 alat untuk di-edit
 * - getDetailData: Ambil 1 alat untuk ditampilkan detail
 * - getAlatList: Ambil list alat dengan filter & pagination
 * - countStats: Hitung statistik (total, aktif, nonaktif)
 */

require_once __DIR__ . '/helpers.php';

// ============================================================
// GET SINGLE RECORD
// ============================================================

/**
 * Ambil data alat untuk edit
 * @param mixed $conn - Connection object
 * @param int $id - ID_Alat
 * @return array|false - Data alat atau FALSE jika tidak ditemukan
 */
function getEditData($conn, $id) {
    $id = intval($id);
    if ($id <= 0) return false;
    
    $sql = "SELECT * FROM Alat WHERE ID_Alat = ? AND Is_Deleted = 0";
    $result = safeQuery($conn, $sql, [$id]);
    return $result ? safeFetch($result) : false;
}

/**
 * Ambil data alat untuk ditampilkan detail
 * @param mixed $conn - Connection object
 * @param int $id - ID_Alat
 * @return array|false - Data alat atau FALSE jika tidak ditemukan
 */
function getDetailData($conn, $id) {
    $id = intval($id);
    if ($id <= 0) return false;
    
    $sql = "SELECT 
                ID_Alat,
                Nama_Alat,
                Stok,
                Harga_Alat,
                Photo_Alat,
                Status,
                Created_By,
                Created_Date,
                Modified_By,
                Modified_Date
            FROM Alat 
            WHERE ID_Alat = ? AND Is_Deleted = 0";
    
    $result = safeQuery($conn, $sql, [$id]);
    return $result ? safeFetch($result) : false;
}

// ============================================================
// GET LIST WITH FILTER & PAGINATION
// ============================================================

/**
 * Hitung total data dengan filter
 * @param mixed $conn - Connection object
 * @param array $filters - Filter conditions ['f_status' => '1', ...]
 * @return int - Total data
 */
function getTotalAlatCount($conn, $filters = []) {
    $where_clauses = ["Is_Deleted = 0"];
    $params = [];
    
    if (isset($filters['f_status']) && $filters['f_status'] !== '') {
        $where_clauses[] = "Status = ?";
        $params[] = intval($filters['f_status']);
    }
    
    $where_sql = implode(" AND ", $where_clauses);
    $sql = "SELECT COUNT(*) as total FROM Alat WHERE $where_sql";
    $result = safeQuery($conn, $sql, $params);
    
    if ($result) {
        $row = safeFetch($result);
        return $row['total'] ?? 0;
    }
    return 0;
}

/**
 * Ambil list alat dengan filter & pagination
 * @param mixed $conn - Connection object
 * @param array $options - Options array
 *     - filters: array filter conditions
 *     - limit: int (default: 12)
 *     - page: int (default: 1)
 *     - sort: string 'nama_asc', 'stok_desc', 'harga_asc', 'harga_desc'
 * @return array - Array of alat records
 */
function getAlatList($conn, $options = []) {
    $filters = $options['filters'] ?? [];
    $limit = intval($options['limit'] ?? 12);
    $page = max(1, intval($options['page'] ?? 1));
    $sort = $options['sort'] ?? 'nama_asc';
    
    // Build WHERE clause
    $where_clauses = ["Is_Deleted = 0"];
    $params = [];
    
    if (isset($filters['f_status']) && $filters['f_status'] !== '') {
        $where_clauses[] = "Status = ?";
        $params[] = intval($filters['f_status']);
    }
    
    $where_sql = implode(" AND ", $where_clauses);
    
    // Build ORDER BY
    $sort_by = "Nama_Alat ASC";
    switch ($sort) {
        case 'nama_asc':
            $sort_by = "Nama_Alat ASC";
            break;
        case 'nama_desc':
            $sort_by = "Nama_Alat DESC";
            break;
        case 'stok_desc':
            $sort_by = "Stok DESC";
            break;
        case 'stok_asc':
            $sort_by = "Stok ASC";
            break;
        case 'harga_desc':
            $sort_by = "Harga_Alat DESC";
            break;
        case 'harga_asc':
            $sort_by = "Harga_Alat ASC";
            break;
        case 'terbaru':
            $sort_by = "Created_Date DESC";
            break;
    }
    
    // Count total
    $count_sql = "SELECT COUNT(*) as total FROM Alat WHERE $where_sql";
    $count_result = safeQuery($conn, $count_sql, $params);
    $total = 0;
    if ($count_result) {
        $row = safeFetch($count_result);
        $total = $row['total'] ?? 0;
    }
    
    // Calculate pagination
    $total_pages = max(1, ceil($total / $limit));
    $page = min($page, $total_pages);
    $offset = ($page - 1) * $limit;
    
    // Fetch data
    $query_sql = "SELECT * FROM Alat 
                  WHERE $where_sql 
                  ORDER BY $sort_by 
                  OFFSET $offset ROWS 
                  FETCH NEXT $limit ROWS ONLY";
    
    $query = safeQuery($conn, $query_sql, $params);
    $results = [];
    
    if ($query) {
        while ($row = safeFetch($query)) {
            $results[] = $row;
        }
    }
    
    return [
        'data' => $results,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => $total_pages
        ]
    ];
}

// ============================================================
// STATISTICS & COUNTS
// ============================================================

/**
 * Hitung statistik jumlah alat
 * @param mixed $conn - Connection object
 * @return array - Stats [total, aktif, nonaktif]
 */
function getAlatStats($conn) {
    $stats = [
        'total' => 0,
        'aktif' => 0,
        'nonaktif' => 0
    ];
    
    // Total alat (tidak dihapus)
    $q_total = safeQuery($conn, "SELECT COUNT(*) as t FROM Alat WHERE Is_Deleted = 0");
    if ($q_total) {
        $row = safeFetch($q_total);
        $stats['total'] = $row['t'] ?? 0;
    }
    
    // Alat aktif
    $q_aktif = safeQuery($conn, "SELECT COUNT(*) as t FROM Alat WHERE Is_Deleted = 0 AND Status = 1");
    if ($q_aktif) {
        $row = safeFetch($q_aktif);
        $stats['aktif'] = $row['t'] ?? 0;
    }
    
    // Alat nonaktif
    $q_nonaktif = safeQuery($conn, "SELECT COUNT(*) as t FROM Alat WHERE Is_Deleted = 0 AND Status = 0");
    if ($q_nonaktif) {
        $row = safeFetch($q_nonaktif);
        $stats['nonaktif'] = $row['t'] ?? 0;
    }
    
    return $stats;
}

/**
 * Hitung total pending booking (untuk widget)
 * @param mixed $conn - Connection object
 * @return int - Jumlah booking pending
 */
function getPendingBookingCount($conn) {
    $q = safeQuery($conn, "SELECT COUNT(*) as t FROM Booking WHERE Status = 0");
    if ($q) {
        $row = safeFetch($q);
        return $row['t'] ?? 0;
    }
    return 0;
}

?>