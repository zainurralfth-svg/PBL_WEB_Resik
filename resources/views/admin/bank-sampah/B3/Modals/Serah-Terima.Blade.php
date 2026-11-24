{{-- Modal Catat Serah Terima (tampilan saja) --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('css/b3-rekap-serah-terima.css?v=' . time()) }}">
@endpush

<div class="rs-overlay" id="rs-overlay">
    <div class="rs-modal">
        <h2>Catat Serah Terima</h2>
        @php
            $stItems = $rsItems ?? [];
            $stTotal = array_sum(array_column($stItems, 'jumlah'));
            $stNama = implode(', ', array_column($stItems, 'jenis'));
        @endphp
        <p class="rs-sub">Barang terverifikasi yang belum diserahkan: <strong>{{ $stTotal }} item</strong> ({{ $stNama ?: '-' }})</p>
        <div class="rs-field">
            <label>Tujuan</label>
            <select id="rs-tujuan"><option>TPS B3 DLH</option></select>
        </div>
        <div class="rs-field">
            <label>Nama Penerima</label>
            <input id="rs-penerima" placeholder="Nama petugas atau perusahaan">
            <div class="rs-err" id="rs-err">Nama penerima wajib diisi.</div>
        </div>
        <div class="rs-field">
            <label>Tanggal</label>
            <input type="date" id="rs-tanggal" value="2026-10-20">
        </div>
        <div class="rs-field">
            <label>Berita Acara (opsional)</label>
            <div class="rs-upload" id="rs-upload">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 16V4M7 9l5-5 5 5M5 20h14"/></svg>
                <span id="rs-upload-text">Unggah berkas</span>
            </div>
            <input type="file" id="rs-berkas" hidden>
        </div>
        <div class="rs-foot">
            <button type="button" class="rs-btn" id="rs-btnBatal">Batal</button>
            <button type="button" class="rs-btn rs-btn--hijau" id="rs-btnSimpan">Simpan</button>
        </div>
    </div>
</div>

<div class="rs-toast" id="rs-toast"></div>