<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\B3Pendaftaran;
use App\Models\B3Periode;
use App\Models\Masyarakat;
use App\Models\Pns;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * API B3 untuk warga (aplikasi mobile).
 * Bentuk JSON mengikuti kontrak di repo mobile: lib/models/b3_model.dart
 */
class B3WargaController extends Controller
{
    /**
     * GET /api/b3/periode-aktif?id_desa=..
     *
     * Periode yang dibuat admin di web (tab Periode) untuk desa warga:
     * yang sedang dibuka, atau yang akan dibuka paling dekat.
     * Periode yang pendaftarannya sudah ditutup atau sudah selesai tidak dikirim.
     *
     * data = null berarti belum ada periode -> mobile menampilkan "Belum ada periode B3".
     */
    public function periodeAktif(Request $request)
    {
        $idDesa = (int) $request->query('id_desa');

        if ($idDesa <= 0) {
            return $this->kirim(null);
        }

        $periode = B3Periode::with('desa.kecamatan')
            ->whereNull('selesai_at')
            ->whereDate('tgl_tutup', '>=', Carbon::today())
            ->whereHas('desa', fn ($d) => $d->where('desa.id_desa', $idDesa))
            ->orderBy('tgl_buka')
            ->first();

        if (! $periode) {
            return $this->kirim(null);
        }

        return $this->kirim([
            'id_periode' => $periode->id_periode,
            'kecamatan'  => $periode->desa->pluck('kecamatan.nama_kecamatan')
                ->filter()->unique()->values()->join(', '),
            'desa'       => $periode->desa->pluck('nama_desa')->values(),
            'tgl_buka'   => $periode->tgl_buka->toDateString(),
            'tgl_tutup'  => $periode->tgl_tutup->toDateString(),
            'tgl_jemput' => $periode->tgl_jemput->toDateString(),
            'berat_min'  => (float) $periode->berat_min,
            'berat_max'  => (float) $periode->berat_max,
            'status'     => $periode->status, // Dijadwalkan | Dibuka
        ]);
    }

    /**
     * GET /api/b3/riwayat?id_masyarakat=1   (atau ?id_pns=1)
     * Dipakai layar "Riwayat B3".
     */
    public function riwayat(Request $request)
    {
        $idMasyarakat = (int) $request->query('id_masyarakat');
        $idPns = (int) $request->query('id_pns');

        if ($idMasyarakat <= 0 && $idPns <= 0) {
            return $this->gagal('id_masyarakat atau id_pns wajib diisi', 422);
        }

        $rows = B3Pendaftaran::with(['periode', 'items'])
            ->when(
                $idMasyarakat > 0,
                fn ($q) => $q->where('id_masyarakat', $idMasyarakat),
                fn ($q) => $q->where('id_pns', $idPns)
            )
            ->orderByDesc('id')
            ->get()
            ->sortByDesc(fn ($b) => $this->tanggalJemput($b)->timestamp)
            ->values();

        return $this->kirim($rows->map(fn ($b) => $this->formatRiwayat($b))->values());
    }

    /**
     * GET /api/b3/{id}   (opsional: ?id_masyarakat=1 atau ?id_pns=1 untuk cek kepemilikan)
     * Dipakai layar "Detail Pendaftaran".
     */
    public function show(Request $request, int $id)
    {
        $b = B3Pendaftaran::with(['periode', 'items', 'petugas', 'masyarakat', 'pns'])->find($id);

        if (! $b) {
            return $this->gagal('Data tidak ditemukan', 404);
        }

        $idMasyarakat = (int) $request->query('id_masyarakat');
        $idPns = (int) $request->query('id_pns');

        if (($idMasyarakat > 0 && (int) $b->id_masyarakat !== $idMasyarakat)
            || ($idPns > 0 && (int) $b->id_pns !== $idPns)) {
            return $this->gagal('Data tidak ditemukan', 404);
        }

        return $this->kirim($this->formatDetail($b));
    }

    /**
     * POST /api/b3
     * Pendaftaran B3 baru. Body JSON:
     * {
     *   "id_periode": 3,
     *   "id_masyarakat": 12,            // atau "id_pns"
     *   "est_berat": 4,
     *   "items": [ {"jenis_b3": "Baterai bekas", "jumlah": 20}, ... ]
     * }
     */
    public function store(Request $request)
    {
        $v = Validator::make($request->all(), [
            'id_periode'         => 'required|integer',
            'id_masyarakat'      => 'required_without:id_pns|nullable|integer',
            'id_pns'             => 'required_without:id_masyarakat|nullable|integer',
            'est_berat'          => 'required|numeric|min:0.1',
            'items'              => 'required|array|min:1',
            'items.*.jenis_b3'   => 'required|string|max:100',
            'items.*.jumlah'     => 'required|integer|min:1',
        ]);

        if ($v->fails()) {
            return $this->gagal('Validasi gagal', 422, $v->errors());
        }

        $pemilik = $request->filled('id_masyarakat')
            ? Masyarakat::find($request->id_masyarakat)
            : Pns::find($request->id_pns);

        if (! $pemilik) {
            return $this->gagal('Pengguna tidak ditemukan', 404);
        }

        $idDesa = (int) $pemilik->id_desa;
        $hariIni = Carbon::today();

        $periode = B3Periode::whereNull('selesai_at')
            ->whereDate('tgl_buka', '<=', $hariIni)
            ->whereDate('tgl_tutup', '>=', $hariIni)
            ->whereHas('desa', fn ($d) => $d->where('desa.id_desa', $idDesa))
            ->where('id_periode', $request->id_periode)
            ->first();

        if (! $periode) {
            return $this->gagal('Periode B3 tidak ditemukan atau pendaftaran sudah ditutup', 422);
        }

        $berat = (float) $request->est_berat;
        if ($berat < (float) $periode->berat_min || $berat > (float) $periode->berat_max) {
            return $this->gagal(
                'Estimasi berat harus antara ' . (float) $periode->berat_min
                . ' dan ' . (float) $periode->berat_max . ' kg',
                422
            );
        }

        $pendaftaran = DB::transaction(function () use ($request, $periode, $idDesa, $berat) {
            $p = B3Pendaftaran::create([
                'id_periode'    => $periode->id_periode,
                'id_masyarakat' => $request->filled('id_masyarakat') ? $request->id_masyarakat : null,
                'id_pns'        => $request->filled('id_pns') ? $request->id_pns : null,
                'id_desa'       => $idDesa ?: null,
                'est_berat'     => $berat,
                'status'        => B3Pendaftaran::TERDAFTAR,
            ]);

            foreach ($request->items as $item) {
                $p->items()->create([
                    'jenis_b3' => trim($item['jenis_b3']),
                    'jumlah'   => (int) $item['jumlah'],
                ]);
            }

            return $p;
        });

        $pendaftaran->load(['periode', 'items', 'petugas', 'masyarakat', 'pns']);

        return response()->json([
            'success' => true,
            'status'  => 'success',
            'message' => 'Pendaftaran B3 berhasil',
            'data'    => $this->formatDetail($pendaftaran),
        ], 201);
    }

