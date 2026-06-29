<?php
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
            echo "<div style='padding:20px;background:#fee;border:1px solid #fcc;border-radius:8px;font-family:sans-serif;margin:20px;'>
                <h3 style='color:#c00;margin:0 0 10px;'><i class='fa-solid fa-circle-exclamation'></i> Database Error</h3>
                <p style='color:#333;margin:0 0 5px;'><strong>Detail Error:</strong></p>
                <pre style='background:#fff;padding:10px;border-radius:4px;overflow-x:auto;font-size:12px;'>" . htmlspecialchars($error_msg) . "</pre>
                <p style='color:#666;font-size:12px;margin:10px 0 0;'>SQL: " . htmlspecialchars($sql) . "</p>
                <p style='color:#666;font-size:12px;margin:5px 0 0;'>Silakan periksa koneksi database atau hubungi administrator.</p>
            </div>";
            exit();
        }
        return false;
    }
    return $stmt;
}

function safe_sqlsrv_fetch_array($stmt, $fetch_type = SQLSRV_FETCH_ASSOC) {
    if ($stmt === false || $stmt === null) return false;
    return sqlsrv_fetch_array($stmt, $fetch_type);
}

function format_tgl_display($date) {
    if (empty($date)) return '-';
    if (is_object($date) && method_exists($date, 'format')) {
        $d = $date->format('Y-m-d');
        $bulan = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $parts = explode('-', $d);
        return (int)$parts[2] . ' ' . $bulan[(int)$parts[1]-1] . ' ' . $parts[0];
    }
    return $date;
}

function jk_label($jk) {
    return $jk == 1 ? 'Laki-laki' : ($jk == 0 ? 'Perempuan' : '-');
}

function rupiah($n)
{
    return 'Rp ' . number_format($n, 0, ',', '.');
}

function safeQuery($conn, $sql, $params = [])
{
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false)
        return null;
    return $stmt;
}
function safeFetch($stmt)
{
    if ($stmt === null)
        return false;
    return sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC);
}

?>