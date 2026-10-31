<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Selama user wajib ganti password (login pertama / setelah direset admin),
 * semua halaman dialihkan ke /ganti-password (SPEC F1).
 * Route /ganti-password dan /logout tidak memakai middleware ini.
 */
class WajibGantiPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->harus_ganti_password) {
            return redirect()->route('ganti-password');
        }

        return $next($request);
    }
}
