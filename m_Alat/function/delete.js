function confirmDelete(id, name) {
    Swal.fire({
        title: 'Hapus Data?',
        html: `Hapus alat <strong style="color:var(--orange);">${name}</strong>?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!'
    }).then((result) => {
        if (result.isConfirmed) {
            // Arahkan ke folder action
            window.location.href = 'action/delete.php?delete_id=' + id;
        }
    });
}