<?php

namespace Tests\Feature\Admin;

use App\Actions\GantiPassword;
use App\Actions\HapusAkun;
use App\Enums\Role;
use App\Enums\Tugas;
use App\Http\Requests\Admin\SimpanAkunRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** SPEC F9 (v0.9): hapus akun dan batas jumlah admin. */
class HapusAkunTest extends AdminTestCase
{
    private const KONFIRMASI = 'Tindakan ini tidak bisa dibatalkan.';

    private function hapus(User $akun): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->from("/admin/akun/{$akun->id}/edit")->delete("/admin/akun/{$akun->id}");
    }

    private function verifikasiOleh(User $verifikator): void
    {
        DB::table('submissions')->insert([
            'user_id' => $this->siswa()->id, 'class_id' => $this->kelas->id, 'tanggal' => '2026-10-26',
            'tugas' => Tugas::Menyapu->value, 'status' => 'disetujui',
            'verified_by' => $verifikator->id, 'verified_at' => now(), 'created_at' => now(),
        ]);
    }

    public function test_hapus_siswa_tanpa_riwayat_beserta_jadwal_dan_sesinya(): void
    {
        $siswa = $this->siswa();
        $this->jadwal($siswa);
        DB::table('sessions')->insert([
            'id' => 'sesi-siswa', 'user_id' => $siswa->id, 'ip_address' => null,
            'user_agent' => null, 'payload' => '', 'last_activity' => now()->getTimestamp(),
        ]);

        $this->actingAs($this->admin)->get("/admin/akun/{$siswa->id}/edit")
            ->assertSee("Hapus akun {$siswa->nama}? ".self::KONFIRMASI)
            ->assertSee('data-teks-proses="Menghapus…"', false);

        $this->hapus($siswa)
            ->assertRedirect('/admin/akun')
            ->assertSessionHas('status', "Akun {$siswa->nama} dihapus.");

        $this->assertModelMissing($siswa);
        $this->assertSame(0, DB::table('duty_schedules')->where('user_id', $siswa->id)->count());
        $this->assertDatabaseMissing('sessions', ['id' => 'sesi-siswa']);
    }

    public function test_hapus_guru_tanpa_riwayat(): void
    {
        $guru = $this->guru();

        $this->hapus($guru)->assertRedirect('/admin/akun');
        $this->assertModelMissing($guru);
    }

    public function test_siswa_yang_punya_submit_tidak_bisa_dihapus(): void
    {
        $siswa = $this->siswa();
        $this->submit($siswa, $this->kelas);

        $this->assertDitolakKarenaRiwayat($siswa);
    }

    public function test_pj_yang_pernah_memverifikasi_tidak_bisa_dihapus(): void
    {
        $pj = $this->siswa(Role::PjKebersihan);
        $this->verifikasiOleh($pj);

        $this->assertDitolakKarenaRiwayat($pj);
    }

    public function test_guru_wali_kelas_tidak_bisa_dihapus(): void
    {
        $guru = $this->guru();
        $this->kelas->update(['wali_kelas_id' => $guru->id]);

        $this->assertDitolakKarenaRiwayat($guru);
    }

    public function test_guru_yang_pernah_memverifikasi_tidak_bisa_dihapus(): void
    {
        $guru = $this->guru();
        $this->verifikasiOleh($guru);

        $this->assertDitolakKarenaRiwayat($guru);
    }

    private function assertDitolakKarenaRiwayat(User $akun): void
    {
        // Tombol tidak tampil, diganti teks alasan.
        $this->actingAs($this->admin)->get("/admin/akun/{$akun->id}/edit")
            ->assertSee(HapusAkun::PESAN_RIWAYAT)
            ->assertDontSee(self::KONFIRMASI);

        // Dikirim paksa: server menolak.
        $this->hapus($akun)
            ->assertRedirect("/admin/akun/{$akun->id}/edit")
            ->assertSessionHasErrors(['hapus' => HapusAkun::PESAN_RIWAYAT]);
        $this->assertModelExists($akun);
    }

    public function test_admin_ketiga_ditolak(): void
    {
        $this->buatUser(Role::Admin, ['school_id' => $this->sekolah->id]); // admin ke-2

        $this->actingAs($this->admin)->from('/admin/akun/baru')
            ->post('/admin/akun', ['nama' => 'Admin Tiga', 'role' => 'admin', 'email' => 'tiga@sekolah.test'])
            ->assertSessionHasErrors(['role' => 'Admin sudah mencapai batas maksimal (2).']);

        $this->assertDatabaseMissing('users', ['email' => 'tiga@sekolah.test']);
    }

    public function test_admin_kedua_boleh_dan_batas_dihitung_per_sekolah(): void
    {
        // Sekolah lain sudah punya 2 admin; tidak memengaruhi sekolah ini.
        $lain = School::factory()->create();
        $this->buatUser(Role::Admin, ['school_id' => $lain->id]);
        $this->buatUser(Role::Admin, ['school_id' => $lain->id]);

        $this->actingAs($this->admin)
            ->post('/admin/akun', ['nama' => 'Admin Dua', 'role' => 'admin', 'email' => 'dua@sekolah.test'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'dua@sekolah.test', 'role' => 'admin']);
    }

    public function test_admin_menghapus_admin_lain(): void
    {
        $adminLain = $this->buatUser(Role::Admin, ['school_id' => $this->sekolah->id]);

        $this->hapus($adminLain)->assertRedirect('/admin/akun');
        $this->assertModelMissing($adminLain);
    }

    public function test_admin_tidak_bisa_menghapus_dirinya_sendiri(): void
    {
        $this->buatUser(Role::Admin, ['school_id' => $this->sekolah->id]); // masih ada admin lain

        $this->actingAs($this->admin)->get("/admin/akun/{$this->admin->id}/edit")
            ->assertSee(HapusAkun::PESAN_SENDIRI)
            ->assertDontSee(self::KONFIRMASI);

        $this->hapus($this->admin)->assertForbidden();
        $this->assertModelExists($this->admin);
    }

    public function test_admin_terakhir_tidak_bisa_dihapus(): void
    {
        // Admin lain di sekolah ini adalah satu-satunya admin (mis. admin yang login
        // berasal dari data lama). Dicek langsung lewat aksi, karena lewat halaman
        // admin yang login selalu ikut terhitung.
        $sekolah = School::factory()->create();
        $satuSatunya = $this->buatUser(Role::Admin, ['school_id' => $sekolah->id]);
        $pelaku = $this->buatUser(Role::Admin, ['school_id' => $sekolah->id]);
        $pelaku->forceFill(['role' => Role::Guru])->save(); // sekarang hanya 1 admin di sekolah itu

        $this->assertSame(HapusAkun::PESAN_ADMIN_TERAKHIR, app(HapusAkun::class)->alasanDitolak($satuSatunya, $pelaku));

        try {
            app(HapusAkun::class)->jalankan($satuSatunya, $pelaku);
            $this->fail('Admin terakhir seharusnya tidak bisa dihapus.');
        } catch (ValidationException $e) {
            $this->assertSame([HapusAkun::PESAN_ADMIN_TERAKHIR], $e->errors()['hapus']);
        }

        $this->assertModelExists($satuSatunya);
    }

    public function test_hapus_pj_tanpa_riwayat(): void
    {
        $pj = $this->siswa(Role::PjKebersihan);

        $this->hapus($pj)->assertRedirect('/admin/akun');
        $this->assertModelMissing($pj);
    }

    public function test_pesan_penolakan_hapus_tampil_sekali(): void
    {
        $siswa = $this->siswa();
        $this->submit($siswa, $this->kelas);

        $tujuan = $this->hapus($siswa)->assertRedirect()->baseResponse->getTargetUrl();
        $halaman = $this->actingAs($this->admin)->get($tujuan)->assertOk()->getContent();

        $this->assertSame(1, substr_count($halaman, e(HapusAkun::PESAN_RIWAYAT)));
        $this->assertStringContainsString('role="alert"', $halaman);
    }

    /**
     * Dua admin menambah admin bersamaan: keduanya lolos validasi (saat itu baru 1 admin),
     * lalu pengecekan ulang di dalam transaksi menolak yang kedua.
     * Disimulasikan dengan menambah admin ke-2 tepat SETELAH validasi lolos.
     */
    public function test_admin_ketiga_ditolak_walau_lolos_validasi(): void
    {
        $this->app->afterResolving(SimpanAkunRequest::class, function () {
            $this->buatUser(Role::Admin, ['school_id' => $this->sekolah->id, 'email' => 'dua@sekolah.test']);
        });

        $this->actingAs($this->admin)->from('/admin/akun/baru')
            ->post('/admin/akun', ['nama' => 'Admin Tiga', 'role' => 'admin', 'email' => 'tiga@sekolah.test'])
            ->assertRedirect('/admin/akun/baru')
            ->assertSessionHasErrors(['role' => 'Admin sudah mencapai batas maksimal (2).']);

        $this->assertDatabaseMissing('users', ['email' => 'tiga@sekolah.test']);
        $this->assertSame(2, $this->sekolah->jumlahAdmin());
    }

    public function test_ubah_akun_admin_tetap_bisa_saat_sudah_dua_admin(): void
    {
        $adminLain = $this->buatUser(Role::Admin, ['school_id' => $this->sekolah->id]);

        foreach ([$adminLain, $this->admin] as $akun) {
            $this->actingAs($this->admin)->put("/admin/akun/{$akun->id}", [
                'nama' => 'Nama Baru '.$akun->id, 'email' => $akun->email,
            ])->assertSessionHasNoErrors();

            $this->assertSame('Nama Baru '.$akun->id, $akun->fresh()->nama);
        }
    }

    /** Akun dihapus admin lain di antara buka form dan simpan → 404, bukan "disimpan". */
    public function test_ubah_akun_yang_baru_saja_dihapus(): void
    {
        $siswa = $this->siswa();
        $this->app->afterResolving(SimpanAkunRequest::class, fn () => DB::table('users')->where('id', $siswa->id)->delete());

        $this->actingAs($this->admin)->put("/admin/akun/{$siswa->id}", [
            'nama' => 'Nama Baru', 'role' => 'siswa', 'nis' => $siswa->nis, 'class_id' => $this->kelas->id, 'no_absen' => $siswa->no_absen,
        ])
            ->assertNotFound()
            ->assertSessionMissing('status');
    }

    /** Sama untuk reset password: tidak ada password "baru" yang ditampilkan untuk akun yang sudah tidak ada. */
    public function test_reset_password_akun_yang_baru_saja_dihapus(): void
    {
        $siswa = $this->siswa();
        $this->app->afterResolving(GantiPassword::class, fn () => DB::table('users')->where('id', $siswa->id)->delete());

        $this->actingAs($this->admin)->post("/admin/akun/{$siswa->id}/reset-password")
            ->assertNotFound()
            ->assertSessionMissing('password_sekali');
    }

    public function test_akun_sekolah_lain_404(): void
    {
        $kelasLain = $this->kelasBaru('XI RPL 1', School::factory()->create());
        $siswaLain = $this->siswa(Role::Siswa, $kelasLain);

        $this->actingAs($this->admin)->delete("/admin/akun/{$siswaLain->id}")->assertNotFound();
        $this->assertModelExists($siswaLain);
    }
}
