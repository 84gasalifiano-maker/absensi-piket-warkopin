<?php

namespace Tests\Feature\Jadwal;

use App\Enums\Role;
use App\Enums\Tugas;
use App\Models\DutySchedule;
use App\Models\Kelas;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/** SPEC F2 "Kelas yang dibuka di /jadwal", §7, §8.1, §9 (v0.15): siapa boleh membuka kelas mana. */
class AksesJadwalTest extends JadwalTestCase
{
    public function test_siswa_mendapat_403(): void
    {
        $siswa = $this->siswa();

        $this->actingAs($siswa)->get(route('jadwal'))->assertForbidden();
        $this->simpan($siswa, [$siswa->id => ['hari' => '1', 'tugas' => 'menyapu']])->assertForbidden();
        $this->assertSame(0, DutySchedule::count());
    }

    public function test_tamu_dialihkan_ke_login(): void
    {
        $this->get(route('jadwal'))->assertRedirect(route('login'));
        $this->post(route('jadwal.store'))->assertRedirect(route('login'));
    }

    public function test_pj_selalu_kelasnya_sendiri_parameter_kelas_diabaikan(): void
    {
        $kelasLain = $this->kelasBaru('XI RPL 2');
        $siswaLain = $this->siswa(kelas: $kelasLain);

        $this->actingAs($this->pj)->get(route('jadwal'))->assertOk()->assertSee('Jadwal piket '.$this->kelas->nama);
        $this->actingAs($this->pj)->get(route('jadwal', ['kelas' => $kelasLain->id]))
            ->assertOk()
            ->assertSee('Jadwal piket '.$this->kelas->nama)
            ->assertDontSee($siswaLain->nama);

        // Isian kelas lain diabaikan: yang disimpan tetap kelas PJ sendiri.
        $this->simpan($this->pj, [$this->pj->id => ['hari' => '1', 'tugas' => 'menyapu']], kelas: $kelasLain)
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('jadwal'));
        $this->assertSame([$this->kelas->id], DutySchedule::pluck('class_id')->all());

