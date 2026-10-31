<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabel `classes`. Nama model "Kelas" karena `Class` adalah kata terlarang di PHP.
 */
#[Table(name: 'classes', timestamps: false)]
#[Fillable(['school_id', 'nama', 'wali_kelas_id'])]
class Kelas extends Model
{
    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /** @return BelongsTo<User, $this> */
    public function waliKelas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wali_kelas_id');
    }

    /**
     * Semua anggota kelas (siswa & PJ kebersihan).
     *
     * @return HasMany<User, $this>
     */
    public function anggota(): HasMany
    {
        return $this->hasMany(User::class, 'class_id')
            ->whereIn('role', [Role::Siswa, Role::PjKebersihan])
            ->orderBy('no_absen');
    }

    /** @return HasMany<DutySchedule, $this> */
    public function dutySchedules(): HasMany
    {
        return $this->hasMany(DutySchedule::class, 'class_id');
    }

    /** @return HasMany<Submission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class, 'class_id');
    }
}