    // ==================== FORMAT RESPONS (cocok dengan layar Flutter) ====================

    private function formatRiwayat(B3Pendaftaran $b): array
    {
        $tgl = $this->tanggalJemput($b);
        $status = $this->statusApi($b);

        return [
            'id'                  => $b->id,
            'tanggal'             => $tgl->toDateString(),
            'periode'             => $tgl->copy()->locale('id')->isoFormat('MMMM'), // "Oktober"
            'jenis'               => $this->jenisText($b),
            'estimasi_berat'      => $this->angka($b->est_berat),
            'berat_terverifikasi' => $this->angka($b->berat_verifikasi),
            'status'              => $status,
            'alasan'              => $status === 'tidak_sesuai' ? $b->alasan_tidak_sesuai : null,
        ];
    }

    private function formatDetail(B3Pendaftaran $b): array
    {
        $status = $this->statusApi($b);
        $pemilik = $b->masyarakat ?? $b->pns;

        return [
            'id'                  => $b->id,
            'no_pendaftaran'      => sprintf('#B3-%05d', $b->id),
            'jenis'               => $this->jenisText($b),
            'estimasi_berat'      => $this->angka($b->est_berat),
            'berat_dijemput'      => $this->angka($b->berat_dijemput),
            'berat_terverifikasi' => $this->angka($b->berat_verifikasi),
            'alamat'              => $pemilik->alamat ?? null,
            'jadwal_jemput'       => $this->fmt($this->tanggalJemput($b), 'dddd, D MMM YYYY'),
            'petugas'             => $b->petugas->nama_lengkap ?? null,
            'tanggal_daftar'      => $this->fmt($b->created_at, 'D MMM YYYY · HH:mm'),
            'tanggal_dijadwalkan' => $this->fmt($b->tgl_dijadwalkan, 'D MMM YYYY'),
            'tanggal_dijemput'    => $this->fmt($b->tgl_dijemput, 'D MMM YYYY'),
            'tanggal_diperiksa'   => $this->fmt($b->tgl_verifikasi, 'D MMM YYYY'),
            'status'              => $status,
            'alasan'              => $status === 'tidak_sesuai' ? $b->alasan_tidak_sesuai : null,
        ];
    }

    /**
     * Status untuk mobile. Di database tidak ada status "tidak_sesuai":
     * pendaftaran yang ditandai tidak sesuai oleh admin punya alasan_tidak_sesuai
     * dan belum berstatus diverifikasi.
     */
    private function statusApi(B3Pendaftaran $b): string
    {
        if (! empty($b->alasan_tidak_sesuai) && $b->status !== B3Pendaftaran::DIVERIFIKASI) {
            return 'tidak_sesuai';
        }

        return $b->status; // terdaftar | dijadwalkan | menunggu_verifikasi | diverifikasi
    }

    /** Tanggal jemput dari periode; jika periode tidak ada, pakai tanggal daftar. */
    private function tanggalJemput(B3Pendaftaran $b): Carbon
    {
        return Carbon::parse($b->periode->tgl_jemput ?? $b->created_at);
    }

    /** "Baterai bekas, Lampu neon" */
    private function jenisText(B3Pendaftaran $b): string
    {
        $teks = $b->items->pluck('jenis_b3')->filter()->unique()->implode(', ');

        return $teks !== '' ? $teks : '-';
    }

    /** 4.00 -> 4, 1.50 -> 1.5, null -> null (agar tampil "4 kg" di aplikasi) */
    private function angka($v)
    {
        if ($v === null) {
            return null;
        }

        $f = (float) $v;

        return floor($f) == $f ? (int) $f : $f;
    }

    /** Format tanggal bahasa Indonesia, mis. "Kamis, 29 Okt 2026" */
    private function fmt($date, string $format): ?string
    {
        return $date ? Carbon::parse($date)->locale('id')->isoFormat($format) : null;
    }

    /** `success` dipakai B3Service, `status` dipakai layar B3 lain yang sudah ada. */
    private function kirim($data)
    {
        return response()->json([
            'success' => true,
            'status'  => 'success',
            'data'    => $data,
        ]);
    }

    private function gagal(string $pesan, int $kode, $errors = null)
    {
        $body = ['success' => false, 'status' => 'error', 'message' => $pesan];
        if ($errors) {
            $body['errors'] = $errors;
        }

        return response()->json($body, $kode);
    }
}