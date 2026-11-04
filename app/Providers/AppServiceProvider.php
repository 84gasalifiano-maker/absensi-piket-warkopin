<?php

namespace App\Providers;

use App\Models\HariLibur;
use App\Models\Kelas;
use App\Models\User;
use App\Support\LoginDenganPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // POST /ganti-password: maks 5 kali per menit per user (SPEC F1), supaya
        // password lama tidak bisa ditebak terus-menerus dari sesi yang dibajak.
        // Kalau terlampaui: kembali ke form dengan pesan, bukan halaman error.
        RateLimiter::for('ganti-password', function (Request $request) {
            return Limit::perMinute(5)
                ->by('ganti-password|'.$request->user()->id)
                ->response(function (Request $request, array $headers) {
                    // Pesan di bawah kolom password lama; kalau kolom itu tidak tampil
                    // (ganti wajib tanpa password lama, v0.11), di bawah password baru.
                    $kolom = LoginDenganPassword::bolehTanpaPasswordLama($request) ? 'password_baru' : 'password_lama';

                    return back()->withErrors([
                        $kolom => 'Terlalu banyak percobaan ganti password. Coba lagi dalam '
                            .$headers['Retry-After'].' detik.',
                    ]);
                });
        });

        // ID di URL hanya angka (mis. /admin/akun/1abc/edit → 404).
        Route::pattern('kelas', '[0-9]+');
        Route::pattern('akun', '[0-9]+');
        Route::pattern('hariLibur', '[0-9]+');

        // {kelas}, {akun}, dan {hariLibur} di URL hanya menemukan data di sekolah user yang
        // login (SPEC F8, F9). ID milik sekolah lain → 404, jadi keberadaannya tidak bocor.
        Route::bind('kelas', fn (string $id) => Kelas::query()
            ->where('school_id', request()->user()?->school_id)
            ->findOrFail($id));

        Route::bind('akun', fn (string $id) => User::query()
            ->where('school_id', request()->user()?->school_id)
            ->findOrFail($id));

        Route::bind('hariLibur', fn (string $id) => HariLibur::query()
            ->where('school_id', request()->user()?->school_id)
            ->findOrFail($id));
    }
}
