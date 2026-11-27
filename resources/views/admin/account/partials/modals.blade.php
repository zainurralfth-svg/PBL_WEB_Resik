<!-- Modal: Batas Akun -->
<div class="modal-overlay" id="limitModal" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h3>⚠️ Batas Akun Tercapai</h3>
            <button type="button" class="modal-close" onclick="closeModal('limitModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p><strong>Jumlah akun admin sudah mencapai batas maksimal (3).</strong></p>
            <p>Silakan <strong>hapus salah satu akun tambahan</strong> terlebih dahulu jika ingin menambah akun baru.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('limitModal')"
                style="width: 100%;">Mengerti</button>
        </div>
    </div>
</div>

<!-- Modal: Konfirmasi Kirim OTP -->
<div class="modal-overlay" id="otpRequestModal" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h3 id="otpModalTitle">Verifikasi Edit Akun</h3>
            <button type="button" class="modal-close" onclick="closeModal('otpRequestModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p>Kode OTP akan dikirim ke email berikut:</p>
            <div class="email-display-box" id="targetEmailDisplay">
                email@domain.com
            </div>
            <button type="button" class="btn-primary-modal" onclick="sendOTPToTarget()"
                style="width: 100%; margin-top: 10px;">
                Kirim Kode OTP Sekarang
            </button>
            <div id="otpRequestStatus" style="margin-top: 10px;"></div>
        </div>
    </div>
</div>

<!-- Modal: Verifikasi OTP -->
<div class="modal-overlay" id="otpVerifyModal" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h3 style="color: white;">Masukkan Kode OTP</h3>
            <button type="button" class="modal-close" onclick="closeModal('otpVerifyModal')">&times;</button>
        </div>
        <div class="modal-body">
            <p style="margin-bottom: 15px; text-align: center;">
                Masukkan kode 4 digit yang dikirim ke:<br>
                <span id="otpTargetEmail"
                    style="display: inline-block; background: #e8f5e9; color: #2e7d32; padding: 6px 16px; border-radius: 20px; font-weight: 600; margin: 8px 0; word-break: break-all;">email@domain.com</span>
            </p>
            <label for="otpInput"
                style="display: block; margin-bottom: 8px; font-weight: 600; color: #333; text-align: center;">Kode
                OTP</label>
            <input type="text" id="otpInput" maxlength="4" placeholder="----"
                oninput="this.value=this.value.replace(/[^0-9]/g,'')"
                style="width: 100%; padding: 16px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 28px; font-weight: 700; text-align: center; letter-spacing: 12px; color: #2d6a4f; background: #fafafa; box-sizing: border-box;">
            <div id="otpVerifyStatus" style="margin-top: 10px;"></div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-secondary" onclick="closeModal('otpVerifyModal')">Batal</button>
            <button type="button" class="btn-primary-modal" onclick="verifyOTP()">Verifikasi</button>
        </div>
    </div>
</div>

<!-- Modal: Edit Akun Admin -->
<div class="modal-overlay" id="editAdminModal" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h3 id="editAdminModalTitle">Edit Akun Admin</h3>
            <button type="button" class="modal-close" onclick="closeEditAdminModal()">&times;</button>
        </div>

        <form id="accountForm" method="POST" action="">
            @csrf
            @method('PUT')

            <div class="modal-body">
                <!-- Hidden ID Admin -->
                <input type="hidden" id="formIdAdmin" name="id_admin">

                <!-- Email Field -->
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="email"
                        style="display: block; margin-bottom: 5px; font-weight: 600; color: #333; font-size: 14px;">Email</label>
                    <input type="email" id="email" name="email" class="form-control"
                        style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box;"
                        placeholder="contoh@email.com" required>
                </div>

                <!-- Password Field -->
                <div class="form-group" style="margin-bottom: 15px;">
                    <label for="password"
                        style="display: block; margin-bottom: 5px; font-weight: 600; color: #333; font-size: 14px;">
                        Kata Sandi <span style="font-weight:400; color:#6c757d; font-size:12px;"</span>
                    </label>
                    <div style="position: relative;">
                        <input type="password" id="password" name="password" class="form-control"
                            style="width: 100%; padding: 10px 40px 10px 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px; box-sizing: border-box;"
                            placeholder="Kosongkan jika tidak ingin mengubah">
                        <button type="button" id="togglePasswordAdmin"
                            style="position: absolute; right: 10px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 0; display: flex; align-items: center;">
                            <img id="eyeIconAdmin" src="{{ asset('assets/icons/hide.png') }}" alt="Toggle"
                                style="width: 18px; height: 18px;">
                        </button>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeEditAdminModal()">Batal</button>
                <button type="submit" class="btn-primary-modal" id="btnSimpanAdmin">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>


