<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    protected ?FirebaseService $firebase = null;

    public function __construct()
    {
        try {
            $this->firebase = new FirebaseService();
        } catch (\RuntimeException $e) {
            Log::warning('Firebase tidak tersedia; notifikasi push dinonaktifkan.', [
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * ✅ MULTI-DEVICE: Ambil semua FCM token admin
     * - Super Admin (id_desa = null) SELALU dapat
     * - Sub Admin Desa hanya dapat jika id_desa sesuai
     */
    private function getAdminTokens(?int $idDesa = null): Collection
    {
        $query = DB::table('admin_fcm_tokens')
            ->join('admin', 'admin_fcm_tokens.id_admin', '=', 'admin.id_admin')
            ->select('admin_fcm_tokens.id', 'admin_fcm_tokens.fcm_token');

        if ($idDesa !== null) {
            $query->where(function ($q) use ($idDesa) {
                $q->whereNull('admin.id_desa')
                    ->orWhere('admin.id_desa', $idDesa);
            });
        }

        // ✅ TAMBAHKAN distinct() DI SINI
        return $query->distinct()->get();
    }

    /**
     * Function umum untuk mengirim notifikasi ke semua admin
     */
    /**
     * ✅ MULTI-DEVICE: Kirim notifikasi ke semua device admin
     */
    private function sendToAdmins(
        string $title,
        string $body,
        array $data,
        ?int $idDesa = null
    ): void {

        if (!$this->firebase) {
            return;
        }

        $tokens = $this->getAdminTokens($idDesa);

        foreach ($tokens as $tokenRow) {
            try {
                $this->firebase->sendNotification(
                    $tokenRow->fcm_token,
                    $title,
                    $body,
                    $data
                );
            } catch (\Exception $e) {
                // ✅ Hapus token yang invalid/expired
                if (
                    strpos($e->getMessage(), 'NOT_REGISTERED') !== false ||
                    strpos($e->getMessage(), 'InvalidRegistration') !== false
                ) {
                    DB::table('admin_fcm_tokens')
                        ->where('id', $tokenRow->id)
                        ->delete();
                }
                Log::error('FCM Multi-Device Error: ' . $e->getMessage());
            }
        }
    }

    /**
     * ==========================
     * LAPORAN SAMPAH
     * ==========================
     */
    public function sendReport(
        string $nama,
        string $lokasi,
        ?int $idDesa = null,
        int $idLaporan
    ): void {

        $title = "📢 Laporan Sampah Ilegal";

        $body = "{$nama} melaporkan adanya lokasi sampah ilegal di {$lokasi}.";

        $this->sendToAdmins(

            $title,

            $body,

            [
                "type" => "report",
                "url"  => "/admin/laporan",
                "id"   => (string) $idLaporan
            ],

            $idDesa

        );
    }

    /**
     * ==========================
     * PENARIKAN
     * ==========================
     */
    public function sendWithdrawal(
        string $nama,
        string $desa,
        ?int $idDesa = null,
        int $nominal,
        int $idPenarikan
    ): void {

        $title = "💸 Pengajuan Penarikan Baru";

        $body = "{$nama} mengajukan penarikan saldo sebesar Rp " .
            number_format($nominal, 0, ',', '.') . ".";

        $this->sendToAdmins(

            $title,

            $body,

            [
                "type" => "withdrawal",
                "url"  => "/admin/bank-sampah/penarikan",
                "id"   => (string) $idPenarikan
            ],

            $idDesa

        );
    }

    public function sendPickup(
        string $nama,
        string $desa,
        string $kecamatan,
        ?int $idDesa = null,
        int $idPenjemputan
    ): void {

        $title = "🚛 Pengajuan Penjemputan";

        $body = "{$nama} dari Bank Sampah Kecamatan {$kecamatan}, Desa {$desa} mengajukan penjemputan sampah.";

        $this->sendToAdmins(

            $title,

            $body,

            [
                "type" => "pickup",
                "url"  => "/admin/bank-sampah/penjemputan",
                "id"   => (string)$idPenjemputan
            ],

            $idDesa

        );
    }

  public function sendDeposit(
    string $nama,
    ?int $idDesa = null,
    float $berat,
    float $berat_asli,
    float $total_rupiah,
    int $idTransaksi
): void {

    $title = "♻️ Setoran Sampah Baru";

    $body = "Setoran {$berat_asli} Kg dari {$nama} berhasil. Saldo bertambah Rp " . 
            number_format($total_rupiah, 0, ',', '.') . "!";

    $this->sendToAdmins(
        $title,
        $body,
        [
            "type" => "deposit",
            "url"  => "/admin/bank-sampah/setor-sampah",
            "id"   => (string)$idTransaksi
        ],
        $idDesa
    );
}

 public function sendToUser(
    ?string $token,
    string $title,
    string $body,
    array $data = [],
    ?int $userId = null,
    ?string $tipeUser = null
): void {

    \Log::info('=== SEND TO USER CALLED ===');
    \Log::info('User ID: ' . ($userId ?? 'NULL'));
    \Log::info('Tipe User: ' . ($tipeUser ?? 'NULL'));

    $notificationData = array_merge($data, [
        'icon' => 'ic_stat_logo_r_removebg_preview',
    ]);

    // ✅ SIMPAN KE DATABASE MENGGUNAKAN DB::table() (BYPASS ELOQUENT)
    if ($userId && $tipeUser) {
        try {
            \Log::info('Mencoba menyimpan ke database dengan DB::table()...');
            
            $id = \Illuminate\Support\Facades\DB::table('notifications')->insertGetId([
                'user_id' => $userId,
                'tipe_user' => $tipeUser,
                'title' => $title,
                'body' => $body,
                'type' => $data['type'] ?? 'general',
                'data' => json_encode($notificationData),
                'is_read' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            \Log::info('✅ BERHASIL! Notifikasi ID: ' . $id);
            
        } catch (\Exception $e) {
            \Log::error('❌ GAGAL simpan ke database!');
            \Log::error('Error: ' . $e->getMessage());
            \Log::error('File: ' . $e->getFile() . ' Line: ' . $e->getLine());
        }
    } else {
        \Log::warning('userId atau tipeUser kosong!');
    }

    // ✅ KIRIM FCM
    if (!$token) {
        \Log::warning('FCM Token kosong, skip FCM');
        return;
    }

    if (!$this->firebase) {
        \Log::warning('FCM dilewati karena kredensial Firebase tidak tersedia.');
        return;
    }

    try {
        \Log::info('Mengirim FCM...');
        $this->firebase->sendNotification(
            $token,
            $title,
            $body,
            $notificationData
        );
        \Log::info('✅ FCM berhasil dikirim');
    } catch (\Exception $e) {
        \Log::error('❌ Gagal kirim FCM: ' . $e->getMessage());
    }
    
    \Log::info('=== SEND TO USER END ===');
}


    // Mobile
    public function sendReportResult(
        ?string $token,
        string $status,
        ?int $userId = null,
        ?string $tipeUser = null,
        ?int $idLaporan = null
    ): void {
        // LOGGING LENGKAP
        \Log::info('=== SEND REPORT RESULT CALLED ===');
        \Log::info('Token: ' . ($token ?? 'NULL'));
        \Log::info('Status: ' . $status);
        \Log::info('ID Laporan: ' . ($idLaporan ?? 'NULL'));

        if (!$token) {
            \Log::warning('⚠️ FCM Token NULL! Notifikasi tidak dikirim.');
            return;
        }

        $title = "📢 Status Laporan";

        if ($status == "Diterima") {
            $body = "Laporan sampah Anda telah selesai diproses. Silakan buka aplikasi untuk melihat hasil penanganan laporan. Terima kasih atas partisipasi Anda dalam menjaga kebersihan lingkungan di Kota Nganjuk.";
        } elseif ($status == "Diproses") {
            $body = "Laporan sampah Anda sedang ditangani oleh petugas. Kami akan memberikan informasi setelah penanganan selesai.";
        } else {
            $body = "Maaf, laporan sampah Anda ditolak. Admin telah memberikan keterangan terkait laporan Anda. Silakan buka aplikasi untuk melihat detail balasan.";
        }

        try {
            \Log::info(' Mengirim notifikasi ke Firebase...');
            $this->sendToUser(
                $token,
                $title,
                $body,
                [
                    "type" => "report_result",
                    "id" => $idLaporan ? (string) $idLaporan : null,  // ✅ KIRIM ID DI SINI!
                ],
                $userId,
                $tipeUser
            );
            \Log::info('✅ Notifikasi BERHASIL dikirim ke FCM!');
        } catch (\Exception $e) {
            \Log::error('❌ Error kirim notifikasi: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
        }
    }


    public function sendWithdrawalResult(
        ?string $token,
        string $status,
        float $jumlah_uang,
        ?int $userId = null,
        ?string $tipeUser = null,
        ?int $idPenarikan = null  // ✅ TAMBAH PARAMETER INI!
    ): void {

        $title = "💸 Status Penarikan";

        if ($status == "berhasil") {

            $body = "Pengajuan penarikan saldo Anda telah disetujui. Pencairan dana sebesar Rp "
                . number_format($jumlah_uang, 0, ',', '.')
                . " akan segera dilakukan.";
        } elseif ($status == "ditolak") {

            $body = "Maaf, pengajuan penarikan saldo Anda ditolak. Silakan hubungi admin untuk informasi lebih lanjut.";
        } else {

            return;
        }

        $this->sendToUser(
            $token,
            $title,
            $body,
            [
                "type" => "withdrawal_result",
                "id" => $idPenarikan ? (string) $idPenarikan : null,  // ✅ KIRIM ID DI SINI!
            ],
            $userId,
            $tipeUser
        );
    }

    public function sendDepositResult(
        ?string $token,
        float $total_rupiah,
        ?int $userId = null,
        ?string $tipeUser = null,
        ?int $idTransaksi = null
    ): void {

        $this->sendToUser(
            $token,
            "♻️ Status Setoran",
            "Setoran sampah Anda telah disetujui. Saldo sebesar Rp "
                . number_format($total_rupiah, 0, ',', '.')
                . " berhasil ditambahkan ke akun Anda.",
            [
                "type" => "deposit_result",
                "id" => $idTransaksi ? (string) $idTransaksi : null,
            ],
            $userId,
            $tipeUser
        );
    }

    public function sendDepositRejected(
        ?string $token,
        ?int $userId = null,
        ?string $tipeUser = null,
        ?int $idTransaksi = null
    ): void {

        $this->sendToUser(
            $token,
            "♻️ Status Setoran",
            "Maaf, setoran sampah Anda ditolak. Silakan hubungi petugas untuk informasi lebih lanjut.",
            [
                "type" => "deposit_rejected",
                "id" => $idTransaksi ? (string) $idTransaksi : null,
            ],
            $userId,
            $tipeUser
        );
    }

    public function sendPickupResult(
        ?string $token,
        string $status,
        ?int $userId = null,
        ?string $tipeUser = null,
        ?int $idPenjemputan = null
    ): void {

        $title = "🚛 Status Penjemputan";

        if ($status == "disetujui") {
            $body = "Pengajuan penjemputan sampah Anda telah disetujui oleh admin. Penjemputan akan segera dilakukan.";
        } elseif ($status == "ditolak") {
            $body = "Maaf, pengajuan penjemputan sampah Anda ditolak. Silakan hubungi admin untuk informasi lebih lanjut.";
        } else {
            return;
        }

        $this->sendToUser(
            $token,
            $title,
            $body,
            [
                "type" => "pickup_result",
                "id" => $idPenjemputan ? (string) $idPenjemputan : null
            ],
            $userId,
            $tipeUser
        );
    }
}
