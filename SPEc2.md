# SPEC — Aplikasi Absensi Piket Kelas

> Dokumen ini adalah sumber kebenaran tunggal. Semua kode harus sesuai dengan dokumen ini.
> Kalau ada perubahan fitur, ubah dokumen ini DULU, baru kode.

Status: DRAFT v0.10
Bagian bertanda **[KEPUTUSAN TERBUKA]** belum final — putuskan sebelum fitur terkait dikerjakan.

Perubahan v0.5 (keputusan pemilik proyek): piket hanya Senin–Jumat; tugas piket per siswa (menyapu / merapikan meja & kursi / mengepel) yang berputar tiap minggu; hari libur sekolah (F8).

Perubahan v0.6 (keputusan pemilik proyek): `tanggal_mulai_rotasi` wajib diisi; `hari_libur` unik per (sekolah, tanggal); aturan kelola kelas & akun oleh admin (F9); route admin tambahan (§7); urutan pengerjaan §11 diperbarui.

Perubahan v0.7 (keputusan pemilik proyek): nilai enum tugas final; `tanggal_mulai_rotasi` hanya diubah admin, wajib hari Senin, minggu = Senin–Minggu, sebelum tanggal itu jadwal belum berlaku; keterangan hari libur wajib; ubah role hanya siswa ↔ PJ.

Perubahan v0.8 (keputusan pemilik proyek): `tanggal_mulai_rotasi` terkunci setelah kelas punya submit; pesan & prioritas beranda (F3, §8.1); nama kelas unik per sekolah; konfirmasi pindah kelas hanya kalau ada jadwal; password acak minimal 1 huruf + 1 angka, ditampilkan sekali lewat flash; `school_id` tidak bisa diisi lewat mass assignment; route form ubah kelas (§7).

Perubahan v0.9 (keputusan pemilik proyek): hapus akun oleh admin (menggantikan "tidak ada hapus akun"); maks 2 admin per sekolah; isian kembar yang lolos validasi bersamaan tampil sebagai pesan validasi; sesi dienkripsi (`SESSION_ENCRYPT=true`); kolom tugas (`duty_schedules.tugas_awal`, `submissions.tugas`) di MySQL disimpan sebagai teks + CHECK, bukan ENUM, supaya nilai kosong ditolak database (§6).

Perubahan v0.10 (keputusan pemilik proyek): import akun CSV diputuskan (F9) — hanya siswa/PJ; satu baris salah → seluruh file ditolak; daftar password awal diunduh sekali sebagai CSV (pemisah titik koma + BOM UTF-8) dari halaman daftar akun; route `POST /admin/akun/import/password` (§7).

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
| `admin` | Pengelola sekolah | Kelola kelas, akun, pengaturan jam, hari libur, jadwal piket semua kelas; lihat rekap semua kelas |

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
  - Harus tetap berlaku walaupun penyimpanan sesi bukan database (mis. `SESSION_DRIVER=file` di hosting): pakai middleware bawaan Laravel `AuthenticateSession` (sesi yang dibuat dengan password lama otomatis keluar).
- Setiap kolom password (login; ganti password: lama, baru, konfirmasi) punya tombol sendiri untuk menampilkan/menyembunyikan isinya. Default tersembunyi. Label untuk pembaca layar berubah sesuai kondisi: "Tampilkan password" / "Sembunyikan password".
- Batasi percobaan login gagal (maks 5 kali per menit per NIS/email + IP).

### F2. Jadwal Piket & Tugas
- Guru/PJ kebersihan/admin mengatur jadwal per kelas: untuk setiap siswa dipilih **hari** dan **tugas awal** (keduanya dropdown).
- Hari piket: **Senin–Jumat** (hari 1–5). Sabtu dan Minggu tidak ada piket.
- Setiap siswa terjadwal di **tepat satu hari** per minggu (dijaga `UNIQUE(class_id, user_id)`).
- Yang boleh dijadwalkan hanya `siswa`/`pj_kebersihan` dengan `users.class_id` = kelas itu.
- Tiga jenis tugas. Nilai enum → label tampilan (**final**, v0.7):
  - `menyapu` → "Menyapu"
  - `merapikan` → "Merapikan meja & kursi"
  - `mengepel` → "Mengepel"
- Tugas berputar otomatis setiap minggu: menyapu → merapikan → mengepel → menyapu. Dihitung dari `classes.tanggal_mulai_rotasi` dan jumlah minggu kalender sejak tanggal itu (rumus di §6 Aturan).
- Validasi saat simpan jadwal (di server):
  - maks **9 siswa per hari** per kelas → "Hari Senin sudah penuh (maks 9 siswa)";
  - maks **3 siswa per tugas per hari** → "Tugas Menyapu di hari Senin sudah 3 siswa".
- Beranda siswa: "Hari ini kamu piket — tugas: X" kalau terjadwal hari ini, hari ini bukan hari libur (F8), dan jadwal sudah berlaku (tanggal ≥ `tanggal_mulai_rotasi`).

Keputusan F2 (yang sudah diputuskan ditandai "diputuskan"):
- **[KEPUTUSAN TERBUKA] "Tepat satu hari"** — boleh simpan kalau masih ada siswa yang belum terjadwal?
  (a) boleh; layar menampilkan daftar "Belum terjadwal";
  (b) tidak boleh; semua siswa kelas wajib terjadwal. Catatan: kapasitas maks 5 × 9 = 45 siswa — kelas > 45 siswa tidak bisa memenuhi (b).
