<?php

namespace Tests\Feature\Auth;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SPEC F1 + §11 Langkah 5b (v0.11): ganti password wajib tanpa password lama,
 * hanya di sesi yang login dengan mengetik password.
 */
class GantiPasswordWajibTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD_BARU = [
        'password_baru' => 'rahasia-baru',
        'password_baru_confirmation' => 'rahasia-baru',
    ];

    /** Akun baru dari admin: wajib ganti, password "password" (factory). */
    private function userWajibGanti(Role $role = Role::Siswa, array $atribut = []): User
    {
        return User::factory()->role($role)->create($atribut);
    }

    /** Login dengan mengetik password lewat form login (POST /login). */
    private function loginDenganPassword(User $user): void
    {
        $this->post('/login', [
            'login' => $user->nis ?? $user->email,
            'password' => 'password',
        ])->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    /** Sesi baru yang dipulihkan dari cookie "Ingat saya" (tanpa POST /login). */
    private function loginDariCookieIngatSaya(User $user): void
    {
        $user->forceFill(['remember_token' => 'token-ingat'])->save();

        // Request pertama dengan cookie saja: Laravel memulihkan login dari cookie.
        $this->withCookie($this->namaCookieIngatSaya(), $user->id.'|token-ingat|'.$user->password)
            ->get('/ganti-password')
            ->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    /** ID kolom yang punya tombol tampilkan/sembunyikan, urut sesuai halaman. */
    private function kolomDenganTombol(string $html): array
    {
        preg_match_all('/<button\s[^>]*data-toggle-password[^>]*aria-controls="([^"]+)"/', $html, $cocok);

        return $cocok[1];
    }

    private function assertPasswordTidakBerubah(User $user): void
    {
        $user->refresh();
        $this->assertTrue(Hash::check('password', $user->password), 'Password tidak boleh berubah.');
    }

    // ---- Login dengan mengetik password + wajib ganti: tanpa password lama ----

    /** @return array<string, array{Role, string}> */
    public static function semuaRole(): array
    {
        return [
            'siswa' => [Role::Siswa, '/beranda'],
            'pj' => [Role::PjKebersihan, '/beranda'],
            'guru' => [Role::Guru, '/verifikasi'],
            'admin' => [Role::Admin, '/admin'],
        ];
    }

    #[DataProvider('semuaRole')]
    public function test_login_dengan_password_ganti_wajib_tanpa_password_lama(Role $role, string $halamanAwal): void
    {
        $user = $this->userWajibGanti($role);
        $this->loginDenganPassword($user);

        // Sesi lain milik user ini (perangkat lain) dan milik user lain.
        $userLain = $this->buatUser(Role::Siswa);
        foreach (['sesi-hp-lain' => $user, 'sesi-user-lain' => $userLain] as $id => $pemilik) {
            DB::table('sessions')->insert([
                'id' => $id, 'user_id' => $pemilik->id, 'ip_address' => '10.0.0.2',
                'user_agent' => 'HP lain', 'payload' => '', 'last_activity' => now()->getTimestamp(),
            ]);
        }

        $html = $this->get('/ganti-password')
            ->assertOk()
            ->assertSee('Kamu wajib mengganti password dulu sebelum bisa lanjut.')
            ->getContent();
        $this->assertStringNotContainsString('name="password_lama"', $html);
        $this->assertStringNotContainsString('Password lama', $html);

        $this->post('/ganti-password', self::PASSWORD_BARU)
            ->assertRedirect($halamanAwal)
            ->assertSessionHas('status', 'Password berhasil diganti.');

        $user->refresh();
        $this->assertFalse($user->harus_ganti_password);
        $this->assertTrue(Hash::check('rahasia-baru', $user->password));

        // Perangkat lain keluar, perangkat ini tetap login, user lain tidak terpengaruh.
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-hp-lain']);
        $this->assertDatabaseHas('sessions', ['id' => 'sesi-user-lain']);
        $this->assertAuthenticatedAs($user);
        $this->get($halamanAwal)->assertOk();
    }

    public function test_tombol_lihat_hanya_di_kolom_yang_tampil(): void
    {
        $this->loginDenganPassword($this->userWajibGanti());

        $html = $this->get('/ganti-password')->assertOk()->getContent();

        $this->assertSame(['password_baru', 'password_baru_confirmation'], $this->kolomDenganTombol($html));
    }

    public function test_password_lama_yang_tetap_dikirim_diabaikan(): void
    {
        $siswa = $this->userWajibGanti();
        $this->loginDenganPassword($siswa);

        // Password lama salah pun tetap berhasil: isian ini tidak diminta.
        $this->post('/ganti-password', ['password_lama' => 'salah-total'] + self::PASSWORD_BARU)
            ->assertRedirect('/beranda')
            ->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('rahasia-baru', $siswa->fresh()->password));
    }

    public function test_ganti_wajib_password_baru_sama_dengan_password_dari_admin_ditolak(): void
    {
        $siswa = $this->userWajibGanti();
        $this->loginDenganPassword($siswa);

        $this->from('/ganti-password')
            ->post('/ganti-password', ['password_baru' => 'password', 'password_baru_confirmation' => 'password'])
            ->assertRedirect('/ganti-password')
            ->assertSessionHasErrors(['password_baru' => 'Password baru tidak boleh sama dengan password dari admin.']);

        $this->assertTrue($siswa->fresh()->harus_ganti_password);
        $this->assertPasswordTidakBerubah($siswa);
    }

    /** @return array<string, array{array<string, string>, string}> */
    public static function passwordBaruTidakValid(): array
    {
        return [
            'kurang dari 8 karakter' => [
                ['password_baru' => 'pendek', 'password_baru_confirmation' => 'pendek'],
                'Password baru minimal berisi 8 karakter.',
            ],
            'konfirmasi tidak cocok' => [
                ['password_baru' => 'rahasia-baru', 'password_baru_confirmation' => 'rahasia-lain'],
                'Konfirmasi Password baru tidak cocok.',
            ],
            'kosong' => [
                ['password_baru' => '', 'password_baru_confirmation' => ''],
                'Password baru wajib diisi.',
            ],
        ];
    }

    #[DataProvider('passwordBaruTidakValid')]
    public function test_ganti_wajib_aturan_password_baru_tetap_berlaku(array $input, string $pesan): void
    {
        $siswa = $this->userWajibGanti();
        $this->loginDenganPassword($siswa);

        $this->from('/ganti-password')
            ->post('/ganti-password', $input)
            ->assertSessionHasErrors(['password_baru' => $pesan]);

        $this->assertTrue($siswa->fresh()->harus_ganti_password);
        $this->assertPasswordTidakBerubah($siswa);
    }

    public function test_ganti_wajib_percobaan_keenam_dalam_semenit_ditolak(): void
    {
        $siswa = $this->userWajibGanti();
        $this->loginDenganPassword($siswa);
        $salah = ['password_baru' => 'pendek', 'password_baru_confirmation' => 'pendek'];

        for ($i = 0; $i < 5; $i++) {
            $this->from('/ganti-password')->post('/ganti-password', $salah)
                ->assertSessionHasErrors('password_baru');
        }

        // Percobaan ke-6 diblokir walau isiannya benar.
        $this->from('/ganti-password')->post('/ganti-password', self::PASSWORD_BARU)
            ->assertRedirect('/ganti-password')
            ->assertSessionHasErrors('password_baru');
        $this->assertStringContainsString('Terlalu banyak percobaan ganti password', session('errors')->first('password_baru'));
        $this->assertPasswordTidakBerubah($siswa);

        // Pesan tampil di bawah "Password baru", karena kolom password lama tidak ada.
        $this->from('/ganti-password')->followingRedirects()->post('/ganti-password', self::PASSWORD_BARU)
            ->assertOk()
            ->assertSeeInOrder(['id="password_baru-error"', 'Terlalu banyak percobaan ganti password'], escape: false);
        $this->assertPasswordTidakBerubah($siswa);
    }

    // ---- Sesi dari cookie "Ingat saya": tetap diminta password lama ----

    public function test_sesi_dari_cookie_ingat_saya_tetap_diminta_password_lama(): void
    {
        $siswa = $this->userWajibGanti();
        $this->loginDariCookieIngatSaya($siswa);

        $html = $this->get('/ganti-password')
            ->assertOk()
            ->assertSee('Kamu wajib mengganti password dulu sebelum bisa lanjut.')
            ->getContent();
        $this->assertSame(['password_lama', 'password_baru', 'password_baru_confirmation'], $this->kolomDenganTombol($html));

        // Tanpa password lama → ditolak.
        $this->from('/ganti-password')->post('/ganti-password', self::PASSWORD_BARU)
            ->assertSessionHasErrors(['password_lama' => 'Password lama wajib diisi.']);

        // Password lama salah → ditolak.
        $this->from('/ganti-password')->post('/ganti-password', ['password_lama' => 'tebakan'] + self::PASSWORD_BARU)
            ->assertSessionHasErrors(['password_lama' => 'Password lama salah.']);

        $this->assertTrue($siswa->fresh()->harus_ganti_password);
        $this->assertPasswordTidakBerubah($siswa);

        // Password lama benar → berhasil.
        $this->post('/ganti-password', ['password_lama' => 'password'] + self::PASSWORD_BARU)
            ->assertRedirect('/beranda');
        $this->assertFalse($siswa->fresh()->harus_ganti_password);
    }

    public function test_sesi_dari_cookie_ganti_wajib_sama_dengan_password_dari_admin_ditolak(): void
    {
        $siswa = $this->userWajibGanti();
        $this->loginDariCookieIngatSaya($siswa);

        $this->from('/ganti-password')
            ->post('/ganti-password', ['password_lama' => 'password', 'password_baru' => 'password', 'password_baru_confirmation' => 'password'])
            ->assertSessionHasErrors(['password_baru' => 'Password baru tidak boleh sama dengan password dari admin.']);
    }

    public function test_tanda_sesi_milik_user_lain_tidak_berlaku(): void
    {
        $siswaA = $this->userWajibGanti();
        $siswaB = $this->userWajibGanti();

        $this->actingAs($siswaB)->withSession(['login_dengan_password' => (string) $siswaA->id]);

        $html = $this->get('/ganti-password')->assertOk()->getContent();
        $this->assertStringContainsString('name="password_lama"', $html);

        $this->from('/ganti-password')->post('/ganti-password', self::PASSWORD_BARU)
            ->assertSessionHasErrors(['password_lama' => 'Password lama wajib diisi.']);
        $this->assertPasswordTidakBerubah($siswaB);
    }

    // ---- Ganti password biasa (harus_ganti_password = false) ----

    /** @return array<string, array{array<string, string>, string, string}> */
    public static function gantiBiasaTidakValid(): array
    {
        return [
            'tanpa password lama' => [self::PASSWORD_BARU, 'password_lama', 'Password lama wajib diisi.'],
            'password lama salah' => [['password_lama' => 'tebakan'] + self::PASSWORD_BARU, 'password_lama', 'Password lama salah.'],
            'sama dengan password lama' => [
                ['password_lama' => 'password', 'password_baru' => 'password', 'password_baru_confirmation' => 'password'],
                'password_baru', 'Password baru harus berbeda dari password lama.',
            ],
        ];
    }

    /**
     * User yang login dengan mengetik password (punya tanda sesi) tapi tidak wajib
     * ganti: tanda saja tidak cukup, password lama tetap wajib dan harus benar.
     */
    #[DataProvider('gantiBiasaTidakValid')]
    public function test_ganti_biasa_tetap_diminta_password_lama(array $input, string $kolom, string $pesan): void
    {
        $guru = $this->buatUser(Role::Guru);
        $this->loginDenganPassword($guru);

        $html = $this->get('/ganti-password')->assertOk()->assertDontSee('Kamu wajib mengganti password')->getContent();
        $this->assertSame(['password_lama', 'password_baru', 'password_baru_confirmation'], $this->kolomDenganTombol($html));

        $this->from('/ganti-password')->post('/ganti-password', $input)
            ->assertRedirect('/ganti-password')
            ->assertSessionHasErrors([$kolom => $pesan]);

        $this->assertPasswordTidakBerubah($guru);
    }

    /**
     * Password lama salah + password baru = password saat ini: hanya "Password lama salah.".
     * Kalau pesan "harus berbeda" ikut muncul, tebakan kedua di kolom password baru
     * ketahuan benar (satu percobaan = dua tebakan).
     */
    #[DataProvider('sesiYangMemintaPasswordLama')]
    public function test_password_lama_salah_tidak_membocorkan_tebakan_di_password_baru(string $sesi): void
    {
        if ($sesi === 'ganti biasa') {
            $this->loginDenganPassword($this->buatUser(Role::Guru));
        } else {
            $this->loginDariCookieIngatSaya($this->userWajibGanti());
        }

        $this->from('/ganti-password')
            ->post('/ganti-password', ['password_lama' => 'tebakan-salah', 'password_baru' => 'password', 'password_baru_confirmation' => 'password'])
            ->assertSessionHasErrors(['password_lama' => 'Password lama salah.'])
            ->assertSessionDoesntHaveErrors('password_baru');
    }

    /** @return array<string, array{string}> */
    public static function sesiYangMemintaPasswordLama(): array
    {
        return ['ganti biasa' => ['ganti biasa'], 'ganti wajib dari cookie' => ['cookie']];
    }

    public function test_form_dari_tab_lama_setelah_password_diganti_diminta_password_lama(): void
    {
        $siswa = $this->userWajibGanti();
        $this->loginDenganPassword($siswa);

        // Tab 1: ganti wajib berhasil tanpa password lama.
        $this->post('/ganti-password', self::PASSWORD_BARU)->assertRedirect('/beranda');

        // Tab 2 (form lama, tanpa kolom password lama) dikirim setelahnya → ditolak,
        // dan form tampil lagi dengan kolom password lama + pesannya.
        $tabLama = ['password_baru' => 'rahasia-lain', 'password_baru_confirmation' => 'rahasia-lain'];

        $this->from('/ganti-password')->post('/ganti-password', $tabLama)
            ->assertRedirect('/ganti-password')
            ->assertSessionHasErrors(['password_lama' => 'Password lama wajib diisi.']);

        $this->from('/ganti-password')->followingRedirects()->post('/ganti-password', $tabLama)
            ->assertOk()
            ->assertSee('Password lama wajib diisi.')
            ->assertSee('name="password_lama"', escape: false);

        $this->assertTrue(Hash::check('rahasia-baru', $siswa->fresh()->password));
    }

    /**
     * Seperti tes di atas, tapi dengan pengecekan CSRF aktif (Laravel mematikannya saat
     * tes) dan tanpa header Sec-Fetch-Site, seperti browser di http://absensi-piket.test.
     * Form tab lama membawa token CSRF lama: harus mendapat pesan, bukan 419.
     */
    public function test_tab_lama_dengan_csrf_aktif_mendapat_pesan_bukan_419(): void
    {
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $siswa = $this->userWajibGanti();
        $this->actingAs($siswa)->withSession(['login_dengan_password' => (string) $siswa->id]);

        // Pastikan CSRF memang aktif di tes ini: token salah → 419.
        $this->post('/ganti-password', ['_token' => 'token-salah'] + self::PASSWORD_BARU)->assertStatus(419);

        // Kedua tab membuka form: token CSRF yang sama.
        $this->get('/ganti-password')->assertOk()->assertDontSee('name="password_lama"', escape: false);
        $tokenLama = session()->token();
        $idSesiLama = session()->getId();

        // Tab 1 berhasil. Cookie sesi dikirim eksplisit (test client tidak mengirimnya
        // sendiri), jadi ID sesi hanya berbeda kalau login() memang membuat ID baru.
        $this->withCookie(config('session.cookie'), $idSesiLama)
            ->post('/ganti-password', ['_token' => $tokenLama] + self::PASSWORD_BARU)
            ->assertRedirect('/beranda');
        $this->assertNotSame($idSesiLama, session()->getId(), 'ID sesi harus tetap berganti.');

        // Tab 2 dengan token lama → pesan "Password lama wajib diisi.", bukan 419.
        $this->from('/ganti-password')
            ->post('/ganti-password', ['_token' => $tokenLama, 'password_baru' => 'rahasia-lain', 'password_baru_confirmation' => 'rahasia-lain'])
            ->assertRedirect('/ganti-password')
            ->assertSessionHasErrors(['password_lama' => 'Password lama wajib diisi.']);

        $this->assertTrue(Hash::check('rahasia-baru', $siswa->fresh()->password));
    }

    /**
     * Hash bcrypt yang dikirim sebagai "password baru" diperlakukan sebagai teks biasa,
     * bukan disimpan apa adanya. Kalau disimpan apa adanya, user bisa memakai password
     * 1 karakter (hash dari "a") dan melewati aturan minimal 8 karakter (temuan review 5a).
     */
    public function test_hash_yang_dikirim_sebagai_password_baru_tidak_disimpan_apa_adanya(): void
    {
        $guru = $this->buatUser(Role::Guru);
        $this->loginDenganPassword($guru);
        $hashDariA = Hash::make('a');

        $this->post('/ganti-password', ['password_lama' => 'password', 'password_baru' => $hashDariA, 'password_baru_confirmation' => $hashDariA])
            ->assertRedirect('/verifikasi');

        $guru->refresh();
        $this->assertNotSame($hashDariA, $guru->password);
        $this->assertFalse(Hash::check('a', $guru->password), 'Password "a" (1 karakter) tidak boleh berlaku.');
        $this->assertTrue(Hash::check($hashDariA, $guru->password), 'Password-nya adalah teks 60 karakter yang diketik.');
    }

    public function test_harus_ganti_password_tidak_bisa_disetel_lewat_form(): void
    {
        $guru = $this->buatUser(Role::Guru);
        $this->loginDenganPassword($guru);

        // Mencoba menyalakan flag supaya bisa ganti tanpa password lama.
        $this->from('/ganti-password')
            ->post('/ganti-password', ['harus_ganti_password' => '1', 'login_dengan_password' => (string) $guru->id] + self::PASSWORD_BARU)
            ->assertSessionHasErrors(['password_lama' => 'Password lama wajib diisi.']);

        $guru->refresh();
        $this->assertFalse($guru->harus_ganti_password);
        $this->assertPasswordTidakBerubah($guru);
    }
}
