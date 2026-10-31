# SPEC — Aplikasi Absensi Piket Kelas

> Dokumen ini adalah sumber kebenaran tunggal. Semua kode harus sesuai dengan dokumen ini.
> Kalau ada perubahan fitur, ubah dokumen ini DULU, baru kode.

Status: DRAFT v0.5
Bagian bertanda **[KEPUTUSAN TERBUKA]** belum final — putuskan sebelum fitur terkait dikerjakan.

Perubahan v0.5 (keputusan pemilik proyek): piket hanya Senin–Jumat; tugas piket per siswa (menyapu / merapikan meja & kursi / mengepel) yang berputar tiap minggu; hari libur sekolah (F8).

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
| `siswa` | Semua murid | Lihat jadwal & tugas piket sendiri, submit bukti piket, lihat riwayat sendiri |
| `pj_kebersihan` | Penanggung jawab seksi kebersihan (juga siswa) | Semua hak `siswa` + verifikasi submit di kelasnya + lihat rekap kelasnya + kelola jadwal piket kelasnya (hari + tugas awal) |
| `guru` | Wali kelas | Verifikasi submit di kelas yang diwalikannya, lihat rekap kelas itu, kelola jadwal piket kelas itu (hari + tugas awal) |
| `admin` | Pengelola sekolah | Kelola kelas, akun, pengaturan jam, hari libur; lihat rekap semua kelas |

Aturan:
- Satu user = satu role.
- `siswa`, `pj_kebersihan`, dan `guru` hanya bisa melihat data **kelasnya sendiri**.
- Kelas siswa/PJ ditentukan dari `users.class_id`. Kelas guru ditentukan **hanya** dari `classes.wali_kelas_id` (`users.class_id` guru selalu null). Satu guru boleh menjadi wali lebih dari satu kelas.
- `pj_kebersihan` **tidak boleh memverifikasi submit miliknya sendiri** — submit milik PJ hanya bisa diverifikasi `guru`.
- "Verifikator" di dokumen ini = `pj_kebersihan` atau `guru`.

## 3. Fitur MVP

### F1. Login
- Satu halaman login untuk semua role, dengan satu kolom **"NIS atau email"** + password.
  - `siswa`, `pj_kebersihan` login pakai **NIS**.
  - `guru`, `admin` login pakai **email**.
- Checkbox **"Ingat saya"**, default **tidak dicentang**. Kalau dicentang, user tetap login walau sesi habis (memakai `users.remember_token`).
- Setelah login, user diarahkan sesuai role:
  - `siswa`, `pj_kebersihan` → `/beranda`
  - `guru` → `/verifikasi`
  - `admin` → `/admin`
- Akun dibuat admin (import CSV atau form), bukan daftar sendiri.
- Reset password dilakukan admin. Saat login pertama, user wajib ganti password.
  - Selama wajib ganti password, semua halaman dialihkan ke `/ganti-password` (kecuali logout).
  - Password baru: minimal 8 karakter, harus berbeda dari password lama, dan diketik dua kali (konfirmasi).
  - Batasi percobaan ganti password (maks 5 kali per menit per user).
- Setiap kali password berubah (diganti user sendiri atau direset admin), semua sesi dan cookie "Ingat saya" user itu di perangkat lain ikut keluar. Kalau user mengganti sendiri, perangkat yang sedang dipakai tetap login.
- Setiap kolom password (login; ganti password: lama, baru, konfirmasi) punya tombol sendiri untuk menampilkan/menyembunyikan isinya. Default tersembunyi. Label untuk pembaca layar berubah sesuai kondisi: "Tampilkan password" / "Sembunyikan password".
- Batasi percobaan login gagal (maks 5 kali per menit per NIS/email + IP).