- **`classes.tanggal_mulai_rotasi` — diputuskan:**
  - Wajib diisi (NOT NULL, v0.6). Untuk kelas baru, diisi admin saat menambah kelas (F9). Kelas yang sudah ada sebelum migration Langkah 3 diisi oleh migration/seeder.
  - Hanya **admin** yang boleh mengubahnya, di `/admin/kelas` (v0.7). Guru/PJ hanya melihat. Mengubahnya menggeser tugas semua siswa kelas itu.
  - **Terkunci setelah kelas punya submit** (v0.8): boleh diubah hanya selama kelas belum punya satu pun baris `submissions` (status apa pun, termasuk ditolak/kedaluwarsa). Setelah itu kolom tampil terkunci di form ubah kelas dengan alasan "Tanggal mulai rotasi tidak bisa diubah karena kelas ini sudah punya riwayat piket.", dan server menolak perubahannya dengan pesan yang sama.
  - **Wajib hari Senin** (v0.7) → "Tanggal mulai rotasi harus hari Senin."
  - **Minggu kalender = Senin–Minggu** (v0.7). Minggu ke-0 = Senin `tanggal_mulai_rotasi` sampai Minggu berikutnya; tugas bergeser setiap Senin.
  - **Sebelum `tanggal_mulai_rotasi`: jadwal belum berlaku** (v0.7) — tidak ada kewajiban piket, tidak bisa submit, tidak dihitung terjadwal maupun `tidak_piket` di rekap. Beranda: "Jadwal piket mulai berlaku {tanggal}."
- **[KEPUTUSAN TERBUKA] Arti pilihan di dropdown tugas awal.** Secara harfiah `tugas_awal` = tugas di minggu ke-0 (minggu `tanggal_mulai_rotasi`). Akibatnya, kalau jadwal diatur di minggu ke-4 dan dipilih "Menyapu", minggu ini siswa justru mendapat "Merapikan".
  (a) tetap harfiah; form menampilkan pratinjau "Tugas minggu ini: X" di samping dropdown;
  (b) dropdown berarti "tugas minggu ini"; server menghitung mundur dan menyimpan `tugas_awal` yang setara.
  Untuk kedua opsi: sebelum `tanggal_mulai_rotasi` belum ada "minggu ini", jadi pilihan dropdown = tugas minggu ke-0 dan disimpan apa adanya sebagai `tugas_awal`.
- **[KEPUTUSAN TERBUKA] Jadwal atau `tugas_awal` diubah di tengah minggu.** Tugas dan rekap dihitung dari jadwal saat ini, jadi perubahan langsung berlaku — termasuk untuk hari yang sudah lewat dan rekap minggu-minggu lalu (masalah ini sudah ada sejak v0.4). Siswa yang dipindah dari Senin (sudah lewat) ke Kamis bisa piket dua kali, atau tidak sama sekali, minggu itu. Submit yang sudah ada tetap memakai `submissions.tugas`.
  (a) berlaku langsung, terima efeknya ke rekap;
  (b) berlaku mulai minggu depan — butuh kolom tambahan (mis. `berlaku_mulai`) yang belum ada di model data;
  (c) larang perubahan untuk siswa yang sudah submit minggu ini, atau hanya boleh diubah Sabtu–Minggu.
- **Siswa pindah kelas — diputuskan (v0.6):** saat admin mengubah `users.class_id`, baris `duty_schedules` siswa itu di kelas lama **otomatis dihapus**; siswa muncul sebagai "Belum terjadwal" di kelas baru. Sebelum menyimpan, admin melihat konfirmasi: "Jadwal piket siswa ini di kelas lama akan dihapus." (lihat F9). Submit lama tetap tersimpan dengan `class_id` kelas lama.
- **[KEPUTUSAN TERBUKA] Cara simpan jadwal:**
  (a) sekaligus satu kelas (satu POST berisi semua siswa; batas dicek pada hasil akhir — memudahkan tukar hari/tugas antar siswa);
  (b) per siswa (POST tambah/ubah satu siswa, DELETE hapus satu) — tukar di hari yang sudah penuh harus lewat dua langkah.

### F3. Submit Piket
- Hanya siswa (termasuk PJ) yang terjadwal hari itu (Senin–Jumat) yang bisa submit, dan hanya kalau jadwal sudah berlaku (tanggal ≥ `tanggal_mulai_rotasi`, F2). Submit sebelum tanggal itu ditolak server dengan pesan "Jadwal piket mulai berlaku {tanggal}." (v0.8).
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
- Setelah 23:59 → tidak bisa submit; tercatat `tidak_piket` — **kecuali tanggal libur** (F8) **dan tanggal sebelum `tanggal_mulai_rotasi`** (F2).
- Sebelum jendela pertama buka → tombol submit nonaktif + tampilkan jam buka.
- Semua validasi waktu dilakukan di server.

### F5. Verifikasi
- Verifikator melihat daftar submit `menunggu_verifikasi` di kelasnya, dengan **tugas** (`submissions.tugas`) dan tanda "Terlambat" + alasannya bila ada.
- Aksi: `disetujui` atau `ditolak` (alasan wajib diisi kalau ditolak).
- Siswa melihat status + alasan penolakan.
- Submit yang belum diverifikasi saat foto dihapus mingguan (lihat F7) → status otomatis `kedaluwarsa`.

### F6. Rekap Mingguan
- Rekap per kelas per minggu (**Senin–Jumat**): per siswa → jumlah terjadwal, disetujui, terlambat, ditolak, tidak piket, kedaluwarsa.
- Tanggal libur (F8) dan tanggal sebelum `tanggal_mulai_rotasi` (F2) **tidak dihitung** sebagai terjadwal maupun `tidak_piket`.
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

