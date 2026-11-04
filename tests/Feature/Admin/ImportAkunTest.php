<?php

namespace Tests\Feature\Admin;

use App\Actions\ImportAkun;
use App\Enums\Role;
use App\Http\Controllers\Admin\AkunController;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;

/** SPEC F9 (v0.10): import akun siswa/PJ dari CSV + unduh daftar password sekali. */
class ImportAkunTest extends AdminTestCase
{
    private function import(string $isi, string $namaFile = 'akun.csv'): TestResponse
    {
        return $this->actingAs($this->admin)->from('/admin/akun')->post('/admin/akun/import', [
            'file' => UploadedFile::fake()->createWithContent($namaFile, $isi),
        ]);
    }

    private function unduh(): TestResponse
    {
        return $this->actingAs($this->admin)->from('/admin/akun')->post('/admin/akun/import/password');
    }

    private function jumlahAkun(): int
    {
        return User::count();
    }

    public function test_import_membuat_akun_siswa_dan_pj_dengan_password_acak(): void
    {
        $this->import("nama,nis,kelas,no_absen,role\nBudi Santoso,111,XI RPL 1,1,\nSiti Aminah,112,XI RPL 1,2,pj_kebersihan\n")
            ->assertRedirect('/admin/akun')
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', '2 akun dibuat.');

        $budi = User::where('nis', '111')->firstOrFail();
        $this->assertSame(Role::Siswa, $budi->role);
        $this->assertSame($this->kelas->id, $budi->class_id);
        $this->assertSame($this->sekolah->id, $budi->school_id);
        $this->assertSame(1, $budi->no_absen);
        $this->assertNull($budi->email);
        $this->assertTrue($budi->harus_ganti_password);
        $this->assertSame(Role::PjKebersihan, User::where('nis', '112')->value('role'));

        $daftar = session(AkunController::SESI_PASSWORD_IMPORT);
        $this->assertCount(2, $daftar);
        $this->assertSame(['nama' => 'Budi Santoso', 'kelas' => 'XI RPL 1', 'no_absen' => 1, 'nis' => '111'], array_diff_key($daftar[0], ['password' => 1]));
        $this->assertPasswordAcakSesuaiAturan($daftar[0]['password']);
        $this->assertTrue(Hash::check($daftar[0]['password'], $budi->password));

        // Halaman daftar akun menampilkan kotak unduh.
        $this->actingAs($this->admin)->get('/admin/akun')
            ->assertSee('Daftar password awal 2 akun belum diunduh')
            ->assertSee('Unduh daftar password');
    }

    public function test_titik_koma_bom_huruf_besar_dan_tanpa_kolom_role(): void
    {
        // Gaya Excel berbahasa Indonesia: BOM, pemisah ";", CRLF, sel kosong di ujung, baris kosong.
        $isi = "\xEF\xBB\xBFNama;NIS;Kelas;No_Absen\r\nBudi;211;xi rpl 1;05;\r\n;;;\r\n\r\nSiti;212;XI RPL 1;6\r\n";

        $this->import($isi)->assertSessionHasNoErrors()->assertSessionHas('status', '2 akun dibuat.');

        $budi = User::where('nis', '211')->firstOrFail();
        $this->assertSame(Role::Siswa, $budi->role);
        $this->assertSame(5, $budi->no_absen);
        $this->assertSame($this->kelas->id, $budi->class_id);
    }

