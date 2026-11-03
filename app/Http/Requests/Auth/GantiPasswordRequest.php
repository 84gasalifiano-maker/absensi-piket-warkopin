<?php

namespace App\Http\Requests\Auth;

use App\Support\LoginDenganPassword;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Validator;

/**
 * Aturan password baru (SPEC F1): minimal 8 karakter, berbeda dari
 * password saat ini, dan diketik dua kali (field password_baru_confirmation).
 *
 * Password lama (v0.11): TIDAK diminta saat ganti wajib di sesi yang login dengan
 * mengetik password. Ini ditentukan server saat form dikirim (harus_ganti_password
 * dari database + tanda sesi), bukan dari isian form. Kalau tidak diminta,
 * isian password_lama yang tetap dikirim diabaikan (tidak ada aturannya, jadi
 * tidak dicek dan tidak ikut validated()).
 */
class GantiPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    private function perluPasswordLama(): bool
    {
        return ! LoginDenganPassword::bolehTanpaPasswordLama($this);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $aturan = [
            // "Berbeda dari password saat ini" dicek di after(), setelah aturan ini.
            'password_baru' => ['required', 'string', 'min:8', 'confirmed'],
        ];

        if ($this->perluPasswordLama()) {
            // current_password: dicocokkan dengan password user yang sedang login.
            $aturan['password_lama'] = ['required', 'string', 'current_password'];
        }

        return $aturan;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password_lama.current_password' => 'Password lama salah.',
        ];
    }

    /**
     * Password baru harus berbeda dari password saat ini, dicek terhadap hash yang
     * tersimpan (bukan terhadap isian form), karena saat ganti wajib password lama
     * tidak diketik (SPEC F1).
     *
     * Hanya dicek kalau aturan lain sudah lolos, terutama password lama yang benar.
     * Kalau tidak, pesan ini membocorkan apakah tebakan di "password baru" adalah
     * password saat ini: satu percobaan = dua tebakan, melemahkan batas 5 kali per menit.
     *
     * @return list<Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if (! Hash::check((string) $this->input('password_baru'), $this->user()->getAuthPassword())) {
                return;
            }

            // Semua ganti wajib memakai teks "dari admin" (v0.11, disetujui pemilik proyek).
            $validator->errors()->add('password_baru', $this->user()->harus_ganti_password
                ? 'Password baru tidak boleh sama dengan password dari admin.'
                : 'Password baru harus berbeda dari password lama.');
        }];
    }
}
