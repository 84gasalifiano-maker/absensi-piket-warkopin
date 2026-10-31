<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** SPEC bagian 7: middleware auth + role di setiap grup route. */
class AksesRoleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{string}>
     */
    public static function halamanTerlindungi(): array
    {
        return [
            'beranda' => ['/beranda'],
            'verifikasi' => ['/verifikasi'],
            'admin' => ['/admin'],
            'ganti password' => ['/ganti-password'],
        ];
    }

    #[DataProvider('halamanTerlindungi')]
    public function test_tamu_dialihkan_ke_login(string $url): void
    {
        $this->get($url)->assertRedirect('/login');
    }

    /**
     * Matriks role × halaman sesuai tabel route SPEC bagian 7.
     *
     * @return array<string, array{Role, string, int}>
     */
    public static function matriksAkses(): array
    {
        return [
            'siswa → beranda' => [Role::Siswa, '/beranda', 200],
            'siswa → verifikasi' => [Role::Siswa, '/verifikasi', 403],
            'siswa → admin' => [Role::Siswa, '/admin', 403],
            'pj → beranda' => [Role::PjKebersihan, '/beranda', 200],
            'pj → verifikasi' => [Role::PjKebersihan, '/verifikasi', 200],
            'pj → admin' => [Role::PjKebersihan, '/admin', 403],
            'guru → beranda' => [Role::Guru, '/beranda', 403],
            'guru → verifikasi' => [Role::Guru, '/verifikasi', 200],
            'guru → admin' => [Role::Guru, '/admin', 403],
            'admin → beranda' => [Role::Admin, '/beranda', 403],
            'admin → verifikasi' => [Role::Admin, '/verifikasi', 403],
            'admin → admin' => [Role::Admin, '/admin', 200],
        ];
    }

    #[DataProvider('matriksAkses')]
    public function test_akses_sesuai_role(Role $role, string $url, int $status): void
    {
        $response = $this->actingAs($this->buatUser($role))->get($url);

        $response->assertStatus($status);

        if ($status === 403) {
            $response->assertSee('Kamu tidak punya akses ke halaman ini.');
        }
    }
}