Keputusan F8 (yang sudah diputuskan ditandai "diputuskan"):
- **Diputuskan (v0.6):** `UNIQUE(school_id, tanggal)` — satu tanggal satu baris per sekolah. Pesan error: "Tanggal ini sudah terdaftar sebagai hari libur."
- **[KEPUTUSAN TERBUKA] Tanggal yang boleh diisi:**
  - Hari: (a) hanya Senin–Jumat; (b) tanggal apa saja.
  - Tanggal lampau: (a) boleh tambah/hapus kapan saja — rekap minggu lalu ikut berubah karena dihitung ulang; (b) hanya hari ini dan ke depan.
- **Keterangan wajib diisi** (v0.7) — dicek di validasi form; kolom database tetap boleh kosong. → "Keterangan wajib diisi."
- **[KEPUTUSAN TERBUKA] Edit hari libur:** (a) tidak ada edit, cukup hapus lalu tambah; (b) ada edit.
- **[KEPUTUSAN TERBUKA] Tanggal dijadikan libur padahal sudah ada submit di tanggal itu:** (a) submit tetap tampil dan tetap dihitung di rekap; (b) submit tetap tersimpan tetapi tidak dihitung di rekap; (c) admin ditolak menambah libur di tanggal yang sudah ada submit.

### F9. Kelola Kelas & Akun (admin)
- Semua data yang dilihat & diubah admin dibatasi ke sekolahnya sendiri (`school_id` admin).
- **Dashboard `/admin`:** tabel semua kelas (nama, wali kelas, PJ kebersihan, jumlah siswa) + menu Kelas, Akun, Pengaturan, Hari libur. Menu ke halaman yang belum dibuat tetap tampil tetapi tidak bisa diklik, dengan tanda "segera".
- **Kelas:** tambah (nama, wali kelas, `tanggal_mulai_rotasi`), ubah (nama, wali kelas, `tanggal_mulai_rotasi` — terkunci setelah kelas punya submit, F2), hapus. Hanya admin yang boleh mengubah `tanggal_mulai_rotasi` (F2).
  - **Nama kelas unik per sekolah** (v0.8) → "Nama kelas sudah dipakai di sekolah ini."
  - Wali kelas boleh kosong; pilihan wali hanya akun `guru` di sekolah itu. Satu guru boleh wali lebih dari satu kelas (§2).
  - Karena `tanggal_mulai_rotasi` wajib (F2), form tambah kelas wajib mengisinya, dan tanggalnya harus hari Senin (F2).
  - Hapus kelas hanya kalau kelas kosong (tanpa siswa, jadwal, dan submit); selain itu ditolak dengan pesan "Kelas ini masih punya siswa atau riwayat piket." Khusus aksi **hapus kelas**: kalau siswa/jadwal/submit ditambahkan bersamaan, hapus kelas ditolak dengan pesan yang sama (dicek di dalam transaksi database; bukan halaman error, v0.9). Aturan ini hanya untuk sisi hapus kelas; form akun tidak punya penanganan khusus untuk kelas yang terhapus bersamaan.
- **Akun:** tambah satu per satu, ubah, reset password, hapus (v0.9, menggantikan keputusan lama "tidak ada hapus akun").
  - **Hapus guru, siswa, PJ:** hanya kalau akun belum punya riwayat — siswa/PJ: belum punya submit (PJ juga: belum pernah memverifikasi submit — PJ adalah verifikator, dan database menolak menghapus akun yang tercatat di `submissions.verified_by`); guru: belum pernah memverifikasi submit dan tidak sedang menjadi wali kelas. Kalau sudah punya riwayat, tombol hapus tidak tampil, diganti teks "Akun ini sudah punya riwayat, jadi tidak bisa dihapus. Ubah datanya jika ada yang salah.", dan server juga menolak dengan pesan yang sama. Riwayat piket tetap disimpan permanen (F6).
  - **Admin:** maks **2 admin per sekolah** → menambah admin ke-3 ditolak dengan "Admin sudah mencapai batas maksimal (2)." (dicek di dalam transaksi database, supaya dua penyimpanan bersamaan tidak menghasilkan 3 admin). Admin boleh menghapus admin lain, tidak boleh menghapus dirinya sendiri (tombol tidak tampil & ditolak server), dan minimal harus tersisa 1 admin di sekolah itu (juga dicek di dalam transaksi). Admin boleh mereset password admin lain.
  - **Berlaku untuk semua:** konfirmasi sebelum menghapus: "Hapus akun {nama}? Tindakan ini tidak bisa dibatalkan." Jadwal piket milik akun itu ikut terhapus, begitu juga sesi login-nya.
  - `siswa`/`pj_kebersihan`: nama, NIS, kelas, no. absen; tanpa email. `guru`/`admin`: nama, email; tanpa NIS, kelas, no. absen.
  - Maks **2 PJ kebersihan per kelas** → "Kelas {nama} sudah punya 2 PJ kebersihan."
  - **Password awal & hasil reset:** dibuat sistem secara acak, **8 karakter, huruf kecil + angka, tanpa karakter yang mirip (`0`, `o`, `1`, `l`, `i`)**. Ditampilkan **sekali** ke admin setelah simpan/reset, lalu tidak bisa dilihat lagi. User wajib ganti password saat login berikutnya (F1).
    - Minimal 1 huruf dan 1 angka (v0.8). Dibuat dengan pembangkit acak yang aman (`random_int`).
    - Ditampilkan lewat pesan sesi sekali pakai (flash) di halaman berikutnya (v0.8); setelah halaman itu dibuka, password hilang dari sesi.
  - Reset password mengeluarkan user dari semua perangkat (F1).
  - Admin **tidak bisa mereset password akunnya sendiri** (tombol tidak ada & ditolak server); admin memakai `/ganti-password`. Admin boleh mereset password admin lain.
