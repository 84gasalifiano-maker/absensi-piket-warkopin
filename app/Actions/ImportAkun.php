<?php

namespace App\Actions;

use App\Enums\Role;
use App\Http\Requests\Admin\SimpanAkunRequest;
use App\Models\Kelas;
use App\Models\User;
use App\Support\PasswordAcak;
use App\Support\TangkapDuplikat;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Import akun siswa/PJ dari CSV oleh admin (SPEC F9, v0.10).
 *
 * Alurnya tiga tahap:
 * 1. baca file → daftar baris (baris judul & format dicek di sini);
 * 2. periksa SEMUA baris dengan aturan yang sama seperti form tambah akun;
 *    satu saja baris salah → seluruh file ditolak, pesan menyebut nomor barisnya;
 * 3. simpan semua akun dalam satu transaksi (tersimpan semua atau tidak sama sekali).
 *
 * Semua kesalahan dilempar sebagai ValidationException di kolom "file",
 * jadi tampil sebagai pesan biasa di form, bukan halaman error.
 */
class ImportAkun
{
    /** Tiap password di-hash satu per satu (± 0,4 detik), jadi file dibatasi (SPEC F9). */
    public const MAKS_BARIS = 50;

    public const KOLOM_WAJIB = ['nama', 'nis', 'kelas', 'no_absen'];

    public const KOLOM_PILIHAN = ['role'];

    public const PESAN_JUDUL = 'Baris judul harus berisi kolom: nama, nis, kelas, no_absen (kolom role boleh ada).';

    public const PESAN_BUKAN_UTF8 = 'File harus disimpan sebagai CSV UTF-8. Di Excel: Simpan Sebagai → "CSV UTF-8 (Comma delimited)".';

    public const PESAN_KOSONG = 'File tidak berisi baris akun.';

    public const PESAN_KEMBAR_BERSAMAAN = 'Ada NIS atau no. absen di file ini yang baru saja dipakai akun lain. Upload ulang file untuk melihat barisnya.';

    public const PESAN_KELAS_BERUBAH = 'Ada kelas di file ini yang baru saja dihapus. Upload ulang file untuk melihat barisnya.';

    /**
     * Hasil: daftar password awal, urut kelas lalu no. absen (untuk diunduh sekali).
     *
     * @return list<array{nama: string, kelas: string, no_absen: int, nis: string, password: string}>
     *
     * @throws ValidationException
     */
    public function jalankan(string $isiFile, User $admin): array
    {
        $baris = $this->baca($isiFile);
        $calon = $this->periksa($baris, $admin);

        return $this->simpan($calon, $admin);
    }

    /**
     * Tahap 1: ubah isi file menjadi daftar baris [nomor baris => [kolom => nilai]].
     *
     * @return array<int, array<string, string>>
     *
     * @throws ValidationException
     */
    private function baca(string $isi): array
    {
        // NUL tidak ada di file teks; ini menolak file biner yang kebetulan UTF-8 valid.
        if (! mb_check_encoding($isi, 'UTF-8') || str_contains($isi, "\0")) {
            throw $this->gagal(self::PESAN_BUKAN_UTF8);
        }

        $isi = preg_replace('/^\xEF\xBB\xBF/', '', $isi); // BOM yang ditambahkan Excel

        // Pemisah dikenali dari baris pertama: Excel berbahasa Indonesia menyimpan dengan ";".
        $barisPertama = strtok($isi, "\r\n");
        $pemisah = $barisPertama !== false && substr_count($barisPertama, ';') > substr_count($barisPertama, ',') ? ';' : ',';

        $aliran = fopen('php://temp', 'r+');
        fwrite($aliran, $isi);
        rewind($aliran);

        $judul = null;
        $hasil = [];
        $kesalahan = [];
        $nomor = 0;

        // escape '' = tanpa karakter escape khusus; tanda kutip ganda "" tetap dikenali.
        while (($sel = fgetcsv($aliran, null, $pemisah, '"', '')) !== false) {
            $nomor++;
            $sel = array_map(fn ($nilai) => trim((string) $nilai), $sel);

            if (implode('', $sel) === '') {
                continue; // baris kosong dilewati
            }

            if ($judul === null) {
                $judul = $this->bacaJudul($sel);

                continue;
            }

            // Sel kosong di ujung baris (sering ditambahkan Excel) diabaikan;
            // sel yang kurang dianggap kosong, nanti ditolak aturan "wajib diisi".
            while (count($sel) > count($judul) && end($sel) === '') {
                array_pop($sel);
            }

            if (count($sel) > count($judul)) {
                $kesalahan[] = "Baris {$nomor}: jumlah kolom lebih banyak dari baris judul.";

                continue;
            }

            $hasil[$nomor] = array_combine($judul, array_pad($sel, count($judul), ''));
        }

        fclose($aliran);

        if ($judul === null || ($hasil === [] && $kesalahan === [])) {
            throw $this->gagal(self::PESAN_KOSONG);
        }

        $jumlah = count($hasil) + count($kesalahan);
        if ($jumlah > self::MAKS_BARIS) {
            throw $this->gagal("File berisi {$jumlah} baris akun, maks ".self::MAKS_BARIS.' per file. Pecah menjadi beberapa file, mis. satu file per kelas.');
        }

        if ($kesalahan !== []) {
            throw $this->gagal($kesalahan);
        }

        return $hasil;
    }

