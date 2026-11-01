<?php

namespace App\Enums;

enum Role: string
{
    case Siswa = 'siswa';
    case PjKebersihan = 'pj_kebersihan';
    case Guru = 'guru';
    case Admin = 'admin';

    /**
     * Role yang login pakai NIS (SPEC F1).
     *
     * @return list<self>
     */
    public static function loginPakaiNis(): array
    {
        return [self::Siswa, self::PjKebersihan];
    }

    /**
     * Role yang login pakai email (SPEC F1).
     *
     * @return list<self>
     */
    public static function loginPakaiEmail(): array
    {
        return [self::Guru, self::Admin];
    }

    /**
     * Pilihan dropdown [nilai => label].
     *
     * @param  list<self>|null  $roles  default: semua role
     * @return array<string, string>
     */
    public static function opsi(?array $roles = null): array
    {
        $hasil = [];
        foreach ($roles ?? self::cases() as $role) {
            $hasil[$role->value] = $role->label();
        }

        return $hasil;
    }

    /** Label tampilan. */
    public function label(): string
    {
        return match ($this) {
            self::Siswa => 'Siswa',
            self::PjKebersihan => 'PJ kebersihan',
            self::Guru => 'Guru',
            self::Admin => 'Admin',
        };
    }

    /**
     * Nama route tujuan setelah login (SPEC F1).
     */
    public function halamanAwal(): string
    {
        return match ($this) {
            self::Siswa, self::PjKebersihan => 'beranda',
            self::Guru => 'verifikasi',
            self::Admin => 'admin',
        };
    }
}
