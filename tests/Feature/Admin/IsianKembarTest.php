<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Http\Requests\Admin\SimpanKelasRequest;
use App\Models\Kelas;
use App\Models\User;
use App\Support\TangkapDuplikat;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * SPEC F9 (v0.9): dua penyimpanan bersamaan sama-sama lolos validasi, lalu yang
 * kedua ditolak UNIQUE database → tampil sebagai pesan validasi, bukan error 500.
 *
 * Disimulasikan dengan menyisipkan baris kembar tepat SETELAH validasi lolos dan
 * SEBELUM data disimpan (event model "creating"/"updating").
 */
class IsianKembarTest extends AdminTestCase
{
    /** @param  array<string, mixed>  $kolom */
    private function sisipkanUserKembar(array $kolom): void
    {
        DB::table('users')->insert($kolom + [
            'school_id' => $this->sekolah->id, 'nama' => 'Penyimpanan lain',
            'password' => 'x', 'role' => 'siswa', 'harus_ganti_password' => true,
        ]);
    }

    public function test_nis_kembar_saat_tambah(): void
    {
        User::creating(fn () => $this->sisipkanUserKembar(['nis' => '12345678']));

        $this->actingAs($this->admin)->from('/admin/akun/baru')->post('/admin/akun', [
            'nama' => 'Siswa Baru', 'role' => 'siswa', 'nis' => '12345678', 'class_id' => $this->kelas->id, 'no_absen' => 30,
        ])
            ->assertRedirect('/admin/akun/baru')
            ->assertSessionHasErrors(['nis' => 'NIS sudah dipakai.']);

        $this->assertNull(session('password_sekali'));
    }

    public function test_email_kembar_saat_tambah(): void
    {
        User::creating(fn () => $this->sisipkanUserKembar(['email' => 'guru.baru@sekolah.test', 'role' => 'guru']));

        $this->actingAs($this->admin)->from('/admin/akun/baru')->post('/admin/akun', [
            'nama' => 'Guru Baru', 'role' => 'guru', 'email' => 'guru.baru@sekolah.test',
        ])->assertSessionHasErrors(['email' => 'Email sudah dipakai.']);
    }

    public function test_no_absen_kembar_saat_ubah(): void
    {
        $siswa = $this->siswa();
        User::updating(fn () => $this->sisipkanUserKembar([
            'nis' => '87654321', 'class_id' => $this->kelas->id, 'no_absen' => 40,
        ]));

        $this->actingAs($this->admin)->from("/admin/akun/{$siswa->id}/edit")->put("/admin/akun/{$siswa->id}", [
            'nama' => $siswa->nama, 'role' => 'siswa', 'nis' => $siswa->nis, 'class_id' => $this->kelas->id, 'no_absen' => 40,
        ])
            ->assertRedirect("/admin/akun/{$siswa->id}/edit")
            ->assertSessionHasErrors(['no_absen' => 'No. absen ini sudah dipakai di kelas tersebut.']);

        $this->assertNotSame(40, $siswa->fresh()->no_absen);
    }

    public function test_nama_kelas_kembar_saat_tambah_dan_ubah(): void
    {
        $sisipkan = fn (string $nama) => DB::table('classes')->insert([
            'school_id' => $this->sekolah->id, 'nama' => $nama, 'tanggal_mulai_rotasi' => '2026-10-26',
        ]);

        Kelas::creating(fn () => $sisipkan('XI RPL 5'));
        $this->actingAs($this->admin)->from('/admin/kelas')->post('/admin/kelas', [
            'nama' => 'XI RPL 5', 'tanggal_mulai_rotasi' => '2026-11-02',
        ])->assertSessionHasErrors(['nama' => 'Nama kelas sudah dipakai di sekolah ini.']);

        Kelas::updating(fn () => $sisipkan('XI RPL 6'));
        $this->actingAs($this->admin)->from("/admin/kelas/{$this->kelas->id}/edit")->put("/admin/kelas/{$this->kelas->id}", [
            'nama' => 'XI RPL 6', 'tanggal_mulai_rotasi' => '2026-10-26',
        ])->assertSessionHasErrors(['nama' => 'Nama kelas sudah dipakai di sekolah ini.']);

        $this->assertSame('XI RPL 1', $this->kelas->fresh()->nama);
    }

    public function test_nis_kembar_saat_ubah(): void
    {
        $siswa = $this->siswa();
        User::updating(fn () => $this->sisipkanUserKembar(['nis' => '55556666']));

        $this->actingAs($this->admin)->from("/admin/akun/{$siswa->id}/edit")->put("/admin/akun/{$siswa->id}", [
            'nama' => $siswa->nama, 'role' => 'siswa', 'nis' => '55556666', 'class_id' => $this->kelas->id, 'no_absen' => $siswa->no_absen,
        ])->assertSessionHasErrors(['nis' => 'NIS sudah dipakai.']);

        $this->assertNotSame('55556666', $siswa->fresh()->nis);
    }

    public function test_email_kembar_saat_ubah(): void
    {
        $guru = $this->guru();
        User::updating(fn () => $this->sisipkanUserKembar(['email' => 'kembar@sekolah.test', 'role' => 'guru']));

        $this->actingAs($this->admin)->from("/admin/akun/{$guru->id}/edit")->put("/admin/akun/{$guru->id}", [
            'nama' => $guru->nama, 'email' => 'kembar@sekolah.test',
        ])->assertSessionHasErrors(['email' => 'Email sudah dipakai.']);
    }

