window.confirmToggle = function(id, status) {
    let action = status == 1 ? 'membuat tidak tersedia' : 'menyediakan';
    Swal.fire({
        title: 'Konfirmasi',
        text: `Yakin ingin ${action} jadwal ini?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#FF4500',
        confirmButtonText: 'Ya, Ubah!',
        cancelButtonText: 'Batal'
    }).then((res) => {
        if(res.isConfirmed) window.location.href = `?toggle_id=${id}&s=${status}`;
        else { let cb = document.querySelector(`input[onchange*="${id}"]`); if(cb) cb.checked = !cb.checked; }
    });
};