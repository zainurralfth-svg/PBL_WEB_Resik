{{-- Modal Catat Serah Terima (tampilan saja) --}}
<div class="b3-overlay" id="b3-overlay">
    <div class="b3-modal">
        <h2>Catat Serah Terima</h2>
        <div class="b3-field">
            <label>Tujuan</label>
            <select id="b3-tujuan"><option>TPS B3 DLH</option></select>
        </div>
        <div class="b3-field">
            <label>Nama Penerima</label>
            <input id="b3-penerima" placeholder="Nama petugas atau perusahaan">
            <div class="b3-err" id="b3-err">Nama penerima wajib diisi.</div>
        </div>
        <div class="b3-field">
            <label>Tanggal</label>
            <input type="date" id="b3-tanggal" value="2026-10-20">
        </div>
        <div class="b3-field">
            <label>Berita Acara (opsional)</label>
            <div class="b3-upload" id="b3-upload"><span id="b3-upload-text">Unggah berkas (prototype)</span></div>
            <input type="file" id="b3-berkas" hidden>
        </div>
        <div class="b3-foot">
            <button class="b3-btn" id="b3-btnBatal">Batal</button>
            <button class="b3-btn primary" id="b3-btnSimpan">Simpan</button>
        </div>
    </div>
</div>