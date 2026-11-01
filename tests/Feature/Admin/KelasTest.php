<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Http\Controllers\Admin\KelasController;
use App\Http\Requests\Admin\SimpanKelasRequest;
use App\Models\Kelas;
use App\Models\School;
use Illuminate\Support\Facades\DB;

/** SPEC F9 (kelas) + F2 (tanggal_mulai_rotasi). */
class KelasTest extends AdminTestCase
{
    /** @param  array<string, mixed>  $atribut */
    private function tambah(array $atribut = []): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->from('/admin/kelas')->post('/admin/kelas', $atribut + [
            'nama' => 'XI RPL 2',
            'wali_kelas_id' => null,
            'tanggal_mulai_rotasi' => '2026-11-02', // Senin
        ]);
    }

    public function test_dashboard_menampilkan_ringkasan_kelas(): void
    {
        $wali = $this->guru();
        $this->kelas->update(['wali_kelas_id' => $wali->id]);
        $pj = $this->siswa(Role::PjKebersihan);
        $this->siswa();
        $this->siswa();

        $this->actingAs($this->admin)->get('/admin')
            ->assertOk()
            ->assertSeeInOrder(['XI RPL 1', $wali->nama, $pj->nama, '3'])
            ->assertSee('Hari libur')
            ->assertSee('(segera)');
    }

    public function test_tambah_kelas(): void
    {
        $wali = $this->guru();

        $this->tambah(['wali_kelas_id' => $wali->id])
            ->assertRedirect('/admin/kelas')
            ->assertSessionHas('status', 'Kelas XI RPL 2 ditambahkan.');

        $kelas = Kelas::where('nama', 'XI RPL 2')->firstOrFail();
        $this->assertSame($this->sekolah->id, $kelas->school_id);
        $this->assertSame($wali->id, $kelas->wali_kelas_id);
        $this->assertSame('2026-11-02', $kelas->tanggal_mulai_rotasi->toDateString());
    }

    public function test_school_id_dari_form_diabaikan(): void
    {
        $sekolahLain = School::factory()->create();

        $this->tambah(['school_id' => $sekolahLain->id])->assertRedirect('/admin/kelas');

        $this->assertSame($this->sekolah->id, Kelas::where('nama', 'XI RPL 2')->firstOrFail()->school_id);
    }

    public function test_nama_kelas_unik_per_sekolah(): void
    {
        $this->tambah(['nama' => 'XI RPL 1'])
            ->assertSessionHasErrors(['nama' => 'Nama kelas sudah dipakai di sekolah ini.']);

        // Sekolah lain boleh punya nama kelas yang sama.
        $this->kelasBaru('XI RPL 2', School::factory()->create());
        $this->tambah(['nama' => 'XI RPL 2'])->assertSessionHasNoErrors();
    }

    public function test_tanggal_mulai_rotasi_wajib_dan_harus_senin(): void
    {
        $this->tambah(['tanggal_mulai_rotasi' => ''])
            ->assertSessionHasErrors(['tanggal_mulai_rotasi' => 'Tanggal mulai rotasi wajib diisi.']);

        $this->tambah(['tanggal_mulai_rotasi' => '2026-11-03']) // Selasa
            ->assertSessionHasErrors(['tanggal_mulai_rotasi' => SimpanKelasRequest::PESAN_BUKAN_SENIN]);

        $this->tambah(['tanggal_mulai_rotasi' => 'bukan-tanggal'])
            ->assertSessionHasErrors('tanggal_mulai_rotasi');

        $this->assertDatabaseMissing('classes', ['nama' => 'XI RPL 2']);
    }

    public function test_wali_kelas_harus_guru_di_sekolah_ini(): void
    {
        $pesan = ['wali_kelas_id' => 'Wali kelas harus akun guru di sekolah ini.'];

        $this->tambah(['wali_kelas_id' => $this->siswa()->id])->assertSessionHasErrors($pesan);
        $this->tambah(['wali_kelas_id' => $this->guru(School::factory()->create())->id])->assertSessionHasErrors($pesan);
    }

    public function test_ubah_kelas(): void
    {
        $wali = $this->guru();

        $this->actingAs($this->admin)->get("/admin/kelas/{$this->kelas->id}/edit")
            ->assertOk()
            ->assertSee('Tanggal mulai rotasi (harus hari Senin)');

        $this->actingAs($this->admin)->put("/admin/kelas/{$this->kelas->id}", [
            'nama' => 'XI RPL 1A',
            'wali_kelas_id' => $wali->id,
            'tanggal_mulai_rotasi' => '2026-11-09',
        ])->assertRedirect('/admin/kelas');

        $this->kelas->refresh();
        $this->assertSame('XI RPL 1A', $this->kelas->nama);
        $this->assertSame($wali->id, $this->kelas->wali_kelas_id);
        $this->assertSame('2026-11-09', $this->kelas->tanggal_mulai_rotasi->toDateString());
    }

    public function test_ubah_kelas_dengan_nama_sendiri_boleh(): void
    {
        $this->actingAs($this->admin)->put("/admin/kelas/{$this->kelas->id}", [
            'nama' => 'XI RPL 1',
            'tanggal_mulai_rotasi' => '2026-10-26',
        ])->assertSessionHasNoErrors();
    }

    public function test_tanggal_mulai_terkunci_setelah_kelas_punya_submit(): void
    {
        $this->submit($this->siswa(), $this->kelas);

        $html = $this->actingAs($this->admin)->get("/admin/kelas/{$this->kelas->id}/edit")
            ->assertOk()
            ->assertSee(SimpanKelasRequest::PESAN_TERKUNCI)
            ->getContent();

        // Input tanggal nonaktif dan tidak punya name (jadi tidak ikut terkirim).
        $this->assertMatchesRegularExpression('/<input id="tanggal_mulai_rotasi"[^>]*\bdisabled\b/', $html);
        $this->assertStringNotContainsString('name="tanggal_mulai_rotasi"', $html);

        // Dikirim paksa dengan tanggal lain → ditolak.
        $this->actingAs($this->admin)->from("/admin/kelas/{$this->kelas->id}/edit")
            ->put("/admin/kelas/{$this->kelas->id}", ['nama' => 'XI RPL 1', 'tanggal_mulai_rotasi' => '2026-11-09'])
            ->assertSessionHasErrors(['tanggal_mulai_rotasi' => SimpanKelasRequest::PESAN_TERKUNCI]);

        // Tanpa tanggal (kolom nonaktif tidak terkirim) → isian lain tetap tersimpan.
        $this->actingAs($this->admin)
            ->put("/admin/kelas/{$this->kelas->id}", ['nama' => 'XI RPL 1B'])
            ->assertSessionHasNoErrors();

        $this->kelas->refresh();
        $this->assertSame('XI RPL 1B', $this->kelas->nama);
        $this->assertSame('2026-10-26', $this->kelas->tanggal_mulai_rotasi->toDateString());
    }

    public function test_petunjuk_tanggal_terhubung_ke_inputnya(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin/kelas')->getContent();

        $this->assertMatchesRegularExpression('/name="tanggal_mulai_rotasi"[^>]*aria-describedby="tanggal_mulai_rotasi-bantuan"/', $html);
        $this->assertStringContainsString('id="tanggal_mulai_rotasi-bantuan"', $html);

        // Setelah error, petunjuk dan pesan error sama-sama terhubung.
        $html = $this->actingAs($this->admin)->from('/admin/kelas')->followingRedirects()
            ->post('/admin/kelas', ['nama' => 'X', 'tanggal_mulai_rotasi' => '2026-11-03'])
            ->getContent();
        $this->assertMatchesRegularExpression('/aria-describedby="tanggal_mulai_rotasi-bantuan tanggal_mulai_rotasi-error"/', $html);
    }

    public function test_isian_yang_dikosongkan_tidak_terisi_lagi_setelah_error(): void
    {
        $html = $this->actingAs($this->admin)->from("/admin/kelas/{$this->kelas->id}/edit")
            ->followingRedirects()
            ->put("/admin/kelas/{$this->kelas->id}", ['nama' => '', 'tanggal_mulai_rotasi' => '2026-10-26'])
            ->assertSee('Nama kelas wajib diisi.')
            ->getContent();

        $this->assertMatchesRegularExpression('/name="nama"[^>]*value=""/', $html);
    }

    public function test_isian_berbentuk_array_ditolak_tanpa_error_server(): void
    {
        $this->tambah(['nama' => ['XI', 'RPL']])->assertSessionHasErrors('nama');
    }

    public function test_hapus_kelas_kosong(): void
    {
        $kosong = $this->kelasBaru('Kelas Kosong');

        $this->actingAs($this->admin)->delete("/admin/kelas/{$kosong->id}")
            ->assertRedirect('/admin/kelas')
            ->assertSessionHas('status', 'Kelas Kelas Kosong dihapus.');

        $this->assertModelMissing($kosong);
    }

    public function test_hapus_kelas_yang_masih_punya_siswa_atau_riwayat_ditolak(): void
    {
        $pesan = ['hapus' => 'Kelas ini masih punya siswa atau riwayat piket.'];

        // Punya siswa.
        $this->siswa();
        $this->actingAs($this->admin)->from('/admin/kelas')->delete("/admin/kelas/{$this->kelas->id}")
            ->assertSessionHasErrors($pesan);
        $this->assertModelExists($this->kelas);

        // Tidak punya siswa lagi, tapi punya riwayat submit (siswanya sudah pindah).
        $lama = $this->kelasBaru('Kelas Lama');
        $this->submit($this->siswa(), $lama);
        $this->actingAs($this->admin)->from('/admin/kelas')->delete("/admin/kelas/{$lama->id}")
            ->assertSessionHasErrors($pesan);
        $this->assertModelExists($lama);
    }

    /**
     * Siswa ditambahkan ke kelas tepat setelah pengecekan "kelas kosong" lolos, sebelum
     * kelas dihapus: database menolak (foreign key) → pesan biasa, bukan error 500 (v0.9).
     */
    public function test_hapus_kelas_yang_bentrok_dengan_penambahan_siswa(): void
    {
        $kosong = $this->kelasBaru('Kelas Kosong');
        Kelas::deleting(fn () => $this->siswa(Role::Siswa, $kosong));

        $this->actingAs($this->admin)->from('/admin/kelas')->delete("/admin/kelas/{$kosong->id}")
            ->assertRedirect('/admin/kelas')
            ->assertSessionHasErrors(['hapus' => KelasController::PESAN_TIDAK_KOSONG])
            ->assertSessionMissing('status');

        $this->assertModelExists($kosong);
    }

    /** Kelas yang baru saja dihapus admin lain (setelah halaman dibuka) → 404. */
    public function test_hapus_kelas_yang_baru_saja_dihapus(): void
    {
        $kosong = $this->kelasBaru('Kelas Kosong');

        // Dijalankan sekali, saat kelas dari URL selesai dimuat (sebelum dikunci di controller).
        $sudah = false;
        Kelas::retrieved(function (Kelas $kelas) use ($kosong, &$sudah) {
            if (! $sudah && $kelas->is($kosong)) {
                $sudah = true;
                DB::table('classes')->where('id', $kosong->id)->delete();
            }
        });

        $this->actingAs($this->admin)->delete("/admin/kelas/{$kosong->id}")
            ->assertNotFound()
            ->assertSessionMissing('status');
    }
}
