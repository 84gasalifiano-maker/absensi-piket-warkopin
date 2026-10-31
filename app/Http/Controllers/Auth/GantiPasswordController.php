<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\GantiPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GantiPasswordController extends Controller
{
    public function edit(Request $request): View
    {
        return view('auth.ganti-password', [
            'wajib' => $request->user()->harus_ganti_password,
        ]);
    }

    public function update(GantiPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        // forceFill karena harus_ganti_password tidak fillable.
        // Password otomatis di-hash oleh cast 'hashed' di model User.
        $user->forceFill([
            'password' => $request->validated('password_baru'),
            'harus_ganti_password' => false,
        ])->save();

        $request->session()->regenerate();

        return redirect()
            ->route($user->role->halamanAwal())
            ->with('status', 'Password berhasil diganti.');
    }
}
