window.closeEditModal = function() { window.location.href = 'index.php'; };

window.validateJadwalEdit = function() {
    let valid = true;
    const tgl = document.getElementById('tanggal_edit');
    const jm = document.getElementById('jam_mulai_edit');
    const js = document.getElementById('jam_selesai_edit');
    const vTgl = document.getElementById('val-tanggal_edit');
    const vJm = document.getElementById('val-jam_mulai_edit');
    const vJs = document.getElementById('val-jam_selesai_edit');

    [tgl, jm, js].forEach(el => el.classList.remove('error'));
    [vTgl, vJm, vJs].forEach(el => el.classList.remove('show'));

    if(!tgl.value) { tgl.classList.add('error'); vTgl.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Tanggal wajib diisi'; vTgl.classList.add('show'); valid = false; }
    if(!jm.value) { jm.classList.add('error'); vJm.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Jam mulai wajib diisi'; vJm.classList.add('show'); valid = false; }
    if(!js.value) { js.classList.add('error'); vJs.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Jam selesai wajib diisi'; vJs.classList.add('show'); valid = false; }

    if(jm.value && js.value) {
        let startD = new Date(`1970-01-01T${jm.value}:00`);
        let endD = new Date(`1970-01-01T${js.value}:00`);
        let diffMins = (endD - startD) / 60000;

        if(diffMins <= 0) {
            js.classList.add('error'); vJs.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Jam selesai harus lebih besar'; vJs.classList.add('show'); valid = false;
        } else {
            let allowed = [60, 90, 120, 180];
            if(!allowed.includes(diffMins)) {
                js.classList.add('error'); vJs.innerHTML = '<i class="fa-solid fa-circle-exclamation"></i> Durasi hanya boleh 1, 1.5, 2, atau 3 jam'; vJs.classList.add('show'); valid = false;
            }
        }
    }
    return valid;
};

document.addEventListener('DOMContentLoaded', function() {
    ['tanggal_edit', 'jam_mulai_edit', 'jam_selesai_edit'].forEach(id => {
        let el = document.getElementById(id);
        if(el) el.addEventListener('input', function() { this.classList.remove('error'); let valBox = document.getElementById('val-' + id); if(valBox) valBox.classList.remove('show'); });
    });
});