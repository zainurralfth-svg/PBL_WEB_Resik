<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Aman dijalankan di database yang tabelnya sudah ada (hasil impor SQL).
        if (Schema::hasTable('b3_periode_desa')) {
            return;
        }

        Schema::create('b3_periode_desa', function (Blueprint $table) {
            $table->id('id_periode_desa');
            $table->unsignedBigInteger('id_periode');
            $table->unsignedBigInteger('id_desa')->index();
            $table->timestamps();

            $table->unique(['id_periode', 'id_desa']);
            $table->foreign('id_periode')
                ->references('id_periode')->on('b3_periode')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('b3_periode_desa');
    }
};