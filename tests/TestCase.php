<?php

namespace Tests;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tes tidak bergantung pada hasil `npm run build`.
        $this->withoutVite();
    }

    /**
     * Akun dengan role tertentu yang sudah ganti password (tidak dipaksa ke /ganti-password).
     *
     * @param  array<string, mixed>  $atribut
     */
    protected function buatUser(Role $role, array $atribut = []): User
    {
        return User::factory()->role($role)->sudahGantiPassword()->create($atribut);
    }
}
