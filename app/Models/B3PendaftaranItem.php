<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B3PendaftaranItem extends Model
{
    protected $table = 'b3_pendaftaran_items';

    protected $fillable = ['id_pendaftaran', 'jenis_b3', 'jumlah'];

    public function pendaftaran(): BelongsTo
    {
        return $this->belongsTo(B3Pendaftaran::class, 'id_pendaftaran');
    }
}