<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Tanda di sesi: "sesi ini login dengan mengetik password" (SPEC F1, v0.11).
 *
 * Diberi hanya oleh POST /login yang berhasil. Sesi yang dipulihkan dari cookie
 * "Ingat saya" tidak melewati POST /login, jadi tidak punya tanda ini.
 * Hanya sesi bertanda yang boleh ganti password wajib tanpa password lama.
 *
 * Isinya ID user (bukan sekadar true), supaya tanda tidak berlaku untuk user lain
 * yang kebetulan memakai sesi yang sama.
 */
class LoginDenganPassword
{
    private const KUNCI = 'login_dengan_password';

    public static function tandai(Request $request): void
    {
        $request->session()->put(self::KUNCI, (string) $request->user()->getAuthIdentifier());
    }

    public static function ada(Request $request): bool
    {
        $user = $request->user();

        return $user !== null
            // Dibandingkan sebagai teks: ID bisa terbaca int atau string tergantung driver.
            && $request->session()->get(self::KUNCI) === (string) $user->getAuthIdentifier();
    }

    public static function hapus(Request $request): void
    {
        $request->session()->forget(self::KUNCI);
    }

    /**
     * Boleh ganti password tanpa password lama? Ya hanya kalau user memang wajib
     * ganti (nilai harus_ganti_password dari database, bukan dari form) DAN sesi
     * ini login dengan mengetik password.
     */
    public static function bolehTanpaPasswordLama(Request $request): bool
    {
        return $request->user()?->harus_ganti_password === true && self::ada($request);
    }
}
