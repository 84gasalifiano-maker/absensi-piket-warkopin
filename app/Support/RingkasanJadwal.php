<?php

namespace App\Support;

use App\Enums\Tugas;

/**
 * Isi jadwal satu kelas per hari dan per hari × tugas (SPEC F2, §6, §8.1).
 *
 * Dipakai untuk ringkasan di layar DAN untuk cek batas saat simpan, supaya angka yang
 * dilihat PJ sama dengan yang dicek server. Tugas dihitung dalam istilah minggu acuan;
 * hasilnya sama dengan menghitung tugas_awal, karena semua siswa satu kelas bergeser
 * bersama (SPEC §6 "Validasi jadwal").
 */
final class RingkasanJadwal
{
    public const MAKS_PER_HARI = 9;

    public const MAKS_PER_TUGAS = 3;

    /** Hari piket (SPEC F2): 1 = Senin … 5 = Jumat. Tidak ada Sabtu/Minggu. */
    public const HARI = [1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat'];

    /** @var array<int, int> */
    private array $perHari = [];

    /** @var array<int, array<string, int>> */
    private array $perTugas = [];

    /**
     * @param  iterable<array{hari: int, tugas: Tugas|null}>  $jadwal  satu baris per siswa yang terjadwal;
     *                                                                 tugas null (belum dipilih, hanya di layar saat error) dihitung di hari saja
     */
    public function __construct(iterable $jadwal)
    {
        foreach ($jadwal as ['hari' => $hari, 'tugas' => $tugas]) {
            $this->perHari[$hari] = ($this->perHari[$hari] ?? 0) + 1;

            if ($tugas !== null) {
                $this->perTugas[$hari][$tugas->value] = ($this->perTugas[$hari][$tugas->value] ?? 0) + 1;
            }
        }
    }

    public function jumlahHari(int $hari): int
    {
        return $this->perHari[$hari] ?? 0;
    }

    public function jumlahTugas(int $hari, Tugas $tugas): int
    {
        return $this->perTugas[$hari][$tugas->value] ?? 0;
    }

    public function hariMelebihi(int $hari): bool
    {
        return $this->jumlahHari($hari) > self::MAKS_PER_HARI;
    }

    public function tugasMelebihi(int $hari, Tugas $tugas): bool
    {
        return $this->jumlahTugas($hari, $tugas) > self::MAKS_PER_TUGAS;
    }

    /**
     * Pesan untuk setiap batas yang terlampaui, teks SPEC F2.
     *
     * @return list<string>
     */
    public function pesanMelebihi(): array
    {
        $pesan = [];

        foreach (self::HARI as $hari => $namaHari) {
            if ($this->hariMelebihi($hari)) {
                $pesan[] = "Hari {$namaHari} sudah penuh (maks ".self::MAKS_PER_HARI.' siswa)';
            }

            foreach (Tugas::cases() as $tugas) {
                if ($this->tugasMelebihi($hari, $tugas)) {
                    $pesan[] = "Tugas {$tugas->label()} di hari {$namaHari} sudah ".self::MAKS_PER_TUGAS.' siswa';
                }
            }
        }

        return $pesan;
    }
}