### F2. Jadwal Piket & Tugas
- Guru/PJ kebersihan/admin mengatur jadwal per kelas: untuk setiap siswa dipilih **hari** dan **tugas awal** (keduanya dropdown).
- Hari piket: **Senin–Jumat** (hari 1–5). Sabtu dan Minggu tidak ada piket.
- Setiap siswa terjadwal di **tepat satu hari** per minggu (dijaga `UNIQUE(class_id, user_id)`).
- Yang boleh dijadwalkan hanya `siswa`/`pj_kebersihan` dengan `users.class_id` = kelas itu.
- Tiga jenis tugas. **Usulan** nilai enum → label tampilan:
  - `menyapu` → "Menyapu"
  - `merapikan` → "Merapikan meja & kursi"
  - `mengepel` → "Mengepel"
- Tugas berputar otomatis setiap minggu: menyapu → merapikan → mengepel → menyapu. Dihitung dari `classes.tanggal_mulai_rotasi` dan jumlah minggu kalender sejak tanggal itu (rumus di §6 Aturan).
- Validasi saat simpan jadwal (di server):
  - maks **9 siswa per hari** per kelas → "Hari Senin sudah penuh (maks 9 siswa)";
  - maks **3 siswa per tugas per hari** → "Tugas Menyapu di hari Senin sudah 3 siswa".
- Beranda siswa: "Hari ini kamu piket — tugas: X" kalau terjadwal hari ini dan hari ini bukan hari libur (F8).

Keputusan terbuka F2:
- **[KEPUTUSAN TERBUKA] "Tepat satu hari"** — boleh simpan kalau masih ada siswa yang belum terjadwal?
  (a) boleh; layar menampilkan daftar "Belum terjadwal";
  (b) tidak boleh; semua siswa kelas wajib terjadwal. Catatan: kapasitas maks 5 × 9 = 45 siswa — kelas > 45 siswa tidak bisa memenuhi (b).
- **[KEPUTUSAN TERBUKA] `classes.tanggal_mulai_rotasi`**:
  - Siapa mengisi: (a) admin di `/admin/kelas`; (b) guru/PJ di `/jadwal`; (c) keduanya.
  - Kalau kosong: (a) kolom wajib (NOT NULL); (b) boleh null → jadwal belum bisa disimpan / tugas tidak ditampilkan sampai diisi; (c) null dianggap tanggal tertentu (mis. tanggal kelas dibuat).
  - Harus hari Senin? (a) wajib Senin; (b) tanggal apa saja, minggu yang memuat tanggal itu = minggu ke-0.
  - Definisi "minggu kalender": (a) Senin–Minggu (sejalan dengan rekap F6 dan F7); (b) definisi lain.
  - Tanggal sebelum `tanggal_mulai_rotasi` (mis. diisi tanggal depan): (a) jadwal belum berlaku; (b) pakai `tugas_awal` apa adanya; (c) hitung mundur (n negatif).
  - Boleh diubah setelah rotasi berjalan? Mengubahnya menggeser tugas semua siswa kelas itu. (a) boleh; (b) hanya admin; (c) dikunci setelah diisi.
- **[KEPUTUSAN TERBUKA] Arti pilihan di dropdown tugas awal.** Secara harfiah `tugas_awal` = tugas di minggu ke-0 (minggu `tanggal_mulai_rotasi`). Akibatnya, kalau jadwal diatur di minggu ke-4 dan dipilih "Menyapu", minggu ini siswa justru mendapat "Merapikan".
  (a) tetap harfiah; form menampilkan pratinjau "Tugas minggu ini: X" di samping dropdown;
  (b) dropdown berarti "tugas minggu ini"; server menghitung mundur dan menyimpan `tugas_awal` yang setara.
- **[KEPUTUSAN TERBUKA] Jadwal atau `tugas_awal` diubah di tengah minggu.** Tugas dan rekap dihitung dari jadwal saat ini, jadi perubahan langsung berlaku — termasuk untuk hari yang sudah lewat dan rekap minggu-minggu lalu (masalah ini sudah ada sejak v0.4). Siswa yang dipindah dari Senin (sudah lewat) ke Kamis bisa piket dua kali, atau tidak sama sekali, minggu itu. Submit yang sudah ada tetap memakai `submissions.tugas`.
  (a) berlaku langsung, terima efeknya ke rekap;
  (b) berlaku mulai minggu depan — butuh kolom tambahan (mis. `berlaku_mulai`) yang belum ada di model data;
  (c) larang perubahan untuk siswa yang sudah submit minggu ini, atau hanya boleh diubah Sabtu–Minggu.
