<?php

namespace App\Models;

use App\Enums\Tugas;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Jadwal piket: satu siswa tepat satu hari per kelas (SPEC F2),
 * dengan tugas di minggu pertama rotasi (tugas_awal).
 */
#[Fillable(['class_id', 'hari', 'user_id', 'tugas_awal'])]
#[WithoutTimestamps]
class DutySchedule extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'hari' => 'integer', // 1 = Senin … 5 = Jumat
            'tugas_awal' => Tugas::class,
        ];
    }

    /** @return BelongsTo<Kelas, $this> */
    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'class_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
