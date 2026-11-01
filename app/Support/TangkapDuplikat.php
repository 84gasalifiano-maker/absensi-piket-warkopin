<?php

namespace App\Support;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Isian kembar yang lolos validasi (dua penyimpanan bersamaan) lalu ditolak
 * database lewat UNIQUE ditampilkan sebagai pesan validasi biasa, bukan halaman
 * error (SPEC F9, v0.9).
 */
final class TangkapDuplikat
{
    /**
     * @template T
     *
     * @param  callable(): T  $simpan
     * @param  list<array{list<string>, string, string}>  $peta  [nama constraint, kolom form, pesan]
     * @return T
     *
     * @throws ValidationException
     */
    public static function jalankan(callable $simpan, array $peta): mixed
    {
        try {
            return $simpan();
        } catch (UniqueConstraintViolationException $e) {
            $constraint = self::namaConstraint($e);

            foreach ($peta as [$namaConstraint, $kolom, $pesan]) {
                if ($constraint !== null && in_array($constraint, $namaConstraint, true)) {
                    throw ValidationException::withMessages([$kolom => $pesan]);
                }
            }

            throw $e; // UNIQUE lain yang tidak dikenal: biarkan tetap terlihat sebagai error
        }
    }

    /**
     * Nama constraint yang dilanggar, dicocokkan persis (bukan "mengandung"):
     * - MySQL/MariaDB: "Duplicate entry '…' for key 'users.users_nis_unique'" → "users_nis_unique"
     * - SQLite (tes): "UNIQUE constraint failed: users.class_id, users.no_absen" → "users.class_id, users.no_absen"
     *
     * Yang dibaca hanya pesan dari driver database, bukan getMessage(): getMessage()
     * memuat SQL beserta isian form, jadi isian seperti "users.nis" bisa salah cocok.
     */
    public static function namaConstraint(UniqueConstraintViolationException $e): ?string
    {
        $pesan = (string) ($e->errorInfo[2] ?? '');

        // Dibaca dari ujung pesan: isian yang kembar (di dalam '…') tidak ikut terbaca.
        if (preg_match("/ for key '([^']+)'$/", $pesan, $cocok)) {
            $nama = $cocok[1];

            // MySQL 8 menambahkan nama tabel di depan: "users.users_nis_unique".
            return str_contains($nama, '.') ? substr($nama, strrpos($nama, '.') + 1) : $nama;
        }

        if (preg_match('/^UNIQUE constraint failed: (.+)$/', $pesan, $cocok)) {
            return $cocok[1];
        }

        return null;
    }
}
