<?php

namespace App\Http\Controllers\BankSampah;

use App\Http\Controllers\Controller;
use App\Models\B3Pendaftaran;
use App\Models\B3Periode;
use App\Models\Kecamatan;
use App\Models\Petugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Tab "Periode" pada halaman B3: Penjemputan Berperiode.
 * Tab Pendaftaran ada di B3PendaftaranController.
 *
 * Admin DLH (super admin) boleh membuat periode dan menugaskan petugas.
 * Sub-admin desa hanya melihat periode yang mencakup desanya.
 */
class B3Controller extends Controller
{
    public function index(Request $request)
    {
        $desaTerkunci = $this->desaTerkunci();
        $bisaKelola = $this->bisaKelola();

        $query = B3Periode::with(['desa.kecamatan', 'petugas']);

        if ($desaTerkunci) {
            $query->whereHas('desa', fn ($d) => $d->where('desa.id_desa', $desaTerkunci));
        }

        // Kolom pencarian di atas halaman: cocokkan nama desa atau kecamatan dalam rute
        if ($request->filled('q')) {
            $kata = trim($request->query('q'));
            $query->whereHas('desa', function ($d) use ($kata) {
                $d->where('nama_desa', 'like', "%{$kata}%")
                  ->orWhereHas('kecamatan', fn ($k) => $k->where('nama_kecamatan', 'like', "%{$kata}%"));
            });
        }

        $periodes = $query->orderByDesc('tgl_buka')->get();
        $terdaftar = $this->hitungTerdaftar($periodes->pluck('id_periode')->all());

        // Data untuk form Buat Periode hanya diperlukan oleh yang boleh mengelola
        $kecamatans = collect();
        $petugasDlh = collect();
        if ($bisaKelola) {
            $kecamatans = Kecamatan::with(['desas' => fn ($d) => $d->orderBy('nama_desa')])
                ->orderBy('nama_kecamatan')
                ->get();

            $petugasDlh = Petugas::where('level', 'petugas_dlh')
                ->orderBy('nama_lengkap')
                ->get(['id_petugas', 'nama_lengkap']);
        }

        return view('admin.bank-sampah.B3.Index', compact(
            'periodes',
            'terdaftar',
            'kecamatans',
            'petugasDlh',
            'bisaKelola'
        ) + ['tab' => 'periode']);
    }

    /**
     * Simpan periode baru beserta desa-desa dalam rutenya.
     */
    public function store(Request $request)
    {
        $this->pastikanBolehKelola();

        $data = $request->validate([
            'id_desa'    => ['required', 'array', 'min:1'],
            'id_desa.*'  => ['integer', 'exists:desa,id_desa'],
            'tgl_buka'   => ['required', 'date'],
            'tgl_tutup'  => ['required', 'date', 'after_or_equal:tgl_buka'],
            'tgl_jemput' => ['required', 'date', 'after_or_equal:tgl_tutup'],
            'berat_min'  => ['required', 'numeric', 'min:0'],
            'berat_max'  => ['required', 'numeric', 'gte:berat_min'],
            'id_petugas' => [
                'nullable',
                'integer',
                Rule::exists('petugas', 'id_petugas')->where('level', 'petugas_dlh'),
            ],
        ], [
            'id_desa.required'          => 'Pilih minimal satu desa.',
            'tgl_tutup.after_or_equal'  => 'Tanggal tutup tidak boleh sebelum tanggal buka.',
            'tgl_jemput.after_or_equal' => 'Tanggal penjemputan tidak boleh sebelum tanggal tutup.',
            'berat_max.gte'             => 'Berat maksimal tidak boleh lebih kecil dari berat minimal.',
            'id_petugas.exists'         => 'Petugas yang dipilih bukan Petugas DLH.',
        ]);

        // Satu desa tidak boleh masuk dua periode yang masa pendaftarannya bertumpuk
        $bentrok = B3Periode::whereNull('selesai_at')
            ->where('tgl_buka', '<=', $data['tgl_tutup'])
            ->where('tgl_tutup', '>=', $data['tgl_buka'])
            ->whereHas('desa', fn ($d) => $d->whereIn('desa.id_desa', $data['id_desa']))
            ->with(['desa' => fn ($d) => $d->whereIn('desa.id_desa', $data['id_desa'])])
            ->first();

        if ($bentrok) {
            return back()->withInput()->with(
                'error',
                'Desa ' . $bentrok->desa->pluck('nama_desa')->join(', ')
                . ' sudah masuk periode lain yang masa pendaftarannya bertumpuk.'
            );
        }

        DB::transaction(function () use ($data) {
            $periode = B3Periode::create([
                'tgl_buka'   => $data['tgl_buka'],
                'tgl_tutup'  => $data['tgl_tutup'],
                'tgl_jemput' => $data['tgl_jemput'],
                'berat_min'  => $data['berat_min'],
                'berat_max'  => $data['berat_max'],
                'id_petugas' => $data['id_petugas'] ?? null,
            ]);
            $periode->desa()->sync($data['id_desa']);
        });

        return redirect()
            ->route('admin.bank-sampah.b3.periode.index')
            ->with('success', 'Periode berhasil dibuat.');
    }

    /**
     * Tugaskan petugas DLH ke satu periode (tombol "Tugaskan" pada tabel).
     */
    public function tugaskan(Request $request, $id)
    {
        $this->pastikanBolehKelola();

        $data = $request->validate([
            'id_petugas' => [
                'required',
                'integer',
                Rule::exists('petugas', 'id_petugas')->where('level', 'petugas_dlh'),
            ],
        ]);

        $periode = B3Periode::findOrFail($id);

        if ($periode->id_petugas) {
            return back()->with('error', 'Periode ini sudah punya petugas.');
        }

        $periode->update(['id_petugas' => $data['id_petugas']]);

        // TODO (fitur Notifikasi B3): kirim notifikasi ke petugas yang ditugaskan.

        return back()->with('success', 'Petugas berhasil ditugaskan.');
    }

    // ───────────────────────── helper ─────────────────────────

    /** id_desa milik sub-admin desa; null untuk admin DLH (melihat semua desa). */
    private function desaTerkunci()
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        return ($admin && $admin->isSubAdminDesa() && $admin->id_desa) ? $admin->id_desa : null;
    }

    /** Hanya admin DLH yang boleh membuat periode / menugaskan petugas. */
    private function bisaKelola(): bool
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        return ! ($admin && $admin->isSubAdminDesa());
    }

    private function pastikanBolehKelola(): void
    {
        abort_unless($this->bisaKelola(), 403, 'Hanya admin DLH yang dapat mengelola periode.');
    }

    /**
     * Jumlah pendaftar per periode per desa: [id_periode => [id_desa => jumlah]].
     * Dijaga Schema::hasTable supaya halaman tetap terbuka walau tabel pendaftaran belum dimigrasi.
     */
    private function hitungTerdaftar(array $idPeriode): array
    {
        $tabel = (new B3Pendaftaran)->getTable();

        if (empty($idPeriode) || ! Schema::hasTable($tabel)) {
            return [];
        }

        $hasil = [];
        $rows = B3Pendaftaran::query()
            ->selectRaw('id_periode, id_desa, COUNT(*) as jumlah')
            ->whereIn('id_periode', $idPeriode)
            ->groupBy('id_periode', 'id_desa')
            ->get();

        foreach ($rows as $r) {
            $hasil[$r->id_periode][$r->id_desa] = (int) $r->jumlah;
        }

        return $hasil;
    }
}