<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\KelasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Tabel `classes`. Nama model "Kelas" karena `Class` adalah kata terlarang di PHP.
 *
 * school_id sengaja tidak fillable (SPEC §6): buat kelas lewat
 * $school->kelas()->create([...]) supaya selalu masuk sekolah admin yang login.
 */
#[Table(name: 'classes', timestamps: false)]
#[Fillable(['nama', 'wali_kelas_id', 'tanggal_mulai_rotasi'])]
class Kelas extends Model
{
    /** @use HasFactory<KelasFactory> */
    use HasFactory;

    /** Maks PJ kebersihan per kelas (SPEC F9). */
    public const MAKS_PJ = 2;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            // Titik awal hitungan rotasi tugas mingguan (SPEC F2). Wajib diisi, hari Senin.
            'tanggal_mulai_rotasi' => 'date',
        ];
    }

    /**
     * tanggal_mulai_rotasi terkunci setelah kelas punya submit apa pun (SPEC F2, v0.8).
     */
    public function tanggalMulaiTerkunci(): bool
    {
        return $this->submissions()->exists();
    }

    /** Jumlah PJ kebersihan di kelas ini, tanpa menghitung $kecuali (akun yang sedang diubah). */
    public function jumlahPjSelain(?User $kecuali): int
    {
        return $this->pjKebersihan()
            ->when($kecuali, fn ($q, User $akun) => $q->whereKeyNot($akun->id))
            ->count();
    }

    public function pesanPjPenuh(): string
    {
        return "Kelas {$this->nama} sudah punya ".self::MAKS_PJ.' PJ kebersihan.';
    }

    /**
     * Kelas boleh dihapus hanya kalau kosong: tanpa siswa, jadwal, dan submit (SPEC F9).
     */
    public function bolehDihapus(): bool
    {
        return ! $this->hasMany(User::class, 'class_id')->exists()
            && ! $this->dutySchedules()->exists()
            && ! $this->submissions()->exists();
    }

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

    /**
     * Akun yang ikut reset massal (SPEC F9, v0.11): siswa & PJ kelas ini yang
     * belum pernah mengganti password. Guru/admin tidak punya kelas, jadi tidak ikut.
     *
     * @return HasMany<User, $this>
     */
    public function akunBelumGantiPassword(): HasMany
    {
        return $this->hasMany(User::class, 'class_id')
            ->where('school_id', $this->school_id) // pengaman tambahan: akun sekolah kelas ini saja
            ->whereIn('role', [Role::Siswa, Role::PjKebersihan])
            ->where('harus_ganti_password', true);
    }

    /** @return HasMany<User, $this> */
    public function pjKebersihan(): HasMany
    {
        return $this->hasMany(User::class, 'class_id')
            ->where('role', Role::PjKebersihan)
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
