<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Default: akun siswa (login pakai NIS, tanpa email) tanpa kelas.
     * Set class_id & no_absen lewat atribut bila perlu.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'class_id' => null,
            'nama' => fake()->name(),
            'email' => null,
            'nis' => fake()->unique()->numerify('########'),
            'no_absen' => null,
            'password' => static::$password ??= Hash::make('password'),
            'role' => Role::Siswa,
            'harus_ganti_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Akun dengan role tertentu.
     * Guru & admin login pakai email: tanpa NIS, kelas, dan no. absen.
     */
    public function role(Role $role): static
    {
        return $this->state(function (array $attributes) use ($role) {
            if (! in_array($role, [Role::Guru, Role::Admin], true)) {
                return ['role' => $role];
            }

            return [
                'role' => $role,
                'email' => fake()->unique()->safeEmail(),
                'nis' => null,
                'class_id' => null,
                'no_absen' => null,
            ];
        });
    }

    /**
     * Akun yang wajib ganti password saat login pertama.
     */
    public function harusGantiPassword(): static
    {
        return $this->state(['harus_ganti_password' => true]);
    }
}