- **[KEPUTUSAN TERBUKA] Siswa pindah kelas** (admin mengubah `users.class_id`), padahal baris `duty_schedules` masih menunjuk kelas lama:
  (a) baris jadwal kelas lama otomatis dihapus; siswa muncul sebagai "Belum terjadwal" di kelas baru;
  (b) admin ditolak memindahkan kelas sebelum jadwal lama dihapus;
  (c) baris lama dibiarkan tetapi diabaikan (siswa dianggap tidak terjadwal).
- **[KEPUTUSAN TERBUKA] Cara simpan jadwal:**
  (a) sekaligus satu kelas (satu POST berisi semua siswa; batas dicek pada hasil akhir — memudahkan tukar hari/tugas antar siswa);
  (b) per siswa (POST tambah/ubah satu siswa, DELETE hapus satu) — tukar di hari yang sudah penuh harus lewat dua langkah.

### F3. Submit Piket
- Hanya siswa (termasuk PJ) yang terjadwal hari itu (Senin–Jumat) yang bisa submit.
- Isi: 1–3 foto + catatan opsional.
- Foto diambil dari kamera (`<input type="file" accept="image/*" capture="environment">`) — sejauh browser mendukung.
- Waktu submit dicatat oleh **server**, bukan dari HP.
- `submissions.tugas` diisi **server** saat submit dari hasil rotasi (F2), bukan dari input form. Submit ulang setelah ditolak memakai tugas hasil rotasi hari itu.
- Setelah submit → status `menunggu_verifikasi`.
- Satu siswa = maksimal satu submit aktif per tanggal. Kalau ditolak, boleh submit ulang di hari yang sama.
- **[KEPUTUSAN TERBUKA] Submit di tanggal libur (F8):**
  (a) ditolak — tombol submit tidak ditampilkan, pesan "Hari ini libur, tidak ada piket";
  (b) boleh sebagai piket sukarela — lalu perlu diputuskan apakah dihitung di rekap (sebagai disetujui/terlambat/ditolak) atau diabaikan.

### F4. Batas Waktu & Keterlambatan
- Jendela waktu tepat waktu diatur admin (default: 06:00–07:30 dan 14:00–15:30 WIB), berlaku Senin–Jumat.
- Submit **di dalam jendela** → `terlambat = false`.
- Submit **setelah jendela tutup sampai 23:59 WIB di hari yang sama** → diterima dengan `terlambat = true`, dan **alasan keterlambatan wajib diisi**.
- Setelah 23:59 → tidak bisa submit; tercatat `tidak_piket` — **kecuali tanggal libur** (F8).
- Sebelum jendela pertama buka → tombol submit nonaktif + tampilkan jam buka.
- Semua validasi waktu dilakukan di server.

### F5. Verifikasi
- Verifikator melihat daftar submit `menunggu_verifikasi` di kelasnya, dengan **tugas** (`submissions.tugas`) dan tanda "Terlambat" + alasannya bila ada.
- Aksi: `disetujui` atau `ditolak` (alasan wajib diisi kalau ditolak).
- Siswa melihat status + alasan penolakan.
- Submit yang belum diverifikasi saat foto dihapus mingguan (lihat F7) → status otomatis `kedaluwarsa`.

