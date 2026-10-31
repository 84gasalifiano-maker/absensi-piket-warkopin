<?php

namespace Tests\Feature\Auth;

use App\Actions\GantiPassword;
use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/** SPEC F1: setiap kali password berubah, sesi & "Ingat saya" di perangkat lain ikut keluar. */
class KeluarPerangkatLainTest extends TestCase
{
    use RefreshDatabase;

    private const INPUT_VALID = [
        'password_lama' => 'password',
        'password_baru' => 'rahasia-baru',
        'password_baru_confirmation' => 'rahasia-baru',
    ];

    /** Pura-pura ada sesi login di perangkat lain (baris di tabel sessions). */
    private function buatSesi(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '10.0.0.2',
            'user_agent' => 'HP lain',
            'payload' => '',
            'last_activity' => now()->getTimestamp(),
        ]);
    }

    public function test_sesi_di_perangkat_lain_dihapus_perangkat_ini_tetap_login(): void
    {
        $siswa = $this->buatUser(Role::Siswa);
        $siswaLain = $this->buatUser(Role::Siswa);
        $this->buatSesi('sesi-hp-lain', $siswa);
        $this->buatSesi('sesi-siswa-lain', $siswaLain);

        $this->actingAs($siswa)
            ->post('/ganti-password', self::INPUT_VALID)
            ->assertRedirect('/beranda');

        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-hp-lain']);
        $this->assertDatabaseHas('sessions', ['id' => 'sesi-siswa-lain']); // user lain tidak terpengaruh
        $this->assertAuthenticatedAs($siswa);
        $this->get('/beranda')->assertOk();
    }

    public function test_cookie_ingat_saya_lama_tidak_berlaku_lagi(): void
    {
        $siswa = $this->buatUser(Role::Siswa, ['remember_token' => 'token-lama']);
        $cookieLama = $siswa->id.'|token-lama|'.$siswa->password; // format cookie Laravel: id|token|hash

        // Sebelum ganti password: cookie lama bisa membuka halaman tanpa login.
        $this->withCookie($this->namaCookieIngatSaya(), $cookieLama)
            ->get('/beranda')
            ->assertOk();

        // Ganti password dari perangkat lain (tanpa cookie lama).
        $this->mulaiPerangkatBaru();
        $this->actingAs($siswa->fresh())
            ->post('/ganti-password', self::INPUT_VALID)
            ->assertRedirect('/beranda');

        $this->assertNotSame('token-lama', $siswa->fresh()->remember_token);

        // Perangkat pertama datang lagi dengan cookie lama: sudah tidak dikenali.
        $this->mulaiPerangkatBaru();
        $this->withCookie($this->namaCookieIngatSaya(), $cookieLama)
            ->get('/beranda')
            ->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_perangkat_ini_tetap_diingat_kalau_sebelumnya_dicentang(): void
    {
        $siswa = $this->buatUser(Role::Siswa, ['remember_token' => 'token-lama']);

        $response = $this->actingAs($siswa)
            ->withCookie($this->namaCookieIngatSaya(), $siswa->id.'|token-lama|'.$siswa->password)
            ->post('/ganti-password', self::INPUT_VALID)
            ->assertCookieNotExpired($this->namaCookieIngatSaya());

        // Cookie baru memakai token baru, bukan token lama.
        $tokenBaru = $siswa->fresh()->remember_token;
        $this->assertNotSame('token-lama', $tokenBaru);
        $this->assertStringStartsWith(
            $siswa->id.'|'.$tokenBaru.'|',
            $response->getCookie($this->namaCookieIngatSaya())->getValue(),
        );
    }

    /**
     * Cookie "Ingat saya" yang tidak berlaku tetap tersimpan di browser. Cookie
     * seperti itu tidak boleh membuat perangkat ini ikut "diingat" (SPEC F1:
     * default tidak dicentang), misalnya di komputer lab yang dipakai bergantian.
     */
    public function test_cookie_basi_atau_milik_user_lain_tidak_membuat_perangkat_diingat(): void
    {
        $siswaA = $this->buatUser(Role::Siswa, ['remember_token' => 'token-a']);
        $siswaB = $this->buatUser(Role::Siswa, ['remember_token' => 'token-b']);

        $cookieTidakBerlaku = [
            'milik user lain' => $siswaA->id.'|token-a|'.$siswaA->password,
            'token sudah basi' => $siswaB->id.'|token-sudah-diganti|'.$siswaB->password,
            'isi rusak' => 'bukan-cookie-laravel',
        ];

        foreach ($cookieTidakBerlaku as $kasus => $isi) {
            $this->mulaiPerangkatBaru();
            $siswaB->forceFill(['password' => 'password', 'remember_token' => 'token-b'])->save();

            $response = $this->actingAs($siswaB)
                ->withCookie($this->namaCookieIngatSaya(), $isi)
                ->post('/ganti-password', self::INPUT_VALID)
                ->assertRedirect('/beranda');

            $this->assertNull(
                $response->getCookie($this->namaCookieIngatSaya(), decrypt: false),
                "Kasus '{$kasus}': perangkat tidak boleh ikut diingat.",
            );
        }
    }

    public function test_perangkat_ini_tidak_diingat_kalau_sebelumnya_tidak_dicentang(): void
    {
        $this->actingAs($this->buatUser(Role::Siswa))
            ->post('/ganti-password', self::INPUT_VALID)
            ->assertCookieMissing($this->namaCookieIngatSaya());
    }

    /** Pemakaian untuk reset password oleh admin (Langkah 3): keluar dari SEMUA perangkat. */
    public function test_action_untuk_reset_admin_mengeluarkan_dari_semua_perangkat(): void
    {
        $siswa = $this->buatUser(Role::Siswa, ['remember_token' => 'token-lama']);
        $this->buatSesi('sesi-hp', $siswa);
        $this->buatSesi('sesi-laptop', $siswa);

        app(GantiPassword::class)->jalankan($siswa, 'reset-dari-admin', harusGantiPassword: true);

        $siswa->refresh();
        $this->assertTrue(Hash::check('reset-dari-admin', $siswa->password));
        $this->assertTrue($siswa->harus_ganti_password);
        $this->assertNotSame('token-lama', $siswa->remember_token);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $siswa->id)->count());
    }

    /** Request berikutnya seperti dari browser lain: tanpa cookie, sesi, atau user login. */
    private function mulaiPerangkatBaru(): void
    {
        $this->defaultCookies = [];
        $this->flushSession();
        $this->app['auth']->forgetGuards();
    }
}
