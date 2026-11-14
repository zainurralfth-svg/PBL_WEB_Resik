<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\B3Pendaftaran;

class B3PetugasController extends Controller
{
    // 1. Mengambil daftar tugas B3 untuk petugas (beserta item & data masyarakat)
    public function index(Request $request)
    {
        $status = $request->query('status'); // Filter opsional (pending, diverifikasi, selesai, dll)
        
        $query = B3Pendaftaran::with(['items', 'masyarakat']);
        
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
    // URL: /api/petugas/b3-ringkasan
    public function getRingkasanRute(Request $request)
    {
        $totalWarga = B3Pendaftaran::count();
        $dijemput = B3Pendaftaran::where('status', 'selesai')->count();
        $totalBerat = B3Pendaftaran::where('status', 'selesai')->sum('est_berat');

        return response()->json([
            'success' => true,
            'data' => [
                'dijemput' => $dijemput,
                'total_warga' => $totalWarga,
                'total_berat' => $totalBerat,
                'wilayah' => 'Kec. Bagor',
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

        $pendaftaran->status = $request->input('status', 'selesai');
        
        if ($request->has('est_berat')) {
            $pendaftaran->est_berat = $request->input('est_berat');
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

    public function getRuteDesa(Request $request)
{
    // Mengambil data pendaratan b3 dikelompokkan berdasarkan wilayah/alamat/desa
    // (Sesuaikan jika Anda punya kolom 'desa' di tabel b3_pendaftarans)
    
    $pendaftarans = B3Pendaftaran::with(['items', 'masyarakat'])->get();

    // Contoh pengelompokan sederhana berdasarkan wilayah/desa
    // Jika belum ada kolom desa, kita bisa grouping atau buat dummy dynamic dari data DB
    $totalWarga = $pendaftarans->count();
    $totalBerat = $pendaftarans->sum('est_berat');
    $selesai = $pendaftarans->where('status', 'selesai')->count();

    return response()->json([
        'success' => true,
        'data' => [
            'total_warga' => $totalWarga,
            'total_berat' => $totalBerat,
            'dijemput' => $selesai,
            'list_desa' => [
                [
                    'nama_desa' => 'Desa Bagor',
                    'total_warga_desa' => $totalWarga,
                    'dijemput_desa' => $selesai,
                    'est_berat_desa' => $totalBerat,
                    'status_rute' => 'Dijadwalkan'
                ],
                // Anda bisa kembangkan query grouping desa di sini sesuai struktur database Anda
            ]
        ]
    ], 200);
}
}