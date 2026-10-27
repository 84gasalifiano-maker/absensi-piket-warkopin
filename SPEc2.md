# SPEC — Aplikasi Absensi Piket Kelas

> Dokumen ini adalah sumber kebenaran tunggal. Semua kode harus sesuai dengan dokumen ini.
> Kalau ada perubahan fitur, ubah dokumen ini DULU, baru kode.

Status: DRAFT v0.2
Bagian bertanda **[KEPUTUSAN TERBUKA]** belum final — putuskan sebelum fitur terkait dikerjakan.

---

## 1. Tujuan

Mencatat siapa yang piket hari ini, apakah piketnya sudah dikerjakan, dan buktinya — menggantikan pencatatan manual di kertas/grup chat.

**Ukuran berhasil (MVP):**
- Siswa bisa submit bukti piket dalam < 1 menit dari HP.
- Verifikator bisa lihat siapa yang belum piket hari ini dalam 1 layar.
- Rekap mingguan per kelas dan per siswa tersedia tanpa hitung manual.

## 2. Pengguna & Role

| Role | Siapa | Bisa apa |
|---|---|---|
| `siswa` | Semua murid | Lihat jadwal piket sendiri, submit bukti piket, lihat riwayat sendiri |
| `pj_kebersihan` | Penanggung jawab seksi kebersihan (juga siswa) | Semua hak `siswa` + verifikasi submit di kelasnya + lihat rekap kelasnya + kelola jadwal piket kelasnya |
| `guru` | Wali kelas | Verifikasi submit di kelasnya, lihat rekap kelasnya, kelola jadwal piket kelasnya |
| `admin` | Pengelola sekolah | Kelola kelas, akun, pengaturan jam; lihat rekap semua kelas |

Aturan:
- Satu user = satu role.
- `siswa`, `pj_kebersihan`, dan `guru` hanya bisa melihat data **kelasnya sendiri**.
- `pj_kebersihan` **tidak boleh memverifikasi submit miliknya sendiri** — submit milik PJ hanya bisa diverifikasi `guru`.
- "Verifikator" di dokumen ini = `pj_kebersihan` atau `guru`.

## 3. Fitur MVP

### F1. Login
- Satu halaman login untuk semua role: email + password.
- Setelah login, user diarahkan sesuai role:
  - `siswa`, `pj_kebersihan` → `/beranda`
  - `guru` → `/verifikasi`
  - `admin` → `/admin`
- Akun dibuat admin (import CSV atau form), bukan daftar sendiri.
- Reset password dilakukan admin. Saat login pertama, user wajib ganti password.
- Batasi percobaan login gagal (maks 5 kali per menit per email+IP).

### F2. Jadwal Piket
- Guru/PJ kebersihan/admin mengatur jadwal per kelas: hari Senin → [siswa A, B, C], dst.
- Hari piket: Senin–Sabtu.
- Siswa melihat "Hari ini kamu piket" di beranda kalau terjadwal.

### F3. Submit Piket
- Hanya siswa (termasuk PJ) yang terjadwal hari itu yang bisa submit.
- Isi: 1–3 foto + catatan opsional.
- Foto diambil dari kamera (`<input type="file" accept="image/*" capture="environment">`) — sejauh browser mendukung.
- Waktu submit dicatat oleh **server**, bukan dari HP.
- Setelah submit → status `menunggu_verifikasi`.
- Satu siswa = maksimal satu submit aktif per tanggal. Kalau ditolak, boleh submit ulang di hari yang sama.

### F4. Batas Waktu & Keterlambatan
- Jendela waktu tepat waktu diatur admin (default: 06:00–07:30 dan 14:00–15:30 WIB).
- Submit **di dalam jendela** → `terlambat = false`.
- Submit **setelah jendela tutup sampai 23:59 WIB di hari yang sama** → diterima dengan `terlambat = true`, dan **alasan keterlambatan wajib diisi**.
- Setelah 23:59 → tidak bisa submit; tercatat `tidak_piket`.
- Sebelum jendela pertama buka → tombol submit nonaktif + tampilkan jam buka.
- Semua validasi waktu dilakukan di server.

