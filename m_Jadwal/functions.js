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

function searchTable() {
    var input = document.getElementById('src').value.toUpperCase();
    var rows = document.getElementById('tbl').getElementsByTagName('tr');
    
    for (var i = 1; i < rows.length; i++) {
        var tdNama = rows[i].getElementsByTagName('td')[2]; // Kolom Lapangan
        var tdTanggal = rows[i].getElementsByTagName('td')[3]; // Kolom Tanggal
        
        if (tdNama || tdTanggal) {
            var txtValueNama = tdNama.textContent || tdNama.innerText;
            var txtValueTanggal = tdTanggal.textContent || tdTanggal.innerText;
            if (txtValueNama.toUpperCase().indexOf(input) > -1 || txtValueTanggal.toUpperCase().indexOf(input) > -1) {
                rows[i].style.display = "";
            } else {
                rows[i].style.display = "none";
            }
        }
    }
}

function confirmDelete(id, text) {
    Swal.fire({
        title: 'Hapus Jadwal?',
        html: `Apakah anda yakin menghapus jadwal <strong>${text}</strong>?<br><span style='color:red;font-size:12px;'>Data akan dihapus secara (soft delete).</span>`,
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

function validateJadwal() {
    const tgl = document.getElementById('tanggal').value;
    const jamM = document.getElementById('jam_mulai').value;
    const jamS = document.getElementById('jam_selesai').value;

    if (!tgl || !jamM || !jamS) {
        Swal.fire('Error', 'Semua kolom wajib diisi!', 'error');
        return false;
    }

    // Convert time string to minutes for easy calculation
    const timeM = jamM.split(':');
    const timeS = jamS.split(':');
    const minsMulai = parseInt(timeM[0]) * 60 + parseInt(timeM[1]);
    const minsSelesai = parseInt(timeS[0]) * 60 + parseInt(timeS[1]);

    if (minsSelesai <= minsMulai) {
        Swal.fire('Error', 'Jam Selesai harus lebih besar dari Jam Mulai!', 'error');
        return false;
    }

    const duration = minsSelesai - minsMulai;

    // Validasi durasi (60m, 90m, 120m, 180m) sesuai requestmu
    const validDurations = [60, 90, 120, 180];
    
    if (!validDurations.includes(duration)) {
        Swal.fire('Durasi Tidak Valid', 'Durasi main basket harus 1 jam, 1.5 jam, 2 jam, atau 3 jam!', 'warning');
        return false;
    }

    return true;
}