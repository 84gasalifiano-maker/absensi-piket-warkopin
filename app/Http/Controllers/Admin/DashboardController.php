<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Dashboard admin (SPEC F9): ringkasan semua kelas di sekolah admin. */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $kelas = $request->user()->school->kelas()
            ->with(['waliKelas', 'pjKebersihan'])
            ->withCount('anggota')
            ->orderBy('nama')
            ->get();

        return view('admin.dashboard', ['daftarKelas' => $kelas]);
    }
}
