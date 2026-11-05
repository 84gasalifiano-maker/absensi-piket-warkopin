<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Token warna di resources/css/app.css (SPEC §11 Langkah 5c): setiap token punya nilai
 * mode terang dan gelap, dan pasangan teks/latar memenuhi kontras WCAG AA di kedua mode.
 * Nilai dibaca langsung dari app.css, jadi mengubah token tanpa memenuhi AA → tes gagal.
 */
class KontrasWarnaTest extends TestCase
{
    /** Teks biasa (WCAG 1.4.3). */
    private const TEKS = 4.5;

    /** Bingkai isian, garis fokus, komponen non-teks (WCAG 1.4.11). */
    private const NON_TEKS = 3.0;

    /**
     * [warna depan, warna latar, rasio minimal, dipakai untuk]
     *
     * @return array<string, array{string, string, float}>
     */
    public static function pasangan(): array
    {
        $hasil = [];
        $tambah = function (string $depan, array $latar, float $minimal) use (&$hasil) {
            foreach ($latar as $l) {
                $hasil["{$depan} di atas {$l}"] = [$depan, $l, $minimal];
            }
        };

        $latarUmum = ['dasar', 'permukaan', 'permukaan-lembut'];
        $tambah('teks', $latarUmum, self::TEKS);
        $tambah('teks-lembut', $latarUmum, self::TEKS);
        $tambah('utama', ['dasar', 'permukaan'], self::TEKS); // tautan
        $tambah('utama-hover', ['dasar', 'permukaan'], self::TEKS); // tautan saat disentuh
        $tambah('bahaya', ['dasar', 'permukaan'], self::TEKS); // pesan error di bawah isian
        $tambah('pada-utama', ['utama', 'utama-hover'], self::TEKS); // tombol utama
        $tambah('pada-bahaya', ['bahaya', 'bahaya-hover'], self::TEKS); // tombol bahaya
        $tambah('sukses', ['sukses-lembut'], self::TEKS);
        $tambah('peringatan', ['peringatan-lembut'], self::TEKS);
        $tambah('bahaya', ['bahaya-lembut'], self::TEKS);
        $tambah('info', ['info-lembut'], self::TEKS);
        $tambah('teks', ['sukses-lembut', 'peringatan-lembut', 'bahaya-lembut', 'info-lembut'], self::TEKS); // isi pesan
        $tambah('garis-isian', $latarUmum, self::NON_TEKS);
        $tambah('fokus', $latarUmum, self::NON_TEKS);
        $tambah('fokus', ['sukses-lembut', 'peringatan-lembut', 'bahaya-lembut', 'info-lembut'], self::NON_TEKS); // tautan di dalam pesan

        return $hasil;
    }

    #[DataProvider('pasangan')]
    public function test_kontras_aa_di_mode_terang_dan_gelap(string $depan, string $latar, float $minimal): void
    {
        foreach (['terang' => self::tokenTerang(), 'gelap' => self::tokenGelap()] as $mode => $token) {
            $this->assertArrayHasKey($depan, $token, "Token {$depan} tidak ada di mode {$mode}");
            $this->assertArrayHasKey($latar, $token, "Token {$latar} tidak ada di mode {$mode}");

            $rasio = self::rasio($token[$depan], $token[$latar]);
            $this->assertGreaterThanOrEqual(
                $minimal,
                round($rasio, 2),
                sprintf('Mode %s: %s (%s) di atas %s (%s) = %.2f:1, minimal %.1f:1', $mode, $depan, $token[$depan], $latar, $token[$latar], $rasio, $minimal),
            );
        }
    }

    public function test_setiap_token_punya_nilai_mode_gelap_dan_sebaliknya(): void
    {
        $terang = self::tokenTerang();
        $gelap = self::tokenGelap();

        $this->assertNotEmpty($terang);
        $this->assertSame(array_keys($terang), array_keys($gelap), 'Token terang dan gelap harus sama dan berurutan sama');

        foreach ($terang as $nama => $nilai) {
            $this->assertNotSame($nilai, $gelap[$nama] ?? null, "Token {$nama} sama di kedua mode, jadi tidak ikut mode gelap");
        }
    }

    /** Pembaca token tidak boleh melewatkan token diam-diam (mis. ditulis #fff atau oklch()). */
    public function test_semua_token_warna_terbaca_sebagai_hex_enam_digit(): void
    {
        $css = self::css();

        $this->assertSame(1, preg_match_all('/@media\s*\(prefers-color-scheme:\s*dark\)/', $css), 'Nilai gelap harus di satu blok @media saja');
        $this->assertSame(1, preg_match_all('/@theme\b/', $css), 'Token terang harus di satu blok @theme saja');

        foreach (['terang' => [self::blokTerang(), self::tokenTerang()], 'gelap' => [self::blokGelap(), self::tokenGelap()]] as $mode => [$blok, $token]) {
            $jumlahDeklarasi = preg_match_all('/--color-[a-z-]+\s*:/', $blok);
            $this->assertSame($jumlahDeklarasi, count($token), "Mode {$mode}: ada token --color- yang bukan #rrggbb");
        }
    }

    public function test_rumus_kontras_sesuai_contoh_wcag(): void
    {
        $this->assertEqualsWithDelta(21.0, self::rasio('#000000', '#ffffff'), 0.01);
        $this->assertEqualsWithDelta(1.0, self::rasio('#777777', '#777777'), 0.01);
        $this->assertEqualsWithDelta(4.48, self::rasio('#777777', '#ffffff'), 0.01); // abu-abu "hampir lolos"
    }

    // --- Pembaca app.css ---

    private static function css(): string
    {
        return (string) file_get_contents(__DIR__.'/../../resources/css/app.css');
    }

    private static function blokTerang(): string
    {
        preg_match('/@theme\b[^{]*\{(.*?)\n\}/s', self::css(), $blok);

        return $blok[1] ?? '';
    }

    private static function blokGelap(): string
    {
        preg_match('/@media\s*\(prefers-color-scheme:\s*dark\)\s*\{(.*?)\n\}/s', self::css(), $blok);

        return $blok[1] ?? '';
    }

    /** @return array<string, string> nama token (tanpa --color-) => #rrggbb */
    private static function tokenTerang(): array
    {
        return self::warna(self::blokTerang());
    }

    /** @return array<string, string> */
    private static function tokenGelap(): array
    {
        return self::warna(self::blokGelap());
    }

    /** @return array<string, string> */
    private static function warna(string $blok): array
    {
        preg_match_all('/--color-([a-z-]+):\s*(#[0-9a-fA-F]{6})\s*;/', $blok, $cocok, PREG_SET_ORDER);

        $hasil = [];
        foreach ($cocok as [, $nama, $hex]) {
            $hasil[$nama] = strtolower($hex);
        }

        return $hasil;
    }

    // --- Rumus WCAG 2.x ---

    private static function rasio(string $a, string $b): float
    {
        [$terang, $gelap] = [max(self::luminans($a), self::luminans($b)), min(self::luminans($a), self::luminans($b))];

        return ($terang + 0.05) / ($gelap + 0.05);
    }

    private static function luminans(string $hex): float
    {
        $kanal = array_map(function (string $dua) {
            $c = hexdec($dua) / 255;

            return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $kanal[0] + 0.7152 * $kanal[1] + 0.0722 * $kanal[2];
    }
}
