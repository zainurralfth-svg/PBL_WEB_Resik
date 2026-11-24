{{-- Dialog Verifikasi B3 + dialog Tandai Tidak Sesuai (isi item dirender oleh public/js/b3-verifikasi.js) --}}
<dialog id="b3-modal-verifikasi" class="b3-modal-verifikasi" aria-labelledby="b3-verifikasi-title">
    <form method="POST" id="b3-verifikasi-form">
        @csrf
        <div class="b3-modal-verifikasi__header">
            <h2 id="b3-verifikasi-title" class="b3-modal-verifikasi__title">Verifikasi</h2>
            <p id="b3-verifikasi-subtitle" class="b3-modal-verifikasi__subtitle">
                Desa -, Petugas -. Isi jumlah sebenarnya di lapangan.
            </p>
        </div>

        {{-- Foto bukti petugas: tampil hanya kalau tombol Verifikasi membawa data-foto --}}
        <div id="b3-verifikasi-foto" class="b3-foto" hidden>
            <img class="b3-foto__img" id="b3-verifikasi-foto-img" alt="Foto bukti petugas">
            <div class="b3-foto__info">
                <span id="b3-verifikasi-foto-ket">Foto bukti petugas</span><br>
                <a href="#" id="b3-verifikasi-foto-link" target="_blank" rel="noopener">Klik untuk memperbesar</a>
            </div>
        </div>

        <div id="b3-verifikasi-items" class="b3-modal-verifikasi__body">
            {{-- Form item dirender secara dinamis lewat b3-verifikasi.js --}}
        </div>

        <div id="b3-verifikasi-saldo" class="b3-saldo" hidden></div>

        <div class="b3-modal-verifikasi__body" style="margin-bottom:0">
            <div>
                <label class="b3-modal-label" for="b3-verifikasi-catatan">Catatan <small>(opsional)</small></label>
                <textarea id="b3-verifikasi-catatan" name="catatan" rows="2" maxlength="500"
                    placeholder="Kondisi barang, selisih jumlah, dll"></textarea>
            </div>
        </div>

        <div class="b3-modal-verifikasi__actions">
            <button type="button" class="b3-btn-tidak-sesuai" id="b3-btn-tidak-sesuai">Tidak sesuai</button>
            <button type="submit" class="b3-btn-simpan-verifikasi">Simpan verifikasi</button>
        </div>
    </form>
</dialog>

<dialog id="b3-modal-ts" class="b3-modal-ts" aria-labelledby="b3-ts-title">
    <form method="POST" id="b3-ts-form">
        @csrf
        <h2 id="b3-ts-title">Tandai Tidak Sesuai</h2>
        <p id="b3-ts-sub">Alasan akan tampil di aplikasi warga (detail pendaftaran).</p>

        <span class="b3-modal-label">Alasan tidak sesuai *</span>
        <div class="b3-chips" id="b3-ts-chips">
            <button type="button" class="b3-chip is-on">Ada sampah non-B3</button>
            <button type="button" class="b3-chip">Jumlah jauh berbeda</button>
            <button type="button" class="b3-chip">Kemasan rusak/bocor</button>
            <button type="button" class="b3-chip">Lainnya</button>
        </div>
        <textarea id="b3-ts-detail" rows="3" maxlength="400" placeholder="Jelaskan singkat kondisi di lapangan"></textarea>
        <input type="hidden" name="alasan" id="b3-ts-alasan">
        <p class="b3-ts-err" id="b3-ts-err">Alasan wajib diisi.</p>
        <p class="b3-ts-note">
            Status tetap <strong>Menunggu verifikasi</strong> di database dan ditampilkan sebagai
            <strong>Tidak sesuai</strong> di web &amp; mobile. Tidak ada saldo yang ditambahkan.
        </p>

        <div class="b3-modal-ts__aksi">
            <button type="button" class="b3-btn-batal" id="b3-ts-batal">Batal</button>
            <button type="submit" class="b3-btn-tidak-sesuai">Kirim Tidak Sesuai</button>
        </div>
    </form>
</dialog>