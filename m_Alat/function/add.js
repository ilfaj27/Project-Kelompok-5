// Mengatur Buka, Tutup, dan Validasi Modal Tambah Data Baru
window.openAddModal = function() {
    document.getElementById('modalAddAlat').classList.add('open');
};

window.closeAddModal = function() {
    document.getElementById('modalAddAlat').classList.remove('open');
    document.getElementById('formAddAlat').reset();
    document.querySelectorAll('#modalAddAlat .modal-input').forEach(i => i.classList.remove('error'));
    document.querySelectorAll('#modalAddAlat .val-msg').forEach(m => m.classList.remove('show'));
};

window.validateFormAdd = function() {
    let valid = true;
    
    const nama = document.getElementById('nama_alat_add');
    const stok = document.getElementById('stok_add');
    const harga = document.getElementById('harga_add');
    
    const vNama = document.getElementById('val-nama_alat_add');
    const vStok = document.getElementById('val-stok_add');
    const vHarga = document.getElementById('val-harga_add');

    // Reset UI Error
    [nama, stok, harga].forEach(el => el.classList.remove('error'));
    [vNama, vStok, vHarga].forEach(el => el.classList.remove('show'));

    // Validasi Nama Alat
    const nm = nama.value.trim();
    if(nm === '') {
        nama.classList.add('error'); 
        vNama.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Nama alat wajib diisi'; 
        vNama.classList.add('show'); valid = false;
    } else if(nm.length < 3) {
        nama.classList.add('error'); 
        vNama.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Minimal 3 karakter'; 
        vNama.classList.add('show'); valid = false;
    }

    // Validasi Stok
    if(stok.value.trim() === '') {
        stok.classList.add('error'); 
        vStok.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Stok wajib diisi'; 
        vStok.classList.add('show'); valid = false;
    } else if(Number(stok.value) < 0) {
        stok.classList.add('error'); 
        vStok.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Tidak boleh minus'; 
        vStok.classList.add('show'); valid = false;
    }

    // Validasi Harga
    if(harga.value.trim() === '') {
        harga.classList.add('error'); 
        vHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Harga wajib diisi'; 
        vHarga.classList.add('show'); valid = false;
    } else if(Number(harga.value) < 5000) {
        harga.classList.add('error'); 
        vHarga.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Minimal Rp 5.000'; 
        vHarga.classList.add('show'); valid = false;
    }

    return valid;
};

// Hilangkan error merah saat mengetik di form Add
document.addEventListener('DOMContentLoaded', function() {
    ['nama_alat_add', 'stok_add', 'harga_add'].forEach(id => {
        let el = document.getElementById(id);
        if(el) {
            el.addEventListener('input', function() {
                this.classList.remove('error');
                document.getElementById('val-' + id).classList.remove('show');
            });
        }
    });
});