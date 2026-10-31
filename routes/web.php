<?php

use App\Http\Controllers\Auth\GantiPasswordController;
use App\Http\Controllers\Auth\LoginController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// SPEC bagian 7: / dialihkan ke /login, atau ke halaman awal role kalau sudah login.
Route::get('/', function (Request $request) {
    $user = $request->user();

    return $user
        ? redirect()->route($user->role->halamanAwal())
        : redirect()->route('login');
});

// Hanya untuk tamu. User yang sudah login dialihkan ke halaman awalnya
// (diatur di bootstrap/app.php).
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

// Selama user wajib ganti password, semua route di bawah ini dialihkan ke
// /ganti-password oleh middleware WajibGantiPassword (otomatis, lihat bootstrap/app.php).
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('/ganti-password', [GantiPasswordController::class, 'edit'])->name('ganti-password');
    Route::post('/ganti-password', [GantiPasswordController::class, 'update'])
        ->middleware('throttle:ganti-password'); // maks 5 kali per menit per user (SPEC F1)

    // Halaman sementara (Langkah 2). Isinya dibuat di langkah masing-masing.
    Route::view('/beranda', 'sementara', ['judul' => 'Beranda'])
        ->middleware('role:siswa,pj_kebersihan')
        ->name('beranda');

    Route::view('/verifikasi', 'sementara', ['judul' => 'Antrean verifikasi'])
        ->middleware('role:pj_kebersihan,guru')
        ->name('verifikasi');

    Route::view('/admin', 'sementara', ['judul' => 'Admin'])
        ->middleware('role:admin')
        ->name('admin');
});
