<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Masyarakat;
use App\Models\Pns;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function update(Request $request)
    {
        \Log::info('=== PROFILE UPDATE DIPANGGIL ===');
        \Log::info('Request: ' . json_encode($request->all()));

        // ✅ Validasi
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|integer',
            'tipe' => 'required|in:masyarakat,pns',
            'nama' => 'nullable|string|max:100',
            'no_telepon' => ['nullable', 'regex:/^08[0-9]{10,11}$/'],
            'tanggal_lahir' => 'nullable|date|before:today',
            'alamat' => 'nullable|string|max:255',
            'jenis_kelamin' => 'nullable|string|max:20',
            // maksimal 10MB
            'foto' => 'nullable|image|mimes:jpg,jpeg,png|max:10240',
        ], [
            'no_telepon.regex' => 'No telepon harus berupa angka dan memiliki panjang antara 12 hingga 13 digit.',
            'tanggal_lahir.before' => 'Tanggal lahir harus sebelum hari ini dan bukan di masa depan.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $userId = $request->user_id;
            $tipe = $request->tipe;

            // ✅ Cari user berdasarkan tipe
            if ($tipe === 'masyarakat') {

                $user = Masyarakat::with('desa.kecamatan')
                    ->where('id_masyarakat', $userId)
                    ->first();
            } else {

                $user = Pns::with('dinas')
                    ->where('id_pns', $userId)
                    ->first();
            }

            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User tidak ditemukan'
                ], 404);
            }

            if ($request->hasFile('foto')) {

                // hapus foto lama
                if ($user->foto) {

                    $oldFile = $this->uploadDir('profile/' . basename($user->foto));

                    if (file_exists($oldFile) && is_file($oldFile)) {
                        unlink($oldFile);
                    }
                }

                $file = $request->file('foto');

                $fileName = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();

                $destination = $this->uploadDir('profile');

                if (!file_exists($destination)) {
                    mkdir($destination, 0755, true);
                }

                $file->move($destination, $fileName);

                $fotoPath = 'profile/' . $fileName;
            }

            $updateData = [];

            if ($request->filled('nama')) {
                $updateData['nama'] = $request->nama;
            }

            if ($request->filled('no_telepon')) {
                $updateData['no_telepon'] = $request->no_telepon;
            }

            if ($request->filled('tanggal_lahir')) {
                $updateData['tanggal_lahir'] = $request->tanggal_lahir;
            }

            if ($request->filled('alamat')) {
                $updateData['alamat'] = $request->alamat;
            }

            if ($request->filled('jenis_kelamin')) {
                $updateData['jenis_kelamin'] = $request->jenis_kelamin;
            }

            if (isset($fotoPath)) {
                $updateData['foto'] = $fotoPath;
            }

            \Log::info('DATA UPDATE : ', $updateData);

            $user->update($updateData);

            // ✅ REFRESH DATA USER DARI DATABASE
            $user = $user->fresh();

            // ✅ RESPONSE: Kirim URL yang sudah jadi
            return response()->json([
                'status' => 'success',
                'message' => 'Profil berhasil diupdate',
                'data' => [
                    'id_masyarakat' => $user->id_masyarakat ?? null,
                    'id_pns' => $user->id_pns ?? null,
                    'nama' => $user->nama,
                    'email' => $user->email ?? '',
                    'no_telepon' => $user->no_telepon,
                    'tanggal_lahir' => $user->tanggal_lahir,
                    'jenis_kelamin' => $user->jenis_kelamin,
                    'alamat' => $user->alamat,

                    'id_desa' => $user->id_desa ?? null,
                    'id_dinas' => $user->id_dinas ?? null,

                    'nama_desa' => $tipe == 'masyarakat'
                        ? optional($user->desa)->nama_desa
                        : null,

                    'nama_kecamatan' => $tipe == 'masyarakat'
                        ? optional(optional($user->desa)->kecamatan)->nama_kecamatan
                        : null,

                    'nama_dinas' => $tipe == 'pns'
                        ? optional($user->dinas)->nama_dinas
                        : null,

                    // ✅ PASTIKAN TIDAK DOBEL:
                    'foto' => $user->foto ? asset('uploads/' . $user->foto) : null,

                    'tipe' => $tipe,
                ]
            ], 200);
        } catch (\Exception $e) {
            \Log::error('Profile Update Error: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());

            return response()->json([
                'status' => 'error',
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
}