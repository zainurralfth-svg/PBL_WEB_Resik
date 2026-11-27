<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Masyarakat;
use App\Models\Pns;
use App\Models\Petugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use App\Helpers\BarcodeHelper;

class AuthController extends Controller
{
    // ✅ REGISTER
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tipe' => 'required|in:masyarakat,pns',
            'nama' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:150', 'regex:/^[A-Za-z0-9._%+\-]+@gmail\.com$/i', 'unique:masyarakat,email', 'unique:pns,email'],
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'no_telepon' => ['required', 'regex:/^08[0-9]{10,11}$/'],
            'jenis_kelamin' => 'nullable|in:Laki-laki,Perempuan',
            'tanggal_lahir' => 'nullable|date|before:today',
            'alamat' => 'nullable|string',
            // WAJIB ADA untuk SEMUA tipe user (Masyarakat & PNS)
            'id_desa' => 'required|exists:desa,id_desa',

            'id_dinas' => 'required_if:tipe,pns|nullable|exists:dinas,id_dinas',
        ], [
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.mixed' => 'Kata sandi harus mengandung huruf besar dan huruf kecil.',
            'password.numbers' => 'Kata sandi harus mengandung angka.',
            'password.symbols' => 'Kata sandi harus mengandung simbol (contoh: !@#$%&*).',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
            'email.regex' => 'Email harus menggunakan @gmail.com.',
            'email.unique' => 'Email sudah terdaftar.',
            'no_telepon.required' => 'No. telepon wajib diisi.',
            'no_telepon.regex'    => 'No. telepon harus diawali 08 dan terdiri dari 12 sampai 13 angka.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // Generate barcode ID unik
        $barcode_id = 'RK' . strtoupper(Str::random(13));
        $hashedPassword = Hash::make($request->password);
        $tipe = $request->tipe;

        if ($tipe === 'masyarakat') {
            $user = Masyarakat::create([
                'nama' => $request->nama,
                'email' => $request->email,
                'password' => $hashedPassword,
                'no_telepon' => $request->no_telepon,
                'jenis_kelamin' => $request->jenis_kelamin,
                'tanggal_lahir' => $request->tanggal_lahir,
                'alamat' => $request->alamat,
                'id_desa' => $request->id_desa,
                'barcode_id' => $barcode_id,
                'saldo' => 0.00,
            ]);

            return response()->json([
                'status' => 'success',
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'data' => [
                    'id_masyarakat' => $user->id_masyarakat,
                    'barcode_id' => $user->barcode_id,
                    'tipe' => 'masyarakat'
                ],
                'message' => 'Registrasi masyarakat berhasil'
            ], 201);
        } elseif ($tipe === 'pns') {
            // Validasi id_dinas wajib untuk PNS
            if (empty($request->id_dinas)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Dinas wajib dipilih untuk PNS'
                ], 422);
            }

            // Generate kode anggota otomatis
            $count = Pns::count() + 1;
            $kode_anggota = 'PNS-' . date('Y') . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);

            $user = Pns::create([
                'kode_anggota' => $kode_anggota,
                'nama' => $request->nama,
                'email' => $request->email,
                'password' => $hashedPassword,
                'no_telepon' => $request->no_telepon,
                'jenis_kelamin' => $request->jenis_kelamin,
                'tanggal_lahir' => $request->tanggal_lahir,
                'alamat' => $request->alamat,
                'id_dinas' => $request->id_dinas,
                'id_desa' => $request->id_desa,
                'barcode_id' => $barcode_id,
                'saldo' => 0.00,
            ]);

            return response()->json([
                'status' => 'success',
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'data' => [
                    'id_pns' => $user->id_pns,
                    'kode_anggota' => $user->kode_anggota,
                    'barcode_id' => $user->barcode_id,
                    'tipe' => 'pns'
                ],
                'message' => 'Registrasi PNS berhasil'
            ], 201);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Tipe user tidak valid'
        ], 422);
    }

    // ✅ LOGIN
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $email = trim($request->email);
        $password = $request->password;

        // 1. Cek tabel Masyarakat
        $user = Masyarakat::with('desa.kecamatan')->where('email', $email)->first();
        if ($user && Hash::check($password, $user->password)) {
            return response()->json([
                'status' => 'success',
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'data' => [
                    'tipe' => 'masyarakat',
                    'user' => [
                        'id_masyarakat' => $user->id_masyarakat,
                        'nama' => $user->nama,
                        'email' => $user->email,
                        'no_telepon' => $user->no_telepon,
                        'tanggal_lahir' => $user->tanggal_lahir,
                        'jenis_kelamin' => $user->jenis_kelamin,
                        'alamat' => $user->alamat,
                        'foto' => $user->foto ? asset('uploads/' . $user->foto) : null,
                        'barcode_id' => $user->barcode_id,
                        'id_desa' => $user->id_desa,
                        'nama_desa' => optional($user->desa)->nama_desa,
                        'nama_kecamatan' => optional(optional($user->desa)->kecamatan)->nama_kecamatan,
                    ],
                    'redirect' => '/home-user'
                ],
                'message' => 'Login berhasil'
            ]);
        }

        // 2. Cek tabel PNS (ASN)
        // TAMBAHKAN 'desa.kecamatan' DI SINI AGAR DATA BISA DIAMBIL
        $user = Pns::with(['dinas', 'desa.kecamatan'])->where('email', $email)->first();

        if ($user && Hash::check($password, $user->password)) {
            return response()->json([
                'status' => 'success',
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'data' => [
                    'tipe' => 'pns',
                    'user' => [
                        'id_pns' => $user->id_pns,
                        'nama' => $user->nama,
                        'email' => $user->email,
                        'no_telepon' => $user->no_telepon,
                        'tanggal_lahir' => $user->tanggal_lahir,
                        'jenis_kelamin' => $user->jenis_kelamin,
                        'alamat' => $user->alamat,
                        'foto' => $user->foto ? asset('uploads/' . $user->foto) : null,
                        'barcode_id' => $user->barcode_id,

                        // DATA DINAS
                        'id_dinas' => $user->id_dinas,
                        'nama_dinas' => optional($user->dinas)->nama_dinas,

                        // TAMBAHKAN DATA DESA & KECAMATAN DI SINI
                        'id_desa' => $user->id_desa,
                        'nama_desa' => optional($user->desa)->nama_desa,
                        'nama_kecamatan' => optional(optional($user->desa)->kecamatan)->nama_kecamatan,
                    ],
                    'redirect' => '/home-user'
                ],
                'message' => 'Login berhasil'
            ]);
        }

        // 3. Cek tabel Petugas (Admin)
        $user = Petugas::where('email', $request->email)->first();

        if ($user && Hash::check($password, $user->password)) {

            return response()->json([
                'status' => 'success',
                'timestamp' => now()->format('Y-m-d H:i:s'),
                'data' => [
                    'tipe' => 'petugas',
                    'user' => [
                        'id_petugas'   => $user->id_petugas,
                        'nama_lengkap' => $user->nama_lengkap,
                        'email'        => $user->email,
                        'no_telepon'   => $user->no_telepon,
                        'foto' => $user->foto ? asset('uploads/' . $user->foto) : null,
                        'level'        => $user->level,
                        // tambahan
                        'desa_id'      => $user->desa_id,
                        'nama_wilayah' => $user->nama_wilayah,
                    ],
                    'redirect' => '/home-admin'
                ],
                'message' => 'Login berhasil'
            ]);
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Email atau password salah'
        ], 401);
    }

    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $email = trim($request->email);

        // Set timezone
        date_default_timezone_set('Asia/Jakarta');
        DB::statement("SET time_zone = '+07:00'");

        // Cek email di tabel masyarakat
        $user = Masyarakat::where('email', $email)->first();

        // Kalau tidak ada di masyarakat, cek di pns
        if (!$user) {
            $user = Pns::where('email', $email)->first();
        }

        // ✅ VALIDASI EMAIL TIDAK TERDAFTAR
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email belum terdaftar'  // ← Ini yang dideteksi frontend
            ], 404);
        }

        // Generate OTP 4 digit
        $otp = str_pad(rand(1000, 9999), 4, '0', STR_PAD_LEFT);
        $otp_expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

        // Simpan OTP ke database
        if ($user instanceof Masyarakat) {
            Masyarakat::where('email', $email)->update([
                'otp' => $otp,
                'otp_expires' => $otp_expires
            ]);
        } else {
            Pns::where('email', $email)->update([
                'otp' => $otp,
                'otp_expires' => $otp_expires
            ]);
        }

        // Kirim email via Laravel Mail
        try {
            $pesan = "Kode OTP Anda: {$otp}\r\n\r\n" .
                "Gunakan kode ini untuk reset password akun RESIK App Anda.\r\n" .
                "Kode berlaku 10 menit. Jangan bagikan ke siapa pun.\r\n\r\n" .
                "--\r\n" .
                "RESIK App | Peduli Lingkungan";

            Mail::raw($pesan, function ($message) use ($email) {
                $message->to($email)
                    ->subject('Kode OTP RESIK App')
                    ->from('simpelsi2025@gmail.com', 'RESIK App')
                    ->getHeaders()->addTextHeader('X-Mailer', 'RESIK App');
            });
        } catch (\Exception $e) {
            \Log::error('Gagal kirim email: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'message' => 'Kode verifikasi telah dikirim ke email Anda.'
        ]);
    }

    // VERIFY OTP
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|string|size:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $email = trim($request->email);
        $otp = $request->otp;

        // Cek di tabel masyarakat
        $user = Masyarakat::where('email', $email)
            ->where('otp', $otp)
            ->where('otp_expires', '>', now())
            ->first();

        // Kalau tidak ada di masyarakat, cek di pns
        if (!$user) {
            $user = Pns::where('email', $email)
                ->where('otp', $otp)
                ->where('otp_expires', '>', now())
                ->first();
        }

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kode verifikasi salah atau sudah kadaluarsa'
            ], 422);
        }

        // Generate reset token untuk langkah selanjutnya
        $resetToken = bin2hex(random_bytes(32));
        $resetExpires = now()->addHour();

        // Simpan reset token
        if ($user instanceof Masyarakat) {
            Masyarakat::where('email', $email)->update([
                'reset_token' => $resetToken,
                'reset_token_expires' => $resetExpires,
            ]);
        } else {
            Pns::where('email', $email)->update([
                'reset_token' => $resetToken,
                'reset_token_expires' => $resetExpires,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'data' => [
                'token' => $resetToken,
                'email' => $email
            ],
            'message' => 'Verifikasi berhasil'
        ]);
    }
    // ✅ RESET PASSWORD
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
        ], [
            'password.min' => 'Kata sandi minimal 8 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $email = trim($request->email);
        $token = $request->token;
        $password = $request->password;

        // Cek token di tabel masyarakat
        $user = Masyarakat::where('email', $email)
            ->where('reset_token', $token)
            ->where('reset_token_expires', '>', now())
            ->first();

        // Kalau tidak ada di masyarakat, cek di pns
        if (!$user) {
            $user = Pns::where('email', $email)
                ->where('reset_token', $token)
                ->where('reset_token_expires', '>', now())
                ->first();
        }

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token tidak valid atau sudah kadaluarsa'
            ], 422);
        }

        // Update password
        $hashedPassword = Hash::make($password);

        if ($user instanceof Masyarakat) {
            Masyarakat::where('email', $email)->update([
                'password' => $hashedPassword,
                'reset_token' => null,
                'reset_token_expires' => null,
                'otp' => null,
                'otp_expires' => null,
            ]);
        } else {
            Pns::where('email', $email)->update([
                'password' => $hashedPassword,
                'reset_token' => null,
                'reset_token_expires' => null,
                'otp' => null,
                'otp_expires' => null,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'message' => 'Password berhasil direset'
        ]);
    }

    public function getSaldo(Request $request)
    {
        \Log::info('=== GET SALDO DIPANGGIL ===');
        \Log::info('Query: ' . json_encode($request->query()));

        $userId = $request->query('user_id');
        $tipe = $request->query('tipe');

        if (!$userId || !$tipe) {
            \Log::error('Parameter tidak lengkap');
            return response()->json([
                'status' => 'error',
                'message' => 'Parameter user_id dan tipe wajib diisi'
            ], 422);
        }

        try {
            $user = null;
            if ($tipe === 'masyarakat') {
                $user = \App\Models\Masyarakat::find($userId);
            } elseif ($tipe === 'pns') {
                $user = \App\Models\Pns::find($userId);
            }

            if (!$user) {
                \Log::error("User $userId tidak ditemukan");
                return response()->json([
                    'status' => 'error',
                    'message' => 'User tidak ditemukan'
                ], 404);
            }

            // ✅ FIX: Hitung total_setoran HANYA yang sudah dikonfirmasi
            $totalSetoran = \App\Models\TransaksiSetor::where(function ($query) use ($tipe, $userId) {
                if ($tipe === 'masyarakat') {
                    $query->where('id_masyarakat', $userId);
                } else {
                    $query->where('id_pns', $userId);
                }
            })
                ->whereNotNull('tanggal_koreksi')      // ✅ Hanya yang sudah dikonfirmasi
                ->where('status_transaksi', '!=', 'dibatalkan')  // ✅ Kecuali yang dibatalkan
                ->sum('berat');

            // ✅ RETURN JSON
            $response = [
                'status' => 'success',
                'data' => [
                    'saldo' => (float) ($user->saldo ?? 0),
                    'total_setoran' => (float) ($totalSetoran ?? 0),
                ]
            ];

            \Log::info('Response: ' . json_encode($response));
            return response()->json($response, 200);
        } catch (\Exception $e) {
            \Log::error('ERROR getSaldo: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());

            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }

    public function saveFcmToken(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'tipe' => 'required|in:masyarakat,pns,petugas',
            'fcm_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        switch ($request->tipe) {

            case 'masyarakat':

                $user = Masyarakat::find($request->user_id);

                break;

            case 'pns':

                $user = Pns::find($request->user_id);

                break;

            case 'petugas':

                $user = Petugas::find($request->user_id);

                break;

            default:

                $user = null;
        }

        if (!$user) {

            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        $user->fcm_token = $request->fcm_token;
        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'FCM Token berhasil disimpan'
        ]);
    }

    public function sendEmailOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'tipe' => 'required|in:masyarakat,pns',
            'email' => ['nullable', 'email', 'regex:/^[A-Za-z0-9._%+\-]+@gmail\.com$/i'],
        ], [
            'email.regex' => 'Email harus menggunakan @gmail.com.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        if ($request->tipe == 'masyarakat') {
            $user = Masyarakat::find($request->user_id);
        } else {
            $user = Pns::find($request->user_id);
        }

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        // ✅ LOGIC: Tentukan email tujuan OTP
        if ($request->has('email') && !empty($request->email)) {
            // CASE 1: UBAH EMAIL - OTP dikirim ke EMAIL BARU
            $targetEmail = $request->email;

            // Validasi email baru harus berbeda
            if ($user->email == $targetEmail) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Email baru harus berbeda'
                ], 422);
            }

            // Cek email baru sudah dipakai atau belum
            $cek = Masyarakat::where('email', $targetEmail)->exists()
                || Pns::where('email', $targetEmail)->exists();

            if ($cek) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Email sudah digunakan'
                ], 422);
            }

            $subject = 'Kode Verifikasi Ubah Email';
            $tujuan = 'mengganti email akun Anda';
        } else {
            // CASE 2: UBAH PASSWORD - OTP dikirim ke EMAIL LAMA (yang sudah terdaftar)
            $targetEmail = $user->email;
            $subject = 'Kode Verifikasi Ubah Password';
            $tujuan = 'mengubah password akun Anda';
        }

        // Generate & Simpan OTP
        $otp = random_int(1000, 9999);
        $user->otp = $otp;
        $user->otp_expires = now()->addMinutes(10);
        $user->save();

        // KIRIM EMAIL - FORMAT COMPACT
        try {
            $pesan = "Kode OTP Anda: {$otp}\r\n\r\n" .
                "Gunakan kode ini untuk {$tujuan}.\r\n" .
                "Kode berlaku 10 menit. Jangan bagikan ke siapa pun.\r\n\r\n" .
                "--\r\n" .
                "RESIK App | Peduli Lingkungan";

            Mail::raw($pesan, function ($message) use ($targetEmail, $subject) {
                $message->to($targetEmail)
                    ->subject($subject)
                    ->from('simpelsi2025@gmail.com', 'RESIK App')
                    ->getHeaders()->addTextHeader('X-Mailer', 'RESIK App');
            });
        } catch (\Exception $e) {
            \Log::error('Gagal kirim email OTP: ' . $e->getMessage());
        }

        return response()->json([
            'status' => 'success',
            'message' => 'OTP berhasil dikirim ke ' . $targetEmail
        ]);
    }

    public function verifyEmailOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'tipe' => 'required|in:masyarakat,pns',
            'otp' => 'required|string|size:4',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        if ($request->tipe == 'masyarakat') {
            $user = Masyarakat::find($request->user_id);
        } else {
            $user = Pns::find($request->user_id);
        }

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan'
            ], 404);
        }

        if ($user->otp != $request->otp) {
            return response()->json([
                'status' => 'error',
                'message' => 'OTP salah'
            ], 422);
        }

        if (now()->gt($user->otp_expires)) {
            return response()->json([
                'status' => 'error',
                'message' => 'OTP sudah kadaluarsa'
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'OTP valid'
        ]);
    }

    public function updateEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'tipe' => 'required|in:masyarakat,pns',
            'email' => ['required', 'email', 'regex:/^[A-Za-z0-9._%+\-]+@gmail\.com$/i'],
            'otp' => 'required|string|size:4',
        ], [
            'email.regex' => 'Email harus menggunakan @gmail.com.',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        // cek email sudah dipakai atau belum
        $emailExist = Masyarakat::where('email', $request->email)->exists()
            || Pns::where('email', $request->email)->exists();

        if ($emailExist) {
            return response()->json([
                'status' => 'error',
                'message' => 'Email sudah digunakan.'
            ], 422);
        }

        if ($request->tipe == 'masyarakat') {

            $user = Masyarakat::find($request->user_id);
        } else {

            $user = Pns::find($request->user_id);
        }

        if (!$user) {

            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan.'
            ], 404);
        }

        if ($user->otp != $request->otp) {

            return response()->json([
                'status' => 'error',
                'message' => 'OTP salah.'
            ], 422);
        }

        if (now()->gt($user->otp_expires)) {

            return response()->json([
                'status' => 'error',
                'message' => 'OTP sudah kadaluarsa.'
            ], 422);
        }

        $user->email = $request->email;

        // hapus OTP setelah berhasil
        $user->otp = null;
        $user->otp_expires = null;

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Email berhasil diperbarui.',
            'data' => [
                'email' => $user->email
            ]
        ]);
    }

    public function updatePassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'tipe' => 'required|in:masyarakat,pns',
            'password_baru' => ['required', 'confirmed', Password::min(8)->mixedCase()->numbers()->symbols()],
            'otp' => 'required|string|size:4',
        ], [
            'password_baru.min' => 'Kata sandi minimal 8 karakter.',
            'password_baru.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        if ($request->tipe == 'masyarakat') {
            $user = Masyarakat::find($request->user_id);
        } else {
            $user = Pns::find($request->user_id);
        }

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak ditemukan.'
            ], 404);
        }

        // ✅ VERIFIKASI OTP (bukan password lama)
        if ($user->otp != $request->otp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kode OTP salah.'
            ], 422);
        }

        if (now()->gt($user->otp_expires)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Kode OTP sudah kadaluarsa.'
            ], 422);
        }

        // ✅ UPDATE PASSWORD
        $user->password = Hash::make($request->password_baru);

        // Hapus OTP setelah berhasil
        $user->otp = null;
        $user->otp_expires = null;

        $user->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Password berhasil diperbarui.'
        ]);
    }

    // ✅ LOGOUT (untuk Sanctum)
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logout berhasil'
        ]);
    }

    // ✅ TOTAL SETORAN USER
    public function totalSetoran(Request $request)
    {
        $user = $request->user();

        if ($user instanceof \App\Models\Masyarakat) {
            $tipe = 'masyarakat';
            $userId = $user->id_masyarakat;
        } elseif ($user instanceof \App\Models\Pns) {
            $tipe = 'pns';
            $userId = $user->id_pns;
        } else {
            return response()->json([
                'status' => 'error',
                'message' => 'User tidak valid'
            ], 400);
        }

        $totalSetoran = \App\Models\TransaksiSetor::where(function ($query) use ($tipe, $userId) {
            if ($tipe === 'masyarakat') {
                $query->where('id_masyarakat', $userId);
            } else {
                $query->where('id_pns', $userId);
            }
        })
            ->whereNotNull('tanggal_koreksi')
            ->where('status_transaksi', '!=', 'dibatalkan')
            ->sum('berat');

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_setoran' => (float) ($totalSetoran ?? 0),
                'saldo' => (float) ($user->saldo ?? 0),
            ]
        ]);
    }
}
