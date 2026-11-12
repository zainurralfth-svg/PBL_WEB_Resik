<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Aman dijalankan di database yang tabelnya sudah ada (hasil impor SQL).
        if (Schema::hasTable('b3_periode')) {
            return;
        }

        Schema::create('b3_periode', function (Blueprint $table) {
            $table->id('id_periode');
            $table->date('tgl_buka');
            $table->date('tgl_tutup');
            $table->date('tgl_jemput');
            $table->decimal('berat_min', 8, 2)->default(0);
            $table->decimal('berat_max', 8, 2);
            // Tanpa foreign key ke tabel petugas: struktur DB produksi
            // belum tentu sama dengan migration lama.
            $table->unsignedBigInteger('id_petugas')->nullable()->index();
            $table->timestamp('selesai_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b3_periode');
    }
};