// Menjalankan Jam Digital
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

// Jalankan jam setiap detik
setInterval(updateClock, 1000);
updateClock(); // Panggil sekali langsung agar tidak nunggu 1 detik

// Fungsi Pencarian Tabel
function searchTable() {
    var input = document.getElementById('src').value.toUpperCase();
    var rows = document.getElementById('tbl').getElementsByTagName('tr');
    
    for (var i = 1; i < rows.length; i++) {
        var tdNama = rows[i].getElementsByTagName('td')[2];
        var tdId = rows[i].getElementsByTagName('td')[3];
        
        if (tdNama || tdId) {
            var txtValueNama = tdNama.textContent || tdNama.innerText;
            var txtValueId = tdId.textContent || tdId.innerText;
            if (txtValueNama.toUpperCase().indexOf(input) > -1 || txtValueId.toUpperCase().indexOf(input) > -1) {
                rows[i].style.display = "";
            } else {
                rows[i].style.display = "none";
            }
        }
    }
}

// Validasi saat Create/Update
function validateForm() {
    const stok = document.getElementById('stok').value;
    const harga = document.getElementById('harga').value;

    if(stok < 0) {
        Swal.fire('Error!', 'Stok tidak boleh minus!', 'error');
        return false;
    }
    if(harga <= 0) {
        Swal.fire('Error!', 'Harga harus lebih dari Rp 0!', 'error');
        return false;
    }
    return true;
}

// SweetAlert Hapus
function confirmDelete(id, nama) {
    Swal.fire({
        title: 'Hapus Master Alat?',
        html: `Anda akan menyembunyikan alat <strong>${nama}</strong>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#EF4444',
        cancelButtonColor: '#6B7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'action/delete.php?id=' + id;
        }
    });
}