<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * Satu-satunya folder upload: public/uploads (lokal) atau public_html/uploads (hosting).
     */
    protected function uploadDir(string $sub = ''): string
    {
        $base = app()->environment('production')
            ? dirname(base_path()) . '/public_html/uploads'
            : public_path('uploads');

        return $sub === '' ? $base : $base . '/' . ltrim($sub, '/');
    }

    /**
     * URL publik file upload. $path = nilai kolom DB, contoh "laporan/abc.jpg".
     */
    public static function uploadUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        if (preg_match('#^https?://#', $path)) {
            return $path;
        }
        $path = preg_replace('#^(storage|uploads)/#', '', ltrim($path, '/'));

        return asset('uploads/' . $path);
    }
}