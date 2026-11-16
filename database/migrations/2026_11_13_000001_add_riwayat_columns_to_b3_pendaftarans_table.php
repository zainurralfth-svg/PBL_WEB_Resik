<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom tambahan untuk layar Riwayat & Detail B3 di aplikasi mobile.
 * Semua nullable dan dicek dulu (aman dijalankan di DB hasil impor SQL).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('b3_pendaftarans')) {
            return;
        }

        Schema::table('b3_pendaftarans', function (Blueprint $table) {
            if (! Schema::hasColumn('b3_pendaftarans', 'berat_dijemput')) {
                $table->decimal('berat_dijemput', 8, 2)->nullable()->after('est_berat');
            }
            if (! Schema::hasColumn('b3_pendaftarans', 'berat_verifikasi')) {
                $table->decimal('berat_verifikasi', 8, 2)->nullable()->after('berat_dijemput');
            }
            if (! Schema::hasColumn('b3_pendaftarans', 'tgl_dijadwalkan')) {
                $table->timestamp('tgl_dijadwalkan')->nullable();
            }
            if (! Schema::hasColumn('b3_pendaftarans', 'tgl_dijemput')) {
                $table->timestamp('tgl_dijemput')->nullable();
            }
            if (! Schema::hasColumn('b3_pendaftarans', 'alasan_tidak_sesuai')) {
                $table->text('alasan_tidak_sesuai')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('b3_pendaftarans')) {
            return;
        }

        Schema::table('b3_pendaftarans', function (Blueprint $table) {
            foreach (['berat_dijemput', 'berat_verifikasi', 'tgl_dijadwalkan', 'tgl_dijemput', 'alasan_tidak_sesuai'] as $kolom) {
                if (Schema::hasColumn('b3_pendaftarans', $kolom)) {
                    $table->dropColumn($kolom);
                }
            }
        });
    }
};