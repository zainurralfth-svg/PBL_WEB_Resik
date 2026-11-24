{{-- Tab Rekap versi Sub Desa: tampilan saja, data contoh. Export Excel memakai backend. --}}
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
    <a href="{{ route('admin.bank-sampah.b3.rekap.export') }}" class="sdr-btn sdr-btn--link">Export Excel</a>
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