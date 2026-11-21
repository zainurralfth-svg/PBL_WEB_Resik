<?php

namespace App\Services;

use Google\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FirebaseService
{
    protected string $projectId;

    protected string $credentials;

    public function __construct()
    {
        $projectId   = config('firebase.project_id');
        $credentials = config('firebase.credentials');

        if (empty($projectId) || empty($credentials)) {
            throw new RuntimeException(
                'Konfigurasi Firebase belum lengkap. Cek FIREBASE_PROJECT_ID dan FIREBASE_CREDENTIALS di .env, '
                . 'lalu jalankan: php artisan config:clear'
            );
        }

        $this->projectId   = (string) $projectId;
        $this->credentials = base_path($credentials);

        if (!file_exists($this->credentials)) {
            throw new RuntimeException(
                "File service account Firebase tidak ditemukan: {$this->credentials}"
            );
        }
    }

    /**
     * Ambil access token OAuth2 untuk FCM v1.
     * Di-cache agar tidak request token baru setiap kirim notifikasi.
     */
    private function getAccessToken(): string
    {
        return Cache::remember('firebase_fcm_access_token', 50 * 60, function () {
            $client = new Client();
            $client->setAuthConfig($this->credentials);
            $client->addScope('https://www.googleapis.com/auth/firebase.messaging');

            $token = $client->fetchAccessTokenWithAssertion();

            if (empty($token['access_token'])) {
                Log::error('Gagal mengambil access token Firebase', $token ?? []);
                throw new RuntimeException('Gagal mengambil access token Firebase.');
            }

            return $token['access_token'];
        });
    }

    /**
     * Kirim notifikasi ke satu device token.
     *
     * @param  string  $deviceToken
     * @param  string  $title
     * @param  string  $body
     * @param  array   $data  (opsional: id, icon, image, dll)
     */
    public function sendNotification($deviceToken, $title, $body, array $data = [])
    {
        $accessToken = $this->getAccessToken();
        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        // FCM v1 mewajibkan semua value di "data" bertipe string
        $stringData = array_map(
            fn ($value) => is_scalar($value) ? (string) $value : json_encode($value),
            $data
        );

        // Notification dasar (tampil di semua platform)
        $notification = array_filter([
            'title' => $title,
            'body'  => $body,
            'image' => $data['image'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        // Konfigurasi Android (heads-up / pop-up)
        $androidNotification = array_filter([
            'channel_id'            => 'high_importance_channel',
            'notification_priority' => 'PRIORITY_MAX',
            'sound'                 => 'default',
            'visibility'            => 'PUBLIC',
            'icon'                  => $data['icon'] ?? null,
            'image'                 => $data['image'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        // Konfigurasi Web Push (browser)
        $webpushNotification = array_filter([
            'title' => $title,
            'body'  => $body,
            'icon'  => $data['icon'] ?? '/icons/Icon-192.png',
            'image' => $data['image'] ?? null,
            'tag'   => (string) ($data['id'] ?? 'resik-notif'), // per-pesan, tidak saling timpa
        ], fn ($v) => $v !== null && $v !== '');

        $payload = [
            'message' => [
                'token'        => $deviceToken,
                'notification' => $notification,
                'data'         => (object) $stringData, // (object) agar tetap {} saat kosong

                'webpush' => [
                    'headers' => [
                        'Urgency' => 'high',
                    ],
                    'notification' => $webpushNotification,
                ],

                'android' => [
                    'priority'     => 'high',
                    'notification' => $androidNotification,
                ],
            ],
        ];

        $response = Http::withToken($accessToken)->post($url, $payload);

        if ($response->failed()) {
            Log::error('FCM gagal mengirim notifikasi', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            // Token expired / tidak valid -> buang cache agar request berikutnya ambil token baru
            if ($response->status() === 401) {
                Cache::forget('firebase_fcm_access_token');
            }
        }

        return $response->json();
    }
}