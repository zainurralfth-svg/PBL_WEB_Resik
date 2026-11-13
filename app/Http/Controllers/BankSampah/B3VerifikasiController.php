<?php

namespace App\Http\Controllers\BankSampah;

use App\Http\Controllers\Controller;
use App\Models\B3Pendaftaran;
use App\Models\B3PendaftaranItem;
use App\Models\Desa;
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

        // Hanya tampilkan status yang relevan dengan verifikasi (dijadwalkan, menunggu_verifikasi, diverifikasi)
        $query->whereIn('status', [
            B3Pendaftaran::DIJADWALKAN,
            B3Pendaftaran::MENUNGGU_VERIFIKASI,
            B3Pendaftaran::DIVERIFIKASI,
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
            ->orderByRaw("FIELD(status, 'menunggu_verifikasi', 'dijadwalkan', 'diverifikasi')")
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
     * Status diubah menjadi 'diverifikasi' dan total imbalan dihitung.
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

        $berhasil = DB::transaction(function () use ($id, $request, $admin) {
            /** @var B3Pendaftaran $pendaftaran */
            $pendaftaran = B3Pendaftaran::with('items')->lockForUpdate()->findOrFail($id);

            if ($admin && method_exists($admin, 'isSubAdminDesa') && $admin->isSubAdminDesa() && $admin->id_desa && $pendaftaran->id_desa != $admin->id_desa) {
                abort(403, 'Pendaftaran ini bukan di desa Anda.');
            }

            $totalImbalan = 0;

            if ($request->has('items') && is_array($request->items)) {
                foreach ($request->items as $itemInput) {
                    $item = $pendaftaran->items->firstWhere('id', (int) ($itemInput['id'] ?? 0));
                    if (! $item) {
                        continue;
                    }

                    $jumlahRiil = (int) ($itemInput['jumlah'] ?? $item->jumlah);
                    $jenisLower = strtolower(trim($item->jenis_b3));

                    // Tentukan tarif per jenis
                    $hargaSatuan = $item->harga_satuan > 0 ? (float) $item->harga_satuan : $this->getTarifPerJenis($jenisLower);
                    $subtotal = $jumlahRiil * $hargaSatuan;

                    $item->update([
                        'jumlah_verifikasi' => $jumlahRiil,
                        'jumlah_petugas' => $item->jumlah_petugas ?? $jumlahRiil,
                        'harga_satuan' => $hargaSatuan,
                        'subtotal' => $subtotal,
                    ]);

                    $totalImbalan += $subtotal;
                }
            } else {
                // Jika tidak ada array items spesifik, hitung dari item yang ada
                foreach ($pendaftaran->items as $item) {
                    $jumlahRiil = $item->jumlah_verifikasi ?? $item->jumlah_petugas ?? $item->jumlah;
                    $hargaSatuan = $item->harga_satuan > 0 ? (float) $item->harga_satuan : $this->getTarifPerJenis(strtolower(trim($item->jenis_b3)));
                    $subtotal = $jumlahRiil * $hargaSatuan;

                    $item->update([
                        'jumlah_verifikasi' => $jumlahRiil,
                        'harga_satuan' => $hargaSatuan,
                        'subtotal' => $subtotal,
                    ]);

                    $totalImbalan += $subtotal;
                }
            }

            $pendaftaran->update([
                'status' => B3Pendaftaran::DIVERIFIKASI,
                'total_imbalan' => $totalImbalan,
                'catatan_verifikasi' => $request->input('catatan'),
                'tgl_verifikasi' => now(),
                'alasan_tidak_sesuai' => null,
            ]);

            return true;
        });

        if (! $berhasil) {
            return back()->with('error', 'Gagal memverifikasi data B3.');
        }

        return back()->with('success', 'Verifikasi berhasil disimpan.');
    }

    /**
     * Tandai data penjemputan B3 tidak sesuai dengan kondisi riil di lapangan.
     */
    public function tidakSesuai(Request $request, $id)
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        $pendaftaran = B3Pendaftaran::findOrFail($id);

        if ($admin && method_exists($admin, 'isSubAdminDesa') && $admin->isSubAdminDesa() && $admin->id_desa && $pendaftaran->id_desa != $admin->id_desa) {
            abort(403, 'Pendaftaran ini bukan di desa Anda.');
        }

                $alasan = trim((string) $request->input('alasan', ''));

        $pendaftaran->update([
            'status' => B3Pendaftaran::MENUNGGU_VERIFIKASI,
            'catatan_verifikasi' => 'Jumlah fisik dilaporkan tidak sesuai dengan pendaftaran. Menunggu konfirmasi ulang.',
            'alasan_tidak_sesuai' => $alasan !== ''
                ? $alasan
                : 'Jumlah fisik tidak sesuai dengan pendaftaran.',
        ]);

        return back()->with('error', 'Data ditandai tidak sesuai. Menunggu tindak lanjut konfirmasi.');
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

