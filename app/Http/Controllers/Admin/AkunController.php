<?php

namespace App\Http\Controllers\Admin;

use App\Actions\GantiPassword;
use App\Actions\HapusAkun;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SimpanAkunRequest;
use App\Models\Kelas;
use App\Models\School;
use App\Models\User;
use App\Support\PasswordAcak;
use App\Support\TangkapDuplikat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Kelola akun oleh admin (SPEC F9). {akun} di URL hanya menemukan akun di
 * sekolah admin (Route::bind di AppServiceProvider); akun sekolah lain → 404.
 */
class AkunController extends Controller
{
    public function index(Request $request): View
    {
        $sekolah = $request->user()->school;
        $daftarKelas = $sekolah->kelas()->orderBy('nama')->get();

        // Filter dari URL; nilai yang tidak dikenal diabaikan.
        $kelas = $request->query('kelas');
        $filterKelas = is_string($kelas) && ctype_digit($kelas) ? $daftarKelas->firstWhere('id', (int) $kelas) : null;
        $role = $request->query('role');
        $filterRole = is_string($role) ? Role::tryFrom($role) : null;

        $daftarAkun = $sekolah->users()
            ->with('kelas')
            ->when($filterKelas, fn ($q) => $q->where('class_id', $filterKelas->id))
            ->when($filterRole, fn ($q) => $q->where('role', $filterRole))
            ->orderBy('class_id')->orderBy('no_absen')->orderBy('nama')
            ->paginate(50)
            ->withQueryString();

        return view('admin.akun.index', compact('daftarKelas', 'daftarAkun', 'filterKelas', 'filterRole'));
    }

    public function create(Request $request): View
    {
        return view('admin.akun.create', [
            'daftarKelas' => $request->user()->school->kelas()->orderBy('nama')->get(),
        ]);
    }

    public function store(SimpanAkunRequest $request): RedirectResponse
    {
        $password = PasswordAcak::buat();
        $akun = new User($request->dataAkun());

        TangkapDuplikat::jalankan(fn () => DB::transaction(function () use ($request, $akun, $password) {
            $this->cekUlangBatasAdmin($request);
            $this->cekUlangBatasPj($request, null);

            // school_id, role, harus_ganti_password tidak fillable: diisi eksplisit (SPEC §6).
            $akun->forceFill([
                'school_id' => $request->user()->school_id,
                'role' => $request->roleTujuan(),
                'password' => $password,
                'harus_ganti_password' => true, // wajib ganti saat login pertama (F1)
            ])->save();
        }), self::DUPLIKAT_AKUN);

        return redirect()
            ->route('admin.akun.edit', $akun)
            ->with('status', "Akun {$akun->nama} dibuat.")
            ->with('password_sekali', $this->infoPassword($akun, $password));
    }

