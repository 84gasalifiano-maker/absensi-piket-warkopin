<?php

use App\Http\Middleware\CekRole;
use App\Http\Middleware\WajibGantiPassword;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\AuthenticateSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => CekRole::class,
        ]);

        // Berlaku untuk semua route web, termasuk route yang ditambahkan nanti (SPEC F1).
        $middleware->web(append: [
            // Sesi yang dibuat dengan password lama otomatis keluar, apa pun
            // SESSION_DRIVER-nya (mis. file di hosting). Melengkapi App\Actions\GantiPassword.
            AuthenticateSession::class,
            WajibGantiPassword::class,
        ]);

        // Cek role & wajib ganti password SEBELUM {kelas}/{akun} dicari di database
        // (SubstituteBindings). Tanpa ini, non-admin mendapat 404 (bukan 403) untuk ID
        // yang tidak ada, sehingga bisa menebak ID mana yang ada (SPEC §7, §11 Langkah 4).
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: WajibGantiPassword::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: CekRole::class);

        // User yang sudah login lalu membuka /login dialihkan ke halaman awal role-nya.
        $middleware->redirectUsersTo(
            fn (Request $request) => route($request->user()->role->halamanAwal()),
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
