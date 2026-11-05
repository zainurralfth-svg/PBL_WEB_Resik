{{-- Dialog / Modal Verifikasi B3 --}}
<dialog id="b3-modal-verifikasi" class="b3-modal-verifikasi" aria-labelledby="b3-verifikasi-title">
    <form method="POST" id="b3-verifikasi-form">
        @csrf
        <div class="b3-modal-verifikasi__header">
            <h2 id="b3-verifikasi-title" class="b3-modal-verifikasi__title">Verifikasi</h2>
            <p id="b3-verifikasi-subtitle" class="b3-modal-verifikasi__subtitle">
                Desa -, Petugas -. Isi jumlah sebenarnya di lapangan.
            </p>
        </div>

        <div id="b3-verifikasi-items" class="b3-modal-verifikasi__body">
            {{-- Form item dirender secara dinamis lewat b3-verifikasi.js --}}
        </div>

        <div class="b3-modal-verifikasi__actions">
            <button type="button" class="b3-btn-tidak-sesuai" id="b3-btn-tidak-sesuai">Tidak sesuai</button>
            <button type="submit" class="b3-btn-simpan-verifikasi">Simpan verifikasi</button>
        </div>
    </form>
</dialog>

