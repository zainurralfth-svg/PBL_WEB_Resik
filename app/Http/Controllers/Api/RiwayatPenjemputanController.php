<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class RiwayatPenjemputanController extends Controller
{
    /**
     * GET /api/riwayat-penjemputan-admin/{id_petugas}
     */
    public function index($idPetugas)
    {
        try {
            $riwayat = DB::table('penjemputans')
                ->where('id_petugas', $idPetugas)
                ->orderBy('waktu', 'desc')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'id_petugas' => $item->id_petugas,
                        'nama_admin' => $item->nama_admin ?? '-',
                        'waktu' => $item->waktu ?? $item->created_at,
                        'lokasi' => $item->lokasi ?? '-',
                        'berat' => (float) ($item->berat ?? 0),
                        'keterangan' => $item->keterangan ?? '',
                        'status' => $item->status ?? 'diproses',
                        'foto' => $item->foto ? $this->getUrlFoto($item->foto) : null,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $riwayat,
                'total' => $riwayat->count()
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Riwayat Penjemputan Error: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal memuat riwayat penjemputan: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getUrlFoto($path)
    {
        return self::uploadUrl($path);
    }
}