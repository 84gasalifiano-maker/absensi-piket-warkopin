<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Aturan password baru (SPEC F1): minimal 8 karakter, berbeda dari
 * password lama, dan diketik dua kali (field password_baru_confirmation).
 */
class GantiPasswordRequest extends FormRequest
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
        return [
            // current_password: dicocokkan dengan password user yang sedang login.
            'password_lama' => ['required', 'string', 'current_password'],
            'password_baru' => ['required', 'string', 'min:8', 'confirmed', 'different:password_lama'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'password_lama.current_password' => 'Password lama salah.',
            'password_baru.different' => 'Password baru harus berbeda dari password lama.',
        ];
    }
}