    public function test_satu_baris_salah_menolak_seluruh_file_dengan_nomor_baris(): void
    {
        $sudahAda = $this->siswa(); // no_absen 1
        $this->kelasBaru('XI TKJ 1', School::factory()->create());
        $sebelum = $this->jumlahAkun();

        $isi = implode("\n", [
            'nama,nis,kelas,no_absen,role',
            'Benar,301,XI RPL 1,10,',                   // baris 2: benar, tapi ikut batal
            "Lama,{$sudahAda->nis},XI RPL 1,11,",        // 3: NIS sudah dipakai
            'Kembar,301,XI RPL 1,12,',                  // 4: NIS sama dengan baris 2
            'Tanpa Kelas,302,XII RPL 9,1,',             // 5: kelas tidak ada
            'Sekolah Lain,303,XI TKJ 1,1,',             // 6: kelas milik sekolah lain
            'Absen Dipakai,304,XI RPL 1,1,',            // 7: no. absen dipakai
            'Absen Kembar,305,XI RPL 1,10,',            // 8: no. absen sama dengan baris 2
            'Guru,306,XI RPL 1,13,guru',                // 9: role tidak boleh
            ',307,XI RPL 1,14,',                        // 10: nama kosong
            'NIS Aneh,1.2E+11,XI RPL 1,15,',            // 11: NIS bukan huruf/angka
            'Absen Nol,308,XI RPL 1,0,',                // 12: no. absen < 1
        ]);

        $this->import($isi)->assertRedirect('/admin/akun')->assertSessionHasErrors([
            'file' => 'Baris 3: NIS sudah dipakai.',
        ]);

        $pesan = session('errors')->get('file');
        $this->assertContains('Baris 4: NIS sama dengan baris 2.', $pesan);
        $this->assertContains('Baris 5: Kelas "XII RPL 9" tidak ada di sekolah ini.', $pesan);
        $this->assertContains('Baris 6: Kelas "XI TKJ 1" tidak ada di sekolah ini.', $pesan);
        $this->assertContains('Baris 7: No. absen ini sudah dipakai di kelas tersebut.', $pesan);
        $this->assertContains('Baris 8: No. absen 10 di kelas XI RPL 1 sama dengan baris 2.', $pesan);
        $this->assertContains('Baris 9: Role harus siswa atau pj_kebersihan (kosong = siswa).', $pesan);
        $this->assertContains('Baris 10: Nama wajib diisi.', $pesan);
        $this->assertContains('Baris 11: NIS hanya boleh berisi huruf dan angka.', $pesan);
        $this->assertTrue(collect($pesan)->contains(fn ($p) => str_starts_with($p, 'Baris 12: No. absen')));
        $this->assertFalse(collect($pesan)->contains(fn ($p) => str_starts_with($p, 'Baris 2:')));

        $this->assertSame($sebelum, $this->jumlahAkun(), 'Tidak ada akun yang boleh dibuat.');
        $this->assertNull(session(AkunController::SESI_PASSWORD_IMPORT));
    }

    public function test_pj_ketiga_di_kelas_ditolak(): void
    {
        $this->siswa(Role::PjKebersihan);
        $sebelum = $this->jumlahAkun();

        $this->import("nama,nis,kelas,no_absen,role\nA,401,XI RPL 1,10,pj_kebersihan\nB,402,XI RPL 1,11,pj_kebersihan\n")
            ->assertSessionHasErrors(['file' => 'Baris 3: Kelas XI RPL 1 sudah punya 2 PJ kebersihan.']);

        $this->assertSame($sebelum, $this->jumlahAkun());
    }

    public function test_format_file_yang_salah_ditolak(): void
    {
        $sebelum = $this->jumlahAkun();

        $this->import("nama,nis,kelas\nBudi,1,XI RPL 1\n")->assertSessionHasErrors(['file' => ImportAkun::PESAN_JUDUL]);
        $this->import("nama,nis,kelas,no_absen,email\nBudi,1,XI RPL 1,1,a@b.c\n")->assertSessionHasErrors(['file' => ImportAkun::PESAN_JUDUL]);
        $this->import("nama,nis,nis,kelas,no_absen\n")->assertSessionHasErrors(['file' => ImportAkun::PESAN_JUDUL]);
        $this->import("nama,nis,kelas,no_absen\n\n")->assertSessionHasErrors(['file' => ImportAkun::PESAN_KOSONG]);
        $this->import('')->assertSessionHasErrors('file');
        $this->import("nama,nis,kelas,no_absen\nJos\xE9,1,XI RPL 1,1\n")->assertSessionHasErrors(['file' => ImportAkun::PESAN_BUKAN_UTF8]);
        $this->import("nama,nis,kelas,no_absen\nBudi,1,XI RPL 1,1,lebih\n")
            ->assertSessionHasErrors(['file' => 'Baris 2: jumlah kolom lebih banyak dari baris judul.']);
        $this->import("nama,nis,kelas,no_absen\nBudi,1,XI RPL 1,1\n", 'akun.xlsx')
            ->assertSessionHasErrors(['file' => 'File harus berformat .csv.']);

        $this->assertSame($sebelum, $this->jumlahAkun());
    }

