<?php

namespace App\Models;

use Database\Factories\SchoolFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['nama', 'jam_buka_pagi', 'jam_tutup_pagi', 'jam_buka_siang', 'jam_tutup_siang'])]
#[WithoutTimestamps]
class School extends Model
{
    /** @use HasFactory<SchoolFactory> */
    use HasFactory;

    /** Maks admin per sekolah (SPEC F9, v0.9). */
    public const MAKS_ADMIN = 2;

    public const PESAN_ADMIN_PENUH = 'Admin sudah mencapai batas maksimal ('.self::MAKS_ADMIN.').';

    /** Jam jendela tepat waktu, urut dari yang paling awal (SPEC F4). */
    public const KOLOM_JAM = ['jam_buka_pagi', 'jam_tutup_pagi', 'jam_buka_siang', 'jam_tutup_siang'];

    /** Jam untuk form, tanpa detik: "06:00:00" → "06:00" (SPEC F4, v0.13). */
    public function jamTanpaDetik(string $kolom): string
    {
        return substr((string) $this->getAttribute($kolom), 0, 5);
    }

    public function jumlahAdmin(): int
    {
        return $this->users()->where('role', \App\Enums\Role::Admin)->count();
    }

    /** @return HasMany<Kelas, $this> */
    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class);
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** @return HasMany<HariLibur, $this> */
    public function hariLibur(): HasMany
    {
        return $this->hasMany(HariLibur::class);
    }
}
