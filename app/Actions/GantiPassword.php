<?php

namespace App\Actions;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use LogicException;

/**
 * Mengganti password user dan mengeluarkannya dari SEMUA perangkat (SPEC F1).
 *
 * Dipakai di tiga tempat:
 * - user ganti password sendiri (GantiPasswordController): setelah ini,
 *   perangkat yang sedang dipakai di-login-kan ulang oleh controller;
 * - admin mereset password (Langkah 4, AkunController::resetPassword): panggil dengan $harusGantiPassword = true;
 * - reset massal satu kelas (Langkah 5a, ResetMassalKelas): lewat jalankanDenganHash(),
 *   karena hash sudah dibuat di luar transaksi.
 *
 * $harusGantiPassword sengaja tanpa nilai default, supaya pemanggil selalu
 * memutuskan secara eksplisit (reset admin yang lupa mengisinya akan error).
 */
class GantiPassword
{
    /** $passwordBaru = password biasa (selalu di-hash di sini). */
    public function jalankan(User $user, string $passwordBaru, bool $harusGantiPassword): void
    {
        // Di-hash eksplisit, bukan mengandalkan cast "hashed" di model User: cast itu
        // menyimpan apa adanya isian yang sudah berbentuk hash, sehingga user bisa
        // mengirim hash bcrypt sebagai "password baru" dan melewati aturan minimal 8 karakter.
        $this->simpan($user, Hash::make($passwordBaru), $harusGantiPassword);
    }

    /** $hash = hasil Hash::make() yang sudah dibuat pemanggil (reset massal). */
    public function jalankanDenganHash(User $user, string $hash, bool $harusGantiPassword): void
    {
        if (! Hash::isHashed($hash)) {
            throw new LogicException('jalankanDenganHash() butuh password yang sudah di-hash.');
        }

        $this->simpan($user, $hash, $harusGantiPassword);
    }

    private function simpan(User $user, string $hash, bool $harusGantiPassword): void
    {
        DB::transaction(function () use ($user, $hash, $harusGantiPassword) {
            // forceFill karena harus_ganti_password tidak fillable. Nilainya sudah hash,
            // jadi cast "hashed" di model User tidak meng-hash ulang.
            $user->forceFill([
                'password' => $hash,
                'harus_ganti_password' => $harusGantiPassword,
                // Token baru membuat cookie "Ingat saya" lama di semua perangkat tidak berlaku.
                'remember_token' => Str::random(60),
            ])->save();

            // Hapus semua sesi login user ini (SESSION_DRIVER=database, SPEC bagian 6).
            DB::table(config('session.table'))->where('user_id', $user->id)->delete();
        });
    }
}
