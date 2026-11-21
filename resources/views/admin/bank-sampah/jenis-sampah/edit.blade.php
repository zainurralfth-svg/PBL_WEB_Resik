@extends('layouts.admin')

@section('title', 'Edit Jenis Sampah - RESIK')

@push('styles')
    {{-- FUNGSI: Memuat file CSS khusus untuk halaman jenis sampah --}}
    <link rel="stylesheet" href="{{ asset('css/jenis-sampah.css?v=' . time()) }}">
@endpush

@section('content')
    @php
        // ✅ Redirect jika bukan Super Admin
        if (!auth()->guard('admin')->user()->isSuperAdmin()) {
            return redirect()
                ->route('admin.bank-sampah.jenis-sampah.index')
                ->with('error', '❌ Anda tidak memiliki izin untuk mengakses halaman ini!');
        }
    @endphp

    <div class="form-container">
        <div class="form-header">
            <h2><i class="fas fa-edit"></i> Edit Jenis Sampah</h2>
        </div>

        <form action="{{ route('admin.bank-sampah.jenis-sampah.update', $jenisSampah->id_jenis_sampah) }}" method="POST"
            enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <!-- Upload Gambar -->
            <div class="form-group full-width">
                <label>Upload Foto Jenis Sampah</label>

                {{-- ✅ KOTAK UPLOAD DENGAN PREVIEW DAN TOMBOL HAPUS --}}
                <div class="image-upload" id="upload-area" style="cursor: pointer; position: relative;">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <p id="upload-text">Klik untuk upload foto</p>
                    <small>Format: JPG, PNG. Max: 10MB</small>

                    {{-- ✅ PREVIEW GAMBAR (Di dalam kotak) --}}
                    <div id="image-preview" style="margin-top: 15px; text-align: center; position: relative;">
                        @if ($jenisSampah->gambar)
                            @php
                                $gambarUrl = asset('uploads/' . ltrim(preg_replace('#^(storage|uploads)/#', '', $jenisSampah->gambar), '/'));
                                $gambarUrl = asset($disk . '/' . $jenisSampah->gambar);
                            @endphp
                            <div style="display: inline-block; position: relative;">
                                <img src="{{ $gambarUrl }}" alt="{{ $jenisSampah->jenis }}" id="current-image"
                                    style="max-width: 180px; height: auto; border-radius: 8px; cursor: pointer; border: 2px solid #e5e7eb;">

                                {{-- ✅ TOMBOL HAPUS GAMBAR (X) --}}
                                <button type="button" id="remove-image-btn"
                                    style="position: absolute; top: -8px; right: -8px; 
                       background: #dc3545; color: white; 
                       border: 2px solid white; border-radius: 50%; 
                       width: 26px; height: 26px; 
                       cursor: pointer; display: flex; 
                       align-items: center; justify-content: center;
                       font-size: 18px; line-height: 1;
                       box-shadow: 0 2px 4px rgba(0,0,0,0.2);"
                                    title="Hapus foto">
                                    &times;
                                </button>
                            </div>
                        @endif
                    </div>

                    <input type="file" id="gambar" name="gambar" accept="image/*" style="display: none;">
                    <input type="hidden" id="remove-current-image" name="remove_current_image" value="0">
                    @error('gambar')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Jenis Sampah -->
                <div class="form-group full-width">
                    <label>Jenis Sampah <span class="required">*</span></label>
                    <input type="text" name="jenis" value="{{ old('jenis', $jenisSampah->jenis) }}"
                        placeholder="Contoh: Plastik, Kertas, Logam" required>
                    @error('jenis')
                        <span class="invalid-feedback">{{ $message }}</span>
                    @enderror
                </div>

                <!-- Satuan & Harga -->
                <div class="form-row">
                    <div class="form-group">
                        <label>Satuan <span class="required">*</span></label>
                        <select name="satuan" required>
                            <option value="">Pilih Satuan</option>
                            <option value="Kg" {{ old('satuan', $jenisSampah->satuan) == 'Kg' ? 'selected' : '' }}>Kg
                                (Kilogram)</option>
                            <option value="Lt" {{ old('satuan', $jenisSampah->satuan) == 'Lt' ? 'selected' : '' }}>Lt
                                (Liter)</option>
                        </select>
                        @error('satuan')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-group">
                        <label>Harga (Rp) <span class="required">*</span></label>
                        <input type="number" name="harga" value="{{ old('harga', $jenisSampah->harga) }}"
                            placeholder="Contoh: 2000" min="0" required>
                        @error('harga')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @enderror
                    </div>
                </div>

                <!-- Tombol Aksi -->
                <div class="form-actions">
                    <a href="{{ route('admin.bank-sampah.jenis-sampah.index') }}" class="btn btn-cancel">
                        Batal
                    </a>
                    <button type="submit" class="btn btn-submit">
                        <i class="fas fa-edit"></i> Update
                    </button>
                </div>
        </form>
    </div>

    @push('scripts')
        {{-- SweetAlert2 CDN --}}
        <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

        {{-- FUNGSI: Memuat file JS eksternal yang berisi semua fungsi form handling --}}
        <script src="{{ asset('js/jenis-sampah.js?v=' . time()) }}"></script>

        {{-- Script untuk preview image saat edit --}}
        <script>
            // ✅ KLIK AREA UPLOAD ATAU GAMBAR UNTUK UPLOAD
            const uploadArea = document.getElementById('upload-area');
            const fileInput = document.getElementById('gambar');
            const currentImage = document.getElementById('current-image');
            const removeBtn = document.getElementById('remove-image-btn');
            const removeInput = document.getElementById('remove-current-image');
            const uploadText = document.getElementById('upload-text');

            // Klik area upload atau gambar yang sudah ada
            if (uploadArea) {
                uploadArea.addEventListener('click', function(e) {
                    // Jangan trigger jika klik tombol hapus
                    if (e.target.id !== 'remove-image-btn' && !e.target.closest('#remove-image-btn')) {
                        fileInput.click();
                    }
                });
            }

            // Jika gambar sudah ada, klik gambar juga bisa upload
            if (currentImage) {
                currentImage.addEventListener('click', function(e) {
                    e.stopPropagation(); // Mencegah double trigger
                    fileInput.click();
                });
            }

            // ✅ TOMBOL HAPUS GAMBAR (LANGSUNG HAPUS TANPA KONFIRMASI)
            if (removeBtn) {
                removeBtn.addEventListener('click', function(e) {
                    e.stopPropagation(); // Mencegah trigger upload

                    // Langsung hapus tanpa konfirmasi
                    currentImage.style.display = 'none';
                    removeBtn.style.display = 'none';

                    // Set flag untuk hapus dari database
                    if (removeInput) {
                        removeInput.value = '1';
                    }

                    // Reset text upload
                    if (uploadText) {
                        uploadText.textContent = 'Klik untuk upload foto';
                    }
                });
            }

            // ✅ PREVIEW SAAT UPLOAD FILE BARU
            fileInput.addEventListener('change', function(e) {
                const preview = document.getElementById('image-preview');

                if (this.files && this.files[0]) {
                    const reader = new FileReader();

                    reader.onload = function(e) {
                        // Jika ada gambar lama, ganti dengan preview baru
                        if (currentImage) {
                            currentImage.src = e.target.result;
                            currentImage.style.display = 'block';

                            // Tampilkan kembali tombol hapus jika sebelumnya disembunyikan
                            if (removeBtn) {
                                removeBtn.style.display = 'flex';
                            }
                        } else {
                            // Buat elemen gambar baru
                            const img = document.createElement('img');
                            img.src = e.target.result;
                            img.alt = 'Preview';
                            img.id = 'current-image';
                            img.style.cssText =
                                'max-width: 180px; height: auto; border-radius: 8px; display: block; cursor: pointer;';

                            // Tambahkan tombol hapus
                            const removeButton = document.createElement('button');
                            removeButton.type = 'button';
                            removeButton.id = 'remove-image-btn';
                            removeButton.innerHTML = '&times;';
                            removeButton.style.cssText =
                                'position: absolute; top: 5px; right: 5px; background: #dc3545; color: white; border: none; border-radius: 50%; width: 28px; height: 28px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 16px; line-height: 1;';

                            preview.innerHTML = '';
                            preview.appendChild(img);
                            preview.appendChild(removeButton);
                        }

                        if (uploadText) {
                            uploadText.textContent = 'Klik untuk ganti foto';
                        }
                    };

                    reader.readAsDataURL(this.files[0]);
                }
            });
        </script>

        {{-- SweetAlert untuk notifikasi --}}
        @if (session('success'))
            <script>
                Swal.fire({
                    icon: 'success',
                    title: 'Berhasil!',
                    text: '{{ session('success') }}',
                    timer: 3000,
                    showConfirmButton: false,
                    position: 'center'
                });
            </script>
        @endif
    @endpush
@endsection
