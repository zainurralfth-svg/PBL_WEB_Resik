<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel untuk pendaftaran & verifikasi B3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('b3_pendaftarans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_periode')->nullable()->index();
            $table->integer('id_masyarakat')->nullable()->index();   // warga
            $table->integer('id_pns')->nullable()->index();          // atau pegawai (PNS)
            $table->unsignedBigInteger('id_desa')->nullable()->index();
            $table->decimal('est_berat', 8, 2)->default(0);
            $table->enum('status', ['terdaftar', 'dijadwalkan', 'menunggu_verifikasi', 'diverifikasi'])
                ->default('terdaftar')
                ->index();
            $table->integer('id_petugas')->nullable()->index();
            $table->decimal('total_imbalan', 12, 2)->default(0);
            $table->text('catatan_verifikasi')->nullable();
            $table->timestamp('tgl_verifikasi')->nullable();
            $table->timestamps();
        });

        Schema::create('b3_pendaftaran_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_pendaftaran')->constrained('b3_pendaftarans')->cascadeOnDelete();
            $table->string('jenis_b3', 100);
            $table->unsignedInteger('jumlah')->default(1);
            $table->unsignedInteger('jumlah_petugas')->nullable();
            $table->unsignedInteger('jumlah_verifikasi')->nullable();
            $table->decimal('harga_satuan', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b3_pendaftaran_items');
        Schema::dropIfExists('b3_pendaftarans');
    }
};
