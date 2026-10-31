<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
                    return back()->withErrors([
                        'password_lama' => 'Terlalu banyak percobaan ganti password. Coba lagi dalam '
                            .$headers['Retry-After'].' detik.',
                    ]);
                });
        });
    }
}
