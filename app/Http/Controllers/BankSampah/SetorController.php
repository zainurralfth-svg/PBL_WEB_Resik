<?php

namespace App\Http\Controllers\BankSampah;

use App\Http\Controllers\Controller;
use App\Models\TransaksiSetor;
use App\Models\JenisSampah;
use App\Models\Masyarakat;
use App\Models\Pns;
use App\Models\Kecamatan;
use App\Models\Desa;
use App\Models\Dinas;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class SetorController extends Controller
{
    public function index(Request $request)
    {
        $query = TransaksiSetor::with(['masyarakat.desa.kecamatan', 'pns.dinas', 'pns.desa.kecamatan', 'jenisSampah', 'petugas'])
            ->orderBy('tanggal_transaksi', 'desc');

        // ✅ FILTER OTOMATIS UNTUK SUB ADMIN DESA
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        if ($admin && $admin->isSubAdminDesa() && $admin->id_desa) {

            // ✅ AMBIL SEMUA ID PETUGAS YANG BERASAL DARI DESA INI
            $petugasIds = \App\Models\Petugas::where('level', 'bank_sampah_' . $admin->id_desa)
                ->pluck('id_petugas')
                ->toArray();

            $query->where(function ($q) use ($admin, $petugasIds) {
                $q->whereHas('masyarakat.desa', function ($sub) use ($admin) {
                    $sub->where('id_desa', $admin->id_desa);
                })
                    ->orWhereHas('pns.desa', function ($sub) use ($admin) {
                        $sub->where('id_desa', $admin->id_desa);
                    })
                    // ✅ TAMBAHKAN INI: Tampilkan juga jika petugas yang memproses adalah dari desa ini
                    ->orWhereIn('id_petugas', $petugasIds);
            });
        }

        // Filter Pencarian
        if ($request->filled('search')) {
            $keyword = $request->search;
            $query->where(function ($q) use ($keyword) {
                $q->whereHas('masyarakat', function ($sub) use ($keyword) {
                    $sub->where('nama', 'like', '%' . $keyword . '%');
                })
                    ->orWhereHas('pns', function ($sub) use ($keyword) {
                        $sub->where('nama', 'like', '%' . $keyword . '%');
                    })
                    ->orWhereHas('petugas', function ($sub) use ($keyword) {
                        $sub->where('nama_lengkap', 'like', '%' . $keyword . '%');
                    })
                    ->orWhereHas('jenisSampah', function ($sub) use ($keyword) {
                        $sub->where('jenis', 'like', '%' . $keyword . '%');
                    });
            });
        } // ✅ FIX 1: Closing brace untuk if search

        // Filter Bulan
        if ($request->filled('bulan')) {
            $query->whereMonth('tanggal_transaksi', $request->bulan);
        }

        // Filter Tahun
        if ($request->filled('tahun')) {
            $query->whereYear('tanggal_transaksi', $request->tahun);
        }

        // Filter Kecamatan
        if ($request->filled('kecamatan_id')) {
            $query->whereHas('masyarakat.desa', function ($q) use ($request) {
                $q->where('id_kecamatan', $request->kecamatan_id);
            });
        }

        // Filter Desa (untuk Masyarakat)
        if ($request->filled('desa_id')) {
            $query->whereHas('masyarakat.desa', function ($q) use ($request) {
                $q->where('id_desa', $request->desa_id);
            });
        }

        // Filter Dinas (untuk PNS)
        if ($request->filled('dinas_id')) {
            $query->whereHas('pns', function ($q) use ($request) {
                $q->where('id_dinas', $request->dinas_id);
            });
        }

        // Pagination & Statistik
        $setorData = $query->paginate(15);

        // Statistik
        $totalSetor   = TransaksiSetor::count();
        $totalBerat   = TransaksiSetor::sum('berat') ?? 0;
        $totalNilai   = TransaksiSetor::sum('total_rupiah') ?? 0;

        // Hitung nasabah unik
        $totalNasabah = DB::table('transaksi_setor')
            ->select(DB::raw('COUNT(DISTINCT id_masyarakat) + COUNT(DISTINCT id_pns) as total'))
            ->value('total') ?? 0;

        $jenisSampah = JenisSampah::all();

        $tahunList = collect(range(date('Y') - 5, date('Y') + 5));

        // Data untuk filter wilayah
        $kecamatans = Kecamatan::orderBy('nama_kecamatan')->get();
        $desas = collect();
        if ($request->filled('kecamatan_id')) {
            $desas = Desa::where('id_kecamatan', $request->kecamatan_id)
                ->orderBy('nama_desa')
                ->get();
        }

        // Data untuk filter dinas
        $dinasList = Dinas::orderBy('nama_dinas')->get();

        // ✅ TAMBAHKAN DATA PEKERJAAN (Kecamatan/Desa/Dinas)
        foreach ($setorData as $row) {
            if ($row->masyarakat) {
                $row->nama_pengsetor = $row->masyarakat->nama ?? 'Unknown';
                $row->tipe_pengsetor = 'Masyarakat';
                $row->kecamatan = $row->masyarakat->desa->kecamatan->nama_kecamatan ?? '-';
                $row->desa = $row->masyarakat->desa->nama_desa ?? '-';
                $row->dinas = null;
            } else if ($row->pns) {
                $row->nama_pengsetor = $row->pns->nama ?? 'Unknown';
                $row->tipe_pengsetor = 'PNS';
                $row->dinas = $row->pns->dinas->nama_dinas ?? 'ASN/PNS';

                // ✅ AMBIL JUGA KECAMATAN DAN DESA UNTUK PNS
                $row->kecamatan = $row->pns->desa->kecamatan->nama_kecamatan ?? '-';
                $row->desa = $row->pns->desa->nama_desa ?? '-';
            }
        }

        // AJAX Response
        if ($request->ajax()) {
            return response()->json([
                'table' => view('admin.bank-sampah.setor-sampah.index', compact('setorData', 'totalSetor', 'totalBerat', 'totalNilai', 'totalNasabah', 'jenisSampah', 'tahunList', 'kecamatans', 'desas', 'dinasList'))->render()
            ]);
        }

        return view('admin.bank-sampah.setor-sampah.index', compact(
            'setorData',
            'totalSetor',
            'totalBerat',
            'totalNilai',
            'totalNasabah',
            'jenisSampah',
            'tahunList',
            'kecamatans',
            'desas',
            'dinasList'
        ));
    }

    public function detail($id): JsonResponse
    {
        // ✅ TAMBAHKAN 'pns.desa.kecamatan'
        $data = TransaksiSetor::with(['masyarakat.desa.kecamatan', 'pns.dinas', 'pns.desa.kecamatan', 'jenisSampah', 'petugas'])->findOrFail($id);
        return response()->json($data);
    }

    // ✅ METHOD UPDATE: Untuk koreksi transaksi (hanya 1x)
    public function update(Request $request, $id): JsonResponse
    {
        $transaksi = TransaksiSetor::findOrFail($id);

        // Validasi: hanya bisa koreksi jika status 'selesai'
        if ($transaksi->status_transaksi !== 'selesai') {
            return response()->json([
                'error' => '❌ Transaksi sudah dikoreksi atau dibatalkan'
            ], 403);
        }

        $validated = $request->validate([
            'berat' => 'required|numeric|min:0.1|max:1000',
            'catatan_koreksi' => 'required|string|max:255',
        ], [
                'berat.required' => 'Berat wajib diisi.',
                'berat.numeric'  => 'Berat harus berupa angka.',
                'berat.min'      => 'Berat minimal 0,1 kg.',
                'berat.max'      => 'Berat maksimal 1000 kg.',
            ]);

        $beratLama = $transaksi->berat;
        $beratBaru = $validated['berat'];
        $selisihBerat = $beratBaru - $beratLama;
        $selisihSaldo = $selisihBerat * $transaksi->harga_per_kg;

        DB::beginTransaction();
        try {
            // Update data transaksi
            $transaksi->update([
                'berat' => $beratBaru,
                'total_rupiah' => $beratBaru * $transaksi->harga_per_kg,
                'status_transaksi' => 'dikoreksi',
                'catatan_koreksi' => $validated['catatan_koreksi'],
                'dikoreksi_oleh' => auth()->user()->id_petugas ?? auth()->id(),
                'tanggal_koreksi' => now(),
            ]);

            // Auto update saldo (tambah/kurangi sesuai selisih)
            if ($transaksi->id_masyarakat) {
                Masyarakat::where('id_masyarakat', $transaksi->id_masyarakat)
                    ->increment('saldo', $selisihSaldo);
            }
            if ($transaksi->id_pns) {
                Pns::where('id_pns', $transaksi->id_pns)
                    ->increment('saldo', $selisihSaldo);
            }

            // Hitung selisih berat untuk adjust total_setoran
            $selisihBerat = $beratBaru - $beratLama;

            if ($transaksi->id_pns) {
                Pns::where('id_pns', $transaksi->id_pns)
                    ->increment('total_setoran', $selisihBerat);
            }
            if ($transaksi->id_masyarakat) {
                Masyarakat::where('id_masyarakat', $transaksi->id_masyarakat)
                    ->increment('total_setoran', $selisihBerat);
            }
            DB::commit();

            return response()->json([
                'success' => '✅ Koreksi berhasil!',
                'data' => $transaksi->load(['masyarakat', 'pns', 'jenisSampah'])
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => '❌ ' . $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'id_masyarakat'   => 'nullable|exists:masyarakat,id_masyarakat',
            'id_pns'          => 'nullable|exists:pns,id_pns',
            'id_jenis_sampah' => 'required|exists:jenis_sampah,id_jenis_sampah',
            'berat' => 'required|numeric|min:0.1|max:1000',
        ], [
                'berat.required' => 'Berat wajib diisi.',
                'berat.numeric'  => 'Berat harus berupa angka.',
                'berat.min'      => 'Berat minimal 0,1 kg.',
                'berat.max'      => 'Berat maksimal 1000 kg.',
          ]);

        // Validasi: Harus ada salah satu
        if (empty($validated['id_masyarakat']) && empty($validated['id_pns'])) {
            return redirect()->back()->with('error', 'Pengguna tidak ditemukan!');
        }

        $jenis = JenisSampah::find($validated['id_jenis_sampah']);
        $harga_per_kg = $jenis->harga;
        $total = $validated['berat'] * $harga_per_kg;

        // ✅ Dapatkan ID petugas yang benar
        $idPetugas = auth()->user()->id_petugas ?? auth()->id();

        DB::beginTransaction();
        try {
            // Buat transaksi
            TransaksiSetor::create([
                'id_masyarakat'    => $validated['id_masyarakat'] ?? null,
                'id_pns'           => $validated['id_pns'] ?? null,
                'id_jenis_sampah'  => $validated['id_jenis_sampah'],
                'id_petugas'       => $idPetugas,
                'berat'            => $validated['berat'],
                'berat_asli'       => $validated['berat'],        // ✅ Simpan berat original
                'harga_per_kg'     => $harga_per_kg,
                'total_rupiah'     => $total,
                'tanggal_transaksi' => now(),
                'status_transaksi' => 'selesai',                  // ✅ Default status
            ]);


            // Update total_setoran untuk PNS/Masyarakat
            if ($validated['id_pns']) {
                Pns::where('id_pns', $validated['id_pns'])
                    ->increment('total_setoran', $validated['berat']);
            }
            if ($validated['id_masyarakat']) {
                Masyarakat::where('id_masyarakat', $validated['id_masyarakat'])
                    ->increment('total_setoran', $validated['berat']);
            }


            DB::commit();
            return redirect()->back()->with('success', '✅ Data setoran berhasil disimpan! Saldo pengguna telah ditambahkan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', '❌ Terjadi kesalahan: ' . $e->getMessage());
        }
    }
}