<!-- Fungsi: Modal form untuk menambah atau mengedit data akun petugas -->
<div class="modal-overlay" id="modalPetugas">
    <div class="modal-container">
        <!-- Header -->
        <div class="modal-header">
            <h3 id="modalPetugasTitle">Tambah Akun Petugas</h3>
            <button type="button" class="modal-close" id="btnClosePetugasModal">&times;</button>
        </div>

        <!-- Form Body -->
        <form id="formPetugas" class="modal-body" novalidate>
            <input type="hidden" id="petugasId" name="id">
            @csrf

            <div class="form-group">
                <label for="namaLengkap">Nama Petugas</label>
                <input type="text" id="namaLengkap" name="nama_lengkap" placeholder="Masukkan nama petugas"
                    required>
            </div>

            <div class="form-group">
                <label for="emailPetugas">Email</label>
                <input type="email" id="emailPetugas" name="email" placeholder="contoh@email.com" required>
            </div>

            <div class="form-group">
                <label for="noTelepon">No Telpon</label>
                <input type="tel" id="noTelepon" name="no_telepon" placeholder="08xxxxxxxxxx" required>
            </div>

            {{-- WILAYAH KERJA (Petugas DLH atau Bank Sampah) --}}
            <div class="form-group">
                <label for="tipeWilayah">Tipe Wilayah Kerja</label>
                <select id="tipeWilayah" required>
                    <option value="">-- Pilih Tipe --</option>
                    <option value="petugas_dlh">Petugas DLH</option>
                    <option value="bank_sampah">Bank Sampah (Pilih Kecamatan & Desa)</option>
                </select>
            </div>

            {{-- KECAMATAN (Hidden by default) --}}
            <div class="form-group" id="kecamatanGroup" style="display: none;">
                <label for="kecamatanSelect">Kecamatan</label>
                <select id="kecamatanSelect">
                    <option value="">-- Pilih Kecamatan --</option>
                </select>
            </div>

            {{-- DESA (Hidden by default) --}}
            <div class="form-group" id="desaGroup" style="display: none;">
                <label for="desaSelect">Desa</label>
                <select id="desaSelect">
                    <option value="">-- Pilih Desa --</option>
                </select>
            </div>

            {{-- HIDDEN INPUT untuk submit level --}}
            <input type="hidden" id="levelPetugas" name="level" value="">

            <div class="form-group">
                <label for="passwordPetugas">Kata Sandi <span id="passHint"
                        style="font-weight:400; color:#6c757d; font-size:12px;"></span></label>
                <div style="position: relative;">
                    <input type="password" id="passwordPetugas" name="password"
                        style="width: 100%; padding: 12px 45px 12px 15px; border: 2px solid #e2e8f0; border-radius: 10px; font-size: 14px; box-sizing: border-box;"
                        placeholder="Masukkan Kata sandi">
                    <button type="button" id="togglePasswordPetugas"
                        style="position: absolute; right: 12px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 0; display: flex; align-items: center;">
                        <img id="eyeIconPetugas" src="{{ asset('assets/hide.png') }}" alt="Toggle"
                            style="width: 18px; height: 18px;">
                    </button>
                </div>
            </div>
        </form>

        <!-- Footer -->
        <div class="modal-footer">
            <button type="button" class="btn-secondary" id="btnBatalPetugas">Batal</button>
            <button type="submit" form="formPetugas" class="btn-primary-modal"
                id="btnSimpanPetugas">Simpan</button>
        </div>
    </div>
</div>
