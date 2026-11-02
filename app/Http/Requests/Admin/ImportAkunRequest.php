<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Upload file import akun CSV (SPEC F9, v0.10). Akses admin dicek di route (role:admin).
 * Di sini hanya file-nya (wajib, .csv, maks 1 MB); isi file dicek di App\Actions\ImportAkun.
 *
 * Tipe file tidak dicek dari "mime type": CSV sering terbaca text/plain atau
 * application/vnd.ms-excel tergantung komputer. Isinya yang dicek (harus teks UTF-8
 * dengan baris judul yang benar), jadi file lain tetap ditolak.
 */
class ImportAkunRequest extends FormRequest
{
    public const MAKS_KB = 1024;

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
            'file' => ['bail', 'required', 'file', 'extensions:csv', 'max:'.self::MAKS_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'Pilih file CSV yang akan diimport.',
            'file.file' => 'Pilih file CSV yang akan diimport.',
            'file.uploaded' => 'File gagal diupload. Coba lagi.',
            'file.extensions' => 'File harus berformat .csv.',
            'file.max' => 'Ukuran file maks 1 MB.',
        ];
    }
}
