// ============================================================
// MASTER ALAT - togs.js
// ============================================================

window.confirmToggle = function(id, name, status, event) {
    event.preventDefault();
    let action = status == 1 ? 'menonaktifkan' : 'mengaktifkan';
    
    Swal.fire({
        title: 'Konfirmasi',
        html: `Yakin ingin <strong>${action}</strong> <strong>${name}</strong>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#FF4500',
        confirmButtonText: 'Ya!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if(result.isConfirmed) {
            window.location.href = `index.php?toggle_id=${id}&s=${status}`;
        } else {
            let checkbox = event.target;
            if(checkbox && checkbox.type === 'checkbox') {
                checkbox.checked = !checkbox.checked;
            }
        }
    });
};
