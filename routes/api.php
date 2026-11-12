<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DesaController;
use App\Http\Controllers\Api\KecamatanController;
use App\Http\Controllers\Api\DinasController;
use App\Http\Controllers\Api\WilayahController;
use App\Http\Controllers\Api\ArtikelController;
use App\Http\Controllers\Api\PenarikanController;
use App\Http\Controllers\Api\LaporanController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\TpsController;
use App\Http\Controllers\Api\JenisSampahController;
use App\Http\Controllers\Api\SetorController;
use App\Http\Controllers\Api\PenjemputanController;
use App\Http\Controllers\Api\RiwayatSetorAdminController;
use App\Http\Controllers\Api\RiwayatPenjemputanController;
use App\Http\Controllers\Api\KonfirmasiSetorController;
use App\Http\Controllers\Api\B3WargaController;


// ==================== 🔓 PUBLIC ROUTES (Tanpa Login) ====================

// Auth - Register & Login
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::post('/save-fcm-token', [AuthController::class, 'saveFcmToken']);

// Notifikasi User
Route::get('/notifications', [App\Http\Controllers\Api\NotificationController::class, 'index']);
Route::get('/notifications/unread-count', [App\Http\Controllers\Api\NotificationController::class, 'unreadCount']);
Route::put('/notifications/{id}/read', [App\Http\Controllers\Api\NotificationController::class, 'markAsRead']);
Route::put('/notifications/mark-all-read', [App\Http\Controllers\Api\NotificationController::class, 'markAllAsRead']);

// B3: periode yang dibuat admin di web (hanya baca, untuk layar Sampah B3 warga)
Route::get('/b3/periode-aktif', [B3WargaController::class, 'periodeAktif']);

Route::get('/kecamatan', [KecamatanController::class, 'index']);
Route::get('/desa', [DesaController::class, 'index']); // Support ?kecamatan_id= filter

// Auth - Forgot Password Flow
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

// Profile routes
Route::post('/profile', [ProfileController::class, 'update']);
Route::post('/send-email-otp', [AuthController::class, 'sendEmailOtp']);
Route::post('/verify-email-otp', [AuthController::class, 'verifyEmailOtp']);
Route::put('/update-email', [AuthController::class, 'updateEmail']);
Route::put('/update-password', [AuthController::class, 'updatePassword']);

// Data Publik
Route::get('/dinas', [DinasController::class, 'index']);
Route::get('/artikel', [ArtikelController::class, 'index']);
Route::get('/jenis-sampah', [JenisSampahController::class, 'index']);

Route::post('/cari-pengguna', [SetorController::class, 'cariPengguna']);

Route::post('/transaksi-setor', [App\Http\Controllers\Api\SetorController::class, 'store']);
Route::get('/riwayat-setor', [SetorController::class, 'riwayatSetor']);
Route::get('/transaksi-setor/{id}', [App\Http\Controllers\Api\SetorController::class, 'show']); 

// Penjemputan
Route::post('/penjemputan/store', [PenjemputanController::class, 'store']);
Route::get('/riwayat-penjemputan/{admin_id}', [PenjemputanController::class, 'index']);

// ==================== KONFIRMASI SETOR ADMIN ====================
Route::get('/setor-need-confirmation/{id_petugas}', [KonfirmasiSetorController::class, 'getNeedConfirmation']);
Route::get('/jenis-sampah-list', [KonfirmasiSetorController::class, 'getJenisSampah']);
Route::put('/konfirmasi-setor/{id_transaksi}', [KonfirmasiSetorController::class, 'confirm']);
Route::delete('/tolak-setor/{id_transaksi}', [KonfirmasiSetorController::class, 'reject']);
Route::get('/setor-statistics/{id_petugas}', [KonfirmasiSetorController::class, 'getStatistics']);
Route::get('/setor-history/{id_petugas}', [KonfirmasiSetorController::class, 'getHistory']);

// ==================== RIWAYAT ADMIN (TERPISAH) ====================
Route::get('/riwayat-setor-admin/{id_petugas}', [RiwayatSetorAdminController::class, 'index']);
Route::get('/riwayat-penjemputan-admin/{id_petugas}', [RiwayatPenjemputanController::class, 'index']);

// Wilayah (Kecamatan & Desa)
Route::get('/kecamatans', [WilayahController::class, 'kecamatans']);
Route::get('/desas', [WilayahController::class, 'desas']);



// ==================== 🔐 PROTECTED ROUTES (Butuh Login - Sanctum) ====================

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user/total-setoran', [AuthController::class, 'totalSetoran']); // GET: Total setoran
});

// 📝 Laporan Sampah Ilegal
Route::post('/laporan', [LaporanController::class, 'store']);
Route::get('/laporan', [LaporanController::class, 'index']);
Route::get('/laporan/{id}', [LaporanController::class, 'show']);

Route::get('/get-saldo', [AuthController::class, 'getSaldo']);

// 💰 Penarikan Dana (Bank Sampah)
Route::post('/penarikan', [PenarikanController::class, 'store']);
Route::get('/penarikan', [PenarikanController::class, 'index']);
Route::get('/penarikan/{id}', [PenarikanController::class, 'show']); 

Route::get('/tps', [TpsController::class, 'index']);           // List semua TPS
Route::get('/tps/{id}', [TpsController::class, 'show']);       // Detail TPS by ID