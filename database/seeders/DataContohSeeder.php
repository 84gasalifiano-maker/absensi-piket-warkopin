<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\Kelas;
use App\Models\School;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Data contoh untuk pengembangan lokal: 1 sekolah, 2 kelas, akun tiap role.
 * Menolak jalan kalau APP_ENV bukan local, termasuk saat dipanggil langsung
 * (php artisan db:seed --class=DataContohSeeder), karena passwordnya mudah ditebak.
 *
 * - Semua akun berpassword "password".
 * - Semua nama & NIS PALSU. Nama dibuat Faker (seed tetap, jadi hasilnya sama
 *   setiap seeding). NIS berawalan "99" supaya jelas bukan NIS asli.
 * - XI RPL 1: 44 siswa (no. absen 1–44, sama dengan ukuran kelas asli),
 *   no. absen 1 adalah PJ kebersihan. XI RPL 2: kelas kecil, 4 siswa.
 * - Siswa/PJ login pakai NIS (tanpa email). Guru/admin login pakai email.
 * - Guru tidak punya class_id; kelasnya hanya dari classes.wali_kelas_id.
 * - Belum ada jadwal piket & hari libur: diisi lewat halaman masing-masing (SPEC §11).
 */
class DataContohSeeder extends Seeder
{
    private const PASSWORD = 'password';

    private const TANGGAL_MULAI_ROTASI = '2026-10-26'; // Senin

    private Generator $faker;

    private string $passwordHash;

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('DataContohSeeder dilewati: hanya boleh jalan saat APP_ENV=local.');

            return;
        }

        $this->faker = FakerFactory::create('id_ID');
        $this->faker->seed(2026);

        // Hash sekali saja; cast 'hashed' tidak meng-hash ulang nilai yang sudah ter-hash.
        $this->passwordHash = Hash::make(self::PASSWORD);

        DB::transaction(function () {
            $school = School::create(['nama' => 'SMK Contoh Nusantara']);

            $this->buatAkun($school, Role::Admin, nama: 'Admin Sekolah', email: 'admin@absensi.test');

            $this->buatKelas($school, 'XI RPL 1', kodeNis: '9901', jumlahSiswa: 44, emailGuru: 'guru1@absensi.test');
            $this->buatKelas($school, 'XI RPL 2', kodeNis: '9902', jumlahSiswa: 4, emailGuru: 'guru2@absensi.test');

            // Satu akun untuk mengetes wajib ganti password saat login pertama:
            // siswa no. absen terakhir XI RPL 1.
            User::where('nis', '990144')->firstOrFail()
                ->forceFill(['harus_ganti_password' => true])
                ->save();
        });
    }

    /**
     * Buat kelas + wali kelas + siswa. No. absen 1 dijadikan PJ kebersihan.
     * NIS = kodeNis + no. absen 2 digit, mis. 9901 + 07 = "990107".
     */
    private function buatKelas(School $school, string $nama, string $kodeNis, int $jumlahSiswa, string $emailGuru): void
    {
        $guru = $this->buatAkun($school, Role::Guru, nama: $this->namaPalsu(), email: $emailGuru);

        $kelas = Kelas::create([
            'school_id' => $school->id,
            'nama' => $nama,
            'wali_kelas_id' => $guru->id,
            // Wajib, harus hari Senin (SPEC F2). Tanggal tetap untuk data contoh,
            // disetujui pemilik proyek (SPEC §11 Langkah 3, v0.7).
            'tanggal_mulai_rotasi' => self::TANGGAL_MULAI_ROTASI,
        ]);

        for ($noAbsen = 1; $noAbsen <= $jumlahSiswa; $noAbsen++) {
            $this->buatAkun(
                $school,
                $noAbsen === 1 ? Role::PjKebersihan : Role::Siswa,
                nama: $this->namaPalsu(),
                nis: $kodeNis.str_pad((string) $noAbsen, 2, '0', STR_PAD_LEFT),
                kelas: $kelas,
                noAbsen: $noAbsen,
            );
        }
    }

    private function buatAkun(
        School $school,
        Role $role,
        string $nama,
        ?string $email = null,
        ?string $nis = null,
        ?Kelas $kelas = null,
        ?int $noAbsen = null,
    ): User {
        // forceCreate karena role & harus_ganti_password tidak fillable.
        return User::forceCreate([
            'school_id' => $school->id,
            'class_id' => $kelas?->id,
            'nama' => $nama,
            'email' => $email,
            'nis' => $nis,
            'no_absen' => $noAbsen,
            'password' => $this->passwordHash,
            'role' => $role,
            'harus_ganti_password' => false,
        ]);
    }

    private function namaPalsu(): string
    {
        return $this->faker->unique()->firstName().' '.$this->faker->lastName();
    }
}
