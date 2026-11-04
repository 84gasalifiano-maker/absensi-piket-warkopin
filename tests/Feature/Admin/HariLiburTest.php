<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Enums\SubmissionStatus;
use App\Enums\Tugas;
use App\Http\Requests\Admin\TambahHariLiburRequest;
use App\Models\HariLibur;
use App\Models\Kelas;
use App\Models\School;
use App\Models\Submission;
use Carbon\CarbonImmutable;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;

/** SPEC F8, §7, §8.1, §11 Langkah 6 (v0.12): kelola hari libur oleh admin. */
class HariLiburTest extends AdminTestCase
{
    private const PESAN_WAJIB_TANGGAL = 'Tanggal wajib diisi.';

    private const PESAN_TIDAK_VALID = 'Tanggal harus berupa tanggal yang valid.';

    private const PESAN_AKHIR_PEKAN = 'Hari libur hanya untuk Senin–Jumat. Sabtu dan Minggu memang tidak ada piket.';

    private const PESAN_SUDAH_TERDAFTAR = 'Tanggal ini sudah terdaftar sebagai hari libur.';

    private const PESAN_ADA_SUBMIT = 'Tanggal ini sudah punya submit piket, jadi tidak bisa dijadikan hari libur.';

    private const PESAN_WAJIB_KETERANGAN = 'Keterangan wajib diisi.';

    protected function setUp(): void
    {
        parent::setUp();

        // "Hari ini" = Rabu, 4 Nov 2026, siang WIB.
        $this->travelTo(CarbonImmutable::parse('2026-11-04 10:00:00', 'Asia/Jakarta'));
    }

    private function halaman(): TestResponse
    {
        return $this->actingAs($this->admin)->get('/admin/hari-libur');
    }

    /** @param  array<string, mixed>  $isian */
    private function tambah(array $isian = []): TestResponse
    {
        return $this->actingAs($this->admin)
            ->from('/admin/hari-libur')
            ->post('/admin/hari-libur', $isian + ['tanggal' => '2026-11-11', 'keterangan' => 'Libur nasional']);
    }

    private function hapus(HariLibur $libur): TestResponse
    {
        return $this->actingAs($this->admin)->from('/admin/hari-libur')->delete("/admin/hari-libur/{$libur->id}");
    }

    private function libur(string $tanggal, ?string $keterangan = 'Libur', ?School $sekolah = null): HariLibur
    {
        return ($sekolah ?? $this->sekolah)->hariLibur()->create(['tanggal' => $tanggal, 'keterangan' => $keterangan]);
    }

    /** Submit lewat Eloquent (cast tanggal ikut diuji), di kelas $kelas pada $tanggal. */
    private function submitDi(Kelas $kelas, string $tanggal, SubmissionStatus $status = SubmissionStatus::MenungguVerifikasi): void
    {
        $siswa = $this->siswa(Role::Siswa, $kelas);
        (new Submission)->forceFill([
            'user_id' => $siswa->id, 'class_id' => $kelas->id, 'tanggal' => $tanggal,
            'tugas' => Tugas::Menyapu, 'status' => $status,
        ])->save();
    }

    /** Jalankan $ubah sekali, tepat saat transaksi pertama di request dimulai (penyimpanan bersamaan). */
    private function saatTransaksiDimulai(callable $ubah): void
    {
        $sudah = false;
        Event::listen(TransactionBeginning::class, function () use (&$sudah, $ubah) {
            if (! $sudah) {
                $sudah = true;
                $ubah();
            }
        });
    }

    private function assertTidakAdaLibur(): void
    {
        $this->assertSame(0, HariLibur::count());
    }

    // ---- Halaman & menu ----

    public function test_daftar_kosong(): void
    {
        $html = $this->halaman()
            ->assertOk()
            ->assertSeeInOrder(['Tanggal', 'Keterangan', 'Tambah', 'Belum ada hari libur.'])
            ->getContent();

        $this->assertSame('Hari Libur', trim($this->xpath($html)->query('//h1')->item(0)?->textContent ?? ''));
    }

