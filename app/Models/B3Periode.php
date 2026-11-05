<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class B3Periode extends Model
{
    protected $table = 'b3_periode';
    protected $primaryKey = 'id_periode';
    public $incrementing = true;
    public $timestamps = true;

    protected $fillable = [
        'tgl_buka',
        'tgl_tutup',
        'tgl_jemput',
        'berat_min',
        'berat_max',
        'id_petugas',
        'selesai_at',
    ];

    protected $casts = [
        'tgl_buka'   => 'date',
        'tgl_tutup'  => 'date',
        'tgl_jemput' => 'date',
        'berat_min'  => 'decimal:2',
        'berat_max'  => 'decimal:2',
        'selesai_at' => 'datetime',
    ];

    public function desa(): BelongsToMany
    {
        return $this->belongsToMany(
            Desa::class,
            'b3_periode_desa',
            'id_periode',
            'id_desa',
            'id_periode',
            'id_desa'
        );
    }

    public function petugas(): BelongsTo
    {
        return $this->belongsTo(Petugas::class, 'id_petugas', 'id_petugas');
    }

    /**
     * Status dihitung dari tanggal, tidak disimpan, jadi selalu sesuai jadwal.
     * Dijadwalkan (belum buka) -> Dibuka -> Ditutup (menunggu jemput) -> Selesai
     */
    public function getStatusAttribute(): string
    {
        if ($this->selesai_at) {
            return 'Selesai';
        }

        $today = Carbon::today();

        if ($today->lt($this->tgl_buka)) {
            return 'Dijadwalkan';
        }
        if ($today->lte($this->tgl_tutup)) {
            return 'Dibuka';
        }

        return 'Ditutup';
    }
}