<?php

namespace App\Http\Controllers\BankSampah;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\Penjemputan;
use Illuminate\Support\Facades\Auth;

class PenjemputanController extends Controller
{
    protected $table = 'penjemputans';

    public function index(Request $request)
    {
        $query = Penjemputan::with('petugas');

    // ✅ FILTER OTOMATIS UNTUK SUB ADMIN DESA
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        if ($admin && $admin->isSubAdminDesa() && $admin->id_desa) {
            $query->where('id_petugas', function ($q) use ($admin) {
                $q->select('id_petugas')
                    ->from('petugas')
                    ->where('level', 'bank_sampah_' . $admin->id_desa);
            });
        }

        // FILTER BULAN
        if ($request->filled('bulan')) {
            $query->whereMonth('waktu', $request->bulan);
        }

        // FILTER TAHUN
        if ($request->filled('tahun')) {
            $query->whereYear('waktu', $request->tahun);
        }

        // FILTER STATUS
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // ✅ FILTER KECAMATAN - VERSI LEBIH RELIABLE
        if ($request->filled('kecamatan_id')) {
            // Ambil semua ID petugas yang berada di kecamatan tersebut
            $petugasIds = \App\Models\Petugas::where('level', 'LIKE', 'bank_sampah_%')
                ->get()
                ->filter(function ($petugas) use ($request) {
                    // Extract ID desa dari level
                    if (strpos($petugas->level, 'bank_sampah_') === 0) {
                        $idDesa = str_replace('bank_sampah_', '', $petugas->level);
                        $desa = \App\Models\Desa::find($idDesa);
                        return $desa && $desa->id_kecamatan == $request->kecamatan_id;
                    }
                    return false;
                })
                ->pluck('id_petugas')
                ->toArray();

            // Filter penjemputan berdasarkan id_petugas
            if (!empty($petugasIds)) {
                $query->whereIn('id_petugas', $petugasIds);
            } else {
                // Jika tidak ada petugas di kecamatan ini, return empty result
                $query->whereRaw('1 = 0');
            }
        }

        // ✅ FILTER DESA - VERSI LEBIH RELIABLE
        if ($request->filled('desa_id')) {
            // Ambil semua ID petugas yang berada di desa tersebut
            $petugasIds = \App\Models\Petugas::where('level', 'bank_sampah_' . $request->desa_id)
                ->pluck('id_petugas')
                ->toArray();

            // Filter penjemputan berdasarkan id_petugas
            if (!empty($petugasIds)) {
                $query->whereIn('id_petugas', $petugasIds);
            } else {
                $query->whereRaw('1 = 0');
            }
        }

        $penjemputans = $query->orderBy('waktu', 'desc')->paginate(15);
        $tahunList = collect(range(date('Y') - 5, date('Y') + 5));

        // ✅ AMBIL DATA KECAMATAN UNTUK DROPDOWN FILTER
        $kecamatans = \App\Models\Kecamatan::orderBy('nama_kecamatan')->get();

        // ✅ TAMBAHKAN INI: Ambil data desa jika ada filter kecamatan
        $desas = collect();
        if ($request->filled('kecamatan_id')) {
            $desas = \App\Models\Desa::where('id_kecamatan', $request->kecamatan_id)
                ->orderBy('nama_desa')
                ->get();
        }

        return view('admin.bank-sampah.penjemputan.index', compact(
            'penjemputans',
            'tahunList',
            'kecamatans',
            'desas'  // ← ✅ TAMBAHKAN INI!
        ));
    }


    public function show($id)
    {
        // ✅ HAPUS eager loading 'desa.kecamatan' yang bermasalah
        $item = Penjemputan::with('petugas')->find($id);

        if (!$item) {
            return response()->json(['error' => 'Data tidak ditemukan'], 404);
        }

        // ✅ HITUNG WILAYAH KERJA SECARA MANUAL
        $wilayahKerja = 'Petugas DLH';
        $idKecamatan = null;
        $idDesa = null;

        if ($item->petugas) {
            if ($item->petugas->level === 'petugas_dlh') {
                $wilayahKerja = 'Petugas DLH';
            } elseif (strpos($item->petugas->level, 'bank_sampah_') === 0) {
                $idDesa = str_replace('bank_sampah_', '', $item->petugas->level);
                $desa = \App\Models\Desa::with('kecamatan')->find($idDesa);
                if ($desa && $desa->kecamatan) {
                    $wilayahKerja = 'Bank Sampah ' . strtoupper($desa->nama_desa) .
                        ' (' . $desa->nama_desa . ', ' . $desa->kecamatan->nama_kecamatan . ')';
                    $idKecamatan = $desa->id_kecamatan;
                }
            }
        }

        return response()->json([
            'id' => $item->id,
            'id_petugas' => $item->id_petugas,
            'nama_petugas' => $item->petugas->nama_lengkap ?? $item->nama_admin,
            'nama_admin' => $item->nama_admin,
            'wilayah_kerja' => $wilayahKerja,
            'id_kecamatan' => $idKecamatan,
            'id_desa' => $idDesa,
            'waktu' => $item->waktu,
            'berat' => $item->berat,
            'lokasi' => $item->lokasi,
            'keterangan' => $item->keterangan,
            'status' => $item->status,
            'foto' => $item->foto ? $this->getUrlFoto($item->foto) : null,
        ]);
    }

