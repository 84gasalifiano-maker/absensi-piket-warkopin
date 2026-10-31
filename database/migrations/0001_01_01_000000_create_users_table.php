<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Foreign key school_id & class_id ditambahkan di migrasi
     * add_foreign_keys_to_users_table, karena tabel schools & classes
     * baru dibuat setelah tabel ini (classes.wali_kelas_id butuh users).
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('school_id');
            // Hanya untuk siswa & PJ. Null untuk guru & admin: kelas guru
            // ditentukan dari classes.wali_kelas_id (SPEC bagian 2).
            $table->unsignedBigInteger('class_id')->nullable();
            $table->string('nama');
            $table->string('email')->nullable()->unique(); // login guru & admin; boleh null untuk siswa/PJ
            $table->string('nis', 30)->nullable(); // login siswa & PJ; null untuk guru & admin
            $table->unsignedTinyInteger('no_absen')->nullable(); // null untuk guru & admin
            $table->string('password');
            $table->enum('role', ['siswa', 'pj_kebersihan', 'guru', 'admin']);
            $table->boolean('harus_ganti_password')->default(true);
            $table->rememberToken();
            $table->timestamps();

            // MySQL mengizinkan banyak NULL di unique index, jadi guru/admin
            // (nis & no_absen null) tidak bentrok satu sama lain.
            $table->unique(['school_id', 'nis']);     // NIS unik per sekolah
            $table->unique(['class_id', 'no_absen']); // no. absen unik per kelas
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
