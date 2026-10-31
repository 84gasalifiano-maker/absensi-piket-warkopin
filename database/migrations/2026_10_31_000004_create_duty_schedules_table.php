<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('duty_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->unsignedTinyInteger('hari'); // 1 = Senin … 6 = Sabtu
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            $table->unique(['class_id', 'hari', 'user_id']);
        });

        // Batasi hari ke 1–6 (Senin–Sabtu) di level database.
        // Blueprint Laravel belum punya method check(), jadi pakai SQL langsung
        // (tanpa input user). Dilewati di SQLite (dipakai tes) karena SQLite
        // tidak mendukung ALTER TABLE ... ADD CONSTRAINT.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE duty_schedules ADD CONSTRAINT duty_schedules_hari_check CHECK (hari BETWEEN 1 AND 6)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('duty_schedules');
    }
};
