<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Http\Requests\Admin\SimpanAkunRequest;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\TestResponse;

/** SPEC F9 (akun): tambah, ubah, role, PJ, pindah kelas. */
class AkunTest extends AdminTestCase
{
    /** @param  array<string, mixed>  $atribut */
    private function tambahSiswa(array $atribut = []): TestResponse
    {
        return $this->actingAs($this->admin)->from('/admin/akun/baru')->post('/admin/akun', $atribut + [
            'nama' => 'Siswa Baru',
            'role' => 'siswa',
            'nis' => '12345678',
            'class_id' => $this->kelas->id,
            'no_absen' => 30,
        ]);
    }

    /** @param  array<string, mixed>  $atribut */
    private function ubah(User $akun, array $atribut): TestResponse
    {
        $dasar = in_array($akun->role, Role::loginPakaiNis(), true)
            ? ['nama' => $akun->nama, 'role' => $akun->role->value, 'nis' => $akun->nis, 'class_id' => $akun->class_id, 'no_absen' => $akun->no_absen]
            : ['nama' => $akun->nama, 'email' => $akun->email];

        return $this->actingAs($this->admin)->from("/admin/akun/{$akun->id}/edit")
            ->put("/admin/akun/{$akun->id}", $atribut + $dasar);
    }

    public function test_daftar_akun_dan_filter(): void
    {
        $siswa = $this->siswa();
        $guru = $this->guru();
        $kelasLain = $this->kelasBaru('XI RPL 2');
        $siswaLain = $this->siswa(Role::Siswa, $kelasLain);

        $this->actingAs($this->admin)->get('/admin/akun')
            ->assertOk()->assertSee($siswa->nama)->assertSee($guru->nama)->assertSee($siswaLain->nama);

        $this->actingAs($this->admin)->get("/admin/akun?kelas={$kelasLain->id}")
            ->assertSee($siswaLain->nama)->assertDontSee($siswa->nama);

        $this->actingAs($this->admin)->get('/admin/akun?role=guru')
            ->assertSee($guru->nama)->assertDontSee($siswa->nama);

        // Filter tidak dikenal diabaikan, bukan error.
        $this->actingAs($this->admin)->get('/admin/akun?role=kepala&kelas=999')->assertOk()->assertSee($siswa->nama);
    }

    public function test_tambah_siswa_dengan_password_acak_yang_tampil_sekali(): void
    {
        $response = $this->tambahSiswa();

        $akun = User::where('nis', '12345678')->firstOrFail();
        $response->assertRedirect("/admin/akun/{$akun->id}/edit");

        $this->assertSame(Role::Siswa, $akun->role);
        $this->assertSame($this->sekolah->id, $akun->school_id);
        $this->assertSame($this->kelas->id, $akun->class_id);
        $this->assertSame(30, $akun->no_absen);
        $this->assertNull($akun->email);
        $this->assertTrue($akun->harus_ganti_password);

        $password = session('password_sekali')['password'];
        $this->assertPasswordAcakSesuaiAturan($password);
        $this->assertTrue(Hash::check($password, $akun->password));

        // Tampil di halaman berikutnya, lalu tidak lagi.
        $this->get("/admin/akun/{$akun->id}/edit")
            ->assertSee('Catat password ini sekarang')
            ->assertSee($password);
        $this->get("/admin/akun/{$akun->id}/edit")
            ->assertDontSee('Catat password ini sekarang')
            ->assertDontSee($password);
    }

    public function test_tambah_guru_pakai_email_huruf_kecil_tanpa_nis_dan_kelas(): void
    {
        $this->actingAs($this->admin)->post('/admin/akun', [
            'nama' => 'Bu Guru',
            'role' => 'guru',
            'email' => ' Bu.Guru@Sekolah.TEST ',
            'nis' => '999', 'class_id' => $this->kelas->id, 'no_absen' => 5, // diabaikan untuk guru
        ])->assertSessionHasNoErrors();

        $guru = User::where('email', 'bu.guru@sekolah.test')->firstOrFail();
        $this->assertSame(Role::Guru, $guru->role);
        $this->assertNull($guru->nis);
        $this->assertNull($guru->class_id);
        $this->assertNull($guru->no_absen);
    }

