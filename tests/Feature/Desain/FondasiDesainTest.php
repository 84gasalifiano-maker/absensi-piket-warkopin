<?php

namespace Tests\Feature\Desain;

use App\Enums\Role;
use App\Models\School;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * SPEC §8 "Desain & Dark mode", §11 Langkah 5c (v0.16), sesi 1: token warna, komponen
 * bersama, layout, x-field/x-select, dan contoh halaman login.
 */
class FondasiDesainTest extends TestCase
{
    use RefreshDatabase;

    /**
     * File view yang sudah memakai token warna. Sesi 5c berikutnya menambah file ke sini;
     * di sesi terakhir pemeriksaan ini berlaku untuk seluruh resources/views (SPEC 5c).
     */
    private const VIEW_SUDAH_TOKEN = [
        'auth/login.blade.php',
        'components/layouts/app.blade.php',
        'components/field.blade.php',
        'components/select.blade.php',
        'components/tombol.blade.php',
        'components/kartu.blade.php',
        'components/pesan.blade.php',
    ];

    /** Pola warna Tailwind mentah dari SPEC 5c "Selesai kalau". */
    private const POLA_WARNA_MENTAH = '/\b(bg|text|border|ring|outline|divide|from|via|to|fill|stroke|placeholder|accent|caret|decoration|shadow)-(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|black|white)\b/';

    /** @return array<string, array{string}> */
    public static function viewSudahToken(): array
    {
        return array_combine(self::VIEW_SUDAH_TOKEN, array_map(fn ($f) => [$f], self::VIEW_SUDAH_TOKEN));
    }

    #[DataProvider('viewSudahToken')]
    public function test_view_yang_sudah_diubah_tidak_menulis_warna_langsung(string $file): void
    {
        $isi = file_get_contents(resource_path('views/'.$file));

        $this->assertSame(0, preg_match_all(self::POLA_WARNA_MENTAH, $isi, $cocok), "{$file}: ".implode(', ', $cocok[0] ?? []));
        $this->assertStringNotContainsString('-[#', $isi, "{$file}: warna arbitrer");
        $this->assertDoesNotMatchRegularExpression('/rgba?\(/', $isi, "{$file}: warna rgb()");
        $this->assertDoesNotMatchRegularExpression('/style="[^"]*color/i', $isi, "{$file}: warna di style");

        // Nilai gelap hanya lewat token. Kelas dark: bawaan memakai @media biasa, jadi selama
        // masa transisi akan ikut gelap juga di halaman ber-data-hanya-terang (review 5c).
        $this->assertDoesNotMatchRegularExpression('/\bdark:/', $isi, "{$file}: kelas dark: langsung");
    }

    public function test_pola_warna_mentah_benar_benar_menangkap_kelas_lama(): void
    {
        // Pengaman tes: pola di atas harus menangkap kelas yang dulu dipakai.
        foreach (['bg-blue-600', 'text-gray-600', 'border-gray-300', 'text-red-700', 'focus:ring-blue-600/30', 'hover:bg-gray-100', 'bg-white'] as $kelas) {
            $this->assertMatchesRegularExpression(self::POLA_WARNA_MENTAH, $kelas);
        }
        foreach (['bg-permukaan', 'text-teks-lembut', 'border-garis-isian', 'bg-utama'] as $kelas) {
            $this->assertDoesNotMatchRegularExpression(self::POLA_WARNA_MENTAH, $kelas);
        }
    }

    // --- Mode gelap ---

    public function test_login_ikut_mode_gelap_hp(): void
    {
        $halaman = $this->get(route('login'))->assertOk();

        $halaman->assertSee('<meta name="color-scheme" content="light dark">', false);
        $this->assertDoesNotMatchRegularExpression('/<html[^>]*data-hanya-terang/', $halaman->getContent());
    }

    /** Sementara (5c sesi 1–2): halaman yang belum memakai token tetap terang. */
    public function test_halaman_yang_belum_diubah_tetap_terang(): void
    {
        $admin = $this->buatUser(Role::Admin, ['school_id' => School::factory()->create()->id]);

        foreach ([route('admin'), route('ganti-password')] as $url) {
            $halaman = $this->actingAs($admin)->get($url)->assertOk();

            $halaman->assertSee('<meta name="color-scheme" content="light">', false);
            $this->assertMatchesRegularExpression('/<html[^>]*data-hanya-terang/', $halaman->getContent(), $url);
        }
    }

    public function test_nilai_gelap_hanya_untuk_halaman_tanpa_data_hanya_terang(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertMatchesRegularExpression('/@media\s*\(prefers-color-scheme:\s*dark\)\s*\{\s*:root:not\(\[data-hanya-terang\]\)\s*\{[^}]*color-scheme:\s*dark;/s', $css);
        $this->assertMatchesRegularExpression('/:root\s*\{\s*color-scheme:\s*light;/', $css);
        $this->assertMatchesRegularExpression('/:focus-visible\s*\{[^}]*outline:\s*2px solid var\(--color-fokus\)/', $css);
        $this->assertStringNotContainsString('@custom-variant', $css); // diputuskan v0.16: dark: bawaan
    }

