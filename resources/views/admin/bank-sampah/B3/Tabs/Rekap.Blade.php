{{-- Tab Rekap: tampilan saja, data contoh. Backend nanti bisa kirim $rsItems dan $rsRiwayat --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/b3-rekap-serah-terima.css?v=' . time()) }}">
@endpush

@php
    $rsItems = $rsItems ?? [['jenis' => 'Lampu neon', 'jumlah' => 6, 'nilai' => 9000]];
    $rsRiwayat = $rsRiwayat ?? [['tgl' => '20 Okt', 'jumlah' => 6, 'tujuan' => 'TPS B3 DLH', 'penerima' => 'Petugas gudang DLH']];

    // Kolom cari di Index mengirim ?q=...
    $q = strtolower(trim(request('q', '')));
    $rsBaris = array_filter($rsItems, fn ($i) => $q === '' || str_contains(strtolower($i['jenis']), $q));
    $rsTampilDesa = $q === '' || str_contains('payaman', $q) || str_contains('per desa', $q);
@endphp

<div class="rs-actions">
    <button type="button" class="rs-btn rs-btn--hijau" id="rs-btnExport">Export Excel</button>
    <button type="button" class="rs-btn rs-btn--hijau" id="rs-btnOpen">Catat Serah Terima</button>
</div>

<table class="rs-table">
    <thead>
        <tr><th>Jenis B3</th><th>Jumlah</th><th class="rs-kanan">Nilai Saldo</th></tr>
    </thead>
    <tbody>
        @forelse ($rsBaris as $i)
            <tr>
                <td>{{ $i['jenis'] }}</td>
                <td>{{ $i['jumlah'] }} item</td>
                <td class="rs-kanan">Rp {{ number_format($i['nilai'], 0, ',', '.') }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="rs-muted">Data tidak ditemukan.</td></tr>
        @endforelse
    </tbody>
</table>

@if ($rsTampilDesa)
    <div class="rs-box">Per desa: Payaman 1 warga</div>
@endif

<div class="rs-box">
    <h3>Riwayat serah terima</h3>
    <div id="rs-riwayat"></div>
</div>

@include('admin.bank-sampah.B3.Modals.Serah-Terima')
<div class="rs-toast" id="rs-toast"></div>

@push('scripts')
<script>
(function () {
    const items = @json(array_values($rsItems));
    const riwayat = @json($rsRiwayat);
    const bulan = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    const defaultText = 'Unggah berkas (prototype)';
    const $ = id => document.getElementById(id);

    function renderRiwayat() {
        $('rs-riwayat').innerHTML = riwayat.length
            ? riwayat.map(r => `<p>${r.tgl}: ${r.jumlah} item ke ${r.tujuan}, penerima ${r.penerima}</p>`).join('')
            : '<p class="rs-muted">Belum ada riwayat.</p>';
    }
    function toast(msg) {
        const t = $('rs-toast'); t.textContent = msg; t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 2200);
    }
    function openModal() { $('rs-overlay').classList.add('show'); $('rs-penerima').focus(); }
    function closeModal() {
        $('rs-overlay').classList.remove('show');
        $('rs-penerima').value = ''; $('rs-berkas').value = '';
        $('rs-upload-text').textContent = defaultText; $('rs-upload').classList.remove('has-file');
        $('rs-err').style.display = 'none';
    }

    $('rs-btnOpen').onclick = openModal;
    $('rs-btnBatal').onclick = closeModal;
    $('rs-overlay').onclick = e => { if (e.target.id === 'rs-overlay') closeModal(); };
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

    $('rs-upload').onclick = () => $('rs-berkas').click();
    $('rs-berkas').onchange = e => {
        const f = e.target.files[0];
        $('rs-upload-text').textContent = f ? f.name : defaultText;
        $('rs-upload').classList.toggle('has-file', !!f);
    };

    $('rs-btnSimpan').onclick = () => {
        const nama = $('rs-penerima').value.trim();
        if (!nama) { $('rs-err').style.display = 'block'; return; }
        const d = new Date($('rs-tanggal').value);
        const total = items.reduce((s, i) => s + i.jumlah, 0);
        riwayat.unshift({ tgl: `${d.getDate()} ${bulan[d.getMonth()]}`, jumlah: total, tujuan: $('rs-tujuan').value, penerima: nama });
        renderRiwayat(); closeModal(); toast('Serah terima berhasil dicatat');
    };

    $('rs-btnExport').onclick = () => {
        const csv = 'Jenis B3,Jumlah,Nilai Saldo\n' + items.map(i => `${i.jenis},${i.jumlah},${i.nilai}`).join('\n');
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        a.download = 'rekap-b3.csv'; a.click();
        toast('Rekap diekspor');
    };

    renderRiwayat();
})();
</script>
@endpush