    private function getUrlFoto($path)
    {
        return self::uploadUrl($path);
    }


      public function approve($id)
    {
        try {
            $affected = DB::table($this->table)
                ->where('id', $id)
                ->where('status', 'diproses')
                ->update(['status' => 'disetujui']);
    
            if (!$affected) {
                return redirect()->back()->with('error', 'Data sudah diproses atau tidak ditemukan.');
            }
    
            $penjemputan = Penjemputan::with('petugas')->find($id);
    
            if (!$penjemputan || !$penjemputan->petugas) {
                \Log::error('Penjemputan atau petugas tidak ditemukan, ID: ' . $id);
                return redirect()->back()->with('error', 'Data tidak ditemukan.');
            }
    
            // ✅ KIRIM NOTIFIKASI DENGAN ERROR HANDLING
            try {
                $notification = new \App\Services\NotificationService();
                $notification->sendPickupResult(
                    $penjemputan->petugas->fcm_token,
                    "disetujui",
                    $penjemputan->id_petugas,
                    'petugas',
                    $penjemputan->id
                );
                \Log::info('Notifikasi penjemputan disetujui berhasil dikirim');
            } catch (\Exception $notifError) {
                \Log::error(' Gagal kirim notifikasi: ' . $notifError->getMessage());
                // Tetap return success karena penjemputan sudah disetujui
            }
    
            return redirect()->back()->with('success', 'Penjemputan berhasil disetujui.');
            
        } catch (\Exception $e) {
            \Log::error('❌ Error approve penjemputan: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

       public function reject($id)
    {
        try {
            $affected = DB::table($this->table)
                ->where('id', $id)
                ->where('status', 'diproses')
                ->update(['status' => 'ditolak']);
    
            if (!$affected) {
                return redirect()->back()->with('error', 'Gagal menolak data.');
            }
    
            $penjemputan = Penjemputan::with('petugas')->find($id);
    
            if (!$penjemputan || !$penjemputan->petugas) {
                \Log::error('Penjemputan atau petugas tidak ditemukan, ID: ' . $id);
                return redirect()->back()->with('error', 'Data tidak ditemukan.');
            }
    
            // ✅ KIRIM NOTIFIKASI DENGAN ERROR HANDLING
            try {
                $notification = new \App\Services\NotificationService();
                $notification->sendPickupResult(
                    $penjemputan->petugas->fcm_token,
                    "ditolak",
                    $penjemputan->id_petugas,
                    'petugas',
                    $penjemputan->id
                );
                \Log::info('Notifikasi penjemputan ditolak berhasil dikirim');
            } catch (\Exception $notifError) {
                \Log::error('❌ Gagal kirim notifikasi: ' . $notifError->getMessage());
                // Tetap return success karena penjemputan sudah ditolak
            }
    
            return redirect()->back()->with('success', 'Penjemputan ditolak.');
            
        } catch (\Exception $e) {
            \Log::error('❌ Error reject penjemputan: ' . $e->getMessage());
            \Log::error($e->getTraceAsString());
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    // ========================================
    // 🔹 ADMIN AJAX METHODS (Dari folder Admin, Direname)
    // ========================================

    /**
     * Store data penjemputan via AJAX (Admin Web)
     * Endpoint: POST /admin/bank-sampah/penjemputan/store
     */
    public function storeFromAdmin(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'foto' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'nama_admin' => 'required|string',
            'waktu' => 'required|date',
            'berat' => 'required|numeric|min:0.1|max:1000',
            'lokasi' => 'required|string',
            'keterangan' => 'nullable|string',
            'status' => 'required|in:diproses,disetujui,ditolak',
        ]);

        if ($validator->fails()) {
            return response()->json(['status' => 'error', 'message' => $validator->errors()->first()], 422);
        }

        $data = $request->except('foto');

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

        return response()->json(['status' => 'success', 'message' => 'Penjemputan berhasil diajukan', 'data' => $penjemputan], 201);
    }

    /**
     * Get list by admin ID via AJAX (Admin Web)
     * Endpoint: GET /admin/bank-sampah/penjemputan/list/{adminId}
     */
    public function indexByAdmin($adminId)
    {
        $data = Penjemputan::where('nama_admin', $adminId)
            ->orWhere('id', 'like', "%$adminId%")
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['status' => 'success', 'data' => $data]);
    }
}