    /** Form tambah & hapus memuat token CSRF; form hapus dikirim sebagai DELETE. */
    public function test_form_memuat_token_csrf(): void
    {
        $libur = $this->libur('2026-11-11');
        $xpath = $this->xpath($this->halaman()->getContent());

        $tambah = '//form[@method="POST"][@action="'.route('admin.hari-libur.store').'"]';
        $hapus = '//form[@method="POST"][@action="'.route('admin.hari-libur.destroy', $libur).'"]';
        $this->assertSame(1, $xpath->query($tambah.'/input[@name="_token"][@value!=""]')->length);
        $this->assertSame(1, $xpath->query($hapus.'/input[@name="_token"][@value!=""]')->length);
        $this->assertSame(1, $xpath->query($hapus.'/input[@name="_method"][@value="DELETE"]')->length);
    }

    public function test_menu_hari_libur_aktif_di_dashboard(): void
    {
        $html = $this->actingAs($this->admin)->get('/admin')->assertOk()->getContent();

        $this->assertStringContainsString('href="'.route('admin.hari-libur.index').'"', $html);
        // Sejak Langkah 7 tidak ada lagi menu "segera".
        $this->assertStringNotContainsString('(segera)', $html);
    }

    // ---- Tambah ----

    public function test_tambah_hari_libur(): void
    {
        $sekolahLain = School::factory()->create();

        $this->tambah(['school_id' => $sekolahLain->id]) // school_id dari form diabaikan
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/hari-libur')
            ->assertSessionHas('status', 'Hari libur ditambahkan.');

        $libur = HariLibur::sole();
        $this->assertSame($this->sekolah->id, $libur->school_id);
        $this->assertSame('Libur nasional', $libur->keterangan);
        $this->assertSame('2026-11-11', $libur->tanggal->toDateString());

        $this->halaman()->assertSeeInOrder(['Rabu, 11 Nov 2026', 'Libur nasional']);
    }

    public function test_senin_sampai_jumat_diterima_sabtu_minggu_ditolak(): void
    {
        foreach (['2026-11-09', '2026-11-13'] as $tanggal) { // Senin, Jumat
            $this->tambah(['tanggal' => $tanggal])->assertSessionHasNoErrors();
        }

        foreach (['2026-11-07', '2026-11-08'] as $tanggal) { // Sabtu, Minggu
            $this->tambah(['tanggal' => $tanggal])->assertSessionHasErrors(['tanggal' => self::PESAN_AKHIR_PEKAN]);
        }

        $this->assertSame(['2026-11-09', '2026-11-13'], HariLibur::orderBy('tanggal')->pluck('tanggal')->map->toDateString()->all());
    }

    public function test_tanggal_kosong_atau_tidak_valid(): void
    {
        $this->tambah(['tanggal' => ''])->assertSessionHasErrors(['tanggal' => self::PESAN_WAJIB_TANGGAL]);
        $this->tambah(['tanggal' => '   '])->assertSessionHasErrors(['tanggal' => self::PESAN_WAJIB_TANGGAL]);

        $tidakValid = [
            'bukan-tanggal', '2026-02-30', '11/11/2026', '2026-1-5', '2026-11-11 10:00',
            '20261-01-05', '02026-01-05', // tahun bukan 4 digit
            '1999-12-31', '2101-01-03', '0001-01-01', // di luar tahun 2000–2100 (Jumat / Senin / Senin)
            ['2026-11-11'], // bukan teks
        ];
        foreach ($tidakValid as $tanggal) {
            $this->tambah(['tanggal' => $tanggal])->assertSessionHasErrors(['tanggal' => self::PESAN_TIDAK_VALID]);
        }

        $this->assertTidakAdaLibur();
    }

    public function test_batas_tahun_2000_dan_2100_diterima(): void
    {
        $this->tambah(['tanggal' => '2000-01-03'])->assertSessionHasNoErrors(); // Senin
        $this->tambah(['tanggal' => '2100-12-31'])->assertSessionHasNoErrors(); // Jumat

        $this->assertSame(2, HariLibur::count());
    }

