<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Tabel utama pendaftaran B3
        Schema::create('b3_pendaftarans', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_masyarakat');
            $table->decimal('est_berat', 8, 2)->nullable();
            $table->enum('status', ['pending', 'diverifikasi', 'ditolak', 'selesai'])->default('pending');
            $table->text('catatan_verifikasi')->nullable();
            $table->timestamps();
        });

        // 2. Tabel detail item/jenis B3 yang didaftarkan
        Schema::create('b3_pendaftaran_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('id_pendaftaran');
            $table->string('jenis_b3');
            $table->timestamps();

            $table->foreign('id_pendaftaran')
                ->references('id')
                ->on('b3_pendaftarans')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('b3_pendaftaran_items');
        Schema::dropIfExists('b3_pendaftarans');
    }
};