// Mengatur SweetAlert untuk Tombol Toggle Status (Aktif/Nonaktif)
window.confirmToggle = function(id, status) {
    let action = status == 1 ? 'menonaktifkan' : 'mengaktifkan';
    Swal.fire({
        title: 'Konfirmasi',
        text: `Yakin ingin ${action} alat ini?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#FF4500',
        confirmButtonText: 'Ya, Ubah!',
        cancelButtonText: 'Batal'
    }).then((res) => {
        if(res.isConfirmed) {
            window.location.href = `?toggle_id=${id}&s=${status}`;
        } else {
            // Kembalikan posisi toggle jika batal
            let cb = document.querySelector(`input[onchange*="${id}"]`);
            if(cb) cb.checked = !cb.checked;
        }
    });
};