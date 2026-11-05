{{-- Dialog "Buat Periode". Dipakai oleh Tabs/Periode.blade.php.
     Variabel: $kecamatans (dengan relasi desas), $petugasDlh, $rute (dari Tabs/Periode) --}}

@push('styles')
<style>
    .b3-dialog--periode{width:min(440px,94vw);padding:16px 20px 18px}
    .b3-dialog--periode h2{margin-bottom:10px;font-size:1rem}
    .b3-dialog--periode label,.b3-dialog--periode .bp-label{display:block;margin:0 0 3px;font-size:.72rem;font-weight:400;color:#222}
    .b3-dialog--periode select,
    .b3-dialog--periode input[type=date],
    .b3-dialog--periode input[type=number]{width:100%;box-sizing:border-box;padding:5px 8px;font:inherit;font-size:.78rem;color:#222;background:#f4f4f4;border:1px solid #888;border-radius:6px}
    .bp-field{margin-bottom:9px}
    .bp-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px 12px}
    .bp-chips{display:flex;flex-wrap:wrap;gap:5px}
    .bp-chips[hidden]{display:none}
    .b3-dialog--periode .bp-chip{position:relative;display:inline-block;margin:0;cursor:pointer}
    .bp-chip input{position:absolute;opacity:0;pointer-events:none}
    .bp-chip span{display:inline-block;padding:2px 11px;font-size:.72rem;color:#222;background:#fff;border:1px solid #555;border-radius:999px}
    .bp-chip input:checked + span{color:#fff;background:#2e8b57;border-color:#2e8b57}
    .bp-chip input:focus-visible + span{outline:3px solid #1b5e32;outline-offset:2px}
    .bp-pesan{margin:0 0 8px;font-size:.75rem;color:#8a1f1a}
    .b3-dialog--periode .b3-dialog__aksi{margin-top:14px}
    .b3-dialog--periode .b3-btn{padding:5px 18px;font-size:.78rem;border-radius:6px}
    .b3-btn--abu{color:#222;background:#c9c9c9;border-color:#c9c9c9}
    @media (max-width:480px){.bp-grid{grid-template-columns:1fr}}
</style>
@endpush

<dialog id="b3-periode-dialog" class="b3-dialog b3-dialog--periode" aria-labelledby="bp-judul"
    data-reopen="{{ old('_form') === 'buat-periode' ? '1' : '0' }}">
    <form method="POST" action="{{ $rute('admin.bank-sampah.b3.periode.store') }}" id="bp-form" novalidate>
        @csrf
        <input type="hidden" name="_form" value="buat-periode">
        <h2 id="bp-judul">Buat Periode</h2>

        {{-- Kecamatan menentukan desa mana yang tampil di bawahnya --}}
        <div class="bp-field">
            <label for="bp-kec">Kecamatan</label>
            <select id="bp-kec" name="id_kecamatan">
                @foreach ($kecamatans as $kec)
                    @continue($kec->desas->isEmpty())
                    <option value="{{ $kec->id_kecamatan }}" @selected(old('id_kecamatan') == $kec->id_kecamatan)>
                        {{ $kec->nama_kecamatan }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="bp-field" role="group" aria-labelledby="bp-desa-label">
            <span class="bp-label" id="bp-desa-label">Desa dalam rute (pilih beberapa)</span>
            @foreach ($kecamatans as $kec)
                @continue($kec->desas->isEmpty())
                <div class="bp-chips" data-kec="{{ $kec->id_kecamatan }}">
                    @foreach ($kec->desas as $desa)
                        <label class="bp-chip">
                            <input type="checkbox" name="id_desa[]" value="{{ $desa->id_desa }}"
                                @checked(in_array($desa->id_desa, (array) old('id_desa', [])))>
                            <span>{{ $desa->nama_desa }}</span>
                        </label>
                    @endforeach
                </div>
            @endforeach
            <p class="bp-pesan" id="bp-pesan" role="alert" hidden>Pilih minimal satu desa.</p>
        </div>

        <div class="bp-grid">
            <div class="bp-field">
                <label for="bp-buka">Buka Daftar</label>
                <input type="date" id="bp-buka" name="tgl_buka" value="{{ old('tgl_buka') }}" required>
            </div>
            <div class="bp-field">
                <label for="bp-tutup">Tutup Daftar</label>
                <input type="date" id="bp-tutup" name="tgl_tutup" value="{{ old('tgl_tutup') }}" required>
            </div>
            <div class="bp-field">
                <label for="bp-jemput">Tanggal Penjemputan</label>
                <input type="date" id="bp-jemput" name="tgl_jemput" value="{{ old('tgl_jemput') }}" required>
            </div>
            <div class="bp-field">
                <label for="bp-petugas">Petugas DLH (opsional)</label>
                <select id="bp-petugas" name="id_petugas">
                    <option value="">Belum ditugaskan</option>
                    @foreach ($petugasDlh as $petugas)
                        <option value="{{ $petugas->id_petugas }}" @selected(old('id_petugas') == $petugas->id_petugas)>
                            {{ $petugas->nama_lengkap }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="bp-field">
                <label for="bp-min">Minimal Berat (kg)</label>
                <input type="number" id="bp-min" name="berat_min" min="0" step="0.1" value="{{ old('berat_min', 1) }}" required>
            </div>
            <div class="bp-field">
                <label for="bp-max">Maksimal Per Orang (kg)</label>
                <input type="number" id="bp-max" name="berat_max" min="0" step="0.1" value="{{ old('berat_max', 10) }}" required>
            </div>
        </div>

        {{-- Ada di desain, tetapi aturan pengulangannya belum ditentukan tim.
             Sementara hanya "Tidak" yang bisa dipilih dan nilainya tidak disimpan. --}}
        <div class="bp-field">
            <label for="bp-ulangi">Ulangi Periode</label>
            <select id="bp-ulangi" name="ulangi">
                <option value="tidak" selected>Tidak</option>
                <option value="mingguan" disabled>Setiap minggu (belum tersedia)</option>
                <option value="bulanan" disabled>Setiap bulan (belum tersedia)</option>
            </select>
        </div>

        <div class="b3-dialog__aksi">
            <button type="button" class="b3-btn b3-btn--abu" id="bp-batal">Batal</button>
            <button type="submit" class="b3-btn b3-btn--primer">Simpan Periode</button>
        </div>
    </form>
</dialog>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var dialog = document.getElementById('b3-periode-dialog');
    if (!dialog) return;

    var form = document.getElementById('bp-form');
    var kec = document.getElementById('bp-kec');
    var pesan = document.getElementById('bp-pesan');
    var tombolBuka = document.getElementById('b3-periode-buka');

    // Hanya desa milik kecamatan terpilih yang tampil; pilihan kecamatan lain dibatalkan
    function tampilkanDesa() {
        dialog.querySelectorAll('.bp-chips').forEach(function (box) {
            var cocok = box.dataset.kec === kec.value;
            box.hidden = !cocok;
            if (!cocok) {
                box.querySelectorAll('input').forEach(function (i) { i.checked = false; });
            }
        });
        pesan.hidden = true;
    }
    kec.addEventListener('change', tampilkanDesa);
    tampilkanDesa();

    if (tombolBuka) tombolBuka.addEventListener('click', function () { dialog.showModal(); });
    document.getElementById('bp-batal').addEventListener('click', function () { dialog.close(); });

    form.addEventListener('submit', function (e) {
        if (!dialog.querySelector('.bp-chips:not([hidden]) input:checked')) {
            e.preventDefault();
            pesan.hidden = false;
        }
    });

    // Setelah validasi gagal di server, buka lagi dialog dengan isian sebelumnya
    if (dialog.dataset.reopen === '1') dialog.showModal();
});
</script>
@endpush