<?php

namespace App\Http\Requests;

use App\Actions\SimpanJadwal;
use App\Enums\Tugas;
use App\Support\MingguAcuan;
use App\Support\RingkasanJadwal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Simpan jadwal piket satu kelas sekaligus (SPEC F2, §7, v0.15).
 *
 * Isian: minggu_acuan (Senin minggu saat form ditampilkan), dan per siswa
 * jadwal[{user_id}][hari] (kosong = belum terjadwal, atau 1–5) +
 * jadwal[{user_id}][tugas] (tugas di minggu acuan). Kelas & hak akses dicek
 * di JadwalController + SimpanJadwal (di dalam transaksi), bukan di sini.
 */
class SimpanJadwalRequest extends FormRequest
{
    public const PESAN_HARI = 'Hari piket hanya Senin–Jumat.';

    public const PESAN_TUGAS_KOSONG = 'Tugas wajib dipilih.';

    public const PESAN_TUGAS_TIDAK_VALID = 'Tugas tidak valid.';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Isian per siswa dicek di after(): aturan "tugas diabaikan kalau hari kosong"
     * lebih jelas ditulis langsung daripada lewat aturan bawaan bersyarat.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'jadwal' => ['sometimes', 'array'],
        ];
    }

    /**
     * `jadwal` yang bukan daftar hanya datang dari kiriman yang bukan form ini.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['jadwal.array' => SimpanJadwal::PESAN_ANGGOTA_BERUBAH];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if (MingguAcuan::dariIsian($this->input('minggu_acuan')) === null) {
                $validator->errors()->add('minggu_acuan', MingguAcuan::PESAN_TIDAK_VALID);
            }

            if ($validator->errors()->has('jadwal')) {
                return;
            }

            foreach ($this->input('jadwal', []) as $userId => $baris) {
                // Kunci harus id akun dan isinya array; selain itu bukan kiriman form ini.
                if (! ctype_digit((string) $userId) || ! is_array($baris)) {
                    $validator->errors()->add('jadwal', SimpanJadwal::PESAN_ANGGOTA_BERUBAH);

                    return;
                }

                $hari = $baris['hari'] ?? null;
                $tugas = $baris['tugas'] ?? null;

                if ($hari === null || $hari === '') {
                    continue; // belum terjadwal; tugas diabaikan (SPEC F2)
                }

                // "1" … "5" (atau angka 1–5); kunci string angka di PHP sama dengan kunci angka.
                if (! (is_int($hari) || is_string($hari)) || ! array_key_exists((string) $hari, RingkasanJadwal::HARI)) {
                    $validator->errors()->add("jadwal.{$userId}.hari", self::PESAN_HARI);
                } elseif ($tugas === null || $tugas === '') {
                    $validator->errors()->add("jadwal.{$userId}.tugas", self::PESAN_TUGAS_KOSONG);
                } elseif (! is_string($tugas) || Tugas::tryFrom($tugas) === null) {
                    $validator->errors()->add("jadwal.{$userId}.tugas", self::PESAN_TUGAS_TIDAK_VALID);
                }
            }
        }];
    }

    /** Hanya dipanggil setelah validasi lolos. */
    public function mingguAcuan(): CarbonImmutable
    {
        return MingguAcuan::dariIsian($this->input('minggu_acuan'));
    }

    /**
     * Pilihan per siswa yang ada di form. Hanya dipanggil setelah validasi lolos.
     *
     * @return array<int, array{hari: int|null, tugas: Tugas|null}>
     */
    public function isian(): array
    {
        $hasil = [];

        foreach ($this->input('jadwal', []) as $userId => $baris) {
            $hari = $baris['hari'] ?? null;
            $terjadwal = $hari !== null && $hari !== '';

            $hasil[(int) $userId] = [
                'hari' => $terjadwal ? (int) $hari : null,
                'tugas' => $terjadwal ? Tugas::from($baris['tugas']) : null,
            ];
        }

        return $hasil;
    }
}
