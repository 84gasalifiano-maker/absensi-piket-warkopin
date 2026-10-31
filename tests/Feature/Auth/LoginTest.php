<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use Illuminate\Auth\SessionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** SPEC F1: login NIS/email, redirect per role, batas percobaan, "Ingat saya", logout. */
class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function namaCookieIngatSaya(): string
    {
        /** @var SessionGuard $guard */
        $guard = Auth::guard('web');

        return $guard->getRecallerName();
    }

    public function test_halaman_login_tampil_dengan_ingat_saya_tidak_dicentang(): void
    {
        $html = $this->get('/login')
            ->assertOk()
            ->assertSee('NIS atau email')
            ->assertSee('Ingat saya')
            ->getContent();

        $this->assertMatchesRegularExpression('/<input type="checkbox" name="remember"[^>]*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<input type="checkbox" name="remember"[^>]*checked[^>]*>/', $html);
    }

    /**
     * @return array<string, array{Role, string}>
     */
    public static function roleDanHalamanAwal(): array
    {
        return [
            'siswa' => [Role::Siswa, '/beranda'],
            'pj kebersihan' => [Role::PjKebersihan, '/beranda'],
            'guru' => [Role::Guru, '/verifikasi'],
            'admin' => [Role::Admin, '/admin'],
        ];
    }

    #[DataProvider('roleDanHalamanAwal')]
    public function test_login_berhasil_diarahkan_sesuai_role(Role $role, string $halamanAwal): void
    {
        $user = $this->buatUser($role);
        $login = in_array($role, Role::loginPakaiNis(), true) ? $user->nis : $user->email;

        $this->post('/login', ['login' => $login, 'password' => 'password'])
            ->assertRedirect($halamanAwal);

        $this->assertAuthenticatedAs($user);
    }

    public function test_email_tidak_peka_huruf_besar_kecil(): void
    {
        $guru = $this->buatUser(Role::Guru, ['email' => 'guru@absensi.test']);

        $this->post('/login', ['login' => '  GURU@Absensi.test ', 'password' => 'password'])
            ->assertRedirect('/verifikasi');

        $this->assertAuthenticatedAs($guru);
    }

    public function test_siswa_tidak_bisa_login_pakai_email(): void
    {
        $this->buatUser(Role::Siswa, ['email' => 'siswa@absensi.test']);

        $this->from('/login')
            ->post('/login', ['login' => 'siswa@absensi.test', 'password' => 'password'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['login' => 'NIS/email atau password salah.']);

        $this->assertGuest();
    }

    public function test_guru_tidak_bisa_login_pakai_nis(): void
    {
        $this->buatUser(Role::Guru, ['nis' => '123456']);

        $this->post('/login', ['login' => '123456', 'password' => 'password'])
            ->assertSessionHasErrors(['login' => 'NIS/email atau password salah.']);

        $this->assertGuest();
    }

    public function test_password_salah_ditolak(): void
    {
        $siswa = $this->buatUser(Role::Siswa);

        $this->post('/login', ['login' => $siswa->nis, 'password' => 'salah'])
            ->assertSessionHasErrors(['login' => 'NIS/email atau password salah.']);

        $this->assertGuest();
    }

    public function test_field_wajib_diisi(): void
    {
        $this->post('/login', [])
            ->assertSessionHasErrors([
                'login' => 'NIS atau email wajib diisi.',
                'password' => 'Password wajib diisi.',
            ]);
    }

    public function test_diblokir_setelah_lima_kali_gagal_dalam_semenit(): void
    {
        $siswa = $this->buatUser(Role::Siswa);

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['login' => $siswa->nis, 'password' => 'salah']);
        }

        // Percobaan ke-6 diblokir walau password benar.
        $this->post('/login', ['login' => $siswa->nis, 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertStringContainsString('Terlalu banyak percobaan login', session('errors')->first('login'));
        $this->assertGuest();

        // Batas dihitung per NIS/email + IP: NIS lain dari IP yang sama tetap bisa login.
        $siswaLain = $this->buatUser(Role::Siswa);
        $this->post('/login', ['login' => $siswaLain->nis, 'password' => 'password'])
            ->assertRedirect('/beranda');

        // Setelah semenit, NIS pertama bisa login lagi.
        Auth::logout();
        $this->travel(61)->seconds();
        $this->post('/login', ['login' => $siswa->nis, 'password' => 'password'])
            ->assertRedirect('/beranda');
    }

    public function test_ingat_saya_dicentang_memberi_cookie_pengingat(): void
    {
        $siswa = $this->buatUser(Role::Siswa);

        $this->post('/login', ['login' => $siswa->nis, 'password' => 'password', 'remember' => '1'])
            ->assertCookie($this->namaCookieIngatSaya());

        $this->assertNotNull($siswa->fresh()->remember_token);
    }

    public function test_ingat_saya_tidak_dicentang_tanpa_cookie_pengingat(): void
    {
        $siswa = $this->buatUser(Role::Siswa);

        $this->post('/login', ['login' => $siswa->nis, 'password' => 'password'])
            ->assertCookieMissing($this->namaCookieIngatSaya());
    }

    public function test_user_yang_sudah_login_dialihkan_dari_halaman_login(): void
    {
        $this->actingAs($this->buatUser(Role::Guru))
            ->get('/login')
            ->assertRedirect('/verifikasi');
    }

    public function test_logout(): void
    {
        $this->actingAs($this->buatUser(Role::Siswa))
            ->post('/logout')
            ->assertRedirect('/login');

        $this->assertGuest();
    }

    public function test_halaman_utama_mengalihkan_tamu_ke_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_halaman_utama_mengalihkan_user_ke_halaman_awal(): void
    {
        $this->actingAs($this->buatUser(Role::Admin))
            ->get('/')
            ->assertRedirect('/admin');
    }
}
