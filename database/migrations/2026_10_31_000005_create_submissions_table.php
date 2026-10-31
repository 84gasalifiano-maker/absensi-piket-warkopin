<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submissions', function (Blueprint $table) {
            $table->id();
            // Tanpa cascade: riwayat piket disimpan permanen (SPEC F6),
            // jadi user/kelas yang punya submit tidak bisa dihapus begitu saja.
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('class_id')->constrained('classes');
            $table->date('tanggal');
            $table->text('catatan')->nullable();
            $table->enum('status', ['menunggu_verifikasi', 'disetujui', 'ditolak', 'kedaluwarsa'])
                ->default('menunggu_verifikasi');
            $table->boolean('terlambat')->default(false);
            $table->text('alasan_terlambat')->nullable();
            $table->text('alasan_tolak')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('created_at')->nullable(); // diisi waktu server oleh Eloquent

            // Cek "satu submit aktif per siswa per tanggal" & antrean/rekap per kelas.
            $table->index(['user_id', 'tanggal']);
            $table->index(['class_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submissions');
    }
};
