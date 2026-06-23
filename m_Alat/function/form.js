function closeModal() {
    window.location.href = 'index.php';
}

function handlePhotoUpload(input) {
    if (!input.files || !input.files[0]) return;
    var uploadArea = document.getElementById('uploadArea');
    var valPhoto = document.getElementById('val-photo_alat');
    if (uploadArea) uploadArea.classList.remove('error');
    if (valPhoto) { valPhoto.classList.remove('show'); valPhoto.innerHTML = ''; }

    var file = input.files[0];
    if (file.size > 5 * 1024 * 1024) {
        Swal.fire({ icon: 'error', title: 'File Terbesar', text: 'Maksimal 5MB!', confirmButtonColor: '#FF4500' });
        input.value = ''; return;
    }
    
    var reader = new FileReader();
    reader.onload = function(e) {
        var previewImg = document.getElementById('previewImg');
        var uploadPlaceholder = document.getElementById('uploadPlaceholder');
        var removeBtn = document.getElementById('removeBtn');
        if (previewImg) { previewImg.src = e.target.result; previewImg.style.display = 'block'; }
        if (uploadArea) uploadArea.classList.add('has-image');
        if (uploadPlaceholder) uploadPlaceholder.style.display = 'none';
        if (removeBtn) removeBtn.style.display = 'flex';
    };
    reader.readAsDataURL(file);
}

function removePhoto() {
    var previewImg = document.getElementById('previewImg');
    var uploadPlaceholder = document.getElementById('uploadPlaceholder');
    var fileInput = document.getElementById('photo_alat');
    var uploadArea = document.getElementById('uploadArea');
    var removeBtn = document.getElementById('removeBtn');
    
    if (fileInput) fileInput.value = '';
    if (previewImg) { previewImg.src = ''; previewImg.style.display = 'none'; }
    if (uploadArea) {
        uploadArea.classList.remove('has-image');
        var isEditMode = document.querySelector('input[name="edit_mode"]') !== null;
        var editPhotoPath = document.querySelector('input[name="edit_photo_path"]');
        if (isEditMode && editPhotoPath && editPhotoPath.value) { editPhotoPath.value = ''; }
    }
    if (uploadPlaceholder) uploadPlaceholder.style.display = 'flex';
    if (removeBtn) removeBtn.style.display = 'none';
}

function validateForm() {
    var valid = true;
    document.querySelectorAll('.modal-input').forEach(el => el.classList.remove('error'));
    document.querySelectorAll('.val-msg').forEach(el => { el.classList.remove('show'); el.innerHTML = ''; });

    var nama = document.getElementById('nama_alat');
    if (nama && nama.value.trim() === '') {
        nama.classList.add('error');
        document.getElementById('val-nama_alat').innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Wajib diisi.';
        document.getElementById('val-nama_alat').classList.add('show');
        valid = false;
    }

    var stok = document.getElementById('stok');
    if (stok && stok.value.trim() === '') {
        stok.classList.add('error');
        document.getElementById('val-stok').innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Wajib diisi.';
        document.getElementById('val-stok').classList.add('show');
        valid = false;
    }

    if (!valid) return false;

    var btn = document.getElementById('btnSubmit');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Memproses...'; }
    return true;

}// function/form.js

function openAddModal() {
        document.getElementById('modalAddAlat').classList.add('open');
    }

function closeAddModal() {
        document.getElementById('modalAddAlat').classList.remove('open');
    }

function closeEditModal() {
        window.location.href = 'index.php'; // Hapus ?edit_id dari URL
    }