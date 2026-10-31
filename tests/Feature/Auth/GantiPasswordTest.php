<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** SPEC F1: wajib ganti password saat login pertama + aturan password baru. */
class GantiPasswordTest extends TestCase
{
    use RefreshDatabase;

    private function userWajibGanti(): User
    {
        return User::factory()->role(Role::Siswa)->create(); // default factory: wajib ganti
    }

    public function test_login_pertama_dipaksa_ke_ganti_password(): void
    {
        $siswa = $this->userWajibGanti();

        $this->post('/login', ['login' => $siswa->nis, 'password' => 'password'])
            ->assertRedirect('/beranda');

        $this->get('/beranda')->assertRedirect('/ganti-password');
        $this->get('/ganti-password')
            ->assertOk()
            ->assertSee('Kamu wajib mengganti password dulu sebelum bisa lanjut.');
    }

    public function test_selama_wajib_ganti_semua_halaman_dialihkan(): void
    {
        $this->actingAs(User::factory()->role(Role::Admin)->create());

        $this->get('/admin')->assertRedirect('/ganti-password');
        $this->get('/beranda')->assertRedirect('/ganti-password');
    }

    public function test_tetap_bisa_logout_walau_wajib_ganti(): void
    {
        $this->actingAs($this->userWajibGanti())
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_ganti_password_berhasil(): void
    {
        $siswa = $this->userWajibGanti();

        $this->actingAs($siswa)
            ->post('/ganti-password', [
                'password_lama' => 'password',
                'password_baru' => 'rahasia-baru',
                'password_baru_confirmation' => 'rahasia-baru',
            ])
            ->assertRedirect('/beranda')
            ->assertSessionHas('status', 'Password berhasil diganti.');

        $siswa->refresh();
        $this->assertFalse($siswa->harus_ganti_password);
        $this->assertTrue(Hash::check('rahasia-baru', $siswa->password));

        $this->get('/beranda')->assertOk();
    }

    public function test_user_biasa_juga_bisa_ganti_password(): void
    {
        $guru = $this->buatUser(Role::Guru);

        $this->actingAs($guru)
            ->get('/ganti-password')
            ->assertOk()
            ->assertDontSee('Kamu wajib mengganti password');

        $this->post('/ganti-password', [
            'password_lama' => 'password',
            'password_baru' => 'rahasia-baru',
            'password_baru_confirmation' => 'rahasia-baru',
        ])->assertRedirect('/verifikasi');
    }

    /**
     * @return array<string, array{array<string, string>, string, string}>
     */
    public static function inputTidakValid(): array
    {
        return [
            'password lama salah' => [
                ['password_lama' => 'salah', 'password_baru' => 'rahasia-baru', 'password_baru_confirmation' => 'rahasia-baru'],
                'password_lama', 'Password lama salah.',
            ],
            'kurang dari 8 karakter' => [
                ['password_lama' => 'password', 'password_baru' => 'pendek', 'password_baru_confirmation' => 'pendek'],
                'password_baru', 'Password baru minimal berisi 8 karakter.',
            ],
            'sama dengan password lama' => [
                ['password_lama' => 'password', 'password_baru' => 'password', 'password_baru_confirmation' => 'password'],
                'password_baru', 'Password baru harus berbeda dari password lama.',
            ],
            'konfirmasi tidak cocok' => [
                ['password_lama' => 'password', 'password_baru' => 'rahasia-baru', 'password_baru_confirmation' => 'rahasia-lain'],
                'password_baru', 'Konfirmasi Password baru tidak cocok.',
            ],
        ];
    }

    /**
     * @param  array<string, string>  $input
     */
    #[DataProvider('inputTidakValid')]
    public function test_input_tidak_valid_ditolak(array $input, string $field, string $pesan): void
    {
        $siswa = $this->userWajibGanti();

        $this->actingAs($siswa)
            ->from('/ganti-password')
            ->post('/ganti-password', $input)
            ->assertRedirect('/ganti-password')
            ->assertSessionHasErrors([$field => $pesan]);

        $siswa->refresh();
        $this->assertTrue($siswa->harus_ganti_password);
        $this->assertTrue(Hash::check('password', $siswa->password));
    }
}
