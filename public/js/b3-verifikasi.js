/**
 * Tab Verifikasi B3: modal Verifikasi (jumlah, nilai saldo, catatan) dan modal Tandai Tidak Sesuai.
 */
document.addEventListener('DOMContentLoaded', function () {
    var dialog = document.getElementById('b3-modal-verifikasi');
    if (!dialog) return;

    var form = document.getElementById('b3-verifikasi-form');
    var title = document.getElementById('b3-verifikasi-title');
    var subtitle = document.getElementById('b3-verifikasi-subtitle');
    var itemsContainer = document.getElementById('b3-verifikasi-items');
    var saldoBox = document.getElementById('b3-verifikasi-saldo');
    var fotoBox = document.getElementById('b3-verifikasi-foto');
    var btnTidakSesuai = document.getElementById('b3-btn-tidak-sesuai');

    var ts = document.getElementById('b3-modal-ts');
    var tsForm = document.getElementById('b3-ts-form');
    var tsSub = document.getElementById('b3-ts-sub');
    var tsChips = document.getElementById('b3-ts-chips');
    var tsDetail = document.getElementById('b3-ts-detail');
    var tsAlasan = document.getElementById('b3-ts-alasan');
    var tsErr = document.getElementById('b3-ts-err');

    var currentTidakSesuai = '';
    var currentNama = '';
    var currentJenis = '';
    var currentItems = [];

    function rupiah(n) { return 'Rp ' + Math.round(n).toLocaleString('id-ID'); }
    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function hitungSaldo() {
        var rincian = [];
        var total = 0;
        var lengkap = currentItems.length > 0;
        currentItems.forEach(function (item, i) {
            var input = document.getElementById('item_input_' + i);
            var jml = input ? parseFloat(input.value) || 0 : 0;
            if (typeof item.tarif !== 'number') { lengkap = false; return; }
            total += jml * item.tarif;
            rincian.push(jml + ' \u00d7 ' + rupiah(item.tarif));
        });
        if (!lengkap) { saldoBox.hidden = true; return; }
        saldoBox.hidden = false;
        saldoBox.innerHTML = 'Nilai saldo: ' + rincian.join(' + ') + ' = <strong>' + rupiah(total) + '</strong>';
    }

    document.querySelectorAll('.b3-btn-verifikasi:not(.b3-btn-verifikasi--off)').forEach(function (button) {
        button.addEventListener('click', function () {
            form.action = button.dataset.action;
            currentTidakSesuai = button.dataset.actionTidakSesuai;
            currentNama = button.dataset.nama || '';

            var desa = button.dataset.desa || '-';
            var petugas = button.dataset.petugas || '-';
            subtitle.textContent = (currentNama ? currentNama + ' \u00b7 ' : '') + 'Desa ' + desa +
                ' \u00b7 Petugas ' + petugas + '. Isi jumlah sebenarnya di lapangan.';

            // Foto bukti (hanya kalau server mengirim data-foto)
            if (button.dataset.foto) {
                document.getElementById('b3-verifikasi-foto-img').src = button.dataset.foto;
                document.getElementById('b3-verifikasi-foto-link').href = button.dataset.foto;
                document.getElementById('b3-verifikasi-foto-ket').textContent = button.dataset.fotoKet || 'Foto bukti petugas';
                fotoBox.hidden = false;
            } else {
                fotoBox.hidden = true;
            }

            currentItems = [];
            try { currentItems = JSON.parse(button.dataset.items || '[]'); }
            catch (e) { console.error('Gagal membaca data item:', e); }
            currentJenis = currentItems.map(function (it) { return it.jenis_b3; }).join(', ');

            itemsContainer.innerHTML = '';
            if (currentItems.length === 0) {
                itemsContainer.innerHTML = '<p style="color:#666;font-size:0.85rem">Tidak ada detail item sampah.</p>';
            }
            currentItems.forEach(function (item, index) {
                var wrapper = document.createElement('div');
                wrapper.className = 'b3-modal-item-row';

                var didaftarkan = item.jumlah_didaftarkan !== undefined ? item.jumlah_didaftarkan : (item.jumlah || 1);
                var dibawa = (item.jumlah_petugas !== null && item.jumlah_petugas !== undefined) ? item.jumlah_petugas : didaftarkan;
                var def = (item.jumlah_verifikasi !== null && item.jumlah_verifikasi !== undefined) ? item.jumlah_verifikasi : dibawa;

                wrapper.innerHTML =
                    '<label for="item_input_' + index + '">' + esc(item.jenis_b3) +
                    ' <small style="font-weight:400;color:#666">(didaftarkan ' + didaftarkan + ', dibawa petugas ' + dibawa + ')</small></label>' +
                    '<input type="hidden" name="items[' + index + '][id]" value="' + item.id + '">' +
                    '<input type="number" id="item_input_' + index + '" name="items[' + index + '][jumlah]" min="0" value="' + def + '" required>';
                itemsContainer.appendChild(wrapper);
                wrapper.querySelector('input[type=number]').addEventListener('input', hitungSaldo);
            });

            document.getElementById('b3-verifikasi-catatan').value = '';
            hitungSaldo();
            dialog.showModal();
        });
    });

    // ---- Tandai Tidak Sesuai ----
    function pilihChip(chip) {
        tsChips.querySelectorAll('.b3-chip').forEach(function (c) { c.classList.toggle('is-on', c === chip); });
        tsErr.style.display = 'none';
    }
    if (ts && tsChips) {
        tsChips.querySelectorAll('.b3-chip').forEach(function (chip) {
            chip.addEventListener('click', function () { pilihChip(chip); });
        });
        document.getElementById('b3-ts-batal').addEventListener('click', function () { ts.close(); });
        ts.addEventListener('click', function (e) { if (e.target === ts) ts.close(); });

        tsForm.addEventListener('submit', function (e) {
            var chip = tsChips.querySelector('.b3-chip.is-on');
            var detail = tsDetail.value.trim();
            var alasan = chip ? chip.textContent.trim() : '';
            if (chip && chip.textContent.trim() === 'Lainnya' && !detail) alasan = '';
            if (!alasan) { e.preventDefault(); tsErr.style.display = 'block'; return; }
            tsAlasan.value = (chip.textContent.trim() === 'Lainnya' ? '' : alasan + (detail ? ': ' : '')) + detail;
        });
    }

    if (btnTidakSesuai) {
        btnTidakSesuai.addEventListener('click', function () {
            if (!currentTidakSesuai || !ts) { dialog.close(); return; }
            tsForm.action = currentTidakSesuai;
            tsSub.textContent = (currentNama ? currentNama + ' \u00b7 ' : '') + currentJenis +
                '. Alasan akan tampil di aplikasi warga (detail pendaftaran).';
            tsDetail.value = '';
            tsErr.style.display = 'none';
            pilihChip(tsChips.querySelector('.b3-chip'));
            dialog.close();
            ts.showModal();
        });
    }

    // Tutup saat klik di backdrop luar modal
    dialog.addEventListener('click', function (e) {
        if (e.target === dialog) dialog.close();
    });
});