<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

// ✅ Controllers umum
use App\Http\Controllers\LandingController;

// ✅ Admin Controllers
use App\Http\Controllers\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LaporanController;
use App\Http\Controllers\Admin\ArtikelController;
use App\Http\Controllers\Admin\TpsController;
use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\PetugasController;
use App\Http\Controllers\Admin\DataPenggunaController;
use App\Http\Controllers\Admin\JenisSampahController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\SubAdminController;
use App\Http\Controllers\Admin\SubAdminDashboardController;

// ✅ BankSampah Controllers
use App\Http\Controllers\BankSampah\PenarikanController;
use App\Http\Controllers\BankSampah\PenjemputanController;
use App\Http\Controllers\BankSampah\B3PendaftaranController;
use App\Http\Controllers\BankSampah\SetorController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

// Landing Page (Public)
Route::get('/', [LandingController::class, 'index'])->name('landing');

// Route::get('/admin/dashboard', function () {
//     return auth()->guard('admin')->user();
// });

// Admin Routes - Group Utama
Route::prefix('admin')->name('admin.')->group(function () {

    // ── Guest routes (belum login) ──
    Route::middleware('guest:admin')->group(function () {
        Route::get('/login', [AdminAuthController::class, 'showLoginForm'])->name('login');
        Route::post('/login', [AdminAuthController::class, 'login'])->name('login.post');
    });

    // ── Protected routes (sudah login) ──
    Route::middleware('auth:admin', 'nocache')->group(function () {

        // Logout
        Route::post('/logout', [AdminAuthController::class, 'logout'])->name('logout');

        // FCM Token (Multi-Device)
        Route::post('/save-fcm-token', [AdminAuthController::class, 'saveFcmToken'])
            ->name('fcm.save-token');

        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

        // ── Data Pengguna ──
        Route::prefix('data-pengguna')->name('data-pengguna.')->group(function () {
            Route::get('/', [DataPenggunaController::class, 'index'])->name('index');
            Route::get('/export', [DataPenggunaController::class, 'export'])->name('export');

            // Route API desa by kecamatan
            Route::get('/desa/{kecamatan_id}', [DataPenggunaController::class, 'getDesaByKecamatan'])
                ->name('desa-by-kecamatan');

            // API untuk detail user (modal)
            Route::get('/api/{type}/{id}', [DataPenggunaController::class, 'show'])
                ->where(['type' => 'masyarakat|pns'])
                ->name('api.show');
        });

        // ── Kelola Laporan ──
        Route::prefix('laporan')->name('laporan.')->group(function () {
            Route::get('/', [LaporanController::class, 'index'])->name('index');
            Route::post('/update-status', [LaporanController::class, 'updateStatus'])->name('update-status');
        });

        // ── Kelola Artikel ──
        Route::prefix('artikel')->name('artikel.')->group(function () {
            Route::get('/', [ArtikelController::class, 'index'])->name('index');
            Route::get('/create', [ArtikelController::class, 'create'])->name('create');
            Route::post('/', [ArtikelController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [ArtikelController::class, 'edit'])->name('edit');
            Route::put('/{id}', [ArtikelController::class, 'update'])->name('update');
            Route::delete('/{id}', [ArtikelController::class, 'destroy'])->name('destroy');
        });

        // ── Kelola TPS ──
        Route::prefix('tps')->name('tps.')->group(function () {
            Route::get('/', [TpsController::class, 'index'])->name('index');
            Route::get('/create', [TpsController::class, 'create'])->name('create');
            Route::post('/', [TpsController::class, 'store'])->name('store');
            Route::get('/{id}/edit', [TpsController::class, 'edit'])->name('edit');
            Route::put('/{id}', [TpsController::class, 'update'])->name('update');
            Route::delete('/{id}', [TpsController::class, 'destroy'])->name('destroy');
        });

        // ── Kelola Akun Admin (dengan OTP) ──
        Route::prefix('akun')->name('akun.')->group(function () {
            // ✅ ROUTE INDEX (tanpa parameter)
            Route::get('/', [AccountController::class, 'index'])->name('index');

            // ✅ ROUTE SPESIFIK (WAJIB DI ATAS /{id})
            Route::get('/kecamatan', [AccountController::class, 'getKecamatan'])->name('kecamatan');
            Route::get('/get-desa', [AccountController::class, 'getDesaWithKecamatan'])->name('get-desa');
            Route::get('/get-admin-emails', [AccountController::class, 'getAdminEmails'])->name('get-emails');

            // ✅ ROUTE POST (tidak akan konflik dengan {id})
            Route::post('/send-otp', [AccountController::class, 'processSendOtp'])->name('send-otp');
            Route::post('/request-otp', [AccountController::class, 'requestOtp'])->name('request-otp');
            Route::post('/verify-otp', [AccountController::class, 'verifyOtp'])->name('verify-otp');
            Route::post('/ajax/get-password', [AccountController::class, 'getPasswordPlaceholder'])->name('ajax.get-password');
            Route::post('/ajax/get-password-raw', [AccountController::class, 'getPasswordRaw'])->name('ajax.get-password-raw');

            // ❌ ROUTE DENGAN PARAMETER {id} - WAJIB DI PALING BAWAH
            Route::get('/{id}', [AccountController::class, 'show'])->name('show');
            Route::put('/{id}', [AccountController::class, 'update'])->name('update');
        });

        // ── Kelola Petugas (tanpa OTP) ──
        Route::prefix('petugas')->name('petugas.')->group(function () {
            Route::post('/', [PetugasController::class, 'store'])->name('store');
            Route::get('/{id}', [PetugasController::class, 'show'])->name('show');
            Route::put('/{id}', [PetugasController::class, 'update'])->name('update');
            Route::delete('/{id}', [PetugasController::class, 'destroy'])->name('destroy');
        });

        // ── Kelola Sub Admin Desa (HANYA untuk Super Admin) ──
        Route::prefix('sub-admin')->name('sub-admin.')->middleware('role:super_admin')->group(function () {
            Route::get('/', [SubAdminController::class, 'index'])->name('index');
            Route::get('/create', [SubAdminController::class, 'create'])->name('create');
            Route::post('/', [SubAdminController::class, 'store'])->name('store');

            // ✅ ROUTE SPESIFIK (WAJIB DI ATAS /{id})
            Route::get('/desa/{kecamatan_id}', [SubAdminController::class, 'getDesaByKecamatan'])->name('desa-by-kecamatan');

            // ❌ ROUTE DENGAN PARAMETER {id} - WAJIB DI PALING BAWAH
            Route::get('/{id}/edit', [SubAdminController::class, 'edit'])->name('edit');
            Route::put('/{id}', [SubAdminController::class, 'update'])->name('update');
            Route::delete('/{id}', [SubAdminController::class, 'destroy'])->name('destroy');
        });

        // ── Dashboard Sub Admin Desa (Bisa diakses Sub Admin) ──
        Route::get('/sub-admin/dashboard', [SubAdminDashboardController::class, 'index'])
            ->name('sub-admin.dashboard')
            ->middleware('subadmin');

        // ✅ BANK SAMPAH ROUTES - WRAPPER GROUP (WAJIB ADA!)
        Route::prefix('bank-sampah')->name('bank-sampah.')->group(function () {

            // ── Penarikan (Withdrawal) ──
            Route::prefix('penarikan')->name('penarikan.')->group(function () {
                Route::get('/', [PenarikanController::class, 'index'])->name('index');

                // ✅ Export HARUS di atas {id} agar tidak tertangkap route {id}
                Route::get('/export', [PenarikanController::class, 'export'])->name('export');

                Route::get('/{id}', [PenarikanController::class, 'show'])
                    ->whereNumber('id')
                    ->name('show');
                Route::put('/{id}/status', [PenarikanController::class, 'updateStatus'])
                    ->whereNumber('id')
                    ->name('update-status');
            });

            // ── Jenis Sampah ──
            Route::prefix('jenis-sampah')->name('jenis-sampah.')->group(function () {
                Route::get('/', [JenisSampahController::class, 'index'])->name('index');
                Route::get('/create', [JenisSampahController::class, 'create'])->name('create');
                Route::post('/', [JenisSampahController::class, 'store'])->name('store');
                Route::get('/{id}/edit', [JenisSampahController::class, 'edit'])->name('edit');
                Route::put('/{id}', [JenisSampahController::class, 'update'])->name('update');
                Route::delete('/{id}', [JenisSampahController::class, 'destroy'])->name('destroy');
            });

            // ── Penjemputan ──
            Route::prefix('penjemputan')->name('penjemputan.')->group(function () {
                Route::get('/', [PenjemputanController::class, 'index'])->name('index');
                Route::get('/{id}/detail', [PenjemputanController::class, 'show'])->name('show');
                Route::patch('/{id}/approve', [PenjemputanController::class, 'approve'])->name('approve');
                Route::delete('/{id}/reject', [PenjemputanController::class, 'reject'])->name('reject');
                Route::post('/store', [PenjemputanController::class, 'storeFromAdmin'])->name('store');
                Route::get('/list/{adminId}', [PenjemputanController::class, 'indexByAdmin'])->name('list');
            });

            // ── B3: Penjemputan Berperiode ──
            Route::prefix('b3')->name('b3.')->group(function () {
                // Tab Pendaftaran
                Route::get('/pendaftaran', [B3PendaftaranController::class, 'index'])->name('pendaftaran.index');
                Route::post('/pendaftaran/{id}/tugaskan', [B3PendaftaranController::class, 'tugaskan'])
                    ->whereNumber('id')
                    ->name('pendaftaran.tugaskan');

                // SEMENTARA: penanda tab milik teman. HAPUS route penanda ini kalau route aslinya sudah dibuat.
                Route::view('/periode', 'admin.bank-sampah.B3.Index', ['tab' => 'periode'])->name('periode.index');
                Route::view('/verifikasi', 'admin.bank-sampah.B3.Index', ['tab' => 'verifikasi'])->name('verifikasi.index');
                Route::view('/rekap', 'admin.bank-sampah.B3.Index', ['tab' => 'rekap'])->name('rekap.index');
            });

            // ── Setor Sampah ──
            Route::prefix('setor')->name('setor.')->group(function () {
                Route::get('/', [SetorController::class, 'index'])->name('index');
                Route::get('/{id}', [SetorController::class, 'detail'])->name('detail');
                Route::post('/', [SetorController::class, 'store'])->name('store');
                Route::put('/{id}', [SetorController::class, 'update'])->name('update');
            });

            // ── Shortcut Sidebar (opsional) ──
            Route::get('/tarik', [PenarikanController::class, 'index'])->name('shortcut.tarik');
        }); // ← ✅ TUTUP group bank-sampah (SEMUA route bank sampah harus di dalam sini!)

        Route::get('/notifications/counts', [NotificationController::class, 'getCounts']);
        Route::get('/notifications/recent/penarikan', [NotificationController::class, 'recentPenarikan']);
        Route::get('/notifications/recent/laporan', [NotificationController::class, 'recentLaporan']);
        Route::get('/notifications/recent/penjemputan', [NotificationController::class, 'recentPenjemputan']);
    }); // ← Tutup middleware auth:admin

}); // ← Tutup prefix admin

// API Route untuk detail user (di luar group admin agar URL konsisten)
Route::middleware('auth:admin')->prefix('admin')->group(function () {
    Route::get('/api/users/{id}', function ($id) {
        $user = DB::table('masyarakat')
            ->where('id_masyarakat', $id)
            ->first();

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        return response()->json([
            'id_masyarakat' => $user->id_masyarakat,
            'nama' => $user->nama,
            'email' => $user->email,
            'jenis_kelamin' => $user->jenis_kelamin,
            'no_telp' => $user->no_telp,
            'pekerjaan' => $user->pekerjaan,
            'alamat' => $user->alamat,
            'saldo_bank_sampah' => $user->saldo_bank_sampah ?? 0,
            'qr_code_path' => $user->qr_code_path,
            'created_at' => $user->created_at,
        ]);
    })->name('admin.api.users.show');
});


// ── Download APK Route (Public) ──
Route::get('/download-apk', function () {
    return redirect('/downloads_apk/resik.apk');
})->name('download.apk');