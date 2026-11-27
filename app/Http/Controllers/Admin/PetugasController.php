<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Petugas;
use App\Services\EncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class PetugasController extends Controller
{
    /**
     * Simpan petugas baru (AJAX - RETURN JSON)
     */
    public function store(Request $request)
    {
        try {
            $request->merge(['nama_lengkap' => preg_replace('/\s+/', ' ', trim((string) $request->input('nama_lengkap')))]);
            $validated = $request->validate([
                'nama_lengkap' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[\pL\s.\'\-]+$/u'],
                'email' => ['required', 'email', 'max:150', 'regex:/^[A-Za-z0-9._%+\-]+@gmail\.com$/i', 'unique:petugas,email'],
                'password' => ['required', 'string', 'max:50', Password::min(8)->mixedCase()->numbers()->symbols()], // TANPA REGEX - boleh karakter spesial
                'no_telepon' => ['required', 'regex:/^08[0-9]{10,11}$/'],
                'level' => [
                    'required',
                    'string',
                    function ($attribute, $value, $fail) {
                        // Validasi custom: harus 'petugas_dlh' atau 'bank_sampah_{id_desa}'
                        if ($value !== 'petugas_dlh' && !preg_match('/^bank_sampah_\d+$/', $value)) {
                            $fail('Pilih wilayah kerja yang valid.');
                        }
                    },
                ],
            ], [
                'password.required' => 'Kata sandi wajib diisi.',
                'password.min'      => 'Kata sandi minimal 8 karakter.',
                'password.max'      => 'Kata sandi maksimal 50 karakter.',
                'email.unique' => 'Email sudah terdaftar.',
                'email.regex' => 'Email harus menggunakan @gmail.com.',
                'no_telepon.digits_between' => 'No. telepon harus 12 sampai 13 angka (hanya angka).',
                'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
                'nama_lengkap.min'      => 'Nama minimal 3 karakter.',
                'nama_lengkap.max'      => 'Nama maksimal 100 karakter.',
                'nama_lengkap.regex'    => 'Nama hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung (tanpa angka).',
            ]);

            $hashedPassword = Hash::make($validated['password']);
            $encryptedPassword = EncryptionService::encrypt($validated['password']);

            Petugas::create([
                'nama_lengkap' => $validated['nama_lengkap'],
                'email' => $validated['email'],
                'password' => $hashedPassword,
                'password_encrypted' => $encryptedPassword,
                'no_telepon' => $validated['no_telepon'],
                'level' => $validated['level'],
                'is_active' => 1,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Akun petugas berhasil ditambahkan.'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error create petugas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update petugas (AJAX - RETURN JSON)
     */
    public function update(Request $request, $id)
    {
        try {
            $petugas = Petugas::findOrFail($id);

            $request->merge(['nama_lengkap' => preg_replace('/\s+/', ' ', trim((string) $request->input('nama_lengkap')))]);

            $validated = $request->validate([
                'nama_lengkap' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[\pL\s.\'\-]+$/u'],
                'email' => ['required', 'email', 'max:150', 'regex:/^[A-Za-z0-9._%+\-]+@gmail\.com$/i', 'unique:petugas,email,' . $id . ',id_petugas'],
                'password' => ['nullable', 'string', 'max:50', Password::min(8)->mixedCase()->numbers()->symbols()], // TANPA REGEX - boleh karakter spesial
                'no_telepon' => ['required', 'regex:/^08[0-9]{10,11}$/'],
                'level' => [
                    'required',
                    'string',
                        function ($attribute, $value, $fail) {
                        // Validasi custom: harus 'petugas_dlh' atau 'bank_sampah_{id_desa}'
                        if ($value !== 'petugas_dlh' && !preg_match('/^bank_sampah_\d+$/', $value)) {
                            $fail('Pilih wilayah kerja yang valid.');
                        }
                    },
                ],
            ], [
                'password.required' => 'Kata sandi wajib diisi.',
                'password.min'      => 'Kata sandi minimal 8 karakter.',
                'password.max'      => 'Kata sandi maksimal 50 karakter.',
                'email.unique' => 'Email sudah terdaftar.',
                'email.regex' => 'Email harus menggunakan @gmail.com.',
                'no_telepon.required' => 'No. telepon wajib diisi.',
                'no_telepon.regex'    => 'No. telepon harus diawali 08 dan terdiri dari 12 sampai 13 angka.',
                'nama_lengkap.required' => 'Nama lengkap wajib diisi.',
                'nama_lengkap.min'      => 'Nama minimal 3 karakter.',
                'nama_lengkap.max'      => 'Nama maksimal 100 karakter.',
                'nama_lengkap.regex'    => 'Nama hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung (tanpa angka).',
            ]);
            $updateData = [
                'nama_lengkap' => $validated['nama_lengkap'],
                'email' => $validated['email'],
                'no_telepon' => $validated['no_telepon'],
                'level' => $validated['level'],
            ];

            // Update password hanya jika diisi
            if (!empty($validated['password'])) {
                $updateData['password'] = Hash::make($validated['password']);
                $updateData['password_encrypted'] = EncryptionService::encrypt($validated['password']);
            }

            $petugas->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Akun petugas berhasil diperbarui.'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error update petugas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hapus petugas (AJAX - RETURN JSON)
     */
    /**
     * Hapus petugas (AJAX - RETURN JSON)
     */
    public function destroy($id)
    {
        try {
            // ✅ GUNAKAN find() bukan findOrFail()
            $petugas = Petugas::find($id);

            // ✅ CEK apakah data ada
            if (!$petugas) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data petugas tidak ditemukan atau sudah dihapus.'
                ], 404);
            }

            $petugas->delete();

            return response()->json([
                'success' => true,
                'message' => 'Akun petugas berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error delete petugas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get data petugas untuk edit (JSON)
     */
    public function show($id)
    {
        try {
            $petugas = Petugas::findOrFail($id);
            return response()->json([
                'success' => true,
                'data' => [
                    'id_petugas' => $petugas->id_petugas,
                    'nama_lengkap' => $petugas->nama_lengkap,
                    'email' => $petugas->email,
                    'no_telepon' => $petugas->no_telepon,
                    'level' => $petugas->level,
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Data tidak ditemukan'
            ], 404);
        }
    }
}
