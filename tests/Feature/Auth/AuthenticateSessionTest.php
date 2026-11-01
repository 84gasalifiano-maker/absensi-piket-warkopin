<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * SPEC F1: sesi yang dibuat dengan password lama ikut keluar, walaupun sesi
 * tidak disimpan di database (di tes ini SESSION_DRIVER=array).
 */
class AuthenticateSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_sesi_lama_keluar_setelah_password_berubah_di_tempat_lain(): void
    {
        $siswa = $this->buatUser(Role::Siswa);

        $this->actingAs($siswa)->get('/beranda')->assertOk(); // sidik password tersimpan di sesi

        // Password diganti dari perangkat lain / direset admin.
        $siswa->forceFill(['password' => 'password-baru-dari-tempat-lain'])->save();

        $this->get('/beranda')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_sesi_tetap_berlaku_kalau_password_tidak_berubah(): void
    {
        $siswa = $this->buatUser(Role::Siswa);

        $this->actingAs($siswa)->get('/beranda')->assertOk();
        $this->get('/beranda')->assertOk();
        $this->assertAuthenticatedAs($siswa);
    }
}
