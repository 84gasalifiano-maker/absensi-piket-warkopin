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
}
