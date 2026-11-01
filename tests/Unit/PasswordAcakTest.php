<?php

namespace Tests\Unit;

use App\Support\PasswordAcak;
use PHPUnit\Framework\TestCase;

/** SPEC F9: password acak 8 karakter huruf kecil + angka, tanpa 0 o 1 l i, ada huruf & angka. */
class PasswordAcakTest extends TestCase
{
    public function test_selalu_sesuai_aturan(): void
    {
        for ($i = 0; $i < 1000; $i++) {
            $password = PasswordAcak::buat();

            $this->assertMatchesRegularExpression('/^[a-z2-9]{8}$/', $password);
            $this->assertDoesNotMatchRegularExpression('/[01oli]/', $password);
            $this->assertMatchesRegularExpression('/[a-z]/', $password, "Tanpa huruf: {$password}");
            $this->assertMatchesRegularExpression('/[2-9]/', $password, "Tanpa angka: {$password}");
        }
    }

    public function test_daftar_karakter_tidak_memuat_karakter_mirip(): void
    {
        foreach (['0', 'o', '1', 'l', 'i'] as $mirip) {
            $this->assertStringNotContainsString($mirip, PasswordAcak::HURUF.PasswordAcak::ANGKA);
        }
        $this->assertSame(23, strlen(PasswordAcak::HURUF));
        $this->assertSame(8, strlen(PasswordAcak::ANGKA));
    }

    public function test_hasilnya_acak(): void
    {
        $hasil = array_map(fn () => PasswordAcak::buat(), range(1, 500));

        $this->assertCount(500, array_unique($hasil));
    }
}
