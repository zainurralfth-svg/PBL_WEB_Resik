<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\B3Pendaftaran;
use App\Models\B3Periode;
use App\Models\Petugas;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * API aplikasi petugas untuk penjemputan B3.
 *
 * Pemetaan status DB -> aplikasi:
 *   terdaftar / dijadwalkan            -> 'pending'  (belum dijemput)
 *   menunggu_verifikasi / diverifikasi -> 'selesai'  (sudah dijemput)
 *
 * Petugas hanya menandai "dijemput" (-> menunggu_verifikasi).
 * Status 'diverifikasi' hanya boleh diset admin di web (B3VerifikasiController).
 */
class B3PetugasController extends Controller
{
    private const SUDAH_DIJEMPUT = [
        B3Pendaftaran::MENUNGGU_VERIFIKASI,
        B3Pendaftaran::DIVERIFIKASI,
    ];

    // ───────────────────────── helper ─────────────────────────

    /**
     * Petugas yang sedang login. Prioritas: token Sanctum (kalau ada),
     * lalu parameter id_petugas (sama seperti route petugas lain di api.php).
     */
    private function idPetugas(Request $request): ?int
    {
        $user = $request->user('sanctum');
        if ($user instanceof Petugas) {
            return (int) $user->id_petugas;
        }

        $id = $request->input('id_petugas');

        return is_numeric($id) ? (int) $id : null;
    }

