<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Hari libur sekolah (SPEC F8). Satu tanggal satu baris per sekolah.
 *
 * school_id sengaja tidak fillable: diisi dari sekolah admin yang login,
 * lewat $school->hariLibur()->create([...]).
 */
#[Table(name: 'hari_libur')]
#[Fillable(['tanggal', 'keterangan'])]
class HariLibur extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
