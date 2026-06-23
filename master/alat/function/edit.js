// ============================================================
// MASTER ALAT - edit.js
// ============================================================

window.validateFormEdit = function() {
    let valid = true;
    const nama = document.getElementById('nama_alat');
    const stok = document.getElementById('stok');
    const harga = document.getElementById('harga_alat');
    
    [nama, stok, harga].forEach(el => el.classList.remove('error'));
    
    const nm = nama.value.trim();
    if(nm === '' || nm.length < 3 || nm.length > 25) {
        nama.classList.add('error');
        valid = false;
    }
    
    const sk = stok.value.trim().replace(/[^0-9]/g, '');
    if(sk === '' || Number(sk) <= 0 || Number(sk) > 9999) {
        stok.classList.add('error');
        valid = false;
    }
    
    const hg = harga.value.trim().replace(/[^0-9.]/g, '');
    if(hg === '' || Number(hg) < 0) {
        harga.classList.add('error');
        valid = false;
    }
    
    return valid;
};