### F6. Rekap Mingguan
- Rekap per kelas per minggu (**Senin–Jumat**): per siswa → jumlah terjadwal, disetujui, terlambat, ditolak, tidak piket, kedaluwarsa.
- Tanggal libur (F8) **tidak dihitung** sebagai terjadwal maupun `tidak_piket`.
- Rekap dihitung dari tabel `submissions` + `duty_schedules` + `hari_libur` (tidak disimpan di tabel terpisah, agar tidak ada data ganda).
- Data teks (status, waktu, alasan, tugas) disimpan permanen; hanya foto yang dihapus. Jadi rekap minggu lalu tetap bisa dilihat.
- Ekspor CSV.
- **[KEPUTUSAN TERBUKA] Kolom tugas di rekap:**
  (a) tidak ada;
  (b) kolom "Tugas" per siswa untuk minggu itu — dari `submissions.tugas` kalau ada submit; kalau tidak ada submit, dihitung dari rotasi dengan jadwal saat ini (bisa beda dari kenyataan kalau jadwal pernah diubah).
  Kalau (b): ikut di CSV atau hanya di layar?

### F7. Penghapusan Foto Mingguan
- Setiap **Minggu pukul 23:00 WIB**, sistem otomatis:
  1. Mengubah semua submit `menunggu_verifikasi` minggu itu menjadi `kedaluwarsa`.
  2. Menghapus semua file foto minggu itu dari storage dan barisnya di `submission_photos`.
- Dijalankan lewat Laravel Scheduler (butuh cron di server).
- Mulai **Jumat sore** sampai Minggu 23:00: dashboard verifikator menampilkan peringatan "X submit belum diverifikasi, foto akan dihapus Minggu 23:00".
- **[KEPUTUSAN TERBUKA] Arti "Jumat sore":** (a) setelah `jam_tutup_siang` sekolah; (b) jam tetap, mis. 15:00 WIB; (c) sepanjang hari Jumat.
- **[KEPUTUSAN TERBUKA] Jumat libur:** (a) peringatan tetap mulai Jumat sore; (b) peringatan maju ke sore hari sekolah terakhir sebelumnya (mis. Kamis). Jadwal penghapusan tetap Minggu 23:00 apa pun pilihannya.

### F8. Hari Libur
- Admin mengelola daftar hari libur per sekolah (`hari_libur`: tanggal + keterangan). Berlaku untuk semua kelas di sekolah itu.
- Di tanggal libur: tidak ada kewajiban piket; tidak dihitung terjadwal maupun `tidak_piket` di rekap (F6).
- Rotasi tugas **tetap berjalan menurut kalender** selama libur (minggu yang ada liburnya tetap dihitung satu minggu; libur seminggu penuh tetap menggeser tugas).
- Beranda di tanggal libur: "Hari ini libur: {keterangan}. Tidak ada piket."

Keputusan terbuka F8:
- **[KEPUTUSAN TERBUKA] Unik per (school_id, tanggal)?** (a) ya, `UNIQUE(school_id, tanggal)` — satu tanggal satu baris; (b) boleh lebih dari satu baris per tanggal.
- **[KEPUTUSAN TERBUKA] Tanggal yang boleh diisi:**
  - Hari: (a) hanya Senin–Jumat; (b) tanggal apa saja.
  - Tanggal lampau: (a) boleh tambah/hapus kapan saja — rekap minggu lalu ikut berubah karena dihitung ulang; (b) hanya hari ini dan ke depan.
- **[KEPUTUSAN TERBUKA] Keterangan:** wajib atau opsional.
- **[KEPUTUSAN TERBUKA] Edit hari libur:** (a) tidak ada edit, cukup hapus lalu tambah; (b) ada edit.
- **[KEPUTUSAN TERBUKA] Tanggal dijadikan libur padahal sudah ada submit di tanggal itu:** (a) submit tetap tampil dan tetap dihitung di rekap; (b) submit tetap tersimpan tetapi tidak dihitung di rekap; (c) admin ditolak menambah libur di tanggal yang sudah ada submit.

## 4. Di luar MVP (jangan dikerjakan dulu)
- Notifikasi pengingat piket
- Tukar jadwal atau tukar tugas antar siswa
- Peringkat kebersihan antar kelas
- Login dengan OTP atau Google
- Ekspor Excel/PDF
- Hari libur per kelas (MVP: libur berlaku untuk satu sekolah)
- Jenis/urutan tugas yang bisa diatur sekolah (MVP: 3 tugas tetap)

