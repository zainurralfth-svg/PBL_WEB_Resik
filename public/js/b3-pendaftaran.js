/* Tab Pendaftaran & Periode B3: dialog "Tugaskan petugas" dan tombol "Tugaskan semua". */
document.addEventListener('DOMContentLoaded', function () {
    function toast(pesan) {
        var t = document.getElementById('b3-toast');
        if (!t) {
            t = document.createElement('div');
            t.id = 'b3-toast';
            t.className = 'b3-toast';
            t.setAttribute('role', 'status');
            document.body.appendChild(t);
        }
        t.textContent = pesan;
        t.classList.add('show');
        setTimeout(function () { t.classList.remove('show'); }, 2600);
    }

    var semua = document.getElementById('b3-tugaskan-semua');
    if (semua) {
        semua.addEventListener('click', function () {
            var form = document.getElementById('b3-tugaskan-semua-form');
            if (!semua.dataset.action || !form) {
                toast('Fitur "Tugaskan semua" belum tersedia di server.');
                return;
            }
            if (confirm('Tugaskan semua pendaftaran berstatus Terdaftar ke petugas periode?')) {
                form.submit();
            }
        });
    }

    var dialog = document.getElementById('b3-dialog');
    if (!dialog) return;

    var form = document.getElementById('b3-dialog-form');
    var nama = document.getElementById('b3-dialog-nama');
    var info = document.getElementById('b3-dialog-info');
    var pilih = document.getElementById('b3-petugas');

    document.querySelectorAll('.b3-tugaskan').forEach(function (tombol) {
        tombol.addEventListener('click', function () {
            form.action = tombol.dataset.action;
            nama.textContent = tombol.dataset.nama;
            if (info) info.textContent = tombol.dataset.info ? ' \u00b7 ' + tombol.dataset.info : '';
            if (pilih && tombol.dataset.petugas) pilih.value = tombol.dataset.petugas;
            dialog.showModal();
        });
    });

    document.getElementById('b3-dialog-batal').addEventListener('click', function () {
        dialog.close();
    });
});