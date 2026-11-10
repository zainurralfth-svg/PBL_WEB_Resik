{{-- Tab VERIFIKASI. Dipanggil dari B3/Index.blade.php. Variabel: $verifikasis, $desas, $tabelBelumAda --}}

@if (! empty($tabelBelumAda))
    <div class="b3-alert b3-alert--err" role="status">
        Tabel B3 belum dibuat di database, jadi daftar masih kosong. Tampilan halaman sudah bisa dilihat; data akan muncul setelah tabel dibuat.
    </div>
@endif

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
                        @else
                            @php
                                $payloadItems = $row->items->map(function ($it) {
                                    return [
                                        'id' => $it->id,
                                        'jenis_b3' => $it->jenis_b3,
                                        'jumlah_didaftarkan' => $it->jumlah,
                                        'jumlah_petugas' => $it->jumlah_petugas ?? $it->jumlah,
                                        'jumlah_verifikasi' => $it->jumlah_verifikasi ?? $it->jumlah_petugas ?? $it->jumlah,
                                    ];
                                });
                            @endphp
                            <button type="button" class="b3-btn-verifikasi"
                                data-action="{{ route('admin.bank-sampah.b3.verifikasi.simpan', $row->id) }}"
                                data-action-tidak-sesuai="{{ route('admin.bank-sampah.b3.verifikasi.tidak-sesuai', $row->id) }}"
                                data-desa="{{ $row->nama_desa }}"
                                data-petugas="{{ $row->petugas_nama }}"
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

@if (isset($verifikasis) && method_exists($verifikasis, 'hasPages') && $verifikasis->hasPages())
    <div class="b3-pager">{{ $verifikasis->links() }}</div>
@endif

@include('admin.bank-sampah.B3.Modals.Verifikasi')

@push('scripts')
    <script src="{{ asset('js/b3-verifikasi.js?v=' . time()) }}"></script>
@endpush

