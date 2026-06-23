// ============================================================
// MASTER ALAT - tampilan.js
// ============================================================

document.addEventListener('DOMContentLoaded', function() {
    // JAM DIGITAL
    function updateClock() {
        const now = new Date();
        const h = document.getElementById('h');
        const m = document.getElementById('m');
        const s = document.getElementById('s');
        const fullDate = document.getElementById('full-date');
        
        if(h) h.innerText = String(now.getHours()).padStart(2, '0');
        if(m) m.innerText = String(now.getMinutes()).padStart(2, '0');
        if(s) s.innerText = String(now.getSeconds()).padStart(2, '0');
        
        if(fullDate) {
            const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
            const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
            fullDate.innerText = `${days[now.getDay()]}, ${now.getDate()} ${months[now.getMonth()]} ${now.getFullYear()}`;
        }
    }
    
    if(document.getElementById('h')) {
        setInterval(updateClock, 1000);
        updateClock();
    }

    // FILTER DROPDOWN
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

    // SWEETALERT NOTIFIKASI
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

window.searchTable = function() {
    let input = document.getElementById('src');
    if(!input) return;
    let rows = document.getElementById('tbl');
    if(!rows) rows = document.querySelector('table') || {getElementsByTagName: () => []};
    
    let filter = input.value.toUpperCase();
    let tr = rows.getElementsByTagName('tr');
    for (let i = 1; i < tr.length; i++) {
        let td = tr[i].getElementsByTagName('td')[0];
        if (td) {
            tr[i].style.display = (td.textContent.toUpperCase().indexOf(filter) > -1) ? '' : 'none';
        }
    }
};

window.resetFilter = function() {
    window.location.href = 'index.php';
};