    /**
     * @param  list<string>  $sel
     * @return list<string> nama kolom (huruf kecil)
     *
     * @throws ValidationException
     */
    private function bacaJudul(array $sel): array
    {
        // Sel kosong di ujung (Excel: "nama,nis,kelas,no_absen,") diabaikan, sama seperti
        // di baris data. Sel kosong di tengah tetap ditolak sebagai kolom tidak dikenal.
        while (end($sel) === '') {
            array_pop($sel);
        }

        $judul = array_map('mb_strtolower', $sel);
        $dikenal = [...self::KOLOM_WAJIB, ...self::KOLOM_PILIHAN];

        $lengkap = array_diff(self::KOLOM_WAJIB, $judul) === [];
        $tanpaKolomAsing = array_diff($judul, $dikenal) === [];
        $tanpaKembar = count($judul) === count(array_unique($judul));

        if (! $lengkap || ! $tanpaKolomAsing || ! $tanpaKembar) {
            throw $this->gagal(self::PESAN_JUDUL);
        }

        return $judul;
    }

    /**
     * Tahap 2: periksa semua baris. Akun belum disimpan; password sudah dibuat & di-hash
     * di sini (lambat), supaya transaksi di tahap 3 singkat.
     *
     * @param  array<int, array<string, string>>  $daftarBaris
     * @return list<array{akun: User, kelas: Kelas, password: string}>
     *
     * @throws ValidationException
     */
    private function periksa(array $daftarBaris, User $admin): array
    {
        // Kunci = nama kelas huruf kecil. Nama kelas unik per sekolah (SPEC F9).
        $kelasSekolah = $admin->school->kelas()->get()->keyBy(fn (Kelas $kelas) => mb_strtolower($kelas->nama));

        $kesalahan = [];
        $calon = [];
        $nisDiFile = [];      // nis (huruf kecil) => nomor baris
        $absenDiFile = [];    // class_id => [no_absen => nomor baris]
        $pjDiFile = [];       // class_id => jumlah PJ di file
        $pjSudahAda = [];     // class_id => jumlah PJ di database

        foreach ($daftarBaris as $nomor => $data) {
            $data = $this->rapikan($data);

            $validator = Validator::make($data, [
                // Aturan yang sama dengan form tambah akun (SPEC F9).
                'nama' => SimpanAkunRequest::ATURAN_NAMA,
                'nis' => ['bail', ...SimpanAkunRequest::ATURAN_NIS, Rule::unique('users', 'nis')],
                'kelas' => ['required', 'string'],
                'no_absen' => SimpanAkunRequest::ATURAN_NO_ABSEN,
                'role' => [Rule::in([Role::Siswa->value, Role::PjKebersihan->value])],
            ], [
                'nis.unique' => 'NIS sudah dipakai.',
                'role.in' => 'Role harus siswa atau pj_kebersihan (kosong = siswa).',
            ], [
                'nama' => 'Nama',
                'nis' => 'NIS',
                'kelas' => 'Kelas',
                'no_absen' => 'No. absen',
                'role' => 'Role',
            ]);

            $pesan = $validator->errors()->all();
            $kelas = $data['kelas'] !== '' ? $kelasSekolah->get(mb_strtolower($data['kelas'])) : null;

            if ($data['kelas'] !== '' && $kelas === null) {
                $pesan[] = "Kelas \"{$data['kelas']}\" tidak ada di sekolah ini.";
            }

            // NIS kembar di dalam file. MySQL tidak membedakan huruf besar/kecil di UNIQUE.
            $kunciNis = mb_strtolower($data['nis']);
            if ($data['nis'] !== '' && isset($nisDiFile[$kunciNis])) {
                $pesan[] = "NIS sama dengan baris {$nisDiFile[$kunciNis]}.";
            } elseif ($data['nis'] !== '') {
                $nisDiFile[$kunciNis] = $nomor;
            }

            if ($kelas !== null && ! $validator->errors()->has('no_absen')) {
                $noAbsen = (int) $data['no_absen'];

                if (isset($absenDiFile[$kelas->id][$noAbsen])) {
                    $pesan[] = "No. absen {$noAbsen} di kelas {$kelas->nama} sama dengan baris {$absenDiFile[$kelas->id][$noAbsen]}.";
                } elseif (User::where('class_id', $kelas->id)->where('no_absen', $noAbsen)->exists()) {
                    $pesan[] = 'No. absen ini sudah dipakai di kelas tersebut.';
                } else {
                    $absenDiFile[$kelas->id][$noAbsen] = $nomor;
                }
            }

            // Maks 2 PJ per kelas: PJ yang sudah ada + PJ di file (SPEC F9).
            if ($kelas !== null && $data['role'] === Role::PjKebersihan->value) {
                $pjSudahAda[$kelas->id] ??= $kelas->jumlahPjSelain(null);
                $pjDiFile[$kelas->id] = ($pjDiFile[$kelas->id] ?? 0) + 1;

                if ($pjSudahAda[$kelas->id] + $pjDiFile[$kelas->id] > Kelas::MAKS_PJ) {
                    $pesan[] = $kelas->pesanPjPenuh();
                }
            }

            foreach ($pesan as $satuPesan) {
                $kesalahan[] = "Baris {$nomor}: {$satuPesan}";
            }

            if ($pesan === []) {
                $calon[] = ['data' => $data, 'kelas' => $kelas];
            }
        }

        if ($kesalahan !== []) {
            throw $this->gagal($kesalahan);
        }

        return array_map(function (array $baris) use ($admin) {
            $password = PasswordAcak::buat();

            $akun = new User([
                'nama' => $baris['data']['nama'],
                'nis' => $baris['data']['nis'],
                'class_id' => $baris['kelas']->id,
                'no_absen' => (int) $baris['data']['no_absen'],
                'email' => null,
            ]);

            // school_id, role, harus_ganti_password tidak fillable: diisi eksplisit (SPEC §6).
            // Password langsung di-hash di sini (cast "hashed" pada model User).
            $akun->forceFill([
                'school_id' => $admin->school_id,
                'role' => Role::from($baris['data']['role']),
                'password' => $password,
                'harus_ganti_password' => true, // wajib ganti saat login pertama (F1)
            ]);

            return ['akun' => $akun, 'kelas' => $baris['kelas'], 'password' => $password];
        }, $calon);
    }

