<?php

namespace Tests\Feature\Admin;

use App\Enums\Role;
use App\Enums\Tugas;
use App\Models\Kelas;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Data dasar tes halaman admin: satu sekolah, satu admin, satu kelas. */
abstract class AdminTestCase extends TestCase
{
    use RefreshDatabase;

    protected School $sekolah;

    protected User $admin;

    protected Kelas $kelas;

    private int $noAbsen = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sekolah = School::factory()->create();
        $this->admin = $this->buatUser(Role::Admin, ['school_id' => $this->sekolah->id, 'email' => 'admin@sekolah.test']);
        $this->kelas = $this->kelasBaru('XI RPL 1');
    }

    protected function kelasBaru(string $nama, ?School $sekolah = null): Kelas
    {
        return Kelas::factory()->for($sekolah ?? $this->sekolah, 'school')->create(['nama' => $nama]);
    }

    /** Siswa/PJ di kelas (default: $this->kelas) dengan no. absen berurutan. */
    protected function siswa(Role $role = Role::Siswa, ?Kelas $kelas = null): User
    {
        $kelas ??= $this->kelas;

        return $this->buatUser($role, [
            'school_id' => $kelas->school_id,
            'class_id' => $kelas->id,
            'no_absen' => ++$this->noAbsen,
        ]);
    }

    protected function guru(?School $sekolah = null): User
    {
        return $this->buatUser(Role::Guru, ['school_id' => ($sekolah ?? $this->sekolah)->id]);
    }

    protected function jadwal(User $siswa, ?Kelas $kelas = null): void
    {
        DB::table('duty_schedules')->insert([
            'class_id' => ($kelas ?? $this->kelas)->id,
            'user_id' => $siswa->id,
            'hari' => 1,
            'tugas_awal' => Tugas::Menyapu->value,
        ]);
    }

    protected function submit(User $siswa, Kelas $kelas): void
    {
        DB::table('submissions')->insert([
            'user_id' => $siswa->id,
            'class_id' => $kelas->id,
            'tanggal' => '2026-10-26',
            'tugas' => Tugas::Menyapu->value,
            'status' => 'ditolak',
            'created_at' => now(),
        ]);
    }

    /** Password sesuai aturan SPEC F9: 8 huruf kecil/angka tanpa 0 o 1 l i, ada huruf & angka. */
    protected function assertPasswordAcakSesuaiAturan(string $password): void
    {
        $this->assertMatchesRegularExpression('/^[a-hjkmnp-z2-9]{8}$/', $password);
        $this->assertMatchesRegularExpression('/[a-z]/', $password);
        $this->assertMatchesRegularExpression('/[0-9]/', $password);
    }
}
