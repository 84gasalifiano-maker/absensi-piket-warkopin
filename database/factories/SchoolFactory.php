<?php

namespace Database\Factories;

use App\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    /**
     * Jam buka/tutup memakai default kolom (SPEC F4).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => 'SMK '.fake()->city(),
        ];
    }
}