    public function test_isian_yang_tidak_boleh_dari_form_diabaikan(): void
    {
        $this->tambahSiswa([
            'school_id' => School::factory()->create()->id,
            'harus_ganti_password' => '0',
            'password' => 'pilihan-sendiri',
        ])->assertSessionHasNoErrors();

        $akun = User::where('nis', '12345678')->firstOrFail();
        $this->assertSame($this->sekolah->id, $akun->school_id);
        $this->assertTrue($akun->harus_ganti_password);
        $this->assertFalse(Hash::check('pilihan-sendiri', $akun->password));
    }

    public function test_validasi_siswa(): void
    {
        $this->tambahSiswa(['nis' => '', 'class_id' => '', 'no_absen' => ''])
            ->assertSessionHasErrors(['nis', 'class_id', 'no_absen']);

        // NIS unik global (juga terhadap sekolah lain), no. absen unik per kelas.
        $kelasLain = $this->kelasBaru('XI RPL 1', School::factory()->create());
        $lain = $this->siswa(Role::Siswa, $kelasLain);
        $this->tambahSiswa(['nis' => $lain->nis])->assertSessionHasErrors('nis');

        $ada = $this->siswa();
        $this->tambahSiswa(['no_absen' => $ada->no_absen])
            ->assertSessionHasErrors(['no_absen' => 'No. absen ini sudah dipakai di kelas tersebut.']);

        // Kelas dari sekolah lain ditolak.
        $this->tambahSiswa(['class_id' => $kelasLain->id])
            ->assertSessionHasErrors(['class_id' => 'Kelas harus kelas di sekolah ini.']);

        // Role tidak dikenal.
        $this->tambahSiswa(['role' => 'kepala'])->assertSessionHasErrors(['role' => 'Role tidak dikenal.']);
    }

    public function test_email_guru_unik(): void
    {
        $this->actingAs($this->admin)->post('/admin/akun', ['nama' => 'X', 'role' => 'admin', 'email' => 'ADMIN@sekolah.test'])
            ->assertSessionHasErrors('email');
    }

    public function test_maksimal_dua_pj_per_kelas(): void
    {
        $this->siswa(Role::PjKebersihan);
        $this->siswa(Role::PjKebersihan);
        $pesan = ['role' => 'Kelas XI RPL 1 sudah punya 2 PJ kebersihan.'];

        // Tambah PJ ke-3.
        $this->tambahSiswa(['role' => 'pj_kebersihan'])->assertSessionHasErrors($pesan);

        // Ubah siswa jadi PJ ke-3.
        $siswa = $this->siswa();
        $this->ubah($siswa, ['role' => 'pj_kebersihan'])->assertSessionHasErrors($pesan);
        $this->assertSame(Role::Siswa, $siswa->fresh()->role);

        // Pindahkan PJ dari kelas lain ke kelas yang sudah punya 2 PJ.
        $pjLain = $this->siswa(Role::PjKebersihan, $this->kelasBaru('XI RPL 2'));
        $this->ubah($pjLain, ['class_id' => $this->kelas->id, 'no_absen' => 40])->assertSessionHasErrors($pesan);
    }

    public function test_ubah_role_siswa_dan_pj(): void
    {
        $siswa = $this->siswa();

        $this->ubah($siswa, ['role' => 'pj_kebersihan'])->assertSessionHasNoErrors();
        $this->assertSame(Role::PjKebersihan, $siswa->fresh()->role);

        $this->ubah($siswa->fresh(), ['role' => 'siswa'])->assertSessionHasNoErrors();
        $this->assertSame(Role::Siswa, $siswa->fresh()->role);
    }

    public function test_siswa_tidak_bisa_dijadikan_guru_atau_admin(): void
    {
        $siswa = $this->siswa();

        foreach (['guru', 'admin'] as $role) {
            $this->ubah($siswa, ['role' => $role, 'email' => 'x@sekolah.test'])
                ->assertSessionHasErrors(['role' => 'Role hanya bisa diubah antara siswa dan PJ kebersihan.']);
        }

        $this->assertSame(Role::Siswa, $siswa->fresh()->role);
    }

