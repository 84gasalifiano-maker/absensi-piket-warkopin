<?php

use App\Http\Controllers\Admin\AkunController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\HariLiburController;
use App\Http\Controllers\Admin\KelasController;
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

    // Halaman admin (SPEC F9, §7). Semua /admin/* hanya untuk admin.
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin', DashboardController::class)->name('admin');

        Route::prefix('admin')->name('admin.')->group(function () {
            Route::get('/kelas', [KelasController::class, 'index'])->name('kelas.index');
            Route::post('/kelas', [KelasController::class, 'store'])->name('kelas.store');
            Route::get('/kelas/{kelas}/edit', [KelasController::class, 'edit'])->name('kelas.edit');
            Route::put('/kelas/{kelas}', [KelasController::class, 'update'])->name('kelas.update');
            Route::delete('/kelas/{kelas}', [KelasController::class, 'destroy'])->name('kelas.destroy');
            // Reset massal (SPEC F9, v0.11): daftar password-nya dipakai bersama dengan import,
            // jadi memakai kunci sesi yang sama dengan route import/unduh di bawah.
            Route::post('/kelas/{kelas}/reset-password', [KelasController::class, 'resetPassword'])->name('kelas.reset-password')
                ->block(lockSeconds: 150, waitSeconds: 150);

            Route::get('/akun', [AkunController::class, 'index'])->name('akun.index');
            Route::post('/akun', [AkunController::class, 'store'])->name('akun.store');
            Route::get('/akun/baru', [AkunController::class, 'create'])->name('akun.create');
            // block(): request import/unduh di sesi yang sama berjalan bergantian, bukan
            // bersamaan. Tanpa ini, import kedua (mis. dari tab lain) bisa menimpa sesi dan
            // menghapus daftar password import pertama (SPEC F9). Kunci 150 detik > batas
            // waktu import (set_time_limit 120 di AkunController::import).
            Route::post('/akun/import', [AkunController::class, 'import'])->name('akun.import')
                ->block(lockSeconds: 150, waitSeconds: 150);
            Route::post('/akun/import/password', [AkunController::class, 'unduhPasswordImport'])->name('akun.import.password')
                ->block(lockSeconds: 150, waitSeconds: 150);
            Route::get('/akun/{akun}/edit', [AkunController::class, 'edit'])->name('akun.edit');
            Route::put('/akun/{akun}', [AkunController::class, 'update'])->name('akun.update');
            Route::post('/akun/{akun}/reset-password', [AkunController::class, 'resetPassword'])->name('akun.reset-password');
            Route::delete('/akun/{akun}', [AkunController::class, 'destroy'])->name('akun.destroy');

            // Hari libur (SPEC F8, v0.12). Tidak ada edit: hapus lalu tambah lagi.
            Route::get('/hari-libur', [HariLiburController::class, 'index'])->name('hari-libur.index');
            Route::post('/hari-libur', [HariLiburController::class, 'store'])->name('hari-libur.store');
            Route::delete('/hari-libur/{hariLibur}', [HariLiburController::class, 'destroy'])->name('hari-libur.destroy');
        });
    });
});
