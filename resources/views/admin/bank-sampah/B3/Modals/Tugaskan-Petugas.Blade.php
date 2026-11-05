{{-- Dialog "Tugaskan petugas". Dipakai oleh Tabs/Pendaftaran.blade.php. Variabel: $petugasDlh --}}
<dialog id="b3-dialog" class="b3-dialog" aria-labelledby="b3-dialog-judul">
    <form method="POST" id="b3-dialog-form">
        @csrf
        <h2 id="b3-dialog-judul">Tugaskan petugas</h2>
        <p>Penjemputan B3 untuk <strong id="b3-dialog-nama"></strong></p>

        <label for="b3-petugas">Petugas DLH</label>
        <select id="b3-petugas" name="id_petugas" required>
            <option value="" disabled selected>Pilih petugas</option>
            @foreach ($petugasDlh as $petugas)
                <option value="{{ $petugas->id_petugas }}">{{ $petugas->nama_lengkap }}</option>
            @endforeach
        </select>

        <div class="b3-dialog__aksi">
            <button type="button" class="b3-btn b3-btn--sekunder" id="b3-dialog-batal">Batal</button>
            <button type="submit" class="b3-btn b3-btn--primer">Tugaskan</button>
        </div>
    </form>
</dialog>