<?php

namespace App\Http\Controllers\BankSampah;

use App\Http\Controllers\Controller;
use App\Models\B3Pendaftaran;
use App\Models\B3PendaftaranItem;
use App\Models\Desa;
use App\Models\Masyarakat;
use App\Models\Pns;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tab "Verifikasi" pada halaman B3: Penjemputan Berperiode.
 */
class B3VerifikasiController extends Controller
{
    /** Tarif default B3 per item untuk menghitung imbalan / reward (Rp) */
    protected array $tarifDefaultB3 = [
        'lampu neon'       => 1500,
        'lampu'            => 1500,
        'kaleng cat'       => 2000,
        'elektronik kecil' => 5000,
        'elektronik'       => 5000,
        'baterai'          => 500,
        'aki'              => 10000,
    ];

    public function index(Request $request)
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        $desaTerkunci = ($admin && method_exists($admin, 'isSubAdminDesa') && $admin->isSubAdminDesa() && $admin->id_desa)
            ? $admin->id_desa
            : null;

        $desas = Desa::when($desaTerkunci, fn ($q) => $q->where('id_desa', $desaTerkunci))
            ->orderBy('nama_desa')
            ->get(['id_desa', 'nama_desa']);

        if (! Schema::hasTable('b3_pendaftarans')) {
            return view('admin.bank-sampah.B3.Index', [
                'tab' => 'verifikasi',
                'verifikasis' => new LengthAwarePaginator([], 0, 15),
                'desas' => $desas,
                'tabelBelumAda' => true,
            ]);
        }

        $query = B3Pendaftaran::with(['masyarakat', 'pns', 'desa', 'petugas', 'items'])
            ->untukDesa($desaTerkunci);

        // Status yang relevan dengan verifikasi
        $query->whereIn('status', [
            B3Pendaftaran::DIJADWALKAN,
            B3Pendaftaran::MENUNGGU_VERIFIKASI,
            B3Pendaftaran::DIVERIFIKASI,
            B3Pendaftaran::TIDAK_SESUAI,
            B3Pendaftaran::TIDAK_ADA_DI_TEMPAT,
        ]);

        if ($request->filled('periode')) {
            $query->where('id_periode', (int) $request->query('periode'));
        }

        if ($request->filled('desa_id')) {
            $query->where('id_desa', $request->query('desa_id'));
        }

        if ($request->filled('q')) {
            $kata = trim($request->query('q'));
            $query->where(function ($q) use ($kata) {
                $q->whereHas('masyarakat', fn ($m) => $m->where('nama', 'like', "%{$kata}%"))
                  ->orWhereHas('pns', fn ($m) => $m->where('nama', 'like', "%{$kata}%"))
                  ->orWhereHas('desa', fn ($d) => $d->where('nama_desa', 'like', "%{$kata}%"))
                  ->orWhereHas('petugas', fn ($p) => $p->where('nama_lengkap', 'like', "%{$kata}%"));
            });
        }

