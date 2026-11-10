/* Tab Pendaftaran B3: dialog "Tugaskan petugas". */
document.addEventListener('DOMContentLoaded', function () {
    var dialog = document.getElementById('b3-dialog');
    if (!dialog) return;

    var form = document.getElementById('b3-dialog-form');
    var nama = document.getElementById('b3-dialog-nama');

    document.querySelectorAll('.b3-tugaskan').forEach(function (tombol) {
        tombol.addEventListener('click', function () {
            form.action = tombol.dataset.action;
            nama.textContent = tombol.dataset.nama;
            dialog.showModal();
        });
    });

    document.getElementById('b3-dialog-batal').addEventListener('click', function () {
        dialog.close();
    });
});