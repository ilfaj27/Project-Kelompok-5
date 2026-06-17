// Mengatur Jam Live, Pencarian Tabel, Dropdown Filter, dan Notifikasi SweetAlert
document.addEventListener('DOMContentLoaded', function() {
    // 1. JAM DIGITAL
    function updateClock() {
        const now = new Date();
        document.getElementById('h').innerText = String(now.getHours()).padStart(2, '0');
        document.getElementById('m').innerText = String(now.getMinutes()).padStart(2, '0');
        document.getElementById('s').innerText = String(now.getSeconds()).padStart(2, '0');
        const d = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        const m = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        document.getElementById('full-date').innerText = `${d[now.getDay()]}, ${now.getDate()} ${m[now.getMonth()]} ${now.getFullYear()}`;
    }
    setInterval(updateClock, 1000); 
    updateClock();

    // 2. FILTER DROPDOWN
    const btnFilter = document.getElementById('btnFilterToggle');
    const filterCard = document.getElementById('filterCard');
    if (btnFilter && filterCard) {
        btnFilter.addEventListener('click', function(e) {
            e.stopPropagation();
            filterCard.classList.toggle('open');
        });
        document.addEventListener('click', () => filterCard.classList.remove('open'));
        filterCard.addEventListener('click', e => e.stopPropagation());
    }

    // 3. SWEETALERT NOTIFIKASI DARI URL
    const urlParams = new URLSearchParams(window.location.search);
    if(urlParams.get('status')) {
        Swal.fire({
            icon: urlParams.get('status'),
            title: urlParams.get('msg'),
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000
        });
        window.history.replaceState(null, null, window.location.pathname);
    }
});

// 4. FUNGSI PENCARIAN TABEL (Search)
window.searchTable = function() {
    let input = document.getElementById('src').value.toUpperCase();
    let rows = document.getElementById('tbl').getElementsByTagName('tr');
    for (let i = 1; i < rows.length; i++) {
        let tdName = rows[i].getElementsByTagName('td')[2];
        if (tdName) {
            rows[i].style.display = (tdName.textContent.toUpperCase().indexOf(input) > -1) ? '' : 'none';
        }
    }
};