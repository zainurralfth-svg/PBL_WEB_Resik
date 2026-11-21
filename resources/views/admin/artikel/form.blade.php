@extends('layouts.admin')

{{-- FUNGSI: Menetapkan judul halaman dinamis berdasarkan mode (Edit/Tambah) --}}
@section('title', isset($artikel) ? 'Edit Artikel' : 'Tambah Artikel')
@section('page-title', isset($artikel) ? 'Edit Artikel' : 'Tambah Artikel')
@section('page-title-mobile', isset($artikel) ? 'EDIT' : 'TAMBAH')

@push('styles')
    {{-- FUNGSI: Memuat file CSS khusus untuk halaman form artikel --}}
    <link rel="stylesheet" href="{{ asset('css/artikel-form.css?v=' . time()) }}">
@endpush

@section('content')
    {{-- FUNGSI: Container utama form artikel --}}
    <div class="form-container">
        {{-- FUNGSI: Judul form yang berubah dinamis: "Edit Artikel" atau "Tambah Artikel" --}}
        <div class="form-title">{{ isset($artikel) ? 'Edit' : 'Tambah' }} Artikel</div>

        {{-- FUNGSI: Form utama dengan action dinamis berdasarkan mode dan enctype untuk upload file --}}
        <form id="artikelForm" method="POST"
            action="{{ isset($artikel) ? route('admin.artikel.update', $artikel->id_artikel) : route('admin.artikel.store') }}"
            enctype="multipart/form-data">
            @csrf
            @if (isset($artikel))
                {{-- FUNGSI: Method spoofing PUT untuk update data di Laravel --}}
                @method('PUT')
            @endif

            <div class="form-row-main">
                {{-- FUNGSI: Section upload foto artikel --}}
                <div class="upload-section">
                    <label class="upload-label">Upload Foto</label>
                    {{-- FUNGSI: Area klik untuk trigger input file --}}
                    <div class="upload-area" id="uploadArea">
                        {{-- FUNGSI: Placeholder yang tampil ketika belum ada gambar dipilih --}}
                        <div class="upload-placeholder" id="uploadPlaceholder">
                            <svg class="upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z">
                                </path>
                            </svg>
                            <div class="upload-text">Klik untuk upload foto artikel</div>
                           <div class="upload-hint">Format: JPG, JPEG, PNG, GIF. Maksimal 10MB.</div>
                        </div>
                        {{-- FUNGSI: Preview gambar yang tampil setelah file dipilih atau saat mode edit --}}
                        <div class="upload-preview {{ isset($artikel) && $artikel->foto ? 'show' : '' }}"
                           src="{{ isset($artikel) && $artikel->foto ? asset('uploads/' . ltrim(preg_replace('#^(storage|uploads)/#', '', $artikel->foto), '/')) : '' }}"
                            {{-- FUNGSI: Tombol untuk menghapus gambar yang sudah dipilih --}}
                            <button type="button" class="remove-image" onclick="removeImage()"
                                title="Hapus gambar">×</button>
                        </div>
                        {{-- FUNGSI: Input file tersembunyi yang di-trigger via klik area upload --}}
                        <input type="file" id="fotoInput" name="foto" accept="image/jpeg,image/png,image/gif"
                            style="display: none;">
                    </div>
                </div>

                {{-- FUNGSI: Section input form data artikel --}}
                <div class="form-section">
                    <div class="form-group">
                        <label class="form-label" for="judul">Judul Artikel *</label>
                        {{-- FUNGSI: Input judul dengan validasi required, maxlength, dan old() untuk retain value --}}
                        <input type="text" class="form-input" id="judul" name="judul"
                            value="{{ old('judul', $artikel->judul ?? '') }}" placeholder="Masukkan judul artikel"
                            maxlength="255" required>
                        @error('judul')
                            {{-- FUNGSI: Menampilkan error validation untuk field judul --}}
                            <span class="error-text">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="tanggal">Tanggal Publikasi *</label>
                        {{-- FUNGSI: Input datetime-local dengan format value yang disesuaikan untuk HTML5 --}}
                        <input type="datetime-local" class="form-input" id="tanggal" name="tanggal"
                            value="{{ old('tanggal', isset($artikel) && $artikel->tanggal ? $artikel->tanggal->format('Y-m-d\TH:i') : date('Y-m-d\TH:i')) }}"
                            required>
                        @error('tanggal')
                            {{-- FUNGSI: Menampilkan error validation untuk field tanggal --}}
                            <span class="error-text">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="deskripsi">Deskripsi Artikel *</label>
                        {{-- FUNGSI: Textarea untuk konten artikel dengan validasi required --}}
                        <textarea class="form-textarea" id="deskripsi" name="deskripsi" placeholder="Tulis konten artikel di sini..." required>{{ old('deskripsi', $artikel->deskripsi ?? '') }}</textarea>
                        @error('deskripsi')
                            {{-- FUNGSI: Menampilkan error validation untuk field deskripsi --}}
                            <span class="error-text">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- FUNGSI: Section tombol aksi form (Batal & Submit) --}}
            <div class="form-actions">
                <a href="{{ route('admin.artikel.index') }}" class="btn btn-batal">Batal</a>
                {{-- FUNGSI: Tombol submit dengan teks dinamis berdasarkan mode --}}
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    {{ isset($artikel) ? 'Perbarui Artikel' : 'Tambah Artikel' }}
                </button>
            </div>
        </form>
    </div>

    {{-- FUNGSI: Modal overlay untuk menampilkan error validation dari server --}}
    <div id="errorModal" class="modal-overlay">
        <div class="modal-content">
            <div class="modal-header error">
                <h3>Kesalahan!</h3>
            </div>
            <div class="modal-body">
                <p id="errorMessage">Terjadi kesalahan.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal btn-batal" onclick="hideErrorModal()">Tutup</button>
            </div>
        </div>
    </div>

    {{-- 🔗 BRIDGE: Pass CSRF token & dynamic data ke file JS eksternal --}}
    <script>
        window.ArtikelFormConfig = {
            csrfToken: "{{ csrf_token() }}",
            @if ($errors->any())
                hasErrors: true,
                errors: @json($errors->all()),
            @endif
            @if (isset($artikel) && $artikel->foto)
                existingFoto: "{{ asset('uploads/' . ltrim(preg_replace('#^(storage|uploads)/#', '', $artikel->foto), '/')) }}",
            @endif
        };
    </script>

    @push('scripts')
        {{-- FUNGSI: Memuat file JS eksternal yang berisi semua fungsi form handling --}}
        <script src="{{ asset('js/artikel-form.js?v=' . time()) }}"></script>

        {{-- FUNGSI: Auto-show error modal jika ada validation errors dari Laravel --}}
        @if ($errors->any())
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof showError === 'function' && window.ArtikelFormConfig?.errors?.[0]) {
                        showError(window.ArtikelFormConfig.errors[0]);
                    }
                });
            </script>
        @endif

        {{-- FUNGSI: Auto-show preview jika mode edit dan ada foto existing --}}
        @if (isset($artikel) && $artikel->foto)
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    if (typeof showPreview === 'function' && window.ArtikelFormConfig?.existingFoto) {
                        showPreview(window.ArtikelFormConfig.existingFoto);
                    }
                });
            </script>
        @endif

        {{-- FUNGSI: Redirect ke index dengan pesan success setelah submit berhasil --}}
        @if (session('success'))
            <script>
                window.location.href = "{{ route('admin.artikel.index') }}";
            </script>
        @endif
    @endpush
@endsection