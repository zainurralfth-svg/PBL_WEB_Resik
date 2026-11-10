<?php

namespace App\Http\Controllers\BankSampah;

use App\Http\Controllers\Controller;
use App\Models\B3Pendaftaran;
use App\Models\Desa;
use App\Models\Petugas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * Tab "Pendaftaran" pada halaman B3: Penjemputan Berperiode.
 */
class B3PendaftaranController extends Controller
{
    public function index(Request $request)
    {
        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();
        $desaTerkunci = ($admin && $admin->isSubAdminDesa() && $admin->id_desa)
            ? $admin->id_desa
            : null;

        $desas = Desa::when($desaTerkunci, fn ($q) => $q->where('id_desa', $desaTerkunci))
            ->orderBy('nama_desa')
            ->get(['id_desa', 'nama_desa']);

        // Tabel B3 belum dibuat (masih dikerjakan teman): tampilkan halaman kosong + pemberitahuan,
        // bukan error. Hapus blok ini kalau tabel sudah ada dan tidak diperlukan lagi.
        if (! Schema::hasTable('b3_pendaftarans')) {
            return view('admin.bank-sampah.B3.Index', [
                'tab' => 'pendaftaran',
                'pendaftarans' => new LengthAwarePaginator([], 0, 15),
                'desas' => $desas,
                'petugasDlh' => collect(),
                'tabelBelumAda' => true,
            ]);
        }

        $query = B3Pendaftaran::with(['masyarakat', 'pns', 'desa', 'petugas', 'items'])
            ->untukDesa($desaTerkunci);

        // Filter periode hanya kalau dikirim lewat URL (?periode=ID); tab Periode milik temanmu
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
                  ->orWhereHas('desa', fn ($d) => $d->where('nama_desa', 'like', "%{$kata}%"));
            });
        }

        // Yang perlu ditindaklanjuti tampil paling atas
        $pendaftarans = $query
            ->orderByRaw("FIELD(status, 'terdaftar', 'dijadwalkan', 'menunggu_verifikasi', 'diverifikasi')")
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $petugasDlh = Petugas::where('level', 'petugas_dlh')
            ->orderBy('nama_lengkap')
            ->get(['id_petugas', 'nama_lengkap']);

        return view('admin.bank-sampah.B3.Index', compact('pendaftarans', 'desas', 'petugasDlh') + ['tab' => 'pendaftaran', 'tabelBelumAda' => false]);
    }

    /**
     * Tugaskan petugas DLH. Status: terdaftar -> dijadwalkan.
     */
    public function tugaskan(Request $request, $id)
    {
        $data = $request->validate([
            'id_petugas' => [
                'required',
                'integer',
                Rule::exists('petugas', 'id_petugas')->where('level', 'petugas_dlh'),
            ],
        ]);

        /** @var \App\Models\Admin|null $admin */
        $admin = Auth::guard('admin')->user();

        $berhasil = DB::transaction(function () use ($id, $data, $admin) {
            // Kunci baris supaya dua admin tidak menugaskan data yang sama bersamaan
            $pendaftaran = B3Pendaftaran::lockForUpdate()->findOrFail($id);

            if ($admin && $admin->isSubAdminDesa() && $admin->id_desa != $pendaftaran->id_desa) {
                abort(403, 'Pendaftaran ini bukan di desa Anda.');
            }

            if ($pendaftaran->status !== B3Pendaftaran::TERDAFTAR) {
                return false;
            }

            $pendaftaran->update([
                'id_petugas' => $data['id_petugas'],
                'status' => B3Pendaftaran::DIJADWALKAN,
            ]);

            return true;
        });

        if (! $berhasil) {
            return back()->with('error', 'Pendaftaran ini sudah ditugaskan sebelumnya.');
        }

        return back()->with('success', 'Petugas berhasil ditugaskan.');
    }
}