    public function test_role_guru_dan_admin_tidak_bisa_diubah(): void
    {
        $guru = $this->guru();

        $this->ubah($guru, ['role' => 'siswa'])
            ->assertSessionHasErrors(['role' => 'Role guru/admin tidak bisa diubah.']);
        $this->assertSame(Role::Guru, $guru->fresh()->role);

        // Tanpa role (form guru tidak punya pilihan role): isian lain tersimpan.
        $this->ubah($guru, ['nama' => 'Nama Baru', 'email' => 'baru@sekolah.test'])->assertSessionHasNoErrors();
        $this->assertSame('baru@sekolah.test', $guru->fresh()->email);
        $this->assertSame(Role::Guru, $guru->fresh()->role);
    }

    public function test_pindah_kelas_tanpa_jadwal_tidak_perlu_konfirmasi(): void
    {
        $siswa = $this->siswa();
        $kelasBaru = $this->kelasBaru('XI RPL 2');

        $this->actingAs($this->admin)->get("/admin/akun/{$siswa->id}/edit")
            ->assertDontSee('Ya, hapus jadwal lama');

        $this->ubah($siswa, ['class_id' => $kelasBaru->id])->assertSessionHasNoErrors();
        $this->assertSame($kelasBaru->id, $siswa->fresh()->class_id);
    }

    public function test_pindah_kelas_yang_punya_jadwal_butuh_konfirmasi_lalu_jadwal_lama_dihapus(): void
    {
        $siswa = $this->siswa();
        $this->jadwal($siswa);
        $kelasBaru = $this->kelasBaru('XI RPL 2');

        $this->actingAs($this->admin)->get("/admin/akun/{$siswa->id}/edit")
            ->assertSee('Jadwal piket siswa ini di kelas lama akan dihapus')
            ->assertSee('Ya, hapus jadwal lama');

        // Tanpa centang: ditolak, tidak ada yang berubah.
        $this->ubah($siswa, ['class_id' => $kelasBaru->id])
            ->assertSessionHasErrors(['konfirmasi_pindah_kelas' => SimpanAkunRequest::PESAN_KONFIRMASI_PINDAH]);
        $this->assertSame($this->kelas->id, $siswa->fresh()->class_id);
        $this->assertSame(1, DB::table('duty_schedules')->where('user_id', $siswa->id)->count());

        // Dengan centang: pindah, jadwal kelas lama dihapus.
        $this->ubah($siswa, ['class_id' => $kelasBaru->id, 'konfirmasi_pindah_kelas' => '1'])->assertSessionHasNoErrors();
        $this->assertSame($kelasBaru->id, $siswa->fresh()->class_id);
        $this->assertSame(0, DB::table('duty_schedules')->where('user_id', $siswa->id)->count());
    }

    public function test_pindah_kelas_ke_no_absen_yang_sudah_dipakai_ditolak(): void
    {
        $kelasBaru = $this->kelasBaru('XI RPL 2');
        $yangAda = $this->siswa(Role::Siswa, $kelasBaru);
        $siswa = $this->siswa();

        $this->ubah($siswa, ['class_id' => $kelasBaru->id, 'no_absen' => $yangAda->no_absen])
            ->assertSessionHasErrors(['no_absen' => 'No. absen ini sudah dipakai di kelas tersebut.']);
        $this->assertSame($this->kelas->id, $siswa->fresh()->class_id);
    }

    public function test_kelas_dikosongkan_hanya_memberi_error_wajib_diisi(): void
    {
        $siswa = $this->siswa();
        $this->jadwal($siswa);

        $this->ubah($siswa, ['class_id' => ''])
            ->assertSessionHasErrors('class_id')
            ->assertSessionDoesntHaveErrors('konfirmasi_pindah_kelas');
    }

