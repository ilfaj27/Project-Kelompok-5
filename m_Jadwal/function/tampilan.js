document.addEventListener('DOMContentLoaded', function() {
    function updateClock() {
        const now = new Date();
        document.getElementById('h').innerText = String(now.getHours()).padStart(2, '0');
        document.getElementById('m').innerText = String(now.getMinutes()).padStart(2, '0');
        document.getElementById('s').innerText = String(now.getSeconds()).padStart(2, '0');
        const d = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
        const m = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        document.getElementById('full-date').innerText = `${d[now.getDay()]}, ${now.getDate()} ${m[now.getMonth()]} ${now.getFullYear()}`;
    }
    setInterval(updateClock, 1000); updateClock();

    const btnFilter = document.getElementById('btnFilterToggle');
    const filterCard = document.getElementById('filterCard');
    if (btnFilter && filterCard) {
        btnFilter.addEventListener('click', e => { e.stopPropagation(); filterCard.classList.toggle('open'); });
        document.addEventListener('click', () => filterCard.classList.remove('open'));
        filterCard.addEventListener('click', e => e.stopPropagation());
    }

    const urlParams = new URLSearchParams(window.location.search);
    if(urlParams.get('status')) {
        Swal.fire({ icon: urlParams.get('status'), title: urlParams.get('msg'), toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
        window.history.replaceState(null, null, window.location.pathname);
    }
});

window.searchTable = function() {
    let input = document.getElementById('src').value.toUpperCase();
    let rows = document.getElementById('tbl').getElementsByTagName('tr');
    for (let i = 1; i < rows.length; i++) {
        let tdName = rows[i].getElementsByTagName('td')[1];
        if (tdName) {
            rows[i].style.display = (tdName.textContent.toUpperCase().indexOf(input) > -1) ? '' : 'none';
        }
    }
};