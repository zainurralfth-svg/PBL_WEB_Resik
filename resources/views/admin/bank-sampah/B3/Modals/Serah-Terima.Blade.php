{{-- Modal Catat Serah Terima (tampilan saja) --}}
<div class="rs-overlay" id="rs-overlay">
    <div class="rs-modal">
        <h2>Catat Serah Terima</h2>
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
            <div class="rs-upload" id="rs-upload"><span id="rs-upload-text">Unggah berkas (prototype)</span></div>
            <input type="file" id="rs-berkas" hidden>
        </div>
        <div class="rs-foot">
            <button type="button" class="rs-btn" id="rs-btnBatal">Batal</button>
            <button type="button" class="rs-btn rs-btn--hijau" id="rs-btnSimpan">Simpan</button>
        </div>
    </div>
</div>