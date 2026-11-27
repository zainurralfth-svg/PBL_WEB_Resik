<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Penjemputan;
use App\Models\Petugas;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PenjemputanController extends Controller
{
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_petugas' => 'required|integer|exists:petugas,id_petugas',
            'nama_admin' => 'required|string',
            'waktu' => 'required|date',
            'lokasi' => 'required|string',
            'berat' => 'required|numeric|min:0.1|max:1000',
            'keterangan' => 'nullable|string',
            'status' => 'nullable|in:diproses,disetujui,ditolak',
            'foto' => 'nullable|image|mimes:jpg,jpeg,png|max:5120',
        ], [
                'berat.required' => 'Berat wajib diisi.',
                'berat.numeric'  => 'Berat harus berupa angka.',
                'berat.min'      => 'Berat minimal 0,1 kg.',
                'berat.max'      => 'Berat maksimal 1000 kg.',
            ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first()
            ], 422);
        }

        $petugas = Petugas::find($request->id_petugas);

        if (!$petugas) {
            return response()->json([
                'status' => 'error',
                'message' => 'Petugas tidak ditemukan'
            ], 404);
        }

        try {
            $data = [
                'id_petugas' => $request->id_petugas,
                'nama_admin' => $request->nama_admin,
                'waktu' => $request->waktu,
                'lokasi' => $request->lokasi,
                'berat' => $request->berat,
                'keterangan' => $request->keterangan,
                'status' => $request->status ?? 'diproses',
            ];

            if ($request->hasFile('foto')) {
                // Local pakai 'public' (storage), Production pakai 'uploads'
                $file = $request->file('foto');
                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

                $destination = $this->uploadDir('penjemputan');

                if (!file_exists($destination)) {
                    mkdir($destination, 0755, true);
                }

                $file->move($destination, $fileName);

                $data['foto'] = 'penjemputan/' . $fileName;
            }

            $penjemputan = Penjemputan::create($data);

            $notification = new NotificationService();

            $notification->sendPickup(
                $petugas->nama_lengkap,
                $petugas->desa?->nama_desa ?? "Tidak diketahui",
                $petugas->desa?->kecamatan?->nama_kecamatan ?? "Tidak diketahui",
                $petugas->desa_id ?? 0,
                $penjemputan->id
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Penjemputan berhasil diajukan',
                'data' => $penjemputan
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal mengajukan penjemputan: ' . $e->getMessage()
            ], 500);
        }
    }
}