### F5. Verifikasi
- Verifikator melihat daftar submit `menunggu_verifikasi` di kelasnya, dengan tanda "Terlambat" + alasannya bila ada.
- Aksi: `disetujui` atau `ditolak` (alasan wajib diisi kalau ditolak).
- Siswa melihat status + alasan penolakan.
- Submit yang belum diverifikasi saat foto dihapus mingguan (lihat F7) → status otomatis `kedaluwarsa`.

### F6. Rekap Mingguan
- Rekap per kelas per minggu (Senin–Sabtu): per siswa → jumlah terjadwal, disetujui, terlambat, ditolak, tidak piket, kedaluwarsa.
- Rekap dihitung dari tabel `submissions` + `duty_schedules` (tidak disimpan di tabel terpisah, agar tidak ada data ganda).
- Data teks (status, waktu, alasan) disimpan permanen; hanya foto yang dihapus. Jadi rekap minggu lalu tetap bisa dilihat.
- Ekspor CSV.

### F7. Penghapusan Foto Mingguan
- Setiap **Minggu pukul 23:00 WIB**, sistem otomatis:
  1. Mengubah semua submit `menunggu_verifikasi` minggu itu menjadi `kedaluwarsa`.
  2. Menghapus semua file foto minggu itu dari storage dan barisnya di `submission_photos`.
- Dijalankan lewat Laravel Scheduler (butuh cron di server).
- Sabtu sore dan Minggu: dashboard verifikator menampilkan peringatan "X submit belum diverifikasi, foto akan dihapus Minggu 23:00".

## 4. Di luar MVP (jangan dikerjakan dulu)
- Notifikasi pengingat piket
- Tukar jadwal antar siswa
- Peringkat kebersihan antar kelas
- Login dengan OTP atau Google
- Ekspor Excel/PDF

## 5. Status Submit

```
(belum submit) → menunggu_verifikasi → disetujui
                         │            ↘ ditolak → (submit ulang hari itu) → menunggu_verifikasi
                         └→ kedaluwarsa (belum diverifikasi saat penghapusan mingguan)

Jadwal lewat tanpa submit → tidak_piket (dihitung saat rekap, tidak disimpan sebagai baris)
Flag terpisah: terlambat (true/false) + alasan_terlambat
```

## 6. Model Data (MySQL)

```
schools          id, nama, jam_buka_pagi, jam_tutup_pagi, jam_buka_siang, jam_tutup_siang
classes          id, school_id, nama (mis. "XI RPL 1"), wali_kelas_id → users.id
users            id, school_id, class_id (null utk admin), nama, email (unik), nis (unik per sekolah, null utk guru/admin),
                 password, role, harus_ganti_password, created_at
duty_schedules   id, class_id, hari (1=Senin … 6=Sabtu), user_id
                 UNIQUE(class_id, hari, user_id)
submissions      id, user_id, class_id, tanggal, catatan,
                 status (menunggu_verifikasi|disetujui|ditolak|kedaluwarsa),
                 terlambat (bool), alasan_terlambat,
                 alasan_tolak, verified_by → users.id, verified_at,
                 created_at (waktu server)
submission_photos id, submission_id, file_path, created_at
```

Aturan:
- Cegah dua submit aktif per siswa per tanggal: sebelum insert, cek tidak ada submit dengan status `menunggu_verifikasi`/`disetujui` untuk (user_id, tanggal), di dalam transaksi database.
- Tidak ada data yang disimpan dua kali (mis. nama kelas cukup di `classes`).
- Timezone aplikasi: `Asia/Jakarta`.

## 7. Halaman & Route

Aplikasi berbasis halaman (Laravel + Blade), bukan API terpisah. Semua route kecuali login butuh login.

