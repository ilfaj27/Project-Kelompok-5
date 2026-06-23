// Jam Realtime
function updateClock() {
    var now = new Date();
    var h = String(now.getHours()).padStart(2,'0');
    var m = String(now.getMinutes()).padStart(2,'0');
    var s = String(now.getSeconds()).padStart(2,'0');
    if(document.getElementById('clock-h')) document.getElementById('clock-h').textContent = h;
    if(document.getElementById('clock-m')) document.getElementById('clock-m').textContent = m;
    if(document.getElementById('clock-s')) document.getElementById('clock-s').textContent = s;
    
    var days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
    var months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    if(document.getElementById('full-date')) document.getElementById('full-date').textContent = days[now.getDay()] + ', ' + now.getDate() + ' ' + months[now.getMonth()] + ' ' + now.getFullYear();
}
setInterval(updateClock, 1000); updateClock();

// Search Khusus Tabel
function searchTable() {
    let input = document.getElementById("src").value.toLowerCase();
    let rows = document.querySelectorAll("#tbl tbody tr");
    rows.forEach(row => {
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(input) ? "" : "none";
    });
}

// Fitur Dropdown User
function toggleUserDropdown() {
    var dd = document.getElementById('userDropdown');
    if (dd) dd.classList.toggle('active');
}
document.addEventListener('click', e => {
    var dd = document.getElementById('userDropdown');
    if (dd && !dd.contains(e.target) && !e.target.closest('.topbar-user')) dd.classList.remove('active');
});

// Penangkap Notifikasi / Alert Sukses dari PHP
document.addEventListener('DOMContentLoaded', () => {
    var urlParams = new URLSearchParams(window.location.search);
    var status = urlParams.get('status');
    var msg = urlParams.get('msg');

    if (status && msg) {
        Swal.fire({
            icon: status === 'success' ? 'success' : 'error',
            title: status === 'success' ? 'Berhasil!' : 'Gagal!',
            text: msg,
            toast: true, position: 'top-end', timer: 3000, showConfirmButton: false, timerProgressBar: true
        });
        window.history.replaceState({}, document.title, window.location.pathname);
    }
    
    // Toggle Modal Filter
    var btnFilterToggle = document.getElementById('btnFilterToggle');
    var filterCard = document.getElementById('filterCard');
    if (btnFilterToggle && filterCard) {
        btnFilterToggle.addEventListener('click', e => { e.stopPropagation(); filterCard.classList.toggle('open'); });
        filterCard.addEventListener('click', e => e.stopPropagation());
        document.addEventListener('click', () => filterCard.classList.remove('open'));
    }
});