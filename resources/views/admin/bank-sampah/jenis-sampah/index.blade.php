@extends('layouts.admin')

@section('title', 'Bank Sampah - Jenis & Harga Sampah')
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/jenis-sampah.css?v=' . time()) }}">
@endpush

@section('content')
    <div class="page-container">

        {{-- 1. SEARCH BAR (Baris atas sendiri, pojok kanan) --}}
        <div class="search-top">
            <div class="search-box">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="Cari jenis sampah...">
                <button type="button" id="clearSearch" class="clear-btn" style="display: none;">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>

        {{-- 2. JUDUL & TOMBOL (Sejajar di baris kedua) --}}
        <div class="content-header">
            <h2 class="page-title">Daftar Jenis & Harga Sampah</h2>
            @if (auth()->guard('admin')->user()->isSuperAdmin())
                <a href="{{ route('admin.bank-sampah.jenis-sampah.create') }}" class="btn-add">
                    <i class="fas fa-plus"></i> Tambah
                </a>
            @endif
        </div>

        {{-- 3. GARIS HIJAU --}}
        <div class="green-divider"></div>

        {{-- Tabel tetap sama di bawah --}}

        <!-- Table Container -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width: 10%;">No</th>
                        <th style="width: 20%;">Gambar</th>
                        <th style="width: 20%;">Jenis</th>
                        <th style="width: 20%;">Satuan</th>
                        <th style="width: 20%;">Harga</th>
                        <th style="text-align: center;">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($jenisSampah as $key => $item)
                        <tr>
                            <td>{{ $jenisSampah->firstItem() + $key }}</td>
                            <td>
                               @if ($item->gambar)
                                    <img src="{{ asset('uploads/' . ltrim(preg_replace('#^(storage|uploads)/#', '', $item->gambar), '/')) }}"
                                        alt="{{ $item->jenis }}" class="table-img"
                                        onerror="this.onerror=null;this.style.visibility='hidden'">
                               @else
                                    <span class="text-muted">-</span>
                               @endif
                            </td>
                            <td>{{ $item->jenis }}</td>
                            <td>{{ $item->satuan }}</td>
                            <td><strong>Rp {{ number_format($item->harga, 0, ',', '.') }}</strong></td>
                            <td>
                                @if (auth()->guard('admin')->user()->isSuperAdmin())
                                    <div class="action-buttons">
                                        <!-- ✅ Edit: Link ke halaman edit -->
                                        <a href="{{ route('admin.bank-sampah.jenis-sampah.edit', $item->id_jenis_sampah) }}"
                                            class="btn-icon btn-edit" title="Edit">
                                            <img src="{{ asset('assets/icons/edit.png') }}" alt="Edit"
                                                style="width: 1.2em; height: 1.2em; object-fit: contain; vertical-align: middle;">
                                        </a>
                                        <!-- ✅ Hapus: Tetap pakai modal konfirmasi -->
                                        <button class="btn-icon btn-delete"
                                            onclick="confirmDelete({{ $item->id_jenis_sampah }}, '{{ addslashes($item->jenis) }}')"
                                            title="Hapus">
                                            <img src="{{ asset('assets/icons/delete.png') }}" alt="Hapus"
                                                style="width: 1.2em; height: 1.2em; object-fit: contain; vertical-align: middle;">
                                        </button>
                                    </div>
                                @else
                                    <span class="text-muted">Read Only</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center py-4">
                                <div class="empty-state">
                                    <i class="fas fa-recycle"></i>
                                    <p>Belum ada data jenis sampah.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!--  Modal Confirm Delete-->
    <div id="deleteModal" class="modal-overlay">
        <div class="modal-card modal-sm">
            <div class="modal-header">
                <h3>Konfirmasi Hapus</h3>
                <button class="close-btn" onclick="closeDeleteModal()">&times;</button>
            </div>
            <div class="modal-body text-center">
                <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #f59e0b; margin-bottom: 15px;"></i>
                <p>Apakah Anda yakin ingin menghapus <strong id="deleteName"></strong>?</p>
                <p class="text-muted" style="font-size: 13px;">Data yang dihapus tidak bisa dikembalikan.</p>
            </div>
            <div class="modal-footer" style="justify-content: center; gap: 10px;">
                <button class="btn-secondary" onclick="closeDeleteModal()" style="background: #6c757d;">Batal</button>
                <form id="deleteForm" method="POST" style="display: inline;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-danger" style="background: #dc3545;">Hapus</button>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    {{-- SweetAlert2 CDN --}}
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="{{ asset('js/jenis-sampah.js?v=' . time()) }}"></script>

    {{-- SweetAlert Notifikasi (Blade syntax akan diproses di sini!) --}}
    @if (session('success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Berhasil!',
                text: '{{ session('success') }}',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                position: 'center'
            });
        </script>
    @endif

    @if (session('error'))
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Akses Ditolak!',
                text: '{{ session('error') }}',
                timer: 3000,
                timerProgressBar: true,
                showConfirmButton: false,
                position: 'center'
            });
        </script>
    @endif

    @if ($errors->any())
        <script>
            let errorHtml = '<ul style="text-align: left; margin: 10px 0; padding-left: 20px;">';
            @foreach ($errors->all() as $error)
                errorHtml += '<li>{{ $error }}</li>';
            @endforeach
            errorHtml += '</ul>';

            Swal.fire({
                icon: 'error',
                title: 'Terjadi Kesalahan!',
                html: errorHtml,
                confirmButtonColor: '#dc2626',
                position: 'center'
            });
        </script>
    @endif
@endpush
