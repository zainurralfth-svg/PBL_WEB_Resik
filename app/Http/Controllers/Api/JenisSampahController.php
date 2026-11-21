<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JenisSampah;
use Illuminate\Http\Request;

class JenisSampahController extends Controller
{
    /**
     * GET /api/jenis-sampah
     * List semua jenis sampah
     */
    public function index()
    {
        try {
            $jenisSampah = JenisSampah::orderBy('jenis', 'asc')->get()->map(function ($item) {
                return [
                    'id_jenis_sampah' => $item->id_jenis_sampah,
                    'jenis' => $item->jenis,
                    'satuan' => $item->satuan ?? 'kg',
                    'harga' => (float) ($item->harga ?? 0),
                    'gambar' => $item->gambar ? $this->getUrlGambar($item->gambar) : null,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $jenisSampah,
                'total' => $jenisSampah->count()
            ], 200);
        } catch (\Exception $e) {
            \Log::error('JenisSampah List Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat data jenis sampah'
            ], 500);
        }
    }

    private function getUrlGambar($path)
    {
        return self::uploadUrl($path);
    }
}