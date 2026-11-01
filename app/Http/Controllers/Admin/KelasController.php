<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanKelasRequest;
use App\Models\Kelas;
use App\Models\School;
use App\Models\User;
use App\Support\TangkapDuplikat;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Kelola kelas oleh admin (SPEC F9). {kelas} di URL hanya menemukan kelas di
 * sekolah admin (Route::bind di AppServiceProvider); kelas sekolah lain → 404.
 */
class KelasController extends Controller
{
    /**
     * Nama kelas kembar dari dua penyimpanan bersamaan → pesan validasi (SPEC F9, v0.9).
     * Nama constraint: nama index (MySQL) atau daftar kolom (SQLite, dipakai tes).
     */
    private const DUPLIKAT_KELAS = [
        [['classes_school_id_nama_unique', 'classes.school_id, classes.nama'], 'nama', 'Nama kelas sudah dipakai di sekolah ini.'],
    ];

    public const PESAN_TIDAK_KOSONG = 'Kelas ini masih punya siswa atau riwayat piket.';

    public function index(Request $request): View
    {
        $sekolah = $request->user()->school;

        return view('admin.kelas.index', [
            'daftarKelas' => $sekolah->kelas()->with('waliKelas')->withCount('anggota')->orderBy('nama')->get(),
            'daftarGuru' => $this->daftarGuru($sekolah),
        ]);
    }

    public function store(SimpanKelasRequest $request): RedirectResponse
    {
        // Lewat relasi sekolah admin: school_id tidak fillable (SPEC §6).
        $data = $request->validated();
        $kelas = $this->simpan(fn () => $request->user()->school->kelas()->create($data), $data);

        return redirect()->route('admin.kelas.index')->with('status', "Kelas {$kelas->nama} ditambahkan.");
    }

    public function edit(Request $request, Kelas $kelas): View
    {
        return view('admin.kelas.edit', [
            'kelas' => $kelas,
            'daftarGuru' => $this->daftarGuru($request->user()->school),
            'tanggalTerkunci' => $kelas->tanggalMulaiTerkunci(),
        ]);
    }

    public function update(SimpanKelasRequest $request, Kelas $kelas): RedirectResponse
    {
        $data = $request->validated();

        // Terkunci setelah kelas punya submit (SPEC F2): nilai lama dipertahankan.
        if ($request->tanggalTerkunci()) {
            unset($data['tanggal_mulai_rotasi']);
        }

        $this->simpan(fn () => $kelas->update($data), $data);

        return redirect()->route('admin.kelas.index')->with('status', "Kelas {$kelas->nama} disimpan.");
    }

    public function destroy(Kelas $kelas): RedirectResponse
    {
        try {
            $dihapus = DB::transaction(function () use ($kelas) {
                // Kunci baris kelas dulu (sebelum query biasa pertama). Penambahan siswa,
                // jadwal, atau submit ke kelas ini menunggu kunci ini karena foreign key;
                // yang selesai duluan terlihat oleh bolehDihapus() di bawah (SPEC F9).
                // Kelas yang baru saja dihapus admin lain → 404.
                abort_unless(Kelas::whereKey($kelas->id)->lockForUpdate()->first(), 404);

                if (! $kelas->bolehDihapus()) {
                    return false;
                }

                $kelas->delete();

                return true;
            });
        } catch (QueryException $e) {
            // Cadangan: database menolak karena masih ada siswa atau submit yang menunjuk
            // kelas ini (foreign key) → pesan biasa, bukan halaman error (v0.9).
            // Jadwal TIDAK membuat database menolak: duty_schedules.class_id memakai
            // cascadeOnDelete (ikut terhapus). Jadwal yang ditambahkan bersamaan hanya
            // dilindungi oleh kunci baris kelas di atas.
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            $dihapus = false;
        }

        if (! $dihapus) {
            return back()->withErrors(['hapus' => self::PESAN_TIDAK_KOSONG]);
        }

        return redirect()->route('admin.kelas.index')->with('status', "Kelas {$kelas->nama} dihapus.");
    }

    /**
     * Simpan kelas. Isian yang lolos validasi tapi lalu ditolak database karena
     * penyimpanan lain bersamaan ditampilkan sebagai pesan validasi (SPEC F9, v0.9):
     * - nama kelas kembar (UNIQUE);
     * - guru yang dipilih sebagai wali baru saja dihapus (foreign key).
     *
     * @template T
     *
     * @param  callable(): T  $simpan
     * @param  array<string, mixed>  $data
     * @return T
     *
     * @throws ValidationException
     */
    private function simpan(callable $simpan, array $data): mixed
    {
        try {
            return TangkapDuplikat::jalankan($simpan, self::DUPLIKAT_KELAS);
        } catch (QueryException $e) {
            // SQLSTATE 23000 = pelanggaran aturan data (termasuk foreign key). Dicek dari
            // datanya, bukan dari teks error, supaya sama di MySQL dan SQLite.
            $waliId = $data['wali_kelas_id'] ?? null;
            $waliHilang = $e->getCode() === '23000' && $waliId !== null && ! User::whereKey($waliId)->exists();

            if ($waliHilang) {
                throw ValidationException::withMessages(['wali_kelas_id' => SimpanKelasRequest::PESAN_WALI]);
            }

            throw $e;
        }
    }

    /** @return Collection<int, User> */
    private function daftarGuru(School $sekolah): Collection
    {
        return $sekolah->users()->where('role', Role::Guru)->orderBy('nama')->get();
    }
}
