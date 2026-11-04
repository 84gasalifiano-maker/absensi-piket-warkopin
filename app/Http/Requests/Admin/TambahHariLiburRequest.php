<?php

namespace App\Http\Requests\Admin;

use App\Models\HariLibur;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Tambah hari libur oleh admin (SPEC F8, §8.1, v0.12). Akses admin dicek di route (role:admin).
 *
 * Aturan "tanggal yang sudah punya submit" sengaja TIDAK di sini, tapi di
 * HariLiburController::store, di dalam transaksi setelah baris sekolah dikunci,
 * supaya hanya ada satu tempat pengecekan (keputusan pemilik proyek, Langkah 6).
 */
class TambahHariLiburRequest extends FormRequest
{
    public const PESAN_TIDAK_VALID = 'Tanggal harus berupa tanggal yang valid.';

    public const PESAN_AKHIR_PEKAN = 'Hari libur hanya untuk Senin–Jumat. Sabtu dan Minggu memang tidak ada piket.';

    public const PESAN_SUDAH_TERDAFTAR = 'Tanggal ini sudah terdaftar sebagai hari libur.';

    /** Tahun yang dianggap valid (SPEC F8, v0.12). */
    public const TAHUN_MIN = 2000;

    public const TAHUN_MAKS = 2100;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // bail: berhenti di kesalahan pertama, jadi tiap tanggal mendapat satu pesan saja.
            'tanggal' => [
                'bail', 'required', 'string', 'date_format:Y-m-d',
                $this->tahunWajar(...),
                $this->bukanAkhirPekan(...),
                // Cek cepat untuk pesan biasa. Penyimpanan bersamaan ditangkap UNIQUE di database.
                $this->belumTerdaftar(...),
            ],
            'keterangan' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tanggal.string' => self::PESAN_TIDAK_VALID,
            'tanggal.date_format' => self::PESAN_TIDAK_VALID,
        ];
    }

    private function tahunWajar(string $atribut, string $nilai, Closure $gagal): void
    {
        // date_format:Y-m-d di atas sudah memastikan tahunnya tepat 4 digit (mis. 0001 lolos, 20261 tidak).
        $tahun = (int) substr($nilai, 0, 4);

        if ($tahun < self::TAHUN_MIN || $tahun > self::TAHUN_MAKS) {
            $gagal(self::PESAN_TIDAK_VALID);
        }
    }

    /** whereDate, bukan Rule::unique: lihat komentar cast tanggal di model HariLibur. */
    private function belumTerdaftar(string $atribut, string $nilai, Closure $gagal): void
    {
        $terdaftar = HariLibur::query()
            ->where('school_id', $this->user()->school_id)
            ->whereDate('tanggal', $nilai)
            ->exists();

        if ($terdaftar) {
            $gagal(self::PESAN_SUDAH_TERDAFTAR);
        }
    }

    private function bukanAkhirPekan(string $atribut, string $nilai, Closure $gagal): void
    {
        if (CarbonImmutable::createFromFormat('!Y-m-d', $nilai)->isWeekend()) {
            $gagal(self::PESAN_AKHIR_PEKAN);
        }
    }
}
