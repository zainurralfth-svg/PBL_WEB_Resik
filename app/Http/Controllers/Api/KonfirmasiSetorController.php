<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TransaksiSetor;
use App\Models\Masyarakat;
use App\Models\Pns;
use App\Models\JenisSampah;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Services\NotificationService;

class KonfirmasiSetorController extends Controller
{
    /**
     * GET /api/setor-need-confirmation/{id_petugas}
     */
    public function getNeedConfirmation($idPetugas)
    {
        try {
            $list = TransaksiSetor::with(['jenisSampah', 'masyarakat', 'pns'])
                ->where('id_petugas', $idPetugas)
                ->whereNull('tanggal_koreksi')
                ->where('status_transaksi', '!=', 'dibatalkan')
                ->orderBy('tanggal_transaksi', 'desc')
                ->get()
                ->map(function ($trx) {
                    $canEdit = $trx->berat_asli === null;
                    $isExpired = Carbon::parse($trx->tanggal_transaksi)->diffInHours(Carbon::now()) >= 24;

                    return [
                        'id_transaksi' => $trx->id_transaksi,
                        'tanggal_transaksi' => $trx->tanggal_transaksi,
                        'jenis_sampah' => $trx->jenisSampah?->jenis ?? 'Umum',
                        'id_jenis_sampah' => $trx->id_jenis_sampah,
                        'berat' => (float) ($trx->berat ?? 0),
                        'harga_per_kg' => (float) ($trx->harga_per_kg ?? 0),
                        'total_rupiah' => (float) ($trx->total_rupiah ?? 0),
                        'nama_pengguna' => $trx->masyarakat?->nama ?? $trx->pns?->nama ?? '-',
                        'id_masyarakat' => $trx->id_masyarakat,
                        'id_pns' => $trx->id_pns,
                        'status_transaksi' => $trx->status_transaksi,
                        'berat_asli' => $trx->berat_asli,
                        'can_edit' => $canEdit && !$isExpired,
                        'is_expired' => $isExpired,
                    ];
                });

            return response()->json([
                'status' => 'success',
                'data' => $list,
                'total' => $list->count()
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/jenis-sampah-list
     */
    public function getJenisSampah()
    {
        try {
            $jenis = JenisSampah::orderBy('jenis', 'asc')->get()->map(function ($item) {
                return [
                    'id_jenis_sampah' => $item->id_jenis_sampah,
                    'jenis' => $item->jenis,
                    'harga' => (float) ($item->harga ?? 0),
                    'satuan' => $item->satuan ?? 'kg',
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $jenis
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * PUT /api/konfirmasi-setor/{id_transaksi}
     * ✅ SALDO DITAMBAHKAN DI SINI SAAT KONFIRMASI
     */
    public function confirm(Request $request, $idTransaksi)
    {
        $request->validate([
            'id_jenis_sampah' => 'required|integer|exists:jenis_sampah,id_jenis_sampah',
            'berat' => 'required|numeric|min:0.1|max:1000',
            'id_petugas' => 'required|integer',
            'catatan' => 'nullable|string',
        ], [
                'berat.required' => 'Berat wajib diisi.',
                'berat.numeric'  => 'Berat harus berupa angka.',
                'berat.min'      => 'Berat minimal 0,1 kg.',
                'berat.max'      => 'Berat maksimal 1000 kg.',
            ]);

        try {
            // ✅ PERBAIKAN #1: Gunakan lockForUpdate() di dalam transaction
            $result = DB::transaction(function () use ($request, $idTransaksi) {
                // ✅ LOCK BARIS INI agar tidak bisa diakses request lain
                $transaksi = TransaksiSetor::where('id_transaksi', $idTransaksi)
                    ->lockForUpdate()  // ✅ PESSIMISTIC LOCKING
                    ->first();

                if (!$transaksi) {
                    throw new \Exception('Transaksi tidak ditemukan');
                }

                // ✅ PERBAIKAN #2: Validasi DI DALAM transaction
                if ($transaksi->status_transaksi === 'dibatalkan') {
                    throw new \Exception('Transaksi sudah dibatalkan, tidak bisa dikonfirmasi');
                }

                if ($transaksi->tanggal_koreksi) {
                    throw new \Exception('Transaksi sudah dikonfirmasi sebelumnya');
                }

                if ($transaksi->berat_asli !== null) {
                    throw new \Exception('Data sudah pernah diedit, tidak bisa diedit lagi');
                }

                $jenis = JenisSampah::find($request->id_jenis_sampah);

                // Simpan berat asli jika ada perubahan
                if ($transaksi->berat != $request->berat || $transaksi->id_jenis_sampah != $request->id_jenis_sampah) {
                    $transaksi->berat_asli = $transaksi->berat;
                    $transaksi->status_transaksi = 'dikoreksi';
                    $transaksi->catatan_koreksi = $request->catatan ?? 'Dikoreksi saat konfirmasi';
                }

                // Update data final
                $transaksi->id_jenis_sampah = $request->id_jenis_sampah;
                $transaksi->berat = $request->berat;
                $transaksi->harga_per_kg = $jenis->harga;
                $transaksi->total_rupiah = $request->berat * $jenis->harga;
                $transaksi->dikoreksi_oleh = $request->id_petugas;
                $transaksi->tanggal_koreksi = now();
                $transaksi->save();

                // ✅✅✅ INCREMENT SALDO HANYA SAAT KONFIRMASI ✅✅✅
                if ($transaksi->id_masyarakat) {
                    Masyarakat::where('id_masyarakat', $transaksi->id_masyarakat)
                        ->increment('saldo', $transaksi->total_rupiah);
                    Masyarakat::where('id_masyarakat', $transaksi->id_masyarakat)
                        ->increment('total_setoran', $transaksi->berat);
                } elseif ($transaksi->id_pns) {
                    Pns::where('id_pns', $transaksi->id_pns)
                        ->increment('saldo', $transaksi->total_rupiah);
                    Pns::where('id_pns', $transaksi->id_pns)
                        ->increment('total_setoran', $transaksi->berat);
                }

                return $transaksi;
            });

            // ✅ Kirim notifikasi DI LUAR transaction (agar tidak memperlambat)
            $result->refresh();
            $user = $result->masyarakat ?? $result->pns;
            $petugas = \App\Models\Petugas::find($result->id_petugas);  // ✅ TAMBAH INI

            $notification = new NotificationService();

            $tipeUser = $result->id_masyarakat ? 'masyarakat' : 'pns';
            $userId = $result->id_masyarakat ?? $result->id_pns;

            // 1. Notifikasi ke user (masyarakat/PNS)
            $notification->sendDepositResult(
                $user?->fcm_token,
                $result->total_rupiah,
                $userId,
                $tipeUser,
                $result->id_transaksi
            );

            // 2. ✅ TAMBAH INI: Notifikasi ke Admin Web
            $notification->sendDeposit(
                $user->nama ?? 'Pengguna',
                $petugas?->desa_id,
                $result->berat,
                $result->berat_asli ?? $result->berat,
                $result->total_rupiah,
                $result->id_transaksi
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Berhasil dikonfirmasi & saldo user ditambahkan'
            ], 200);
        } catch (\Exception $e) {
            // ✅ PERBAIKAN #3: Return error yang lebih informatif
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], $e->getMessage() === 'Transaksi tidak ditemukan' ? 404 : 400);
        }
    }


    /**
     * DELETE /api/tolak-setor/{id_transaksi}
     */
    public function reject($idTransaksi)
    {
        try {
            // ✅ PERBAIKAN: Gunakan lockForUpdate() di dalam transaction
            $result = DB::transaction(function () use ($idTransaksi) {
                // ✅ LOCK BARIS agar tidak bisa diakses request lain
                $transaksi = TransaksiSetor::where('id_transaksi', $idTransaksi)
                    ->lockForUpdate()  // ✅ PESSIMISTIC LOCKING
                    ->first();

                if (!$transaksi) {
                    throw new \Exception('Transaksi tidak ditemukan');
                }

                // ✅ VALIDASI DI DALAM transaction (setelah lock)
                if ($transaksi->tanggal_koreksi !== null) {
                    throw new \Exception('Transaksi sudah dikonfirmasi, tidak bisa ditolak');
                }

                if ($transaksi->status_transaksi === 'dibatalkan') {
                    throw new \Exception('Transaksi sudah dibatalkan sebelumnya');
                }

                // ✅ Update status
                $transaksi->status_transaksi = 'dibatalkan';
                $transaksi->tanggal_koreksi = now();  // ✅ TANDAI SUDAH DIPROSES
                $transaksi->save();

                return $transaksi;
            });

            // ✅ Kirim notifikasi DI LUAR transaction
            $result->refresh();
            $user = $result->masyarakat ?? $result->pns;

            $notification = new NotificationService();

            $tipeUser = $result->id_masyarakat ? 'masyarakat' : 'pns';
            $userId = $result->id_masyarakat ?? $result->id_pns;

            $notification->sendDepositRejected(
                $user?->fcm_token,
                $userId,
                $tipeUser,
                $result->id_transaksi
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Transaksi ditolak'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], $e->getMessage() === 'Transaksi tidak ditemukan' ? 404 : 400);
        }
    }

    /**
     * GET /api/setor-statistics/{id_petugas}
     * UBAH: Dari harian ke mingguan (7 hari terakhir)
     */
    public function getStatistics($idPetugas)
    {
        try {
            $today = Carbon::now();  // PAKAI now() BUKAN today()
            $sevenDaysAgo = Carbon::today()->subDays(6)->startOfDay();  // 7 hari lalu pukul 00:00

            // Filter 7 hari terakhir untuk semua query
            $pending = TransaksiSetor::where('id_petugas', $idPetugas)
                ->whereBetween('tanggal_transaksi', [$sevenDaysAgo, $today])  // MINGGUAN
                ->whereNull('tanggal_koreksi')
                ->where('status_transaksi', '!=', 'dibatalkan')
                ->count();

            $selesai = TransaksiSetor::where('id_petugas', $idPetugas)
                ->whereBetween('tanggal_transaksi', [$sevenDaysAgo, $today])  // MINGGUAN
                ->whereNotNull('tanggal_koreksi')
                ->where('status_transaksi', '!=', 'dibatalkan')
                ->count();

            $dibatalkan = TransaksiSetor::where('id_petugas', $idPetugas)
                ->whereBetween('tanggal_transaksi', [$sevenDaysAgo, $today])  // MINGGUAN
                ->where('status_transaksi', 'dibatalkan')
                ->count();

            $hariIni = TransaksiSetor::where('id_petugas', $idPetugas)
                ->whereBetween('tanggal_transaksi', [$sevenDaysAgo, $today])  // MINGGUAN
                ->count();

            $totalNominalHariIni = TransaksiSetor::where('id_petugas', $idPetugas)
                ->whereBetween('tanggal_transaksi', [$sevenDaysAgo, $today])  // MINGGUAN
                ->whereNotNull('tanggal_koreksi')
                ->where('status_transaksi', '!=', 'dibatalkan')
                ->sum('total_rupiah');

            $totalBeratHariIni = TransaksiSetor::where('id_petugas', $idPetugas)
                ->whereBetween('tanggal_transaksi', [$sevenDaysAgo, $today])  // MINGGUAN
                ->whereNotNull('tanggal_koreksi')
                ->where('status_transaksi', '!=', 'dibatalkan')
                ->sum('berat');

            return response()->json([
                'status' => 'success',
                'data' => [
                    'pending' => $pending,
                    'selesai' => $selesai,
                    'dibatalkan' => $dibatalkan,
                    'hari_ini' => $hariIni,
                    'total_nominal_hari_ini' => (float) $totalNominalHariIni,
                    'total_berat_hari_ini' => (float) $totalBeratHariIni,
                    'periode' => [  // TAMBAH: Info periode
                        'dari' => $sevenDaysAgo->format('d/m/Y'),
                        'sampai' => $today->format('d/m/Y'),
                    ],
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /api/setor-history/{id_petugas}
     */
    public function getHistory($idPetugas, Request $request)
    {
        try {
            $status = $request->query('status', 'all');

            $query = TransaksiSetor::with([
                'jenisSampah',
                'masyarakat.desa.kecamatan',
                'pns.dinas'
            ])
                ->where('id_petugas', $idPetugas)
                ->orderBy('tanggal_transaksi', 'desc');

            if ($status === 'pending') {
                $query->whereNull('tanggal_koreksi')
                    ->where('status_transaksi', '!=', 'dibatalkan');
            } elseif ($status === 'selesai') {
                $query->whereNotNull('tanggal_koreksi')
                    ->where('status_transaksi', '!=', 'dibatalkan');
            } elseif ($status === 'dibatalkan') {
                $query->where('status_transaksi', 'dibatalkan');
            }

            $list = $query->get()->map(function ($trx) {
                $isExpired = Carbon::parse($trx->tanggal_transaksi)->diffInHours(Carbon::now()) >= 24;

                // LOGIC PEKERJAAN DI SINI
                $pekerjaan = '';
                if ($trx->masyarakat) {
                    $kecamatan = optional(optional($trx->masyarakat)->desa)->kecamatan;
                    $desa = optional($trx->masyarakat)->desa;
                    $pekerjaan = 'Masyarakat - ' .
                        ($kecamatan?->nama_kecamatan ?? '-') . ', ' .
                        ($desa?->nama_desa ?? '-');
                } elseif ($trx->pns) {
                    $dinas = optional($trx->pns)->dinas;
                    $pekerjaan = 'ASN/PNS - ' . ($dinas?->nama_dinas ?? '-');
                }

                return [
                    'id_transaksi' => $trx->id_transaksi,
                    'tanggal_transaksi' => $trx->tanggal_transaksi,
                    'jenis_sampah' => $trx->jenisSampah?->jenis ?? 'Umum',
                    'id_jenis_sampah' => $trx->id_jenis_sampah,
                    'berat' => (float) ($trx->berat ?? 0),
                    'berat_asli' => $trx->berat_asli ? (float) $trx->berat_asli : null,
                    'harga_per_kg' => (float) ($trx->harga_per_kg ?? 0),
                    'total_rupiah' => (float) ($trx->total_rupiah ?? 0),
                    'nama_pengguna' => $trx->masyarakat?->nama ?? $trx->pns?->nama ?? '-',
                    'pekerjaan' => $pekerjaan,
                    'id_masyarakat' => $trx->id_masyarakat,
                    'id_pns' => $trx->id_pns,
                    'status_transaksi' => $trx->status_transaksi,
                    'tanggal_koreksi' => $trx->tanggal_koreksi,
                    'catatan_koreksi' => $trx->catatan_koreksi,
                    'can_edit' => $trx->berat_asli === null
                        && !$isExpired
                        && $trx->tanggal_koreksi === null
                        && $trx->status_transaksi !== 'dibatalkan',
                    'is_expired' => $isExpired,
                    'is_confirmed' => $trx->tanggal_koreksi !== null,
                ];
            });

            return response()->json([
                'status' => 'success',
                'data' => $list,
                'total' => $list->count()
            ], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }
}
