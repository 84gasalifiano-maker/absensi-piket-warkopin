<?php

namespace App\Enums;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use InvalidArgumentException;

/**
 * Jenis tugas piket (SPEC F2). Urutan case = urutan rotasi mingguan:
 * menyapu → merapikan → mengepel → menyapu.
 */
enum Tugas: string
{
    case Menyapu = 'menyapu';
    case Merapikan = 'merapikan';
    case Mengepel = 'mengepel';

    /**
     * Hari dan minggu dihitung menurut WIB (SPEC §6), apa pun zona objek tanggalnya.
     * Harus sama dengan config('app.timezone') — dijaga tes ZonaWaktuTest.
     */
    public const ZONA_WAKTU = 'Asia/Jakarta';

    /** Label tampilan (SPEC F2). */
    public function label(): string
    {
        return match ($this) {
            self::Menyapu => 'Menyapu',
            self::Merapikan => 'Merapikan meja & kursi',
            self::Mengepel => 'Mengepel',
        };
    }

    /**
     * Rotasi tugas (SPEC §6 Aturan). Satu-satunya tempat rumus ini dihitung.
     *
     * $this = tugas_awal siswa, yaitu tugasnya di minggu ke-0. Minggu = Senin–Minggu;
     * minggu ke-0 dimulai di $mulaiRotasi (selalu hari Senin). Setiap Senin
     * tugas bergeser satu langkah. Hari libur tidak menghentikan hitungan.
     *
     * Hasil null: $tanggal sebelum $mulaiRotasi, artinya jadwal belum berlaku (F2).
     *
     * @throws InvalidArgumentException kalau $mulaiRotasi bukan hari Senin.
     */
    public function padaTanggal(CarbonInterface $mulaiRotasi, CarbonInterface $tanggal): ?self
    {
        $mulai = self::tanggalWib($mulaiRotasi);
        $hari = self::tanggalWib($tanggal);

        if (! $mulai->isMonday()) {
            throw new InvalidArgumentException('Tanggal mulai rotasi harus hari Senin.');
        }

        if ($hari->lessThan($mulai)) {
            return null;
        }

        $mingguKe = intdiv((int) $mulai->diffInDays($hari), 7);
        $urutan = self::cases();
        $posisiAwal = array_search($this, $urutan, true);

        return $urutan[($posisiAwal + $mingguKe) % count($urutan)];
    }

    /**
     * Tugas siswa di minggu acuan (SPEC F2, v0.15), untuk dropdown & ringkasan jadwal.
     * $this = tugas_awal. Minggu acuan sebelum $mulaiRotasi belum punya "minggu ini",
     * jadi yang ditampilkan adalah tugas minggu ke-0, yaitu tugas_awal itu sendiri.
     */
    public function diMingguAcuan(CarbonInterface $mulaiRotasi, CarbonInterface $mingguAcuan): self
    {
        return $this->padaTanggal($mulaiRotasi, $mingguAcuan) ?? $this;
    }

    /**
     * Kebalikan diMingguAcuan(): tugas_awal yang membuat siswa mendapat $pilihan di
     * minggu acuan (SPEC F2, v0.15). Tidak menulis rumus kedua: dicari di antara ketiga
     * tugas mana yang hasil padaTanggal()-nya sama dengan $pilihan.
     * Minggu acuan sebelum $mulaiRotasi → $pilihan disimpan apa adanya.
     */
    public static function awalDari(self $pilihan, CarbonInterface $mulaiRotasi, CarbonInterface $mingguAcuan): self
    {
        foreach (self::cases() as $calon) {
            if ($calon->diMingguAcuan($mulaiRotasi, $mingguAcuan) === $pilihan) {
                return $calon;
            }
        }

        // Tidak mungkin terjadi: rotasi menggeser semua tugas, jadi selalu ada tepat satu calon.
        throw new \LogicException('Tugas awal tidak ditemukan.');
    }

    /**
     * Tanggal kalender di zona waktu aplikasi (Asia/Jakarta, SPEC §6), jam diabaikan.
     * Lewat salinan immutable supaya objek milik pemanggil tidak ikut berubah.
     */
    private static function tanggalWib(CarbonInterface $waktu): CarbonImmutable
    {
        return CarbonImmutable::parse(
            $waktu->toImmutable()->setTimezone(self::ZONA_WAKTU)->toDateString(),
            self::ZONA_WAKTU,
        );
    }
}