    /**
     * Tahap 3: simpan semua akun dalam satu transaksi.
     *
     * @param  list<array{akun: User, kelas: Kelas, password: string}>  $calon
     * @return list<array{nama: string, kelas: string, no_absen: int, nis: string, password: string}>
     *
     * @throws ValidationException
     */
    private function simpan(array $calon, User $admin): array
    {
        $kelasDipakai = collect($calon)->pluck('kelas')->unique('id');

        TangkapDuplikat::jalankan(fn () => DB::transaction(function () use ($calon, $admin, $kelasDipakai) {
            $this->cekUlangKelas($kelasDipakai, $calon, $admin);

            foreach ($calon as $satu) {
                $satu['akun']->save();
            }
        }), [
            // Isian kembar dari penyimpanan bersamaan → pesan biasa (SPEC F9).
            [['users_nis_unique', 'users.nis'], 'file', self::PESAN_KEMBAR_BERSAMAAN],
            [['users_class_id_no_absen_unique', 'users.class_id, users.no_absen'], 'file', self::PESAN_KEMBAR_BERSAMAAN],
        ]);

        return collect($calon)
            ->map(fn (array $satu) => [
                'nama' => $satu['akun']->nama,
                'kelas' => $satu['kelas']->nama,
                'no_absen' => $satu['akun']->no_absen,
                'nis' => $satu['akun']->nis,
                'password' => $satu['password'],
            ])
            ->sortBy([['kelas', 'asc'], ['no_absen', 'asc']])
            ->values()
            ->all();
    }