## 5. Status Submit

```
(belum submit) → menunggu_verifikasi → disetujui
                         │            ↘ ditolak → (submit ulang hari itu) → menunggu_verifikasi
                         └→ kedaluwarsa (belum diverifikasi saat penghapusan mingguan)

Jadwal lewat tanpa submit → tidak_piket (dihitung saat rekap, tidak disimpan sebagai baris)
                            — kecuali tanggal libur (F8): tidak ada kewajiban, tidak dihitung
Flag terpisah: terlambat (true/false) + alasan_terlambat
```

## 6. Model Data (MySQL)

```
schools          id, nama, jam_buka_pagi, jam_tutup_pagi, jam_buka_siang, jam_tutup_siang
classes          id, school_id, nama (mis. "XI RPL 1"), wali_kelas_id → users.id,
                 tanggal_mulai_rotasi (date; boleh null atau tidak → [KEPUTUSAN TERBUKA] F2)
users            id, school_id, class_id (null utk guru/admin), nama,
                 email (unik, null boleh utk siswa/pj), nis (unik global, null utk guru/admin),
                 no_absen (null utk guru/admin), password, role, harus_ganti_password,
                 remember_token ("Ingat saya", F1), created_at, updated_at
                 UNIQUE(nis), UNIQUE(class_id, no_absen)
duty_schedules   id, class_id, hari (1=Senin … 5=Jumat, dibatasi CHECK 1–5), user_id,
                 tugas_awal (menyapu|merapikan|mengepel)
                 UNIQUE(class_id, user_id)
submissions      id, user_id, class_id, tanggal,
                 tugas (menyapu|merapikan|mengepel; diisi server saat submit, F3),
                 catatan,
                 status (menunggu_verifikasi|disetujui|ditolak|kedaluwarsa),
                 terlambat (bool), alasan_terlambat,
                 alasan_tolak, verified_by → users.id, verified_at,
                 created_at (waktu server)
submission_photos id, submission_id, file_path, created_at
hari_libur       id, school_id, tanggal, keterangan, created_at, updated_at
                 UNIQUE(school_id, tanggal)? → [KEPUTUSAN TERBUKA] F8
```

Aturan:
- Cegah dua submit aktif per siswa per tanggal: sebelum insert, cek tidak ada submit dengan status `menunggu_verifikasi`/`disetujui` untuk (user_id, tanggal), di dalam transaksi database.
- Tidak ada data yang disimpan dua kali (mis. nama kelas cukup di `classes`).
  - Pengecualian yang disengaja: `submissions.tugas` adalah salinan hasil rotasi saat submit, supaya riwayat dan kartu verifikasi tidak berubah kalau jadwal, `tugas_awal`, atau `tanggal_mulai_rotasi` diubah belakangan.
- **Rotasi tugas:** `URUTAN = [menyapu, merapikan, mengepel]`.
  `tugas(siswa, tanggal) = URUTAN[(posisi(tugas_awal) + n) mod 3]`, dengan `n` = jumlah minggu kalender dari minggu `tanggal_mulai_rotasi` sampai minggu `tanggal` (definisi minggu → [KEPUTUSAN TERBUKA] F2).
  Contoh: `tugas_awal = merapikan` (posisi 1), n = 4 → (1 + 4) mod 3 = 2 → `mengepel`.
  Hari libur tidak menghentikan `n`. Rumus ini dihitung di satu tempat saja dan dipakai oleh beranda, submit, dan rekap.
- **Validasi jadwal (F2):** maks 9 baris per (class_id, hari); maks 3 baris per (class_id, hari, tugas_awal). Cukup dicek dengan `tugas_awal`: semua siswa satu kelas bergeser dengan `n` yang sama, jadi komposisi tugas per hari tetap setiap minggu. Pengecekan dilakukan di dalam transaksi database (PJ dan guru bisa menyimpan bersamaan).
- `duty_schedules.user_id` harus `siswa`/`pj_kebersihan` dengan `users.class_id` = `duty_schedules.class_id` (dicek di server).
- Timezone aplikasi: `Asia/Jakarta`.
- `role` dan `harus_ganti_password` tidak boleh diisi lewat mass assignment; diisi eksplisit oleh kode admin.
- NIS unik **global** (bukan per sekolah), karena halaman login tidak memilih sekolah: satu NIS harus menunjuk tepat satu akun.

