<?php

namespace App\Actions;

use App\Enums\Role;
use App\Models\Kelas;
use App\Models\School;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hapus akun oleh admin (SPEC F9, v0.9).
 *
 * - guru/siswa/PJ: hanya kalau belum punya riwayat;
 * - admin: tidak boleh menghapus diri sendiri, dan minimal tersisa 1 admin;
 * - jadwal piket & sesi login akun itu ikut terhapus.
 */
class HapusAkun
{
    public const PESAN_RIWAYAT = 'Akun ini sudah punya riwayat, jadi tidak bisa dihapus. Ubah datanya jika ada yang salah.';

    public const PESAN_SENDIRI = 'Kamu tidak bisa menghapus akunmu sendiri.';

    public const PESAN_ADMIN_TERAKHIR = 'Admin terakhir di sekolah ini tidak bisa dihapus. Minimal harus ada 1 admin.';

    /** null kalau boleh dihapus; selain itu alasan penolakan (ditampilkan di form). */
    public function alasanDitolak(User $akun, User $olehAdmin): ?string
    {
        if ($akun->is($olehAdmin)) {
            return self::PESAN_SENDIRI;
        }

        if ($akun->role === Role::Admin) {
            return $akun->school->jumlahAdmin() <= 1 ? self::PESAN_ADMIN_TERAKHIR : null;
        }

        return $this->punyaRiwayat($akun) ? self::PESAN_RIWAYAT : null;
    }

    /**
     * @throws ValidationException kalau aturan F9 tidak terpenuhi
     * @throws \Symfony\Component\HttpKernel\Exception\NotFoundHttpException kalau akun sudah terhapus
     */
    public function jalankan(User $akun, User $olehAdmin): void
    {
        DB::transaction(function () use ($akun, $olehAdmin) {
            // Kunci baris sekolah sebagai antrean: dua admin yang saling menghapus
            // pada saat bersamaan tidak bisa membuat sekolah tanpa admin.
            School::whereKey($akun->school_id)->lockForUpdate()->first();

            // Kunci baris akun ini juga. Penyimpanan lain yang menunjuk akun ini
            // (mis. menjadikannya wali kelas, atau submit/verifikasi nanti) harus
            // menunggu kunci ini karena foreign key. Akibatnya:
            // - penyimpanan itu selesai duluan → terlihat sebagai riwayat di bawah → ditolak;
            // - hapus selesai duluan → penyimpanan itu ditolak database (KelasController
            //   menampilkannya sebagai pesan validasi).
            // Kedua kunci harus sebelum query biasa pertama di transaksi: di MySQL
            // (REPEATABLE READ) hitungan sesudahnya baru memakai data terbaru.
            // Akun yang baru saja dihapus admin lain → 404 (bukan "dihapus" atau
            // pesan "admin terakhir" yang menyesatkan).
            abort_unless(User::whereKey($akun->id)->lockForUpdate()->first(), 404);

            if ($alasan = $this->alasanDitolak($akun, $olehAdmin)) {
                throw ValidationException::withMessages(['hapus' => $alasan]);
            }

            $akun->dutySchedules()->delete();
            DB::table(config('session.table'))->where('user_id', $akun->id)->delete();
            $akun->delete();
        });
    }

    /**
     * Riwayat = data yang disimpan permanen (F6) dan menunjuk ke akun ini:
     * submit miliknya, submit yang pernah ia verifikasi (PJ/guru), atau kelas yang ia walikan.
     */
    private function punyaRiwayat(User $akun): bool
    {
        return Submission::where('user_id', $akun->id)->exists()
            || Submission::where('verified_by', $akun->id)->exists()
            || Kelas::where('wali_kelas_id', $akun->id)->exists();
    }
}
