{{-- Tab PERIODE (FRONTEND SAJA). Dipanggil dari B3/Index.blade.php.
     Sekarang memakai DATA CONTOH. Nanti, kalau controller sudah mengirim $periodes,
     $terdaftar, $kecamatans, $petugasDlh, dan $bisaKelola, blok "MODE CONTOH" di bawah
     otomatis tidak dipakai dan tampilan memakai data asli. --}}

@php
    // Rute dipakai kalau sudah didaftarkan; kalau belum, tombol aman (tidak error).
    $rute = fn (string $nama, $param = null) => \Illuminate\Support\Facades\Route::has($nama) ? route($nama, $param) : '#';

    // ===== MODE CONTOH: hanya aktif selama controller belum mengirim $periodes =====
    if (! isset($periodes)) {
        $bisaKelola = true;
        $buatDesa = fn ($id, $nama, $kec) => (object) [
            'id_desa' => $id, 'nama_desa' => $nama,
            'kecamatan' => (object) ['nama_kecamatan' => $kec],
        ];
        $nganjuk = collect([[1, 'Payaman'], [2, 'Cangkrimar'], [3, 'Begadung'], [4, 'Sawahan'], [5, 'Gebangayu']])
            ->map(fn ($d) => $buatDesa($d[0], $d[1], 'Nganjuk'));
        $pace = collect([[6, 'Pace 1'], [7, 'Pace 2']])
            ->map(fn ($d) => $buatDesa($d[0], $d[1], 'Pace'));

        $periodes = collect([
            (object) ['id_periode' => 1, 'desa' => $nganjuk->whereIn('id_desa', [1, 2, 3])->values(),
                'tgl_buka' => \Carbon\Carbon::create(2026, 10, 1), 'tgl_tutup' => \Carbon\Carbon::create(2026, 10, 12),
                'tgl_jemput' => \Carbon\Carbon::create(2026, 10, 15), 'berat_min' => 1, 'berat_max' => 10,
                'petugas' => null, 'status' => 'Dibuka'],
            (object) ['id_periode' => 2, 'desa' => $nganjuk->whereIn('id_desa', [4, 5])->values(),
                'tgl_buka' => \Carbon\Carbon::create(2026, 10, 1), 'tgl_tutup' => \Carbon\Carbon::create(2026, 10, 12),
                'tgl_jemput' => \Carbon\Carbon::create(2026, 10, 16), 'berat_min' => 1, 'berat_max' => 10,
                'petugas' => (object) ['nama_lengkap' => 'Dika'], 'status' => 'Dibuka'],
            (object) ['id_periode' => 3, 'desa' => $pace,
                'tgl_buka' => \Carbon\Carbon::create(2026, 10, 15), 'tgl_tutup' => \Carbon\Carbon::create(2026, 11, 5),
                'tgl_jemput' => \Carbon\Carbon::create(2026, 11, 18), 'berat_min' => 1, 'berat_max' => 10,
                'petugas' => null, 'status' => 'Dijadwalkan'],
        ]);
        // [id_periode => [id_desa => jumlah warga]]
        $terdaftar = [1 => [1 => 3, 2 => 1, 3 => 1], 2 => [4 => 1, 5 => 1], 3 => [6 => 0, 7 => 0]];

        $kecamatans = collect([
            (object) ['id_kecamatan' => 1, 'nama_kecamatan' => 'Nganjuk', 'desas' => $nganjuk],
            (object) ['id_kecamatan' => 2, 'nama_kecamatan' => 'Pace', 'desas' => $pace],
        ]);
        $petugasDlh = collect([
            (object) ['id_petugas' => 1, 'nama_lengkap' => 'Dika'],
            (object) ['id_petugas' => 2, 'nama_lengkap' => 'Rofik'],
        ]);
    }
@endphp