    public function test_keterangan_wajib_dan_maks_255_karakter(): void
    {
        $this->tambah(['keterangan' => ''])->assertSessionHasErrors(['keterangan' => self::PESAN_WAJIB_KETERANGAN]);
        $this->tambah(['keterangan' => '    '])->assertSessionHasErrors(['keterangan' => self::PESAN_WAJIB_KETERANGAN]);
        $this->tambah(['keterangan' => str_repeat('a', 256)])
            ->assertSessionHasErrors(['keterangan' => 'Keterangan tidak boleh lebih dari 255 karakter.']);
        $this->assertTidakAdaLibur();

        // 255 karakter, termasuk huruf non-ASCII (dihitung per karakter, bukan per byte).
        $this->tambah(['keterangan' => str_repeat('é', 255)])->assertSessionHasNoErrors();
        $this->assertSame(str_repeat('é', 255), HariLibur::sole()->keterangan);
    }

    public function test_isian_tidak_hilang_saat_error(): void
    {
        $this->tambah(['tanggal' => '2026-11-07', 'keterangan' => 'Sabtu'])
            ->assertRedirect('/admin/hari-libur')
            ->assertSessionHasInput(['tanggal' => '2026-11-07', 'keterangan' => 'Sabtu']);

        $this->halaman()->assertSee('value="2026-11-07"', false)->assertSee('value="Sabtu"', false);
    }

    public function test_tanggal_sudah_terdaftar_ditolak_tapi_boleh_di_sekolah_lain(): void
    {
        $this->libur('2026-11-11');

        $this->tambah()->assertSessionHasErrors(['tanggal' => self::PESAN_SUDAH_TERDAFTAR]);
        $this->assertSame(1, HariLibur::count());

        // Admin sekolah lain boleh memakai tanggal yang sama.
        $sekolahLain = School::factory()->create();
        $adminLain = $this->buatUser(Role::Admin, ['school_id' => $sekolahLain->id, 'email' => 'admin@lain.test']);
        $this->actingAs($adminLain)->post('/admin/hari-libur', ['tanggal' => '2026-11-11', 'keterangan' => 'Libur'])
            ->assertSessionHasNoErrors();
        $this->assertSame(1, $sekolahLain->hariLibur()->count());
    }

    /** Dua admin menyimpan tanggal yang sama hampir bersamaan: lolos validasi, ditolak UNIQUE. */
    public function test_tanggal_kembar_dari_penyimpanan_bersamaan_jadi_pesan_validasi(): void
    {
        // Setelah validasi request ini lolos, admin lain menyimpan tanggal yang sama.
        $this->app->afterResolving(TambahHariLiburRequest::class, fn () => $this->libur('2026-11-11', 'Dari admin lain'));

        $this->tambah()->assertSessionHasErrors(['tanggal' => self::PESAN_SUDAH_TERDAFTAR]);

        $this->assertSame(['Dari admin lain'], HariLibur::pluck('keterangan')->all());
    }

    // ---- Tanggal lampau ----

    public function test_tanggal_lampau_bisa_ditambah_dan_dihapus(): void
    {
        $this->tambah(['tanggal' => '2026-10-28'])->assertSessionHasNoErrors(); // Rabu minggu lalu
        $libur = HariLibur::sole();

        $this->hapus($libur)->assertSessionHas('status', 'Hari libur dihapus.');
        $this->assertModelMissing($libur);
    }

    // ---- Tanggal yang sudah punya submit ----

