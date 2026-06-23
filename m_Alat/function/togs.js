function confirmToggle(id, name, currentStatus, event) {
    var checkbox = event.target;
    var newStatus = currentStatus === 1 ? 0 : 1;
    var statusText = newStatus === 1 ? 'Aktif' : 'Nonaktif';
    var icon = newStatus === 1 ? 'success' : 'warning';
    
    Swal.fire({
        title: 'Ubah Status?',
        html: `Ubah status <strong style="color:var(--orange);">${name}</strong> menjadi <strong>${statusText}</strong>?`,
        icon: icon,
        showCancelButton: true,
        confirmButtonColor: newStatus === 1 ? '#10B981' : '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Ubah!',
        cancelButtonText: 'Batal',
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Arahkan ke folder action
            window.location.href = 'action/toggle.php?toggle_id=' + id + '&s=' + currentStatus;
        } else {
            checkbox.checked = !checkbox.checked;
        }
    });
}