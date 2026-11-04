<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Models\School;
use DOMDocument;
use DOMXPath;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

/** SPEC F4, §8.1, §11 Langkah 7 (v0.13): pengaturan jam buka/tutup oleh admin. */
class PengaturanTest extends AdminTestCase
{
    private const BENAR = [
        'jam_buka_pagi' => '06:15', 'jam_tutup_pagi' => '07:45',
        'jam_buka_siang' => '13:30', 'jam_tutup_siang' => '15:00',
    ];

    private const NAMA = [
        'jam_buka_pagi' => 'Jam buka pagi', 'jam_tutup_pagi' => 'Jam tutup pagi',
        'jam_buka_siang' => 'Jam buka siang', 'jam_tutup_siang' => 'Jam tutup siang',
    ];

    private const PESAN_URUTAN = [
        'jam_tutup_pagi' => 'Jam tutup pagi harus setelah jam buka pagi.',
        'jam_buka_siang' => 'Jam buka siang harus setelah jam tutup pagi.',
        'jam_tutup_siang' => 'Jam tutup siang harus setelah jam buka siang.',
    ];

    private function halaman(): TestResponse
    {
        return $this->actingAs($this->admin)->get('/admin/pengaturan');
    }

    /** @param  array<string, mixed>  $isian */
    private function simpan(array $isian = []): TestResponse
    {
        return $this->actingAs($this->admin)
            ->from('/admin/pengaturan')
            ->post('/admin/pengaturan', $isian + self::BENAR);
    }

    /** @return array<string, string> jam tersimpan apa adanya di database */
    private function jamDiDatabase(School $sekolah): array
    {
        return (array) DB::table('schools')->where('id', $sekolah->id)->first(School::KOLOM_JAM);
    }

    private function assertJamTidakBerubah(): void
    {
        $this->assertSame([
            'jam_buka_pagi' => '06:00:00', 'jam_tutup_pagi' => '07:30:00',
            'jam_buka_siang' => '14:00:00', 'jam_tutup_siang' => '15:30:00',
        ], $this->jamDiDatabase($this->sekolah));
    }

    private function xpath(string $html): DOMXPath
    {
        $dokumen = new DOMDocument;
        libxml_use_internal_errors(true);
        $dokumen->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        return new DOMXPath($dokumen);
    }

    /** @return array<string, string> nilai isian jam di form */
    private function nilaiForm(string $html): array
    {
        $xpath = $this->xpath($html);

        return collect(School::KOLOM_JAM)
            ->mapWithKeys(fn ($kolom) => [$kolom => $xpath->query("//input[@name='{$kolom}'][@type='time']")->item(0)?->attributes->getNamedItem('value')?->nodeValue])
            ->all();
    }

    // ---- Halaman ----

    public function test_sekolah_baru_menampilkan_jam_default_tanpa_detik(): void
    {
        $html = $this->halaman()->assertOk()->getContent();

        $this->assertSame([
            'jam_buka_pagi' => '06:00', 'jam_tutup_pagi' => '07:30',
            'jam_buka_siang' => '14:00', 'jam_tutup_siang' => '15:30',
        ], $this->nilaiForm($html));
    }

    public function test_jam_dengan_detik_tampil_tanpa_detik(): void
    {
        DB::table('schools')->where('id', $this->sekolah->id)->update(['jam_buka_pagi' => '06:15:30']);

        $this->assertSame('06:15', $this->nilaiForm($this->halaman()->getContent())['jam_buka_pagi']);
    }

