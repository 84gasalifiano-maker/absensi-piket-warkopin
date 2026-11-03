<?php

namespace App\Http\Controllers\Auth;

use App\Actions\GantiPassword;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GantiPasswordRequest;
use App\Support\LoginDenganPassword;
use Illuminate\Auth\Recaller;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class GantiPasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.ganti-password', [
            'wajib' => $request->user()->harus_ganti_password,
            // Kolom password lama tidak tampil saat ganti wajib di sesi yang login dengan password (F1, v0.11).
            'perluPasswordLama' => ! LoginDenganPassword::bolehTanpaPasswordLama($request),
        ]);
    }

    public function update(GantiPasswordRequest $request, GantiPassword $gantiPassword): RedirectResponse
    {
        /** @var SessionGuard $guard */
        $guard = Auth::guard('web');
        $user = $request->user();

        // Harus dicek SEBELUM token diganti di bawah.
        $ingatSaya = $this->perangkatIniDiingat($request, $guard);

        // Keluarkan user dari semua perangkat...
        $gantiPassword->jalankan($user, $request->validated('password_baru'), harusGantiPassword: false);

        // ...lalu login-kan ulang perangkat yang sedang dipakai: ID sesi baru
        // (sesi lama dihapus) dan, bila perlu, cookie "Ingat saya" dengan token baru.
        // login() juga mengganti token CSRF. Token lama dipulihkan, supaya form dari
        // tab lain di perangkat ini mendapat pesan "Password lama wajib diisi."
        // (SPEC F1), bukan halaman 419. ID sesi tetap baru; token tanpa sesinya
        // tidak berguna bagi orang lain.
        $tokenCsrf = $request->session()->token();
        $guard->login($user, $ingatSaya);
        $request->session()->put('_token', $tokenCsrf);

        // Tanda hanya untuk ganti wajib; sudah tidak diperlukan (pengaman tambahan).
        LoginDenganPassword::hapus($request);

        return redirect()
            ->route($user->role->halamanAwal())
            ->with('status', 'Password berhasil diganti.');
    }

    /**
     * Apakah user ini mencentang "Ingat saya" di perangkat ini?
     *
     * Tidak cukup mengecek cookie-nya ada: cookie yang sudah tidak berlaku
     * (token lama, atau milik user lain di komputer bersama) tetap tersimpan
     * di browser. Jadi cookie harus milik user ini dan tokennya masih cocok.
     */
    private function perangkatIniDiingat(Request $request, SessionGuard $guard): bool
    {
        $recaller = new Recaller((string) $request->cookies->get($guard->getRecallerName()));
        $user = $request->user();

        return $recaller->valid()
            && (string) $recaller->id() === (string) $user->getAuthIdentifier()
            && hash_equals((string) $user->getRememberToken(), $recaller->token());
    }
}
