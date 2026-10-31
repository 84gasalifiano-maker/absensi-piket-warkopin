<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** SPEC F1: setiap kolom password punya tombol tampilkan/sembunyikan sendiri. */
class TombolLihatPasswordTest extends TestCase
{
    use RefreshDatabase;

    /**
     * ID kolom yang dikendalikan setiap tombol (aria-controls), urut sesuai halaman.
     *
     * @return list<string>
     */
    private function kolomDenganTombol(string $html): array
    {
        preg_match_all('/<button\s[^>]*data-toggle-password[^>]*>/', $html, $tombol);

        return array_map(function (string $tag) {
            // Bukan tombol submit dan default tersembunyi.
            $this->assertStringContainsString('type="button"', $tag);
            $this->assertStringContainsString('aria-label="Tampilkan password"', $tag);
            preg_match('/aria-controls="([^"]+)"/', $tag, $cocok);

            return $cocok[1];
        }, $tombol[0]);
    }

    public function test_tombol_ada_di_halaman_login(): void
    {
        $html = $this->get('/login')->assertOk()->getContent();

        $this->assertSame(['password'], $this->kolomDenganTombol($html));
        $this->assertMatchesRegularExpression('/id="password"\s+name="password"\s+type="password"/', $html);
    }

    public function test_tombol_ada_di_setiap_kolom_ganti_password(): void
    {
        $html = $this->actingAs($this->buatUser(Role::Siswa))
            ->get('/ganti-password')
            ->assertOk()
            ->getContent();

        $this->assertSame(
            ['password_lama', 'password_baru', 'password_baru_confirmation'],
            $this->kolomDenganTombol($html),
        );
    }
}
