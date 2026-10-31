<?php

namespace Tests\Feature;

use App\Enums\Tugas;
use Tests\TestCase;

/** SPEC §6: timezone aplikasi Asia/Jakarta, dipakai juga oleh rotasi tugas. */
class ZonaWaktuTest extends TestCase
{
    public function test_zona_rotasi_sama_dengan_zona_aplikasi(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame(config('app.timezone'), Tugas::ZONA_WAKTU);
    }
}
