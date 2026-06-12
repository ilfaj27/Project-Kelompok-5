// Jam Digital
function updateClock() {
    const now = new Date();
    const h = String(now.getHours()).padStart(2, '0');
    const m = String(now.getMinutes()).padStart(2, '0');
    const s = String(now.getSeconds()).padStart(2, '0');
    document.getElementById('h').innerText = h;
    document.getElementById('m').innerText = m;
    document.getElementById('s').innerText = s;

    const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    document.getElementById('full-date').innerText = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
}
setInterval(updateClock, 1000);
updateClock();

// Search Table
function searchTable() {
    var input = document.getElementById('src').value.toUpperCase();
    var rows = document.getElementById('tbl').getElementsByTagName('tr');
    
    for (var i = 1; i < rows.length; i++) {
        var tdNama = rows[i].getElementsByTagName('td')[2]; // Index 2 = Kolom Nama (0:Foto, 1:Status)
        var tdId = rows[i].getElementsByTagName('td')[3]; // Index 3 = Kolom ID
        
        if (tdNama || tdId) {
            var match = false;
            if (tdNama && tdNama.textContent.toUpperCase().indexOf(input) > -1) match = true;
            if (tdId && tdId.textContent.toUpperCase().indexOf(input) > -1) match = true;
            rows[i].style.display = match ? '' : 'none';
        }
    }
}

// Konfirmasi Delete
function confirmDelete(id, nama) {
    Swal.fire({
        title: 'Hapus Master Alat?',
        html: `Apakah anda yakin menghapus alat <strong>${nama}</strong>?<br><span style='color:red;font-size:12px;'>Data akan disembunyikan (soft delete).</span>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'delete.php?id=' + id;
        }
    });
}

// Validasi Form (Create / Update)
function validateForm() {
    const stok = document.getElementById('stok').value;
    const harga = document.getElementById('harga').value;
    const foto = document.getElementById('foto').files[0];

    if(stok < 0) {
        Swal.fire('Error!', 'Stok tidak boleh minus!', 'error');
        return false;
    }
    if(harga <= 0) {
        Swal.fire('Error!', 'Harga harus lebih dari Rp 0!', 'error');
        return false;
    }
    
    // Validasi file foto jika ada
    if(foto) {
        const allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        if(!allowedTypes.includes(foto.type)) {
            Swal.fire('Error!', 'Format foto harus JPG atau PNG!', 'error');
            return false;
        }
        if(foto.size > 2 * 1024 * 1024) { // 2MB
            Swal.fire('Error!', 'Ukuran foto maksimal 2MB!', 'error');
            return false;
        }
    }
    return true;
}