Tabel bawaan Laravel (ada dan memang dipakai, tidak termasuk model data aplikasi di atas):
- `sessions` — sesi login (`SESSION_DRIVER=database`).
- `cache`, `cache_locks` — cache, termasuk penghitung batas percobaan login F1 (`CACHE_STORE=database`).
- `jobs`, `job_batches`, `failed_jobs` — antrean tugas (`QUEUE_CONNECTION=database`).
- Tabel `password_reset_tokens` **tidak** dipakai: reset password dilakukan admin (F1), bukan lewat email.

## 7. Halaman & Route

Aplikasi berbasis halaman (Laravel + Blade), bukan API terpisah. Semua route kecuali login butuh login.

| Method | Route | Role | Keterangan |
|---|---|---|---|
| GET | / | publik | alihkan ke `/login`, atau ke halaman awal role (F1) kalau sudah login |
| GET/POST | /login | publik | satu halaman login untuk semua role |
| POST | /logout | semua | |
| GET/POST | /ganti-password | semua | wajib saat login pertama |
| GET | /beranda | siswa, pj | status piket hari ini (+ tugas) + tombol submit |
| GET/POST | /piket/submit | siswa, pj | form submit (foto, catatan, alasan terlambat bila perlu) |
| GET | /piket/riwayat | siswa, pj | riwayat submit sendiri |
| GET | /verifikasi | pj, guru | antrean submit kelasnya |
| POST | /verifikasi/{id} | pj, guru | setujui/tolak |
| GET | /rekap?minggu= | pj, guru, admin | rekap mingguan; `&format=csv` untuk ekspor |
| GET/POST/DELETE | /jadwal | pj, guru, admin | kelola jadwal kelasnya (hari + tugas awal); bentuk POST/DELETE → [KEPUTUSAN TERBUKA] F2 "Cara simpan jadwal" |
| GET | /admin | admin | dashboard admin |
| GET/POST | /admin/kelas | admin | kelola kelas |
| GET/POST | /admin/akun | admin | kelola & import akun (CSV), reset password |
| GET/POST | /admin/pengaturan | admin | jam buka/tutup |
| (usulan) GET/POST | /admin/hari-libur | admin | kelola hari libur (F8) — lihat [KEPUTUSAN TERBUKA] di bawah |

- **[KEPUTUSAN TERBUKA] Route halaman hari libur.** Usulan: `GET /admin/hari-libur` (daftar + form), `POST /admin/hari-libur` (tambah), `DELETE /admin/hari-libur/{id}` (hapus). Kalau ada edit (F8): tambah `PUT /admin/hari-libur/{id}`. Pilihan lain: ikut pola tabel ini (`GET/POST` saja, hapus lewat POST). Nama dan method wajib final sebelum dikerjakan (aturan kontrak route).

Proteksi: middleware `auth` + middleware role di setiap grup route. Semua route `/admin/*` hanya untuk `admin`.

Pesan error ke user dalam bahasa Indonesia, mis. "Kamu tidak terjadwal piket hari ini", "Alasan keterlambatan wajib diisi", "Hari Senin sudah penuh (maks 9 siswa)", "Tugas Menyapu di hari Senin sudah 3 siswa".

## 8. Layar

