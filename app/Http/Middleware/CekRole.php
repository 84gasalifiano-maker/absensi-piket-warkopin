<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hanya meloloskan user dengan role tertentu (SPEC bagian 7).
 * Pemakaian di route: ->middleware('role:siswa,pj_kebersihan').
 * Dipasang setelah middleware `auth`, jadi user pasti sudah login.
 */
class CekRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        abort_unless(
            in_array($request->user()->role->value, $roles, true),
            403,
            'Kamu tidak punya akses ke halaman ini.',
        );

        return $next($request);
    }
}
