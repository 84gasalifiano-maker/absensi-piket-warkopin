<?php

namespace App\Http\Requests\Auth;

use App\Enums\Role;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Login satu kolom "NIS atau email" (SPEC F1).
 */
class LoginRequest extends FormRequest
{
    /** Maksimal percobaan gagal per menit per NIS/email + IP (SPEC F1). */
    private const MAKS_PERCOBAAN = 5;

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
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Coba login. Gagal → pesan yang sama untuk semua kasus (NIS tidak ada,
     * password salah, atau jenis login tidak cocok dengan role), supaya
     * orang luar tidak bisa menebak NIS/email mana yang terdaftar.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->pastikanTidakDiblokir();

        if (! Auth::attempt($this->kredensial(), $this->boolean('remember'))) {
            RateLimiter::hit($this->kunciBatas(), 60);

            throw ValidationException::withMessages([
                'login' => __('auth.failed'),
            ]);
        }

        RateLimiter::clear($this->kunciBatas());
    }

    /**
     * Isian dengan "@" dianggap email dan hanya cocok untuk guru/admin.
     * Selain itu dianggap NIS dan hanya cocok untuk siswa/PJ.
     *
     * @return array<string, mixed>
     */
    private function kredensial(): array
    {
        $login = trim($this->string('login')->toString());
        $password = $this->string('password')->toString();
        $nilai = fn (array $roles) => array_map(fn (Role $role) => $role->value, $roles);

        // Array di kredensial dicocokkan dengan WHERE role IN (...).
        if (str_contains($login, '@')) {
            return ['email' => Str::lower($login), 'role' => $nilai(Role::loginPakaiEmail()), 'password' => $password];
        }

        return ['nis' => $login, 'role' => $nilai(Role::loginPakaiNis()), 'password' => $password];
    }

    /**
     * @throws ValidationException
     */
    private function pastikanTidakDiblokir(): void
    {
        if (! RateLimiter::tooManyAttempts($this->kunciBatas(), self::MAKS_PERCOBAAN)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'login' => __('auth.throttle', [
                'seconds' => RateLimiter::availableIn($this->kunciBatas()),
            ]),
        ]);
    }

    /** Kunci penghitung percobaan: NIS/email + IP (SPEC F1). */
    private function kunciBatas(): string
    {
        return Str::transliterate(Str::lower(trim($this->string('login')->toString()))).'|'.$this->ip();
    }
}
