<?php

namespace Tests\Feature\Jadwal;

use App\Enums\Tugas;
use App\Models\DutySchedule;
use App\Models\School;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;

/** SPEC F2, §8.1, §11 Langkah 8 (v0.15): kelola jadwal piket satu kelas. */
class SimpanJadwalTest extends JadwalTestCase
{
    // --- Rotasi di layar (contoh SPEC §11 Langkah 8) ---

    public function test_tugas_di_layar_mengikuti_rotasi_contoh_spec(): void
    {
        $siswa = $this->siswa();
        $this->jadwalTersimpan($siswa, 1, Tugas::Menyapu);

        $harapan = [
            '2026-11-02' => Tugas::Menyapu, '2026-11-03' => Tugas::Menyapu, '2026-11-04' => Tugas::Menyapu,
            '2026-11-05' => Tugas::Menyapu, '2026-11-06' => Tugas::Menyapu,
            '2026-11-09' => Tugas::Merapikan, '2026-11-16' => Tugas::Mengepel, '2026-11-23' => Tugas::Menyapu,
        ];

        foreach ($harapan as $tanggal => $tugas) {
            $this->travelTo("{$tanggal} 08:00:00");
            $halaman = $this->halaman($this->pj)->assertOk();

            $this->assertSame($tugas->value, $this->terpilih($halaman, "jadwal[{$siswa->id}][tugas]"), $tanggal);
            $this->assertSame('1', $this->terpilih($halaman, "jadwal[{$siswa->id}][hari]"), $tanggal);
            $this->assertSame('1/3', $this->isiRingkasan($halaman, 'data-ringkasan-tugas', "1-{$tugas->value}"), $tanggal);
        }
    }

    public function test_label_dan_minggu_acuan_mengikuti_minggu_saat_form_ditampilkan(): void
    {
        $halaman = $this->halaman($this->pj)
            ->assertSee('Tugas minggu ini (2–8 Nov)')
            ->assertSee('Minggu ini (2–8 Nov)')
            ->assertDontSee('Tugas mulai');
        $this->assertSame('2026-11-02', $this->isianForm($halaman)['minggu_acuan']);

        // Minggu masih termasuk minggu Senin 2 Nov (minggu = Senin–Minggu).
        $this->travelTo('2026-11-08 23:00:00');
        $this->assertSame('2026-11-02', $this->isianForm($this->halaman($this->pj))['minggu_acuan']);

        // Lintas bulan.
        $this->travelTo('2026-12-01 08:00:00');
        $halaman = $this->halaman($this->pj)->assertSee('Tugas minggu ini (30 Nov–6 Des)');
        $this->assertSame('2026-11-30', $this->isianForm($halaman)['minggu_acuan']);
    }

    public function test_sebelum_tanggal_mulai_ringkasan_dan_label_memakai_tugas_awal(): void
    {
        $this->travelTo('2026-10-28 08:00:00');
        $siswa = $this->siswa();
        $this->jadwalTersimpan($siswa, 2, Tugas::Merapikan);

        $halaman = $this->halaman($this->pj)
            ->assertSee('Tugas mulai Senin, 2 Nov 2026')
            ->assertSee('Mulai Senin, 2 Nov 2026')
            ->assertDontSee('Tugas minggu ini');

        $this->assertSame('merapikan', $this->terpilih($halaman, "jadwal[{$siswa->id}][tugas]"));
        $this->assertSame('1/3', $this->isiRingkasan($halaman, 'data-ringkasan-tugas', '2-merapikan'));

        // Disimpan sebelum tanggal mulai: pilihan = tugas_awal, apa adanya.
        $this->simpan($this->pj, [$siswa->id => ['hari' => '2', 'tugas' => 'mengepel']])->assertSessionHasNoErrors();
        $this->assertSame(['hari' => 2, 'tugas_awal' => Tugas::Mengepel], $this->jadwalDi($siswa));
    }

    // --- Hitung mundur tugas_awal dari minggu acuan ---

