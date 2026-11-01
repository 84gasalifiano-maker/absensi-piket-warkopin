<?php

namespace Tests\Feature;

use App\Models\Kelas;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** SPEC §6 (v0.8): nama kelas unik per sekolah; school_id tidak bisa diisi lewat mass assignment. */
class SkemaLangkah4Test extends TestCase
{
    use RefreshDatabase;

    public function test_database_menolak_nama_kelas_kembar_di_satu_sekolah(): void
    {
        $sekolah = School::factory()->create();
        $baris = ['school_id' => $sekolah->id, 'nama' => 'XI RPL 1', 'tanggal_mulai_rotasi' => '2026-10-26'];
        DB::table('classes')->insert($baris);

        // Sekolah lain boleh memakai nama yang sama.
        DB::table('classes')->insert(['school_id' => School::factory()->create()->id] + $baris);

        $this->expectException(QueryException::class);
        DB::table('classes')->insert($baris);
    }

    public function test_school_id_tidak_fillable(): void
    {
        $this->assertNotContains('school_id', (new Kelas)->getFillable());
        $this->assertNotContains('school_id', (new User)->getFillable());
        $this->assertNotContains('role', (new User)->getFillable());
        $this->assertNotContains('harus_ganti_password', (new User)->getFillable());
    }
}
