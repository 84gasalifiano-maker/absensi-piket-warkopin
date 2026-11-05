<?php

namespace Tests\Unit;

use App\Enums\Tugas;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** SPEC F2 + §6 Aturan: rotasi tugas mingguan dari tanggal_mulai_rotasi. */
class RotasiTugasTest extends TestCase
{
    private const MULAI = '2026-11-02'; // Senin

    private function tugas(Tugas $tugasAwal, string $tanggal): ?Tugas
    {
        return $tugasAwal->padaTanggal(CarbonImmutable::parse(self::MULAI, 'Asia/Jakarta'), CarbonImmutable::parse($tanggal, 'Asia/Jakarta'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function hariDiMingguPertama(): array
    {
        return [
            'Senin (tanggal mulai)' => ['2026-11-02'],
            'Rabu' => ['2026-11-04'],
            'Jumat' => ['2026-11-06'],
            'Minggu (akhir minggu ke-0)' => ['2026-11-08'],
        ];
    }

    #[DataProvider('hariDiMingguPertama')]
    public function test_minggu_yang_memuat_tanggal_mulai_tugas_sama_dengan_tugas_awal(string $tanggal): void
    {
        foreach (Tugas::cases() as $tugasAwal) {
            $this->assertSame($tugasAwal, $this->tugas($tugasAwal, $tanggal));
        }
    }

    /**
     * Tiga tugas awal × empat minggu berturut-turut (Senin tiap minggu).
     *
     * @return array<string, array{Tugas, list<Tugas>}>
     */
    public static function urutanMingguan(): array
    {
        return [
            'mulai menyapu' => [Tugas::Menyapu, [Tugas::Menyapu, Tugas::Merapikan, Tugas::Mengepel, Tugas::Menyapu]],
            'mulai merapikan' => [Tugas::Merapikan, [Tugas::Merapikan, Tugas::Mengepel, Tugas::Menyapu, Tugas::Merapikan]],
            'mulai mengepel' => [Tugas::Mengepel, [Tugas::Mengepel, Tugas::Menyapu, Tugas::Merapikan, Tugas::Mengepel]],
        ];
    }

    /**
     * @param  list<Tugas>  $harapan
     */
    #[DataProvider('urutanMingguan')]
    public function test_setiap_minggu_bergeser_satu_langkah(Tugas $tugasAwal, array $harapan): void
    {
        $senin = ['2026-11-02', '2026-11-09', '2026-11-16', '2026-11-23'];

        foreach ($senin as $i => $tanggal) {
            $this->assertSame($harapan[$i], $this->tugas($tugasAwal, $tanggal), "Minggu ke-{$i} ({$tanggal})");
        }
    }

    public function test_tugas_berganti_tepat_hari_senin(): void
    {
        $this->assertSame(Tugas::Menyapu, $this->tugas(Tugas::Menyapu, '2026-11-08')); // Minggu
        $this->assertSame(Tugas::Merapikan, $this->tugas(Tugas::Menyapu, '2026-11-09')); // Senin
    }

    public function test_contoh_di_spec(): void
    {
        // §6: tugas_awal = merapikan, n = 4 → (1 + 4) mod 3 = 2 → mengepel.
        $this->assertSame(Tugas::Mengepel, $this->tugas(Tugas::Merapikan, '2026-11-30'));
    }

    public function test_jam_diabaikan(): void
    {
        $mulai = CarbonImmutable::parse('2026-11-02 23:59:59', 'Asia/Jakarta');
        $mingguDepan = CarbonImmutable::parse('2026-11-09 00:00:01', 'Asia/Jakarta');

        $this->assertSame(Tugas::Merapikan, Tugas::Menyapu->padaTanggal($mulai, $mingguDepan));
        $this->assertSame(Tugas::Menyapu, Tugas::Menyapu->padaTanggal($mulai, CarbonImmutable::parse('2026-11-08 23:59:59', 'Asia/Jakarta')));
    }

    public function test_sebelum_tanggal_mulai_jadwal_belum_berlaku(): void
    {
        $this->assertNull($this->tugas(Tugas::Menyapu, '2026-11-01')); // Minggu sebelumnya
        $this->assertNull($this->tugas(Tugas::Menyapu, '2026-10-26'));
    }

    public function test_tanggal_mulai_bukan_senin_ditolak(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tanggal mulai rotasi harus hari Senin.');

        Tugas::Menyapu->padaTanggal(CarbonImmutable::parse('2026-11-03', 'Asia/Jakarta'), CarbonImmutable::parse('2026-11-10', 'Asia/Jakarta'));
    }

    public function test_hari_tengah_minggu_di_minggu_berikutnya(): void
    {
        $this->assertSame(Tugas::Merapikan, $this->tugas(Tugas::Menyapu, '2026-11-13')); // Jumat minggu ke-1
        $this->assertSame(Tugas::Merapikan, $this->tugas(Tugas::Menyapu, '2026-11-15')); // Minggu, akhir minggu ke-1
        $this->assertSame(Tugas::Mengepel, $this->tugas(Tugas::Menyapu, '2026-11-18')); // Rabu minggu ke-2
    }

    public function test_tanggal_dihitung_menurut_wib(): void
    {
        $mulai = CarbonImmutable::parse(self::MULAI, 'Asia/Jakarta');

        // Minggu 8 Nov 18:00 UTC = Senin 9 Nov 01:00 WIB → sudah minggu ke-1.
        $this->assertSame(Tugas::Merapikan, Tugas::Menyapu->padaTanggal($mulai, CarbonImmutable::parse('2026-11-08 18:00', 'UTC')));

        // Minggu 8 Nov 16:00 UTC = Minggu 8 Nov 23:00 WIB → masih minggu ke-0.
        $this->assertSame(Tugas::Menyapu, Tugas::Menyapu->padaTanggal($mulai, CarbonImmutable::parse('2026-11-08 16:00', 'UTC')));
    }

    public function test_carbon_mutable_dari_pemanggil_tidak_diubah(): void
    {
        // Mis. hasil cast 'date' di model atau now() yang mutable.
        $mulai = Carbon::parse('2026-11-02 00:00', 'Asia/Jakarta');
        $tanggal = Carbon::parse('2026-11-08 18:00', 'UTC');

        $this->assertSame(Tugas::Merapikan, Tugas::Menyapu->padaTanggal($mulai, $tanggal));
        $this->assertSame('UTC', $tanggal->getTimezone()->getName());
        $this->assertSame('2026-11-08 18:00:00', $tanggal->toDateTimeString());
        $this->assertSame('Asia/Jakarta', $mulai->getTimezone()->getName());
        $this->assertSame('2026-11-02 00:00:00', $mulai->toDateTimeString());
    }

    public function test_hari_senin_tanggal_mulai_dicek_menurut_wib(): void
    {
        $tanggal = CarbonImmutable::parse('2026-11-16', 'Asia/Jakarta');

        // Minggu 1 Nov 18:00 UTC = Senin 2 Nov 01:00 WIB → diterima sebagai Senin.
        $this->assertSame(Tugas::Mengepel, Tugas::Menyapu->padaTanggal(CarbonImmutable::parse('2026-11-01 18:00', 'UTC'), $tanggal));

        // Senin 2 Nov 18:00 UTC = Selasa 3 Nov 01:00 WIB → bukan Senin, ditolak.
        $this->expectException(InvalidArgumentException::class);
        Tugas::Menyapu->padaTanggal(CarbonImmutable::parse('2026-11-02 18:00', 'UTC'), $tanggal);
    }

    public function test_tahun_kabisat(): void
    {
        $mulai = CarbonImmutable::parse('2028-02-28', 'Asia/Jakarta'); // Senin; 29 Feb 2028 ada

        $this->assertSame(Tugas::Menyapu, Tugas::Menyapu->padaTanggal($mulai, CarbonImmutable::parse('2028-02-29', 'Asia/Jakarta')));
        $this->assertSame(Tugas::Menyapu, Tugas::Menyapu->padaTanggal($mulai, CarbonImmutable::parse('2028-03-05', 'Asia/Jakarta'))); // Minggu, minggu ke-0
        $this->assertSame(Tugas::Merapikan, Tugas::Menyapu->padaTanggal($mulai, CarbonImmutable::parse('2028-03-06', 'Asia/Jakarta'))); // Senin, minggu ke-1
    }

    public function test_tetap_benar_setelah_bertahun_tahun(): void
    {
        // 2026-11-02 + 156 minggu = 2029-10-29; 156 mod 3 = 0 → kembali ke tugas awal.
        $this->assertSame(Tugas::Menyapu, $this->tugas(Tugas::Menyapu, '2029-10-29'));
        $this->assertSame(Tugas::Merapikan, $this->tugas(Tugas::Menyapu, '2029-11-05'));
    }

    // --- Hitung mundur dari "tugas minggu ini" (SPEC F2, v0.15) ---

    /** Minggu acuan ke-0 s.d. ke-6 × ketiga pilihan: tugas_awal hasil hitung mundur, lalu maju lagi = pilihan. */
    public function test_hitung_mundur_kebalikan_rotasi(): void
    {
        $mulai = CarbonImmutable::parse(self::MULAI, 'Asia/Jakarta');

        for ($minggu = 0; $minggu <= 6; $minggu++) {
            $acuan = $mulai->addWeeks($minggu);

            foreach (Tugas::cases() as $pilihan) {
                $awal = Tugas::awalDari($pilihan, $mulai, $acuan);

                $this->assertSame($pilihan, $awal->padaTanggal($mulai, $acuan), "Minggu ke-{$minggu}, pilih {$pilihan->value}");
                $this->assertSame($pilihan, $awal->diMingguAcuan($mulai, $acuan));
            }
        }
    }

    public function test_hitung_mundur_contoh_konkret(): void
    {
        $mulai = CarbonImmutable::parse(self::MULAI, 'Asia/Jakarta');

        // Minggu ke-0: sama. Minggu ke-1 (9 Nov): pilih merapikan → awal menyapu.
        $this->assertSame(Tugas::Mengepel, Tugas::awalDari(Tugas::Mengepel, $mulai, $mulai));
        $this->assertSame(Tugas::Menyapu, Tugas::awalDari(Tugas::Merapikan, $mulai, CarbonImmutable::parse('2026-11-09', 'Asia/Jakarta')));
        // Minggu ke-2 (16 Nov): pilih mengepel → awal menyapu; pilih menyapu → awal merapikan.
        $this->assertSame(Tugas::Menyapu, Tugas::awalDari(Tugas::Mengepel, $mulai, CarbonImmutable::parse('2026-11-16', 'Asia/Jakarta')));
        $this->assertSame(Tugas::Merapikan, Tugas::awalDari(Tugas::Menyapu, $mulai, CarbonImmutable::parse('2026-11-16', 'Asia/Jakarta')));
    }

    public function test_minggu_acuan_sebelum_tanggal_mulai_disimpan_apa_adanya(): void
    {
        $mulai = CarbonImmutable::parse(self::MULAI, 'Asia/Jakarta');
        $sebelum = CarbonImmutable::parse('2026-10-26', 'Asia/Jakarta');

        foreach (Tugas::cases() as $pilihan) {
            $this->assertSame($pilihan, Tugas::awalDari($pilihan, $mulai, $sebelum));
            $this->assertSame($pilihan, $pilihan->diMingguAcuan($mulai, $sebelum));
        }
    }
}
