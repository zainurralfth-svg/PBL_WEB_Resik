<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\Desa;
use App\Models\Kecamatan;
use App\Services\EncryptionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rules\Password;

class SubAdminController extends Controller
{
    /**
     * Tampilkan daftar Sub Admin Desa
     */
    public function index(Request $request)
    {
        $query = Admin::where('role', 'sub_admin_desa')
            ->with(['desa.kecamatan']);

        // Filter Kecamatan
        if ($request->filled('kecamatan_id')) {
            $query->where('id_kecamatan', $request->kecamatan_id);
        }

        // Filter Desa
        if ($request->filled('desa_id')) {
            $query->where('id_desa', $request->desa_id);
        }

        // Search (nama, email, no_telepon, wilayah)
        if ($request->filled('search')) {
            $search = '%' . $request->search . '%';
            $query->where(function ($q) use ($search) {
                $q->where('nama', 'LIKE', $search)
                    ->orWhere('email', 'LIKE', $search)
                    ->orWhere('no_telepon', 'LIKE', $search)
                    ->orWhereHas('desa', function ($sub) use ($search) {
                        $sub->where('nama_desa', 'LIKE', $search);
                    })
                    ->orWhereHas('kecamatan', function ($sub) use ($search) {
                        $sub->where('nama_kecamatan', 'LIKE', $search);
                    });
            });
        }

        $subAdmins = $query->orderBy('created_at', 'desc')->get();
        $kecamatans = Kecamatan::orderBy('nama_kecamatan')->get();

        return view('admin.sub-admin.index', compact('subAdmins', 'kecamatans'));
    }

    /**
     * ✅ SIMPAN Sub Admin baru (AJAX - RETURN JSON)
     */
    public function store(Request $request)
    {
        try {
            $request->merge(['nama_lengkap' => preg_replace('/\s+/', ' ', trim((string) $request->input('nama_lengkap')))]);
            $validated = $request->validate([
                'nama' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[\pL\s.\'\-]+$/u'],           // ✅ BARU
                'no_telepon' => ['required', 'regex:/^08[0-9]{10,11}$/'],
                'email' => ['required', 'email', 'max:255', 'regex:/^[A-Za-z0-9._%+\-]+@gmail\.com$/i', 'unique:admin,email'],
                'password' => ['required', 'string', 'max:50', Password::min(8)->mixedCase()->numbers()->symbols()],
                'id_kecamatan' => 'required|exists:kecamatan,id_kecamatan',
                'id_desa' => 'required|exists:desa,id_desa',
            ], [
                'password.required' => 'Kata sandi wajib diisi.',
                'password.min'      => 'Kata sandi minimal 8 karakter.',
                'password.max'      => 'Kata sandi maksimal 50 karakter.',
                'email.unique' => 'Email sudah terdaftar.',
                'email.regex' => 'Email harus menggunakan @gmail.com.',
                'no_telepon.required' => 'No. telepon wajib diisi.',
                'no_telepon.regex'    => 'No. telepon harus diawali 08 dan terdiri dari 12 sampai 13 angka.',
                'id_desa.exists' => 'Desa tidak valid.',
                'nama.required' => 'Nama wajib diisi.',
                'nama.min'      => 'Nama minimal 3 karakter.',
                'nama.max'      => 'Nama maksimal 100 karakter.',
                'nama.regex'    => 'Nama hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung (tanpa angka).',
            ]);

            // Validasi: desa harus sesuai kecamatan
            $desa = Desa::find($validated['id_desa']);
            if ($desa->id_kecamatan != $validated['id_kecamatan']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Desa tidak sesuai dengan kecamatan yang dipilih.',
                    'errors' => ['id_desa' => ['Desa tidak sesuai dengan kecamatan yang dipilih.']]
                ], 422);
            }

            $hashedPassword = Hash::make($validated['password']);
            $encryptedPassword = EncryptionService::encrypt($validated['password']);