    public function test_halaman_dengan_password_tidak_disimpan_browser(): void
    {
        $this->tambahSiswa();
        $akun = User::where('nis', '12345678')->firstOrFail();

        $this->get("/admin/akun/{$akun->id}/edit")->assertHeader('Cache-Control', 'no-store, private');
        // Kunjungan berikutnya (tanpa password) memakai header biasa.
        $this->assertStringNotContainsString('no-store', $this->get("/admin/akun/{$akun->id}/edit")->headers->get('Cache-Control'));
    }

    public function test_form_tambah_menandai_isian_per_role(): void
    {
        $this->actingAs($this->admin)->get('/admin/akun/baru')
            ->assertOk()
            ->assertSee('data-pilih-isian-role', false)
            ->assertSee('data-isian-role="siswa pj_kebersihan"', false)
            ->assertSee('data-isian-role="guru admin"', false);
    }

    public function test_isian_berbentuk_array_ditolak_tanpa_error_server(): void
    {
        $this->tambahSiswa(['nama' => ['a'], 'nis' => ['1'], 'email' => ['x']])
            ->assertSessionHasErrors(['nama', 'nis']);

        // role berbentuk array: dianggap tidak dikenal (tambah & ubah), filter diabaikan.
        $this->tambahSiswa(['role' => ['siswa']])->assertSessionHasErrors('role');
        $siswa = $this->siswa();
        $this->ubah($siswa, ['role' => ['pj_kebersihan']])->assertSessionHasErrors('role');
        $kelasLain = $this->kelasBaru('XI RPL 9');
        $siswaKelasLain = $this->siswa(Role::Siswa, $kelasLain);
        $this->actingAs($this->admin)->get("/admin/akun?role[]=guru&kelas[]={$this->kelas->id}")
            ->assertOk()
            ->assertSee($siswaKelasLain->nama); // filter kelas & role berbentuk array diabaikan

        // Form yang dibuka lagi setelah kiriman berisi array tetap tampil (bukan error 500).
        $this->actingAs($this->admin)->followingRedirects()->from('/admin/akun/baru')
            ->post('/admin/akun', ['nama' => ['a'], 'role' => ['siswa'], 'class_id' => ['1']])
            ->assertOk();
        $this->actingAs($this->admin)->followingRedirects()->from("/admin/akun/{$siswa->id}/edit")
            ->put("/admin/akun/{$siswa->id}", ['nama' => ['a'], 'role' => ['siswa'], 'class_id' => ['1']])
            ->assertOk();
    }

    public function test_nis_hanya_huruf_dan_angka_biasa(): void
    {
        $this->tambahSiswa(['nis' => '١٢٣٤'])->assertSessionHasErrors('nis'); // angka Arab
        $this->tambahSiswa(['nis' => '12-34'])->assertSessionHasErrors('nis');
    }

    /**
     * Admin lain memindahkan siswa yang sama tepat setelah akun dibaca, sebelum baris akunnya
     * dikunci (catatan review Langkah 4, SPEC §11 Langkah 8). Kelas lama harus dibaca dari
     * baris yang dikunci, supaya jadwal di kelas perantara tidak tertinggal.
     */
    private function pindahkanDiamDiamSetelahDibaca(User $siswa, int $kelasPerantara): void
    {
        $sudah = false;
        Event::listen('eloquent.retrieved: '.User::class, function (User $akun) use ($siswa, $kelasPerantara, &$sudah) {
            if ($sudah || $akun->id !== $siswa->id) {
                return;
            }
            $sudah = true; // hanya bacaan pertama (route binding), sebelum dikunci

            // Seolah admin lain: pindah ke kelas perantara, lalu PJ di sana menjadwalkannya.
            DB::table('users')->where('id', $siswa->id)->update(['class_id' => $kelasPerantara]);
            DB::table('duty_schedules')->insert([
                'class_id' => $kelasPerantara, 'user_id' => $siswa->id, 'hari' => 1, 'tugas_awal' => 'menyapu',
            ]);
        });
    }

