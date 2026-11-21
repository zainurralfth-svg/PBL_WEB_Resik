@extends('layouts.admin')

@section('title', 'Daftar Artikel - RESIK')
@section('page-title', 'Kelola Artikel')
@section('page-title-mobile', 'ARTIKEL')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/artikel.css?v=' . time()) }}">
@endpush

@section('content')
<div class="page-container">
    
    {{-- 1. SEARCH BAR (Baris atas sendiri, pojok kanan) --}}
    <div class="search-top">
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="searchArtikel" class="search-input" placeholder="Cari berdasarkan judul..." value="{{ request('search') }}" onkeyup="filterArtikel()">
            <button type="button" id="clearSearch" class="clear-btn" style="display: none;" onclick="clearSearch()">
                <i class="fas fa-times"></i>
            </button>
        </div>
    </div>

    {{-- 2. JUDUL & TOMBOL (Sejajar di baris kedua) --}}
    <div class="content-header">
        <h2 class="page-title">Daftar Artikel</h2>
        <a href="{{ route('admin.artikel.create') }}" class="btn-add">
            <i class="fas fa-plus"></i> Tambah Artikel
        </a>
    </div>
    
    {{-- 3. GARIS HIJAU --}}
    <div class="green-divider"></div>
    
    {{-- 4. TABEL --}}
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 8%;">No</th>
                    <th style="width: 15%;">Gambar</th>
                    <th style="text-align: center;">Judul</th>
                    <th style="width: 15%;">Tanggal</th>
                    <th style="text-align: center;">Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($artikelList as $index => $artikel)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>
                        @if($artikel->foto)
                        <img src="{{ asset('uploads/' . ltrim(preg_replace('#^(storage|uploads)/#', '', $artikel->foto), '/')) }}"
                            alt="{{ $artikel->judul }}"
                            class="table-img"
                            onerror="this.onerror=null;this.style.visibility='hidden'">
                        @endif
                    </td>
                    <td>{{ Str::limit($artikel->judul, 70) }}</td>
                    <td>{{ $artikel->tanggal->format('d-m-Y') }}</td>
                    <td>
                        <div class="action-buttons">
                            <a href="{{ route('admin.artikel.edit', $artikel->id_artikel) }}" 
                               class="btn-icon btn-edit" title="Edit">
                                <img src="{{ asset('assets/icons/edit.png') }}" 
                                     alt="Edit" 
                                     style="width: 18px; height: 18px; object-fit: contain;">
                            </a>
                            <button class="btn-icon btn-delete" title="Hapus" onclick="showDeleteModal({{ $artikel->id_artikel }})">
                                <img src="{{ asset('assets/icons/delete.png') }}" 
                                     alt="Hapus" 
                                     style="width: 18px; height: 18px; object-fit: contain;">
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center py-4">
                        <div class="empty-state">
                            <i class="fas fa-newspaper"></i>
                            <p>Belum ada artikel</p>
                            <a href="{{ route('admin.artikel.create') }}" class="btn-add" style="margin-top: 10px;">
                                <i class="fas fa-plus"></i> Tambah artikel sekarang
                            </a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- CSRF Bridge --}}
<script>
    window.ArtikelConfig = { csrfToken: "{{ csrf_token() }}" };
</script>

@push('scripts')

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script src="{{ asset('js/artikel.js?v=' . time()) }}"></script>

{{-- ✅ SweetAlert untuk Session Success --}}
    @if(session('success'))
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
    
    {{-- ✅ SweetAlert untuk Validation Errors --}}
    @if($errors->any())
    <script>
        let errorHtml = '<ul style="text-align: left; margin: 10px 0; padding-left: 20px;">';
        @foreach($errors->all() as $error)
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
@endsection