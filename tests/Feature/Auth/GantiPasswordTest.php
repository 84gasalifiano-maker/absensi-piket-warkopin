<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
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

    public function test_route_baru_otomatis_ikut_wajib_ganti_password(): void
    {
        // Route baru yang tidak menyebut middleware wajib ganti password sama sekali.
        Route::middleware(['web', 'auth'])->get('/route-baru-untuk-tes', fn () => 'isi halaman');

        $this->actingAs($this->userWajibGanti())
            ->get('/route-baru-untuk-tes')
            ->assertRedirect('/ganti-password');

        $this->actingAs($this->buatUser(Role::Siswa))
            ->get('/route-baru-untuk-tes')
            ->assertOk()
            ->assertSee('isi halaman');
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

    public function test_pesan_sukses_tampil_lewat_layout(): void
    {
        // Halaman tujuan (/beranda sementara) tidak menulis pesan ini sendiri;
        // yang menampilkan adalah layout, jadi halaman asli nanti juga ikut.
        $this->actingAs($this->userWajibGanti())
            ->followingRedirects()
            ->post('/ganti-password', [
                'password_lama' => 'password',
                'password_baru' => 'rahasia-baru',
                'password_baru_confirmation' => 'rahasia-baru',
            ])
            ->assertOk()
            ->assertSee('Password berhasil diganti.');
    }

    public function test_diblokir_setelah_lima_kali_percobaan_dalam_semenit(): void
    {
        $siswa = $this->userWajibGanti();
        $salah = ['password_lama' => 'tebakan', 'password_baru' => 'rahasia-baru', 'password_baru_confirmation' => 'rahasia-baru'];

        for ($i = 0; $i < 5; $i++) {
            $this->actingAs($siswa)->from('/ganti-password')->post('/ganti-password', $salah)
                ->assertSessionHasErrors(['password_lama' => 'Password lama salah.']);
        }

        // Percobaan ke-6 diblokir walau password lama benar.
        $this->from('/ganti-password')
            ->post('/ganti-password', ['password_lama' => 'password'] + $salah)
            ->assertRedirect('/ganti-password')
            ->assertSessionHasErrors('password_lama');

        $this->assertStringContainsString(
            'Terlalu banyak percobaan ganti password',
            session('errors')->first('password_lama'),
        );
        $this->assertTrue(Hash::check('password', $siswa->fresh()->password));

        // Batas dihitung per user: user lain (di perangkatnya sendiri) tetap bisa ganti password.
        $this->flushSession();
        $this->actingAs($this->userWajibGanti())
            ->post('/ganti-password', ['password_lama' => 'password'] + $salah)
            ->assertRedirect('/beranda');

        // Setelah semenit, user pertama bisa mencoba lagi (kembali di perangkatnya sendiri).
        // Sesi dikosongkan: tiap user punya sesi sendiri, dan AuthenticateSession
        // mengeluarkan sesi yang menyimpan sidik password user lain.
        $this->travel(61)->seconds();
        $this->flushSession();
        $this->actingAs($siswa)
            ->post('/ganti-password', ['password_lama' => 'password'] + $salah)
            ->assertRedirect('/beranda');
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
            // User di tes ini wajib ganti → teks "dari admin" untuk semua ganti wajib (v0.11).
            // Ganti biasa ("harus berbeda dari password lama") dites di GantiPasswordWajibTest.
            'sama dengan password saat ini' => [
                ['password_lama' => 'password', 'password_baru' => 'password', 'password_baru_confirmation' => 'password'],
                'password_baru', 'Password baru tidak boleh sama dengan password dari admin.',
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
