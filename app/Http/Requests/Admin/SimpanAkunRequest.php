<?php

namespace App\Http\Requests\Admin;

use App\Enums\Role;
use App\Models\Kelas;
use App\Models\School;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Tambah & ubah akun oleh admin (SPEC F9). Akses admin dicek di route (role:admin).
 *
 * - siswa/PJ: nama, NIS, kelas, no. absen (tanpa email);
 * - guru/admin: nama, email (tanpa NIS, kelas, no. absen);
 * - ubah role hanya siswa ↔ PJ; role guru/admin tidak bisa diubah;
 * - maks 2 PJ per kelas; pindah kelas yang menghapus jadwal butuh konfirmasi.
 */
class SimpanAkunRequest extends FormRequest
{
    public const PESAN_KONFIRMASI_PINDAH = 'Jadwal piket siswa ini di kelas lama akan dihapus. Centang "Ya, hapus jadwal lama" untuk melanjutkan.';

    public function authorize(): bool
    {
        return true;
    }

    /** Akun yang sedang diubah; null saat tambah akun. */
    public function akun(): ?User
    {
        return $this->route('akun');
    }

    /** Role akun setelah disimpan. Role guru/admin yang sudah ada tidak bisa diubah. */
    public function roleTujuan(): ?Role
    {
        $akun = $this->akun();

        if ($akun !== null && in_array($akun->role, Role::loginPakaiEmail(), true)) {
            return $akun->role;
        }

        // Isian berbentuk array (role[]=...) dianggap tidak dikenal, bukan error server.
        $role = $this->input('role');

        return is_string($role) ? Role::tryFrom($role) : null;
    }

    /**
     * true kalau akun ini siswa/PJ (login pakai NIS). Saat mengubah akun, jenisnya
     * diambil dari akun yang ada: siswa/PJ tidak bisa menjadi guru/admin, dan sebaliknya.
     */
    public function akunSiswa(): bool
    {
        $role = $this->akun()?->role ?? $this->roleTujuan();

        return in_array($role, Role::loginPakaiNis(), true);
    }

    /**
     * Kolom fillable yang disimpan, sesuai jenis akun. Kolom milik jenis lain dikosongkan.
     *
     * @return array<string, mixed>
     */
    public function dataAkun(): array
    {
        $data = $this->validated();

        if ($this->akunSiswa()) {
            return [
                'nama' => $data['nama'],
                'nis' => $data['nis'],
                'class_id' => (int) $data['class_id'],
                'no_absen' => (int) $data['no_absen'],
                'email' => null,
            ];
        }

        return [
            'nama' => $data['nama'],
            'email' => $data['email'],
            'nis' => null,
            'class_id' => null,
            'no_absen' => null,
        ];
    }

    /** Siswa diubah ke kelas lain padahal punya jadwal di kelas lamanya (SPEC F2, F9). */
    public function pindahKelasMenghapusJadwal(): bool
    {
        $akun = $this->akun();

        // Kelas kosong ditangani aturan "wajib diisi"; jangan tambah error konfirmasi.
        return $akun !== null
            && $this->akunSiswa()
            && $this->filled('class_id')
            && (int) $this->input('class_id') !== $akun->class_id
            && $akun->punyaJadwalDiKelasnya();
    }

    protected function prepareForValidation(): void
    {
        // Hanya teks yang dirapikan; isian berbentuk array dibiarkan supaya ditolak aturan 'string'.
        $teks = fn (string $kolom) => is_string($this->input($kolom)) ? trim($this->input($kolom)) : null;

        $this->merge(array_filter([
            'nama' => $teks('nama'),
            'nis' => $teks('nis'),
            // Login mengubah email ke huruf kecil (LoginRequest), jadi simpan huruf kecil juga.
            'email' => ($email = $teks('email')) !== null ? Str::lower($email) : null,
        ], fn ($nilai) => $nilai !== null));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $akun = $this->akun();
        $sekolahId = $this->user()->school_id;

        $rules = [
            'nama' => ['required', 'string', 'max:100'],
            'role' => [$this->roleTetap() ? 'nullable' : 'required', Rule::in($this->roleYangBoleh())],
        ];

        if ($this->akunSiswa()) {
            return $rules + [
                'nis' => ['required', 'string', 'max:30', 'alpha_num:ascii', Rule::unique('users', 'nis')->ignore($akun?->id)],
                'class_id' => ['bail', 'required', 'integer', Rule::exists('classes', 'id')->where('school_id', $sekolahId)],
                'no_absen' => [
                    'required', 'integer', 'min:1', 'max:255',
                    Rule::unique('users', 'no_absen')->where('class_id', (int) $this->input('class_id'))->ignore($akun?->id),
                ],
                'konfirmasi_pindah_kelas' => $this->pindahKelasMenghapusJadwal() ? ['accepted'] : ['nullable'],
            ];
        }

        return $rules + [
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($akun?->id)],
        ];
    }

    /**
     * Cek maks 2 PJ per kelas, setelah aturan dasar lolos.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            // Maks 2 admin per sekolah (dicek ulang dengan kunci di AkunController).
            function (Validator $validator) {
                if ($validator->errors()->isEmpty() && $this->akun() === null && $this->roleTujuan() === Role::Admin
                    && $this->user()->school->jumlahAdmin() >= School::MAKS_ADMIN) {
                    $validator->errors()->add('role', School::PESAN_ADMIN_PENUH);
                }
            },
            function (Validator $validator) {
                if ($validator->errors()->isNotEmpty() || $this->roleTujuan() !== Role::PjKebersihan) {
                    return;
                }

                // Dicek ulang dengan kunci di AkunController (dua simpan bersamaan).
                $kelas = Kelas::find((int) $this->input('class_id'));

                if ($kelas->jumlahPjSelain($this->akun()) >= Kelas::MAKS_PJ) {
                    $validator->errors()->add('role', $kelas->pesanPjPenuh());
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'role.in' => $this->akun() === null
                ? 'Role tidak dikenal.'
                : ($this->roleTetap()
                    ? 'Role guru/admin tidak bisa diubah.'
                    : 'Role hanya bisa diubah antara siswa dan PJ kebersihan.'),
            'class_id.exists' => 'Kelas harus kelas di sekolah ini.',
            'no_absen.unique' => 'No. absen ini sudah dipakai di kelas tersebut.',
            'konfirmasi_pindah_kelas.accepted' => self::PESAN_KONFIRMASI_PINDAH,
        ];
    }

    /** Akun guru/admin yang sudah ada: role tidak bisa diubah. */
    private function roleTetap(): bool
    {
        $akun = $this->akun();

        return $akun !== null && in_array($akun->role, Role::loginPakaiEmail(), true);
    }

    /**
     * @return list<string>
     */
    private function roleYangBoleh(): array
    {
        $akun = $this->akun();
        $nilai = fn (array $roles) => array_map(fn (Role $role) => $role->value, $roles);

        return match (true) {
            $akun === null => $nilai(Role::cases()),
            $this->roleTetap() => [$akun->role->value],
            default => $nilai(Role::loginPakaiNis()), // siswa ↔ PJ saja
        };
    }
}
