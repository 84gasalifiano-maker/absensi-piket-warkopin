<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\Kelas;
use App\Models\User;
use App\Support\PasswordAcak;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Reset massal password satu kelas oleh admin (SPEC F9, v0.11).
 *
 * Yang ikut: siswa & PJ kelas ini yang masih harus_ganti_password = true
 * (Kelas::akunBelumGantiPassword). Maks 50; lebih dari itu seluruhnya ditolak.
 *
 * Alurnya tiga tahap:
 * 1. cek awal tanpa kunci (supaya tidak meng-hash 50 password untuk reset yang pasti ditolak);
 * 2. buat & hash password di LUAR transaksi (± 0,4 detik per akun), supaya baris tidak terkunci lama;
 * 3. transaksi singkat: kunci baris akun hasil cek awal lewat primary key (urut id), cek
 *    ulang tiap baris, lalu ganti password hanya akun yang masih memenuhi syarat DAN
 *    password-nya belum berubah sejak cek awal. Akun yang baru mengganti password, dihapus,
 *    pindah kelas, atau baru direset admin lain bersamaan tidak ikut, jadi password yang
 *    baru dipilih siswa (atau dibagikan admin lain) tidak tertimpa.
 *
 * Kunci: hanya baris akun, lewat primary key (urut id). Bukan lewat WHERE class_id: kueri
 * itu memakai index (class_id, no_absen), sehingga ikut mengunci rentang index dalam urutan
 * no. absen dan bisa deadlock dengan hapus akun / pindah kelas, serta membuat penambahan
 * siswa ke kelas itu menunggu (temuan review 5a). Sekolah & kelas tidak dikunci.
 */
class ResetMassalKelas
{
    public const MAKS_AKUN = 50;

    public function __construct(private GantiPassword $gantiPassword) {}

    /**
     * Hasil: daftar password baru, urut no. absen (untuk diunduh sekali).
     *
     * @return list<array{nama: string, kelas: string, no_absen: int, nis: string, password: string}>
     *
     * @throws ValidationException kalau tidak ada akun yang memenuhi syarat atau lebih dari 50
     */
    public function jalankan(Kelas $kelas): array
    {
        // Tahap 1: cek awal (tanpa kunci). Hash password lama dicatat untuk tahap 3.
        // Kalau di sini sudah 0 atau > 50, langsung ditolak dengan angka cek awal (selisih
        // dengan cek ulang hanya milidetik), supaya tidak meng-hash percuma. Kompromi ini
        // tercatat di SPEC F9 (v0.11).
        $hashLama = $kelas->akunBelumGantiPassword()->orderBy('id')->pluck('password', 'id');
        $this->cekJumlah($kelas, $hashLama->count());

        // Tahap 2: password + hash, di luar transaksi.
        $siap = [];
        foreach ($hashLama->keys() as $id) {
            $password = PasswordAcak::buat();
            $siap[$id] = ['password' => $password, 'hash' => Hash::make($password)];
        }

        // Tahap 3: kunci + cek ulang + simpan, dalam satu transaksi.
        $direset = DB::transaction(function () use ($kelas, $siap, $hashLama) {
            // Kunci lewat primary key: hanya baris-baris ini yang terkunci (tanpa kunci
            // rentang). Bacaan dengan kunci selalu memakai data terbaru yang sudah di-commit.
            $terkunci = User::whereKey($hashLama->keys())->orderBy('id')->lockForUpdate()->get();

            // Cek ulang batas 50. Ini bacaan biasa pertama di transaksi, jadi di MySQL
            // (REPEATABLE READ) juga memakai data terbaru. Akun yang baru memenuhi syarat
            // (mis. baru ditambahkan) ikut dihitung (keputusan pemilik proyek), tapi tidak
            // ikut direset: passwordnya baru saja dibuat dan tidak di-hash di tahap 2.
            $this->cekJumlah($kelas, $kelas->akunBelumGantiPassword()->count());

            $akunDireset = $terkunci->filter(fn (User $akun) => $this->masihMemenuhiSyarat($akun, $kelas)
                // Password berubah sejak cek awal (ganti sendiri, reset per akun, atau reset
                // massal admin lain) → jangan ditimpa, supaya password itu tetap berlaku.
                && hash_equals($hashLama[$akun->id], $akun->password));

            // Masih ada akun yang belum mengganti password (cekJumlah di atas lolos), tapi
            // tidak satu pun bisa direset karena semuanya berubah sejak cek awal, biasanya
            // karena admin lain baru saja mereset kelas ini. "Tidak ada akun …" akan
            // menyesatkan di sini (SPEC F9, v0.11).
            if ($akunDireset->isEmpty()) {
                throw $this->gagal(self::pesanBerubahBersamaan());
            }

            foreach ($akunDireset as $akun) {
                $this->gantiPassword->jalankanDenganHash($akun, $siap[$akun->id]['hash'], harusGantiPassword: true);
            }

            return $akunDireset;
        }, attempts: 3);
        // attempts: 3 → kalau transaksi gagal karena bentrok kunci (deadlock, mis. dengan
        // pembersihan sesi kedaluwarsa otomatis Laravel yang juga menghapus baris sessions,
        // atau lock wait timeout), transaksi diulang dari awal, maks 3 kali. Aman diulang karena:
        // - Laravel me-rollback semua perubahan percobaan sebelumnya sebelum mengulang;
        // - isi closure hanya mengubah database: tidak ada sesi, file, atau email;
        // - baris akun dibaca ulang di dalam closure, jadi tidak memakai objek dari
        //   percobaan yang gagal (yang password-nya di memori sudah diganti);
        // - hash dari tahap 2 dipakai ulang (tidak di-hash lagi), jadi daftar password tetap cocok;
        // - pengecualian lain (mis. ValidationException) tidak diulang.
        // Daftar disimpan ke sesi oleh pemanggil, setelah fungsi ini selesai (= setelah commit).

        return $direset
            ->sortBy('no_absen')
            ->map(fn (User $akun) => [
                'nama' => $akun->nama,
                'kelas' => $kelas->nama,
                'no_absen' => $akun->no_absen,
                'nis' => $akun->nis,
                'password' => $siap[$akun->id]['password'],
            ])
            ->values()
            ->all();
    }

