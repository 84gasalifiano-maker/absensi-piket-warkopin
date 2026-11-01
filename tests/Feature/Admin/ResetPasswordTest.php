<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** SPEC F9 + F1: reset password oleh admin. */
class ResetPasswordTest extends AdminTestCase
{
    public function test_reset_password_siswa(): void
    {
        $siswa = $this->siswa();
        $siswa->forceFill(['remember_token' => 'token-lama'])->save();
        DB::table('sessions')->insert([
            'id' => 'sesi-hp-siswa', 'user_id' => $siswa->id, 'ip_address' => null,
            'user_agent' => null, 'payload' => '', 'last_activity' => now()->getTimestamp(),
        ]);

        $this->actingAs($this->admin)->post("/admin/akun/{$siswa->id}/reset-password")
            ->assertRedirect("/admin/akun/{$siswa->id}/edit")
            ->assertSessionHas('status', "Password {$siswa->nama} direset.");

        $password = session('password_sekali')['password'];
        $this->assertPasswordAcakSesuaiAturan($password);
        $this->assertSame($siswa->nis, session('password_sekali')['login']);

        $siswa->refresh();
        $this->assertTrue(Hash::check($password, $siswa->password));
        $this->assertTrue($siswa->harus_ganti_password);

        // Keluar dari semua perangkat (F1).
        $this->assertNotSame('token-lama', $siswa->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-hp-siswa']);

        // Admin sendiri tetap login.
        $this->assertAuthenticatedAs($this->admin);
        $this->get("/admin/akun/{$siswa->id}/edit")->assertOk()->assertSee($password);
    }

    public function test_reset_password_admin_lain_boleh(): void
    {
        $adminLain = $this->buatUser(Role::Admin, ['school_id' => $this->sekolah->id]);

        $this->actingAs($this->admin)->post("/admin/akun/{$adminLain->id}/reset-password")
            ->assertRedirect("/admin/akun/{$adminLain->id}/edit");

        $this->assertTrue($adminLain->fresh()->harus_ganti_password);
    }

    public function test_admin_tidak_bisa_mereset_akunnya_sendiri(): void
    {
        $this->actingAs($this->admin)->get("/admin/akun/{$this->admin->id}/edit")
            ->assertOk()
            ->assertSee('Ini akunmu sendiri')
            ->assertDontSee('reset-password');

        $this->actingAs($this->admin)->post("/admin/akun/{$this->admin->id}/reset-password")
            ->assertForbidden();

        $this->assertTrue(Hash::check('password', $this->admin->fresh()->password));
        $this->assertFalse($this->admin->fresh()->harus_ganti_password);
    }
}
