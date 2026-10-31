<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dipisah dari create_users_table karena schools & classes dibuat
     * setelah users (classes.wali_kelas_id merujuk ke users).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreign('school_id')->references('id')->on('schools');
            $table->foreign('class_id')->references('id')->on('classes');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['school_id']);
            $table->dropForeign(['class_id']);
        });
    }
};
