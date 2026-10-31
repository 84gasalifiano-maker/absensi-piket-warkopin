<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC v0.5/v0.6 (§6, F8): hari libur per sekolah, dikelola admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hari_libur', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools');
            $table->date('tanggal');
            // Wajib/opsional masih [KEPUTUSAN TERBUKA] F8: kolom dibuat boleh
            // kosong, aturan wajibnya nanti di validasi form.
            $table->string('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['school_id', 'tanggal']); // satu tanggal satu baris per sekolah
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hari_libur');
    }
};
