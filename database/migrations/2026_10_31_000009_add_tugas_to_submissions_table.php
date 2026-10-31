<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC v0.5 (§6, F3): submissions.tugas, diisi server saat submit dari hasil
 * rotasi. Salinan yang disengaja supaya riwayat tidak berubah kalau jadwal diubah.
 */
return new class extends Migration
{
    private const TUGAS = ['menyapu', 'merapikan', 'mengepel'];

    public function up(): void
    {
        // Nullable dulu lalu wajib, seperti tugas_awal: kalau sudah ada submit
        // tanpa tugas, migration gagal dengan jelas. Saat dibuat, tabel kosong.
        Schema::table('submissions', function (Blueprint $table) {
            $table->enum('tugas', self::TUGAS)->nullable()->after('tanggal');
        });
        Schema::table('submissions', function (Blueprint $table) {
            $table->enum('tugas', self::TUGAS)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('submissions', function (Blueprint $table) {
            $table->dropColumn('tugas');
        });
    }
};
