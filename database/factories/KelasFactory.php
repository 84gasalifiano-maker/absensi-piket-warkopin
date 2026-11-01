<?php

namespace Database\Factories;

use App\Models\Kelas;
use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Kelas>
 */
class KelasFactory extends Factory
{
    protected $model = Kelas::class;

    /**
     * Default: kelas tanpa wali, rotasi mulai Senin 26 Okt 2026.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'nama' => 'XI '.fake()->unique()->bothify('???-#'),
            'wali_kelas_id' => null,
            'tanggal_mulai_rotasi' => '2026-10-26',
        ];
    }
}
