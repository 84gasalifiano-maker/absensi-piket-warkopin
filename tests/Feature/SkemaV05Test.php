<?php

namespace Tests\Feature;

use App\Enums\Role;
use App\Enums\Tugas;
use App\Models\DutySchedule;
use App\Models\HariLibur;
use App\Models\Kelas;
use App\Models\School;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** SPEC §11 Langkah 3: perubahan model data v0.5/v0.6 (§6). */
class SkemaV05Test extends TestCase
{
    use RefreshDatabase;

    private School $school;

    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();

        $this->school = School::factory()->create();
        $this->kelas = $this->school->kelas()->create([
            'nama' => 'XI RPL 1',
            'tanggal_mulai_rotasi' => '2026-11-02',
        ]);
    }

    private function siswa(): User
    {
        return $this->buatUser(Role::Siswa, ['school_id' => $this->school->id, 'class_id' => $this->kelas->id]);
    }

    /** @param  array<string, mixed>  $atribut */
    private function jadwal(User $siswa, array $atribut = []): DutySchedule
    {
        return DutySchedule::create([
            'class_id' => $this->kelas->id,
            'user_id' => $siswa->id,
            'hari' => 1,
            'tugas_awal' => Tugas::Menyapu,
            ...$atribut,
        ]);
    }

    public function test_siswa_hanya_boleh_satu_jadwal_per_kelas(): void
    {
        $siswa = $this->siswa();
        $this->jadwal($siswa, ['hari' => 1]);

        // Hari lain pun ditolak: setiap siswa tepat satu hari (F2).
        $this->expectException(QueryException::class);
        $this->jadwal($siswa, ['hari' => 3]);
    }

    public function test_banyak_siswa_boleh_di_hari_yang_sama(): void
    {
        $this->jadwal($this->siswa(), ['hari' => 2]);
        $this->jadwal($this->siswa(), ['hari' => 2, 'tugas_awal' => Tugas::Mengepel]);

        $this->assertSame(2, DutySchedule::where('hari', 2)->count());
    }

    public function test_tugas_awal_wajib_diisi(): void
    {
        $this->expectException(QueryException::class);
        DB::table('duty_schedules')->insert(['class_id' => $this->kelas->id, 'user_id' => $this->siswa()->id, 'hari' => 1]);
    }

    public function test_tugas_awal_hanya_tiga_nilai(): void
    {
        $this->expectException(QueryException::class);
        DB::table('duty_schedules')->insert([
            'class_id' => $this->kelas->id, 'user_id' => $this->siswa()->id, 'hari' => 1, 'tugas_awal' => 'menyanyi',
        ]);
    }

    public function test_tugas_awal_dibaca_sebagai_enum(): void
    {
        $jadwal = $this->jadwal($this->siswa(), ['tugas_awal' => Tugas::Merapikan]);

        $this->assertSame(Tugas::Merapikan, $jadwal->fresh()->tugas_awal);
        $this->assertSame('Merapikan meja & kursi', $jadwal->fresh()->tugas_awal->label());
    }

    public function test_hari_enam_ditolak_database(): void
    {
        if (! in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->markTestSkipped('CHECK hari 1–5 hanya ada di MySQL/MariaDB (SPEC §10); dicek manual di MySQL.');
        }

        $this->expectException(QueryException::class);
        $this->jadwal($this->siswa(), ['hari' => 6]);
    }

    public function test_tanggal_mulai_rotasi_wajib_diisi(): void
    {
        $this->expectException(QueryException::class);
        DB::table('classes')->insert(['school_id' => $this->school->id, 'nama' => 'XI RPL 9']);
    }

    public function test_tanggal_mulai_rotasi_dibaca_sebagai_tanggal(): void
    {
        $this->assertSame('2026-11-02', $this->kelas->fresh()->tanggal_mulai_rotasi->toDateString());
    }

    public function test_tugas_submit_wajib_diisi(): void
    {
        $this->expectException(QueryException::class);
        DB::table('submissions')->insert([
            'user_id' => $this->siswa()->id, 'class_id' => $this->kelas->id, 'tanggal' => '2026-11-02',
        ]);
    }

    public function test_tugas_submit_tidak_bisa_diisi_lewat_mass_assignment(): void
    {
        $this->assertNotContains('tugas', (new Submission)->getFillable());
    }

    public function test_hari_libur_unik_per_sekolah_dan_tanggal(): void
    {
        $this->school->hariLibur()->create(['tanggal' => '2026-12-25', 'keterangan' => 'Natal']);

        // Sekolah lain boleh libur di tanggal yang sama.
        School::factory()->create()->hariLibur()->create(['tanggal' => '2026-12-25']);
        $this->assertSame(2, HariLibur::count());

        $this->expectException(QueryException::class);
        $this->school->hariLibur()->create(['tanggal' => '2026-12-25', 'keterangan' => 'Dobel']);
    }

    public function test_school_id_hari_libur_tidak_bisa_diisi_lewat_mass_assignment(): void
    {
        $this->assertSame(['tanggal', 'keterangan'], (new HariLibur)->getFillable());
    }
}
