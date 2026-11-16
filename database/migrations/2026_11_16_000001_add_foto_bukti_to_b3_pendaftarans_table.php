<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto bukti penjemputan B3 dari aplikasi petugas.
 * Disimpan sebagai path relatif di public/uploads (mis. "b3/b3_1_20261116101500.jpg").
 * Nullable dan dicek dulu, aman dijalankan di DB hasil impor SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('b3_pendaftarans')) {
            return;
        }

        Schema::table('b3_pendaftarans', function (Blueprint $table) {
            if (! Schema::hasColumn('b3_pendaftarans', 'foto_bukti')) {
                $table->string('foto_bukti')->nullable()->after('catatan_verifikasi');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('b3_pendaftarans') && Schema::hasColumn('b3_pendaftarans', 'foto_bukti')) {
            Schema::table('b3_pendaftarans', function (Blueprint $table) {
                $table->dropColumn('foto_bukti');
            });
        }
    }
};