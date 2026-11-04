<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TambahHariLiburRequest;
use App\Models\HariLibur;
use App\Models\School;
use App\Models\Submission;
use App\Support\TangkapDuplikat;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Kelola hari libur sekolah oleh admin (SPEC F8, §7, §8.1, v0.12). Tidak ada edit:
 * hapus lalu tambah lagi. {hariLibur} di URL hanya menemukan hari libur di sekolah
 * admin (Route::bind di AppServiceProvider); hari libur sekolah lain → 404.
 */
class HariLiburController extends Controller
{
    public const PESAN_ADA_SUBMIT = 'Tanggal ini sudah punya submit piket, jadi tidak bisa dijadikan hari libur.';

    /**
     * Tanggal kembar dari dua penyimpanan bersamaan → pesan validasi (SPEC F8, v0.9).
     * Nama constraint: nama index (MySQL) atau daftar kolom (SQLite, dipakai tes).
     */
    private const DUPLIKAT_HARI_LIBUR = [
        [['hari_libur_school_id_tanggal_unique', 'hari_libur.school_id, hari_libur.tanggal'], 'tanggal', TambahHariLiburRequest::PESAN_SUDAH_TERDAFTAR],
    ];

    public function index(Request $request): View
    {
        // Hari ini dan yang akan datang di atas (terdekat dulu), lalu tanggal lampau
        // (terbaru dulu) (SPEC §8.1, v0.12). "Hari ini" menurut jam server (Asia/Jakarta).
        $hariIni = now()->toDateString();
        [$mendatang, $lampau] = $request->user()->school->hariLibur()
            ->orderBy('tanggal')
            ->get()
            ->partition(fn (HariLibur $libur) => $libur->tanggal->toDateString() >= $hariIni);

        return view('admin.hari-libur.index', [
            'daftarLibur' => $mendatang->concat($lampau->reverse())->values(),
        ]);
    }

    public function store(TambahHariLiburRequest $request): RedirectResponse
    {
        $sekolah = $request->user()->school;
        $data = $request->validated();

        TangkapDuplikat::jalankan(fn () => DB::transaction(function () use ($sekolah, $data) {
            // Kunci baris sekolah dulu, sebelum bacaan biasa pertama. Submit (Langkah 9)
            // mengunci baris yang sama (sharedLock) sebelum mengecek hari libur, jadi submit
            // yang masuk bersamaan menunggu atau ditunggu, dan cek di bawah melihat data terbaru
            // (SPEC §11 Langkah 9). Sekolah tidak pernah dihapus, jadi barisnya selalu ada.
            School::whereKey($sekolah->id)->lockForUpdate()->first();

            if ($this->adaSubmit($sekolah, $data['tanggal'])) {
                throw ValidationException::withMessages(['tanggal' => self::PESAN_ADA_SUBMIT]);
            }

            // Lewat relasi sekolah admin: school_id tidak fillable (SPEC §6).
            $sekolah->hariLibur()->create($data);
        }), self::DUPLIKAT_HARI_LIBUR);

        return redirect()->route('admin.hari-libur.index')->with('status', 'Hari libur ditambahkan.');
    }

    public function destroy(Request $request, HariLibur $hariLibur): RedirectResponse
    {
        // Dihapus langsung dengan syarat id + sekolah. 0 baris = baru saja dihapus admin
        // lain → 404, sama seperti hapus kelas. Tidak perlu kunci: hapus tidak bisa
        // melanggar aturan apa pun (tanggal lampau pun boleh, SPEC F8).
        $terhapus = HariLibur::whereKey($hariLibur->id)
            ->where('school_id', $request->user()->school_id)
            ->delete();

        abort_if($terhapus === 0, 404);

        return redirect()->route('admin.hari-libur.index')->with('status', 'Hari libur dihapus.');
    }

    /**
     * Submit apa pun statusnya, dari kelas mana pun di sekolah ini, di tanggal itu (SPEC F8).
     * whereDate: lihat komentar cast tanggal di model HariLibur.
     */
    private function adaSubmit(School $sekolah, string $tanggal): bool
    {
        return Submission::query()
            ->whereDate('tanggal', $tanggal)
            ->whereIn('class_id', $sekolah->kelas()->select('id'))
            ->exists();
    }
}
