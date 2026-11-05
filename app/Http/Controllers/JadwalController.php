<?php

namespace App\Http\Controllers;

use App\Actions\SimpanJadwal;
use App\Enums\Role;
use App\Enums\Tugas;
use App\Http\Requests\SimpanJadwalRequest;
use App\Models\Kelas;
use App\Models\User;
use App\Support\MingguAcuan;
use App\Support\RingkasanJadwal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Kelola jadwal piket (SPEC F2, §7, §8.1, v0.15). Untuk PJ, guru, admin (middleware role).
 * Kelas yang boleh dibuka: Kelas::jadwalBolehDikelolaOleh(); selain itu 404.
 */
class JadwalController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $daftarKelas = Kelas::query()->jadwalBolehDikelolaOleh($user)->orderBy('nama')->get();
        $kelas = $this->kelasDipilih($user, $request->query('kelas'), $daftarKelas);

        if (! $kelas) {
            return view('jadwal.pilih-kelas', ['daftarKelas' => $daftarKelas, 'guru' => $user->role === Role::Guru]);
        }

        $mingguAcuan = MingguAcuan::seninIni();
        $mulai = $kelas->tanggal_mulai_rotasi;
        $sebelumMulai = $mingguAcuan->lessThan($mulai);
        $anggota = $kelas->anggota()->get();
        $tersimpan = $kelas->dutySchedules()->get()->keyBy('user_id');

        // Nilai dropdown per siswa dari data tersimpan: hari + tugas di minggu acuan.
        $nilai = [];
        foreach ($anggota as $siswa) {
            $jadwal = $tersimpan->get($siswa->id);
            $nilai[$siswa->id] = [
                'hari' => $jadwal?->hari,
                'tugas' => $jadwal?->tugas_awal->diMingguAcuan($mulai, $mingguAcuan),
            ];
        }
        $belumTerjadwal = $anggota->filter(fn (User $siswa) => $nilai[$siswa->id]['hari'] === null);

        // Setelah validasi gagal: tampilkan isian yang dikirim, dan ringkasan dihitung dari
        // isian itu (SPEC §8.1). Tugas di isian berarti tugas di minggu acuan form itu; kalau
        // minggunya sudah berganti (form dibuka Minggu, disimpan Senin), diterjemahkan ke
        // minggu ini lewat rumus rotasi yang sama. Minggu acuan yang tidak valid (pesan
        // "muat ulang") → isian tidak dipakai, form tampil dengan data terbaru.
        $acuanLama = MingguAcuan::dariIsian($request->old('minggu_acuan'));
        $isianLama = $acuanLama ? $request->old('jadwal') : null;
        if (is_array($isianLama)) {
            foreach ($anggota as $siswa) {
                if (is_array($isianLama[$siswa->id] ?? null)) {
                    $nilai[$siswa->id] = $this->nilaiDariIsian($isianLama[$siswa->id], $mulai, $acuanLama, $mingguAcuan);
                }
            }
        }

        // Ringkasan = siswa di form + jadwal tersimpan siswa lain di kelas ini (sama dengan
        // hasil akhir yang dicek SimpanJadwal).
        $barisRingkasan = [];
        foreach ($tersimpan as $userId => $jadwal) {
            if (! array_key_exists($userId, $nilai)) {
                $barisRingkasan[] = ['hari' => $jadwal->hari, 'tugas' => $jadwal->tugas_awal->diMingguAcuan($mulai, $mingguAcuan)];
            }
        }
        // Siswa yang sudah punya hari tetap dihitung di hari itu walau tugasnya belum dipilih
        // (mis. 10/9 tetap terlihat); rincian per tugas hanya untuk tugas yang valid.
        foreach ($nilai as $pilihan) {
            if ($pilihan['hari'] !== null) {
                $barisRingkasan[] = $pilihan;
            }
        }

        return view('jadwal.index', [
            'kelas' => $kelas,
            'bisaPilihKelasLain' => $user->role === Role::Admin || $daftarKelas->count() > 1,
            'mingguAcuan' => $mingguAcuan,
            'labelTanggalMulai' => MingguAcuan::labelTanggal($mulai),
            'labelTugas' => $sebelumMulai
                ? 'Tugas mulai '.MingguAcuan::labelTanggal($mulai)
                : 'Tugas minggu ini ('.MingguAcuan::label($mingguAcuan).')',
            'judulRingkasan' => $sebelumMulai
                ? 'Mulai '.MingguAcuan::labelTanggal($mulai)
                : 'Minggu ini ('.MingguAcuan::label($mingguAcuan).')',
            'anggota' => $anggota,
            'nilai' => $nilai,
            'adaJadwal' => $tersimpan->isNotEmpty(),
            'belumTerjadwal' => $belumTerjadwal,
            'ringkasan' => new RingkasanJadwal($barisRingkasan),
        ]);
    }

    public function store(SimpanJadwalRequest $request, SimpanJadwal $simpanJadwal): RedirectResponse
    {
        $user = $request->user();

        // PJ: selalu kelasnya sendiri, isian kelas diabaikan (SPEC F2). Lainnya: dari form.
        $kelasId = $user->role === Role::PjKebersihan ? $user->class_id : $this->idKelas($request->input('kelas'));
        abort_unless($kelasId, 404);

        // Hak akses atas kelas dicek di dalam transaksi, dari baris yang dikunci.
        $simpanJadwal->jalankan($user, $kelasId, $request->mingguAcuan(), $request->isian());

        return redirect()
            ->route('jadwal', $user->role === Role::PjKebersihan ? [] : ['kelas' => $kelasId])
            ->with('status', 'Jadwal tersimpan.');
    }

    /**
     * Kelas yang dibuka (SPEC F2, v0.15), atau null = tampilkan halaman pilih kelas.
     * PJ: kelasnya sendiri. Guru 1 kelas: langsung. ?kelas= yang bukan haknya → 404.
     *
     * @param  Collection<int, Kelas>  $daftarKelas  kelas yang boleh dikelola $user
     */
    private function kelasDipilih(User $user, mixed $kelas, Collection $daftarKelas): ?Kelas
    {
        if ($user->role === Role::PjKebersihan) {
            return $daftarKelas->first() ?? abort(404);
        }

        if ($kelas !== null) {
            $id = $this->idKelas($kelas);

            return ($id ? $daftarKelas->firstWhere('id', $id) : null) ?? abort(404);
        }

        return $user->role === Role::Guru && $daftarKelas->count() === 1 ? $daftarKelas->first() : null;
    }

    private function idKelas(mixed $nilai): ?int
    {
        if (is_int($nilai)) {
            return $nilai > 0 ? $nilai : null;
        }

        return is_string($nilai) && ctype_digit($nilai) ? (int) $nilai : null;
    }

    /**
     * Isian lama satu siswa → nilai dropdown di minggu ini. Nilai yang tidak dikenal
     * ditampilkan kosong. Tugas diterjemahkan dari minggu acuan form ke minggu ini.
     *
     * @param  array<mixed>  $baris
     * @return array{hari: int|null, tugas: Tugas|null}
     */
    private function nilaiDariIsian(array $baris, CarbonInterface $mulai, CarbonInterface $acuanLama, CarbonInterface $mingguIni): array
    {
        $hari = $baris['hari'] ?? null;
        $hari = (is_int($hari) || is_string($hari)) && array_key_exists((string) $hari, RingkasanJadwal::HARI) ? (int) $hari : null;
        $tugas = is_string($baris['tugas'] ?? null) ? Tugas::tryFrom($baris['tugas']) : null;

        return [
            'hari' => $hari,
            'tugas' => $tugas ? Tugas::awalDari($tugas, $mulai, $acuanLama)->diMingguAcuan($mulai, $mingguIni) : null,
        ];
    }
}