- **Isian kembar** (nama kelas, NIS, email, no. absen) yang lolos validasi karena dua penyimpanan bersamaan lalu ditolak database (UNIQUE) ditampilkan sebagai pesan validasi biasa di kolom yang bersangkutan, mis. "NIS sudah dipakai." — bukan halaman error (v0.9).
- **Data yang baru saja dihapus** (kelas/akun yang dibuka, disimpan, direset, atau dihapus lewat URL setelah dihapus admin lain) → halaman 404 berbahasa Indonesia "Halaman tidak ditemukan" dengan tautan kembali ke halaman awal (v0.9). Halaman yang sama juga tampil untuk kelas/akun milik sekolah lain dan alamat yang tidak ada.
  - Sama halnya kalau guru yang dipilih sebagai wali kelas baru saja dihapus admin lain bersamaan: simpan kelas ditolak dengan pesan di kolom wali kelas "Wali kelas harus akun guru di sekolah ini." (v0.9).
  - **Pindah kelas** siswa/PJ: kalau siswa punya jadwal di kelas lama, tampilkan konfirmasi "Jadwal piket siswa ini di kelas lama akan dihapus." dengan centang "Ya, hapus jadwal lama" yang dicek server; tanpa centang, perubahan tidak disimpan. Kalau siswa belum punya jadwal, tidak perlu konfirmasi (v0.8). Kelas tujuan harus di sekolah yang sama dan tetap memenuhi maks 2 PJ & no. absen unik; setelah disimpan, jadwal di kelas lama dihapus (F2).
  - **Ubah role** (v0.7): hanya antara `siswa` ↔ `pj_kebersihan` (cara menunjuk/mengganti PJ, tetap maks 2 per kelas). Role `guru`/`admin` tidak bisa diubah, dan siswa/PJ tidak bisa diubah menjadi guru/admin.
- **Import akun CSV** (diputuskan v0.10, Langkah 5 §11). Form upload ada di halaman daftar akun (`/admin/akun`).
  - **Hanya `siswa`/`pj_kebersihan`.** Guru dan admin tetap ditambah lewat form.
  - **Format file:** CSV UTF-8 (BOM dari Excel boleh), pemisah koma atau titik koma (dikenali dari baris judul), maks **1 MB** dan maks **50 baris akun** per file. Batas 50 karena setiap password di-hash satu per satu (± 0,4 detik per akun); file lebih besar dipecah, mis. satu file per kelas.
  - **Baris pertama = baris judul** berisi kolom `nama`, `nis`, `kelas`, `no_absen`, dan boleh `role` (urutan bebas, huruf besar/kecil tidak dibedakan). Kolom lain atau kolom wajib yang hilang → file ditolak: "Baris judul harus berisi kolom: nama, nis, kelas, no_absen (kolom role boleh ada)."
    - `kelas` = nama kelas di sekolah ini (huruf besar/kecil tidak dibedakan), mis. `XI RPL 1`.
    - `role` = `siswa` atau `pj_kebersihan`; kosong (atau kolom tidak ada) = `siswa`.
    - Baris yang seluruhnya kosong dilewati.
    Contoh:
    ```
    nama,nis,kelas,no_absen,role
    Budi Santoso,12345,XI RPL 1,1,
    Siti Aminah,12346,XI RPL 1,2,pj_kebersihan
    ```
  - **Aturan tiap baris** sama dengan form tambah akun: nama wajib (maks 100), NIS wajib (huruf/angka, maks 30) & unik global, kelas harus ada di sekolah ini, no. absen 1–255 & unik per kelas, maks 2 PJ per kelas (PJ yang sudah ada + PJ di file). NIS kembar atau no. absen kembar (kelas sama) **di dalam file itu sendiri** juga salah.
  - **Ada satu saja baris salah → seluruh file ditolak**, tidak ada akun yang dibuat. Admin melihat daftar kesalahan per baris, mis. "Baris 5: NIS sudah dipakai.", memperbaiki file, lalu upload ulang. Semua akun dari satu file disimpan dalam satu transaksi database (tersimpan semua atau tidak sama sekali).
    - Isian kembar dari penyimpanan bersamaan yang lolos pengecekan lalu ditolak database (UNIQUE) → file ditolak dengan pesan biasa "Ada NIS atau no. absen di file ini yang baru saja dipakai akun lain. Upload ulang file untuk melihat barisnya." (bukan halaman error, sama seperti aturan isian kembar di bawah).
    - Penyimpanan bersamaan lain, dicek ulang di dalam transaksi dengan baris kelas dikunci: kelas baru saja dihapus → "Ada kelas di file ini yang baru saja dihapus. Upload ulang file untuk melihat barisnya."; PJ kelas sudah penuh → "Kelas {nama} sudah punya 2 PJ kebersihan. Upload ulang file untuk melihat barisnya."
    - Pesan format file: bukan UTF-8 → "File harus disimpan sebagai CSV UTF-8. Di Excel: Simpan Sebagai → "CSV UTF-8 (Comma delimited)"."; tanpa baris akun → "File tidak berisi baris akun."; > 50 baris → "File berisi {n} baris akun, maks 50 per file. Pecah menjadi beberapa file, mis. satu file per kelas."; baris dengan sel terisi melebihi baris judul → "Baris {n}: jumlah kolom lebih banyak dari baris judul." Sel kosong di ujung baris (termasuk baris judul, sering ditambahkan Excel) diabaikan.
    - Request import dan unduh di sesi yang sama dijalankan bergantian (kunci sesi), supaya import kedua (mis. dari tab lain) tidak menimpa sesi dan menghapus daftar password import pertama.
  - Password setiap akun dibuat dengan aturan **password awal** di atas; semua akun hasil import wajib ganti password saat login pertama (F1).
  - **Daftar password awal diunduh sekali:**
    - Setelah import berhasil, admin kembali ke daftar akun dengan pesan "{X} akun dibuat." dan kotak "Unduh daftar password" (tombol, `POST /admin/akun/import/password`). Isi file CSV: `nama, kelas, no_absen, nis, password`, urut kelas lalu no. absen; pemisah **titik koma** (`;`) dan **BOM UTF-8** di awal file, supaya langsung terbuka rapi per kolom di Excel berbahasa Indonesia.
    - Daftar password disimpan **hanya di sesi admin** (sesi dienkripsi, §6), tidak pernah ditulis ke file di server. Setelah diunduh, daftar dihapus dari sesi; kalau tombol dikirim lagi → "Daftar password sudah diunduh atau sudah tidak tersedia. Reset password akun yang belum tercatat."
    - Kalau tidak diunduh (sesi habis, logout), daftar hilang. Admin memakai **reset password** per akun.
    - Selama daftar belum diunduh, import baru ditolak: "Unduh dulu daftar password dari import sebelumnya." (supaya daftar lama tidak tertimpa).

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
                            — kecuali tanggal libur (F8) dan tanggal sebelum tanggal_mulai_rotasi (F2):
                              tidak ada kewajiban, tidak dihitung