    private function tanpaPetugas(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'id_petugas wajib dikirim.',
        ], 422);
    }

    /**
     * Semua pendaftaran yang menjadi tugas petugas ini pada periode yang belum selesai:
     *  - ditugaskan langsung ke petugas (b3_pendaftarans.id_petugas), atau
     *  - belum ditugaskan per orang, tapi periodenya ditugaskan ke petugas (b3_periode.id_petugas).
     */
    private function tugasQuery(int $idPetugas)
    {
        return B3Pendaftaran::query()
            ->whereHas('periode', fn ($p) => $p->whereNull('selesai_at'))
            ->where(function ($q) use ($idPetugas) {
                $q->where('id_petugas', $idPetugas)
                  ->orWhere(function ($x) use ($idPetugas) {
                      $x->whereNull('id_petugas')
                        ->whereHas('periode', fn ($p) => $p->where('id_petugas', $idPetugas));
                  });
            });
    }

    private function statusApp(string $statusDb): string
    {
        return in_array($statusDb, self::SUDAH_DIJEMPUT, true) ? 'selesai' : 'pending';
    }

    private function namaWilayah($tugas): string
    {
        $kecamatan = $tugas
            ->map(fn ($t) => $t->desa?->kecamatan?->nama_kecamatan)
            ->filter()
            ->unique()
            ->values();

        return $kecamatan->isEmpty() ? 'Belum ada rute' : 'Kec. ' . $kecamatan->join(', ');
    }

    private function formatTugas(B3Pendaftaran $t): array
    {
        $warga = $t->masyarakat ?? $t->pns;

        return [
            'id'                 => $t->id,
            'status'             => $this->statusApp($t->status),
            'status_db'          => $t->status,
            // dipertahankan agar layar Flutter lama (masyarakat.name) tetap jalan
            'masyarakat'         => ['name' => $t->nama_pendaftar],
            'no_telepon'         => $warga->no_telepon ?? null,
            'alamat'             => $warga->alamat ?? null,
            'id_desa'            => $t->id_desa,
            'nama_desa'          => $t->nama_desa,
            'est_berat'          => (float) $t->est_berat,
            'berat_dijemput'     => $t->berat_dijemput !== null ? (float) $t->berat_dijemput : null,
            'catatan_verifikasi' => $t->catatan_verifikasi,
            // path relatif (mis. "b3/b3_1_2026....jpg"); di Flutter pakai ApiConfig.imageUrl()
            'foto_bukti'         => $t->foto_bukti,
            'tgl_dijemput'       => $t->tgl_dijemput?->toIso8601String(),
            'items'              => $t->items
                ->map(fn ($i) => [
                    'id'             => $i->id,
                    'jenis_b3'       => $i->jenis_b3,
                    'jumlah'         => (int) $i->jumlah,
                    'jumlah_petugas' => $i->jumlah_petugas !== null ? (int) $i->jumlah_petugas : null,
                ])
                ->values(),
        ];
    }

    // ───────────────────────── endpoint ─────────────────────────

    /** GET /petugas/b3-tasks?id_petugas=..&id_desa=.. */
    public function index(Request $request)
    {
        $idPetugas = $this->idPetugas($request);
        if (! $idPetugas) {
            return $this->tanpaPetugas();
        }

        $query = $this->tugasQuery($idPetugas)->with(['items', 'masyarakat', 'pns', 'desa']);

        if ($request->filled('id_desa')) {
            $query->where('id_desa', (int) $request->query('id_desa'));
        }

        $tasks = $query->orderBy('id')->get()
            ->map(fn ($t) => $this->formatTugas($t))
            ->sortBy(fn ($t) => $t['status'] === 'selesai' ? 1 : 0) // yang belum dijemput di atas
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil data tugas B3',
            'data'    => $tasks,
        ], 200);
    }

    /** GET /petugas/b3-ringkasan?id_petugas=.. (kartu "Penjemputan B3 Hari Ini") */
    public function getRingkasanB3(Request $request)
    {
        $idPetugas = $this->idPetugas($request);
        if (! $idPetugas) {
            return $this->tanpaPetugas();
        }

        $tugas = $this->tugasQuery($idPetugas)->with('desa.kecamatan')->get();

        return response()->json([
            'success' => true,
            'data'    => [
                'dijemput'    => $tugas->whereIn('status', self::SUDAH_DIJEMPUT)->count(),
                'total_warga' => $tugas->count(),
                'total_berat' => round((float) $tugas->sum('est_berat'), 1),
                'wilayah'     => $this->namaWilayah($tugas),
            ],
        ], 200);
    }

    /** GET /petugas/b3-rute?id_petugas=.. */
    public function getRuteDesa(Request $request)
    {
        $idPetugas = $this->idPetugas($request);
        if (! $idPetugas) {
            return $this->tanpaPetugas();
        }

        $tugas = $this->tugasQuery($idPetugas)->with(['desa.kecamatan', 'periode'])->get();

        $listDesa = $tugas->groupBy('id_desa')->map(function ($grup) {
            $total    = $grup->count();
            $dijemput = $grup->whereIn('status', self::SUDAH_DIJEMPUT)->count();

            return [
                'id_desa'          => $grup->first()->id_desa,
                'nama_desa'        => $grup->first()->nama_desa,
                'total_warga_desa' => $total,
                'dijemput_desa'    => $dijemput,
                'est_berat_desa'   => round((float) $grup->sum('est_berat'), 1),
                'status_rute'      => ($total > 0 && $dijemput >= $total) ? 'Selesai' : 'Dijadwalkan',
            ];
        })->sortBy('nama_desa')->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'wilayah'     => $this->namaWilayah($tugas),
                'total_warga' => $tugas->count(),
                'total_berat' => round((float) $tugas->sum('est_berat'), 1),
                'dijemput'    => $tugas->whereIn('status', self::SUDAH_DIJEMPUT)->count(),
                // tanggal jemput paling awal di antara periode aktif petugas (format Y-m-d)
                'jadwal_jemput' => $tugas->map(fn ($t) => $t->periode?->tgl_jemput?->toDateString())->filter()->min(),
                'list_desa'   => $listDesa,
            ],
        ], 200);
    }

    /**
     * PUT|POST /petugas/b3-tasks/{id}/status
     * Body (JSON atau multipart/form-data):
     *   id_petugas, status=selesai, berat_dijemput (atau est_berat, kompatibilitas), catatan_verifikasi,
     *   items = [{id, jumlah}, ...]  jumlah aktual per jenis (di multipart dikirim sebagai string JSON),
     *   foto  = file gambar bukti penjemputan (opsional di API; diwajibkan di aplikasi).
     * Hasil: status -> menunggu_verifikasi (verifikasi akhir tetap oleh admin di web).
     * Rute POST disediakan karena PHP tidak membaca multipart pada method PUT.
     */
    public function updateStatus(Request $request, $id)
    {
        $idPetugas = $this->idPetugas($request);
        if (! $idPetugas) {
            return $this->tanpaPetugas();
        }

        $items = $request->input('items');
        if (is_string($items)) {
            $decoded = json_decode($items, true);
            $items = is_array($decoded) ? $decoded : $items; // string rusak -> gagal validasi 'array'
        }

        $input = [
            'status'  => $request->input('status', 'selesai'),
            // Flutter lama mengirim berat aktual lewat field est_berat
            'berat'   => $request->input('berat_dijemput', $request->input('est_berat')),
            'catatan' => $request->input('catatan_verifikasi'),
            'items'   => $items,
            'foto'    => $request->file('foto'),
        ];

        $validator = Validator::make($input, [
            'status'         => ['in:selesai'],
            'berat'          => ['required', 'numeric', 'gt:0', 'max:99999'],
            'catatan'        => ['nullable', 'string', 'max:500'],
            'items'          => ['nullable', 'array'],
            'items.*.id'     => ['required', 'integer'],
            'items.*.jumlah' => ['required', 'integer', 'min:0', 'max:99999'],
            'foto'           => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ], [
            'status.in'          => 'Status tidak valid.',
            'berat.required'     => 'Berat aktual wajib diisi.',
            'berat.numeric'      => 'Berat aktual harus berupa angka.',
            'berat.gt'           => 'Berat aktual harus lebih dari 0.',
            'catatan.max'        => 'Catatan maksimal 500 karakter.',
            'items.array'        => 'Format jumlah per jenis tidak valid.',
            'items.*.jumlah.*'   => 'Jumlah per jenis harus bilangan bulat 0 atau lebih.',
            'foto.image'         => 'File bukti harus berupa gambar.',
            'foto.mimes'         => 'Foto bukti harus berformat jpg, jpeg, png, atau webp.',
            'foto.max'           => 'Ukuran foto bukti maksimal 5 MB.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors'  => $validator->errors(),
            ], 422);
        }

        $hasil = DB::transaction(function () use ($id, $idPetugas, $input, $request) {
            $p = B3Pendaftaran::lockForUpdate()->find($id);

            if (! $p || ! $this->tugasQuery($idPetugas)->whereKey($p->id)->exists()) {
                return ['kode' => 404, 'pesan' => 'Data penjemputan tidak ditemukan.'];
            }

            if ($p->status === B3Pendaftaran::DIVERIFIKASI) {
                return ['kode' => 409, 'pesan' => 'Data sudah diverifikasi admin dan tidak bisa diubah lagi.'];
            }

            // Jumlah aktual per jenis -> b3_pendaftaran_items.jumlah_petugas
            $baris = collect($input['items'] ?? []);
            if ($baris->isNotEmpty()) {
                $idItem = $baris->pluck('id')->map(fn ($v) => (int) $v)->unique();
                if ($p->items()->whereIn('id', $idItem)->count() !== $idItem->count()) {
                    return ['kode' => 422, 'pesan' => 'Ada jenis barang yang bukan milik pendaftaran ini.'];
                }
                foreach ($baris as $row) {
                    $p->items()->where('id', (int) $row['id'])->update([
                        'jumlah_petugas' => (int) $row['jumlah'],
                        'updated_at'     => now(),
                    ]);
                }
            }

            // Foto bukti -> public/uploads/b3 (sama seperti folder uploads lain; URL lewat ApiConfig.imageUrl)
            $fotoPath = $p->foto_bukti;
            if ($foto = $request->file('foto')) {
                $nama = 'b3_' . $p->id . '_' . now()->format('YmdHis') . '.' . ($foto->guessExtension() ?: 'jpg');
                $foto->move($this->uploadDir('b3'), $nama);
                if ($p->foto_bukti) {
                    @unlink($this->uploadDir($p->foto_bukti));
                }
                $fotoPath = 'b3/' . $nama;
            }

            // forceFill: tidak bergantung pada $fillable model
            $p->forceFill([
                'status'             => B3Pendaftaran::MENUNGGU_VERIFIKASI,
                'id_petugas'         => $idPetugas,
                'berat_dijemput'     => $input['berat'],
                'tgl_dijemput'       => now(),
                'catatan_verifikasi' => $input['catatan'],
                'foto_bukti'         => $fotoPath,
            ])->save();

            return ['kode' => 200, 'data' => $p->fresh(['items', 'masyarakat', 'pns', 'desa'])];
        });

        if ($hasil['kode'] !== 200) {
            return response()->json(['success' => false, 'message' => $hasil['pesan']], $hasil['kode']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Status penjemputan berhasil diperbarui',
            'data'    => $this->formatTugas($hasil['data']),
        ], 200);
    }

    /**
     * GET /petugas/b3-riwayat?id_petugas=..
     * Semua penjemputan yang pernah dikerjakan petugas ini (termasuk periode yang sudah selesai),
     * terbaru di atas. Dipakai layar Riwayat.
     */
    public function riwayat(Request $request)
    {
        $idPetugas = $this->idPetugas($request);
        if (! $idPetugas) {
            return $this->tanpaPetugas();
        }

        $data = B3Pendaftaran::query()
            ->where('id_petugas', $idPetugas)
            ->whereIn('status', self::SUDAH_DIJEMPUT)
            ->with(['items', 'masyarakat', 'pns', 'desa'])
            ->orderByDesc('tgl_dijemput')
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn ($t) => $this->formatTugas($t))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'Berhasil mengambil riwayat penjemputan B3',
            'data'    => $data,
        ], 200);
    }

    /**
     * GET /petugas/b3-muatan?id_petugas=.. (layar Ringkasan Muatan)
     * Hanya menghitung yang sudah dijemput. Muatan per jenis dalam jumlah item (pcs),
     * karena berat tersimpan per pendaftar, bukan per jenis.
     */
    public function getMuatan(Request $request)
    {
        $idPetugas = $this->idPetugas($request);
        if (! $idPetugas) {
            return $this->tanpaPetugas();
        }

        $tugas   = $this->tugasQuery($idPetugas)->with('items')->get();
        $selesai = $tugas->whereIn('status', self::SUDAH_DIJEMPUT);

        $perJenis = $selesai
            ->flatMap(fn ($t) => $t->items->map(fn ($i) => [
                'jenis'  => $i->jenis_b3,
                'id'     => $t->id,
                'jumlah' => (int) ($i->jumlah_petugas ?? $i->jumlah),
            ]))
            ->groupBy('jenis')
            ->map(fn ($g, $jenis) => [
                'jenis'  => $jenis,
                'warga'  => $g->pluck('id')->unique()->count(),
                'jumlah' => $g->sum('jumlah'),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'dijemput'     => $selesai->count(),
                'total_warga'  => $tugas->count(),
                'berat_aktual' => round((float) $selesai->sum(fn ($t) => (float) $t->berat_dijemput), 1),
                'muatan'       => $perJenis,
            ],
        ], 200);
    }

    /**
     * POST /petugas/b3-rute/selesai  (tombol "Selesaikan Rute")
     * Ditolak selama masih ada warga yang belum dijemput. Periode ditandai selesai
     * (selesai_at) hanya jika tidak ada lagi pendaftaran terdaftar/dijadwalkan di periode itu
     * dan masa pendaftarannya sudah ditutup.
     */
    public function selesaikanRute(Request $request)
    {
        $idPetugas = $this->idPetugas($request);
        if (! $idPetugas) {
            return $this->tanpaPetugas();
        }

        $tugas = $this->tugasQuery($idPetugas)->get();

        if ($tugas->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Tidak ada rute aktif.'], 422);
        }

        $sisa = $tugas->whereNotIn('status', self::SUDAH_DIJEMPUT)->count();
        if ($sisa > 0) {
            return response()->json([
                'success' => false,
                'message' => "Masih ada {$sisa} warga yang belum dijemput.",
            ], 409);
        }

        $today = Carbon::today()->toDateString();

        foreach ($tugas->pluck('id_periode')->unique() as $idPeriode) {
            $masihAda = B3Pendaftaran::where('id_periode', $idPeriode)
                ->whereIn('status', [B3Pendaftaran::TERDAFTAR, B3Pendaftaran::DIJADWALKAN])
                ->exists();

            if (! $masihAda) {
                B3Periode::where('id_periode', $idPeriode)
                    ->whereNull('selesai_at')
                    ->whereDate('tgl_tutup', '<=', $today)
                    ->update(['selesai_at' => now()]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Rute berhasil diselesaikan.',
        ], 200);
    }
}