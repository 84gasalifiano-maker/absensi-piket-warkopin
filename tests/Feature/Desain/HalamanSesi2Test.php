<?php

namespace Tests\Feature\Desain;

use App\Enums\Role;
use App\Enums\Tugas;
use App\Models\DutySchedule;
use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Admin\AdminTestCase;

/**
 * SPEC §11 Langkah 5c sesi 2 (v0.17): ganti password, halaman sementara, 404, pilih kelas,
 * kelola jadwal, dan menu admin (dipakai halaman jadwal). Setiap halaman ikut mode gelap
 * (meta "light dark", tanpa data-hanya-terang) dan target sentuh minimal 44 px.
 * Teks halaman dicek oleh tes fiturnya masing-masing; di sini hanya tampilan.
 */
class HalamanSesi2Test extends AdminTestCase
{
    private function assertIkutModeGelap(TestResponse $halaman, string $nama): void
    {
        $html = $halaman->getContent();

        $this->assertStringContainsString('<meta name="color-scheme" content="light dark">', $html, $nama);
        $this->assertDoesNotMatchRegularExpression('/<html[^>]*data-hanya-terang/', $html, $nama);
    }

    /**
     * Semua elemen yang cocok $pola (grup 1 = isi atribut class) memuat semua kelas $harapan.
     *
     * @param  list<string>  $harapan
     */
    private function assertSemuaPunyaKelas(string $html, string $pola, array $harapan, int $minimal = 1): void
    {
        $jumlah = preg_match_all($pola, $html, $cocok);
        $this->assertGreaterThanOrEqual($minimal, $jumlah, "Elemen {$pola} tidak ditemukan");

        foreach ($cocok[1] as $kelas) {
            $daftar = preg_split('/\s+/', trim($kelas));
            foreach ($harapan as $k) {
                $this->assertContains($k, $daftar, "Kelas {$k} tidak ada di: {$kelas}");
            }
        }
    }

    private function pj(): User
    {
        return $this->siswa(Role::PjKebersihan);
    }

    // --- Ganti password ---

    public function test_ganti_password_wajib_ikut_mode_gelap(): void
    {
        $pj = $this->pj();
        $pj->forceFill(['harus_ganti_password' => true])->save();

        $halaman = $this->actingAs($pj)
            ->get(route('ganti-password'))->assertOk();
        $this->assertIkutModeGelap($halaman, 'ganti password wajib');

        $html = $halaman->getContent();
        // Kotak "wajib ganti" memakai pesan bersama jenis peringatan; teksnya tetap.
        $this->assertMatchesRegularExpression('/<div role="status" class="[^"]*\bbg-peringatan-lembut\b[^"]*">\s*Kamu wajib mengganti password dulu sebelum bisa lanjut\.\s*<\/div>/', $html);
        $this->assertSemuaPunyaKelas($html, '/<button type="submit" class="([^"]*)">Simpan password<\/button>/', ['min-h-11', 'bg-utama', 'w-full']);
    }

    public function test_ganti_password_biasa_ikut_mode_gelap(): void
    {
        $halaman = $this->actingAs($this->pj())->get(route('ganti-password'))->assertOk();
        $this->assertIkutModeGelap($halaman, 'ganti password biasa');

        // Kotak info (SPEC §8.2 butir 2), sama seperti kotak "wajib ganti".
        $this->assertMatchesRegularExpression('/<div role="status" class="[^"]*\bbg-info-lembut\b[^"]*">\s*Password baru minimal 8 karakter dan harus berbeda dari password lama\.\s*<\/div>/', $halaman->getContent());
    }

    // --- Halaman sementara ---

    public function test_beranda_pj_dan_verifikasi_guru_ikut_mode_gelap_dengan_tombol_44px(): void
    {
        $guru = $this->guru();

        foreach (['beranda' => $this->pj(), 'verifikasi' => $guru] as $route => $user) {
            $halaman = $this->actingAs($user)->get(route($route))->assertOk()
                ->assertSeeInOrder(["Halo, {$user->nama}.", 'Halaman ini masih dalam pembuatan.']);
            $this->assertIkutModeGelap($halaman, $route);

            $this->assertSemuaPunyaKelas($halaman->getContent(), '/<a href="'.preg_quote(route('jadwal'), '/').'" class="([^"]*)">Jadwal piket<\/a>/', ['min-h-11', 'bg-utama', 'text-pada-utama']);
        }
    }

    // --- 404 ---

    public function test_halaman_404_ikut_mode_gelap_untuk_tamu_dan_yang_login(): void
    {
        foreach (['tamu' => null, 'admin' => $this->admin] as $nama => $user) {
            $permintaan = $user ? $this->actingAs($user) : $this;
            $halaman = $permintaan->get('/alamat-yang-tidak-ada')->assertNotFound()->assertSeeInOrder([
                'Halaman tidak ditemukan',
                'Halaman atau data yang kamu cari tidak ada. Mungkin sudah dihapus, atau alamatnya salah.',
                'Kembali ke halaman awal',
            ]);
            $this->assertIkutModeGelap($halaman, "404 {$nama}");

            $this->assertSemuaPunyaKelas($halaman->getContent(), '/<a href="'.preg_quote(url('/'), '/').'" class="([^"]*)">Kembali ke halaman awal<\/a>/', ['min-h-11', 'bg-utama']);
        }
    }

    // --- Jadwal ---