    /**
     * Kunci baris kelas yang dipakai, lalu cek ulang: kelas masih ada dan batas PJ
     * masih terpenuhi. Admin lain bisa menghapus kelas atau menambah PJ bersamaan.
     * Kunci ini juga membuat hapus kelas bersamaan menunggu, lalu ditolak karena
     * kelas sudah punya siswa (SPEC F9). Kunci harus sebelum query biasa pertama di
     * transaksi (alasannya sama seperti di AkunController::cekUlangBatasPj).
     *
     * @param  Collection<int, Kelas>  $kelasDipakai
     * @param  list<array{akun: User, kelas: Kelas, password: string}>  $calon
     *
     * @throws ValidationException
     */
    private function cekUlangKelas(Collection $kelasDipakai, array $calon, User $admin): void
    {
        $terkunci = Kelas::whereKey($kelasDipakai->pluck('id'))
            ->where('school_id', $admin->school_id)
            ->orderBy('id') // urutan kunci tetap, mengurangi risiko deadlock
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($terkunci->count() !== $kelasDipakai->count()) {
            throw $this->gagal(self::PESAN_KELAS_BERUBAH);
        }

        $pjBaru = collect($calon)
            ->filter(fn (array $satu) => $satu['akun']->role === Role::PjKebersihan)
            ->countBy(fn (array $satu) => $satu['kelas']->id);

        foreach ($pjBaru as $kelasId => $jumlah) {
            $kelas = $terkunci[$kelasId];

            if ($kelas->jumlahPjSelain(null) + $jumlah > Kelas::MAKS_PJ) {
                throw $this->gagal($kelas->pesanPjPenuh().' Upload ulang file untuk melihat barisnya.');
            }
        }
    }

    /**
     * Rapikan satu baris sebelum diperiksa.
     *
     * @param  array<string, string>  $data
     * @return array{nama: string, nis: string, kelas: string, no_absen: string, role: string}
     */
    private function rapikan(array $data): array
    {
        $noAbsen = $data['no_absen'];

        return [
            'nama' => $data['nama'],
            'nis' => $data['nis'],
            'kelas' => $data['kelas'],
            // "05" dari Excel tetap dianggap 5 (aturan integer Laravel menolak nol di depan).
            'no_absen' => preg_match('/^\d+$/', $noAbsen) ? (ltrim($noAbsen, '0') ?: '0') : $noAbsen,
            // role kosong / kolom role tidak ada = siswa (SPEC F9).
            'role' => mb_strtolower($data['role'] ?? '') ?: Role::Siswa->value,
        ];
    }

    /** @param  string|list<string>  $pesan */
    private function gagal(string|array $pesan): ValidationException
    {
        return ValidationException::withMessages(['file' => $pesan]);
    }
}