    public function test_lebih_dari_50_baris_ditolak(): void
    {
        $baris = ['nama,nis,kelas,no_absen'];
        for ($i = 1; $i <= ImportAkun::MAKS_BARIS + 1; $i++) {
            $baris[] = "Siswa {$i},5{$i},XI RPL 1,{$i}";
        }

        $this->import(implode("\n", $baris))->assertSessionHasErrors([
            'file' => 'File berisi 51 baris akun, maks 50 per file. Pecah menjadi beberapa file, mis. satu file per kelas.',
        ]);
        $this->assertSame(0, User::where('nis', 'like', '5%')->count());
    }

    public function test_daftar_password_bisa_diunduh_tepat_sekali(): void
    {
        $this->import("nama,nis,kelas,no_absen\nAni,601,XI RPL 1,1\nBudi,602,XI RPL 1,2\n");
        $daftar = session(AkunController::SESI_PASSWORD_IMPORT);

        $respons = $this->unduh()->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringContainsString('attachment;', $respons->headers->get('Content-Disposition'));

        $this->assertStringContainsString($daftar[1]['password'], $respons->getContent());

        // Sudah dihapus dari sesi: unduhan kedua ditolak, kotak unduh hilang.
        $this->assertNull(session(AkunController::SESI_PASSWORD_IMPORT));
        $this->unduh()->assertRedirect('/admin/akun')->assertSessionHasErrors(['unduh' => AkunController::PESAN_SUDAH_DIUNDUH]);
        $this->actingAs($this->admin)->get('/admin/akun')->assertDontSee('Unduh daftar password');
    }

    /** SPEC F9: pemisah titik koma + BOM UTF-8, supaya langsung rapi di Excel berbahasa Indonesia. */
    public function test_isi_file_unduhan_pakai_titik_koma_dan_bom(): void
    {
        $this->import("nama,nis,kelas,no_absen\nBudi,602,XI RPL 1,2\n\"Andi; Jr\",603,XI RPL 1,3\n=HYPERLINK(\"x\"),601,XI RPL 1,1\n");
        $daftar = session(AkunController::SESI_PASSWORD_IMPORT);

        $isi = $this->unduh()->assertOk()->getContent();

        // BOM UTF-8 tepat di awal file, sekali saja.
        $this->assertStringStartsWith("\xEF\xBB\xBF", $isi);
        $tanpaBom = substr($isi, 3);
        $this->assertStringNotContainsString("\xEF\xBB\xBF", $tanpaBom);

        $baris = array_map(fn ($b) => str_getcsv($b, ';', '"', ''), explode("\n", rtrim($tanpaBom, "\n")));

        $this->assertSame('nama;kelas;no_absen;nis;password', explode("\n", $tanpaBom)[0]);
        $this->assertSame(['nama', 'kelas', 'no_absen', 'nis', 'password'], $baris[0]);
        $this->assertCount(4, $baris);
        foreach ($baris as $kolom) {
            $this->assertCount(5, $kolom, 'Setiap baris harus terbagi tepat 5 kolom oleh titik koma.');
        }

        // Urut no. absen; nama yang mengandung ";" tetap satu kolom (diberi tanda kutip).
        $this->assertSame(["'=HYPERLINK(\"x\")", 'XI RPL 1', '1', '601', $daftar[0]['password']], $baris[1]);
        $this->assertSame(['Budi', 'XI RPL 1', '2', '602', $daftar[1]['password']], $baris[2]);
        $this->assertSame(['Andi; Jr', 'XI RPL 1', '3', '603', $daftar[2]['password']], $baris[3]);
    }

    public function test_import_baru_ditolak_selama_daftar_belum_diunduh(): void
    {
        $this->import("nama,nis,kelas,no_absen\nBudi,701,XI RPL 1,1\n")->assertSessionHasNoErrors();
        $daftarPertama = session(AkunController::SESI_PASSWORD_IMPORT);

        $this->import("nama,nis,kelas,no_absen\nSiti,702,XI RPL 1,2\n")
            ->assertSessionHasErrors(['file' => 'Unduh dulu daftar password sebelumnya di halaman Akun.']);

        $this->assertFalse(User::where('nis', '702')->exists());
        $this->assertSame($daftarPertama, session(AkunController::SESI_PASSWORD_IMPORT));

        // Setelah diunduh, import berikutnya boleh.
        $this->unduh()->assertOk();
        $this->import("nama,nis,kelas,no_absen\nSiti,702,XI RPL 1,2\n")->assertSessionHasNoErrors();
    }

