<?php

use App\Http\Middleware\CekRole;
use App\Http\Middleware\WajibGantiPassword;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
            WajibGantiPassword::class,
        ]);

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
