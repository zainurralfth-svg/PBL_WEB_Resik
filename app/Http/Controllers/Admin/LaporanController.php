<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Laporan;
use Illuminate\Http\Request;

class LaporanController extends Controller
{
    /**
     * Tampilkan halaman kelola laporan
     */
    public function index(Request $request)
    {
        $laporanList = Laporan::with('masyarakat')
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('admin.laporan.index', compact('laporanList'));
    }

    /**
     * Update status laporan via AJAX
     */
    public function updateStatus(Request $request)
    {
        $validated = $request->validate([
            'id' => 'required|integer|exists:laporan,id',
            'status' => 'required|in:Diproses,Diterima,Ditolak',
            'balasan' => 'nullable|string|max:500',
            'foto_balasan' => 'nullable|image|max:10240',
        ], [
            'foto_balasan.image' => 'File harus berupa gambar.',
            'foto_balasan.max' => 'Ukuran gambar maksimal 10MB.',
        ]);

        try {
            // ✅ FIX #1: LOAD RELASI masyarakat DAN pns
            $laporan = Laporan::with(['masyarakat', 'pns'])->findOrFail($validated['id']);

            // Validasi: hanya bisa update jika status masih Diproses
            if ($laporan->status !== 'Diproses' && $validated['status'] === 'Diproses') {
                return response()->json(['success' => false, 'message' => 'Status tidak dapat diubah kembali ke Diproses'], 400);
            }

            $updateData = [
                'status' => $validated['status'],
                'balasan' => $validated['balasan'] ?: null,
            ];

            // ✅ Handle upload foto balasan
            if ($request->hasFile('foto_balasan')) {
                $file = $request->file('foto_balasan');
                $filename = time() . '_balasan_' . $laporan->id . '.' . $file->getClientOriginalExtension();

                $destination = $this->uploadDir('laporan/balasan');

                if (!file_exists($destination)) {
                    mkdir($destination, 0755, true);
                }

                // Hapus foto lama jika ada
                if ($laporan->foto_balasan) {
                    $oldFile = $destination . '/' . basename($laporan->foto_balasan);
                    if (file_exists($oldFile)) {
                        unlink($oldFile);
                    }
                }

                $file->move($destination, $filename);
                $updateData['foto_balasan'] = 'laporan/balasan/' . $filename;
            }

            $laporan->update($updateData);
            $laporan->refresh();

            // ✅ FIX #2: AMBIL USER PELAPOR (masyarakat atau pns)
            $user = $laporan->masyarakat ?: $laporan->pns;

            if (!$user) {
                \Log::error(' User pelapor tidak ditemukan untuk laporan #' . $laporan->id);
                return response()->json(['success' => false, 'message' => 'User pelapor tidak ditemukan'], 404);
            }

            // ✅ FIX #3: LOGGING UNTUK DEBUG
            \Log::info('=== KIRIM NOTIFIKASI LAPORAN ===');
            \Log::info('Laporan ID: ' . $laporan->id);
            \Log::info('User Type: ' . ($laporan->masyarakat ? 'masyarakat' : 'pns'));
            \Log::info('User ID: ' . ($user->id_masyarakat ?? $user->id_pns));
            \Log::info('User Name: ' . $user->nama);
            \Log::info('FCM Token: ' . ($user->fcm_token ?? 'NULL'));
            \Log::info('Status: ' . $laporan->status);

            // ✅ FIX #4: KIRIM NOTIFIKASI
            $notification = new \App\Services\NotificationService();

            $tipeUser = $laporan->masyarakat ? 'masyarakat' : 'pns';
            $userId = $laporan->masyarakat ? $user->id_masyarakat : $user->id_pns;

            $notification->sendReportResult(
                $user->fcm_token,
                $laporan->status,
                $userId,
                $tipeUser,
                $laporan->id
            );

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('❌ Error update status laporan: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}