| Method | Route | Role | Keterangan |
|---|---|---|---|
| GET/POST | /login | publik | satu halaman login untuk semua role |
| POST | /logout | semua | |
| GET/POST | /ganti-password | semua | wajib saat login pertama |
| GET | /beranda | siswa, pj | status piket hari ini + tombol submit |
| GET/POST | /piket/submit | siswa, pj | form submit (foto, catatan, alasan terlambat bila perlu) |
| GET | /piket/riwayat | siswa, pj | riwayat submit sendiri |
| GET | /verifikasi | pj, guru | antrean submit kelasnya |
| POST | /verifikasi/{id} | pj, guru | setujui/tolak |
| GET | /rekap?minggu= | pj, guru, admin | rekap mingguan; `&format=csv` untuk ekspor |
| GET/POST/DELETE | /jadwal | pj, guru, admin | kelola jadwal kelasnya |
| GET | /admin | admin | dashboard admin |
| GET/POST | /admin/kelas | admin | kelola kelas |
| GET/POST | /admin/akun | admin | kelola & import akun (CSV), reset password |
| GET/POST | /admin/pengaturan | admin | jam buka/tutup |

Proteksi: middleware `auth` + middleware role di setiap grup route. Semua route `/admin/*` hanya untuk `admin`.

Pesan error ke user dalam bahasa Indonesia, mis. "Kamu tidak terjadwal piket hari ini", "Alasan keterlambatan wajib diisi".

## 8. Layar

| Layar | Role | Isi utama |
|---|---|---|
| Login | semua | email, password |
| Ganti password | semua | password lama, baru, konfirmasi |
| Beranda | siswa, pj | "Hari ini kamu piket / tidak", jam tepat waktu, tombol Submit, status submit hari ini |
| Form submit | siswa, pj | ambil foto, catatan, field alasan terlambat (muncul hanya jika sudah lewat jam) |
| Riwayat | siswa, pj | daftar submit + status + alasan tolak |
| Antrean verifikasi | pj, guru | kartu submit: nama, foto, jam, tanda terlambat + alasan, tombol setujui/tolak; peringatan penghapusan foto |
| Rekap mingguan | pj, guru, admin | tabel per siswa + pilih minggu + ekspor CSV |
| Kelola jadwal | pj, guru, admin | grid hari × siswa |
| Admin | admin | ringkasan semua kelas, menu kelas/akun/pengaturan |

Desain: mobile-first, bahasa Indonesia, tombol besar. Halaman admin boleh lebih padat (dipakai di laptop).

## 9. Keamanan & Privasi (wajib)
- Data siswa adalah data anak di bawah umur → patuhi UU PDP. Foto hanya bisa dilihat siswa ybs, verifikator kelasnya, dan admin.
- Foto disimpan di `storage/app/private`, BUKAN folder `public`. Ditampilkan lewat route yang mengecek hak akses.
- Upload: hanya jpg/png/webp, maks 5 MB per foto, nama file acak, kompres sebelum simpan.
- Foto dihapus otomatis setiap Minggu 23:00 WIB (F7).
- Password di-hash (bawaan Laravel). CSRF aktif di semua form (bawaan Laravel).
- Cek role & kelas di server untuk setiap route (siswa kelas A tidak boleh lihat kelas B).

## 10. Teknologi
- Backend + tampilan: **Laravel 13** (PHP ≥ 8.3) dengan Blade
- Database: **MySQL** (atau MariaDB)
- Styling: Tailwind CSS (bawaan starter Laravel) — atau Bootstrap kalau lebih familiar
- Lingkungan lokal: **Laragon** atau **XAMPP** dengan PHP ≥ 8.3 (AppServ hanya dipakai jika versi PHP-nya ≥ 8.3)
- Hosting: shared hosting/VPS dengan PHP ≥ 8.3, MySQL, dan **cron job** (wajib untuk F7)

## 11. Urutan Pengerjaan
1. Setup proyek Laravel + koneksi MySQL + migrasi semua tabel + seeder data contoh (1 sekolah, 2 kelas, akun tiap role)
2. F1 Login + redirect per role + middleware role + ganti password
3. Halaman admin: kelola kelas & akun
4. F2 Jadwal piket
5. F3 + F4 Submit dengan batas waktu & keterlambatan
6. F5 Verifikasi
7. F6 Rekap mingguan + CSV
8. F7 Penghapusan foto mingguan (scheduler)
9. Uji coba dengan 1 kelas sungguhan selama 1 minggu
