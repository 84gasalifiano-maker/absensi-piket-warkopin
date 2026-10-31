<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC v0.5 (§6, F2):
 * - tambah tugas_awal (menyapu|merapikan|mengepel), wajib diisi;
 * - setiap siswa tepat satu hari per kelas: UNIQUE(class_id, hari, user_id) → UNIQUE(class_id, user_id);
 * - hari piket hanya Senin–Jumat: CHECK hari 1–6 → 1–5.
 *
 * Nilai enum ditulis langsung (bukan dari App\Enums\Tugas) supaya migration
 * ini tidak berubah arti kalau enum di kode diubah nanti.
 */
return new class extends Migration
{
    private const TUGAS = ['menyapu', 'merapikan', 'mengepel'];

    public function up(): void
    {
        // Ditambah sebagai nullable dulu, lalu dijadikan wajib. Kalau ternyata
        // sudah ada baris jadwal, langkah "wajib" gagal dengan jelas (bukan
        // diam-diam diisi tugas sembarang). Saat migration ini dibuat, tabel kosong.
        Schema::table('duty_schedules', function (Blueprint $table) {
            $table->enum('tugas_awal', self::TUGAS)->nullable()->after('user_id');
        });
        Schema::table('duty_schedules', function (Blueprint $table) {
            $table->enum('tugas_awal', self::TUGAS)->nullable(false)->change();
        });

        // Unique baru dibuat DULU: index lama juga dipakai foreign key class_id,
        // jadi MySQL menolak menghapusnya sebelum ada index pengganti.
        Schema::table('duty_schedules', function (Blueprint $table) {
            $table->unique(['class_id', 'user_id']);
        });
        Schema::table('duty_schedules', function (Blueprint $table) {
            $table->dropUnique(['class_id', 'hari', 'user_id']);
        });

        $this->gantiCheckHari(maks: 5);
    }

    public function down(): void
    {
        $this->gantiCheckHari(maks: 6);

        Schema::table('duty_schedules', function (Blueprint $table) {
            $table->unique(['class_id', 'hari', 'user_id']);
        });
        Schema::table('duty_schedules', function (Blueprint $table) {
            $table->dropUnique(['class_id', 'user_id']);
            $table->dropColumn('tugas_awal');
        });
    }

    /**
     * Ganti batas CHECK kolom hari. Hanya MySQL/MariaDB; SQLite (dipakai tes)
     * tidak mendukung ALTER TABLE ... ADD CONSTRAINT, sama seperti migration
     * create_duty_schedules_table. SQL tetap, tanpa input user.
     */
    private function gantiCheckHari(int $maks): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        DB::statement('ALTER TABLE duty_schedules DROP CONSTRAINT duty_schedules_hari_check');
        DB::statement("ALTER TABLE duty_schedules ADD CONSTRAINT duty_schedules_hari_check CHECK (hari BETWEEN 1 AND {$maks})");
    }
};
