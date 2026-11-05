<?php

namespace App\Support;

use App\Enums\Tugas;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Minggu acuan halaman jadwal (SPEC F2, v0.15): tanggal Senin minggu (Senin–Minggu)
 * saat form jadwal ditampilkan. Dropdown tugas berarti tugas di minggu ini.
 */
final class MingguAcuan
{
    /** Form yang dibuka lebih lama dari ini harus dimuat ulang (SPEC F2). */
    public const MAKS_MINGGU_LALU = 4;

    public const PESAN_TIDAK_VALID = 'Halaman jadwal sudah terlalu lama dibuka. Muat ulang halaman.';

    /** Senin minggu ini menurut waktu server, WIB (SPEC §6), jam 00:00. */
    public static function seninIni(): CarbonImmutable
    {
        return CarbonImmutable::now(Tugas::ZONA_WAKTU)->startOfWeek(CarbonInterface::MONDAY)->startOfDay();
    }

    /**
     * Teks `YYYY-MM-DD` dari form → tanggal, atau null kalau tidak boleh dipakai:
     * bukan tanggal valid, bukan Senin, setelah Senin minggu ini, atau lebih dari
     * MAKS_MINGGU_LALU minggu sebelum Senin minggu ini (SPEC F2, v0.15).
     */
    public static function dariIsian(mixed $teks): ?CarbonImmutable
    {
        if (! is_string($teks) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $teks)) {
            return null;
        }

        $senin = CarbonImmutable::createFromFormat('!Y-m-d', $teks, Tugas::ZONA_WAKTU);

        // createFromFormat menggeser tanggal yang tidak ada (mis. 2026-02-30), jadi dicek balik.
        if (! $senin instanceof CarbonImmutable || $senin->format('Y-m-d') !== $teks || ! $senin->isMonday()) {
            return null;
        }

        $ini = self::seninIni();
        $palingAwal = $ini->subWeeks(self::MAKS_MINGGU_LALU);

        return $senin->betweenIncluded($palingAwal, $ini) ? $senin : null;
    }

    /** "2–8 Nov"; lintas bulan "30 Nov–6 Des" (SPEC F2). */
    public static function label(CarbonInterface $senin): string
    {
        $minggu = $senin->toImmutable()->addDays(6);

        $awal = $senin->month === $minggu->month
            ? $senin->format('j')
            : $senin->translatedFormat('j M');

        return $awal.'–'.$minggu->translatedFormat('j M');
    }

    /** "Senin, 2 Nov 2026" (format tanggal sama dengan halaman hari libur). */
    public static function labelTanggal(CarbonInterface $tanggal): string
    {
        return $tanggal->translatedFormat('l, j M Y');
    }
}