    public function test_simpan_menghitung_mundur_tugas_awal(): void
    {
        $this->travelTo('2026-11-16 08:00:00'); // minggu ke-2
        $siswa = $this->siswa();

        $this->simpan($this->pj, [$siswa->id => ['hari' => '3', 'tugas' => 'mengepel']])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('jadwal'));

        // menyapu + 2 minggu = mengepel.
        $this->assertSame(['hari' => 3, 'tugas_awal' => Tugas::Menyapu], $this->jadwalDi($siswa));
        $this->followRedirects($this->simpan($this->pj, [$siswa->id => ['hari' => '3', 'tugas' => 'mengepel']]))
            ->assertSee('Jadwal tersimpan.');
    }

    /** Kriteria wajib v0.15: form dibuka Minggu, disimpan Senin → tidak ada tugas yang bergeser. */
    public function test_form_tanpa_perubahan_disimpan_di_minggu_lain_tidak_mengubah_tugas_awal(): void
    {
        $siswa = [$this->siswa(), $this->siswa(), $this->siswa()];
        $this->jadwalTersimpan($siswa[0], 1, Tugas::Menyapu);
        $this->jadwalTersimpan($siswa[1], 2, Tugas::Merapikan);
        $this->jadwalTersimpan($siswa[2], 3, Tugas::Mengepel);
        $sebelum = DutySchedule::orderBy('id')->get(['user_id', 'hari', 'tugas_awal'])->toArray();

        $this->travelTo('2026-11-15 21:00:00'); // Minggu, minggu ke-1
        $isian = $this->isianForm($this->halaman($this->pj));
        $this->assertSame('2026-11-09', $isian['minggu_acuan']);

        $this->travelTo('2026-11-16 07:00:00'); // Senin, minggu ke-2
        $this->simpan($this->pj, $isian['jadwal'], $isian['minggu_acuan'])->assertSessionHasNoErrors();

        $this->assertSame($sebelum, DutySchedule::orderBy('id')->get(['user_id', 'hari', 'tugas_awal'])->toArray());
    }

    public function test_form_dibuka_sebelum_tanggal_mulai_lalu_disimpan_sesudahnya_disimpan_apa_adanya(): void
    {
        $siswa = $this->siswa();

        $this->travelTo('2026-11-01 20:00:00'); // Minggu, sebelum tanggal mulai
        $isian = $this->isianForm($this->halaman($this->pj)->assertSee('Tugas mulai Senin, 2 Nov 2026'));
        $this->assertSame('2026-10-26', $isian['minggu_acuan']);

        $isian['jadwal'][$siswa->id] = ['hari' => '1', 'tugas' => 'mengepel'];

        $this->travelTo('2026-11-10 08:00:00'); // minggu ke-1
        $this->simpan($this->pj, $isian['jadwal'], $isian['minggu_acuan'])->assertSessionHasNoErrors();

        $this->assertSame(['hari' => 1, 'tugas_awal' => Tugas::Mengepel], $this->jadwalDi($siswa));
    }

    // --- Batas 9 per hari, 3 per tugas ---

    public function test_siswa_ke_10_di_satu_hari_ditolak(): void
    {
        $siswa = [$this->pj];
        for ($i = 0; $i < 9; $i++) {
            $siswa[] = $this->siswa();
        }
        $tugas = ['menyapu', 'merapikan', 'mengepel'];

        $isian = [];
        foreach ($siswa as $i => $s) {
            $isian[$s->id] = ['hari' => '1', 'tugas' => $tugas[$i % 3]];
        }

        $this->simpan($this->pj, $isian)->assertSessionHasErrors(['jadwal' => 'Hari Senin sudah penuh (maks 9 siswa)']);
        $this->assertSame(0, DutySchedule::count());

        // 9 siswa (3/3/3) boleh.
        array_pop($isian);
        $this->simpan($this->pj, $isian)->assertSessionHasNoErrors();
        $this->assertSame(9, DutySchedule::where('hari', 1)->count());
    }

    public function test_siswa_di_luar_form_ikut_dihitung_dalam_batas_hari(): void
    {
        for ($i = 0; $i < 9; $i++) {
            $this->jadwalTersimpan($this->siswa(), 4, Tugas::cases()[$i % 3]);
        }

        // Hanya PJ yang dikirim; 9 jadwal tersimpan di luar form tetap dihitung.
        $this->simpan($this->pj, [$this->pj->id => ['hari' => '4', 'tugas' => 'menyapu']])
            ->assertSessionHasErrors(['jadwal' => 'Hari Kamis sudah penuh (maks 9 siswa)']);
        $this->assertNull($this->jadwalDi($this->pj));
    }

    public function test_siswa_ke_4_dengan_tugas_sama_di_satu_hari_ditolak(): void
    {
        $siswa = [$this->pj, $this->siswa(), $this->siswa(), $this->siswa()];
        $isian = [];
        foreach ($siswa as $s) {
            $isian[$s->id] = ['hari' => '1', 'tugas' => 'menyapu'];
        }

        $this->simpan($this->pj, $isian)->assertSessionHasErrors(['jadwal' => 'Tugas Menyapu di hari Senin sudah 3 siswa']);
        $this->assertSame(0, DutySchedule::count());

        array_pop($isian);
        $this->simpan($this->pj, $isian)->assertSessionHasNoErrors();
        $this->assertSame(3, DutySchedule::count());
    }

    /** Pesan menyebut tugas di minggu acuan, dan jadwal di luar form dihitung dalam istilah yang sama. */
    public function test_batas_tugas_dihitung_dalam_tugas_minggu_acuan(): void
    {
        $this->travelTo('2026-11-10 08:00:00'); // minggu ke-1: tugas_awal menyapu → merapikan
        for ($i = 0; $i < 3; $i++) {
            $this->jadwalTersimpan($this->siswa(), 2, Tugas::Menyapu);
        }

        $this->simpan($this->pj, [$this->pj->id => ['hari' => '2', 'tugas' => 'merapikan']])
            ->assertSessionHasErrors(['jadwal' => 'Tugas Merapikan meja & kursi di hari Selasa sudah 3 siswa']);

        $this->simpan($this->pj, [$this->pj->id => ['hari' => '2', 'tugas' => 'menyapu']])->assertSessionHasNoErrors();
        // menyapu minggu ke-1 → tugas_awal mengepel.
        $this->assertSame(['hari' => 2, 'tugas_awal' => Tugas::Mengepel], $this->jadwalDi($this->pj));
    }

    // --- Hari & tugas ---

    public function test_hari_sabtu_tidak_ada_dan_ditolak_server(): void
    {
        $halaman = $this->halaman($this->pj);
        $this->assertSame(['', '1', '2', '3', '4', '5'], $this->nilaiOpsi($halaman, "jadwal[{$this->pj->id}][hari]"));
        $halaman->assertDontSee('Sabtu');

        foreach (['6', '0', '7', 'abc', '1.0', ['1']] as $hari) {
            $this->simpan($this->pj, [$this->pj->id => ['hari' => $hari, 'tugas' => 'menyapu']])
                ->assertSessionHasErrors(["jadwal.{$this->pj->id}.hari" => 'Hari piket hanya Senin–Jumat.']);
        }
        $this->assertSame(0, DutySchedule::count());
    }

    public function test_tugas_wajib_dan_harus_valid_kalau_hari_diisi(): void
    {
        $this->simpan($this->pj, [$this->pj->id => ['hari' => '1', 'tugas' => '']])
            ->assertSessionHasErrors(["jadwal.{$this->pj->id}.tugas" => 'Tugas wajib dipilih.']);
        $this->simpan($this->pj, [$this->pj->id => ['hari' => '1']])
            ->assertSessionHasErrors(["jadwal.{$this->pj->id}.tugas" => 'Tugas wajib dipilih.']);
        $this->simpan($this->pj, [$this->pj->id => ['hari' => '1', 'tugas' => 'mencuci']])
            ->assertSessionHasErrors(["jadwal.{$this->pj->id}.tugas" => 'Tugas tidak valid.']);
        $this->assertSame(0, DutySchedule::count());

        // Hari kosong: tugas diabaikan, apa pun isinya.
        $this->simpan($this->pj, [$this->pj->id => ['hari' => '', 'tugas' => 'mencuci']])->assertSessionHasNoErrors();
        $this->assertSame(0, DutySchedule::count());
    }

    public function test_boleh_simpan_dengan_siswa_belum_terjadwal_dan_daftarnya_tampil(): void
    {
        $budi = $this->siswa();
        $budi->update(['nama' => 'Budi Terjadwal']);
        $sari = $this->siswa();
        $sari->update(['nama' => 'Sari Belum']);
        $this->pj->update(['nama' => 'Pj Dikeluarkan']);
        $this->jadwalTersimpan($this->pj, 5, Tugas::Mengepel);

        $this->simpan($this->pj, [
            $budi->id => ['hari' => '1', 'tugas' => 'menyapu'],
            $sari->id => ['hari' => '', 'tugas' => ''],
            $this->pj->id => ['hari' => '', 'tugas' => 'mengepel'], // dikeluarkan dari jadwal
        ])->assertSessionHasNoErrors();

        $this->assertNotNull($this->jadwalDi($budi));
        $this->assertNull($this->jadwalDi($sari));
        $this->assertNull($this->jadwalDi($this->pj));

        $belum = $this->xpath($this->halaman($this->pj))->query('//*[@data-belum-terjadwal]')->item(0)->textContent;
        $this->assertStringContainsString('Sari Belum', $belum);
        $this->assertStringContainsString('Pj Dikeluarkan', $belum);
        $this->assertStringNotContainsString('Budi Terjadwal', $belum);
    }

    // --- minggu_acuan ---

    /** @return array<string, array{mixed}> */
    public static function mingguAcuanTidakValid(): array
    {
        return [
            'bukan Senin' => ['2026-11-03'],
            // Dalam rentang 4 minggu, jadwal hanya ditolak oleh cek "harus Senin".
            'bukan Senin, dalam rentang' => ['2026-10-28'],
            'Minggu, dalam rentang' => ['2026-11-01'],
            'minggu depan (masa depan)' => ['2026-11-09'],
            'lebih dari 4 minggu lalu' => ['2026-09-28'],
            'bukan tanggal' => ['abc'],
            'tanggal tidak ada' => ['2026-02-30'],
            'format lain' => ['02-11-2026'],
            'kosong' => [''],
            'tidak dikirim' => [null],
            'array' => [['2026-11-02']],
        ];
    }

    #[DataProvider('mingguAcuanTidakValid')]
    public function test_minggu_acuan_tidak_valid_menolak_seluruh_simpan(mixed $mingguAcuan): void
    {
        $this->actingAs($this->pj)->from($this->url())->post(route('jadwal.store'), array_filter([
            'kelas' => $this->kelas->id,
            'minggu_acuan' => $mingguAcuan,
            'jadwal' => [$this->pj->id => ['hari' => '1', 'tugas' => 'menyapu']],
        ], fn ($nilai) => $nilai !== null))->assertSessionHasErrors(['minggu_acuan' => 'Halaman jadwal sudah terlalu lama dibuka. Muat ulang halaman.']);

        $this->assertSame(0, DutySchedule::count());
    }

    public function test_minggu_acuan_tepat_4_minggu_lalu_masih_diterima(): void
    {
        $this->simpan($this->pj, [$this->pj->id => ['hari' => '1', 'tugas' => 'menyapu']], '2026-10-05')
            ->assertSessionHasNoErrors();
        $this->assertNotNull($this->jadwalDi($this->pj));
    }

    // --- Siswa di form / di luar form ---

    public function test_siswa_yang_tidak_ada_di_form_tidak_berubah(): void
    {
        $lain = $this->siswa();
        $this->jadwalTersimpan($lain, 2, Tugas::Mengepel);

        $this->simpan($this->pj, [$this->pj->id => ['hari' => '1', 'tugas' => 'menyapu']])->assertSessionHasNoErrors();

        $this->assertSame(['hari' => 2, 'tugas_awal' => Tugas::Mengepel], $this->jadwalDi($lain));
        $this->assertNotNull($this->jadwalDi($this->pj));
    }

    public function test_siswa_yang_bukan_anggota_kelas_menolak_seluruh_simpan(): void
    {
        $kelasLain = $this->kelasBaru('XI RPL 2');
        $sekolahLain = School::factory()->create();
        $kelasSekolahLain = $this->kelasBaru('X TKJ 1', $sekolahLain);

        $dipindah = $this->siswa();
        $dipindah->update(['class_id' => $kelasLain->id]); // dipindah admin setelah form dibuka
        $dihapus = $this->siswa();
        $idDihapus = $dihapus->id;
        $dihapus->delete();
        // Data rusak: guru seharusnya selalu tanpa class_id (§2), tapi tetap tidak boleh dijadwalkan (§6).
        $guruBerkelas = $this->guru();
        DB::table('users')->where('id', $guruBerkelas->id)->update(['class_id' => $this->kelas->id]);

        $bukanAnggota = [
            'guru dengan class_id kelas ini' => $guruBerkelas->id,
            'siswa kelas lain' => $this->siswa(kelas: $kelasLain)->id,
            'baru dipindah' => $dipindah->id,
            'sudah dihapus' => $idDihapus,
            'id tidak ada' => 999999,
            'guru' => $this->guru()->id,
            'admin' => $this->admin->id,
            'siswa sekolah lain' => $this->siswa(kelas: $kelasSekolahLain)->id,
        ];

        foreach ($bukanAnggota as $kasus => $id) {
            $this->simpan($this->pj, [
                $this->pj->id => ['hari' => '1', 'tugas' => 'menyapu'],
                $id => ['hari' => '2', 'tugas' => 'menyapu'],
            ])->assertSessionHasErrors(['jadwal' => 'Daftar siswa kelas ini baru saja berubah. Muat ulang halaman.']);

            $this->assertSame(0, DutySchedule::count(), $kasus);
        }

        // Kunci yang bukan id akun.
        $this->simpan($this->pj, ['abc' => ['hari' => '1', 'tugas' => 'menyapu']])
            ->assertSessionHasErrors(['jadwal' => 'Daftar siswa kelas ini baru saja berubah. Muat ulang halaman.']);
        $this->assertSame(0, DutySchedule::count());
    }

    // --- Tampilan ---

    public function test_teks_layar_sesuai_spec(): void
    {
        $this->halaman($this->pj)
            ->assertSee('Jadwal piket '.$this->kelas->nama)
            ->assertSee('Tanggal mulai rotasi: Senin, 2 Nov 2026')
            ->assertSee('Perubahan langsung berlaku, termasuk minggu ini.')
            ->assertSee('Pilih hari dan tugas untuk setiap siswa, lalu tekan Simpan.')
            ->assertSee('— Belum terjadwal —')
            ->assertSee('Merapikan meja &amp; kursi', false)
            ->assertSee('Simpan')
            ->assertDontSee('Pilih kelas lain'); // PJ hanya punya satu kelas

        // Petunjuk hanya saat kelas belum punya jadwal.
        $this->jadwalTersimpan($this->pj, 1, Tugas::Menyapu);
        $this->halaman($this->pj)->assertDontSee('Pilih hari dan tugas untuk setiap siswa');
    }

    public function test_kelas_tanpa_siswa(): void
    {
        $kosong = $this->kelasBaru('XII RPL 9');

        $this->halaman($this->admin, $kosong)
            ->assertOk()
            ->assertSee('Kelas ini belum punya siswa.')
            ->assertSee('Pilih kelas lain');
    }

    public function test_error_menampilkan_ringkasan_dari_isian_dan_isian_tidak_hilang(): void
    {
        $siswa = [$this->pj];
        for ($i = 0; $i < 9; $i++) {
            $siswa[] = $this->siswa();
        }
        $tugas = ['menyapu', 'merapikan', 'mengepel'];
        $isian = [];
        foreach ($siswa as $i => $s) {
            $isian[$s->id] = ['hari' => '1', 'tugas' => $tugas[$i % 3]];
        }

        $this->simpan($this->pj, $isian)->assertRedirect($this->url());
        $halaman = $this->get($this->url())->assertSee('Hari Senin sudah penuh (maks 9 siswa)');

        $this->assertSame('10/9', $this->isiRingkasan($halaman, 'data-ringkasan-hari', '1'));
        $this->assertTrue($this->ringkasanDisorot($halaman, 'data-ringkasan-hari', '1'));
        $this->assertSame('4/3', $this->isiRingkasan($halaman, 'data-ringkasan-tugas', '1-menyapu'));
        $this->assertTrue($this->ringkasanDisorot($halaman, 'data-ringkasan-tugas', '1-menyapu'));
        $this->assertFalse($this->ringkasanDisorot($halaman, 'data-ringkasan-tugas', '1-mengepel'));

        foreach ($siswa as $i => $s) {
            $this->assertSame('1', $this->terpilih($halaman, "jadwal[{$s->id}][hari]"));
            $this->assertSame($tugas[$i % 3], $this->terpilih($halaman, "jadwal[{$s->id}][tugas]"));
        }

        // Tanpa error: ringkasan dari data tersimpan, tidak disorot.
        $this->assertSame('0/9', $this->isiRingkasan($this->halaman($this->pj), 'data-ringkasan-hari', '1'));
    }

    public function test_pesan_per_siswa_menyebut_namanya(): void
    {
        $this->pj->update(['nama' => 'Rina Pj']);

        $this->simpan($this->pj, [$this->pj->id => ['hari' => '1', 'tugas' => '']]);
        $this->get($this->url())->assertSee('Rina Pj: Tugas wajib dipilih.');
    }

    /** Form dibuka Minggu, disimpan Senin, kena batas: isian tidak hilang, diterjemahkan ke minggu ini. */
    public function test_isian_tidak_hilang_saat_minggu_sudah_berganti(): void
    {
        $siswa = [$this->pj, $this->siswa(), $this->siswa(), $this->siswa()];

        $this->travelTo('2026-11-15 21:00:00'); // Minggu, minggu ke-1
        $isian = $this->isianForm($this->halaman($this->pj));
        foreach ($siswa as $s) {
            $isian['jadwal'][$s->id] = ['hari' => '1', 'tugas' => 'menyapu'];
        }

        $this->travelTo('2026-11-16 07:00:00'); // Senin, minggu ke-2
        $this->simpan($this->pj, $isian['jadwal'], $isian['minggu_acuan'])
            ->assertSessionHasErrors(['jadwal' => 'Tugas Menyapu di hari Senin sudah 3 siswa']);
        $this->assertSame(0, DutySchedule::count());

        $halaman = $this->get($this->url())->assertSee('Tugas minggu ini (16–22 Nov)');
        $this->assertSame('2026-11-16', $this->isianForm($halaman)['minggu_acuan']);

        // Menyapu di minggu 9 Nov = merapikan di minggu 16 Nov (siswa yang sama, rotasi sama).
        foreach ($siswa as $s) {
            $this->assertSame('1', $this->terpilih($halaman, "jadwal[{$s->id}][hari]"));
            $this->assertSame('merapikan', $this->terpilih($halaman, "jadwal[{$s->id}][tugas]"));
        }
        $this->assertSame('4/3', $this->isiRingkasan($halaman, 'data-ringkasan-tugas', '1-merapikan'));
        $this->assertTrue($this->ringkasanDisorot($halaman, 'data-ringkasan-tugas', '1-merapikan'));
    }

    /** Form dibuka sebelum tanggal mulai (pilihan = tugas_awal), error saat disimpan minggu ke-1. */
    public function test_isian_lama_dari_sebelum_tanggal_mulai_diterjemahkan_ke_minggu_ini(): void
    {
        $siswa = [$this->pj, $this->siswa(), $this->siswa(), $this->siswa()];

        $this->travelTo('2026-11-01 20:00:00'); // Minggu, sebelum tanggal mulai 2 Nov
        $isian = $this->isianForm($this->halaman($this->pj));
        $this->assertSame('2026-10-26', $isian['minggu_acuan']);
        foreach ($siswa as $s) {
            $isian['jadwal'][$s->id] = ['hari' => '1', 'tugas' => 'menyapu'];
        }

        $this->travelTo('2026-11-10 08:00:00'); // minggu ke-1
        $this->simpan($this->pj, $isian['jadwal'], $isian['minggu_acuan'])
            ->assertSessionHasErrors(['jadwal' => 'Tugas Menyapu di hari Senin sudah 3 siswa']);

        // tugas_awal menyapu → minggu ke-1 = merapikan.
        $halaman = $this->get($this->url());
        foreach ($siswa as $s) {
            $this->assertSame('merapikan', $this->terpilih($halaman, "jadwal[{$s->id}][tugas]"));
        }
    }

    public function test_ringkasan_saat_error_menghitung_siswa_yang_tugasnya_belum_dipilih(): void
    {
        $isian = [$this->pj->id => ['hari' => '1', 'tugas' => '']];
        for ($i = 0; $i < 9; $i++) {
            $isian[$this->siswa()->id] = ['hari' => '1', 'tugas' => Tugas::cases()[$i % 3]->value];
        }

        $this->simpan($this->pj, $isian)->assertSessionHasErrors(["jadwal.{$this->pj->id}.tugas" => 'Tugas wajib dipilih.']);
        $halaman = $this->get($this->url());

        $this->assertSame('10/9', $this->isiRingkasan($halaman, 'data-ringkasan-hari', '1'));
        $this->assertTrue($this->ringkasanDisorot($halaman, 'data-ringkasan-hari', '1'));
        $this->assertSame('3/3', $this->isiRingkasan($halaman, 'data-ringkasan-tugas', '1-menyapu'));
    }

    public function test_jadwal_bukan_daftar_mendapat_pesan_bahasa_indonesia(): void
    {
        $this->simpan($this->pj, [])->assertSessionHasNoErrors(); // form tanpa siswa: tidak ada yang diubah

        $this->actingAs($this->pj)->from($this->url())->post(route('jadwal.store'), [
            'kelas' => $this->kelas->id, 'minggu_acuan' => '2026-11-02', 'jadwal' => 'abc',
        ])->assertSessionHasErrors(['jadwal' => 'Daftar siswa kelas ini baru saja berubah. Muat ulang halaman.']);
    }

    public function test_isian_lama_dengan_minggu_acuan_tidak_valid_tidak_ditampilkan(): void
    {
        $this->jadwalTersimpan($this->pj, 2, Tugas::Mengepel);

        // Form basi (minggu acuan terlalu lama) → ditolak; form tampil dengan data terbaru.
        $this->simpan($this->pj, [$this->pj->id => ['hari' => '4', 'tugas' => 'menyapu']], '2026-09-28');
        $halaman = $this->get($this->url())->assertSee('Halaman jadwal sudah terlalu lama dibuka. Muat ulang halaman.');

        $this->assertSame('2', $this->terpilih($halaman, "jadwal[{$this->pj->id}][hari]"));
        $this->assertSame('mengepel', $this->terpilih($halaman, "jadwal[{$this->pj->id}][tugas]"));
    }
}