    public function test_route_import_dan_unduh_memakai_kunci_sesi(): void
    {
        // Tanpa kunci sesi, import kedua dari tab lain bisa menimpa sesi dan menghapus
        // daftar password import pertama (temuan review Langkah 5).
        // Reset massal (v0.11) memakai daftar tertunda yang sama, jadi ikut dikunci.
        foreach (['admin.akun.import', 'admin.akun.import.password', 'admin.kelas.reset-password'] as $nama) {
            $route = Route::getRoutes()->getByName($nama);
            $this->assertGreaterThan(120, $route->locksFor(), "{$nama}: kunci harus lebih lama dari set_time_limit(120).");
            $this->assertGreaterThan(0, $route->waitsFor(), $nama);
        }
    }

    public function test_sel_kosong_di_ujung_judul_dan_nol_di_depan_no_absen(): void
    {
        $this->import("nama,nis,kelas,no_absen,,\nBudi,901,XI RPL 1,000007,,\n")->assertSessionHasNoErrors();
        $this->assertSame(7, User::where('nis', '901')->value('no_absen'));
    }

    /**
     * Penyimpanan bersamaan: data berubah di antara pemeriksaan dan penyimpanan.
     * Perubahan disisipkan tepat saat transaksi import dimulai.
     */
    private function saatTransaksiImportDimulai(callable $ubah): void
    {
        $sudah = false;
        Event::listen(TransactionBeginning::class, function () use (&$sudah, $ubah) {
            if (! $sudah) {
                $sudah = true;
                $ubah();
            }
        });
    }

    public function test_nis_dipakai_bersamaan_tampil_sebagai_pesan_biasa(): void
    {
        $this->saatTransaksiImportDimulai(fn () => $this->buatUser(Role::Siswa, [
            'school_id' => $this->sekolah->id, 'class_id' => $this->kelas->id, 'no_absen' => 40, 'nis' => '1001',
        ]));
        $sebelum = $this->jumlahAkun();

        $this->import("nama,nis,kelas,no_absen\nA,1000,XI RPL 1,1\nB,1001,XI RPL 1,2\n")
            ->assertRedirect('/admin/akun')
            ->assertSessionHasErrors(['file' => ImportAkun::PESAN_KEMBAR_BERSAMAAN]);

        $this->assertSame($sebelum, $this->jumlahAkun(), 'Baris A juga tidak boleh tersimpan.');
        $this->assertNull(session(AkunController::SESI_PASSWORD_IMPORT));
    }

    public function test_kelas_dihapus_bersamaan_ditolak_dengan_pesan(): void
    {
        $kelasKosong = $this->kelasBaru('XI RPL 3');
        $this->saatTransaksiImportDimulai(fn () => $kelasKosong->delete());
        $sebelum = $this->jumlahAkun();

        $this->import("nama,nis,kelas,no_absen\nA,1100,XI RPL 3,1\n")
            ->assertSessionHasErrors(['file' => ImportAkun::PESAN_KELAS_BERUBAH]);

        $this->assertSame($sebelum, $this->jumlahAkun());
    }

    public function test_pj_ditambah_bersamaan_dicek_ulang_di_transaksi(): void
    {
        $this->siswa(Role::PjKebersihan);
        $this->saatTransaksiImportDimulai(fn () => $this->siswa(Role::PjKebersihan));
        $this->import("nama,nis,kelas,no_absen,role\nA,1200,XI RPL 1,20,pj_kebersihan\n")
            ->assertSessionHasErrors(['file' => 'Kelas XI RPL 1 sudah punya 2 PJ kebersihan. Upload ulang file untuk melihat barisnya.']);

        // (PJ "admin lain" di tes ini disisipkan di dalam transaksi import, jadi ikut batal.)
        $this->assertFalse(User::where('nis', '1200')->exists());
    }

    public function test_akun_hasil_import_wajib_ganti_password_saat_login(): void
    {
        $this->import("nama,nis,kelas,no_absen\nBudi,801,XI RPL 1,1\n");
        $password = session(AkunController::SESI_PASSWORD_IMPORT)[0]['password'];
        auth()->guard()->logout();

        $this->post('/login', ['login' => '801', 'password' => $password])->assertRedirect();
        $this->get('/beranda')->assertRedirect('/ganti-password');
    }
}
