<?php

namespace App\Support;

/**
 * Password awal & hasil reset oleh admin (SPEC F9):
 * 8 karakter huruf kecil + angka, tanpa karakter mirip (0, o, 1, l, i),
 * minimal 1 huruf dan 1 angka. Memakai random_int (acak yang aman).
 */
final class PasswordAcak
{
    public const HURUF = 'abcdefghjkmnpqrstuvwxyz'; // tanpa i, l, o

    public const ANGKA = '23456789'; // tanpa 0, 1

    public const PANJANG = 8;

    public static function buat(): string
    {
        // Satu huruf + satu angka dijamin ada, sisanya bebas dari gabungan keduanya.
        $karakter = [self::satuDari(self::HURUF), self::satuDari(self::ANGKA)];

        while (count($karakter) < self::PANJANG) {
            $karakter[] = self::satuDari(self::HURUF.self::ANGKA);
        }

        // Acak urutan (Fisher–Yates dengan random_int; shuffle() bawaan PHP tidak aman
        // untuk password) supaya huruf & angka wajib tidak selalu di depan.
        for ($i = count($karakter) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$karakter[$i], $karakter[$j]] = [$karakter[$j], $karakter[$i]];
        }

        return implode('', $karakter);
    }

    private static function satuDari(string $pilihan): string
    {
        return $pilihan[random_int(0, strlen($pilihan) - 1)];
    }
}