    public function test_pilih_kelas_ikut_mode_gelap_dengan_tautan_44px_dan_menu_admin_bertoken(): void
    {
        $this->kelasBaru('XI RPL 2');

        $halaman = $this->actingAs($this->admin)->get(route('jadwal'))->assertOk();
        $this->assertIkutModeGelap($halaman, 'pilih kelas');

        $html = $halaman->getContent();
        $this->assertSemuaPunyaKelas($html, '/<a href="[^"]*\/jadwal\?kelas=\d+"\s+class="([^"]*)">/', ['min-h-11', 'bg-permukaan', 'text-teks'], minimal: 2);

        // Menu admin (pilihan A): token, 44 px; menu aktif memakai warna utama.
        $this->assertSemuaPunyaKelas($html, '/<a href="[^"]*\/admin[^"]*"[^>]*class="([^"]*)">/', ['min-h-11'], minimal: 5);
        $this->assertMatchesRegularExpression('/<a href="'.preg_quote(route('admin'), '/').'"\s+class="[^"]*\bbg-permukaan\b/', $html);
    }

    public function test_pilih_kelas_kosong_ikut_mode_gelap(): void
    {
        $halaman = $this->actingAs($this->guru())->get(route('jadwal'))->assertOk()
            ->assertSee('Kamu belum menjadi wali kelas mana pun.');
        $this->assertIkutModeGelap($halaman, 'guru tanpa kelas');
    }

    public function test_kelola_jadwal_ikut_mode_gelap_dengan_dropdown_dan_simpan_44px(): void
    {
        $pj = $this->pj();
        $siswa = $this->siswa();
        DutySchedule::create(['class_id' => $this->kelas->id, 'user_id' => $siswa->id, 'hari' => 1, 'tugas_awal' => Tugas::Menyapu]);

        $halaman = $this->actingAs($pj)->get(route('jadwal'))->assertOk();
        $this->assertIkutModeGelap($halaman, 'kelola jadwal');

        $html = $halaman->getContent();
        $this->assertSemuaPunyaKelas($html, '/<select id="(?:hari|tugas)-\d+"[^>]*class="([^"]*)"/', ['min-h-11', 'bg-permukaan', 'text-teks', 'border-garis-isian', 'text-base'], minimal: 4);
        $this->assertSemuaPunyaKelas($html, '/<button type="submit" class="([^"]*)">Simpan<\/button>/', ['min-h-11', 'bg-utama', 'w-full']);
        // Bilah Simpan menempel di bawah dan diberi penanda untuk jarak gulir (app.css).
        $this->assertSemuaPunyaKelas($html, '/<div data-bilah-menempel class="([^"]*)">/', ['sticky', 'bottom-0', 'bg-dasar', 'border-garis']);
        $this->assertStringContainsString('Perubahan langsung berlaku, termasuk minggu ini.', $html);
    }

    public function test_kelola_jadwal_error_menyorot_dengan_token_dan_bingkai_error(): void
    {
        $pj = $this->pj();
        $siswa = $this->siswa();

        $halaman = $this->actingAs($pj)->followingRedirects()->from(route('jadwal'))->post(route('jadwal.store'), [
            'minggu_acuan' => now()->startOfWeek()->toDateString(),
            'jadwal' => [$siswa->id => ['hari' => '1', 'tugas' => '']],
        ]);

        $html = $halaman->getContent();
        $this->assertSemuaPunyaKelas($html, '/<div role="alert" class="([^"]*)">/', ['bg-bahaya-lembut']);
        $this->assertStringContainsString($siswa->nama.': Tugas wajib dipilih.', $html);
        $this->assertSemuaPunyaKelas($html, '/<select id="tugas-'.$siswa->id.'"[^>]*aria-invalid="true"[^>]*class="([^"]*)"/', ['border-bahaya', 'min-h-11']);
        $this->assertSemuaPunyaKelas($html, '/<select id="hari-'.$siswa->id.'"[^>]*class="([^"]*)"/', ['border-garis-isian']);
    }

    public function test_kelola_jadwal_kelas_kosong_ikut_mode_gelap(): void
    {
        $halaman = $this->actingAs($this->admin)->get(route('jadwal', ['kelas' => $this->kelasBaru('XII Kosong')->id]))
            ->assertOk()->assertSee('Kelas ini belum punya siswa.');
        $this->assertIkutModeGelap($halaman, 'kelas kosong');
    }

    public function test_jarak_gulir_bawah_untuk_bilah_menempel(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression('/html:has\(\[data-bilah-menempel\]\)\s*\{\s*scroll-padding-bottom:\s*7rem;/', $css);
    }

    public function test_kartu_padding_diganti_lewat_prop_tanpa_kelas_padding_ganda(): void
    {
        $bawaan = Blade::render('<x-kartu>Isi</x-kartu>');
        $rapat = Blade::render('<x-kartu padding="p-0" class="mb-3">Isi</x-kartu>');

        $this->assertMatchesRegularExpression('/class="[^"]*\bp-5\b[^"]*\bsm:p-6\b/', $bawaan);
        $this->assertMatchesRegularExpression('/class="[^"]*\bp-0\b[^"]*"/', $rapat);
        $this->assertDoesNotMatchRegularExpression('/\b(p-5|sm:p-6)\b/', $rapat);
        $this->assertStringContainsString('mb-3', $rapat);
    }
}
