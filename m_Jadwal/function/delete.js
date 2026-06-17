window.confirmDelete = function(id, name) {
    Swal.fire({
        title: 'Hapus Jadwal?',
        html: `Hapus jadwal <strong style="color:red">${name}</strong> secara permanen?`,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        confirmButtonText: 'Hapus',
        cancelButtonText: 'Batal'
    }).then((res) => {
        if(res.isConfirmed) window.location.href = `?delete_id=${id}`;
    });
};