    public function test_teks_halaman_sesuai_spec(): void
    {
        $html = $this->halaman()->getContent();
        $xpath = $this->xpath($html);

        $this->assertSame('Pengaturan', trim($xpath->query('//h1')->item(0)?->textContent ?? ''));
        $this->halaman()->assertSeeInOrder([
            'Jam tepat waktu (WIB), berlaku Senin–Jumat.',
            'Jam tutup dihitung sampai akhir menit itu (mis. 07:30 berarti sampai 07:30:59).',
            'Perubahan langsung berlaku, termasuk hari ini. Submit yang sudah masuk tidak berubah.',
            'Pagi', 'Jam buka', 'Jam tutup',
            'Siang', 'Jam buka', 'Jam tutup',
            'Simpan',
        ]);
        $this->assertSame(['Pagi', 'Siang'], collect($xpath->query('//form//legend'))->map(fn ($l) => trim($l->textContent))->all());
    }

    public function test_menu_pengaturan_aktif_di_dashboard(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin')->getContent();

        $this->assertStringContainsString('href="'.route('admin.pengaturan.edit').'"', $html);
        $this->assertStringNotContainsString('(segera)', $html);

        // Urutan menu sesuai SPEC F9: Kelas, Akun, Pengaturan, Hari libur.
        $menu = collect($this->xpath($html)->query('//nav[@aria-label="Menu admin"]/a'))->map(fn ($a) => trim($a->textContent))->all();
        $this->assertSame(['Dashboard', 'Kelas', 'Akun', 'Pengaturan', 'Hari libur'], $menu);
    }

    // ---- Simpan ----

    public function test_simpan_jam(): void
    {
        $sekolahLain = School::factory()->create();
        $jamSekolahLain = $this->jamDiDatabase($sekolahLain);
        $namaLama = $this->sekolah->nama;

        $this->simpan(['nama' => 'Diganti', 'school_id' => $sekolahLain->id, 'id' => $sekolahLain->id])
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/pengaturan')
            ->assertSessionHas('status', 'Pengaturan jam disimpan.');

        // Disimpan JJ:MM:00, sama di MySQL dan SQLite.
        $this->assertSame([
            'jam_buka_pagi' => '06:15:00', 'jam_tutup_pagi' => '07:45:00',
            'jam_buka_siang' => '13:30:00', 'jam_tutup_siang' => '15:00:00',
        ], $this->jamDiDatabase($this->sekolah));

        // Hanya sekolah admin, dan hanya keempat jam.
        $this->assertSame($jamSekolahLain, $this->jamDiDatabase($sekolahLain));
        $this->assertSame($namaLama, $this->sekolah->fresh()->nama);

        // Form menampilkan nilai baru, tanpa detik.
        $this->assertSame(self::BENAR, $this->nilaiForm($this->halaman()->getContent()));
    }

    public function test_pesan_sukses_tampil_di_halaman(): void
    {
        $this->followingRedirects()
            ->actingAs($this->admin)
            ->post('/admin/pengaturan', self::BENAR)
            ->assertOk()
            ->assertSee('Pengaturan jam disimpan.');
    }

    public function test_admin_sekolah_lain_melihat_jam_sekolahnya_sendiri(): void
    {
        $this->simpan()->assertSessionHasNoErrors();
        $sekolahLain = School::factory()->create();
        $adminLain = $this->buatUser(Role::Admin, ['school_id' => $sekolahLain->id, 'email' => 'admin@lain.test']);

        $html = $this->actingAs($adminLain)->get('/admin/pengaturan')->getContent();

        $this->assertSame('06:00', $this->nilaiForm($html)['jam_buka_pagi']);
    }

    // ---- Validasi ----

    public function test_jam_kosong_ditolak(): void
    {
        foreach (School::KOLOM_JAM as $kolom) {
            foreach (['', '   '] as $kosong) {
                $this->simpan([$kolom => $kosong])
                    ->assertSessionHasErrors([$kolom => self::NAMA[$kolom].' wajib diisi.']);
            }

            // Kolom tidak dikirim sama sekali.
            $this->actingAs($this->admin)->from('/admin/pengaturan')
                ->post('/admin/pengaturan', array_diff_key(self::BENAR, [$kolom => true]))
                ->assertSessionHasErrors([$kolom => self::NAMA[$kolom].' wajib diisi.']);
        }

        $this->assertJamTidakBerubah();
    }

