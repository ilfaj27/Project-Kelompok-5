// Mengatur SweetAlert Konfirmasi Hapus Data
window.confirmDelete = function(id, name) {
    Swal.fire({
        title: 'Hapus Alat?',
        html: `Anda akan menghapus data <strong style="color:red">${name}</strong>.<br>Data akan di-soft-delete!`,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        confirmButtonText: 'Hapus',
        cancelButtonText: 'Batal'
    }).then((res) => {
        if(res.isConfirmed) window.location.href = `?delete_id=${id}`;
    });
};