@push('styles')
<style>
    /* Khusus tab Periode (melengkapi b3.css dan b3-pendaftaran.css) */
    .b3-toolbar{display:flex;justify-content:flex-end;margin:6px 0 16px}
    .b3-table--periode{min-width:900px}
    .b3-table--periode th:nth-child(1){width:23%}
    .b3-table--periode th:nth-child(2){width:13%}
    .b3-table--periode th:nth-child(3){width:9%}
    .b3-table--periode th:nth-child(4){width:11%}
    .b3-table--periode th:nth-child(5){width:19%}
    .b3-table--periode th:nth-child(6){width:11%}
    .b3-table--periode th:nth-child(7){width:14%}
    .b3-table--periode th:nth-child(n+2),
    .b3-table--periode td:nth-child(n+2){text-align:center}
    .b3-table--periode tbody td{height:auto;padding:9px 14px;line-height:1.3}
    .b3-sub{display:block;margin-top:2px;font-size:.68rem;color:#3a3a3a}
    .b3-pill--dibuka{background:#c8f0d2;border:1px solid #2e9d45;color:#14532d}
    .b3-pill--ditutup{background:#fbeeb0;border:1px solid #b8a64a}
    .b3-pill--selesai{background:#e0e0e0;border:1px solid #888}
    .b3-pill{min-width:84px}
</style>
@endpush

@if ($bisaKelola)
    <div class="b3-toolbar">
        <button type="button" class="b3-btn b3-btn--primer" id="b3-periode-buka">+ Buat Periode</button>
    </div>
@endif

@php
    // 1 -> "1", 10 -> "10", 2.5 -> "2,5"
    $kg = fn ($v) => rtrim(rtrim(number_format((float) $v, 1, ',', ''), '0'), ',');
@endphp

<div class="b3-tablewrap">
    <table class="b3-table b3-table--periode">
        <thead>
            <tr>
                <th>Wilayah (rute desa)</th>
                <th>Buka - tutup</th>
                <th>Tgl Jemput</th>
                <th>Kuota/orang</th>
                <th>Terdaftar</th>
                <th>Petugas DLH</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($periodes as $p)
                @php
                    $perDesa = $terdaftar[$p->id_periode] ?? [];
                    $wilayah = $p->desa->pluck('kecamatan.nama_kecamatan')->filter()->unique()
                        ->map(fn ($k) => 'Kec. ' . $k)->join(', ');
                    $format = fn ($tgl) => $tgl->locale('id')->translatedFormat('j M');
                @endphp
                <tr>
                    <td>
                        {{ $wilayah ?: '-' }}
                        <span class="b3-sub">{{ $p->desa->pluck('nama_desa')->join(', ') }}</span>
                    </td>
                    <td>{{ $format($p->tgl_buka) }} - {{ $format($p->tgl_tutup) }}</td>
                    <td>{{ $format($p->tgl_jemput) }}</td>
                    <td>{{ $kg($p->berat_min) }} - {{ $kg($p->berat_max) }} kg</td>
                    <td>
                        {{ array_sum($perDesa) }} warga
                        <span class="b3-sub">
                            {{ $p->desa->map(fn ($d) => $d->nama_desa . ' ' . ($perDesa[$d->id_desa] ?? 0))->join(', ') }}
                        </span>
                    </td>
                    <td>
                        @if ($p->petugas)
                            {{ $p->petugas->nama_lengkap }}
                        @elseif ($bisaKelola)
                            <button type="button" class="b3-tugaskan"
                                data-action="{{ $rute('admin.bank-sampah.b3.periode.tugaskan', $p->id_periode) }}"
                                data-nama="{{ $wilayah }}">Tugaskan</button>
                        @else
                            -
                        @endif
                    </td>
                    <td><span class="b3-pill b3-pill--{{ strtolower($p->status) }}">{{ $p->status }}</span></td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="b3-kosong">
                        Belum ada periode @if (request('q')) yang cocok dengan pencarian @endif.
                        @if ($bisaKelola) Klik "Buat Periode" untuk memulai. @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($bisaKelola)
    @include('admin.bank-sampah.B3.Modals.Buat-Periode')
    {{-- Dialog "Tugaskan petugas" milik tab Pendaftaran dipakai ulang di sini --}}
    @include('admin.bank-sampah.B3.Modals.Tugaskan-Petugas')

    @push('scripts')
        <script src="{{ asset('js/b3-pendaftaran.js?v=' . time()) }}"></script>
    @endpush
@endif