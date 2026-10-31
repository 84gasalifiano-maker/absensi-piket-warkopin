<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Akun contoh memakai password yang mudah ditebak,
        // jadi hanya boleh dibuat di lingkungan lokal.
        if (! app()->environment('local')) {
            $this->command?->warn('DataContohSeeder dilewati: hanya untuk APP_ENV=local.');

            return;
        }

        $this->call(DataContohSeeder::class);
    }
}