        // Siswa kelas lain lewat form PJ → ditolak (bukan anggota kelas PJ).
        $this->simpan($this->pj, [$siswaLain->id => ['hari' => '1', 'tugas' => 'menyapu']], kelas: $kelasLain)
            ->assertSessionHasErrors('jadwal');
        $this->assertNull($this->jadwalDi($siswaLain, $kelasLain));
    }

    public function test_tautan_jadwal_piket_untuk_pj_dan_guru(): void
    {
        $this->actingAs($this->pj)->get(route('beranda'))->assertSee('href="'.route('jadwal').'"', false);
        $this->actingAs($this->siswa())->get(route('beranda'))->assertDontSee('Jadwal piket');
        $this->actingAs($this->pj)->get(route('verifikasi'))->assertDontSee('Jadwal piket'); // PJ: tautan di beranda saja
        $this->actingAs($this->guru())->get(route('verifikasi'))->assertSee('href="'.route('jadwal').'"', false);

        $this->actingAs($this->admin)->get(route('admin.kelas.index'))
            ->assertSee('href="'.e(route('jadwal', ['kelas' => $this->kelas->id])).'"', false);
    }

    public function test_guru_dengan_satu_kelas_langsung_ke_kelasnya(): void
    {
        $guru = $this->guru();
        $this->kelas->update(['wali_kelas_id' => $guru->id]);

        $this->actingAs($guru)->get(route('jadwal'))
            ->assertOk()
            ->assertSee('Jadwal piket '.$this->kelas->nama)
            ->assertDontSee('Pilih kelas lain');
    }

    public function test_guru_dengan_beberapa_kelas_memilih_dulu(): void
    {
        $guru = $this->guru();
        $kelas2 = $this->kelasBaru('XII RPL 2');
        $bukanWalinya = $this->kelasBaru('X TKJ 3');
        $this->kelas->update(['wali_kelas_id' => $guru->id]);
        $kelas2->update(['wali_kelas_id' => $guru->id]);

        $this->actingAs($guru)->get(route('jadwal'))
            ->assertOk()
            ->assertSee('href="'.e(route('jadwal', ['kelas' => $this->kelas->id])).'"', false)
            ->assertSee('href="'.e(route('jadwal', ['kelas' => $kelas2->id])).'"', false)
            ->assertDontSee($bukanWalinya->nama);

        $this->actingAs($guru)->get(route('jadwal', ['kelas' => $kelas2->id]))
            ->assertOk()
            ->assertSee('Jadwal piket '.$kelas2->nama)
            ->assertSee('Pilih kelas lain');
    }

    public function test_guru_tanpa_kelas(): void
    {
        $this->actingAs($this->guru())->get(route('jadwal'))
            ->assertOk()
            ->assertSee('Kamu belum menjadi wali kelas mana pun.');
    }

    public function test_guru_hanya_kelas_walinya_selain_itu_404(): void
    {
        $guru = $this->guru();
        $siswa = $this->siswa();

        $this->actingAs($guru)->get($this->url())->assertNotFound();
        $this->simpan($guru, [$siswa->id => ['hari' => '1', 'tugas' => 'menyapu']])->assertNotFound();
        $this->assertSame(0, DutySchedule::count());

        // Setelah menjadi wali: boleh.
        $this->kelas->update(['wali_kelas_id' => $guru->id]);
        $this->simpan($guru, [$siswa->id => ['hari' => '1', 'tugas' => 'menyapu']])
            ->assertSessionHasNoErrors()
            ->assertRedirect($this->url());
        $this->assertSame(1, DutySchedule::count());
    }

    /** Hak akses dicek ulang saat simpan dari data terbaru: wali diganti setelah form dibuka. */
    public function test_guru_yang_baru_berhenti_jadi_wali_tidak_bisa_menyimpan(): void
    {
        $guru = $this->guru();
        $this->kelas->update(['wali_kelas_id' => $guru->id]);
        $isian = $this->isianForm($this->halaman($guru)->assertOk());

        $this->kelas->update(['wali_kelas_id' => $this->guru()->id]);
        $isian['jadwal'][$this->pj->id] = ['hari' => '1', 'tugas' => 'menyapu'];

        $this->simpan($guru, $isian['jadwal'], $isian['minggu_acuan'])->assertNotFound();
        $this->assertSame(0, DutySchedule::count());
    }

    public function test_admin_memilih_dari_kelas_sekolahnya(): void
    {
        $kelas2 = $this->kelasBaru('XII RPL 2');
        $sekolahLain = School::factory()->create();
        $kelasSekolahLain = $this->kelasBaru('X Sekolah Lain', $sekolahLain);

        $this->actingAs($this->admin)->get(route('jadwal'))
            ->assertOk()
            ->assertSee('href="'.e(route('jadwal', ['kelas' => $this->kelas->id])).'"', false)
            ->assertSee('href="'.e(route('jadwal', ['kelas' => $kelas2->id])).'"', false)
            ->assertDontSee($kelasSekolahLain->nama);

        $this->actingAs($this->admin)->get($this->url($kelas2))->assertOk()->assertSee('Pilih kelas lain');
    }

    public function test_admin_tanpa_kelas(): void
    {
        $sekolahBaru = School::factory()->create();
        $admin = $this->buatUser(Role::Admin, ['school_id' => $sekolahBaru->id]);

        $this->actingAs($admin)->get(route('jadwal'))->assertOk()->assertSee('Belum ada kelas.');
    }

    public function test_kelas_sekolah_lain_dihapus_atau_alamat_salah_404(): void
    {
        $sekolahLain = School::factory()->create();
        $kelasSekolahLain = $this->kelasBaru('X Sekolah Lain', $sekolahLain);
        $siswaSekolahLain = $this->siswa(kelas: $kelasSekolahLain);

        $this->actingAs($this->admin)->get($this->url($kelasSekolahLain))->assertNotFound();
        $this->simpan($this->admin, [$siswaSekolahLain->id => ['hari' => '1', 'tugas' => 'menyapu']], kelas: $kelasSekolahLain)
            ->assertNotFound();

        $dihapus = $this->kelasBaru('XII Dihapus');
        $idDihapus = $dihapus->id;
        $dihapus->delete();
        $this->actingAs($this->admin)->get(route('jadwal', ['kelas' => $idDihapus]))->assertNotFound();

        foreach (['abc', '1x', '-1', '0'] as $salah) {
            $this->actingAs($this->admin)->get(route('jadwal', ['kelas' => $salah]))->assertNotFound();
        }

        $this->actingAs($this->admin)->post(route('jadwal.store'), [
            'kelas' => 'abc', 'minggu_acuan' => '2026-11-02', 'jadwal' => [],
        ])->assertNotFound();

        $this->assertSame(0, DutySchedule::count());
    }

    /** Kelas bukan haknya → 404 apa pun id akun yang dikirim (keberadaan id tidak bocor). */
    public function test_kelas_bukan_haknya_404_sebelum_isian_diperiksa(): void
    {
        $guru = $this->guru();
        $siswa = $this->siswa();

        foreach ([$siswa->id, 999999] as $id) {
            $this->simpan($guru, [$id => ['hari' => '1', 'tugas' => 'menyapu']])->assertNotFound();
        }
        $this->assertSame(0, DutySchedule::count());
    }

    public function test_simpan_hanya_lewat_form_dengan_csrf(): void
    {
        // CSRF aktif (Laravel mematikannya saat tes): tanpa token → 419, tidak ada yang berubah.
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->simpan($this->pj, [$this->pj->id => ['hari' => '1', 'tugas' => 'menyapu']])->assertStatus(419);
        $this->assertSame(0, DutySchedule::count());

        // Form halaman jadwal membawa token CSRF.
        $this->halaman($this->pj)->assertSee('name="_token"', false);
    }

    public function test_pj_yang_sudah_dipindah_kelas_tidak_bisa_menyimpan_kelas_lamanya(): void
    {
        // Kelas PJ diambil dari akunnya saat simpan, bukan dari form.
        $kelasBaru = $this->kelasBaru('XI RPL 2');
        $siswa = $this->siswa();
        $this->jadwalTersimpan($siswa, 3, Tugas::Mengepel);
        $this->pj->update(['class_id' => $kelasBaru->id]);

        $this->simpan($this->pj, [$siswa->id => ['hari' => '1', 'tugas' => 'menyapu']])->assertSessionHasErrors('jadwal');
        $this->assertSame(['hari' => 3, 'tugas_awal' => Tugas::Mengepel], $this->jadwalDi($siswa));
    }

    /**
     * Hak akses PJ dicek ulang dari baris akunnya yang dikunci di transaksi (SPEC §11 Langkah 8
     * langkah 3), bukan dari data akun yang dibaca di awal request. Di sini data akun yang
     * dipakai request ($this->pj lewat actingAs) sengaja dibiarkan basi: database diubah
     * langsung, seolah admin memindahkan / menurunkan PJ tepat saat ia menekan Simpan.
     *
     * @return array<string, array{string}>
     */
    public static function pjBerubahSaatMenyimpan(): array
    {
        return [
            'dipindah ke kelas lain' => ['pindah'],
            'diturunkan jadi siswa' => ['turun'],
        ];
    }

    #[DataProvider('pjBerubahSaatMenyimpan')]
    public function test_hak_akses_pj_dicek_ulang_dari_baris_yang_dikunci(string $perubahan): void
    {
        $siswa = $this->siswa();
        $this->jadwalTersimpan($siswa, 3, Tugas::Mengepel);
        $kelasLain = $this->kelasBaru('XI RPL 2');

        DB::table('users')->where('id', $this->pj->id)->update(
            $perubahan === 'pindah' ? ['class_id' => $kelasLain->id] : ['role' => Role::Siswa->value],
        );
        $this->assertSame($this->kelas->id, $this->pj->class_id); // objek request masih basi
        $this->assertSame(Role::PjKebersihan, $this->pj->role);

        $this->simpan($this->pj, [$siswa->id => ['hari' => '1', 'tugas' => 'menyapu']])->assertNotFound();
        $this->assertSame(['hari' => 3, 'tugas_awal' => Tugas::Mengepel], $this->jadwalDi($siswa));
    }

    /** Role yang tidak dikenal oleh aturan kelas → tidak mendapat kelas apa pun. */
    public function test_aturan_kelas_tidak_memberi_kelas_ke_siswa(): void
    {
        /** @var User $siswa */
        $siswa = $this->siswa();

        $this->assertSame(0, Kelas::query()->jadwalBolehDikelolaOleh($siswa)->count());
        $this->assertSame(1, Kelas::query()->jadwalBolehDikelolaOleh($this->pj)->count());
    }
}