    public function test_pindah_kelas_membaca_kelas_lama_dari_baris_yang_dikunci(): void
    {
        $siswa = $this->siswa();
        $perantara = $this->kelasBaru('XI RPL 2');
        $tujuan = $this->kelasBaru('XI RPL 3');
        $this->pindahkanDiamDiamSetelahDibaca($siswa, $perantara->id);

        $this->ubah($siswa, ['class_id' => $tujuan->id, 'konfirmasi_pindah_kelas' => '1'])->assertSessionHasNoErrors();

        $this->assertSame($tujuan->id, $siswa->fresh()->class_id);
        $this->assertSame(0, DB::table('duty_schedules')->where('user_id', $siswa->id)->count(), 'Jadwal di kelas perantara tertinggal');
    }

    /** Konfirmasi F9 dicek ulang dari data terkunci: form admin ini tidak tahu ada jadwal di kelas perantara. */
    public function test_jadwal_kelas_perantara_tidak_terhapus_tanpa_centang(): void
    {
        $siswa = $this->siswa();
        $perantara = $this->kelasBaru('XI RPL 2');
        $tujuan = $this->kelasBaru('XI RPL 3');
        $this->pindahkanDiamDiamSetelahDibaca($siswa, $perantara->id);

        $this->ubah($siswa, ['class_id' => $tujuan->id, 'nama' => 'Nama Baru'])->assertSessionHasErrors([
            'konfirmasi_pindah_kelas' => 'Jadwal piket siswa ini di kelas lama akan dihapus. Centang "Ya, hapus jadwal lama" untuk melanjutkan.',
        ]);

        // Tidak ada yang berubah oleh simpan ini.
        $this->assertSame($perantara->id, $siswa->fresh()->class_id);
        $this->assertSame($siswa->nama, $siswa->fresh()->nama);
        $this->assertSame(1, DB::table('duty_schedules')->where('user_id', $siswa->id)->where('class_id', $perantara->id)->count());

        // Form dibuka lagi: data terbaru (kelas perantara) + kotak centang.
        $this->actingAs($this->admin)->get("/admin/akun/{$siswa->id}/edit")->assertSee('Ya, hapus jadwal lama');
    }

    public function test_form_basi_yang_hanya_mengganti_nama_tidak_diam_diam_memindahkan_balik(): void
    {
        // Form admin ini masih di kelas awal; admin lain baru memindahkan siswa ke perantara
        // dan ia sudah dijadwalkan di sana. Tanpa centang → ditolak, perpindahan admin lain tetap.
        $siswa = $this->siswa();
        $perantara = $this->kelasBaru('XI RPL 2');
        $this->pindahkanDiamDiamSetelahDibaca($siswa, $perantara->id);

        $this->ubah($siswa, ['nama' => 'Nama Diperbaiki'])->assertSessionHasErrors('konfirmasi_pindah_kelas');
        $this->assertSame($perantara->id, $siswa->fresh()->class_id);
        $this->assertSame(1, DB::table('duty_schedules')->where('user_id', $siswa->id)->count());
    }

    public function test_form_basi_dengan_centang_menulis_kelas_dari_form(): void
    {
        // Yang terakhir menyimpan yang berlaku, termasuk kelas yang sama dengan nilai basi di
        // form: nilai asli model disegarkan dari baris terkunci, jadi class_id tetap ditulis.
        $siswa = $this->siswa();
        $perantara = $this->kelasBaru('XI RPL 2');
        $this->pindahkanDiamDiamSetelahDibaca($siswa, $perantara->id);

        $this->ubah($siswa, ['nama' => 'Nama Diperbaiki', 'konfirmasi_pindah_kelas' => '1'])->assertSessionHasNoErrors();
        $this->assertSame($this->kelas->id, $siswa->fresh()->class_id);
        $this->assertSame('Nama Diperbaiki', $siswa->fresh()->nama);
        $this->assertSame(0, DB::table('duty_schedules')->where('user_id', $siswa->id)->count());
    }

    public function test_ubah_tanpa_pindah_kelas_tidak_menyentuh_jadwal(): void
    {
        $siswa = $this->siswa();
        $this->jadwal($siswa);

        $this->ubah($siswa, ['nama' => 'Nama Diperbaiki'])->assertSessionHasNoErrors();

        $this->assertSame('Nama Diperbaiki', $siswa->fresh()->nama);
        $this->assertSame(1, DB::table('duty_schedules')->where('user_id', $siswa->id)->count());
    }
}