    // --- Halaman login (contoh) ---

    public function test_teks_dan_perilaku_login_tidak_berubah(): void
    {
        $halaman = $this->get(route('login'))->assertOk()->assertSeeInOrder([
            'Masuk pakai NIS (siswa) atau email (guru/admin).',
            'NIS atau email',
            'Password',
            'Ingat saya',
            'Masuk',
        ]);

        $html = $halaman->getContent();
        $this->assertMatchesRegularExpression('/<title>Masuk — Absensi Piket<\/title>/', $html);
        $this->assertMatchesRegularExpression('/<h1[^>]*>Absensi Piket<\/h1>/', $html);
        $this->assertMatchesRegularExpression('/<button type="submit"[^>]*data-teks-proses="Memproses…"[^>]*>\s*Masuk\s*<\/button>/', $html);
        $this->assertMatchesRegularExpression('/<input type="checkbox" name="remember" value="1"(?![^>]*checked)[^>]*>/', $html); // default tidak dicentang
        $halaman->assertSee('aria-label="Tampilkan password"', false);

        // Atribut isian login tetap (perilaku HP: tanpa huruf besar otomatis, isi otomatis browser).
        $this->assertMatchesRegularExpression('/<input\s+id="login"\s+name="login"\s+type="text"[^>]*\bautocomplete="username"[^>]*\bautocapitalize="none"[^>]*\brequired\b[^>]*\bautofocus\b/', $html);
        $this->assertMatchesRegularExpression('/<input\s+id="password"\s+name="password"\s+type="password"(?![^>]*\bvalue=)[^>]*\bautocomplete="current-password"[^>]*\brequired\b/', $html);
    }

    public function test_login_gagal_mengembalikan_isian_lama_dan_menandai_kolom(): void
    {
        $halaman = $this->followingRedirects()->from(route('login'))
            ->post(route('login'), ['login' => '12345', 'password' => 'salah-sekali', 'remember' => '1']);

        $html = $halaman->getContent();
        $this->assertMatchesRegularExpression('/<input\s+id="login"[^>]*\bvalue="12345"[^>]*\baria-invalid="true"[^>]*\baria-describedby="login-error"/', $html);
        $this->assertMatchesRegularExpression('/<p id="login-error" class="[^"]*\btext-bahaya\b[^"]*">/', $html);
        $this->assertMatchesRegularExpression('/<input type="checkbox" name="remember" value="1"\s+checked/', $html); // centang lama dipertahankan
        $this->assertDoesNotMatchRegularExpression('/<input\s+id="password"[^>]*\bvalue=/', $html); // password tidak pernah diisi ulang
    }

