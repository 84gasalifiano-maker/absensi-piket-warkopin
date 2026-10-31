<?php

namespace App\Models;

use App\Enums\SubmissionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'class_id', 'tanggal', 'catatan', 'terlambat', 'alasan_terlambat'])]
class Submission extends Model
{
    /** Tabel ini hanya punya created_at (waktu server saat submit). */
    public const UPDATED_AT = null;

    /**
     * Nilai awal submit baru (SPEC F3): menunggu verifikasi, tidak terlambat.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => SubmissionStatus::MenungguVerifikasi->value,
        'terlambat' => false,
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
            'status' => SubmissionStatus::class,
            'terlambat' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Kelas, $this> */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'class_id');
    }

    /** @return BelongsTo<User, $this> */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /** @return HasMany<SubmissionPhoto, $this> */
    public function photos(): HasMany
    {
        return $this->hasMany(SubmissionPhoto::class);
    }
}
