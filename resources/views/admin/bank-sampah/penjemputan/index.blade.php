@extends('layouts.admin')

@section('title', 'Bank Sampah - Daftar Penjemputan')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/penjemputan.css?v=' . time()) }}">
@endpush

@section('content')

    <div class="page-container">

        {{-- ✅ HEADER WRAPPER: Judul + Filter (Bulan, Tahun, Status) + Search --}}
        <div class="header-wrapper">

            {{-- 1. Judul Halaman --}}
            <h1 class="page-title">Daftar Penjemputan</h1>

            {{-- 2. Filter Bulan, Tahun, Status --}}
            <div class="header-filters">
                <select name="bulan" class="filter-select" form="filterForm">
                    <option value="">Semua Bulan</option>
                    @for ($i = 1; $i <= 12; $i++)
                        <option value="{{ $i }}" {{ request('bulan') == $i ? 'selected' : '' }}>
                            {{ date('F', mktime(0, 0, 0, $i, 1)) }}
                        </option>
                    @endfor
                </select>

                <select name="tahun" class="filter-select" form="filterForm">
                    <option value="">Semua Tahun</option>
                    @foreach ($tahunList as $tahun)
                        <option value="{{ $tahun }}" {{ request('tahun') == $tahun ? 'selected' : '' }}>
                            {{ $tahun }}
                        </option>
                    @endforeach
                </select>

                <select name="status" class="filter-select" form="filterForm">
                    <option value="">Semua Status</option>
                    <option value="diproses" {{ request('status') == 'diproses' ? 'selected' : '' }}>Diproses</option>
                    <option value="disetujui" {{ request('status') == 'disetujui' ? 'selected' : '' }}>Disetujui</option>
                    <option value="ditolak" {{ request('status') == 'ditolak' ? 'selected' : '' }}>Ditolak</option>
                </select>

                <button type="submit" class="btn-filter" form="filterForm">
                    <i class="fas fa-filter"></i> Filter
                </button>

                <button type="button" class="btn-filter reset" id="resetButton" style="display: none;"
                    onclick="window.location.href='{{ route('admin.bank-sampah.penjemputan.index') }}'">
                    <i class="fas fa-undo"></i> Reset
                </button>
            </div>

            {{-- 3. Search Box --}}
            <div class="top-search">
                <div class="search-wrapper">
                    <i class="fas fa-search search-icon"></i>
                    <input type="text" id="liveSearchInput" class="search-input" placeholder="Cari penjemputan..."
                        value="{{ request('search') }}">
                    <button type="button" id="clearSearch"
                        style="display: none; background:none; border:none; color:#888; cursor:pointer; padding:0 5px;">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>

        {{-- ✅ FILTER SECTION: Kecamatan & Desa (BARIS KE-2) --}}
        <div class="filter-section">
            <form id="filterForm" method="GET" action="{{ route('admin.bank-sampah.penjemputan.index') }}">
                <div class="filter-group">
                    {{-- Filter Kecamatan --}}
                    <select name="kecamatan_id" id="filterKecamatan" class="filter-select">
                        <option value="">Semua Kecamatan</option>
                        @foreach ($kecamatans as $kec)
                            <option value="{{ $kec->id_kecamatan }}"
                                {{ request('kecamatan_id') == $kec->id_kecamatan ? 'selected' : '' }}>
                                {{ $kec->nama_kecamatan }}
                            </option>
                        @endforeach
                    </select>

                    {{-- Filter Desa (Cascading) --}}
                    <select name="desa_id" id="filterDesa" class="filter-select"
                        {{ !request('kecamatan_id') ? 'disabled' : '' }}>
                        <option value="">Semua Desa</option>
                        @if (request('kecamatan_id'))
                            @foreach ($desas as $desa)
                                <option value="{{ $desa->id_desa }}"
                                    {{ request('desa_id') == $desa->id_desa ? 'selected' : '' }}>
                                    {{ $desa->nama_desa }}
                                </option>
                            @endforeach
                        @endif
                    </select>

                    {{-- Tombol Filter Wilayah --}}
                    <button type="button" class="btn-filter" onclick="document.getElementById('filterForm').submit()">
                        <i class="fas fa-filter"></i> Filter
                    </button>

                    {{-- Tombol Reset Wilayah --}}
                    @if (request('kecamatan_id') || request('desa_id'))
                        <button type="button" class="btn-filter reset" onclick="resetFilterWilayah()">
                            <i class="fas fa-undo"></i> Reset
                        </button>
                    @endif
                </div>
            </form>
        </div>

        {{-- GREEN DIVIDER --}}
        <div class="green-divider"></div>

        {{-- TABLE CONTAINER --}}
        <div class="table-container">
            <table id="penjemputanTable" class="data-table">
                <thead>
                    <tr>
                        <th style="width: 5%; text-align: center;">No</th>
                        <th style="width: 10%; text-align: left;">Gambar</th>
                        <th style="width: 15%; text-align: left;">Nama Petugas</th>
                        <th style="width: 18%; text-align: center; padding-right: 40px;">Wilayah Kerja</th>
                        <th style="width: 12%; text-align: center; padding-left: 20px;">Waktu</th>
                        <th style="width: 10%; text-align: center;">Berat</th>
                        <th style="width: 10%; text-align: center; padding-right: 20px;">Status</th>
                        <th style="width: 6%; text-align: center; padding-right: 20px;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($penjemputans as $index => $item)
                        @php
                            $wilayahKerja = 'Petugas DLH';
                            if ($item->petugas) {
                                if ($item->petugas->level === 'petugas_dlh') {
                                    $wilayahKerja = 'Petugas DLH';
                                } elseif (strpos($item->petugas->level, 'bank_sampah_') === 0) {
                                    $idDesa = str_replace('bank_sampah_', '', $item->petugas->level);
                                    $desa = \App\Models\Desa::with('kecamatan')->find($idDesa);
                                    if ($desa && $desa->kecamatan) {
                                        $wilayahKerja =
                                            'Bank Sampah ' .
                                            strtoupper($desa->nama_desa) .
                                            ' (' .
                                            $desa->nama_desa .
                                            ', ' .
                                            $desa->kecamatan->nama_kecamatan .
                                            ')';
                                    }
                                }
                            }
                            $namaPetugas = $item->petugas->nama_lengkap ?? $item->nama_admin;
                        @endphp
                        <tr onclick="showDetail({{ $item->id }})">
                            <td>{{ $penjemputans->firstItem() + $index }}</td>
                            <td>
                                @if ($item->foto)
                                    @php
                                        $fotoUrl = asset('uploads/' . ltrim(preg_replace('#^(storage|uploads)/#', '', $item->foto), '/'));
                                    @endphp

                                    <img src="{{ $fotoUrl }}" alt="Foto Penjemputan"
                                        onerror="this.onerror=null;this.style.visibility='hidden'">
                                @else
                                     <span style="color: #999; font-size: 12px;">Tidak ada foto</span>
                                @endif
                            </td>
                            <td style="font-weight: 500;">{{ $namaPetugas }}</td>
                            <td>
                                <span class="badge-wilayah">
                                    {{ $wilayahKerja }}
                                </span>
                            </td>
                            <td>{{ \Carbon\Carbon::parse($item->waktu)->format('d-m-Y, H:i') }}</td>
                            <td>{{ number_format($item->berat, 2) }} Kg</td>
                            <td>
                                @php
                                    $badgeClass = match ($item->status) {
                                        'diproses' => 'status-diproses',
                                        'disetujui' => 'status-berhasil',
                                        'ditolak' => 'status-ditolak',
                                        default => 'status-diproses',
                                    };
                                @endphp
                                <span class="status-badge {{ $badgeClass }}">{{ ucfirst($item->status) }}</span>
                            </td>
                            <td onclick="event.stopPropagation()">
                                <div class="aksi-wrapper">
                                    @if ($item->status === 'diproses')
                                        <form id="form-approve-{{ $item->id }}"
                                            action="{{ route('admin.bank-sampah.penjemputan.approve', $item->id) }}"
                                            method="POST">
                                            @csrf @method('PATCH')
                                            <button type="button" class="btn-approve" title="Setujui"
                                                onclick="showConfirm('approve', {{ $item->id }})">
                                                <i class="fas fa-check"></i>
                                            </button>
                                        </form>
                                        <form id="form-reject-{{ $item->id }}"
                                            action="{{ route('admin.bank-sampah.penjemputan.reject', $item->id) }}"
                                            method="POST">
                                            @csrf @method('DELETE')
                                            <button type="button" class="btn-reject" title="Tolak"
                                                onclick="showConfirm('reject', {{ $item->id }})">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    @else
                                        <span class="aksi-selesai">✓ Selesai</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="empty-state">
                                <i class="fas fa-inbox"></i>
                                Tidak ada data penjemputan.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Pagination --}}
        @if (method_exists($penjemputans, 'links'))
            <div class="pagination-container">
                <div>
                    Menampilkan {{ $penjemputans->firstItem() ?? 0 }} - {{ $penjemputans->lastItem() ?? 0 }} dari
                    {{ $penjemputans->total() ?? 0 }} data
                </div>
                {{ $penjemputans->links() }}
            </div>
        @endif

    </div>

    {{-- Modal Detail (sama seperti sebelumnya) --}}
    <div id="detailModal" class="modal-overlay" style="display: none;">
        <div class="modal-container">
            <div class="modal-header">
                <h3>Detail Penjemputan</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="detail-content">
                    <div class="detail-image">
                        <img id="modalFoto" src="" alt="Foto Penjemputan"
                            style="width: 100%; border-radius: 10px; object-fit: cover; max-height: 280px;">
                    </div>
                    <div class="detail-form">
                        <div class="form-group">
                            <label>No</label>
                            <input type="text" id="modalNo" readonly
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; background: #f9f9f9;">
                        </div>
                        <div class="form-group">
                            <label>Nama Petugas</label>
                            <input type="text" id="modalNamaPetugas" readonly
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; background: #f9f9f9;">
                        </div>
                        <div class="form-group">
                            <label>Wilayah Kerja</label>
                            <input type="text" id="modalWilayahKerja" readonly
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; background: #f9f9f9;">
                        </div>
                        <div class="form-group">
                            <label>Waktu</label>
                            <input type="text" id="modalWaktu" readonly
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; background: #f9f9f9;">
                        </div>
                        <div class="form-group">
                            <label>Berat</label>
                            <input type="text" id="modalBerat" readonly
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; background: #f9f9f9;">
                        </div>
                        <div class="form-group">
                            <label>Lokasi</label>
                            <input type="text" id="modalLokasi" readonly
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; background: #f9f9f9; color: #2f8cea; font-weight: 600; cursor: pointer; text-decoration: underline; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"
                                onclick="if(this.value && this.value !== '-') window.open('https://www.google.com/maps/search/?api=1&query=' + encodeURIComponent(this.value), '_blank')"
                                title="Klik untuk buka di Google Maps">
                        </div>
                        <div class="form-group">
                            <label>Keterangan</label>
                            <textarea id="modalKeterangan" rows="3" readonly
                                style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; background: #f9f9f9;"></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn-tutup" onclick="closeModal()"
                    style="background: #6c757d; color: white; border: none; padding: 10px 24px; border-radius: 6px; cursor: pointer;">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Konfirmasi -->
    <div id="confirmModal" class="modal-overlay" style="display: none;">
        <div class="modal-container" style="max-width: 400px;">
            <div class="modal-header">
                <h3 id="confirmTitle">Konfirmasi</h3>
                <button class="modal-close" onclick="closeConfirmModal()">&times;</button>
            </div>
            <div class="modal-body" style="text-align: center; padding: 30px 24px;">
                <p id="confirmMessage" style="font-size: 15px; color: #555; margin-bottom: 0;"></p>
            </div>
            <div class="modal-footer" style="display: flex; gap: 12px; justify-content: center; padding: 16px 24px 24px;">
                <button class="btn-tutup" onclick="closeConfirmModal()"
                    style="width: 100%; background: #6c757d; color: white; border: none; padding: 10px; border-radius: 6px; cursor: pointer;">Batal</button>
                <button id="confirmYesBtn"
                    style="width: 100%; padding: 10px; border-radius: 6px; border: none; cursor: pointer; font-weight: 600; color: white; transition: background 0.2s;">
                    Ya, Lanjutkan
                </button>
            </div>
        </div>
    </div>

    <!-- Success Popup -->
    <div id="successPopup" class="success-popup-overlay" style="display: none;">
        <div class="success-popup-container">
            <div class="success-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="#4CAF50"
                    stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
            </div>
            <h3 class="success-title">Berhasil!</h3>
            <p class="success-message" id="successMessage">Data penjemputan telah diperbarui.</p>
        </div>
    </div>

    {{-- Bridge: Pass session data --}}
    <script>
        window.PenjemputanConfig = {
            @if (session('success'))
                successMessage: "{{ session('success') }}",
            @endif
            @if (session('error'))
                errorMessage: "{{ session('error') }}",
            @endif
        };
    </script>

    @push('scripts')
        <script src="{{ asset('js/penjemputan.js?v=' . time()) }}"></script>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                // Show reset button hanya untuk filter Bulan/Tahun/Status
                const hasFilter =
                    {{ request('bulan') || request('tahun') || request('status') ? 'true' : 'false' }};

                if (hasFilter) {
                    const resetBtn = document.getElementById('resetButton');
                    if (resetBtn) {
                        resetBtn.style.display = 'inline-flex';
                    }
                }
            });
        </script>
    @endpush
@endsection
