<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schools', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            // Jendela waktu tepat waktu (WIB). Default sesuai SPEC F4.
            $table->time('jam_buka_pagi')->default('06:00:00');
            $table->time('jam_tutup_pagi')->default('07:30:00');
            $table->time('jam_buka_siang')->default('14:00:00');
            $table->time('jam_tutup_siang')->default('15:30:00');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schools');
    }
};