        // Urutkan: Menunggu verifikasi & Dijadwalkan lebih awal, kemudian Diverifikasi
        $verifikasis = $query
            ->orderByRaw("FIELD(status, 'menunggu_verifikasi', 'dijadwalkan', 'tidak_ada_di_tempat', 'tidak_sesuai', 'diverifikasi')")
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('admin.bank-sampah.B3.Index', [
            'tab' => 'verifikasi',
            'verifikasis' => $verifikasis,
            'desas' => $desas,
            'tabelBelumAda' => false,
        ]);
    }

    /**
     * Simpan hasil verifikasi barang B3 dari modal.
     * Hanya untuk status 'menunggu_verifikasi'. Status menjadi 'diverifikasi',
     * total imbalan dihitung (jumlah x harga) dan SALDO warga bertambah.
     * Semuanya satu transaksi; verifikasi ulang ditolak supaya saldo tidak dobel.
     */
    public function simpan(Request $request, $id)
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        $request->validate([
            'items' => 'nullable|array',
            'items.*.id' => 'required|integer',
            'items.*.jumlah' => 'required|numeric|min:0',
            'catatan' => 'nullable|string|max:500',
        ]);

        $hasil = DB::transaction(function () use ($id, $request, $admin) {
            /** @var B3Pendaftaran $pendaftaran */
            $pendaftaran = B3Pendaftaran::with('items')->lockForUpdate()->findOrFail($id);

            if ($admin && method_exists($admin, 'isSubAdminDesa') && $admin->isSubAdminDesa() && $admin->id_desa && $pendaftaran->id_desa != $admin->id_desa) {
                abort(403, 'Pendaftaran ini bukan di desa Anda.');
            }

            if ($pendaftaran->status !== B3Pendaftaran::MENUNGGU_VERIFIKASI) {
                return ['ok' => false, 'pesan' => 'Hanya data berstatus "Menunggu verifikasi" yang bisa diverifikasi.'];
            }

            $inputItems = collect($request->input('items', []))->keyBy(fn ($row) => (int) ($row['id'] ?? 0));
            $totalImbalan = 0;

            foreach ($pendaftaran->items as $item) {
                if ($inputItems->has($item->id)) {
                    $jumlahRiil = (int) $inputItems[$item->id]['jumlah'];
                } else {
                    $jumlahRiil = (int) ($item->jumlah_verifikasi ?? $item->jumlah_petugas ?? $item->jumlah);
                }

                $hargaSatuan = $item->harga_satuan > 0
                    ? (float) $item->harga_satuan
                    : $this->getTarifPerJenis(strtolower(trim($item->jenis_b3)));
                $subtotal = $jumlahRiil * $hargaSatuan;

                $item->update([
                    'jumlah_verifikasi' => $jumlahRiil,
                    'jumlah_petugas' => $item->jumlah_petugas ?? $jumlahRiil,
                    'harga_satuan' => $hargaSatuan,
                    'subtotal' => $subtotal,
                ]);

                $totalImbalan += $subtotal;
            }

            // Saldo masuk ke pendaftar (warga atau PNS)
            if ($totalImbalan > 0) {
                if ($pendaftaran->id_masyarakat) {
                    $diubah = Masyarakat::where('id_masyarakat', $pendaftaran->id_masyarakat)->increment('saldo', $totalImbalan);
                } elseif ($pendaftaran->id_pns) {
                    $diubah = Pns::where('id_pns', $pendaftaran->id_pns)->increment('saldo', $totalImbalan);
                } else {
                    $diubah = 0;
                }

                if (! $diubah) {
                    // Batalkan semuanya: lebih baik gagal daripada status berubah tanpa saldo.
                    throw new \RuntimeException('Pendaftar tidak ditemukan, saldo tidak bisa ditambahkan.');
                }
            }

            $pendaftaran->update([
                'status' => B3Pendaftaran::DIVERIFIKASI,
                'total_imbalan' => $totalImbalan,
                'catatan_verifikasi' => $request->input('catatan'),
                'tgl_verifikasi' => now(),
                'alasan_tidak_sesuai' => null,
            ]);

            return ['ok' => true, 'total' => $totalImbalan];
        });

        if (! $hasil['ok']) {
            return back()->with('error', $hasil['pesan']);
        }

        return back()->with('success', 'Verifikasi berhasil disimpan. Saldo warga bertambah Rp ' . number_format($hasil['total'], 0, ',', '.') . '.');
    }

    /**
     * Tandai data penjemputan B3 tidak sesuai. Status menjadi 'tidak_sesuai'
     * (akhir): tidak ada saldo, warga melihat alasannya. Alasan wajib diisi.
     */
    public function tidakSesuai(Request $request, $id)
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        $request->validate([
            'alasan' => 'required|string|max:500',
        ], [
            'alasan.required' => 'Alasan wajib diisi untuk menandai Tidak sesuai.',
        ]);

        $hasil = DB::transaction(function () use ($id, $request, $admin) {
            $pendaftaran = B3Pendaftaran::lockForUpdate()->findOrFail($id);

            if ($admin && method_exists($admin, 'isSubAdminDesa') && $admin->isSubAdminDesa() && $admin->id_desa && $pendaftaran->id_desa != $admin->id_desa) {
                abort(403, 'Pendaftaran ini bukan di desa Anda.');
            }

            if ($pendaftaran->status !== B3Pendaftaran::MENUNGGU_VERIFIKASI) {
                return ['ok' => false, 'pesan' => 'Hanya data berstatus "Menunggu verifikasi" yang bisa ditandai Tidak sesuai.'];
            }

            $pendaftaran->update([
                'status' => B3Pendaftaran::TIDAK_SESUAI,
                'total_imbalan' => 0,
                'catatan_verifikasi' => null,
                'alasan_tidak_sesuai' => trim((string) $request->input('alasan')),
                'tgl_verifikasi' => now(),
            ]);

            return ['ok' => true];
        });

        if (! $hasil['ok']) {
            return back()->with('error', $hasil['pesan']);
        }

        return back()->with('success', 'Data ditandai Tidak sesuai. Warga akan melihat alasannya.');
    }

    /**
     * Estimasi tarif imbalan berdasarkan kata kunci nama barang B3.
     */
    protected function getTarifPerJenis(string $jenis): float
    {
        foreach ($this->tarifDefaultB3 as $kunci => $tarif) {
            if (str_contains($jenis, $kunci)) {
                return (float) $tarif;
            }
        }

        return 1500.0;
    }
}