<?php

namespace App\Http\Requests\Admin;

use App\Models\School;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Simpan jam buka/tutup sekolah oleh admin (SPEC F4, §8.1, v0.13).
 * Akses admin dicek di route (role:admin).
 */
class SimpanPengaturanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        // JJ:MM 24 jam, tanpa detik. date_format:H:i menolak mis. 6:00, 24:00, 06:00:00.
        return array_fill_keys(School::KOLOM_JAM, ['bail', 'required', 'string', 'date_format:H:i']);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $pesan = ':attribute harus berupa jam yang valid, mis. 06:00.';

        return [
            '*.string' => $pesan,
            '*.date_format' => $pesan,
        ];
    }

    /**
     * Urutan ketat: buka pagi < tutup pagi < buka siang < tutup siang (SPEC F4).
     * Dicek hanya kalau keempat jam berformat benar. Teks JJ:MM yang sama panjang
     * bisa dibandingkan langsung sebagai teks. Pesan ditaruh di kolom yang salah.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $jam = $this->only(School::KOLOM_JAM);
            $urutan = [
                ['jam_buka_pagi', 'jam_tutup_pagi', 'Jam tutup pagi harus setelah jam buka pagi.'],
                ['jam_tutup_pagi', 'jam_buka_siang', 'Jam buka siang harus setelah jam tutup pagi.'],
                ['jam_buka_siang', 'jam_tutup_siang', 'Jam tutup siang harus setelah jam buka siang.'],
            ];

            foreach ($urutan as [$sebelum, $sesudah, $pesan]) {
                if ($jam[$sesudah] <= $jam[$sebelum]) {
                    $validator->errors()->add($sesudah, $pesan);
                }
            }
        }];
    }

    /**
     * Jam siap simpan: JJ:MM:00, sama di MySQL dan SQLite (SPEC §6).
     *
     * @return array<string, string>
     */
    public function jamUntukDisimpan(): array
    {
        return array_map(fn (string $jam) => $jam.':00', $this->safe()->only(School::KOLOM_JAM));
    }
}