    public function test_tanggal_yang_sudah_punya_submit_ditolak_apa_pun_statusnya(): void
    {
        $kelasLain = $this->kelasBaru('XI RPL 2'); // kelas lain, sekolah yang sama
        $tanggal = [
            '2026-11-09' => SubmissionStatus::MenungguVerifikasi,
            '2026-11-10' => SubmissionStatus::Disetujui,
            '2026-11-11' => SubmissionStatus::Ditolak,
            '2026-11-12' => SubmissionStatus::Kedaluwarsa,
        ];
        foreach ($tanggal as $hari => $status) {
            $this->submitDi($kelasLain, $hari, $status);
        }
        // Submit lewat query biasa (tanpa model), seperti data dari helper tes lain.
        $this->submit($this->siswa(), $this->kelas); // 2026-10-26

        foreach ([...array_keys($tanggal), '2026-10-26'] as $hari) {
            $this->tambah(['tanggal' => $hari, 'keterangan' => 'Rapat guru'])
                ->assertSessionHasErrors(['tanggal' => self::PESAN_ADA_SUBMIT])
                ->assertSessionHasInput(['tanggal' => $hari, 'keterangan' => 'Rapat guru']);
        }

        $this->assertTidakAdaLibur();
    }

    public function test_submit_sekolah_lain_atau_tanggal_lain_tidak_berpengaruh(): void
    {
        $kelasSekolahLain = $this->kelasBaru('XI RPL 1', School::factory()->create());
        $this->submitDi($kelasSekolahLain, '2026-11-11');
        $this->submitDi($this->kelas, '2026-11-10');
        $this->submitDi($this->kelas, '2026-11-12');

        $this->tambah(['tanggal' => '2026-11-11'])->assertSessionHasNoErrors();
        $this->assertSame(1, HariLibur::count());
    }

    /** Cek submit ada di dalam transaksi (setelah kunci), bukan sebelum transaksi. */
    public function test_submit_yang_masuk_bersamaan_ditolak(): void
    {
        $this->saatTransaksiDimulai(fn () => $this->submitDi($this->kelas, '2026-11-11'));

        $this->tambah()->assertSessionHasErrors(['tanggal' => self::PESAN_ADA_SUBMIT]);

        // (Di tes, submit "bersamaan" terjadi di dalam transaksi ini, jadi ikut dibatalkan.
        // Yang dicek: pesannya, dan tidak ada hari libur yang tersimpan.)
        $this->assertTidakAdaLibur();
    }

    /**
     * Baris sekolah dikunci sebelum cek submit (SPEC §11 Langkah 9: submit mengunci baris
     * yang sama dengan sharedLock). SQLite tidak punya FOR UPDATE, jadi kata kuncinya hanya
     * dicek di MySQL; urutan query dicek di keduanya.
     */
    public function test_baris_sekolah_dikunci_sebelum_cek_submit(): void
    {
        $query = [];
        $this->saatTransaksiDimulai(function () use (&$query) {
            DB::listen(function (QueryExecuted $q) use (&$query) {
                $query[] = strtolower($q->sql);
            });
        });

        $this->tambah()->assertSessionHasNoErrors();

        $kunci = collect($query)->search(fn ($sql) => preg_match('/from [`"]schools[`"]/', $sql) === 1);
        $cekSubmit = collect($query)->search(fn ($sql) => preg_match('/from [`"]submissions[`"]/', $sql) === 1);
        $this->assertIsInt($kunci, 'Baris sekolah harus dibaca (dikunci) di dalam transaksi.');
        $this->assertIsInt($cekSubmit);
        $this->assertLessThan($cekSubmit, $kunci, 'Kunci sekolah harus sebelum cek submit.');
        $this->assertMatchesRegularExpression('/from [`"]schools[`"] where [`"]schools[`"]\.[`"]id[`"] = \?/', $query[$kunci]);

        if (DB::getDriverName() === 'mysql') {
            $this->assertStringEndsWith('for update', $query[$kunci]);
        }
    }

    // ---- Daftar ----

