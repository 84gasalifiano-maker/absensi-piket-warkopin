<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Mengganti password user dan mengeluarkannya dari SEMUA perangkat (SPEC F1).
 *
 * Dipakai di dua tempat:
 * - user ganti password sendiri (GantiPasswordController): setelah ini,
 *   perangkat yang sedang dipakai di-login-kan ulang oleh controller;
 * - admin mereset password (Langkah 3): panggil dengan $harusGantiPassword = true.
 *
 * $harusGantiPassword sengaja tanpa nilai default, supaya pemanggil selalu
 * memutuskan secara eksplisit (reset admin yang lupa mengisinya akan error).
 */
class GantiPassword
{
    public function jalankan(User $user, string $passwordBaru, bool $harusGantiPassword): void
    {
        DB::transaction(function () use ($user, $passwordBaru, $harusGantiPassword) {
            // forceFill karena harus_ganti_password tidak fillable.
            // Password otomatis di-hash oleh cast 'hashed' di model User.
            $user->forceFill([
                'password' => $passwordBaru,
                'harus_ganti_password' => $harusGantiPassword,
                // Token baru membuat cookie "Ingat saya" lama di semua perangkat tidak berlaku.
                'remember_token' => Str::random(60),
            ])->save();

            // Hapus semua sesi login user ini (SESSION_DRIVER=database, SPEC bagian 6).
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });
    }
}