| Layar | Role | Isi utama |
|---|---|---|
| Login | semua | NIS atau email, password, checkbox "Ingat saya" (default tidak dicentang) |
| Ganti password | semua | password lama, baru, konfirmasi |
| Beranda | siswa, pj | "Hari ini kamu piket — tugas: X" / tidak piket / libur, jam tepat waktu, tombol Submit, status submit hari ini |
| Form submit | siswa, pj | tugas hari ini (teks, tidak bisa diubah), ambil foto, catatan, field alasan terlambat (muncul hanya jika sudah lewat jam) |
| Riwayat | siswa, pj | daftar submit + tugas + status + alasan tolak |
| Antrean verifikasi | pj, guru | kartu submit: nama, tugas, foto, jam, tanda terlambat + alasan, tombol setujui/tolak; peringatan penghapusan foto (mulai Jumat sore) |
| Rekap mingguan | pj, guru, admin | pilih minggu (Senin–Jumat) + tabel per siswa + keterangan tanggal libur minggu itu + ekspor CSV; kolom tugas → [KEPUTUSAN TERBUKA] F6 |
| Kelola jadwal | pj, guru, admin | per siswa: dropdown hari + dropdown tugas awal; ringkasan isi per hari × tugas |
| Admin | admin | ringkasan semua kelas, menu kelas/akun/pengaturan/hari libur |
| Hari libur | admin | form tambah (tanggal, keterangan) + daftar hari libur + tombol hapus |

Desain: mobile-first, bahasa Indonesia, tombol besar. Halaman admin boleh lebih padat (dipakai di laptop).

### 8.1 Detail layar yang berubah di v0.5

Halaman dirender server (Blade), jadi tidak ada state "loading" terpisah; cukup cegah klik ganda pada tombol simpan/submit (nonaktifkan + teks "Menyimpan…").

**Beranda (siswa, pj)** — urutan:
1. Nama, kelas, tanggal hari ini.
2. Kartu status hari ini — tepat satu dari:
   - terjadwal: "Hari ini kamu piket — tugas: Menyapu" + jam tepat waktu + tombol besar "Submit piket";
   - tidak terjadwal hari ini: "Hari ini kamu tidak piket. Jadwalmu: setiap Rabu.";
   - belum punya jadwal: "Kamu belum punya jadwal piket. Hubungi PJ kebersihan atau wali kelas.";
   - libur: "Hari ini libur: {keterangan}. Tidak ada piket.";
   - Sabtu/Minggu: "Tidak ada piket hari ini."
3. Status submit hari ini (menunggu / disetujui / ditolak + alasan + tombol submit ulang).
4. Tautan ke Riwayat.
- Error: tugas tidak bisa dihitung (mis. `tanggal_mulai_rotasi` kosong, tergantung [KEPUTUSAN TERBUKA] F2) → "Tugas piket belum diatur. Hubungi wali kelas."

**Kelola jadwal (pj, guru, admin)** — dipakai PJ di HP:
1. Nama kelas + tanggal mulai rotasi (hanya-baca atau bisa diubah → [KEPUTUSAN TERBUKA] F2).
2. Ringkasan Senin–Jumat: jumlah siswa per hari (x/9), dirinci per tugas minggu ini (x/3).
3. Daftar siswa kelas, urut no. absen: nama, dropdown hari, dropdown tugas awal (+ pratinjau "Tugas minggu ini: X" kalau dipilih opsi (a) di F2).
4. Daftar "Belum terjadwal" (kalau dipilih opsi (a) di F2).
5. Tombol Simpan besar, menempel di bawah layar.
- Kosong (belum ada jadwal): semua siswa tampil sebagai belum terjadwal + petunjuk singkat cara mengisi.
- Error validasi: pesan di atas daftar, hari/tugas yang melebihi batas disorot, isian tidak hilang.
- Sukses: "Jadwal tersimpan."

**Antrean verifikasi (pj, guru)**: kartu menampilkan tugas di bawah nama. Banner peringatan F7 muncul mulai Jumat sore. Kosong: "Tidak ada submit yang menunggu verifikasi."

**Rekap mingguan**: minggu = Senin–Jumat. Kalau ada libur di minggu itu, tampilkan baris keterangan "Libur: Rabu, 11 Nov — {keterangan}" di atas tabel. Kosong (kelas belum punya jadwal): "Belum ada jadwal piket untuk kelas ini."