    public function test_urutan_daftar_mendatang_dulu_lalu_lampau(): void
    {
        foreach (['2026-10-12', '2027-01-05', '2026-11-04', '2026-10-28', '2026-12-25', '2026-11-11'] as $tanggal) {
            $this->libur($tanggal, "Ket {$tanggal}");
        }

        $this->halaman()->assertSeeInOrder([
            'Rabu, 4 Nov 2026', 'Ket 2026-11-04', // hari ini termasuk "akan datang"
            'Rabu, 11 Nov 2026',
            'Jumat, 25 Des 2026',
            'Selasa, 5 Jan 2027',
            'Rabu, 28 Okt 2026', // lampau: terbaru dulu
            'Senin, 12 Okt 2026',
        ]);
    }

    public function test_daftar_hanya_sekolah_sendiri(): void
    {
        $this->libur('2026-11-11', 'Libur sekolah ini');
        $this->libur('2026-11-12', 'Libur sekolah lain', School::factory()->create());

        $this->halaman()->assertSee('Libur sekolah ini')->assertDontSee('Libur sekolah lain');
    }

    public function test_keterangan_kosong_dari_data_lama(): void
    {
        // Kosong (null) atau hanya spasi: sel "—" dan konfirmasi tanpa tanda kurung.
        foreach ([null, '   '] as $keterangan) {
            HariLibur::query()->delete();
            DB::table('hari_libur')->insert(['school_id' => $this->sekolah->id, 'tanggal' => '2026-11-11', 'keterangan' => $keterangan]);

            $html = $this->halaman()->getContent();
            $baris = $this->xpath($html)->query('//tbody/tr/td');
            $this->assertSame('Rabu, 11 Nov 2026', trim($baris->item(0)->textContent));
            $this->assertSame('—', trim($baris->item(1)->textContent));
            $this->assertSame('Hapus hari libur Rabu, 11 Nov 2026?', $this->konfirmasiHapus($html));
        }
    }

    /** Keterangan dari admin tampil sebagai teks, di sel tabel maupun dialog konfirmasi hapus. */
    public function test_keterangan_di_escape_di_daftar_dan_konfirmasi_hapus(): void
    {
        $keterangan = 'Libur "Idul" Fitri\'s <b>x</b> & <script>alert(1)</script>';
        $this->tambah(['keterangan' => $keterangan])->assertSessionHasNoErrors();
        $this->assertSame($keterangan, HariLibur::sole()->keterangan, 'Disimpan apa adanya.');

        $html = $this->halaman()->getContent();

        // Setelah dibaca browser, isi atribut persis teks SPEC (dipakai window.confirm sebagai teks).
        $this->assertSame("Hapus hari libur Rabu, 11 Nov 2026 ({$keterangan})?", $this->konfirmasiHapus($html));
        $this->assertSame($keterangan, trim($this->xpath($html)->query('//tbody/tr/td')->item(1)->textContent));

        // Tidak ada tag atau kutip yang lolos tanpa di-escape.
        $this->assertStringNotContainsString('<script>alert(1)', $html);
        $this->assertStringNotContainsString('<b>x</b>', $html);
        $this->assertStringNotContainsString('"Idul"', $html);
        $this->assertStringContainsString('(Libur &quot;Idul&quot; Fitri&#039;s &lt;b&gt;x&lt;/b&gt; &amp; &lt;script&gt;alert(1)&lt;/script&gt;)?"', $html);
        $this->assertCount(0, $this->xpath($html)->query('//script[contains(., "alert(1)")] | //tbody//b'));
    }

    // ---- Hapus ----

    public function test_hapus_hari_libur(): void
    {
        $libur = $this->libur('2026-11-11');
        $lain = $this->libur('2026-11-12');

        $this->hapus($libur)
            ->assertRedirect('/admin/hari-libur')
            ->assertSessionHas('status', 'Hari libur dihapus.');

        $this->assertModelMissing($libur);
        $this->assertModelExists($lain);
    }

