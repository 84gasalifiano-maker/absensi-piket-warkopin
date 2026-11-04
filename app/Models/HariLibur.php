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
            // Cast 'date' menyimpan dengan format "Y-m-d H:i:s": di MySQL kolom DATE membuang
            // jamnya, tapi di SQLite (tes) tersimpan "2026-11-11 00:00:00". Karena itu pencarian
            // per tanggal memakai whereDate(), supaya sama di MySQL dan SQLite (Langkah 6).
            // Sengaja bukan 'date:Y-m-d': dengan format khusus, Laravel berhenti menormalkan
            // nilai dan menyimpannya apa adanya (Carbon berjam tersimpan dengan jamnya).
            'tanggal' => 'date',
        ];
    }

    /** "Rabu, 11 Nov 2026" (SPEC §8.1, v0.12). */
    public function labelTanggal(): string
    {
        return $this->tanggal->translatedFormat('l, j M Y');
    }

    /** Keterangan bisa kosong di data lama (kolom database boleh kosong, F8). */
    public function adaKeterangan(): bool
    {
        return filled($this->keterangan);
    }

    /**
     * Teks konfirmasi hapus (SPEC §8.1, v0.12). Tanpa tanda kurung kalau keterangan
     * kosong. Teks polos: di view di-escape Blade, dan app.js menampilkannya lewat
     * window.confirm (bukan sebagai HTML).
     */
    public function pesanKonfirmasiHapus(): string
    {
        return 'Hapus hari libur '.$this->labelTanggal().($this->adaKeterangan() ? " ({$this->keterangan})" : '').'?';
    }

    /** @return BelongsTo<School, $this> */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
