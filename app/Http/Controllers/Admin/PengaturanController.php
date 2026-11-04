<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanPengaturanRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pengaturan jam buka/tutup sekolah admin yang login (SPEC F4, §8.1, v0.13).
 *
 * Tanpa kunci: form selalu mengirim keempat jam dan semuanya divalidasi bersama,
 * jadi kalau dua admin menyimpan bersamaan, yang terakhir menyimpan yang berlaku dan
 * hasilnya tetap satu set jam yang berurutan (keputusan pemilik proyek, Langkah 7).
 */
class PengaturanController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.pengaturan.edit', ['sekolah' => $request->user()->school]);
    }

    public function update(SimpanPengaturanRequest $request): RedirectResponse
    {
        // Hanya keempat jam; isian lain (mis. nama, school_id) diabaikan.
        $request->user()->school->update($request->jamUntukDisimpan());

        return redirect()->route('admin.pengaturan.edit')->with('status', 'Pengaturan jam disimpan.');
    }
}
