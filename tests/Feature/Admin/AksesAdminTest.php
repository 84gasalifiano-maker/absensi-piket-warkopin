<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;

/** SPEC §7 + F9: semua /admin/* hanya untuk admin, dan hanya data sekolahnya sendiri. */
class AksesAdminTest extends AdminTestCase
{
    /**
     * Semua route admin. {k} = id kelas, {a} = id akun.
     *
     * @return list<array{string, string}>
     */
    private function semuaRouteAdmin(): array
    {
        return [
            ['GET', '/admin'],
            ['GET', '/admin/kelas'],
            ['POST', '/admin/kelas'],
            ['GET', '/admin/kelas/{k}/edit'],
            ['PUT', '/admin/kelas/{k}'],
            ['DELETE', '/admin/kelas/{k}'],
            ['GET', '/admin/akun'],
            ['POST', '/admin/akun'],
            ['GET', '/admin/akun/baru'],
            ['GET', '/admin/akun/{a}/edit'],
            ['PUT', '/admin/akun/{a}'],
            ['POST', '/admin/akun/{a}/reset-password'],
            ['DELETE', '/admin/akun/{a}'],
        ];
    }

    private function url(string $pola, int $kelasId, int $akunId): string
    {
        return str_replace(['{k}', '{a}'], [$kelasId, $akunId], $pola);
    }

    public function test_daftar_route_di_tes_ini_lengkap(): void
    {
        $routeAdmin = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($route) => Str::startsWith($route->uri(), 'admin'))
            ->flatMap(fn ($route) => collect($route->methods())->reject(fn ($m) => $m === 'HEAD')
                ->map(fn ($m) => $m.' /'.preg_replace(['/\{kelas\}/', '/\{akun\}/'], ['{k}', '{a}'], $route->uri())))
            ->sort()->values()->all();

        $diTes = collect($this->semuaRouteAdmin())->map(fn ($r) => $r[0].' '.$r[1])->sort()->values()->all();

        $this->assertSame($routeAdmin, $diTes, 'Route admin baru harus ditambahkan ke tes akses ini.');
    }

    /**
     * @return array<string, array{Role}>
     */
    public static function roleBukanAdmin(): array
    {
        return ['siswa' => [Role::Siswa], 'pj' => [Role::PjKebersihan], 'guru' => [Role::Guru]];
    }

    #[DataProvider('roleBukanAdmin')]
    public function test_role_lain_mendapat_403_di_semua_route_admin(Role $role): void
    {
        $user = $role === Role::Guru ? $this->guru() : $this->siswa($role);
        $akun = $this->siswa();

        foreach ($this->semuaRouteAdmin() as [$method, $pola]) {
            $this->actingAs($user)
                ->call($method, $this->url($pola, $this->kelas->id, $akun->id), ['_token' => csrf_token()])
                ->assertForbidden();
        }
    }

    /**
     * Role dicek sebelum data dicari: ID yang tidak ada / milik sekolah lain tetap 403,
     * bukan 404. Kalau beda, non-admin bisa menebak ID mana yang ada.
     */
    #[DataProvider('roleBukanAdmin')]
    public function test_role_lain_mendapat_403_walau_id_tidak_ada(Role $role): void
    {
        $user = $role === Role::Guru ? $this->guru() : $this->siswa($role);
        $kelasLain = $this->kelasBaru('XI RPL 1', \App\Models\School::factory()->create());

        foreach ($this->semuaRouteAdmin() as [$method, $pola]) {
            foreach ([[99999, 99999], [$kelasLain->id, $this->admin->id + 1000]] as [$kelasId, $akunId]) {
                $this->actingAs($user)
                    ->call($method, $this->url($pola, $kelasId, $akunId))
                    ->assertForbidden();
            }
        }
    }

    public function test_id_bukan_angka_tidak_cocok_dengan_route(): void
    {
        $this->actingAs($this->admin)->get("/admin/kelas/{$this->kelas->id}abc/edit")->assertNotFound();
        $this->actingAs($this->admin)->get("/admin/akun/{$this->admin->id}abc/edit")->assertNotFound();
    }

    public function test_tamu_dialihkan_ke_login(): void
    {
        foreach ($this->semuaRouteAdmin() as [$method, $pola]) {
            $this->call($method, $this->url($pola, $this->kelas->id, $this->admin->id))
                ->assertRedirect('/login');
        }
    }

    public function test_data_sekolah_lain_tidak_ditemukan(): void
    {
        $sekolahLain = \App\Models\School::factory()->create();
        $kelasLain = $this->kelasBaru('XI RPL 1', $sekolahLain);
        $akunLain = $this->siswa(Role::Siswa, $kelasLain);

        foreach ($this->semuaRouteAdmin() as [$method, $pola]) {
            if (! Str::contains($pola, ['{k}', '{a}'])) {
                continue;
            }

            $this->actingAs($this->admin)
                ->call($method, $this->url($pola, $kelasLain->id, $akunLain->id), [
                    'nama' => 'Diubah', 'tanggal_mulai_rotasi' => '2026-11-02', 'role' => 'siswa',
                ])
                ->assertNotFound();
        }

        $this->assertDatabaseHas('classes', ['id' => $kelasLain->id, 'nama' => 'XI RPL 1']);
        $this->assertDatabaseHas('users', ['id' => $akunLain->id, 'nama' => $akunLain->nama]);
    }

    /** SPEC F9 (v0.9): halaman 404 berbahasa Indonesia dengan tautan kembali. */
    public function test_halaman_404_bahasa_indonesia(): void
    {
        $tautanKembali = 'href="'.url('/').'"';

        // Data yang tidak ada / sudah dihapus, saat login.
        $this->actingAs($this->admin)->get('/admin/akun/99999/edit')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee('Mungkin sudah dihapus')
            ->assertSee($tautanKembali, false)
            ->assertDontSee('Not Found');

        // Alamat yang tidak ada, tanpa login.
        auth()->logout();
        $this->get('/alamat-tidak-ada')
            ->assertNotFound()
            ->assertSee('Halaman tidak ditemukan')
            ->assertSee($tautanKembali, false);
    }

    public function test_daftar_tidak_menampilkan_data_sekolah_lain(): void
    {
        $sekolahLain = \App\Models\School::factory()->create();
        $this->kelasBaru('XII TKJ Sekolah Lain', $sekolahLain);
        $guruLain = $this->guru($sekolahLain);

        $this->actingAs($this->admin)->get('/admin')->assertDontSee('XII TKJ Sekolah Lain');
        $this->actingAs($this->admin)->get('/admin/kelas')->assertDontSee('XII TKJ Sekolah Lain');
        $this->actingAs($this->admin)->get('/admin/akun')->assertDontSee($guruLain->email);
    }
}