    public function test_format_jam_salah_ditolak(): void
    {
        $salah = ['6:00', '24:00', '06:60', '06:00:00', '0600', '06.00', 'jam enam', '-06:00', ['06:00']];

        foreach (School::KOLOM_JAM as $kolom) {
            foreach ($salah as $nilai) {
                $this->simpan([$kolom => $nilai])
                    ->assertSessionHasErrors([$kolom => self::NAMA[$kolom].' harus berupa jam yang valid, mis. 06:00.']);
            }
        }

        $this->assertJamTidakBerubah();
    }

    public function test_urutan_salah_atau_sama_ditolak_di_kolom_yang_salah(): void
    {
        $kasus = [
            'jam_tutup_pagi' => [['jam_tutup_pagi' => '06:15'], ['jam_tutup_pagi' => '06:00']],   // sama, lebih awal
            'jam_buka_siang' => [['jam_buka_siang' => '07:45'], ['jam_buka_siang' => '07:00']],
            'jam_tutup_siang' => [['jam_tutup_siang' => '13:30'], ['jam_tutup_siang' => '12:00']],
        ];

        foreach ($kasus as $kolom => $daftar) {
            foreach ($daftar as $isian) {
                $respons = $this->simpan($isian)->assertSessionHasErrors([$kolom => self::PESAN_URUTAN[$kolom]]);
                // Hanya kolom itu yang salah.
                $this->assertSame([$kolom], array_keys(session('errors')->getBag('default')->messages()));
                $respons->assertSessionHasInput($isian + self::BENAR);
            }
        }

        $this->assertJamTidakBerubah();
    }

    public function test_beberapa_pesan_urutan_sekaligus(): void
    {
        // Semua terbalik: tiap pasangan salah.
        $this->simpan([
            'jam_buka_pagi' => '15:00', 'jam_tutup_pagi' => '14:00',
            'jam_buka_siang' => '07:00', 'jam_tutup_siang' => '06:00',
        ])->assertSessionHasErrors(self::PESAN_URUTAN);

        $this->assertJamTidakBerubah();
    }

    public function test_urutan_tidak_dicek_kalau_ada_format_salah(): void
    {
        $this->simpan(['jam_buka_pagi' => 'salah', 'jam_tutup_siang' => '01:00'])
            ->assertSessionHasErrors(['jam_buka_pagi'])
            ->assertSessionDoesntHaveErrors(['jam_tutup_siang']);
    }

    public function test_selisih_satu_menit_dan_batas_hari_diterima(): void
    {
        $this->simpan([
            'jam_buka_pagi' => '00:00', 'jam_tutup_pagi' => '00:01',
            'jam_buka_siang' => '00:02', 'jam_tutup_siang' => '23:59',
        ])->assertSessionHasNoErrors();

        $this->assertSame([
            'jam_buka_pagi' => '00:00:00', 'jam_tutup_pagi' => '00:01:00',
            'jam_buka_siang' => '00:02:00', 'jam_tutup_siang' => '23:59:00',
        ], $this->jamDiDatabase($this->sekolah));
    }

    public function test_isian_tidak_hilang_saat_error(): void
    {
        $isian = array_merge(self::BENAR, ['jam_tutup_pagi' => '06:00']);
        $this->simpan($isian)->assertSessionHasInput($isian);

        $this->assertSame($isian, $this->nilaiForm($this->halaman()->getContent()));
    }

    // ---- CSRF ----

    public function test_simpan_wajib_csrf(): void
    {
        $form = $this->xpath($this->halaman()->getContent())
            ->query('//form[@method="POST"][@action="'.route('admin.pengaturan.update').'"]/input[@name="_token"][@value!=""]');
        $this->assertSame(1, $form->length);

        // CSRF aktif (Laravel mematikannya saat tes): tanpa token → 419, tidak ada yang berubah.
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->simpan()->assertStatus(419);
        $this->assertJamTidakBerubah();
    }
}
