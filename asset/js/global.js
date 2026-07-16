// ============================================
// JAM REAL-TIME (AMAN DARI CRASH)
// ============================================
function updateClock() {
    const hEl = document.getElementById('h');
    const mEl = document.getElementById('m');
    const sEl = document.getElementById('s');
    const dateEl = document.getElementById('full-date');

    // Hanya berjalan jika elemen jam ditemukan di halaman
    if (hEl && mEl && sEl) {
        const now = new Date();
        hEl.innerText = String(now.getHours()).padStart(2, '0');
        mEl.innerText = String(now.getMinutes()).padStart(2, '0');
        sEl.innerText = String(now.getSeconds()).padStart(2, '0');

        if (dateEl) {
            const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
            const months = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];

            const dayName = days[now.getDay()];
            const date = now.getDate();
            const monthName = months[now.getMonth()];
            const year = now.getFullYear();

            dateEl.innerText = dayName + ', ' + date + ' ' + monthName + ' ' + year;
        }
    }
}

// Jalankan jam pertama kali setelah DOM siap agar tidak memicu null-pointer error
document.addEventListener('DOMContentLoaded', function() {
    updateClock();
    setInterval(updateClock, 1000);
});

// ============================================
// FILTER DROPDOWN GLOBAL
// ============================================
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