<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hanya tabel untuk bagian PENDAFTARAN B3. Tabel periode & serah terima dibuat temanmu.
 *
 * Tipe kolom disamakan dengan database RESIK:
 *   masyarakat.id_masyarakat, pns.id_pns, petugas.id_petugas = int; desa.id_desa = bigint unsigned.
 * id_periode sengaja TANPA foreign key, supaya migration ini bisa jalan walau tabel periode
 * belum dibuat. Tambahkan foreign key-nya belakangan kalau tabel periode sudah final.
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
            $table->timestamps();
        });

        Schema::create('b3_pendaftaran_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_pendaftaran')->constrained('b3_pendaftarans')->cascadeOnDelete();
            $table->string('jenis_b3', 100);
            $table->unsignedInteger('jumlah')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b3_pendaftaran_items');
        Schema::dropIfExists('b3_pendaftarans');
    }
};