/**
 * JavaScript interaksi Tab Verifikasi B3 (Modal Verifikasi)
 */
document.addEventListener('DOMContentLoaded', function () {
    var dialog = document.getElementById('b3-modal-verifikasi');
    if (!dialog) return;

    var form = document.getElementById('b3-verifikasi-form');
    var subtitle = document.getElementById('b3-verifikasi-subtitle');
    var itemsContainer = document.getElementById('b3-verifikasi-items');
    var btnTidakSesuai = document.getElementById('b3-btn-tidak-sesuai');

    var currentActionSimpan = '';
    var currentActionTidakSesuai = '';

    document.querySelectorAll('.b3-btn-verifikasi').forEach(function (button) {
        button.addEventListener('click', function () {
            currentActionSimpan = button.dataset.action;
            currentActionTidakSesuai = button.dataset.actionTidakSesuai;

            form.action = currentActionSimpan;

            var desa = button.dataset.desa || '-';
            var petugas = button.dataset.petugas || '-';
            subtitle.textContent = 'Desa ' + desa + ', Petugas ' + petugas + '. Isi jumlah sebenarnya di lapangan.';

            // Parse data items
            var items = [];
            try {
                items = JSON.parse(button.dataset.items || '[]');
            } catch (e) {
                console.error('Gagal membaca data item:', e);
            }

            // Render form item fields
            itemsContainer.innerHTML = '';
            if (items.length === 0) {
                itemsContainer.innerHTML = '<p style="color:#666;font-size:0.85rem">Tidak ada detail item sampah.</p>';
            } else {
                items.forEach(function (item, index) {
                    var wrapper = document.createElement('div');
                    wrapper.className = 'b3-modal-item-row';

                    var didaftarkan = item.jumlah_didaftarkan !== undefined ? item.jumlah_didaftarkan : (item.jumlah || 1);
                    var dibawa = (item.jumlah_petugas !== null && item.jumlah_petugas !== undefined) ? item.jumlah_petugas : didaftarkan;
                    var defaultVal = (item.jumlah_verifikasi !== null && item.jumlah_verifikasi !== undefined) ? item.jumlah_verifikasi : dibawa;

                    var labelText = item.jenis_b3 + ' (didaftarkan ' + didaftarkan + ', dibawa petugas ' + dibawa + ')';

                    wrapper.innerHTML =
                        '<label for="item_input_' + index + '">' + labelText + '</label>' +
                        '<input type="hidden" name="items[' + index + '][id]" value="' + item.id + '">' +
                        '<input type="number" id="item_input_' + index + '" name="items[' + index + '][jumlah]" min="0" value="' + defaultVal + '" required>';

                    itemsContainer.appendChild(wrapper);
                });
            }

            dialog.showModal();
        });
    });

    // Handler tombol "Tidak sesuai"
    if (btnTidakSesuai) {
        btnTidakSesuai.addEventListener('click', function () {
            if (!currentActionTidakSesuai) {
                dialog.close();
                return;
            }

            if (confirm('Tandai data ini tidak sesuai dengan kondisi di lapangan?')) {
                form.action = currentActionTidakSesuai;
                form.submit();
            }
        });
    }

    // Tutup saat klik di backdrop luar modal
    dialog.addEventListener('click', function (e) {
        var rect = dialog.getBoundingClientRect();
        var isInDialog = (
            rect.top <= e.clientY &&
            e.clientY <= rect.top + rect.height &&
            rect.left <= e.clientX &&
            e.clientX <= rect.left + rect.width
        );
        if (!isInDialog) {
            dialog.close();
        }
    });
});

