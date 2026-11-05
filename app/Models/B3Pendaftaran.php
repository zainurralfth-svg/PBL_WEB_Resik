<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class B3Pendaftaran extends Model
{
    public const TERDAFTAR = 'terdaftar';
    public const DIJADWALKAN = 'dijadwalkan';
    public const MENUNGGU_VERIFIKASI = 'menunggu_verifikasi';
    public const DIVERIFIKASI = 'diverifikasi';

    public const STATUS_LABEL = [
        self::TERDAFTAR => 'Terdaftar',
        self::DIJADWALKAN => 'Dijadwalkan',
        self::MENUNGGU_VERIFIKASI => 'Menunggu verifikasi',
        self::DIVERIFIKASI => 'Diverifikasi',
    ];

    protected $table = 'b3_pendaftarans';

    protected $fillable = [
        'id_periode',
        'id_masyarakat',
        'id_pns',
        'id_desa',
        'est_berat',
        'status',
        'id_petugas',
    ];

    protected $casts = [
        'est_berat' => 'decimal:2',
    ];

    /** B3Periode dibuat oleh temanmu; relasi ini baru dipakai kalau modelnya sudah ada. */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(B3Periode::class, 'id_periode');
    }

    public function masyarakat(): BelongsTo
    {
        return $this->belongsTo(Masyarakat::class, 'id_masyarakat', 'id_masyarakat');
    }

    public function pns(): BelongsTo
    {
        return $this->belongsTo(Pns::class, 'id_pns', 'id_pns');
    }

    /** Nama pendaftar, baik warga maupun PNS */
    public function getNamaPendaftarAttribute(): string
    {
        return $this->masyarakat->nama ?? $this->pns->nama ?? '-';
    }

    public function desa(): BelongsTo
    {
        return $this->belongsTo(Desa::class, 'id_desa', 'id_desa');
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(Petugas::class, 'id_petugas', 'id_petugas');
    }

    public function items(): HasMany
    {
        return $this->hasMany(B3PendaftaranItem::class, 'id_pendaftaran');
    }

    /** Batasi ke satu desa (untuk admin desa). Kosong = semua desa (super admin). */
    public function scopeUntukDesa($query, $idDesa)
    {
        return $idDesa ? $query->where('id_desa', $idDesa) : $query;
    }

    /** Angka merah di tab Pendaftaran: yang belum ditugaskan */
    public static function jumlahBaru($idDesa = null): int
    {
        if (! Schema::hasTable((new static)->getTable())) {
            return 0;
        }

        return static::untukDesa($idDesa)->where('status', self::TERDAFTAR)->count();
    }

    /** "Baterai bekas x20, Lampu neon x4" */
    public function getItemsTextAttribute(): string
    {
        return $this->items
            ->map(fn ($item) => "{$item->jenis_b3} x{$item->jumlah}")
            ->implode(', ');
    }

    /** "1,2 kg", "2 kg" */
    public function getEstBeratLabelAttribute(): string
    {
        $angka = rtrim(rtrim(number_format((float) $this->est_berat, 1, ',', ''), '0'), ',');

        return $angka . ' kg';
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUS_LABEL[$this->status] ?? $this->status;
    }
}