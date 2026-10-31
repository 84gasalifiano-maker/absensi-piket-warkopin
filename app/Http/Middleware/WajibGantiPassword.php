<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Selama user wajib ganti password (login pertama / setelah direset admin),
 * semua halaman dialihkan ke /ganti-password (SPEC F1).
 *
 * Dipasang otomatis di SEMUA route web (bootstrap/app.php), jadi route baru
 * tidak mungkin lupa memakainya. Tamu tidak terpengaruh. Pengecualian hanya
 * halaman ganti password itu sendiri dan logout.
 */
class WajibGantiPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->harus_ganti_password && ! $request->is('ganti-password', 'logout')) {
            return redirect()->route('ganti-password');
        }

        return $next($request);
    }
}
