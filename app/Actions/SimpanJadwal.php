<?php

namespace App\Actions;

use App\Enums\Role;
use App\Enums\Tugas;
use App\Models\DutySchedule;
use App\Models\Kelas;
use App\Models\User;
use App\Support\RingkasanJadwal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Simpan jadwal piket satu kelas sekaligus (SPEC F2, §11 Langkah 8, v0.15).
 *
 * Hanya siswa yang ada di form yang diubah; siswa lain di kelas itu (mis. baru dipindah
 * ke kelas ini setelah form dibuka) dibiarkan, tetapi jadwalnya ikut dihitung dalam batas.
 */
class SimpanJadwal
{
    public const PESAN_ANGGOTA_BERUBAH = 'Daftar siswa kelas ini baru saja berubah. Muat ulang halaman.';

    /**
     * @param  User  $oleh  PJ, guru, atau admin yang menyimpan
     * @param  CarbonImmutable  $mingguAcuan  Senin minggu saat form ditampilkan (sudah divalidasi)
     * @param  array<int, array{hari: int|null, tugas: Tugas|null}>  $isian  user_id => pilihan; tugas = tugas di minggu acuan
     *
     * @throws ValidationException kalau batas 9/3 terlampaui atau ada siswa yang bukan anggota kelas
     * @throws NotFoundHttpException kalau kelas bukan haknya / sudah dihapus
     */
    public function jalankan(User $oleh, int $kelasId, CarbonImmutable $mingguAcuan, array $isian): void
    {
        // Kelas bukan haknya → 404 sebelum isian diperiksa, supaya pesan di bawah tidak bisa
        // dipakai menebak id akun mana yang ada (review Langkah 8). Dicek ulang di transaksi.
        abort_unless(Kelas::whereKey($kelasId)->jadwalBolehDikelolaOleh($oleh)->exists(), 404);

        // Cek awal tanpa kunci (review Langkah 8): id akun yang tidak ada langsung ditolak.
        // Di MySQL, kunci baca lewat primary key untuk id yang tidak ada mengunci "celah"
        // antar-id; kalau id itu lebih besar dari id terbesar, penambahan akun baru (tambah
        // akun PJ, import: kunci kelas → INSERT akun) bisa saling menunggu dengan simpan ini.
        // Keanggotaan tetap dicek ulang dengan baris terkunci di bawah.
        if (User::whereKey(array_keys($isian))->count() !== count($isian)) {
            throw ValidationException::withMessages(['jadwal' => self::PESAN_ANGGOTA_BERUBAH]);
        }

        // attempts: 3 → kalau bentrok kunci (deadlock / lock wait timeout), transaksi diulang dari
        // awal; Laravel me-rollback percobaan sebelumnya, jadi aman diulang (SPEC §11 Langkah 8).
        DB::transaction(function () use ($oleh, $kelasId, $mingguAcuan, $isian) {
            // Urutan kunci SPEC §11 Langkah 8: akun → kelas, sama dengan pindah kelas
            // (AkunController::update: akun → kelas tujuan), supaya tidak saling menunggu
            // selamanya. Kedua kunci sebelum bacaan biasa pertama: di MySQL (REPEATABLE READ)
            // bacaan sesudahnya baru melihat data terbaru. (SQLite di tes mengabaikan kunci.)

            // 1. Akun siswa di form (+ PJ yang menyimpan), kunci baca bersama lewat primary key,
            //    urut id. Kunci ini tidak dihindari: menambah baris jadwal toh mengunci baca baris
            //    akun itu (pemeriksaan foreign key InnoDB). Sesama simpan jadwal tidak saling
            //    menghalangi; pindah kelas / hapus akun / reset password siswa itu menunggu.
            $idAkun = array_keys($isian);
            if ($oleh->role === Role::PjKebersihan) {
                $idAkun[] = $oleh->id;
            }
            $akun = User::whereKey(array_unique($idAkun))->orderBy('id')->sharedLock()->get()->keyBy('id');

            // 2 + 3. Kunci baris kelas, sekaligus cek ulang hak akses dari data terbaru
            //    (guru: wali_kelas_id baris yang dikunci; PJ: class_id & role akunnya yang
            //    dikunci di atas). Kelas bukan haknya atau sudah dihapus → 404 (SPEC F2).
            $pelaku = $oleh->role === Role::PjKebersihan ? $akun->get($oleh->id) : $oleh;
            abort_unless($pelaku, 404);

            $kelas = Kelas::whereKey($kelasId)->jadwalBolehDikelolaOleh($pelaku)->lockForUpdate()->first();
            abort_unless($kelas, 404);

            // 4. Setiap siswa di form harus masih anggota kelas ini (baris akun yang dikunci).
            //    Kalau tidak → seluruh simpan ditolak, tidak ada yang berubah (SPEC F2).
            foreach (array_keys($isian) as $userId) {
                $siswa = $akun->get($userId);

                if (! $siswa
                    || $siswa->class_id !== $kelas->id
                    || ! in_array($siswa->role, [Role::Siswa, Role::PjKebersihan], true)) {
                    throw ValidationException::withMessages(['jadwal' => self::PESAN_ANGGOTA_BERUBAH]);
                }
            }

            // 5. Gabungkan dengan jadwal tersimpan, cek batas pada hasil akhir, lalu tulis.
            $tersimpan = DutySchedule::where('class_id', $kelas->id)->get()->keyBy('user_id');
            $mulai = $kelas->tanggal_mulai_rotasi;

            $hasilAkhir = [];
            foreach ($tersimpan as $userId => $jadwal) {
                if (! array_key_exists($userId, $isian)) {
                    $hasilAkhir[] = ['hari' => $jadwal->hari, 'tugas' => $jadwal->tugas_awal->diMingguAcuan($mulai, $mingguAcuan)];
                }
            }
            foreach ($isian as $pilihan) {
                if ($pilihan['hari'] !== null) {
                    $hasilAkhir[] = $pilihan;
                }
            }

            $pesan = (new RingkasanJadwal($hasilAkhir))->pesanMelebihi();
            if ($pesan !== []) {
                throw ValidationException::withMessages(['jadwal' => $pesan]);
            }

            foreach ($isian as $userId => $pilihan) {
                $jadwal = $tersimpan->get($userId);

                if ($pilihan['hari'] === null) {
                    $jadwal?->delete(); // "— Belum terjadwal —"

                    continue;
                }

                $jadwal ??= new DutySchedule(['class_id' => $kelas->id, 'user_id' => $userId]);
                $jadwal->fill([
                    'hari' => $pilihan['hari'],
                    // Dropdown = tugas di minggu acuan; disimpan sebagai tugas_awal (SPEC F2, v0.15).
                    'tugas_awal' => Tugas::awalDari($pilihan['tugas'], $mulai, $mingguAcuan),
                ])->save();
            }
        }, attempts: 3);
    }
}
