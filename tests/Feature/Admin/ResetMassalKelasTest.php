<?php

namespace Tests\Feature\Admin;

use App\Actions\GantiPassword;
use App\Actions\ResetMassalKelas;
use App\Enums\Role;
use App\Http\Controllers\Admin\AkunController;
use App\Models\Kelas;
use App\Models\School;
use App\Models\User;
use ArrayObject;
use Illuminate\Database\Connection;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;
use Mockery;
use PDOException;

/** SPEC F9 + §11 Langkah 5a (v0.11): reset massal password satu kelas. */
class ResetMassalKelasTest extends AdminTestCase
{
    private const PESAN_DAFTAR_BELUM_DIUNDUH = 'Unduh dulu daftar password sebelumnya di halaman Akun.';

    private function reset(?Kelas $kelas = null): TestResponse
    {
        $kelas ??= $this->kelas;

        return $this->actingAs($this->admin)
            ->from("/admin/kelas/{$kelas->id}/edit")
            ->post("/admin/kelas/{$kelas->id}/reset-password");
    }

    private function unduh(): TestResponse
    {
        return $this->actingAs($this->admin)->from('/admin/akun')->post('/admin/akun/import/password');
    }

    /** Akun siswa/PJ yang belum pernah mengganti password (password "password"). */
    private function belumGanti(Role $role = Role::Siswa, ?Kelas $kelas = null, array $atribut = []): User
    {
        $akun = $this->siswa($role, $kelas);
        $akun->forceFill(['harus_ganti_password' => true, 'remember_token' => 'token-lama'] + $atribut)->save();

        return $akun;
    }