**Hari libur (admin)** — urutan:
1. Judul "Hari Libur".
2. Form tambah: tanggal (date picker), keterangan, tombol "Tambah".
3. Daftar hari libur urut tanggal (yang akan datang di atas): hari + tanggal, keterangan, tombol "Hapus" (dengan konfirmasi).
- Kosong: "Belum ada hari libur."
- Error validasi: tanggal wajib; tanggal sudah terdaftar (kalau unik); aturan tanggal lain sesuai [KEPUTUSAN TERBUKA] F8.
- Sukses: "Hari libur ditambahkan." / "Hari libur dihapus."

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
- Bahasa aplikasi: `APP_LOCALE=id` (pesan validasi bahasa Indonesia di `lang/id`).
- **Ditinjau ulang nanti:** tes otomatis sementara memakai SQLite in-memory (lebih cepat, tanpa setup). SQLite tidak menjalankan CHECK constraint `hari` (1–5) dan beberapa perilaku MySQL lain — pertimbangkan pindah ke database tes MySQL sebelum fitur yang bergantung pada perilaku khusus MySQL. Batas hari 1–5 dan nilai enum tugas tetap wajib divalidasi juga di aplikasi.

## 11. Urutan Pengerjaan
1. Setup proyek Laravel + koneksi MySQL + migrasi semua tabel + seeder data contoh (1 sekolah, 2 kelas, akun tiap role)
2. F1 Login + redirect per role + middleware role + ganti password
3. Migrasi perubahan v0.5 — **migration baru**, jangan ubah migration Langkah 1 yang sudah dijalankan: CHECK `hari` 1–5, `duty_schedules.tugas_awal` + ganti UNIQUE jadi (class_id, user_id), `classes.tanggal_mulai_rotasi`, `submissions.tugas`, tabel `hari_libur`; sesuaikan seeder.
   Butuh keputusan dulu: null/tidak `tanggal_mulai_rotasi` (F2), UNIQUE `hari_libur` (F8).
   Selesai kalau: `php artisan migrate` jalan tanpa error di database yang sudah berisi data Langkah 1–2; insert `hari = 6` ditolak MySQL; insert dua jadwal untuk (class_id, user_id) yang sama ditolak.
4. Halaman admin: kelola kelas & akun (+ isian `tanggal_mulai_rotasi` kalau diputuskan diisi admin)
5. Halaman admin: kelola hari libur (F8). Butuh keputusan: route (§7) + semua butir F8.
   Selesai kalau: admin bisa tambah & hapus libur; role lain mendapat 403; daftar kosong menampilkan "Belum ada hari libur."
6. F2 Jadwal piket + tugas awal + rotasi.
   Selesai kalau (contoh tes, `tanggal_mulai_rotasi` = Senin 2 Nov 2026, `tugas_awal` = menyapu): tugas 2–6 Nov = menyapu, 9 Nov = merapikan, 16 Nov = mengepel, 23 Nov = menyapu; simpan siswa ke-10 di satu hari ditolak; simpan siswa ke-4 dengan tugas yang sama di satu hari ditolak; pilihan hari Sabtu tidak ada dan ditolak server.
7. F3 + F4 Submit dengan batas waktu & keterlambatan (+ `submissions.tugas` diisi server, aturan tanggal libur).
   Selesai kalau: `submissions.tugas` sesuai rotasi walau form dikirim dengan nilai tugas lain; perilaku di tanggal libur sesuai keputusan F3.
8. F5 Verifikasi (kartu menampilkan tugas)
9. F6 Rekap mingguan + CSV (Senin–Jumat, tanggal libur dikecualikan).
   Selesai kalau: siswa terjadwal Rabu, Rabu libur, tanpa submit → terjadwal 0 dan tidak piket 0 untuk minggu itu; minggu berikutnya tugasnya tetap bergeser satu langkah.
10. F7 Penghapusan foto mingguan (scheduler) + peringatan mulai Jumat sore
11. Uji coba dengan 1 kelas sungguhan selama 1 minggu (Senin–Jumat)