    public function edit(Request $request, User $akun, HapusAkun $hapusAkun): Response
    {
        $response = response()->view('admin.akun.edit', [
            'akun' => $akun,
            'daftarKelas' => $request->user()->school->kelas()->orderBy('nama')->get(),
            'akunSendiri' => $akun->is($request->user()),
            'punyaJadwal' => $akun->punyaJadwalDiKelasnya(),
            'alasanTidakBisaDihapus' => $hapusAkun->alasanDitolak($akun, $request->user()),
        ]);

        // Halaman yang menampilkan password sekali tidak boleh disimpan browser,
        // supaya tombol Back tidak menampilkannya lagi (SPEC F9).
        if (session()->has('password_sekali')) {
            $response->header('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    public function update(SimpanAkunRequest $request, User $akun): RedirectResponse
    {
        TangkapDuplikat::jalankan(fn () => DB::transaction(function () use ($request, $akun) {
            $this->kunciAkunMasihAda($akun);
            $this->cekUlangBatasPj($request, $akun);

            $kelasLama = $akun->class_id;

            $akun->fill($request->dataAkun());
            $akun->forceFill(['role' => $request->roleTujuan()]);

            // Pindah kelas: jadwal di kelas lama dihapus (SPEC F2, F9).
            if ($kelasLama !== null && $akun->class_id !== $kelasLama) {
                $akun->dutySchedules()->where('class_id', $kelasLama)->delete();
            }

            $akun->save();
        }), self::DUPLIKAT_AKUN);

        return redirect()->route('admin.akun.edit', $akun)->with('status', "Akun {$akun->nama} disimpan.");
    }

    public function destroy(Request $request, User $akun, HapusAkun $hapusAkun): RedirectResponse
    {
        // Tombol hapus tidak tampil di akun sendiri; kalau dikirim paksa, tolak (SPEC F9).
        abort_if($akun->is($request->user()), 403, HapusAkun::PESAN_SENDIRI);

        // Aturan riwayat & admin terakhir dicek di dalam transaksi (HapusAkun).
        $hapusAkun->jalankan($akun, $request->user());

        return redirect()->route('admin.akun.index')->with('status', "Akun {$akun->nama} dihapus.");
    }

    public function resetPassword(Request $request, User $akun, GantiPassword $gantiPassword): RedirectResponse
    {
        // Akun sendiri lewat /ganti-password (SPEC F9); kalau direset dari sini, admin ikut ter-logout.
        abort_if($akun->is($request->user()), 403, 'Gunakan halaman Ganti password untuk akunmu sendiri.');

        $password = PasswordAcak::buat();

        // Wajib ganti saat login berikutnya + keluar dari semua perangkat (SPEC F1).
        DB::transaction(function () use ($akun, $password, $gantiPassword) {
            $this->kunciAkunMasihAda($akun);
            $gantiPassword->jalankan($akun, $password, harusGantiPassword: true);
        });

        return redirect()
            ->route('admin.akun.edit', $akun)
            ->with('status', "Password {$akun->nama} direset.")
            ->with('password_sekali', $this->infoPassword($akun, $password));
    }

    /**
     * Akun bisa saja baru dihapus admin lain di antara buka form dan simpan (SPEC F9).
     * Kunci barisnya; kalau sudah tidak ada → 404, bukan pesan "disimpan" palsu.
     * Kunci ini juga membuat hapus akun bersamaan menunggu sampai simpan ini selesai.
     */
    private function kunciAkunMasihAda(User $akun): void
    {
        abort_unless(User::whereKey($akun->id)->lockForUpdate()->first(), 404);
    }

    /**
     * Batas maks 2 admin per sekolah sudah dicek di SimpanAkunRequest. Dicek ulang di
     * sini dengan baris sekolah dikunci, supaya dua penyimpanan bersamaan tidak
     * menghasilkan admin ke-3 (SPEC F9). Kunci harus sebelum query biasa (tanpa kunci)
     * pertama di transaksi, sama seperti di cekUlangBatasPj.
     *
     * @throws ValidationException
     */
    private function cekUlangBatasAdmin(SimpanAkunRequest $request): void
    {
        if ($request->roleTujuan() !== Role::Admin) {
            return;
        }

        $sekolah = School::whereKey($request->user()->school_id)->lockForUpdate()->firstOrFail();

        if ($sekolah->jumlahAdmin() >= School::MAKS_ADMIN) {
            throw ValidationException::withMessages(['role' => School::PESAN_ADMIN_PENUH]);
        }
    }

    /**
     * Batas maks 2 PJ per kelas sudah dicek di SimpanAkunRequest. Dicek ulang di sini,
     * di dalam transaksi dengan baris kelas dikunci, supaya dua simpan bersamaan tidak
     * sama-sama lolos dan membuat PJ ke-3 (SPEC F9).
     *
     * @throws ValidationException
     */
    private function cekUlangBatasPj(SimpanAkunRequest $request, ?User $akun): void
    {
        if ($request->roleTujuan() !== Role::PjKebersihan) {
            return;
        }

        // Kunci ini harus sebelum query biasa (tanpa kunci) pertama di transaksi: di MySQL
        // (REPEATABLE READ), "foto" data untuk query biasa diambil saat query biasa pertama,
        // jadi hitungan PJ sesudah kunci melihat data terbaru yang sudah di-commit.
        // (SQLite di tes mengabaikan lockForUpdate.)
        $kelas = Kelas::whereKey($request->dataAkun()['class_id'])->lockForUpdate()->firstOrFail();

        if ($kelas->jumlahPjSelain($akun) >= Kelas::MAKS_PJ) {
            throw ValidationException::withMessages(['role' => $kelas->pesanPjPenuh()]);
        }
    }

    /**
     * Isian kembar yang lolos validasi karena penyimpanan bersamaan, lalu ditolak UNIQUE
     * database → pesan validasi biasa (SPEC F9, v0.9). Nama constraint: nama index (MySQL)
     * atau daftar kolom (SQLite, dipakai tes), dicocokkan persis oleh TangkapDuplikat.
     */
    private const DUPLIKAT_AKUN = [
        [['users_nis_unique', 'users.nis'], 'nis', 'NIS sudah dipakai.'],
        [['users_email_unique', 'users.email'], 'email', 'Email sudah dipakai.'],
        [['users_class_id_no_absen_unique', 'users.class_id, users.no_absen'], 'no_absen', 'No. absen ini sudah dipakai di kelas tersebut.'],
    ];

    /**
     * Ditampilkan sekali di halaman berikutnya lewat flash (SPEC F9), lalu hilang dari sesi.
     *
     * @return array{nama: string, login: string, password: string}
     */
    private function infoPassword(User $akun, string $password): array
    {
        return ['nama' => $akun->nama, 'login' => (string) ($akun->nis ?? $akun->email), 'password' => $password];
    }
}
