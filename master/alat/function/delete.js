// ============================================================
// MASTER ALAT - delete.js
// ============================================================

window.doDelete = function(id, name) {
    Swal.fire({
        title: 'Hapus Alat?',
        html: `Anda akan menghapus <strong>${name}</strong><br>Data akan di-soft-delete!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if(result.isConfirmed) {
            window.location.href = `index.php?delete_id=${id}`;
        }
    });
};
