{{-- Tab VERIFIKASI. Dipanggil dari B3/Index.blade.php. Variabel: $verifikasis, $desas, $tabelBelumAda --}}

@if (! empty($tabelBelumAda))
    <div class="b3-alert b3-alert--err" role="status">
        Tabel B3 belum dibuat di database, jadi daftar masih kosong. Tampilan halaman sudah bisa dilihat; data akan muncul setelah tabel dibuat.
    </div>
@endif

{{-- Filter periode + desa --}}
@php $periodeList = $periodes ?? collect(); @endphp
<form method="GET" action="{{ route('admin.bank-sampah.b3.verifikasi.index') }}" class="b3-filter b3-filter--bar">
    @if (request('q')) <input type="hidden" name="q" value="{{ request('q') }}"> @endif
    <select name="periode_id" class="b3-select" aria-label="Filter periode" onchange="this.form.submit()">
        <option value="">Semua periode</option>
        @foreach ($periodeList as $p)
            <option value="{{ $p->id_periode }}" @selected(request('periode_id') == $p->id_periode)>
                Periode {{ isset($p->tgl_jemput) ? $p->tgl_jemput->locale('id')->translatedFormat('M Y') : '#' . $p->id_periode }}
            </option>
        @endforeach
    </select>
    <select name="desa_id" class="b3-select" aria-label="Filter desa" onchange="this.form.submit()">
        <option value="">Semua desa</option>
        @foreach (($desas ?? []) as $desa)
            <option value="{{ $desa->id_desa }}" @selected(request('desa_id') == $desa->id_desa)>{{ $desa->nama_desa }}</option>
        @endforeach
    </select>
</form>

{{-- Tabel Verifikasi B3 --}}
<div class="b3-tablewrap">
    <table class="b3-table b3-table--verifikasi">
        <thead>
            <tr>
                <th>Nama</th>
                <th>Desa</th>
                <th>Estimasi</th>
                <th>Dibawa petugas</th>
                <th>Petugas</th>
                <th>Status</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($verifikasis as $row)
                <tr>
                    <td>{{ $row->nama_pendaftar }}</td>
                    <td>{{ $row->nama_desa }}</td>
                    <td>{{ $row->items_text ?: '-' }}</td>
                    <td>{{ $row->items_dibawa_petugas_text }}</td>
                    <td>{{ $row->petugas_nama }}</td>
                    <td>
                        <span class="b3-pill b3-pill--{{ $row->status }}">{{ $row->status_label }}</span>
                    </td>
                    <td>
                        @if ($row->status === \App\Models\B3Pendaftaran::DIVERIFIKASI)
                            <span class="b3-nominal-aksi">{{ $row->total_imbalan_label }}</span>
                        @elseif ($row->status === 'dijadwalkan')
                            <span class="b3-btn-verifikasi b3-btn-verifikasi--off" aria-disabled="true">Verifikasi</span>
                        @elseif ($row->status !== 'menunggu_verifikasi')
                            -
                        @else
                            @php
                                $payloadItems = $row->items->map(function ($it) {
                                    return [
                                        'id' => $it->id,
                                        'jenis_b3' => $it->jenis_b3,
                                        'jumlah_didaftarkan' => $it->jumlah,
                                        'jumlah_petugas' => $it->jumlah_petugas ?? $it->jumlah,
                                        'jumlah_verifikasi' => $it->jumlah_verifikasi ?? $it->jumlah_petugas ?? $it->jumlah,
                                        'tarif' => ($it->harga_satuan ?? 0) > 0 ? (float) $it->harga_satuan : null,
                                    ];
                                });
                            @endphp
                            <button type="button" class="b3-btn-verifikasi"
                                data-action="{{ route('admin.bank-sampah.b3.verifikasi.simpan', $row->id) }}"
                                data-action-tidak-sesuai="{{ route('admin.bank-sampah.b3.verifikasi.tidak-sesuai', $row->id) }}"
                                data-nama="{{ $row->nama_pendaftar }}"
                                data-desa="{{ $row->nama_desa }}"
                                data-petugas="{{ $row->petugas_nama }}"
                                data-foto="{{ $row->foto_bukti ? asset('uploads/' . $row->foto_bukti) : '' }}"
                                data-foto-ket="Foto bukti petugas"
                                data-items="{{ json_encode($payloadItems) }}">
                                Verifikasi
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="b3-kosong">
                        Belum ada data penjemputan B3 untuk diverifikasi.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="b3-info b3-info--hijau">
    Tombol <strong>Verifikasi</strong> aktif setelah petugas menandai <strong>Dijemput</strong> di aplikasi
    (status <em>Menunggu verifikasi</em>). Yang bertanda <em>Tidak ada di tempat</em> tidak diverifikasi.
</div>

@if (isset($verifikasis) && method_exists($verifikasis, 'hasPages') && $verifikasis->hasPages())
    <div class="b3-pager">{{ $verifikasis->links() }}</div>
@endif

@include('admin.bank-sampah.B3.Modals.Verifikasi')

@push('scripts')
    <script src="{{ asset('js/b3-verifikasi.js?v=' . time()) }}"></script>
@endpush