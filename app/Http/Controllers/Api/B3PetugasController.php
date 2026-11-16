<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\B3Pendaftaran;
use App\Models\B3Periode;
use Carbon\Carbon;

class B3PetugasController extends Controller
{
    // 1. Mengambil daftar tugas B3 untuk petugas
    public function index(Request $request)
    {
        $status = $request->query('status');
        $today = Carbon::today()->toDateString();

        // Cari periode aktif berdasarkan tgl_buka dan tgl_tutup, atau ambil yang terbaru
        $periodeAktif = B3Periode::where('tgl_buka', '<=', $today)
                            ->where('tgl_tutup', '>=', $today)
                            ->first() ?? B3Periode::latest('id_periode')->first();

        $query = B3Pendaftaran::with(['items', 'masyarakat', 'pns', 'desa']);
        
        if ($periodeAktif) {
            $query->where('id_periode', $periodeAktif->id_periode);
        } else {
            $query->whereRaw('1 = 0'); // Kosongkan jika tidak ada periode
        }
        
        if ($status) {
            $query->where('status', $status);
        }
        
        $tasks = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil data tugas B3',
            'data' => $tasks
        ], 200);
    }

    // 2. Mengambil ringkasan rute (untuk kartu "Penjemputan B3 Hari Ini")
    public function getRingkasanRute(Request $request)
    {
        $today = Carbon::today()->toDateString();
        
        // Cari periode B3 yang aktif hari ini berdasarkan tgl_buka & tgl_tutup
        $periode = B3Periode::where('tgl_buka', '<=', $today)
                    ->where('tgl_tutup', '>=', $today)
                    ->first() ?? B3Periode::latest('id_periode')->first();

        if (!$periode) {
            return response()->json([
                'success' => true,
                'data' => [
                    'dijemput' => 0,
                    'total_warga' => 0,
                    'total_berat' => 0,
                    'wilayah' => 'Tidak ada periode aktif',
                ]
            ], 200);
        }

        $query = B3Pendaftaran::where('id_periode', $periode->id_periode);

        $totalWarga = $query->count();
        // Menghitung status yang sudah dijemput/diproses
        $dijemput = (clone $query)->whereIn('status', ['menunggu_verifikasi', 'diverifikasi'])->count();
        $totalBerat = (clone $query)->sum('est_berat');

        // Ambil nama desa atau wilayah terkait dari periode desa jika ada
        $namaWilayah = 'Kecamatan Nganjuk';
        if ($periode->desa()->exists()) {
            $namaWilayah = 'Desa ' . $periode->desa()->first()->nama_desa;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'dijemput' => $dijemput,
                'total_warga' => $totalWarga,
                'total_berat' => $totalBerat,
                'wilayah' => $namaWilayah,
            ]
        ], 200);
    }

    // 3. Mengupdate status dan berat aktual oleh petugas
    public function updateStatus(Request $request, $id)
    {
        $pendaftaran = B3Pendaftaran::find($id);

        if (!$pendaftaran) {
            return response()->json([
                'success' => false,
                'message' => 'Data penjemputan tidak ditemukan'
            ], 404);
        }

        $pendaftaran->status = $request->input('status', 'diverifikasi');
        
        if ($request->has('est_berat')) {
            $pendaftaran->est_berat = $request->input('est_berat');
        }

        if ($request->has('berat_verifikasi')) {
            $pendaftaran->berat_verifikasi = $request->input('berat_verifikasi');
        }
        
        if ($request->has('catatan_verifikasi')) {
            $pendaftaran->catatan_verifikasi = $request->input('catatan_verifikasi');
        }

        $pendaftaran->save();

        return response()->json([
            'success' => true,
            'message' => 'Status penjemputan berhasil diperbarui',
            'data' => $pendaftaran
        ], 200);
    }

    // 4. Mengambil rute desa secara dinamis berdasarkan periode dari web admin
    public function getRuteDesa(Request $request)
    {
        $today = Carbon::today()->toDateString();
        $periode = B3Periode::where('tgl_buka', '<=', $today)
                    ->where('tgl_tutup', '>=', $today)
                    ->first() ?? B3Periode::latest('id_periode')->first();

        if (!$periode) {
            return response()->json([
                'success' => true,
                'data' => [
                    'total_warga' => 0,
                    'total_berat' => 0,
                    'dijemput' => 0,
                    'list_desa' => []
                ]
            ], 200);
        }

        $pendaftarans = B3Pendaftaran::with(['items', 'masyarakat', 'pns', 'desa'])
                            ->where('id_periode', $periode->id_periode)
                            ->get();

        $totalWarga = $pendaftarans->count();
        $totalBerat = $pendaftarans->sum('est_berat');
        $selesai = $pendaftarans->whereIn('status', ['menunggu_verifikasi', 'diverifikasi'])->count();

        $groupedByDesa = $pendaftarans->groupBy('id_desa');
        $listDesa = [];

        foreach ($groupedByDesa as $idDesa => $items) {
            $namaDesa = $items->first()->desa->nama_desa ?? 'Desa Lainnya';
            $wargaDesa = $items->count();
            $dijemputDesa = $items->whereIn('status', ['menunggu_verifikasi', 'diverifikasi'])->count();
            $beratDesa = $items->sum('est_berat');

            $listDesa[] = [
                'nama_desa' => $namaDesa,
                'total_warga_desa' => $wargaDesa,
                'dijemput_desa' => $dijemputDesa,
                'est_berat_desa' => $beratDesa,
                'status_rute' => ($dijemputDesa >= $wargaDesa && $wargaDesa > 0) ? 'Selesai' : 'Dijadwalkan'
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_warga' => $totalWarga,
                'total_berat' => $totalBerat,
                'dijemput' => $selesai,
                'list_desa' => $listDesa
            ]
        ], 200);
    }
}