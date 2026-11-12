<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\B3Periode;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * API B3 untuk warga (aplikasi mobile).
 * Bentuk JSON mengikuti kontrak di repo mobile: lib/models/b3_model.dart
 */
class B3WargaController extends Controller
{
    /**
     * GET /api/b3/periode-aktif?id_desa=..
     *
     * Periode yang dibuat admin di web (tab Periode) untuk desa warga:
     * yang sedang dibuka, atau yang akan dibuka paling dekat.
     * Periode yang pendaftarannya sudah ditutup atau sudah selesai tidak dikirim.
     *
     * data = null berarti belum ada periode -> mobile menampilkan "Belum ada periode B3".
     */
    public function periodeAktif(Request $request)
    {
        $idDesa = (int) $request->query('id_desa');

        if ($idDesa <= 0) {
            return $this->kirim(null);
        }

        $periode = B3Periode::with('desa.kecamatan')
            ->whereNull('selesai_at')
            ->whereDate('tgl_tutup', '>=', Carbon::today())
            ->whereHas('desa', fn ($d) => $d->where('desa.id_desa', $idDesa))
            ->orderBy('tgl_buka')
            ->first();

        if (! $periode) {
            return $this->kirim(null);
        }

        return $this->kirim([
            'id_periode' => $periode->id_periode,
            'kecamatan'  => $periode->desa->pluck('kecamatan.nama_kecamatan')
                ->filter()->unique()->values()->join(', '),
            'desa'       => $periode->desa->pluck('nama_desa')->values(),
            'tgl_buka'   => $periode->tgl_buka->toDateString(),
            'tgl_tutup'  => $periode->tgl_tutup->toDateString(),
            'tgl_jemput' => $periode->tgl_jemput->toDateString(),
            'berat_min'  => (float) $periode->berat_min,
            'berat_max'  => (float) $periode->berat_max,
            'status'     => $periode->status, // Dijadwalkan | Dibuka
        ]);
    }

    /** `success` dipakai B3Service, `status` dipakai layar B3 lain yang sudah ada. */
    private function kirim($data)
    {
        return response()->json([
            'success' => true,
            'status'  => 'success',
            'data'    => $data,
        ]);
    }
}