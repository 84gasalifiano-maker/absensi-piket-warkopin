<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

// role, harus_ganti_password, dan school_id sengaja tidak fillable: hanya boleh
// diisi eksplisit oleh kode admin (forceFill), bukan dari input form (SPEC §6).
// class_id fillable karena memang dipilih admin di form akun, setelah divalidasi
// harus kelas di sekolah admin itu.
#[Fillable(['class_id', 'nama', 'email', 'nis', 'no_absen', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Akun baru wajib ganti password saat login pertama (SPEC F1).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'harus_ganti_password' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'role' => Role::class,
            'harus_ganti_password' => 'boolean',
            'no_absen' => 'integer',
            'class_id' => 'integer', // dibandingkan saat pindah kelas (SPEC F9)
        ];
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Kelas tempat siswa/PJ terdaftar (null untuk guru & admin).
     * Untuk guru, pakai kelasDiwalikan().
     *
     * @return BelongsTo<Kelas, $this>
     */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'class_id');
    }

    /**
     * Kelas yang diwalikan guru ini (bisa lebih dari satu).
     * Ini satu-satunya sumber "kelas guru"; users.class_id guru selalu null.
     *
     * @return HasMany<Kelas, $this>
     */
    public function kelasDiwalikan(): HasMany
    {
        return $this->hasMany(Kelas::class, 'wali_kelas_id');
    }

    /** @return HasMany<DutySchedule, $this> */
    public function dutySchedules(): HasMany
    {
        return $this->hasMany(DutySchedule::class);
    }

    /** Siswa/PJ ini punya jadwal piket di kelasnya sekarang (dipakai saat pindah kelas, SPEC F9). */
    public function punyaJadwalDiKelasnya(): bool
    {
        return $this->class_id !== null
            && $this->dutySchedules()->where('class_id', $this->class_id)->exists();
    }

    /** @return HasMany<Submission, $this> */
    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }
}