            Admin::create([
                'nama' => $validated['nama'],                  // ✅ BARU
                'email' => $validated['email'],
                'no_telepon' => $validated['no_telepon'],      // ✅ BARU
                'password' => $hashedPassword,
                'password_encrypted' => $encryptedPassword,
                'role' => 'sub_admin_desa',
                'id_kecamatan' => $validated['id_kecamatan'],
                'id_desa' => $validated['id_desa'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Sub Admin Desa berhasil ditambahkan!'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error create sub admin: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * ✅ UPDATE Sub Admin (AJAX - RETURN JSON)
     */
    public function update(Request $request, $id)
    {
        try {
            $subAdmin = Admin::findOrFail($id);

            if (!$subAdmin->isSubAdminDesa()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak valid.'
                ], 403);
            }

            $request->merge(['nama_lengkap' => preg_replace('/\s+/', ' ', trim((string) $request->input('nama_lengkap')))]);

            $validated = $request->validate([
                'nama' => ['required', 'string', 'min:3', 'max:100', 'regex:/^[\pL\s.\'\-]+$/u'],           // ✅ BARU
                'email' => ['required', 'email', 'max:255', 'regex:/^[A-Za-z0-9._%+\-]+@gmail\.com$/i', 'unique:admin,email,' . $id . ',id_admin'],           // ✅ BARU
                'no_telepon' => ['required', 'regex:/^08[0-9]{10,11}$/'],
                'password' => ['nullable', 'string', 'max:50', Password::min(8)->mixedCase()->numbers()->symbols()],
                'id_kecamatan' => 'required|exists:kecamatan,id_kecamatan',
                'id_desa' => 'required|exists:desa,id_desa',
            ], [
                'password.required' => 'Kata sandi wajib diisi.',
                'password.min'      => 'Kata sandi minimal 8 karakter.',
                'password.max'      => 'Kata sandi maksimal 50 karakter.',
                'email.regex' => 'Email harus menggunakan @gmail.com.',
                'nama.required' => 'Nama wajib diisi.',
                'email.unique' => 'Email sudah terdaftar.',
                'no_telepon.required' => 'No. telepon wajib diisi.',
                'no_telepon.regex'    => 'No. telepon harus diawali 08 dan terdiri dari 12 sampai 13 angka.',
                'id_desa.exists' => 'Desa tidak valid.',
                'nama.required' => 'Nama wajib diisi.',
                'nama.min'      => 'Nama minimal 3 karakter.',
                'nama.max'      => 'Nama maksimal 100 karakter.',
                'nama.regex'    => 'Nama hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung (tanpa angka).',
            ]);

            // Validasi: desa harus sesuai kecamatan
            $desa = Desa::find($validated['id_desa']);
            if ($desa->id_kecamatan != $validated['id_kecamatan']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Desa tidak sesuai dengan kecamatan.',
                    'errors' => ['id_desa' => ['Desa tidak sesuai dengan kecamatan.']]
                ], 422);
            }

            $updateData = [
                'nama' => $validated['nama'],                  // ✅ BARU
                'email' => $validated['email'],
                'no_telepon' => $validated['no_telepon'],      // ✅ BARU
                'id_kecamatan' => $validated['id_kecamatan'],
                'id_desa' => $validated['id_desa'],
            ];

            // Update password hanya jika diisi
            if (!empty($validated['password']) && $validated['password'] !== '••••••••') {
                $updateData['password'] = Hash::make($validated['password']);
                $updateData['password_encrypted'] = EncryptionService::encrypt($validated['password']);
            }

            $subAdmin->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Sub Admin Desa berhasil diperbarui!'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error update sub admin: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Hapus Sub Admin
     */
    public function destroy($id)
    {
        try {
            $subAdmin = Admin::find($id);

            if (!$subAdmin) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data tidak ditemukan.'
                ], 404);
            }

            if (!$subAdmin->isSubAdminDesa()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hanya Sub Admin Desa yang bisa dihapus.'
                ], 403);
            }

            $subAdmin->delete();

            return response()->json([
                'success' => true,
                'message' => 'Sub Admin Desa berhasil dihapus.'
            ]);
        } catch (\Exception $e) {
            Log::error('Error delete sub admin: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * AJAX: Get desa by kecamatan
     */
    public function getDesaByKecamatan($kecamatanId)
    {
        $desas = Desa::where('id_kecamatan', $kecamatanId)
            ->orderBy('nama_desa')
            ->get(['id_desa', 'nama_desa']);

        return response()->json($desas);
    }
}
