{{-- Tab PENDAFTARAN. Dipanggil dari B3/Index.blade.php. Variabel: $pendaftarans, $desas, $petugasDlh, $tabelBelumAda --}}

@if (! empty($tabelBelumAda))
    <div class="b3-alert b3-alert--err" role="status">
        Tabel B3 belum dibuat di database, jadi daftar masih kosong. Tampilan halaman sudah bisa dilihat; data akan muncul setelah tabel dibuat.
    </div>
@endif

{{-- Filter desa --}}
<form method="GET" action="{{ route('admin.bank-sampah.b3.pendaftaran.index') }}" class="b3-filter">
    @if (request('q')) <input type="hidden" name="q" value="{{ request('q') }}"> @endif
    <label for="b3-desa" style="position:absolute;left:-9999px">Filter desa</label>
    <select id="b3-desa" name="desa_id" class="b3-select" onchange="this.form.submit()">
        <option value="">desa</option>
        @foreach ($desas as $desa)
            <option value="{{ $desa->id_desa }}" @selected(request('desa_id') == $desa->id_desa)>{{ $desa->nama_desa }}</option>
        @endforeach
    </select>
</form>

{{-- Tabel --}}
<div class="b3-tablewrap">
    <table class="b3-table">
        <thead>
            <tr>
                <th>Nama</th>
                <th>Desa</th>
                <th>B3 Didaftarkan</th>
                <th>Est. berat</th>
                <th>Status</th>
                <th>Petugas DLH</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($pendaftarans as $row)
                <tr>
                    <td>{{ $row->nama_pendaftar }}</td>
                    <td>{{ $row->desa->nama_desa ?? '-' }}</td>
                    <td>{{ $row->items_text ?: '-' }}</td>
                    <td>{{ $row->est_berat_label }}</td>
                    <td><span class="b3-pill b3-pill--{{ $row->status }}">{{ $row->status_label }}</span></td>
                    <td>
                        @if ($row->status === 'terdaftar')
                            <button type="button" class="b3-tugaskan"
                                data-action="{{ route('admin.bank-sampah.b3.pendaftaran.tugaskan', $row->id) }}"
                                data-nama="{{ $row->nama_pendaftar }}">Tugaskan</button>
                        @else
                            {{ $row->petugas->nama_lengkap ?? '-' }}
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="b3-kosong">
                        Belum ada pendaftaran B3 @if (request('desa_id') || request('q')) dengan filter ini @endif.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

@if ($pendaftarans->hasPages())
    <div class="b3-pager">{{ $pendaftarans->links() }}</div>
@endif

@include('admin.bank-sampah.B3.Modals.Tugaskan-Petugas')

@push('scripts')
    <script src="{{ asset('js/b3-pendaftaran.js?v=' . time()) }}"></script>
@endpush