    private function buatSesi(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id, 'user_id' => $user->id, 'ip_address' => '10.0.0.2',
            'user_agent' => 'HP', 'payload' => '', 'last_activity' => now()->getTimestamp(),
        ]);
    }

    /** Jalankan $ubah sekali, tepat saat transaksi reset dimulai (penyimpanan bersamaan). */
    private function saatTransaksiResetDimulai(callable $ubah): void
    {
        $sudah = false;
        Event::listen(TransactionBeginning::class, function () use (&$sudah, $ubah) {
            if (! $sudah) {
                $sudah = true;
                $ubah();
            }
        });
    }

    /** @return ArrayObject<int, int> tingkat transaksi saat tiap Hash::make dipanggil */
    private function pantauHashMake(): ArrayObject
    {
        $tingkatSaatHash = new ArrayObject;
        $asli = app('hash');
        $pemantau = Mockery::mock($asli)->makePartial();
        $pemantau->shouldReceive('make')->andReturnUsing(function ($nilai, $opsi = []) use ($asli, $tingkatSaatHash) {
            $tingkatSaatHash[] = DB::transactionLevel();

            return $asli->make($nilai, $opsi);
        });
        Hash::swap($pemantau);

        return $tingkatSaatHash;
    }

    /**
     * RefreshDatabase membungkus tiap tes dalam transaksi, sehingga transaksi reset menjadi
     * transaksi bersarang, dan Laravel tidak pernah mengulang transaksi bersarang. Untuk tes
     * pengulangan, transaksi pembungkus di-commit dulu. Saat tes selesai, RefreshDatabase
     * melihat transaksinya sudah tidak ada dan menyiapkan ulang database tes (migrate:fresh)
     * untuk tes berikutnya, jadi data tes ini tidak bocor ke tes lain.
     */
    private function tanpaTransaksiPembungkus(): void
    {
        DB::commit();
        $this->assertSame(0, DB::transactionLevel());
    }

    /**
     * Simulasikan deadlock MySQL (SQLSTATE 40001) saat menghapus sesi akun di dalam transaksi
     * reset. $gagalPada = urutan penghapusan sesi (dihitung lintas percobaan) yang gagal.
     */
    private function deadlockSaatHapusSesi(array $gagalPada): void
    {
        $ke = 0;
        DB::beforeExecuting(function (string $sql, array $bindings, Connection $koneksi) use (&$ke, $gagalPada) {
            if (! str_starts_with($sql, 'delete from') || ! str_contains($sql, 'sessions') || $koneksi->transactionLevel() === 0) {
                return;
            }

            if (in_array(++$ke, $gagalPada, true)) {
                throw new QueryException($koneksi->getName(), $sql, $bindings, new PDOException(
                    'SQLSTATE[40001]: Serialization failure: 1213 Deadlock found when trying to get lock; try restarting transaction'
                ));
            }
        });
    }

    /** @return ArrayObject<string, int> jumlah percobaan transaksi reset (tingkat teratas) */
    private function hitungPercobaanTransaksi(): ArrayObject
    {
        $hitung = new ArrayObject(['percobaan' => 0]);
        Event::listen(TransactionBeginning::class, function () use ($hitung) {
            if (DB::transactionLevel() === 1) {
                $hitung['percobaan']++;
            }
        });

        return $hitung;
    }

    private function assertTidakBerubah(User $akun): void
    {
        $sesudah = $akun->fresh();
        $this->assertSame($akun->password, $sesudah->password, "Password {$akun->nama} tidak boleh berubah.");
        $this->assertSame($akun->remember_token, $sesudah->remember_token);
        $this->assertSame($akun->harus_ganti_password, $sesudah->harus_ganti_password);
    }

    // ---- Alur utama ----

    public function test_hanya_siswa_dan_pj_kelas_ini_yang_belum_ganti_password_yang_direset(): void
    {
        // Urutan dibuat (id) sengaja berbeda dari urutan no. absen: daftar harus urut no. absen.
        $siswa1 = $this->belumGanti(Role::Siswa, null, ['no_absen' => 30]);
        $siswa2 = $this->belumGanti(Role::Siswa, null, ['no_absen' => 10]);
        $pj = $this->belumGanti(Role::PjKebersihan, null, ['no_absen' => 20]);
        $sudahGanti = $this->siswa(); // harus_ganti_password = false: password pilihannya sendiri

        // Bukan sasaran: guru wali, admin lain, guru/admin dengan class_id kelas ini
        // (data tidak wajar), siswa kelas lain, siswa sekolah lain.
        $guruWali = $this->guru();
        $this->kelas->update(['wali_kelas_id' => $guruWali->id]);
        $guruAneh = User::factory()->role(Role::Guru)->create(['school_id' => $this->sekolah->id]);
        $guruAneh->forceFill(['class_id' => $this->kelas->id])->save();
        $adminLain = User::factory()->role(Role::Admin)->create(['school_id' => $this->sekolah->id]);
        $siswaKelasLain = $this->belumGanti(Role::Siswa, $this->kelasBaru('XI RPL 2'));
        $sekolahLain = School::factory()->create();
        $siswaSekolahLain = $this->belumGanti(Role::Siswa, $this->kelasBaru('XI RPL 1', $sekolahLain));
        // Data tidak wajar: class_id kelas ini, tapi akun milik sekolah lain.
        $akunSekolahLainDiKelasIni = $this->belumGanti();
        $akunSekolahLainDiKelasIni->forceFill(['school_id' => $sekolahLain->id])->save();
        $bukanSasaran = [$sudahGanti, $guruWali->fresh(), $guruAneh->fresh(), $adminLain, $siswaKelasLain, $siswaSekolahLain, $akunSekolahLainDiKelasIni];

        foreach ([$siswa1, $siswa2, $pj, $sudahGanti, $siswaKelasLain] as $i => $akun) {
            $this->buatSesi("sesi-{$i}", $akun);
        }

        // {n} di form ubah kelas hanya menghitung sasaran yang sah.
        $this->actingAs($this->admin)->get("/admin/kelas/{$this->kelas->id}/edit")->assertSee('3 akun belum mengganti password.');

        $this->reset()
            ->assertRedirect("/admin/akun?kelas={$this->kelas->id}")
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Password 3 akun di kelas XI RPL 1 direset. Unduh daftar password sekarang — daftar hanya bisa diunduh sekali.');

        // Daftar urut no. absen, sama dengan jumlah di pesan sukses.
        $daftar = session(AkunController::SESI_PASSWORD_IMPORT);
        $this->assertSame([$siswa2->nis, $pj->nis, $siswa1->nis], array_column($daftar, 'nis'));
        $this->assertSame([$siswa2->no_absen, $pj->no_absen, $siswa1->no_absen], array_column($daftar, 'no_absen'));

        foreach ([$siswa2, $pj, $siswa1] as $i => $akun) {
            $baru = $akun->fresh();
            $this->assertSame(['nama' => $akun->nama, 'kelas' => 'XI RPL 1', 'no_absen' => $akun->no_absen, 'nis' => $akun->nis], array_diff_key($daftar[$i], ['password' => 1]));
            $this->assertPasswordAcakSesuaiAturan($daftar[$i]['password']);
            $this->assertTrue(Hash::check($daftar[$i]['password'], $baru->password));
            $this->assertFalse(Hash::check('password', $baru->password), 'Password lama tidak boleh berlaku lagi.');
            $this->assertTrue($baru->harus_ganti_password);
            $this->assertNotSame('token-lama', $baru->remember_token, 'Cookie "Ingat saya" lama harus tidak berlaku.');
            $this->assertSame($akun->role, $baru->role);
        }

        // Sesi akun yang direset keluar; sesi akun lain tetap.
        foreach ([0, 1, 2] as $i) {
            $this->assertDatabaseMissing('sessions', ['id' => "sesi-{$i}"]);
        }
        $this->assertDatabaseHas('sessions', ['id' => 'sesi-3']);
        $this->assertDatabaseHas('sessions', ['id' => 'sesi-4']);

        foreach ($bukanSasaran as $akun) {
            $this->assertTidakBerubah($akun);
        }

        // Halaman akun: pesan sukses + kotak unduh.
        $this->actingAs($this->admin)->get("/admin/akun?kelas={$this->kelas->id}")
            ->assertOk()
            ->assertSee('Password 3 akun di kelas XI RPL 1 direset.')
            ->assertSee('Unduh daftar password');
    }

    public function test_password_lama_tidak_bisa_login_password_baru_bisa(): void
    {
        $siswa = $this->belumGanti();
        $this->reset();
        $passwordBaru = session(AkunController::SESI_PASSWORD_IMPORT)[0]['password'];

        $this->post('/logout');
        $this->post('/login', ['login' => $siswa->nis, 'password' => 'password'])->assertSessionHasErrors('login');
        $this->assertGuest();

        $this->post('/login', ['login' => $siswa->nis, 'password' => $passwordBaru])->assertRedirect('/beranda');
        $this->assertAuthenticatedAs($siswa->fresh());
        $this->get('/beranda')->assertRedirect('/ganti-password'); // tetap wajib ganti (F1)
    }

    public function test_daftar_diunduh_tepat_sekali_dengan_titik_koma_bom_dan_urut_no_absen(): void
    {
        $this->belumGanti(Role::Siswa, null, ['nama' => '=HYPERLINK("x")']);
        $this->belumGanti(Role::PjKebersihan, null, ['nama' => 'Andi; Jr']);
        $this->reset();
        $daftar = session(AkunController::SESI_PASSWORD_IMPORT);

        $isi = $this->unduh()->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $isi);
        $baris = array_map(fn ($b) => str_getcsv($b, ';', '"', ''), explode("\n", rtrim(substr($isi, 3), "\n")));
        $this->assertSame(['nama', 'kelas', 'no_absen', 'nis', 'password'], $baris[0]);
        $this->assertCount(3, $baris, 'Jumlah baris data = jumlah di pesan sukses (2).');
        $this->assertSame("'=HYPERLINK(\"x\")", $baris[1][0], 'Pelindung rumus Excel.');
        $this->assertSame('Andi; Jr', $baris[2][0]);
        $this->assertSame($daftar[1]['password'], $baris[2][4]);

        // Unduh kedua: pesan v0.10.
        $this->assertNull(session(AkunController::SESI_PASSWORD_IMPORT));
        $this->unduh()->assertRedirect('/admin/akun')->assertSessionHasErrors([
            'unduh' => 'Daftar password sudah diunduh atau sudah tidak tersedia. Reset password akun yang belum tercatat.',
        ]);
    }

    // ---- Batas 50 ----

    public function test_tepat_50_akun_berhasil(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $this->belumGanti();
        }

        $this->reset()->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Password 50 akun di kelas XI RPL 1 direset. Unduh daftar password sekarang — daftar hanya bisa diunduh sekali.');
        $this->assertCount(50, session(AkunController::SESI_PASSWORD_IMPORT));
    }

    public function test_51_akun_ditolak_seluruhnya(): void
    {
        $akun = [];
        for ($i = 0; $i < 51; $i++) {
            $akun[] = $this->belumGanti();
        }

        $this->reset()
            ->assertRedirect("/admin/kelas/{$this->kelas->id}/edit")
            ->assertSessionHasErrors(['reset_password' => 'Kelas XI RPL 1 punya 51 akun yang belum mengganti password, maks 50 per reset. Pakai reset password per akun.']);

        $this->assertNull(session(AkunController::SESI_PASSWORD_IMPORT));
        foreach ($akun as $satu) {
            $this->assertTidakBerubah($satu);
        }
    }

    // ---- Tidak ada akun yang memenuhi syarat ----

    public function test_kelas_tanpa_akun_yang_memenuhi_syarat(): void
    {
        $sudahGanti = $this->siswa();
        $kosong = $this->kelasBaru('XI RPL 9');
        $pesan = fn (string $nama) => "Tidak ada akun di kelas {$nama} yang belum mengganti password. Untuk akun tertentu, pakai reset password per akun.";

        $this->reset()->assertSessionHasErrors(['reset_password' => $pesan('XI RPL 1')]);
        $this->reset($kosong)->assertSessionHasErrors(['reset_password' => $pesan('XI RPL 9')]);

        $this->assertNull(session(AkunController::SESI_PASSWORD_IMPORT));
        $this->assertTidakBerubah($sudahGanti);
    }

    // ---- Penyimpanan bersamaan (dicek ulang di transaksi) ----

    public function test_akun_yang_berubah_bersamaan_tidak_ikut_direset(): void
    {
        $tetap = $this->belumGanti();
        $gantiSendiri = $this->belumGanti();
        $dihapus = $this->belumGanti();
        $pindah = $this->belumGanti();
        $diresetAdminLain = $this->belumGanti();
        $flagSaja = $this->belumGanti();
        $roleAneh = $this->belumGanti();
        $sekolahAneh = $this->belumGanti();
        $sekolahLain = School::factory()->create();
        $kelasLain = $this->kelasBaru('XI RPL 2');
        $baru = null;

        $this->saatTransaksiResetDimulai(function () use ($gantiSendiri, $dihapus, $pindah, $diresetAdminLain, $flagSaja, $roleAneh, $sekolahAneh, $sekolahLain, $kelasLain, &$baru) {
            // Tepat sebelum transaksi: siswa mengganti password sendiri, akun dihapus,
            // akun pindah kelas, akun baru ditambahkan admin lain, dan admin lain mereset
            // satu akun (password berubah, tetap wajib ganti).
            $gantiSendiri->forceFill(['password' => 'pilihan-siswa', 'harus_ganti_password' => false])->save();
            DB::table('users')->where('id', $dihapus->id)->delete();
            DB::table('users')->where('id', $pindah->id)->update(['class_id' => $kelasLain->id, 'no_absen' => 99]);
            $baru = $this->belumGanti();
            $diresetAdminLain->forceFill(['password' => 'dari-admin-lain'])->save();
            // Data tidak wajar, untuk menguji tiap syarat di cek ulang (hash tetap sama).
            DB::table('users')->where('id', $flagSaja->id)->update(['harus_ganti_password' => false]);
            DB::table('users')->where('id', $roleAneh->id)->update(['role' => Role::Guru->value]);
            DB::table('users')->where('id', $sekolahAneh->id)->update(['school_id' => $sekolahLain->id]);
        });

        $this->reset()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Password 1 akun di kelas XI RPL 1 direset. Unduh daftar password sekarang — daftar hanya bisa diunduh sekali.');

        $daftar = session(AkunController::SESI_PASSWORD_IMPORT);
        $this->assertSame([$tetap->nis], array_column($daftar, 'nis'), 'Hanya akun yang lolos cek awal dan cek ulang.');
        $this->assertTrue(Hash::check($daftar[0]['password'], $tetap->fresh()->password));

        // Password pilihan siswa tidak tertimpa.
        $this->assertTrue(Hash::check('pilihan-siswa', $gantiSendiri->fresh()->password));
        $this->assertFalse($gantiSendiri->fresh()->harus_ganti_password);
        $this->assertTrue(Hash::check('password', $pindah->fresh()->password));
        // Password dari admin lain tidak tertimpa (daftar/tampilan admin itu tetap berlaku).
        $this->assertTrue(Hash::check('dari-admin-lain', $diresetAdminLain->fresh()->password));
        $this->assertTrue(Hash::check('password', $flagSaja->fresh()->password));
        $this->assertTrue(Hash::check('password', $roleAneh->fresh()->password));
        $this->assertTrue(Hash::check('password', $sekolahAneh->fresh()->password));
        $this->assertModelMissing($dihapus);
        // Akun baru: tidak direset (passwordnya baru saja dibagikan).
        $this->assertNotNull($baru);
        $this->assertTrue(Hash::check('password', $baru->fresh()->password));
    }

    public function test_akun_baru_bersamaan_tetap_dihitung_untuk_batas_50(): void
    {
        $akun = [];
        for ($i = 0; $i < 50; $i++) {
            $akun[] = $this->belumGanti();
        }
        $this->saatTransaksiResetDimulai(fn () => $this->belumGanti());

        $this->reset()->assertSessionHasErrors([
            'reset_password' => 'Kelas XI RPL 1 punya 51 akun yang belum mengganti password, maks 50 per reset. Pakai reset password per akun.',
        ]);

        $this->assertNull(session(AkunController::SESI_PASSWORD_IMPORT));
        foreach ($akun as $satu) {
            $this->assertTidakBerubah($satu);
        }
    }

    public function test_semua_akun_mengganti_password_bersamaan(): void
    {
        $siswa = $this->belumGanti();
        $this->saatTransaksiResetDimulai(fn () => $siswa->forceFill(['password' => 'pilihan-siswa', 'harus_ganti_password' => false])->save());

        $this->reset()->assertSessionHasErrors([
            'reset_password' => 'Tidak ada akun di kelas XI RPL 1 yang belum mengganti password. Untuk akun tertentu, pakai reset password per akun.',
        ]);

        // Bukan pesan "direset admin lain": saat dicek ulang memang sudah tidak ada akun
        // yang belum mengganti password, jadi "Tidak ada akun …" benar.
        // (Di tes, perubahan "bersamaan" terjadi di dalam transaksi reset, jadi ikut
        // dibatalkan saat reset ditolak. Yang dicek: pesannya, dan tidak ada daftar.)
        $this->assertNull(session(AkunController::SESI_PASSWORD_IMPORT));
    }

    /** SPEC F9 (v0.11, dari review 5a): dua admin mereset kelas yang sama hampir bersamaan. */
    public function test_kelas_baru_saja_direset_admin_lain_pesan_khusus(): void
    {
        $this->belumGanti();
        $this->belumGanti();

        // Admin lain menjalankan reset massal kelas yang sama, tepat setelah cek awal
        // request ini dan sebelum barisnya dikunci. Akun tetap wajib ganti password,
        // tapi password-nya sudah berbeda dari yang dicatat di cek awal.
        $this->saatTransaksiResetDimulai(fn () => app(ResetMassalKelas::class)->jalankan($this->kelas));

        $this->reset()->assertSessionHasErrors([
            'reset_password' => 'Password akun di kelas ini baru saja direset admin lain. Muat ulang halaman.',
        ]);

        $this->assertNull(session(AkunController::SESI_PASSWORD_IMPORT));
    }

    public function test_pesan_khusus_juga_kalau_sebagian_ganti_sendiri_sebagian_direset_admin_lain(): void
    {
        $gantiSendiri = $this->belumGanti();
        $diresetAdminLain = $this->belumGanti();
        $this->saatTransaksiResetDimulai(function () use ($gantiSendiri, $diresetAdminLain) {
            $gantiSendiri->forceFill(['password' => 'pilihan-siswa', 'harus_ganti_password' => false])->save();
            $diresetAdminLain->forceFill(['password' => 'dari-admin-lain'])->save();
        });

        $this->reset()->assertSessionHasErrors([
            'reset_password' => 'Password akun di kelas ini baru saja direset admin lain. Muat ulang halaman.',
        ]);

        $this->assertNull(session(AkunController::SESI_PASSWORD_IMPORT));
    }

    /** Hash dibuat di luar transaksi, supaya baris akun tidak terkunci lama (SPEC F9). */
    public function test_password_tidak_di_hash_di_dalam_transaksi(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->belumGanti();
        }

        $tingkatDasar = DB::transactionLevel(); // RefreshDatabase membungkus tes dalam transaksi
        $tingkatSaatHash = $this->pantauHashMake();

        $this->reset()->assertSessionHasNoErrors();

        $this->assertCount(3, $tingkatSaatHash, 'Tepat satu hash per akun, tidak di-hash ulang saat disimpan.');
        foreach ($tingkatSaatHash as $tingkat) {
            $this->assertSame($tingkatDasar, $tingkat, 'Hash::make dipanggil di dalam transaksi.');
        }
    }

    // ---- Pengulangan transaksi saat deadlock (attempts: 3) ----

    public function test_deadlock_transaksi_diulang_dan_daftar_di_sesi_cocok_dengan_database(): void
    {
        $akun = [$this->belumGanti(), $this->belumGanti(), $this->belumGanti()];
        foreach ($akun as $i => $satu) {
            $this->buatSesi("sesi-{$i}", $satu);
        }

        $this->tanpaTransaksiPembungkus();
        $tingkatSaatHash = $this->pantauHashMake();
        $hitung = $this->hitungPercobaanTransaksi();
        // Percobaan 1 gagal saat menghapus sesi akun ke-2: akun ke-1 sudah sempat diubah,
        // lalu dibatalkan database. Percobaan 2 harus membaca ulang dan mereset ketiganya.
        $this->deadlockSaatHapusSesi([2]);

        // Isi sesi saat commit transaksi reset (tingkat teratas).
        $sesiSaatCommit = [];
        Event::listen(TransactionCommitted::class, function () use (&$sesiSaatCommit) {
            if (DB::transactionLevel() === 0) {
                $sesiSaatCommit[] = session()->has(AkunController::SESI_PASSWORD_IMPORT);
            }
        });

        $this->reset()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Password 3 akun di kelas XI RPL 1 direset. Unduh daftar password sekarang — daftar hanya bisa diunduh sekali.');

        $this->assertSame(2, $hitung['percobaan'], 'Transaksi harus diulang sekali.');
        $this->assertSame([false], $sesiSaatCommit, 'Daftar baru boleh masuk sesi SETELAH commit.');
        $this->assertCount(3, $tingkatSaatHash, 'Pengulangan tidak boleh meng-hash ulang.');

        $daftar = session(AkunController::SESI_PASSWORD_IMPORT);
        $this->assertCount(3, $daftar);
        foreach ($akun as $satu) {
            $baris = collect($daftar)->firstWhere('nis', $satu->nis);
            $this->assertNotNull($baris, "{$satu->nama} harus ada di daftar.");
            $sesudah = $satu->fresh();
            $this->assertTrue(Hash::check($baris['password'], $sesudah->password), 'Password di daftar harus cocok dengan database.');
            $this->assertFalse(Hash::check('password', $sesudah->password));
            $this->assertTrue($sesudah->harus_ganti_password);
            $this->assertNotSame('token-lama', $sesudah->remember_token);
            $this->assertSame(0, DB::table('sessions')->where('user_id', $satu->id)->count());
        }
    }

    public function test_deadlock_terus_menerus_tidak_ada_yang_berubah_dan_tidak_ada_daftar(): void
    {
        $akun = [$this->belumGanti(), $this->belumGanti()];
        foreach ($akun as $i => $satu) {
            $this->buatSesi("sesi-{$i}", $satu);
        }

        $this->tanpaTransaksiPembungkus();
        $hitung = $this->hitungPercobaanTransaksi();
        $this->deadlockSaatHapusSesi([1, 2, 3]); // tiap percobaan gagal di akun pertama

        $this->withoutExceptionHandling();
        try {
            $this->reset();
            $this->fail('Deadlock pada percobaan ke-3 harus diteruskan sebagai error.');
        } catch (PDOException $e) {
            $this->assertStringContainsString('Deadlock found', $e->getMessage());
        }

        $this->assertSame(3, $hitung['percobaan'], 'Maks 3 percobaan.');
        $this->assertFalse(session()->has(AkunController::SESI_PASSWORD_IMPORT), 'Tidak ada daftar kalau transaksi tidak pernah commit.');
        foreach ($akun as $satu) {
            $this->assertTidakBerubah($satu);
            $this->assertSame(1, DB::table('sessions')->where('user_id', $satu->id)->count());
        }
    }

    public function test_jalankan_dengan_hash_menolak_password_polos(): void
    {
        $this->expectException(\LogicException::class);

        app(GantiPassword::class)->jalankanDenganHash($this->belumGanti(), 'bukan-hash', harusGantiPassword: true);
    }

    // ---- Daftar tertunda bersama import ----

    public function test_daftar_tertunda_memblokir_reset_dan_import(): void
    {
        $siswa = $this->belumGanti();

        // Daftar dari import belum diunduh → reset ditolak, tidak ada yang berubah.
        $this->actingAs($this->admin)->post('/admin/akun/import', [
            'file' => UploadedFile::fake()->createWithContent('a.csv', "nama,nis,kelas,no_absen\nBaru,9001,XI RPL 1,90\n"),
        ])->assertSessionHasNoErrors();
        $daftarImport = session(AkunController::SESI_PASSWORD_IMPORT);

        $this->reset()->assertSessionHasErrors(['reset_password' => self::PESAN_DAFTAR_BELUM_DIUNDUH]);
        $this->assertTidakBerubah($siswa);
        $this->assertSame($daftarImport, session(AkunController::SESI_PASSWORD_IMPORT), 'Daftar lama tidak boleh tertimpa.');

        // Pesan tampil di form ubah kelas dengan tautan ke halaman Akun.
        $this->actingAs($this->admin)->from("/admin/kelas/{$this->kelas->id}/edit")->followingRedirects()
            ->post("/admin/kelas/{$this->kelas->id}/reset-password")
            ->assertOk()
            ->assertSee('Unduh dulu daftar password sebelumnya di <a href="'.route('admin.akun.index').'"', escape: false)
            ->assertSee('halaman Akun</a>.', escape: false);

        // Setelah diunduh: reset boleh. Lalu daftar reset memblokir import.
        $this->unduh()->assertOk();
        $this->reset()->assertSessionHasNoErrors();
        $daftarReset = session(AkunController::SESI_PASSWORD_IMPORT);

        $this->actingAs($this->admin)->from('/admin/akun')->post('/admin/akun/import', [
            'file' => UploadedFile::fake()->createWithContent('b.csv', "nama,nis,kelas,no_absen\nLain,9002,XI RPL 1,91\n"),
        ])->assertSessionHasErrors(['file' => self::PESAN_DAFTAR_BELUM_DIUNDUH]);
        $this->assertFalse(User::where('nis', '9002')->exists());
        $this->assertSame($daftarReset, session(AkunController::SESI_PASSWORD_IMPORT));

        // Reset kedua juga ditolak selama daftar reset belum diunduh.
        $this->reset()->assertSessionHasErrors(['reset_password' => self::PESAN_DAFTAR_BELUM_DIUNDUH]);
        $this->assertSame($daftarReset, session(AkunController::SESI_PASSWORD_IMPORT));
    }

    // ---- Tampilan form ubah kelas ----

    public function test_kotak_reset_di_form_ubah_kelas(): void
    {
        $this->belumGanti();
        $this->belumGanti(Role::PjKebersihan);
        $this->siswa(); // sudah ganti: tidak dihitung

        $konfirmasi = 'Reset password 2 akun di kelas XI RPL 1 yang belum pernah mengganti password? '
            .'Password yang sudah dibagikan tetapi belum dipakai login ikut tidak berlaku, dan akun-akun itu keluar dari semua perangkat. '
            .'Tindakan ini tidak bisa dibatalkan.';

        $this->actingAs($this->admin)->get("/admin/kelas/{$this->kelas->id}/edit")
            ->assertOk()
            ->assertSeeInOrder([
                'Reset password kelas ini',
                'Untuk siswa/PJ yang belum pernah mengganti password, mis. daftar password hilang.',
                'Akun yang sudah mengganti password tidak ikut.',
                '2 akun belum mengganti password.',
                'Reset password 2 akun',
            ])
            ->assertSee('data-konfirmasi="'.e($konfirmasi).'"', escape: false)
            ->assertSee('action="'.route('admin.kelas.reset-password', $this->kelas).'"', escape: false)
            ->assertSee('data-teks-proses="Mereset password… jangan tutup halaman ini."', escape: false);
    }

    public function test_kotak_reset_tanpa_tombol_kalau_tidak_ada_akun_yang_memenuhi_syarat(): void
    {
        $this->siswa();

        $this->actingAs($this->admin)->get("/admin/kelas/{$this->kelas->id}/edit")
            ->assertOk()
            ->assertSee('Reset password kelas ini')
            ->assertSee('Tidak ada akun di kelas XI RPL 1 yang belum mengganti password. Untuk akun tertentu, pakai reset password per akun.')
            ->assertDontSee('reset-password', escape: false);
    }

    // ---- Akses ----

    public function test_kelas_sekolah_lain_atau_sudah_dihapus_404(): void
    {
        $kelasLain = $this->kelasBaru('XI RPL 1', School::factory()->create());
        $siswaLain = $this->belumGanti(Role::Siswa, $kelasLain);

        $this->reset($kelasLain)->assertNotFound();
        $this->assertTidakBerubah($siswaLain);

        $kelasDihapus = $this->kelasBaru('XI RPL 8');
        $idDihapus = $kelasDihapus->id;
        $kelasDihapus->delete();
        $this->actingAs($this->admin)->post("/admin/kelas/{$idDihapus}/reset-password")->assertNotFound();

        $this->assertNull(session(AkunController::SESI_PASSWORD_IMPORT));
    }

    public function test_hanya_post_dengan_csrf(): void
    {
        $siswa = $this->belumGanti();
        $url = "/admin/kelas/{$this->kelas->id}/reset-password";

        $this->actingAs($this->admin)->get($url)->assertStatus(405);

        // CSRF aktif (Laravel mematikannya saat tes): tanpa token → 419, tidak ada yang berubah.
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });
        $this->actingAs($this->admin)->post($url)->assertStatus(419);
        $this->assertTidakBerubah($siswa);
    }
}
