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

    const dayName = days[now.getDay()];
    const date = now.getDate();
    const monthName = months[now.getMonth()];
    const year = now.getFullYear();

    document.getElementById('full-date').innerText = dayName + ', ' + date + ' ' + monthName + ' ' + year;
}

updateClock();
setInterval(updateClock, 1000);


// FILTER DROPDOWN
document.addEventListener('DOMContentLoaded', function() {
    const btnFilterToggle = document.getElementById('btnFilterToggle');
    const filterCard = document.getElementById('filterCard');
    const filterClose = document.getElementById('filterClose');

    if (btnFilterToggle && filterCard) {
        btnFilterToggle.addEventListener('click', function(e) {
            e.stopPropagation();
            this.classList.toggle('active');
            filterCard.classList.toggle('open');
        });

        filterCard.addEventListener('click', function(e) {
            e.stopPropagation();
        });

        if (filterClose) {
            filterClose.addEventListener('click', function() {
                btnFilterToggle.classList.remove('active');
                filterCard.classList.remove('open');
            });
        }

        document.addEventListener('click', function() {
            btnFilterToggle.classList.remove('active');
            filterCard.classList.remove('open');
        });
    }

    // Dropdown profil user
    const userDropdown = document.querySelector('.dropdown-wrap');
    if (userDropdown) {
        userDropdown.addEventListener('click', function (e) {
            e.stopPropagation();
            this.classList.toggle('active');
        });
        document.addEventListener('click', function () {
            userDropdown.classList.remove('active');
        });
    }

    // URL PARAMETER NOTIFICATION
    const urlParams = new URLSearchParams(window.location.search);
    const status = urlParams.get('status');
    const msg = urlParams.get('msg');

    if (status && msg) {
        const isSuccess = status === 'success';

        Swal.fire({
            icon: isSuccess ? 'success' : 'error',
            title: isSuccess ? 'Berhasil!' : 'Gagal!',
            text: msg,
            showConfirmButton: true,
            confirmButtonText: 'OK',
            confirmButtonColor: isSuccess ? '#10B981' : '#EF4444',
            allowOutsideClick: false,
            allowEscapeKey: false
        });

        window.history.replaceState({}, document.title, window.location.pathname);
    }
});