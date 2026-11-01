<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC v0.8 (§6, F9): nama kelas unik per sekolah.
 *
 * Catatan MySQL: saat unique (school_id, nama) dibuat, MySQL diam-diam menghapus
 * index bawaan foreign key school_id dan memakai unique ini sebagai gantinya.
 * Karena itu down() membuat index pengganti dulu sebelum menghapus unique, dan
 * up() membuang index pengganti itu lagi supaya bisa dijalankan bolak-balik.
 */
return new class extends Migration
{
    private const INDEX_PENGGANTI = 'classes_school_id_index';

    public function up(): void
    {
        Schema::table('classes', function (Blueprint $table) {
            $table->unique(['school_id', 'nama']);
        });

        if (Schema::hasIndex('classes', self::INDEX_PENGGANTI)) {
            Schema::table('classes', function (Blueprint $table) {
                $table->dropIndex(self::INDEX_PENGGANTI);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('classes', self::INDEX_PENGGANTI)) {
            Schema::table('classes', function (Blueprint $table) {
                $table->index('school_id', self::INDEX_PENGGANTI);
            });
        }

        Schema::table('classes', function (Blueprint $table) {
            $table->dropUnique(['school_id', 'nama']);
        });
    }
};
