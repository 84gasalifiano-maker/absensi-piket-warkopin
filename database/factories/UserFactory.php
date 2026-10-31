<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * Mengikuti aturan SPEC F1:
 * - siswa & PJ login pakai NIS (tanpa email), guru & admin pakai email (tanpa NIS);
 * - akun baru wajib ganti password saat login pertama;
 * - "Ingat saya" default tidak dicentang, jadi akun baru belum punya remember_token.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Default: akun siswa baru tanpa kelas.
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
            'harus_ganti_password' => true,
            'remember_token' => null,
        ];
    }

    /**
     * Akun dengan role tertentu, lengkap dengan identitas login yang sesuai.
     */
    public function role(Role $role): static
    {
        return $this->state(function (array $attributes) use ($role) {
            // Guru & admin: login pakai email; tanpa NIS, kelas, dan no. absen.
            if (in_array($role, [Role::Guru, Role::Admin], true)) {
                return [
                    'role' => $role,
                    'email' => $attributes['email'] ?? fake()->unique()->safeEmail(),
                    'nis' => null,
                    'class_id' => null,
                    'no_absen' => null,
                ];
            }

            // Siswa & PJ: login pakai NIS; tanpa email.
            return [
                'role' => $role,
                'email' => null,
                'nis' => $attributes['nis'] ?? fake()->unique()->numerify('########'),
            ];
        });
    }

    /**
     * Akun yang sudah mengganti password (tidak dipaksa ganti lagi saat login).
     */
    public function sudahGantiPassword(): static
    {
        return $this->state(['harus_ganti_password' => false]);
    }
}