    public function test_hapus_hari_libur_sekolah_lain_404(): void
    {
        $liburLain = $this->libur('2026-11-11', 'Libur', School::factory()->create());
        // Filter sekolah di Route::bind: data sekolah lain bahkan tidak terbaca (bukan hanya
        // tidak terhapus oleh filter di destroy()).
        $terbaca = false;
        HariLibur::retrieved(function () use (&$terbaca) {
            $terbaca = true;
        });

        $this->hapus($liburLain)->assertNotFound();
        $this->assertFalse($terbaca, 'Hari libur sekolah lain tidak boleh ditemukan oleh route binding.');
        $this->assertModelExists($liburLain);
    }

    public function test_hapus_yang_baru_saja_dihapus_admin_lain_404(): void
    {
        $libur = $this->libur('2026-11-11');

        // Admin lain menghapus baris ini tepat setelah request ini menemukannya di URL.
        $sudah = false;
        HariLibur::retrieved(function () use (&$sudah, $libur) {
            if (! $sudah) {
                $sudah = true;
                DB::table('hari_libur')->where('id', $libur->id)->delete();
            }
        });

        $this->hapus($libur)->assertNotFound();
        $this->assertTrue($sudah);
    }

    // ---- Route & CSRF ----

    public function test_tidak_ada_edit(): void
    {
        $libur = $this->libur('2026-11-11');

        $this->actingAs($this->admin)->put("/admin/hari-libur/{$libur->id}", ['keterangan' => 'Diubah'])->assertStatus(405);
        $this->actingAs($this->admin)->get("/admin/hari-libur/{$libur->id}/edit")->assertNotFound();
        $this->assertSame('Libur', $libur->fresh()->keterangan);
    }

    public function test_tambah_dan_hapus_wajib_csrf(): void
    {
        $libur = $this->libur('2026-11-12');

        // CSRF aktif (Laravel mematikannya saat tes): tanpa token → 419, tidak ada yang berubah.
        $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
        {
            protected function runningUnitTests()
            {
                return false;
            }
        });

        $this->tambah()->assertStatus(419);
        $this->hapus($libur)->assertStatus(419);

        $this->assertSame([$libur->id], HariLibur::pluck('id')->all());
    }

    // ---- Pencarian per tanggal (whereDate) ----

    /**
     * Cast 'date' menyimpan "2026-11-11 00:00:00" di SQLite (di MySQL kolom DATE hanya tanggal).
     * Cek "sudah terdaftar" harus menemukan kedua bentuk itu di validasi (sebelum transaksi),
     * bukan baru di UNIQUE database.
     */
    public function test_tanggal_terdaftar_ditemukan_apa_pun_bentuk_simpannya(): void
    {
        DB::table('hari_libur')->insert([
            ['school_id' => $this->sekolah->id, 'tanggal' => '2026-11-11 00:00:00', 'keterangan' => 'Berjam'],
            ['school_id' => $this->sekolah->id, 'tanggal' => '2026-11-12', 'keterangan' => 'Tanpa jam'],
        ]);
        $transaksi = 0;
        Event::listen(TransactionBeginning::class, function () use (&$transaksi) {
            $transaksi++;
        });

        foreach (['2026-11-11', '2026-11-12'] as $tanggal) {
            $this->tambah(['tanggal' => $tanggal])->assertSessionHasErrors(['tanggal' => self::PESAN_SUDAH_TERDAFTAR]);
        }

        $this->assertSame(0, $transaksi, 'Harus sudah ditolak di validasi.');
        $this->assertSame(2, HariLibur::count());
    }

    // ---- Bantuan baca HTML ----

    private function xpath(string $html): DOMXPath
    {
        $dokumen = new DOMDocument;
        libxml_use_internal_errors(true);
        $dokumen->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_clear_errors();

        return new DOMXPath($dokumen);
    }

    /** Isi data-konfirmasi form hapus (satu-satunya), seperti yang dibaca browser. */
    private function konfirmasiHapus(string $html): string
    {
        $form = $this->xpath($html)->query('//form[@data-konfirmasi]');
        $this->assertSame(1, $form->length);
        $elemen = $form->item(0);
        $this->assertInstanceOf(DOMElement::class, $elemen);

        return $elemen->getAttribute('data-konfirmasi');
    }
}
