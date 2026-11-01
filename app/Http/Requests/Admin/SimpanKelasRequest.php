<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\Kelas;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Tambah & ubah kelas oleh admin (SPEC F9). Akses admin dicek di route (role:admin).
 */
class SimpanKelasRequest extends FormRequest
{
    public const PESAN_BUKAN_SENIN = 'Tanggal mulai rotasi harus hari Senin.';

    public const PESAN_TERKUNCI = 'Tanggal mulai rotasi tidak bisa diubah karena kelas ini sudah punya riwayat piket.';

    public const PESAN_WALI = 'Wali kelas harus akun guru di sekolah ini.';

    public function authorize(): bool
    {
        return true;
    }

    /** Kelas yang sedang diubah; null saat tambah kelas. */
    public function kelas(): ?Kelas
    {
        return $this->route('kelas');
    }

    /** tanggal_mulai_rotasi terkunci kalau kelas sudah punya submit (SPEC F2). */
    public function tanggalTerkunci(): bool
    {
        return $this->kelas()?->tanggalMulaiTerkunci() ?? false;
    }

    protected function prepareForValidation(): void
    {
        // Hanya teks yang dirapikan; isian berbentuk array dibiarkan supaya ditolak aturan 'string'.
        if (is_string($this->input('nama'))) {
            $this->merge(['nama' => trim($this->input('nama'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $sekolahId = $this->user()->school_id;

        return [
            'nama' => [
                'required', 'string', 'max:50',
                Rule::unique('classes', 'nama')->where('school_id', $sekolahId)->ignore($this->kelas()?->id),
            ],
            // Boleh kosong; kalau diisi harus guru di sekolah ini.
            'wali_kelas_id' => [
                'nullable', 'integer',
                Rule::exists('users', 'id')->where('school_id', $sekolahId)->where('role', Role::Guru->value),
            ],
            'tanggal_mulai_rotasi' => $this->tanggalTerkunci()
                ? ['nullable', $this->tolakPerubahanTanggal(...)]
                : ['bail', 'required', 'date_format:Y-m-d', $this->harusSenin(...)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'nama.unique' => 'Nama kelas sudah dipakai di sekolah ini.',
            'wali_kelas_id.exists' => self::PESAN_WALI,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nama' => 'Nama kelas'];
    }

    private function harusSenin(string $atribut, mixed $nilai, Closure $gagal): void
    {
        if (! CarbonImmutable::createFromFormat('!Y-m-d', (string) $nilai)->isMonday()) {
            $gagal(self::PESAN_BUKAN_SENIN);
        }
    }

    /** Kolom terkunci tampil nonaktif di form; kalau tetap dikirim dengan nilai lain, tolak. */
    private function tolakPerubahanTanggal(string $atribut, mixed $nilai, Closure $gagal): void
    {
        if ($nilai !== null && $nilai !== $this->kelas()->tanggal_mulai_rotasi->toDateString()) {
            $gagal(self::PESAN_TERKUNCI);
        }
    }
}
