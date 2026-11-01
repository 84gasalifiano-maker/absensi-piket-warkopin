<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC §6 (v0.9): kolom tugas di MySQL jadi VARCHAR(20) NOT NULL + CHECK, bukan ENUM.
 *
 * Alasannya: kolom ENUM NOT NULL di MySQL punya nilai bawaan tersembunyi, yaitu nilai
 * pertama ('menyapu'). Kode yang lupa mengisi tugas tidak ditolak, tapi diam-diam
 * mendapat 'menyapu'. Kolom teks NOT NULL tanpa nilai bawaan ditolak (mode strict),
 * dan CHECK tetap membatasi nilainya ke tiga tugas.
 *
 * Hanya MySQL/MariaDB. Di SQLite (dipakai tes), Laravel sudah membuat "enum" sebagai
 * teks + CHECK, jadi tidak ada yang perlu diubah. SQL tetap, tanpa input user.
 */
return new class extends Migration
{
    private const TUGAS = ['menyapu', 'merapikan', 'mengepel'];

    /** tabel => kolom */
    private const KOLOM = ['duty_schedules' => 'tugas_awal', 'submissions' => 'tugas'];

    public function up(): void
    {
        if (! $this->mysql()) {
            return;
        }

        $daftarNilai = "'".implode("', '", self::TUGAS)."'";

        foreach (self::KOLOM as $tabel => $kolom) {
            // Isi lama tetap (nilainya sudah salah satu dari tiga tugas).
            Schema::table($tabel, function (Blueprint $table) use ($kolom) {
                $table->string($kolom, 20)->change(); // tanpa nullable() → tetap NOT NULL
            });

            DB::statement("ALTER TABLE {$tabel} ADD CONSTRAINT {$tabel}_{$kolom}_check CHECK ({$kolom} IN ({$daftarNilai}))");
        }
    }

    public function down(): void
    {
        if (! $this->mysql()) {
            return;
        }

        foreach (self::KOLOM as $tabel => $kolom) {
            DB::statement("ALTER TABLE {$tabel} DROP CONSTRAINT {$tabel}_{$kolom}_check");

            Schema::table($tabel, function (Blueprint $table) use ($kolom) {
                $table->enum($kolom, self::TUGAS)->change();
            });
        }
    }

    private function mysql(): bool
    {
        return in_array(DB::getDriverName(), ['mysql', 'mariadb'], true);
    }
};
