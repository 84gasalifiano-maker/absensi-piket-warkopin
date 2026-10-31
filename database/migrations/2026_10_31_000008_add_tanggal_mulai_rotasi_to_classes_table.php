<?php

use Carbon\CarbonInterface;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC v0.6 (§6, F2): classes.tanggal_mulai_rotasi, wajib diisi (NOT NULL).
 * Titik awal hitungan rotasi tugas mingguan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->date('tanggal_mulai_rotasi')->nullable()->after('wali_kelas_id');
        });

        // Kelas yang sudah ada (data contoh Langkah 1) perlu nilai supaya kolom
        // bisa dijadikan wajib: Senin minggu ini (SPEC §11 Langkah 3).
        DB::table('classes')
            ->whereNull('tanggal_mulai_rotasi')
            ->update(['tanggal_mulai_rotasi' => now()->startOfWeek(CarbonInterface::MONDAY)->toDateString()]);

        Schema::table('classes', function (Blueprint $table) {
            $table->date('tanggal_mulai_rotasi')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->dropColumn('tanggal_mulai_rotasi');
        });
    }
};
