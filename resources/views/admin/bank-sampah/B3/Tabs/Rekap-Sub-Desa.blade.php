{{-- Tab Rekap versi Sub Desa: tampilan saja, data contoh. Backend nanti bisa kirim $rsItems --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/b3-rekap-sub-desa.css?v=' . time()) }}">
@endpush

@php
    $rsItems = $rsItems ?? [['jenis' => 'Lampu neon', 'jumlah' => 6, 'nilai' => 9000]];

    // Kolom cari di Index mengirim ?q=...
    $q = strtolower(trim(request('q', '')));
    $rsBaris = array_filter($rsItems, fn ($i) => $q === '' || str_contains(strtolower($i['jenis']), $q));
    $rsTampilDesa = $q === '' || str_contains('payaman', $q) || str_contains('per desa', $q);
@endphp

<div class="sdr-actions">
    <button type="button" class="sdr-btn" id="sdr-btnExport">Export Excel</button>
</div>

<div class="sdr-table-wrap">
    <table class="sdr-table">
        <thead>
            <tr>
                <th>Jenis B3</th>
                <th class="sdr-tengah">Jumlah</th>
                <th class="sdr-tengah">Nilai Saldo</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rsBaris as $i)
                <tr>
                    <td>{{ $i['jenis'] }}</td>
                    <td class="sdr-tengah">{{ $i['jumlah'] }} item</td>
                    <td class="sdr-tengah">Rp {{ number_format($i['nilai'], 0, ',', '.') }}</td>
                </tr>
            @empty
                <tr><td colspan="3" class="sdr-muted">Data tidak ditemukan.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($rsTampilDesa)
    <div class="sdr-box">Per desa: Payaman 1 warga</div>
@endif

<div class="sdr-toast" id="sdr-toast"></div>

@push('scripts')
<script>
(function () {
    const items = @json(array_values($rsItems));
    const $ = id => document.getElementById(id);

    function toast(msg) {
        const t = $('sdr-toast'); t.textContent = msg; t.classList.add('show');
        setTimeout(() => t.classList.remove('show'), 2200);
    }

    $('sdr-btnExport').onclick = () => {
        const csv = 'Jenis B3,Jumlah,Nilai Saldo\n' + items.map(i => `${i.jenis},${i.jumlah},${i.nilai}`).join('\n');
        const a = document.createElement('a');
        a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv' }));
        a.download = 'rekap-b3-desa.csv'; a.click();
        toast('Rekap diekspor');
    };
})();
</script>
@endpush