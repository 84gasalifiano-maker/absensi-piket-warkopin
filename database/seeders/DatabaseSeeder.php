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
        // DataContohSeeder sendiri yang menolak jalan di luar APP_ENV=local.
        $this->call(DataContohSeeder::class);
    }
}