Flag terpisah: terlambat (true/false) + alasan_terlambat
```

## 6. Model Data (MySQL)

```
schools          id, nama, jam_buka_pagi, jam_tutup_pagi, jam_buka_siang, jam_tutup_siang
classes          id, school_id, nama (mis. "XI RPL 1"), wali_kelas_id → users.id,
                 tanggal_mulai_rotasi (date, wajib / NOT NULL — F2)
                 UNIQUE(school_id, nama)
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
hari_libur       id, school_id, tanggal,
                 keterangan (nullable di DB; wajib diisi lewat validasi form, F8),
                 created_at, updated_at
                 UNIQUE(school_id, tanggal)
```

Aturan:
- Cegah dua submit aktif per siswa per tanggal: sebelum insert, cek tidak ada submit dengan status `menunggu_verifikasi`/`disetujui` untuk (user_id, tanggal), di dalam transaksi database.
- Tidak ada data yang disimpan dua kali (mis. nama kelas cukup di `classes`).
  - Pengecualian yang disengaja: `submissions.tugas` adalah salinan hasil rotasi saat submit, supaya riwayat dan kartu verifikasi tidak berubah kalau jadwal, `tugas_awal`, atau `tanggal_mulai_rotasi` diubah belakangan.
- **Rotasi tugas:** `URUTAN = [menyapu, merapikan, mengepel]`.
  `tugas(siswa, tanggal) = URUTAN[(posisi(tugas_awal) + n) mod 3]`, dengan `n` = jumlah minggu kalender dari minggu `tanggal_mulai_rotasi` sampai minggu `tanggal`, dengan minggu = Senin–Minggu (F2). `tanggal_mulai_rotasi` selalu hari Senin, jadi `n` = jumlah hari sejak `tanggal_mulai_rotasi` dibagi 7, dibulatkan ke bawah. Tanggal sebelum `tanggal_mulai_rotasi` tidak punya tugas (jadwal belum berlaku).
  Contoh: `tugas_awal = merapikan` (posisi 1), n = 4 → (1 + 4) mod 3 = 2 → `mengepel`.
  Hari libur tidak menghentikan `n`. Rumus ini dihitung di satu tempat saja dan dipakai oleh beranda, submit, dan rekap.
- **Validasi jadwal (F2):** maks 9 baris per (class_id, hari); maks 3 baris per (class_id, hari, tugas_awal). Cukup dicek dengan `tugas_awal`: semua siswa satu kelas bergeser dengan `n` yang sama, jadi komposisi tugas per hari tetap setiap minggu. Pengecekan dilakukan di dalam transaksi database (PJ dan guru bisa menyimpan bersamaan).
- **Kolom tugas** (`duty_schedules.tugas_awal`, `submissions.tugas`) di MySQL bertipe teks `VARCHAR(20) NOT NULL` + CHECK nilai `menyapu`/`merapikan`/`mengepel` (v0.9), **bukan ENUM**: kolom ENUM NOT NULL di MySQL diam-diam terisi nilai pertama (`menyapu`) kalau kode lupa mengisinya. Dengan teks + CHECK, nilai kosong maupun nilai lain ditolak database.
- `duty_schedules.user_id` harus `siswa`/`pj_kebersihan` dengan `users.class_id` = `duty_schedules.class_id` (dicek di server).
- Timezone aplikasi: `Asia/Jakarta`.
- `role` dan `harus_ganti_password` tidak boleh diisi lewat mass assignment; diisi eksplisit oleh kode admin.
- `school_id` di `users` dan `classes` tidak boleh diisi lewat mass assignment (v0.8); diisi dari sekolah admin yang login (mis. `$school->kelas()->create(...)`).
- NIS unik **global** (bukan per sekolah), karena halaman login tidak memilih sekolah: satu NIS harus menunjuk tepat satu akun.

Tabel bawaan Laravel (ada dan memang dipakai, tidak termasuk model data aplikasi di atas):
- `sessions` — sesi login (`SESSION_DRIVER=database`), isinya dienkripsi (`SESSION_ENCRYPT=true`, v0.9).
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
| GET | /admin | admin | dashboard admin (F9) |
| GET/POST | /admin/kelas | admin | daftar kelas + form tambah / tambah kelas (F9) |
| GET | /admin/kelas/{id}/edit | admin | form ubah kelas (v0.8) |
| PUT | /admin/kelas/{id} | admin | ubah kelas |
| DELETE | /admin/kelas/{id} | admin | hapus kelas (hanya kalau kosong) |
| GET/POST | /admin/akun | admin | daftar akun (filter kelas & role) / tambah akun (F9) |
| GET | /admin/akun/baru | admin | form tambah akun |
| GET | /admin/akun/{id}/edit | admin | form ubah akun |
| PUT | /admin/akun/{id} | admin | ubah akun (termasuk pindah kelas) |
| POST | /admin/akun/{id}/reset-password | admin | reset password; password baru ditampilkan sekali |
| DELETE | /admin/akun/{id} | admin | hapus akun (v0.9; hanya kalau aturan F9 terpenuhi) |
| POST | /admin/akun/import | admin | import akun siswa/PJ dari CSV (F9, v0.10) |
| POST | /admin/akun/import/password | admin | unduh daftar password awal hasil import, sekali saja (F9, v0.10). POST (dengan CSRF), bukan GET, karena menghapus daftar dari sesi |
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
| Beranda | siswa, pj | "Hari ini kamu piket — tugas: X" / tidak piket / libur / belum punya jadwal / jadwal belum berlaku, jam tepat waktu, tombol Submit, status submit hari ini |
| Form submit | siswa, pj | tugas hari ini (teks, tidak bisa diubah), ambil foto, catatan, field alasan terlambat (muncul hanya jika sudah lewat jam) |
| Riwayat | siswa, pj | daftar submit + tugas + status + alasan tolak |
| Antrean verifikasi | pj, guru | kartu submit: nama, tugas, foto, jam, tanda terlambat + alasan, tombol setujui/tolak; peringatan penghapusan foto (mulai Jumat sore) |
| Rekap mingguan | pj, guru, admin | pilih minggu (Senin–Jumat) + tabel per siswa + keterangan tanggal libur minggu itu + ekspor CSV; kolom tugas → [KEPUTUSAN TERBUKA] F6 |
| Kelola jadwal | pj, guru, admin | per siswa: dropdown hari + dropdown tugas awal; ringkasan isi per hari × tugas |
| Admin | admin | tabel kelas (nama, wali, PJ, jumlah siswa) + menu kelas/akun/pengaturan/hari libur; menu yang belum dibuat tampil dengan tanda "segera" dan tidak bisa diklik |
| Kelola kelas | admin | daftar kelas + form tambah (nama, wali kelas, tanggal mulai rotasi) / ubah (nama, wali kelas, tanggal mulai rotasi; tanggal harus Senin) + tombol hapus (dengan konfirmasi) |
| Kelola akun | admin | daftar akun + filter kelas & role; form tambah/ubah (isian mengikuti role); tombol reset password (tidak ada untuk akun sendiri); password baru ditampilkan sekali; konfirmasi saat pindah kelas; tombol hapus (dengan konfirmasi) atau teks alasan tidak bisa dihapus; form import CSV (+ contoh format) dan kotak "Unduh daftar password" setelah import |
| Hari libur | admin | form tambah (tanggal, keterangan) + daftar hari libur + tombol hapus |

Desain: mobile-first, bahasa Indonesia, tombol besar. Halaman admin boleh lebih padat (dipakai di laptop).

### 8.1 Detail layar yang berubah di v0.5

Halaman dirender server (Blade), jadi tidak ada state "loading" terpisah; cukup cegah klik ganda pada tombol simpan/submit (nonaktifkan + teks "Menyimpan…").

**Beranda (siswa, pj)** — urutan:
1. Nama, kelas, tanggal hari ini.
2. Kartu status hari ini — tepat satu, dipilih menurut **prioritas** (v0.8; yang pertama cocok yang tampil): (1) Sabtu/Minggu → (2) hari libur → (3) sebelum `tanggal_mulai_rotasi` → (4) belum punya jadwal → (5) tidak terjadwal hari ini → (6) terjadwal dengan tugas. Teks masing-masing:
   - terjadwal: "Hari ini kamu piket — tugas: Menyapu" + jam tepat waktu + tombol besar "Submit piket";
   - tidak terjadwal hari ini: "Hari ini kamu tidak piket. Jadwalmu: setiap Rabu.";
   - belum punya jadwal: "Kamu belum punya jadwal piket. Hubungi PJ kebersihan atau wali kelas.";
   - libur: "Hari ini libur: {keterangan}. Tidak ada piket.";
   - Sabtu/Minggu: "Tidak ada piket hari ini.";
   - sebelum `tanggal_mulai_rotasi` kelas: "Jadwal piket mulai berlaku {tanggal}." (F2)
3. Status submit hari ini (menunggu / disetujui / ditolak + alasan + tombol submit ulang).
4. Tautan ke Riwayat.

**Kelola jadwal (pj, guru, admin)** — dipakai PJ di HP:
1. Nama kelas + tanggal mulai rotasi (hanya-baca; hanya admin yang mengubah, di `/admin/kelas`).
2. Ringkasan Senin–Jumat: jumlah siswa per hari (x/9), dirinci per tugas minggu ini (x/3). Sebelum `tanggal_mulai_rotasi` belum ada "minggu ini": ringkasan dirinci per tugas minggu ke-0 (= `tugas_awal`), dengan judul "Mulai {tanggal}".
3. Daftar siswa kelas, urut no. absen: nama, dropdown hari, dropdown tugas awal (+ pratinjau "Tugas minggu ini: X" kalau dipilih opsi (a) di F2; sebelum `tanggal_mulai_rotasi` pratinjaunya "Tugas mulai {tanggal}: X", dengan X = `tugas_awal`).
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
- Error validasi: tanggal wajib; keterangan wajib → "Keterangan wajib diisi."; tanggal sudah terdaftar → "Tanggal ini sudah terdaftar sebagai hari libur."; aturan tanggal lain sesuai [KEPUTUSAN TERBUKA] F8.
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
- Database: **MySQL ≥ 8.0.19** (atau MariaDB ≥ 10.2). Versi lebih lama (mis. MySQL 5.7 di sebagian shared hosting) tidak menjalankan CHECK `hari` dan gagal di migration Langkah 3 (`DROP CONSTRAINT`).
- Styling: Tailwind CSS (bawaan starter Laravel) — atau Bootstrap kalau lebih familiar
- Lingkungan lokal: **Laragon** atau **XAMPP** dengan PHP ≥ 8.3 (AppServ hanya dipakai jika versi PHP-nya ≥ 8.3)
- Hosting: shared hosting/VPS dengan PHP ≥ 8.3, MySQL, dan **cron job** (wajib untuk F7)
- Bahasa aplikasi: `APP_LOCALE=id` (pesan validasi bahasa Indonesia di `lang/id`).
- **Ditinjau ulang nanti:** tes otomatis sementara memakai SQLite in-memory (lebih cepat, tanpa setup). SQLite tidak menjalankan CHECK constraint `hari` (1–5) dan beberapa perilaku MySQL lain — pertimbangkan pindah ke database tes MySQL sebelum fitur yang bergantung pada perilaku khusus MySQL. Batas hari 1–5 dan nilai enum tugas tetap wajib divalidasi juga di aplikasi.

### Daftar periksa deploy (hosting)
- `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY` baru (`php artisan key:generate`), `SESSION_ENCRYPT=true`.
- `SESSION_CONNECTION` **dibiarkan kosong**: sesi harus di koneksi database utama, karena ganti/reset password dan hapus akun menghapus sesi user lewat koneksi itu (F1, F9).
- **Bahasa pesan error MySQL harus bahasa Inggris** (`lc_messages = en_US`, bawaan MySQL): isian kembar dikenali dari pesan error database (F9). Dengan bahasa lain, isian kembar dari penyimpanan bersamaan tampil sebagai halaman error.
  Di shared hosting pengaturan global MySQL biasanya tidak bisa diubah; kalau bahasanya bukan Inggris, atur **per koneksi** di `config/database.php` (opsi init command koneksi `mysql`, mis. `SET lc_messages = 'en_US'`).
- **Semua tabel memakai mesin InnoDB** (bawaan MySQL 8): foreign key dan kunci baris (`lockForUpdate`) yang menjaga penyimpanan bersamaan (F9) hanya bekerja di InnoDB. Cek dengan `SHOW TABLE STATUS`.
- MySQL ≥ 8.0.19 / MariaDB ≥ 10.2 (lihat di atas); cron job untuk scheduler (F7).
- Setelah deploy pertama dengan `SESSION_ENCRYPT=true`, semua user perlu login ulang sekali.

## 11. Urutan Pengerjaan
1. Setup proyek Laravel + koneksi MySQL + migrasi semua tabel + seeder data contoh (1 sekolah, 2 kelas, akun tiap role)
2. F1 Login + redirect per role + middleware role + ganti password
3. Migrasi perubahan v0.5 — **migration baru**, jangan ubah migration Langkah 1 yang sudah dijalankan: CHECK `hari` 1–5, `duty_schedules.tugas_awal` + ganti UNIQUE jadi (class_id, user_id), `classes.tanggal_mulai_rotasi`, `submissions.tugas`, tabel `hari_libur`; sesuaikan seeder.
   Keputusan (v0.6): `tanggal_mulai_rotasi` NOT NULL; `hari_libur` UNIQUE(school_id, tanggal).
   Teknis: kelas yang sudah ada sebelum migration (data contoh) diisi `tanggal_mulai_rotasi` = Senin minggu saat migration dijalankan, supaya kolom bisa dibuat NOT NULL. Data contoh lokal: Senin 26 Okt 2026 (disetujui pemilik proyek, v0.7); seeder memakai tanggal tetap ini.
   Dimajukan atas permintaan pemilik proyek (v0.7): fungsi hitung rotasi `Tugas::padaTanggal()` + unit test-nya (aslinya bagian Langkah 8). Langkah 8 tinggal memakainya.
   Selesai kalau: `php artisan migrate` jalan tanpa error di database yang sudah berisi data Langkah 1–2; insert `hari = 6` ditolak MySQL; insert dua jadwal untuk (class_id, user_id) yang sama ditolak; dua hari libur dengan (school_id, tanggal) sama ditolak.
4. Halaman admin (F9): dashboard + kelola kelas + kelola akun (tambah/ubah/reset password, pindah kelas) + middleware `AuthenticateSession` (F1).
   Termasuk: keluarkan `school_id` dari fillable model `Kelas` dan `User`; kelas dibuat lewat relasi sekolah admin (`$school->kelas()->create(...)`); migration baru UNIQUE(school_id, nama) di `classes`.
   Selesai kalau: role lain mendapat 403 di semua `/admin/*`; admin tidak bisa melihat/mengubah data sekolah lain; password acak sesuai aturan F9 dan tampil sekali; reset mengeluarkan user dari semua perangkat; admin tidak bisa mereset akunnya sendiri; PJ ke-3 di satu kelas ditolak; pindah kelas menghapus jadwal kelas lama; `tanggal_mulai_rotasi` bukan Senin ditolak; role `guru`/`admin` tidak bisa diubah dan siswa/PJ tidak bisa dijadikan guru/admin; nama kelas kembar di satu sekolah ditolak; `tanggal_mulai_rotasi` kelas yang sudah punya submit tidak bisa diubah; password acak minimal 1 huruf + 1 angka; hapus akun sesuai F9 (siswa dengan submit, guru wali kelas, guru yang pernah memverifikasi ditolak; admin ke-3 ditolak; admin tidak bisa menghapus diri sendiri atau admin terakhir); isian kembar dari penyimpanan bersamaan tampil sebagai pesan validasi.
5. Halaman admin: import akun CSV (F9, diputuskan v0.10).
   Selesai kalau: file contoh (siswa + PJ, pemisah koma maupun titik koma, dengan BOM) membuat semua akun dengan password acak sesuai aturan F9 dan `harus_ganti_password`; satu baris salah (NIS sudah dipakai, NIS kembar di file, kelas tidak ada / milik sekolah lain, no. absen kembar, PJ ke-3, role guru/admin) → tidak ada akun yang dibuat dan pesan menyebut nomor barisnya; baris judul salah, file bukan UTF-8, file kosong, dan > 50 baris ditolak; daftar password bisa diunduh tepat sekali, dengan pemisah titik koma dan BOM UTF-8 di awal; import baru ditolak selama daftar belum diunduh; role lain mendapat 403.
6. Halaman admin: kelola hari libur (F8). Butuh keputusan: route (§7) + butir F8 yang masih terbuka.
   Selesai kalau: admin bisa tambah & hapus libur; role lain mendapat 403; daftar kosong menampilkan "Belum ada hari libur."; keterangan kosong ditolak ("Keterangan wajib diisi.").
7. Halaman admin: pengaturan jam buka/tutup (`/admin/pengaturan`, F4) — harus selesai sebelum F3 + F4.
8. F2 Jadwal piket + tugas awal + rotasi (fungsi rotasi sudah ada sejak Langkah 3).
   Ditinjau ulang di langkah ini (catatan review Langkah 4): kalau dua admin memindahkan siswa yang sama pada saat bersamaan, jadwal di kelas perantara bisa tertinggal (`AkunController::update` membaca kelas lama dari data sebelum baris akun dikunci).
   Selesai kalau (contoh tes, `tanggal_mulai_rotasi` = Senin 2 Nov 2026, `tugas_awal` = menyapu): tugas 2–6 Nov = menyapu, 9 Nov = merapikan, 16 Nov = mengepel, 23 Nov = menyapu; simpan siswa ke-10 di satu hari ditolak; simpan siswa ke-4 dengan tugas yang sama di satu hari ditolak; pilihan hari Sabtu tidak ada dan ditolak server; jadwal diatur sebelum `tanggal_mulai_rotasi` → ringkasan & pratinjau memakai `tugas_awal`.
9. F3 + F4 Submit dengan batas waktu & keterlambatan (+ `submissions.tugas` diisi server, aturan tanggal libur dan tanggal sebelum `tanggal_mulai_rotasi`).
   Selesai kalau: `submissions.tugas` sesuai rotasi walau form dikirim dengan nilai tugas lain; perilaku di tanggal libur sesuai keputusan F3; submit sebelum `tanggal_mulai_rotasi` ditolak.
   Teknis: akun bisa dihapus admin (F9) bersamaan dengan submit/verifikasi. Hapus akun mengunci baris akun itu, jadi database menolak submit/verifikasi yang menunjuk akun yang sudah terhapus (foreign key) — tangkap dan tampilkan sebagai pesan, bukan halaman error (berlaku juga untuk Langkah 10).
   Teknis: tombol submit yang dinonaktifkan dari server (mis. sebelum jam buka, F4) wajib diberi atribut `data-tetap-nonaktif`, supaya tidak diaktifkan lagi oleh skrip pencegah klik ganda (`resources/js/app.js`).
10. F5 Verifikasi (kartu menampilkan tugas)
11. F6 Rekap mingguan + CSV (Senin–Jumat, tanggal libur dan tanggal sebelum `tanggal_mulai_rotasi` dikecualikan).
    Selesai kalau: siswa terjadwal Rabu, Rabu libur, tanpa submit → terjadwal 0 dan tidak piket 0 untuk minggu itu; minggu berikutnya tugasnya tetap bergeser satu langkah; minggu sebelum `tanggal_mulai_rotasi` → terjadwal 0 dan tidak piket 0.
    Catatan dari Langkah 5: pelindung rumus Excel (nilai yang diawali `=`, `+`, `-`, `@`, tab, atau enter diberi `'` di depan) sekarang ada di `AkunController::csvPassword`. Pindahkan ke tempat bersama (mis. `App\Support`) dan pakai juga untuk ekspor rekap, karena nama siswa dari import bisa diawali `=`. Jangan ditulis dua kali. Saat dipindah, tambahkan juga `\n` (baris baru): versi sekarang hanya menangkap tab dan `\r`, cukup untuk nama hasil import (sel di-trim), tapi belum untuk data lain seperti alasan tolak.
12. F7 Penghapusan foto mingguan (scheduler) + peringatan mulai Jumat sore
13. Uji coba dengan 1 kelas sungguhan selama 1 minggu (Senin–Jumat)