    public static function pesanKosong(Kelas $kelas): string
    {
        return "Tidak ada akun di kelas {$kelas->nama} yang belum mengganti password. Untuk akun tertentu, pakai reset password per akun.";
    }

    public static function pesanBerubahBersamaan(): string
    {
        return 'Password akun di kelas ini baru saja direset admin lain. Muat ulang halaman.';
    }

    public static function pesanSukses(Kelas $kelas, int $jumlah): string
    {
        return "Password {$jumlah} akun di kelas {$kelas->nama} direset. Unduh daftar password sekarang — daftar hanya bisa diunduh sekali.";
    }

    public static function pesanKonfirmasi(Kelas $kelas, int $jumlah): string
    {
        return "Reset password {$jumlah} akun di kelas {$kelas->nama} yang belum pernah mengganti password? "
            .'Password yang sudah dibagikan tetapi belum dipakai login ikut tidak berlaku, dan akun-akun itu keluar dari semua perangkat. '
            .'Tindakan ini tidak bisa dibatalkan.';
    }

    /** Syarat yang sama dengan Kelas::akunBelumGantiPassword, dicek pada baris yang sudah dikunci. */
    private function masihMemenuhiSyarat(User $akun, Kelas $kelas): bool
    {
        // (int): school_id/class_id tidak punya cast, jadi tipenya bergantung pada driver database.
        return (int) $akun->school_id === (int) $kelas->school_id
            && (int) $akun->class_id === (int) $kelas->id
            && in_array($akun->role, [Role::Siswa, Role::PjKebersihan], true)
            && $akun->harus_ganti_password === true;
    }

    /** @throws ValidationException */
    private function cekJumlah(Kelas $kelas, int $jumlah): void
    {
        if ($jumlah === 0) {
            throw $this->gagal(self::pesanKosong($kelas));
        }

        if ($jumlah > self::MAKS_AKUN) {
            throw $this->gagal("Kelas {$kelas->nama} punya {$jumlah} akun yang belum mengganti password, maks ".self::MAKS_AKUN.' per reset. Pakai reset password per akun.');
        }
    }

    private function gagal(string $pesan): ValidationException
    {
        return ValidationException::withMessages(['reset_password' => $pesan]);
    }
}