    /**
     * Pesan error database memuat SQL beserta isian form. Isian yang kebetulan berisi
     * nama constraint lain ("users.nis") tidak boleh membuat pesan jatuh ke kolom lain.
     */
    public function test_isian_mirip_nama_constraint_tidak_salah_kolom(): void
    {
        User::creating(fn () => $this->sisipkanUserKembar([
            'nis' => '99990000', 'class_id' => $this->kelas->id, 'no_absen' => 30,
        ]));

        $this->actingAs($this->admin)->from('/admin/akun/baru')->post('/admin/akun', [
            'nama' => 'Budi users.nis users_nis_unique', 'role' => 'siswa', 'nis' => '12121212',
            'class_id' => $this->kelas->id, 'no_absen' => 30,
        ])
            ->assertSessionHasErrors(['no_absen' => 'No. absen ini sudah dipakai di kelas tersebut.'])
            ->assertSessionDoesntHaveErrors('nis');
    }

    public function test_unique_yang_tidak_dikenal_tetap_menjadi_error(): void
    {
        $this->sekolah->hariLibur()->create(['tanggal' => '2026-12-25', 'keterangan' => 'Natal']);

        $this->expectException(UniqueConstraintViolationException::class);
        TangkapDuplikat::jalankan(
            fn () => $this->sekolah->hariLibur()->create(['tanggal' => '2026-12-25', 'keterangan' => 'Dobel']),
            [[['users_nis_unique', 'users.nis'], 'nis', 'NIS sudah dipakai.']],
        );
    }

    /**
     * Pembacaan nama constraint dari pesan MySQL, termasuk isian kembar yang sengaja
     * berisi teks "for key '…'" (isian ada di tanda kutip pertama, nama key di ujung).
     */
    public function test_nama_constraint_dari_pesan_mysql_dan_sqlite(): void
    {
        $pengecualian = function (string $pesanDriver): UniqueConstraintViolationException {
            $pdo = new \PDOException($pesanDriver);
            $pdo->errorInfo = ['23000', 1062, $pesanDriver];

            return new UniqueConstraintViolationException('mysql', 'insert into users ...', [], $pdo);
        };

        $this->assertSame('users_nis_unique', TangkapDuplikat::namaConstraint(
            $pengecualian("Duplicate entry '12345678' for key 'users.users_nis_unique'"),
        ));
        $this->assertSame('users_nis_unique', TangkapDuplikat::namaConstraint(
            $pengecualian("Duplicate entry 'x' for key 'users.users_email_unique' for key 'users.users_nis_unique'"),
        ));
        $this->assertSame('users_email_unique', TangkapDuplikat::namaConstraint(
            $pengecualian("Duplicate entry 'a@b.test' for key 'users_email_unique'"), // MariaDB / MySQL lama
        ));
        $this->assertSame('users.class_id, users.no_absen', TangkapDuplikat::namaConstraint(
            $pengecualian('UNIQUE constraint failed: users.class_id, users.no_absen'),
        ));
        $this->assertNull(TangkapDuplikat::namaConstraint($pengecualian('pesan lain')));
    }

    /**
     * Guru dipilih sebagai wali, lalu dihapus admin lain tepat sebelum kelas disimpan:
     * database menolak (foreign key) → pesan di kolom wali kelas, bukan error 500.
     */
    public function test_wali_kelas_yang_baru_dihapus_saat_tambah_dan_ubah(): void
    {
        $pesan = ['wali_kelas_id' => SimpanKelasRequest::PESAN_WALI];

        $guru = $this->guru();
        Kelas::creating(fn () => DB::table('users')->where('id', $guru->id)->delete());
        $this->actingAs($this->admin)->from('/admin/kelas')->post('/admin/kelas', [
            'nama' => 'XI RPL 7', 'wali_kelas_id' => $guru->id, 'tanggal_mulai_rotasi' => '2026-11-02',
        ])->assertRedirect('/admin/kelas')->assertSessionHasErrors($pesan);
        $this->assertDatabaseMissing('classes', ['nama' => 'XI RPL 7']);

        $guru2 = $this->guru();
        Kelas::updating(fn () => DB::table('users')->where('id', $guru2->id)->delete());
        $this->actingAs($this->admin)->from("/admin/kelas/{$this->kelas->id}/edit")->put("/admin/kelas/{$this->kelas->id}", [
            'nama' => 'XI RPL 1', 'wali_kelas_id' => $guru2->id, 'tanggal_mulai_rotasi' => '2026-10-26',
        ])->assertSessionHasErrors($pesan);
        $this->assertNull($this->kelas->fresh()->wali_kelas_id);
    }

    public function test_tanpa_tabrakan_tetap_tersimpan(): void
    {
        $this->actingAs($this->admin)->post('/admin/akun', [
            'nama' => 'Siswa Baru', 'role' => Role::Siswa->value, 'nis' => '11112222', 'class_id' => $this->kelas->id, 'no_absen' => 31,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['nis' => '11112222']);
    }
}
