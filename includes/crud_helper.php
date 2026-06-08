<?php
// crud_helper.php

function soft_delete($conn, $tabel, $pk_kolom, $id) {
    $sql = "UPDATE $tabel SET is_deleted = 1 WHERE $pk_kolom = ?";
    $stmt = sqlsrv_query($conn, $sql, array($id));
    return $stmt;
}

function ambil_data_aktif($conn, $tabel) {
    $sql = "SELECT * FROM $tabel WHERE is_deleted = 0";
    return sqlsrv_query($conn, $sql);
}
?>