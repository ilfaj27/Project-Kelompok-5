<?php
session_start();
include '../../includes/config.php';
include 'helper.php';

$nama_user = $_SESSION['nama'] ?? 'Karyawan';

// 1. PROSES TAMBAH ALAT (ADD)
if (isset($_POST['save_alat_add'])) {
    $id_baru = $_POST['id_alat_add'];
    $nama_alat = trim($_POST['nama_alat_add']);
    $stok = (int)$_POST['stok_add'];
    $harga = (float)$_POST['harga_add'];
    $foto_name = null;

    if (isset($_FILES['foto_add']) && $_FILES['foto_add']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['foto_add']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['foto_add']['size'] <= 2097152) { 
            $foto_name = $id_baru . "_" . time() . "." . $ext;
            move_uploaded_file($_FILES['foto_add']['tmp_name'], "../uploads/" . $foto_name);
        } else {
            header("Location: ../index.php?status=error&msg=Gagal upload! Format salah atau ukuran > 2MB.");
            exit();
        }
    }

    $sql = "INSERT INTO Alat (ID_Alat, Nama_Alat, Stok, Harga_Alat, Status, Is_Deleted, Created_By, Created_Date, Foto_Alat) 
            VALUES (?, ?, ?, ?, 1, 0, ?, GETDATE(), ?)";
    $stmt = safeQuery($conn, $sql, array($id_baru, $nama_alat, $stok, $harga, $nama_user, $foto_name));

    header("Location: ../index.php?status=success&msg=Alat baru berhasil ditambahkan!");
    exit();
}

// 2. PROSES EDIT ALAT (UPDATE)
if (isset($_POST['save_alat_edit'])) {
    $id_edit = $_POST['id_alat_edit'];
    $nama_alat = trim($_POST['nama_alat_edit']);
    $stok = (int)$_POST['stok_edit'];
    $harga = (float)$_POST['harga_edit'];
    $foto_name = $_POST['foto_lama'] ?? null;

    if (isset($_FILES['foto_edit']) && $_FILES['foto_edit']['error'] === 0) {
        $allowed = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['foto_edit']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed) && $_FILES['foto_edit']['size'] <= 2097152) { 
            $foto_name = $id_edit . "_" . time() . "." . $ext;
            move_uploaded_file($_FILES['foto_edit']['tmp_name'], "../uploads/" . $foto_name);
            
            if(!empty($_POST['foto_lama']) && file_exists("../uploads/" . $_POST['foto_lama'])) {
                unlink("../uploads/" . $_POST['foto_lama']);
            }
        } else {
            header("Location: ../index.php?status=error&msg=Gagal upload foto edit! Periksa format & ukuran.");
            exit();
        }
    }

    $sql = "UPDATE Alat SET Nama_Alat=?, Stok=?, Harga_Alat=?, Foto_Alat=?, Modified_By=?, Modified_Date=GETDATE() WHERE ID_Alat=?";
    $stmt = safeQuery($conn, $sql, array($nama_alat, $stok, $harga, $foto_name, $nama_user, $id_edit));

    header("Location: ../index.php?status=success&msg=Alat berhasil diperbarui!");
    exit();
}
?>