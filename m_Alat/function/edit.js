// Mengatur Tutup Modal Edit (Membukanya dikendalikan via PHP $_GET) dan Validasi Edit
window.closeEditModal = function() {
    // Karena edit dibuka via URL parameter ?edit_id=..., menutupnya berarti mereset URL
    window.location.href = 'index.php';
};

window.validateFormEdit = function() {
    let valid = true;
    
    const nama = document.getElementById('nama_alat_edit');
    const stok = document.getElementById('stok_edit');
    const harga = document.getElementById('harga_edit');
    
    const vNama = document.getElementById('val-nama_alat_edit');
    const vStok = document.getElementById('val-stok_edit');
    const vHarga = document.getElementById('val-harga_edit');

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

// Hilangkan error merah saat mengetik di form Edit
document.addEventListener('DOMContentLoaded', function() {
    ['nama_alat_edit', 'stok_edit', 'harga_edit'].forEach(id => {
        let el = document.getElementById(id);
        if(el) {
            el.addEventListener('input', function() {
                this.classList.remove('error');
                let valBox = document.getElementById('val-' + id);
                if(valBox) valBox.classList.remove('show');
            });
        }
    });
});