    public function test_tombol_keluar_dan_tautan_ganti_password_di_layout(): void
    {
        $admin = $this->buatUser(Role::Admin, ['school_id' => School::factory()->create()->id]);
        $html = $this->actingAs($admin)->get(route('admin'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('/<button type="submit"[^>]*data-teks-proses="Keluar…"[^>]*>Keluar<\/button>/', $html);
        $this->assertKelas($html, ['min-h-11', 'text-utama'], '/<a href="'.preg_quote(route('ganti-password'), '/').'" class="([^"]*)">Ganti password<\/a>/');
    }

    // --- Komponen ---

    public function test_tombol_tiga_varian_minimal_44px_dan_atribut_diteruskan(): void
    {
        $utama = Blade::render('<x-tombol data-teks-proses="Menghapus…" class="w-full">Simpan</x-tombol>');
        $this->assertMatchesRegularExpression('/^<button type="submit"[^>]*>Simpan<\/button>$/', trim($utama));
        $this->assertStringContainsString('data-teks-proses="Menghapus…"', $utama);
        $this->assertKelas($utama, ['min-h-11', 'bg-utama', 'text-pada-utama', 'w-full']);

        $sekunder = Blade::render('<x-tombol varian="sekunder" type="button" data-tetap-nonaktif disabled>Tunggu</x-tombol>');
        $this->assertMatchesRegularExpression('/^<button type="button"[^>]*>Tunggu<\/button>$/', trim($sekunder));
        $this->assertStringContainsString('data-tetap-nonaktif', $sekunder);
        $this->assertStringContainsString('disabled', $sekunder);
        $this->assertKelas($sekunder, ['min-h-11', 'border-garis-isian', 'bg-permukaan', 'text-teks']);

        $bahaya = Blade::render('<x-tombol varian="bahaya">Hapus</x-tombol>');
        $this->assertKelas($bahaya, ['min-h-11', 'bg-bahaya', 'text-pada-bahaya']);

        $tautan = Blade::render('<x-tombol href="/jadwal" varian="sekunder">Jadwal</x-tombol>');
        $this->assertMatchesRegularExpression('/^<a href="\/jadwal"[^>]*>Jadwal<\/a>$/', trim($tautan));
        $this->assertKelas($tautan, ['min-h-11', 'border-garis-isian']);
    }

    public function test_isian_dan_dropdown_minimal_44px_memakai_token(): void
    {
        // Di request biasa $errors dibagikan middleware ShareErrorsFromSession; di sini manual.
        view()->share('errors', new ViewErrorBag);
        $field = Blade::render('<x-field name="nama" label="Nama" />');
        $select = Blade::render('<x-select name="hari" label="Hari" :options="[1 => \'Senin\']" />');

        $harapan = ['min-h-11', 'border-garis-isian', 'bg-permukaan', 'text-teks', 'text-base'];
        $this->assertKelas($field, $harapan, '/<input[^>]*class="([^"]*)"/');
        $this->assertKelas($select, $harapan, '/<select[^>]*class="([^"]*)"/');
    }

    /** Perilaku x-field/x-select tetap setelah warnanya diganti token: error, aria-*, isian lama. */
    public function test_isian_dan_dropdown_dengan_error_dan_isian_lama(): void
    {
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['nama' => 'Nama wajib diisi.', 'hari' => 'Hari wajib dipilih.']));
        view()->share('errors', $errors);
        // old() membaca sesi lewat request; di luar request HTTP sesinya dipasang manual.
        request()->setLaravelSession(app('session.store'));
        session()->flashInput(['nama' => 'Budi', 'hari' => '2']);

        $field = Blade::render('<x-field name="nama" label="Nama" bantuan="Nama lengkap." value="Lama" />');
        $this->assertMatchesRegularExpression('/<input[^>]*\bvalue="Budi"[^>]*\baria-invalid="true"[^>]*\baria-describedby="nama-bantuan nama-error"/', $field);
        $this->assertMatchesRegularExpression('/<p id="nama-bantuan" class="[^"]*\btext-teks-lembut\b[^"]*">Nama lengkap\.<\/p>/', $field);
        $this->assertMatchesRegularExpression('/<p id="nama-error" class="[^"]*\btext-bahaya\b[^"]*">Nama wajib diisi\.<\/p>/', $field);

        $select = Blade::render('<x-select name="hari" label="Hari" kosong="— Pilih —" :options="[1 => \'Senin\', 2 => \'Selasa\']" value="1" />');
        $this->assertMatchesRegularExpression('/<select[^>]*\baria-invalid="true"[^>]*\baria-describedby="hari-error"/', $select);
        $this->assertMatchesRegularExpression('/<option value="2"\s+selected>Selasa<\/option>/', $select);
        $this->assertDoesNotMatchRegularExpression('/<option value="1"\s+selected>/', $select);
        $this->assertMatchesRegularExpression('/<p id="hari-error" class="[^"]*\btext-bahaya\b[^"]*">Hari wajib dipilih\.<\/p>/', $select);
    }

    /**
     * Atribut class pertama yang cocok dengan $pola memuat semua kelas $harapan.
     *
     * @param  list<string>  $harapan
     */
    private function assertKelas(string $html, array $harapan, string $pola = '/class="([^"]*)"/'): void
    {
        $this->assertSame(1, preg_match($pola, $html, $cocok), "Tidak ada atribut class di: {$html}");
        $kelas = preg_split('/\s+/', trim($cocok[1]));

        foreach ($harapan as $k) {
            $this->assertContains($k, $kelas, "Kelas {$k} tidak ada di: {$cocok[1]}");
        }
    }

    public function test_pesan_empat_jenis_dengan_role_yang_tepat(): void
    {
        $harapan = [
            'sukses' => ['status', 'bg-sukses-lembut'],
            'info' => ['status', 'bg-info-lembut'],
            'error' => ['alert', 'bg-bahaya-lembut'],
            'peringatan' => ['status', 'bg-peringatan-lembut'], // bisa tampil tiap muat halaman: tidak dibacakan paksa
        ];

        foreach ($harapan as $jenis => [$role, $latar]) {
            $html = Blade::render("<x-pesan jenis=\"{$jenis}\">Isi pesan</x-pesan>");
            $this->assertMatchesRegularExpression('/^<div role="'.$role.'"[^>]*>\s*Isi pesan\s*<\/div>$/', trim($html), $jenis);
            $this->assertKelas($html, [$latar, 'text-teks', 'border-l-4']);
        }
    }

    public function test_pesan_status_di_layout_memakai_komponen_pesan(): void
    {
        $admin = $this->buatUser(Role::Admin, ['school_id' => School::factory()->create()->id]);

        $html = $this->actingAs($admin)->withSession(['status' => 'Pengaturan jam disimpan.'])->get(route('admin'))
            ->assertOk()
            ->getContent();

        $pola = '/<div role="status" class="([^"]*)">\s*Pengaturan jam disimpan\.\s*<\/div>/';
        $this->assertKelas($html, ['bg-sukses-lembut', 'border-sukses', 'text-teks', 'mb-4'], $pola);
    }
}
