# SPEC — Aplikasi Absensi Piket Kelas

> Dokumen ini adalah sumber kebenaran tunggal. Semua kode harus sesuai dengan dokumen ini.
> Kalau ada perubahan fitur, ubah dokumen ini DULU, baru kode.

Status: DRAFT v0.17
Bagian bertanda **[KEPUTUSAN TERBUKA]** belum final — putuskan sebelum fitur terkait dikerjakan.

Perubahan v0.5 (keputusan pemilik proyek): piket hanya Senin–Jumat; tugas piket per siswa (menyapu / merapikan meja & kursi / mengepel) yang berputar tiap minggu; hari libur sekolah (F8).

Perubahan v0.6 (keputusan pemilik proyek): `tanggal_mulai_rotasi` wajib diisi; `hari_libur` unik per (sekolah, tanggal); aturan kelola kelas & akun oleh admin (F9); route admin tambahan (§7); urutan pengerjaan §11 diperbarui.

Perubahan v0.7 (keputusan pemilik proyek): nilai enum tugas final; `tanggal_mulai_rotasi` hanya diubah admin, wajib hari Senin, minggu = Senin–Minggu, sebelum tanggal itu jadwal belum berlaku; keterangan hari libur wajib; ubah role hanya siswa ↔ PJ.

Perubahan v0.8 (keputusan pemilik proyek): `tanggal_mulai_rotasi` terkunci setelah kelas punya submit; pesan & prioritas beranda (F3, §8.1); nama kelas unik per sekolah; konfirmasi pindah kelas hanya kalau ada jadwal; password acak minimal 1 huruf + 1 angka, ditampilkan sekali lewat flash; `school_id` tidak bisa diisi lewat mass assignment; route form ubah kelas (§7).

Perubahan v0.9 (keputusan pemilik proyek): hapus akun oleh admin (menggantikan "tidak ada hapus akun"); maks 2 admin per sekolah; isian kembar yang lolos validasi bersamaan tampil sebagai pesan validasi; sesi dienkripsi (`SESSION_ENCRYPT=true`); kolom tugas (`duty_schedules.tugas_awal`, `submissions.tugas`) di MySQL disimpan sebagai teks + CHECK, bukan ENUM, supaya nilai kosong ditolak database (§6).

Perubahan v0.10 (keputusan pemilik proyek): import akun CSV diputuskan (F9) — hanya siswa/PJ; satu baris salah → seluruh file ditolak; daftar password awal diunduh sekali sebagai CSV (pemisah titik koma + BOM UTF-8) dari halaman daftar akun; route `POST /admin/akun/import/password` (§7).

Perubahan v0.11 (keputusan pemilik proyek, dari hasil tes manual Langkah 5):
- password yang sudah dibuat tidak pernah ditampilkan lagi; import ulang dengan NIS yang sudah ada tetap ditolak (disengaja) (F9);
- ganti password wajib tanpa password lama, berlaku untuk semua role, hanya di sesi yang login dengan mengetik password; sesi dari cookie "Ingat saya" tetap diminta password lama; isian password lama yang tidak diminta diabaikan; pesan "Password baru tidak boleh sama dengan password dari admin." (F1);
- reset massal password satu kelas untuk siswa/PJ yang belum mengganti password, maks 50, route `POST /admin/kelas/{id}/reset-password`, daftar tertunda dipakai bersama dengan import, beserta teks pesannya (F9, §7, §8.2);
- pesan "daftar belum diunduh" untuk import dan reset massal menjadi "Unduh dulu daftar password sebelumnya di halaman Akun." dengan tautan ke `/admin/akun` (mengganti teks v0.10) (F9);
- dari review Langkah 5a: pesan khusus saat reset massal bertabrakan dengan reset admin lain, dan {n} pada penolakan yang sudah terjadi di cek awal (F9, §8.2, §11 5a);
- langkah 5b lalu 5a sebelum Langkah 6 (§11).

Perubahan v0.12 (keputusan pemilik proyek, untuk Langkah 6): aturan hari libur diputuskan (F8): hanya Senin–Jumat, tahun 2000–2100, tanggal lampau boleh ditambah/dihapus, tidak ada edit, tanggal yang sudah punya submit ditolak; route `GET/POST /admin/hari-libur` + `DELETE /admin/hari-libur/{id}` (§7); teks pesan, konfirmasi hapus, dan urutan daftar (§8.1); menu "Hari libur" di dashboard admin aktif; kriteria selesai Langkah 6 (§11); catatan teknis Langkah 9: submit mengecek hari libur di dalam transaksi dengan `sharedLock()` pada baris sekolah, tambah hari libur dengan `lockForUpdate()` (§11).

Perubahan v0.13 (keputusan pemilik proyek):
- Langkah 9 diawali cast tanggal khusus yang selalu menyimpan `Y-m-d` (§11, catatan "Teknis awal");
- jam tutup dihitung sampai akhir menitnya, mis. 07:30 berarti sampai 07:30:59 (F4);
- halaman pengaturan jam (Langkah 7): format jam, urutan jam, teks pesan, perubahan langsung berlaku, simpan bersamaan (F4, §8, §8.1); kriteria selesai Langkah 7 (§11).

Perubahan v0.14 (keputusan pemilik proyek, dari review Langkah 7, untuk Langkah 9): `whereDate` wajib diganti perbandingan tanggal biasa setelah cast tanggal khusus ada (satu cara membandingkan tanggal); waktu submit diambil sekali di awal request; foto diproses di luar transaksi dan dihapus kalau submit ditolak; jam buka/tutup dibaca dari baris sekolah yang dikunci di dalam transaksi (§11 Langkah 9).

Perubahan v0.15 (keputusan pemilik proyek, untuk Langkah 8): keempat keputusan terbuka F2 diputuskan — boleh simpan walau ada siswa belum terjadwal (daftar "Belum terjadwal"); dropdown tugas = tugas di **minggu acuan** (Senin minggu saat form ditampilkan, isian tersembunyi `minggu_acuan`), server menghitung mundur `tugas_awal`; perubahan jadwal langsung berlaku termasuk minggu ini; simpan sekaligus satu kelas tanpa DELETE, siswa di luar form tidak diubah, siswa bukan anggota kelas → seluruh simpan ditolak; kelas yang dibuka per role dan validasi isian (F2). Route `GET /jadwal?kelas={id}` + `POST /jadwal` (§7). Layar kelola jadwal ditulis ulang, tautan "Jadwal" di daftar kelas admin (§8, §8.1). Langkah 8: perbaikan pindah kelas dari review Langkah 4, urutan kunci simpan jadwal (akun → kelas), kriteria selesai tambahan (§11). Istilah yang terlihat pengguna: "tugas", bukan "tugas awal" (§2); nama kolom `tugas_awal` tetap.

Perubahan v0.16 (keputusan pemilik proyek):
- Langkah 5c "Fondasi desain" (token warna terang/gelap, komponen bersama, dark mode untuk semua halaman yang ada; dikerjakan setelah Langkah 8, sebelum 9). Diputuskan: dark mode mengikuti pengaturan HP/OS, tanpa tombol mode; varian `dark:` bawaan Tailwind v4 tanpa `@custom-variant`, nilai gelap sekali di token; tanpa referensi tampilan, disetujui lewat contoh halaman login dulu; teks pesan di aplikasi dan di tes tidak boleh berubah (§11).
- Baris desain §8 menjadi subbagian "Desain & Dark mode" (§8).
- Langkah 10b "Deploy ke hosting untuk pengujian" (setelah Langkah 10, sebelum 11). Diputuskan: shared hosting dengan akses SSH (penyedia ditentukan nanti); admin pertama dibuat lewat perintah artisan sekali pakai yang membuat sekolah + admin dalam satu transaksi, meminta password tersembunyi dua kali, dan menolak berjalan kalau sudah ada admin (nama perintah dan teksnya ditulis di SPEC sebelum dikerjakan); subdomain untuk pengujian; database bersih sebelum Langkah 13, dengan data uji dihapus hanya setelah persetujuan eksplisit pemilik proyek (§11).
- Rujukan `docs/tes-manual-tertunda.md` untuk tes manual yang dikerjakan penguji lain (§11).

Perubahan v0.17 (keputusan pemilik proyek, dari sesi 1 Langkah 5c): mekanisme transisi dark mode selama 5c belum selesai — prop `modeGelap` di layout bersama, atribut `data-hanya-terang` + meta `color-scheme` `light` untuk halaman yang belum memakai token, nilai gelap token di `:root:not([data-hanya-terang])`, view yang sudah memakai token tidak memakai kelas `dark:` langsung; semua itu wajib dihapus di sesi 3. Keputusan (2) tetap berlaku. Isi tiap sesi diperjelas (sesi 2 menunggu persetujuan contoh login) dan syarat "Selesai kalau" sesi 3 ditambah (§11 Langkah 5c).

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
| `pj_kebersihan` | Penanggung jawab seksi kebersihan (juga siswa) | Semua hak `siswa` + verifikasi submit di kelasnya + lihat rekap kelasnya + kelola jadwal piket kelasnya (hari + tugas) |
| `guru` | Wali kelas | Verifikasi submit di kelas yang diwalikannya, lihat rekap kelas itu, kelola jadwal piket kelas itu (hari + tugas) |
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
  - **Ganti wajib tanpa password lama** (diputuskan v0.11). Berlaku untuk **semua role**. Kalau `harus_ganti_password = true` (login pertama atau setelah reset admin) **dan** sesi ini login dengan mengetik password, form **tidak** meminta password lama, karena user baru saja mengetiknya saat login. Selain itu form tetap meminta password lama ("Password lama salah." kalau keliru):
    - ganti password biasa (`harus_ganti_password = false`);
    - ganti wajib di sesi yang dipulihkan dari cookie "Ingat saya" (lihat butir sesi di bawah).
    - **Sesi dari cookie "Ingat saya" tetap diminta password lama** (diputuskan v0.11), karena komputer sekolah dipakai bergantian. Contoh kasus: user login pertama dengan "Ingat saya", lalu menutup browser tanpa mengganti password. Orang berikutnya yang membuka perangkat itu masuk lewat cookie, dan tanpa aturan ini ia bisa menetapkan password tanpa tahu password dari admin.
      - Teknis: saat login dengan mengetik password berhasil (`POST /login`), server memberi **tanda di sesi**, mis. `login_dengan_password`. Sesi yang dipulihkan dari cookie "Ingat saya" tidak mendapat tanda ini. Hanya sesi bertanda yang boleh ganti wajib tanpa password lama. Sesi tanpa tanda (dari cookie, atau sesi yang sudah ada sebelum aturan ini dipasang) tetap diminta password lama. Tanda hilang bersama sesinya, saat logout atau sesi habis.
      - Kenapa tidak memakai `Auth::viaRemember()` saja: nilainya `true` hanya pada **satu request**, yaitu request tempat cookie memulihkan login. Setelah itu Laravel menyimpan ID user ke sesi, dan request berikutnya, termasuk `POST /ganti-password`, dibaca dari sesi sehingga `viaRemember()` bernilai `false`. Jadi tanda harus disimpan di sesi. Tanda positif saat login dengan password dipilih karena bawaannya aman: sesi tanpa tanda tetap diminta password lama.
      - Tetap ada risiko: sesi yang dibiarkan terbuka di perangkat bersama (tanpa cookie, sebelum sesi habis) masih bisa dipakai orang lain untuk menetapkan password.
    - Tetap berlaku untuk ganti wajib:
      - minimal 8 karakter + konfirmasi;
      - maks 5 kali per menit per user;
      - perangkat lain keluar dan perangkat yang dipakai tetap login (butir di bawah);
      - password baru harus berbeda dari password saat ini. Ini dicek server terhadap hash password yang tersimpan, bukan terhadap isian form.
    - Server menentukan perlu tidaknya password lama dari `harus_ganti_password` dan tanda sesi **saat form dikirim**, bukan dari isian form. Contoh: password sudah diganti di tab lain, lalu form tanpa password lama dikirim dari tab lama → ditolak "Password lama wajib diisi.", dan form tampil lagi dengan kolom password lama.
    - **Isian password lama yang tetap dikirim** padahal tidak diminta (mis. form lama dari cache browser, atau dikirim manual) **diabaikan** (diputuskan v0.11).
    - **Pesan "harus berbeda" saat ganti wajib** (diputuskan v0.11): "Password baru tidak boleh sama dengan password dari admin." User tidak melihat kolom "password lama", jadi kata itu bisa membingungkan. Ganti password biasa tetap "Password baru harus berbeda dari password lama."
- Setiap kali password berubah (diganti user sendiri atau direset admin), semua sesi dan cookie "Ingat saya" user itu di perangkat lain ikut keluar. Kalau user mengganti sendiri, perangkat yang sedang dipakai tetap login.
  - Harus tetap berlaku walaupun penyimpanan sesi bukan database (mis. `SESSION_DRIVER=file` di hosting): pakai middleware bawaan Laravel `AuthenticateSession` (sesi yang dibuat dengan password lama otomatis keluar).
- Setiap kolom password (login; ganti password: lama, baru, konfirmasi) punya tombol sendiri untuk menampilkan/menyembunyikan isinya. Default tersembunyi. Label untuk pembaca layar berubah sesuai kondisi: "Tampilkan password" / "Sembunyikan password".
  - (v0.11) Saat ganti wajib tanpa password lama, kolom password lama tidak ada, jadi tombolnya juga tidak ada. Kolom baru dan konfirmasi tetap punya tombol masing-masing.
- Batasi percobaan login gagal (maks 5 kali per menit per NIS/email + IP).

### F2. Jadwal Piket & Tugas
- Guru/PJ kebersihan/admin mengatur jadwal per kelas: untuk setiap siswa dipilih **hari** dan **tugas minggu ini** (keduanya dropdown). Dropdown tugas berarti tugas di **minggu acuan**; yang disimpan tetap `duty_schedules.tugas_awal`, hasil hitung mundur server (diputuskan v0.15, lihat "Arti dropdown tugas" di bawah).
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
- **"Tepat satu hari" — diputuskan (a)** (v0.15): boleh simpan walau masih ada siswa yang belum terjadwal; layar menampilkan daftar "Belum terjadwal" (§8.1). "Tepat satu hari" berarti **paling banyak** satu hari per siswa (`UNIQUE(class_id, user_id)`).
  Alasan: kapasitas maks 5 × 9 = 45 siswa, jadi kelas > 45 siswa tidak mungkin semua terjadwal; siswa pindah kelas memang muncul sebagai "Belum terjadwal" di kelas baru (v0.6).
- **`classes.tanggal_mulai_rotasi` — diputuskan:**
  - Wajib diisi (NOT NULL, v0.6). Untuk kelas baru, diisi admin saat menambah kelas (F9). Kelas yang sudah ada sebelum migration Langkah 3 diisi oleh migration/seeder.
  - Hanya **admin** yang boleh mengubahnya, di `/admin/kelas` (v0.7). Guru/PJ hanya melihat. Mengubahnya menggeser tugas semua siswa kelas itu.
  - **Terkunci setelah kelas punya submit** (v0.8): boleh diubah hanya selama kelas belum punya satu pun baris `submissions` (status apa pun, termasuk ditolak/kedaluwarsa). Setelah itu kolom tampil terkunci di form ubah kelas dengan alasan "Tanggal mulai rotasi tidak bisa diubah karena kelas ini sudah punya riwayat piket.", dan server menolak perubahannya dengan pesan yang sama.
  - **Wajib hari Senin** (v0.7) → "Tanggal mulai rotasi harus hari Senin."
  - **Minggu kalender = Senin–Minggu** (v0.7). Minggu ke-0 = Senin `tanggal_mulai_rotasi` sampai Minggu berikutnya; tugas bergeser setiap Senin.
  - **Sebelum `tanggal_mulai_rotasi`: jadwal belum berlaku** (v0.7) — tidak ada kewajiban piket, tidak bisa submit, tidak dihitung terjadwal maupun `tidak_piket` di rekap. Beranda: "Jadwal piket mulai berlaku {tanggal}."
- **Arti pilihan di dropdown tugas — diputuskan (b) dengan pengaman "minggu acuan"** (v0.15). Latar: secara harfiah `tugas_awal` = tugas di minggu ke-0 (minggu `tanggal_mulai_rotasi`); kalau jadwal diatur di minggu ke-4 dan dipilih "Menyapu", minggu ini siswa justru mendapat "Merapikan". Karena itu:
  - Dropdown berarti tugas siswa di **minggu acuan**. Server menghitung mundur dan menyimpan `tugas_awal` yang setara, memakai fungsi rotasi yang sama (§6, satu tempat).
  - **Minggu acuan** = tanggal Senin minggu (Senin–Minggu, F2) saat form **ditampilkan**, dikirim form sebagai isian tersembunyi `minggu_acuan` (format `YYYY-MM-DD`). Server menghitung mundur memakai minggu acuan itu, **bukan** minggu saat simpan. Alasan: form yang dibuka hari Minggu lalu disimpan Senin tidak boleh menggeser tugas siapa pun.
  - Validasi `minggu_acuan` di server: format tanggal valid, hari Senin, tidak setelah Senin minggu ini, dan tidak lebih dari 4 minggu sebelum Senin minggu ini. Selain itu **seluruh simpan ditolak**: "Halaman jadwal sudah terlalu lama dibuka. Muat ulang halaman."
  - Minggu acuan sebelum minggu `tanggal_mulai_rotasi` (belum ada "minggu ini" saat form ditampilkan): pilihan dropdown = tugas minggu ke-0, disimpan apa adanya sebagai `tugas_awal` (aturan lama "untuk kedua opsi" tetap).
  - Label kolom dropdown menyebut tanggal minggu acuan, mis. "Tugas minggu ini (2–8 Nov)" (lintas bulan: "30 Nov–6 Des"). Sebelum `tanggal_mulai_rotasi`: "Tugas mulai {tanggal}", format tanggal "Senin, 2 Nov 2026".
  - Pratinjau "Tugas minggu ini: X" di samping dropdown **tidak diperlukan** (itu untuk opsi (a) yang tidak dipilih).
- **Jadwal diubah di tengah minggu — diputuskan (a)** (v0.15): berlaku langsung, termasuk untuk hari yang sudah lewat dan rekap; tidak ada kolom baru. Tugas dan rekap dihitung dari jadwal saat ini (masalah ini sudah ada sejak v0.4): siswa yang dipindah dari Senin (sudah lewat) ke Kamis bisa piket dua kali, atau tidak sama sekali, minggu itu — efek ini diterima. Layar menampilkan "Perubahan langsung berlaku, termasuk minggu ini." (§8.1). Submit yang sudah ada tetap memakai `submissions.tugas`.
- **Siswa pindah kelas — diputuskan (v0.6):** saat admin mengubah `users.class_id`, baris `duty_schedules` siswa itu di kelas lama **otomatis dihapus**; siswa muncul sebagai "Belum terjadwal" di kelas baru. Sebelum menyimpan, admin melihat konfirmasi: "Jadwal piket siswa ini di kelas lama akan dihapus." (lihat F9). Submit lama tetap tersimpan dengan `class_id` kelas lama.
- **Cara simpan jadwal — diputuskan (a)** (v0.15): sekaligus satu kelas (satu POST berisi semua siswa di form; batas dicek pada hasil akhir — memudahkan tukar hari/tugas antar siswa). **Tidak ada DELETE**: mengeluarkan siswa dari jadwal = pilih "— Belum terjadwal —" di dropdown hari.
  - **Siswa yang tidak ada di form** (mis. baru dipindah ke kelas ini setelah form dibuka) **tidak diubah** saat simpan; jadwalnya (kalau ada) tetap dan ikut dihitung dalam batas 9/3.
  - **Siswa yang dikirim tapi bukan anggota kelas** (sudah dipindah/dihapus, bukan `siswa`/`pj_kebersihan`, atau id asing) → **seluruh simpan ditolak**, tidak ada yang berubah: "Daftar siswa kelas ini baru saja berubah. Muat ulang halaman."
  - Dua orang menyimpan jadwal kelas yang sama bersamaan: yang terakhir menyimpan yang berlaku (untuk siswa yang ada di form-nya), seperti pengaturan jam (F4).
- **Kelas yang dibuka di `/jadwal`** (diputuskan v0.15):
  - `pj_kebersihan`: selalu kelasnya sendiri (parameter `kelas` diabaikan).
  - `guru`: kelas yang diwalikannya — 1 kelas → langsung; > 1 kelas → pilih dulu; 0 kelas → "Kamu belum menjadi wali kelas mana pun."
  - `admin`: pilih dari kelas di sekolahnya.
  - Kelas yang bukan haknya, milik sekolah lain, atau sudah dihapus → 404 (halaman 404 F9). `siswa` → 403.
- **Validasi isian per siswa** (diputuskan v0.15, pesan bahasa Indonesia):
  - hari kosong = belum terjadwal; kalau hari kosong, tugas diabaikan;
  - hari selain 1–5 (termasuk 6/Sabtu) → "Hari piket hanya Senin–Jumat.";
  - hari diisi tapi tugas kosong → "Tugas wajib dipilih.";
  - tugas di luar enum → "Tugas tidak valid.";
  - pesan batas tetap "Hari Senin sudah penuh (maks 9 siswa)" dan "Tugas {label} di hari Senin sudah 3 siswa", dengan {label} = label tugas di **minggu acuan**, mis. "Tugas Merapikan meja & kursi di hari Senin sudah 3 siswa".

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
- **Batas jendela** (diputuskan v0.13): jendela mulai tepat pada jam buka (06:00 berarti mulai 06:00:00) dan **jam tutup dihitung sampai akhir menit itu** (07:30 berarti sampai 07:30:59), sama seperti aturan "sampai 23:59" di bawah.
- **Pengaturan jam oleh admin** (diputuskan v0.13, halaman di §8.1):
  - Empat jam: buka pagi, tutup pagi, buka siang, tutup siang. Kedua jendela selalu ada.
  - Format `JJ:MM` 24 jam, tanpa detik. Disimpan di kolom TIME sebagai `JJ:MM:00`; form selalu menampilkan tanpa detik (mis. 06:00), walau database menyimpan dengan detik.
  - Harus berurutan ketat: buka pagi < tutup pagi < buka siang < tutup siang (jam yang sama ditolak, supaya kedua jendela terpisah). Tidak ada batas lain (jendela pagi tidak harus sebelum 12:00; tidak ada panjang minimal).
  - **Perubahan langsung berlaku**, termasuk untuk hari ini: submit memakai jam yang tersimpan saat submit itu diproses. Submit yang sudah masuk tidak dihitung ulang, karena `terlambat` disimpan saat submit.
  - Dua admin menyimpan bersamaan: yang terakhir menyimpan yang berlaku. Form selalu mengirim keempat jam dan semuanya divalidasi bersama, jadi hasilnya selalu satu set jam yang berurutan.
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
- **Hari: hanya Senin–Jumat** (diputuskan v0.12). Sabtu/Minggu memang tidak ada piket, jadi tanggal Sabtu/Minggu ditolak: "Hari libur hanya untuk Senin–Jumat. Sabtu dan Minggu memang tidak ada piket."
- **Tahun 2000–2100** (diputuskan v0.12). Di luar itu dianggap tanggal tidak valid: "Tanggal harus berupa tanggal yang valid."
- **Tanggal lampau: boleh tambah/hapus kapan saja** (diputuskan v0.12). Rekap minggu lalu ikut berubah karena dihitung ulang (F6).
- **Keterangan wajib diisi** (v0.7) — dicek di validasi form; kolom database tetap boleh kosong. → "Keterangan wajib diisi." Maks 255 karakter (panjang kolom).
- **Tidak ada edit** (diputuskan v0.12). Untuk mengubah, hapus lalu tambah lagi.
- **Tanggal yang sudah punya submit tidak bisa dijadikan libur** (diputuskan v0.12): "Tanggal ini sudah punya submit piket, jadi tidak bisa dijadikan hari libur." "Submit" = baris `submissions` apa pun statusnya, dari kelas mana pun di sekolah itu, dengan `tanggal` = tanggal yang diisi. Dicek di server saat menyimpan.
  - Ini hanya mengatur **penambahan** libur. Submit di tanggal yang **sudah** libur tetap [KEPUTUSAN TERBUKA] F3 "Submit di tanggal libur".
- **Hapus** tidak punya syarat tambahan (tanggal lampau pun boleh, lihat di atas).

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
    - **Tidak pernah ditampilkan lagi** (diputuskan v0.11): tidak ada di tabel daftar akun, form ubah akun, atau tempat lain. Password disimpan sebagai hash (§9), jadi memang tidak bisa dilihat siapa pun, termasuk admin. Satu-satunya cara mendapat password baru adalah **reset** (per akun, atau reset massal satu kelas, di bawah).
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
  - **Import hanya membuat akun baru** (diputuskan v0.11), tidak memperbarui atau mereset akun yang sudah ada. Import ulang file yang NIS-nya sudah ada (mis. file yang sama diupload lagi) tetap **ditolak seluruhnya** dengan pesan per baris "Baris {n}: NIS sudah dipakai.". Ini perilaku yang disengaja. Untuk memperbaiki data akun yang sudah ada → ubah akun; untuk password baru → reset password per akun, atau reset massal satu kelas (di bawah).
  - **Ada satu saja baris salah → seluruh file ditolak**, tidak ada akun yang dibuat. Admin melihat daftar kesalahan per baris, mis. "Baris 5: NIS sudah dipakai.", memperbaiki file, lalu upload ulang. Semua akun dari satu file disimpan dalam satu transaksi database (tersimpan semua atau tidak sama sekali).
    - Isian kembar dari penyimpanan bersamaan yang lolos pengecekan lalu ditolak database (UNIQUE) → file ditolak dengan pesan biasa "Ada NIS atau no. absen di file ini yang baru saja dipakai akun lain. Upload ulang file untuk melihat barisnya." (bukan halaman error, sama seperti aturan isian kembar di bawah).
    - Penyimpanan bersamaan lain, dicek ulang di dalam transaksi dengan baris kelas dikunci: kelas baru saja dihapus → "Ada kelas di file ini yang baru saja dihapus. Upload ulang file untuk melihat barisnya."; PJ kelas sudah penuh → "Kelas {nama} sudah punya 2 PJ kebersihan. Upload ulang file untuk melihat barisnya."
    - Pesan format file: bukan UTF-8 → "File harus disimpan sebagai CSV UTF-8. Di Excel: Simpan Sebagai → "CSV UTF-8 (Comma delimited)"."; tanpa baris akun → "File tidak berisi baris akun."; > 50 baris → "File berisi {n} baris akun, maks 50 per file. Pecah menjadi beberapa file, mis. satu file per kelas."; baris dengan sel terisi melebihi baris judul → "Baris {n}: jumlah kolom lebih banyak dari baris judul." Sel kosong di ujung baris (termasuk baris judul, sering ditambahkan Excel) diabaikan.
    - Request import dan unduh di sesi yang sama dijalankan bergantian (kunci sesi), supaya import kedua (mis. dari tab lain) tidak menimpa sesi dan menghapus daftar password import pertama.
  - Password setiap akun dibuat dengan aturan **password awal** di atas; semua akun hasil import wajib ganti password saat login pertama (F1).
  - **Daftar password awal diunduh sekali:**
    - Setelah import berhasil, admin kembali ke daftar akun dengan pesan "{X} akun dibuat." dan kotak "Unduh daftar password" (tombol, `POST /admin/akun/import/password`). Isi file CSV: `nama, kelas, no_absen, nis, password`, urut kelas lalu no. absen; pemisah **titik koma** (`;`) dan **BOM UTF-8** di awal file, supaya langsung terbuka rapi per kolom di Excel berbahasa Indonesia.
    - Daftar password disimpan **hanya di sesi admin** (sesi dienkripsi, §6), tidak pernah ditulis ke file di server. Setelah diunduh, daftar dihapus dari sesi; kalau tombol dikirim lagi → "Daftar password sudah diunduh atau sudah tidak tersedia. Reset password akun yang belum tercatat."
    - Kalau tidak diunduh (sesi habis, logout), daftar hilang. Admin memakai **reset password** per akun atau **reset massal satu kelas** (v0.11, di bawah).
    - Selama daftar belum diunduh, import baru ditolak (supaya daftar lama tidak tertimpa): "Unduh dulu daftar password sebelumnya di halaman Akun.", dengan tautan ke `/admin/akun` (v0.11). Teks ini mengganti teks v0.10 "Unduh dulu daftar password dari import sebelumnya.". Sejak v0.11 daftar ini dipakai bersama dengan reset massal (di bawah).
- **Reset massal password satu kelas** (diputuskan v0.11). Untuk siswa yang belum pernah login atau belum mengganti password, padahal password awalnya hilang (mis. daftar import tidak sempat diunduh, atau kertasnya hilang). Admin mereset sekaligus semua akun `siswa` dan `pj_kebersihan` di **satu kelas** yang masih `harus_ganti_password = true`, lalu mengunduh daftar password barunya sekali.
  - **Cakupan: per kelas** (diputuskan v0.11). Tidak ada reset massal satu sekolah.
  - **Yang ikut: `siswa` + `pj_kebersihan`** (diputuskan v0.11), dengan `class_id` = kelas itu dan `harus_ganti_password = true`. PJ juga login dengan NIS dan bisa kehilangan kertas yang sama. Guru/admin tidak punya kelas, jadi tidak pernah ikut.
  - Mengikuti aturan yang sudah ada:
    - password baru dibuat dengan aturan **password awal** di atas;
    - reset mengeluarkan akun dari semua perangkat (F1);
    - `harus_ganti_password` tetap true;
    - akun yang sudah mengganti password sendiri (`harus_ganti_password = false`) tidak ikut direset.
  - **Route:** `POST /admin/kelas/{id}/reset-password` (§7). Tombolnya ada di form ubah kelas. Kelas milik sekolah lain atau yang sudah dihapus → 404, sama seperti route kelas lain. Setelah reset berhasil, admin dialihkan ke `/admin/akun?kelas={id}`, tempat kotak "Unduh daftar password".
  - **Batas: maks 50 akun per reset** (diputuskan v0.11), sama dengan import. Kalau akun yang memenuhi syarat lebih dari 50, seluruh reset ditolak dan tidak ada akun yang berubah. Akun yang direset per akun tetap terhitung, karena masih `harus_ganti_password = true`, jadi kelas itu baru bisa memakai reset massal setelah sebagian siswanya mengganti password.
    - "Reset 50 pertama, lalu ulangi" **tidak bisa dipakai**. Akun yang sudah direset tetap `harus_ganti_password = true`, jadi akan direset lagi pada putaran berikutnya.
  - **Daftar hasil reset dipakai bersama dengan daftar import** (diputuskan v0.11). Mekanismenya sama dengan daftar import (v0.10):
    - satu daftar tertunda per sesi admin, disimpan hanya di sesi (dienkripsi), tidak pernah ditulis ke file di server;
    - selama daftar belum diunduh, import **dan** reset massal sama-sama ditolak;
    - tombol "Unduh daftar password" yang sama, dengan route yang sudah ada `POST /admin/akun/import/password` (nama route tidak diubah, supaya kontrak v0.10 tetap);
    - dihapus dari sesi setelah diunduh; unduh kedua kali → pesan v0.10 "Daftar password sudah diunduh atau sudah tidak tersedia. Reset password akun yang belum tercatat.";
    - file CSV pemisah `;` + BOM UTF-8, kolom `nama, kelas, no_absen, nis, password`, urut no. absen;
    - request reset massal, import, dan unduh di sesi yang sama dijalankan bergantian (kunci sesi, seperti route import).
  - **Teknis:** akun yang memenuhi syarat dicek ulang di dalam satu transaksi dengan baris akun dikunci. Jadi akun yang mengganti password atau dihapus bersamaan tidak ikut direset, dan password yang baru dipilih siswa tidak tertimpa. Password di-hash sebelum transaksi, supaya baris tidak terkunci lama.
  - **Konfirmasi wajib menyebut** (diputuskan v0.11) bahwa **password yang sudah dibagikan tetapi belum dipakai login ikut tidak berlaku** (hasil import atau reset per akun). Teks lengkapnya ada di butir teks pesan di bawah.
  - **Teks pesan** (diputuskan v0.11):
    - konfirmasi: "Reset password {n} akun di kelas {nama} yang belum pernah mengganti password? Password yang sudah dibagikan tetapi belum dipakai login ikut tidak berlaku, dan akun-akun itu keluar dari semua perangkat. Tindakan ini tidak bisa dibatalkan."
      - {n} = jumlah akun yang memenuhi syarat saat halaman ubah kelas dibuka.
    - sukses: "Password {n} akun di kelas {nama} direset. Unduh daftar password sekarang — daftar hanya bisa diunduh sekali." + kotak "Unduh daftar password".
      - {n} = jumlah akun yang **benar-benar direset**, yaitu hasil cek ulang di dalam transaksi. Bukan jumlah di halaman konfirmasi: angkanya bisa berbeda kalau ada siswa yang mengganti password atau akun yang dihapus sebelum tombol ditekan. Jumlah ini juga sama dengan jumlah baris di file unduhan.
    - kelas tanpa akun yang memenuhi syarat: "Tidak ada akun di kelas {nama} yang belum mengganti password. Untuk akun tertentu, pakai reset password per akun." Tidak ada yang diubah. Teks ini benar juga untuk kelas yang tidak punya siswa. Teks yang sama dipakai kalau, saat dicek ulang di transaksi, sudah tidak ada akun yang memenuhi syarat.
    - direset bersamaan (diputuskan v0.11, dari review Langkah 5a): "Password akun di kelas ini baru saja direset admin lain. Muat ulang halaman." Dipakai **hanya** kalau saat dicek ulang di transaksi masih ada akun yang belum mengganti password, tetapi tidak satu pun bisa direset karena semuanya berubah sejak cek awal. Penyebab yang biasa: dua admin menekan tombol reset kelas yang sama hampir bersamaan, dan admin kedua mendapat pesan ini, bukan "Tidak ada akun …" (yang menyesatkan, karena kotak reset masih menampilkan N akun). Tidak ada yang diubah oleh request kedua. Password dari admin pertama tetap berlaku.
    - lebih dari 50 akun: "Kelas {nama} punya {n} akun yang belum mengganti password, maks 50 per reset. Pakai reset password per akun." {n} = jumlah saat dicek ulang di transaksi.
      - **Kompromi** (diputuskan v0.11, dari review Langkah 5a): sebelum password di-hash ada **cek awal** tanpa kunci. Kalau cek awal sudah menemukan 0 akun atau lebih dari 50, reset langsung ditolak dengan pesan yang sama, supaya server tidak meng-hash sampai 50 password untuk reset yang pasti ditolak. Kalau ditolak karena lebih dari 50 di cek awal, {n} di pesan ini adalah jumlah saat **cek awal**, bukan cek ulang (pesan 0 akun tidak memakai {n}). Selisih waktunya hanya milidetik.
    - daftar belum diunduh: "Unduh dulu daftar password sebelumnya di halaman Akun.", dengan "halaman Akun" sebagai **tautan ke `/admin/akun`**. Berlaku untuk import **dan** reset massal, karena daftarnya bersama. Teks ini **mengganti** teks v0.10 "Unduh dulu daftar password dari import sebelumnya."
    - unduh kedua kali: tetap teks v0.10 "Daftar password sudah diunduh atau sudah tidak tersedia. Reset password akun yang belum tercatat."

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
                 (TIME, disimpan JJ:MM:00; default 06:00, 07:30, 14:00, 15:30 — F4)
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
| GET/POST | /ganti-password | semua | wajib saat login pertama; tanpa password lama saat ganti wajib di sesi yang login dengan password (F1, v0.11) |
| GET | /beranda | siswa, pj | status piket hari ini (+ tugas) + tombol submit |
| GET/POST | /piket/submit | siswa, pj | form submit (foto, catatan, alasan terlambat bila perlu) |
| GET | /piket/riwayat | siswa, pj | riwayat submit sendiri |
| GET | /verifikasi | pj, guru | antrean submit kelasnya |
| POST | /verifikasi/{id} | pj, guru | setujui/tolak |
| GET | /rekap?minggu= | pj, guru, admin | rekap mingguan; `&format=csv` untuk ekspor |
| GET | /jadwal?kelas={id} | pj, guru, admin | halaman kelola jadwal; tanpa `kelas` untuk guru > 1 kelas/admin = pilih kelas; PJ: `kelas` diabaikan (F2, v0.15) |
| POST | /jadwal | pj, guru, admin | simpan jadwal satu kelas sekaligus (CSRF). Isian: `kelas` (id; diabaikan untuk PJ), `minggu_acuan` (YYYY-MM-DD, Senin), `jadwal[{user_id}][hari]` (kosong atau 1–5), `jadwal[{user_id}][tugas]` (menyapu\|merapikan\|mengepel = tugas di minggu acuan). Tidak ada DELETE (v0.15) |
| GET | /admin | admin | dashboard admin (F9) |
| GET/POST | /admin/kelas | admin | daftar kelas + form tambah / tambah kelas (F9) |
| GET | /admin/kelas/{id}/edit | admin | form ubah kelas (v0.8) |
| PUT | /admin/kelas/{id} | admin | ubah kelas |
| DELETE | /admin/kelas/{id} | admin | hapus kelas (hanya kalau kosong) |
| POST | /admin/kelas/{id}/reset-password | admin | reset massal password siswa/PJ kelas itu yang belum mengganti password, maks 50 (F9, v0.11). Lalu dialihkan ke `/admin/akun?kelas={id}` untuk mengunduh daftarnya. POST (dengan CSRF) + kunci sesi seperti route import |
| GET/POST | /admin/akun | admin | daftar akun (filter kelas & role) / tambah akun (F9) |
| GET | /admin/akun/baru | admin | form tambah akun |
| GET | /admin/akun/{id}/edit | admin | form ubah akun |
| PUT | /admin/akun/{id} | admin | ubah akun (termasuk pindah kelas) |
| POST | /admin/akun/{id}/reset-password | admin | reset password; password baru ditampilkan sekali |
| DELETE | /admin/akun/{id} | admin | hapus akun (v0.9; hanya kalau aturan F9 terpenuhi) |
| POST | /admin/akun/import | admin | import akun siswa/PJ dari CSV (F9, v0.10) |
| POST | /admin/akun/import/password | admin | unduh daftar password yang tertunda, sekali saja: hasil import (F9, v0.10) atau reset massal (v0.11, daftar bersama; nama route tetap). POST (dengan CSRF), bukan GET, karena menghapus daftar dari sesi |
| GET/POST | /admin/pengaturan | admin | form jam buka/tutup / simpan jam buka/tutup (F4, §8.1, v0.13) |
| GET/POST | /admin/hari-libur | admin | daftar hari libur + form tambah / tambah hari libur (F8, diputuskan v0.12) |
| DELETE | /admin/hari-libur/{id} | admin | hapus hari libur (F8, v0.12); id milik sekolah lain → 404. Tidak ada edit (F8) |

Proteksi: middleware `auth` + middleware role di setiap grup route. Semua route `/admin/*` hanya untuk `admin`.

Pesan error ke user dalam bahasa Indonesia, mis. "Kamu tidak terjadwal piket hari ini", "Alasan keterlambatan wajib diisi", "Hari Senin sudah penuh (maks 9 siswa)", "Tugas Menyapu di hari Senin sudah 3 siswa".

## 8. Layar

| Layar | Role | Isi utama |
|---|---|---|
| Login | semua | NIS atau email, password, checkbox "Ingat saya" (default tidak dicentang) |
| Ganti password | semua | password lama, baru, konfirmasi; saat ganti wajib di sesi yang login dengan password: tanpa kolom password lama (F1, §8.2, v0.11) |
| Beranda | siswa, pj | "Hari ini kamu piket — tugas: X" / tidak piket / libur / belum punya jadwal / jadwal belum berlaku, jam tepat waktu, tombol Submit, status submit hari ini |
| Form submit | siswa, pj | tugas hari ini (teks, tidak bisa diubah), ambil foto, catatan, field alasan terlambat (muncul hanya jika sudah lewat jam) |
| Riwayat | siswa, pj | daftar submit + tugas + status + alasan tolak |
| Antrean verifikasi | pj, guru | kartu submit: nama, tugas, foto, jam, tanda terlambat + alasan, tombol setujui/tolak; peringatan penghapusan foto (mulai Jumat sore) |
| Rekap mingguan | pj, guru, admin | pilih minggu (Senin–Jumat) + tabel per siswa + keterangan tanggal libur minggu itu + ekspor CSV; kolom tugas → [KEPUTUSAN TERBUKA] F6 |
| Kelola jadwal | pj, guru, admin | per siswa: dropdown hari + dropdown tugas minggu ini (minggu acuan); ringkasan isi per hari × tugas; daftar "Belum terjadwal" (F2, §8.1, v0.15) |
| Admin | admin | tabel kelas (nama, wali, PJ, jumlah siswa) + menu kelas/akun/pengaturan/hari libur; menu yang belum dibuat tampil dengan tanda "segera" dan tidak bisa diklik |
| Kelola kelas | admin | daftar kelas + form tambah (nama, wali kelas, tanggal mulai rotasi) / ubah (nama, wali kelas, tanggal mulai rotasi; tanggal harus Senin) + tombol hapus (dengan konfirmasi); kotak "Reset password kelas ini" (reset massal) di form ubah kelas (F9, §8.2, v0.11); tautan "Jadwal" per kelas di daftar kelas, ke `/jadwal?kelas={id}` (v0.15; menu dashboard admin F9 tidak berubah) |
| Kelola akun | admin | daftar akun + filter kelas & role; form tambah/ubah (isian mengikuti role); tombol reset password (tidak ada untuk akun sendiri); password baru ditampilkan sekali; konfirmasi saat pindah kelas; tombol hapus (dengan konfirmasi) atau teks alasan tidak bisa dihapus; form import CSV (+ contoh format) dan kotak "Unduh daftar password" setelah import atau reset massal; password yang sudah dibuat tidak pernah tampil lagi di tabel maupun form ubah (diputuskan v0.11) |
| Hari libur | admin | form tambah (tanggal, keterangan) + daftar hari libur + tombol hapus |
| Pengaturan | admin | form jam buka/tutup pagi & siang (F4, §8.1, v0.13) |

**Desain & Dark mode** (v0.16)
- Prinsip yang tetap berlaku: mobile-first, bahasa Indonesia, tombol besar. Halaman admin boleh lebih padat (dipakai di laptop).
- Token warna (mode terang & gelap), komponen bersama (tombol, kartu, input/dropdown, pesan), dan dark mode diatur di **Langkah 5c** (§11). Halaman yang dibuat sesudahnya memakai token dan komponen itu.
- Dark mode **mengikuti pengaturan HP/OS** (`prefers-color-scheme`), tanpa tombol mode; di Tailwind memakai varian `dark:` bawaan v4 (diputuskan v0.16, Langkah 5c).

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

**Kelola jadwal (pj, guru, admin)** (diputuskan v0.15) — dipakai PJ di HP. Urutan:
1. Nama kelas + tanggal mulai rotasi (hanya-baca; hanya admin yang mengubah, di `/admin/kelas`). Guru > 1 kelas/admin: tautan "Pilih kelas lain".
2. Teks: "Perubahan langsung berlaku, termasuk minggu ini."
3. Ringkasan Senin–Jumat: jumlah siswa per hari (x/9), dirinci per tugas minggu acuan (x/3), dengan judul "Minggu ini (2–8 Nov)". Sebelum `tanggal_mulai_rotasi`: dirinci per `tugas_awal`, dengan judul "Mulai {tanggal}". Saat error validasi, ringkasan dihitung dari isian yang dikirim (supaya yang melebihi batas terlihat, mis. 10/9) dan sel yang melebihi batas disorot.
4. Daftar siswa kelas, urut no. absen: no. absen + nama, dropdown hari ("— Belum terjadwal —", Senin–Jumat; tanpa Sabtu), dropdown tugas berlabel sesuai F2 ("Tugas minggu ini (2–8 Nov)" / "Tugas mulai {tanggal}"). Isian tersembunyi `minggu_acuan`.
5. Daftar "Belum terjadwal": nama siswa yang belum punya hari, dari data tersimpan.
6. Tombol "Simpan" besar, menempel di bawah layar.
- Kosong (belum ada jadwal): semua siswa tampil sebagai belum terjadwal + petunjuk "Pilih hari dan tugas untuk setiap siswa, lalu tekan Simpan." Kelas tanpa siswa: "Kelas ini belum punya siswa."
- Halaman pilih kelas (guru > 1 kelas, admin): judul "Jadwal piket", daftar nama kelas sebagai tautan. Admin tanpa kelas: "Belum ada kelas." (Guru tanpa kelas: pesan F2 "Kamu belum menjadi wali kelas mana pun.")
- Menyimpan: tombol nonaktif + "Menyimpan…" untuk mencegah klik ganda (lihat pembuka §8.1; tidak ada state loading terpisah).
- Error validasi: pesan di atas daftar, isian tidak hilang.
- Sukses: "Jadwal tersimpan."
- Tautan masuk: "Jadwal piket" di halaman sementara `/beranda` (hanya PJ) dan `/verifikasi` (guru), sampai halaman itu dibuat di Langkah 9/10. Admin: tautan "Jadwal" per kelas di daftar kelas (§8).

**Antrean verifikasi (pj, guru)**: kartu menampilkan tugas di bawah nama. Banner peringatan F7 muncul mulai Jumat sore. Kosong: "Tidak ada submit yang menunggu verifikasi."

**Rekap mingguan**: minggu = Senin–Jumat. Kalau ada libur di minggu itu, tampilkan baris keterangan "Libur: Rabu, 11 Nov — {keterangan}" di atas tabel. Kosong (kelas belum punya jadwal): "Belum ada jadwal piket untuk kelas ini."

**Hari libur (admin)** — urutan:
1. Judul "Hari Libur".
2. Form tambah: tanggal (date picker), keterangan, tombol "Tambah".
3. Daftar hari libur urut tanggal (yang akan datang di atas): hari + tanggal, keterangan, tombol "Hapus" (dengan konfirmasi).
   - Urutan persisnya (v0.12): hari ini dan tanggal yang akan datang di atas, mulai dari yang terdekat; di bawahnya tanggal lampau, mulai dari yang terbaru.
   - Format tanggal: "Rabu, 11 Nov 2026".
   - Konfirmasi hapus (v0.12): "Hapus hari libur Rabu, 11 Nov 2026 ({keterangan})?"
- Kosong: "Belum ada hari libur."
- Error validasi (teks diputuskan v0.12), isian tidak hilang:
  - tanggal kosong → "Tanggal wajib diisi."; tanggal tidak valid → "Tanggal harus berupa tanggal yang valid." Tidak valid = bukan format `YYYY-MM-DD`, tanggal yang tidak ada di kalender (mis. 30 Feb), atau tahun di luar 2000–2100 (v0.12);
  - keterangan kosong → "Keterangan wajib diisi."; lebih dari 255 karakter → "Keterangan tidak boleh lebih dari 255 karakter.";
  - tanggal sudah terdaftar → "Tanggal ini sudah terdaftar sebagai hari libur.";
  - Sabtu/Minggu → "Hari libur hanya untuk Senin–Jumat. Sabtu dan Minggu memang tidak ada piket.";
  - tanggal yang sudah punya submit → "Tanggal ini sudah punya submit piket, jadi tidak bisa dijadikan hari libur."
- Sukses: "Hari libur ditambahkan." / "Hari libur dihapus."

**Pengaturan (admin)** (diputuskan v0.13) — urutan:
1. Judul "Pengaturan".
2. Teks: "Jam tepat waktu (WIB), berlaku Senin–Jumat."
3. Teks: "Jam tutup dihitung sampai akhir menit itu (mis. 07:30 berarti sampai 07:30:59)."
4. Teks: "Perubahan langsung berlaku, termasuk hari ini. Submit yang sudah masuk tidak berubah."
5. Kelompok "Pagi": Jam buka, Jam tutup. Kelompok "Siang": Jam buka, Jam tutup. Pemilih jam bawaan browser, terisi jam tersimpan tanpa detik (mis. 06:00).
6. Tombol "Simpan".
- Error validasi (isian tidak hilang):
  - kosong → "Jam buka pagi wajib diisi." (sama untuk keempat jam: "Jam tutup pagi …", "Jam buka siang …", "Jam tutup siang …");
  - format salah → "Jam buka pagi harus berupa jam yang valid, mis. 06:00." (sama untuk keempat jam);
  - urutan: "Jam tutup pagi harus setelah jam buka pagi."; "Jam buka siang harus setelah jam tutup pagi."; "Jam tutup siang harus setelah jam buka siang." (dicek setelah keempat jam berformat benar; beberapa pesan bisa muncul sekaligus).
- Sukses: "Pengaturan jam disimpan."

### 8.2 Detail layar v0.11

Urutan, state, dan teks di bawah ini sudah diputuskan (v0.11). Teks pesan reset massal ada di F9.

**Ganti password (semua role, dipakai siswa di HP)**. Urutan:
1. Judul "Ganti password".
2. Kotak info. Saat ganti wajib: "Kamu wajib mengganti password dulu sebelum bisa lanjut." (teks yang sudah ada). Saat ganti biasa: "Password baru minimal 8 karakter dan harus berbeda dari password lama."
3. Kolom password lama. Tampil saat ganti biasa, dan saat ganti wajib di sesi yang **tidak** login dengan mengetik password (dipulihkan dari cookie "Ingat saya", F1). **Tidak tampil** saat ganti wajib di sesi yang login dengan password.
4. Password baru (minimal 8 karakter), lalu Ulangi password baru. Setiap kolom punya tombol tampilkan/sembunyikan.
5. Tombol besar "Simpan password".
- Kosong: tidak berlaku, karena form selalu tampil.
- Loading: tombol nonaktif + "Menyimpan…" (§8.1).
- Error validasi: pesan di bawah kolom yang salah, mis.:
  - "Password lama salah.";
  - "Password lama wajib diisi." (form dikirim dari tab lama, F1);
  - saat ganti wajib: "Password baru tidak boleh sama dengan password dari admin." (F1);
  - konfirmasi tidak cocok.
  Kolom password dikosongkan lagi.
- Terlalu sering (lebih dari 5 kali per menit): ditolak. Teks halamannya belum diatur SPEC, dan v0.11 tidak mengubahnya.
- Sukses: dialihkan ke halaman awal role dengan "Password berhasil diganti." (teks yang sudah ada).

**Kotak reset massal password (admin)**, di form ubah kelas (`/admin/kelas/{id}/edit`). Urutan:
1. Judul "Reset password kelas ini".
2. Penjelasan: "Untuk siswa/PJ yang belum pernah mengganti password, mis. daftar password hilang. Akun yang sudah mengganti password tidak ikut."
3. Jumlah: "{n} akun belum mengganti password."
4. Tombol "Reset password {n} akun", dengan konfirmasi. Konfirmasi wajib menyebut bahwa password yang sudah dibagikan tetapi belum dipakai login ikut tidak berlaku (F9).
- Kosong (n = 0): tombol tidak tampil; tampil teks "kelas tanpa akun yang memenuhi syarat" (F9).
- Loading: bisa ± 20 detik untuk 50 akun. Tombol nonaktif + "Mereset password… jangan tutup halaman ini."
- Error: daftar sebelumnya belum diunduh, lebih dari 50 akun, atau akun baru saja direset admin lain (F9). Tidak ada akun yang berubah.
- Sukses: dialihkan ke `/admin/akun?kelas={id}` dengan pesan sukses + kotak "Unduh daftar password" (sama seperti setelah import).

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

**Tes manual tertunda** (v0.16): butir tes manual yang dikerjakan penguji lain dicatat di `docs/tes-manual-tertunda.md`. Isinya hal yang tidak bisa diuji otomatis: file yang dibuka di aplikasi lain (mis. CSV di Excel), tampilan dan perangkat HP, hosting dan data asli. Format tiap butir: kotak centang + nama langkah (mis. "Langkah 8: halaman jadwal di HP"), **Apa yang dites**, **Langkah** singkat (maks 3), **Hasil yang diharapkan**. Setiap langkah yang punya tes manual seperti itu menambah butirnya ke file tersebut.

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

   **Langkah tambahan v0.11, dikerjakan sebelum Langkah 6, urutannya 5b lalu 5a** (diputuskan v0.11). Nomor langkah lain tidak diubah, karena dirujuk di dokumen ini dan di komentar kode. Satu langkah per sesi.
   - **5b. Ganti password wajib tanpa password lama (F1).** Dikerjakan lebih dulu: perubahannya kecil dan langsung terasa saat siswa login pertama.
     Selesai kalau:
     - login dengan mengetik password + `harus_ganti_password = true` → form tanpa kolom password lama; simpan berhasil dengan password baru + konfirmasi; `harus_ganti_password` jadi false; perangkat lain keluar; perangkat ini tetap login. Berlaku untuk semua role, termasuk guru/admin;
     - sesi yang dipulihkan dari cookie "Ingat saya" + `harus_ganti_password = true` → kolom password lama tampil dan wajib benar;
     - isian password lama yang tetap dikirim saat tidak diminta → diabaikan (password salah pun tetap berhasil);
     - saat ganti wajib, password baru sama dengan password saat ini → "Password baru tidak boleh sama dengan password dari admin.";
     - `harus_ganti_password = false` → password lama kosong atau salah ditolak, dan pesan "harus berbeda" tetap "Password baru harus berbeda dari password lama.";
     - form tanpa password lama yang dikirim dari tab lama setelah password diganti → "Password lama wajib diisi.";
     - percobaan ke-6 dalam semenit ditolak;
     - tombol tampilkan/sembunyikan hanya ada di kolom yang tampil.
   - **5a. Reset massal password satu kelas (F9).**
     Selesai kalau:
     - kelas berisi akun yang belum dan yang sudah mengganti password → hanya siswa/PJ yang belum yang direset, dan password pilihan siswa tetap berlaku;
     - password baru sesuai aturan password awal F9; `harus_ganti_password` tetap true; password lama akun yang direset tidak bisa dipakai login; sesi dan cookie "Ingat saya" akun itu keluar;
     - pesan sukses menyebut jumlah akun yang benar-benar direset (sama dengan jumlah baris file unduhan), bukan jumlah di konfirmasi;
     - daftar bisa diunduh tepat sekali lewat `POST /admin/akun/import/password` (CSV `;` + BOM, kolom `nama, kelas, no_absen, nis, password`, urut no. absen), dan unduh kedua menampilkan pesan v0.10;
     - selama daftar (dari import maupun reset) belum diunduh, import dan reset massal sama-sama ditolak dengan "Unduh dulu daftar password sebelumnya di halaman Akun." + tautan ke `/admin/akun`;
     - lebih dari 50 akun yang memenuhi syarat → seluruh reset ditolak, tidak ada yang berubah;
     - kelas tanpa akun yang memenuhi syarat, termasuk kelas tanpa siswa → "Tidak ada akun di kelas {nama} yang belum mengganti password. …", tidak ada yang berubah;
     - semua akun hasil cek awal baru saja direset admin lain (masih belum mengganti password) → "Password akun di kelas ini baru saja direset admin lain. Muat ulang halaman.", tidak ada yang berubah oleh request kedua;
     - konfirmasi memakai teks F9, termasuk "Tindakan ini tidak bisa dibatalkan.";
     - kelas sekolah lain atau yang sudah dihapus → 404; role lain mendapat 403.
     - Juga: tes import Langkah 5 yang memeriksa teks lama "Unduh dulu daftar password dari import sebelumnya." disesuaikan ke teks baru.
6. Halaman admin: kelola hari libur (F8). Keputusan route (§7) dan F8: v0.12.
   Selesai kalau: admin bisa tambah & hapus libur; role lain mendapat 403; daftar kosong menampilkan "Belum ada hari libur."; keterangan kosong ditolak ("Keterangan wajib diisi.");
   dan (v0.12): admin hanya melihat dan menghapus hari libur sekolahnya sendiri (id sekolah lain → 404); tanggal yang sudah terdaftar ditolak, termasuk dari penyimpanan bersamaan; Sabtu/Minggu ditolak; tahun di luar 2000–2100 ditolak sebagai tanggal tidak valid; tanggal lampau bisa ditambah dan dihapus; tanggal yang sudah punya submit (status apa pun, kelas mana pun di sekolah itu) ditolak, sedangkan submit sekolah lain di tanggal itu tidak berpengaruh; urutan daftar dan teks sesuai §8.1; tambah dan hapus hanya lewat form dengan CSRF; menu "Hari libur" di dashboard admin aktif.
7. Halaman admin: pengaturan jam buka/tutup (`/admin/pengaturan`, F4) — harus selesai sebelum F3 + F4.
   Selesai kalau (v0.13): admin bisa mengubah keempat jam, hanya untuk sekolahnya sendiri; role lain mendapat 403; simpan hanya lewat form dengan CSRF; form menampilkan jam tersimpan tanpa detik (sekolah baru: default F4); jam kosong, format salah, dan urutan salah ditolak dengan pesan §8.1, isian tidak hilang; jam disimpan `JJ:MM:00`; teks halaman sesuai §8.1; menu "Pengaturan" di dashboard admin aktif.
8. F2 Jadwal piket + tugas awal + rotasi (fungsi rotasi sudah ada sejak Langkah 3).
   Ditinjau ulang di langkah ini (catatan review Langkah 4): kalau dua admin memindahkan siswa yang sama pada saat bersamaan, jadwal di kelas perantara bisa tertinggal (`AkunController::update` membaca kelas lama dari data sebelum baris akun dikunci).
   Selesai kalau (contoh tes, `tanggal_mulai_rotasi` = Senin 2 Nov 2026, `tugas_awal` = menyapu): tugas 2–6 Nov = menyapu, 9 Nov = merapikan, 16 Nov = mengepel, 23 Nov = menyapu; simpan siswa ke-10 di satu hari ditolak; simpan siswa ke-4 dengan tugas yang sama di satu hari ditolak; pilihan hari Sabtu tidak ada dan ditolak server; jadwal diatur sebelum `tanggal_mulai_rotasi` → ringkasan & label tugas memakai `tugas_awal`.
   **Perbaikan catatan review Langkah 4 dikerjakan di langkah ini** (v0.15): `AkunController::update` membaca kelas lama dari baris akun yang sudah dikunci, bukan dari data sebelum dikunci; ada tes dengan data akun yang berubah setelah dibaca.
   **Teknis — urutan kunci simpan jadwal** (v0.15), dalam satu transaksi, diulang maks 3 kali kalau bentrok kunci/deadlock:
   1. Kunci baris akun siswa yang ada di form (ditambah akun PJ yang menyimpan) dengan `sharedLock()`, lewat primary key, urut id — **sebelum** bacaan biasa pertama.
   2. Kunci baris kelas dengan `lockForUpdate()` (antre dengan simpan jadwal lain dan hapus kelas). Kelas sudah dihapus → 404.
   3. Cek ulang hak akses dari baris yang dikunci (guru: `wali_kelas_id`; PJ: `class_id` & role akunnya sendiri).
   4. Cek keanggotaan setiap siswa di form dari baris akun yang dikunci (ada, `siswa`/`pj_kebersihan`, `class_id` = kelas ini). Gagal → seluruh simpan ditolak (pesan F2).
   5. Baca jadwal kelas yang ada, gabungkan dengan isian (siswa di luar form tidak diubah), cek batas 9/3 pada hasil akhir, lalu tulis (ubah/tambah/hapus hanya untuk siswa di form).
   - Alasan kunci akun tidak dihindari: menambah baris jadwal baru otomatis mengunci baca baris akun itu (pemeriksaan foreign key InnoDB). Kalau kunci itu baru terjadi di akhir (setelah kunci kelas), urutannya kelas→akun, berlawanan dengan pindah kelas (akun→kelas tujuan) sehingga bisa deadlock. Dengan mengunci akun lebih dulu, urutannya sama dengan pindah kelas: **akun → kelas**. Kunci baca bersama tidak saling menghalangi antar simpan jadwal; hanya pindah kelas, hapus akun, dan reset password akun yang sama yang menunggu sebentar.
   - Akibatnya untuk pindah kelas bersamaan: kalau pindah kelas selesai duluan, simpan jadwal melihat siswa itu bukan anggota → ditolak; kalau simpan jadwal duluan, pindah kelas menunggu lalu menghapus jadwal kelas lama siswa itu. Tidak ada jadwal yang tertinggal.
   Selesai kalau juga (v0.15): `siswa` → 403; PJ hanya kelasnya, guru hanya kelas walinya, admin hanya sekolahnya (selain itu 404); simpan hanya lewat form dengan CSRF; boleh simpan dengan siswa belum terjadwal dan daftar "Belum terjadwal" tampil; dropdown tugas = tugas minggu acuan dan `tugas_awal` disimpan hasil hitung mundur; **form tanpa perubahan yang disimpan di minggu berbeda dari minggu acuan → `tugas_awal` semua siswa tidak berubah** (dengan uji mutasi); form yang ditampilkan sebelum `tanggal_mulai_rotasi` lalu disimpan sesudahnya → pilihan disimpan apa adanya; `minggu_acuan` bukan Senin / di masa depan / lebih dari 4 minggu lalu ditolak; siswa yang tidak ada di form tidak berubah; siswa yang dikirim tapi bukan anggota kelas → seluruh simpan ditolak; teks layar sesuai §8.1 termasuk "Perubahan langsung berlaku, termasuk minggu ini."; pindah kelas membaca kelas lama dari baris akun yang dikunci.

   **5c. Fondasi desain** (v0.16). **Urutan pengerjaan: setelah Langkah 8, sebelum Langkah 9.** Nomornya 5c, bukan 9, supaya nomor langkah lain tidak berubah, karena nomor langkah dirujuk di dokumen ini dan di komentar kode. Langkah ini hanya mengubah tampilan.
   - **Isi:**
     - **Token warna** didefinisikan di satu tempat (`resources/css/app.css`), masing-masing dengan nilai mode terang dan mode gelap: warna dasar (latar halaman), permukaan/kartu, teks (biasa + lembut, mis. teks bantuan), garis, utama/aksi (+ warna teks di atasnya), sukses, peringatan, bahaya. Nama token bahasa Indonesia, mis. `dasar`, `permukaan`, `teks`, `teks-lembut`, `garis`, `utama`, `sukses`, `peringatan`, `bahaya`.
     - Kontrol bawaan browser (pemilih tanggal/jam, dropdown) ikut mode gelap lewat properti CSS `color-scheme`.
     - **Komponen Blade bersama:**
       - tombol: utama, sekunder, bahaya. Keadaan nonaktif dan "Menyimpan…" tetap memakai skrip pencegah klik ganda yang ada (`resources/js/app.js`, atribut `data-teks-proses` dan `data-tetap-nonaktif`); teks proses tiap tombol tidak berubah;
       - kartu;
       - input/dropdown: memakai ulang `x-field` dan `x-select` yang sudah ada. Hanya warnanya yang diubah; perilakunya tetap (teks 16 px supaya HP tidak auto-zoom, tombol tampilkan/sembunyikan password, isian lama setelah validasi gagal, atribut `aria-*`);
       - pesan: sukses, error, info (termasuk pesan `status` di layout dan `x-admin.pesan-error`).
     - **Dark mode untuk semua halaman yang sudah ada:** login, ganti password, halaman sementara (`/beranda`, `/verifikasi`), halaman 404, semua halaman admin (dashboard, kelas, akun termasuk kotak password sekali tampil, hari libur, pengaturan), dan jadwal (pilih kelas + kelola jadwal).
     - Tampilan modern yang cocok untuk siswa SMA, mobile-first, tombol besar (target sentuh minimal 44×44 px), kontras terbaca **WCAG AA** di kedua mode: teks biasa minimal 4.5:1, teks besar minimal 3:1.
     - Fokus keyboard terlihat (`focus-visible`) di semua tombol, tautan, dan isian, dengan garis fokus yang kontrasnya minimal 3:1 terhadap latar, di kedua mode.
     - Halaman yang dibuat setelah 5c (Langkah 9 dst.) wajib memakai token dan komponen ini.
   - **Batasan:**
     - Tidak menambah fitur, route, atau kolom, dan tidak mengubah teks atau perilaku yang sudah ada di SPEC; hanya tampilan. Tidak ada tombol mode gelap.
     - **Teks pesan tidak boleh diubah**, baik di aplikasi maupun di tes. Tes lama yang memeriksa kelas CSS boleh disesuaikan; tes yang memeriksa teks pesan tidak.
     - Tidak ada aset eksternal dari CDN (font, ikon, CSS, skrip). Font bawaan proyek (Instrument Sans, `vite.config.js`) tetap seperti sekarang.
   - **(1) Cara dark mode aktif — diputuskan (a) (v0.16):** mengikuti pengaturan HP/OS (`prefers-color-scheme`). **Tidak ada tombol mode** dan tidak ada JavaScript untuk mode, jadi tidak ada kedip warna dan tidak ada pilihan yang disimpan. Di komputer sekolah, mode ikut pengaturan komputer itu. Alasan: paling sedikit kode dan tidak menambah fitur.
   - **(2) Cara mengaktifkan dark mode di Tailwind — diputuskan (a) (v0.16):** varian `dark:` bawaan **Tailwind CSS v4** (terpasang: `^4.0.0` di `package.json`, terkunci 4.3.3; konfigurasi di CSS lewat `@theme` di `resources/css/app.css`, tanpa `tailwind.config.js`). Varian ini berbasis media query `prefers-color-scheme`; **tanpa `@custom-variant`**. Nilai mode gelap tetap ditulis **sekali di token** (variabel CSS yang nilainya diganti di blok `@media (prefers-color-scheme: dark)` di `app.css`), jadi view hampir tidak perlu kelas `dark:`.
   - **(2a) Mekanisme transisi dark mode — diputuskan (v0.17):** berlaku hanya selama 5c belum selesai (sesi 1–2).
     - Layout bersama (`resources/views/components/layouts/app.blade.php`) punya prop `modeGelap`. Halaman yang sudah memakai token mengirim `mode-gelap`. Halaman lain mendapat atribut `data-hanya-terang` di `<html>` dan `<meta name="color-scheme" content="light">`, jadi tetap terang.
     - Di `resources/css/app.css`, nilai gelap token ditulis di `@media (prefers-color-scheme: dark) { :root:not([data-hanya-terang]) { … } }`.
     - Alasan: layout, `x-field`, dan `x-select` dipakai semua halaman. Tanpa mekanisme ini, di mode gelap teks dari layout yang terang akan tampil di atas kartu putih halaman lama yang belum diubah.
     - Selama transisi, view yang sudah memakai token **tidak boleh memakai kelas `dark:` langsung**. Kelas `dark:` bawaan memakai media query biasa, jadi ikut gelap juga di halaman `data-hanya-terang`. Nilai gelap hanya lewat token.
     - Keputusan (2) tetap berlaku: varian `dark:` bawaan Tailwind, tanpa `@custom-variant`.
     - **Wajib dihapus di sesi terakhir 5c (sesi 3):** prop `modeGelap`, atribut `data-hanya-terang`, dan pengecualian `:not([data-hanya-terang])` di `app.css`. Setelah itu semua halaman memakai `<meta name="color-scheme" content="light dark">`.
     - Ini bukan fitur: tidak ada route, kolom, JavaScript, atau tombol baru.
   - **(3) Referensi tampilan — diputuskan (b) (v0.16):** tanpa referensi; dibuat dari prinsip di atas. Pemilik proyek menyetujui lewat **contoh halaman login dulu** (sesi 1). Halaman lain dikerjakan setelah contoh itu disetujui.
   - **Pembagian sesi** (v0.16, diperjelas v0.17; satu sesi per bagian, tes hijau di akhir tiap sesi):
     1. **Sesi 1:** token + `color-scheme` + komponen bersama + layout (termasuk mekanisme transisi (2a)) + `x-field`/`x-select` + halaman login sebagai contoh. **Contoh login menunggu persetujuan pemilik proyek; sesi 2 baru dimulai setelah disetujui.**
     2. **Sesi 2:** ganti password, halaman sementara (`/beranda`, `/verifikasi`), halaman 404, jadwal (pilih kelas + kelola jadwal).
     3. **Sesi 3 (terakhir):** semua halaman admin + penghapusan mekanisme transisi (2a) + pemeriksaan grep dan kontras + butir tes manual.
   - **Selesai kalau:**
     - semua halaman yang ada dirender tanpa error di kedua mode: feature test membuka setiap halaman dengan role yang berhak (200; halaman 404 → 404), dan setiap halaman memakai layout bersama yang memuat pengaturan mode gelap (mis. `<meta name="color-scheme" content="light dark">`). Tampilan kedua mode dicek di browser dengan emulasi `prefers-color-scheme` (DevTools);
     - tidak ada warna yang ditulis langsung di view, selain lewat token/komponen. Dicek dengan grep di `resources/views`, mis. pola `(bg|text|border|ring|outline|divide|from|via|to|fill|stroke|placeholder|accent|caret|decoration|shadow)-(slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|black|white)\b`, ditambah warna arbitrer (`-[#`, `rgb(`) dan `style="…color…"` → tidak ada hasil. Pengecualian hanya yang tercatat di sini beserta alasannya (saat ini: tidak ada). `currentColor` di SVG dan `transparent` bukan pelanggaran;
     - pasangan warna teks/latar di token memenuhi kontras AA di kedua mode: tabel kontras di sini (pasangan, rasio mode terang, rasio mode gelap) **atau** tes otomatis yang menghitung rasio dari nilai token;
     - tombol utama dan dropdown di halaman siswa/PJ (ganti password, halaman sementara, jadwal) tingginya minimal 44 px (mis. kelas `min-h-11` di komponen, dicek tes);
     - seluruh tes yang ada tetap hijau, dan teks pesan di aplikasi maupun di tes tidak berubah (hanya tes yang memeriksa kelas CSS yang boleh disesuaikan);
     - tes manual di HP (mode terang dan gelap lewat pengaturan HP, tombol mudah diketuk, pemilih tanggal/jam terbaca) masuk `docs/tes-manual-tertunda.md`;
     - **syarat sesi terakhir (sesi 3) (v0.17):**
       - pencarian `data-hanya-terang|modeGelap|mode-gelap` di `resources/` dan `tests/` tidak menemukan apa pun;
       - setiap halaman memakai `<meta name="color-scheme" content="light dark">`;
       - tes sementara "halaman yang belum diubah tetap terang" dihapus atau dibalik menjadi pemeriksaan bahwa semua halaman ikut mode gelap.
9. F3 + F4 Submit dengan batas waktu & keterlambatan (+ `submissions.tugas` diisi server, aturan tanggal libur dan tanggal sebelum `tanggal_mulai_rotasi`).
   Selesai kalau: `submissions.tugas` sesuai rotasi walau form dikirim dengan nilai tugas lain; perilaku di tanggal libur sesuai keputusan F3; submit sebelum `tanggal_mulai_rotasi` ditolak.
   Selesai kalau juga (v0.14): tidak ada lagi `whereDate` di kode, dan tes penuh hijau di SQLite dan MySQL sementara; submit yang ditolak di dalam transaksi tidak meninggalkan file foto di storage; status `terlambat` dan `created_at` memakai waktu awal request, jadi berkas yang sampai sebelum jam tutup tetap tepat waktu walau kompresi foto melewati jam tutup.
   Teknis awal (v0.13, dikerjakan pertama di langkah ini, sebelum fitur submit): buat cast tanggal khusus yang selalu menyimpan format `Y-m-d` (jamnya dibuang), lalu pakai di model yang punya kolom tanggal (`submissions.tanggal`, `hari_libur.tanggal`, `classes.tanggal_mulai_rotasi`). Alasannya (temuan Langkah 6): cast `date` bawaan menyimpan `Y-m-d H:i:s`, jadi di SQLite (tes) tanggal tersimpan dengan jam `00:00:00`, sedangkan `date:Y-m-d` menyimpan nilai apa adanya tanpa dirapikan. Setelah cast ini ada, perbandingan tanggal biasa (`where`, `whereBetween`) bisa memakai index. Diuji di SQLite **dan** MySQL sementara, termasuk: nilai Carbon yang punya jam tersimpan tanpa jam, dan rentang `whereBetween` Senin–Jumat mencakup hari terakhir (Jumat).
   - **Satu cara membandingkan tanggal** (diputuskan v0.14): sebagai bagian dari Teknis awal ini, semua `whereDate` yang sudah ada **wajib** diganti dengan perbandingan biasa. Saat ini ada di Langkah 6: cek "sudah terdaftar" (`TambahHariLiburRequest`) dan cek "sudah punya submit" (`HariLiburController`). Tes Langkah 6 yang sengaja memakai tanggal tersimpan dengan jam disesuaikan ke format baru. Syarat selesai: tes penuh hijau di SQLite **dan** MySQL sementara, sebelum fitur submit dikerjakan.
   Teknis: akun bisa dihapus admin (F9) bersamaan dengan submit/verifikasi. Hapus akun mengunci baris akun itu, jadi database menolak submit/verifikasi yang menunjuk akun yang sudah terhapus (foreign key) — tangkap dan tampilkan sebagai pesan, bukan halaman error (berlaku juga untuk Langkah 10).
   Teknis (v0.12): penambahan hari libur (Langkah 6) dan submit bisa terjadi bersamaan. Tambah hari libur menolak tanggal yang sudah punya submit (F8), jadi submit **wajib mengecek hari libur di dalam transaksi**, setelah mengunci baris `schools` sekolah itu sebelum bacaan biasa pertama: submit memakai kunci baca bersama (`sharedLock()`), tambah hari libur memakai `lockForUpdate()`. Kunci baca bersama tidak saling menghalangi, jadi submit tidak saling antre di jam sibuk; tambah hari libur menunggu submit yang sedang berjalan selesai, dan sebaliknya. Tanpa kunci di baris yang sama, keduanya bisa lolos pengecekan dan tanggal libur tetap punya submit. Perilaku submit di tanggal libur tetap mengikuti keputusan F3 (masih terbuka sampai langkah ini).
   Teknis (v0.14, dari review Langkah 7) — urutan proses submit:
   1. **Waktu submit diambil sekali di awal request**, saat server mulai menangani request (berkas sudah diterima server), sebelum validasi dan proses foto. Satu nilai ini dipakai untuk semua keputusan waktu: tanggal submit, apakah sudah lewat jam tutup (`terlambat`) atau lewat 23:59, dan `created_at`. Jadi siswa yang berkasnya sampai tepat waktu tidak dihitung terlambat karena kompresi foto lama, dan submit pukul 23:59:59 tetap tercatat di tanggal itu.
   2. **Foto diproses di luar transaksi**, sebelum transaksi: cek tipe & ukuran, kompres, simpan ke storage privat dengan nama acak (§9). Dengan begitu kunci di dalam transaksi hanya dipegang sebentar, dan simpan pengaturan atau tambah hari libur tidak tertahan lama di jam sibuk.
   3. **Transaksi singkat**: kunci baris `schools` dengan `sharedLock()` sebelum bacaan biasa pertama, lalu baca jam buka/tutup **dari baris yang dikunci itu** (bukan dari data sekolah yang dibaca sebelum transaksi), cek hari libur, cek submit aktif, lalu simpan baris `submissions` dan `submission_photos`. Jam dari baris yang dikunci dibandingkan dengan waktu dari langkah 1.
   4. **Kalau transaksi ditolak atau gagal** (mis. hari libur baru ditambahkan, sudah ada submit aktif, akun baru saja dihapus, atau error database), **file foto yang sudah tersimpan di langkah 2 wajib dihapus**, supaya tidak ada file yatim di storage. Berlaku juga kalau penolakan berupa pesan validasi. Ada tes untuk tiap jenis penolakan: file tidak tertinggal, dan tidak ada baris yang tersimpan.
   Teknis: tombol submit yang dinonaktifkan dari server (mis. sebelum jam buka, F4) wajib diberi atribut `data-tetap-nonaktif`, supaya tidak diaktifkan lagi oleh skrip pencegah klik ganda (`resources/js/app.js`).
10. F5 Verifikasi (kartu menampilkan tugas)

    **10b. Deploy ke hosting untuk pengujian** (v0.16). **Urutan pengerjaan: setelah Langkah 10, sebelum Langkah 11.** Nomornya 10b dengan alasan yang sama seperti 5c. Langkah ini terutama pekerjaan server, bukan kode. Satu-satunya kode baru adalah perintah artisan admin pertama (2), dikerjakan sebagai sesi tersendiri sebelum deploy; kode lain yang ternyata dibutuhkan ditulis di SPEC dulu.
    - **Isi:**
      - Ikuti "Daftar periksa deploy (hosting)" di §10.
      - Document root domain mengarah ke folder `public/`, bukan folder proyek, supaya `.env` dan `storage` tidak bisa dibuka dari browser.
      - Aplikasi dibuka lewat **HTTPS**: HTTP dialihkan ke HTTPS (pengaturan hosting/`.htaccess`), `APP_URL` memakai `https://`, dan cookie sesi aman (`SESSION_SECURE_COOKIE=true`).
      - **Cron** untuk Laravel Scheduler terpasang (`php artisan schedule:run` tiap menit) dan terbukti jalan. Sebelum Langkah 12 belum ada tugas terjadwal, jadi buktinya dari output cron yang diarahkan ke file log (berisi "No scheduled commands are ready to run." tiap menit), tanpa kode baru.
      - **Ukur waktu import 50 akun** (file CSV data palsu) di server, karena batas 50 di F9 dihitung dari ± 0,4 detik per akun di lokal. Bandingkan dengan `max_execution_time` PHP server. Kalau mendekati batas, usulkan perubahan batas (berlaku juga untuk reset massal, yang memakai batas 50 yang sama, F9/§8.2).
        Hasil (diisi saat Langkah 10b): waktu import 50 akun: … detik; `max_execution_time` server: … detik.
      - **Data uji palsu saja**: tidak ada data atau foto siswa asli sebelum Langkah 12 (penghapusan foto mingguan) selesai (§9, UU PDP). Foto uji mis. foto lantai atau sapu, bukan wajah.
      - `APP_DEBUG=false` dicek dengan membuka halaman error, mis. `GET /logout` (route itu hanya POST, jadi muncul 405): yang tampil hanya halaman singkat, tanpa jejak kode.
      - Kalau saat deploy ditemukan langkah yang belum ada di daftar periksa §10 (mis. `npm run build`, `php artisan migrate --force`), usulkan tambahannya ke §10 di versi SPEC berikutnya.
    - **(1) Penyedia hosting — diputuskan (a) (v0.16):** shared hosting **dengan akses SSH** (untuk `composer` dan `php artisan`). Syarat minimal dari §10: PHP ≥ 8.3, MySQL ≥ 8.0.19 / MariaDB ≥ 10.2, cron, HTTPS. Saat memilih, cek juga: document root bisa diarahkan ke `public/`, `max_execution_time`, dan bahasa pesan error MySQL (§10). Alasan: aplikasinya kecil, dan VPS menambah pekerjaan keamanan server.
      Catatan: penyedianya ditentukan nanti.
    - **(2) Cara membuat admin pertama di produksi — diputuskan: perintah artisan sekali pakai (v0.16).** Akun contoh seeder tidak dipakai (seeder data contoh tidak dijalankan di produksi). Produksi belum punya baris `schools` (tidak ada halaman tambah sekolah), jadi perintah ini membuat sekolahnya juga. Perilaku:
      - meminta nama sekolah, nama admin, dan email;
      - **meminta password lewat isian tersembunyi** (tidak tampil saat diketik), diketik dua kali (konfirmasi), minimal 8 karakter (sama dengan F1);
      - membuat baris `schools` (jam default F4) + admin pertama di sekolah itu, dengan `harus_ganti_password = true`;
      - **menolak berjalan kalau sudah ada admin** (di sekolah mana pun), tanpa mengubah apa pun;
      - semuanya dalam **satu transaksi** (tersimpan semua atau tidak sama sekali).
      Nama perintah, teks pertanyaan, dan teks pesannya **belum ditulis**: ditulis di SPEC dulu, lalu dikerjakan sebagai sesi tersendiri dengan tes, sebelum deploy.
    - **(3) Domain — diputuskan (b) (v0.16):** subdomain untuk pengujian (mis. dari penyedia hosting, atau dari domain sekolah dengan izin pengelolanya). Domain akhir diputuskan sebelum Langkah 13. Kalau domain diganti nanti, semua user login ulang (cookie sesi terikat domain) dan HTTPS dipasang lagi.
    - **(4) Data uji — diputuskan (v0.16):**
      - sebelum Langkah 13 (uji coba dengan kelas sungguhan) dipakai **database bersih**;
      - di hosting uji, **semua data uji dihapus, termasuk berkas di storage** (mis. foto uji), **hanya setelah persetujuan eksplisit pemilik proyek**. Jangan `migrate:fresh` tanpa bertanya (CLAUDe2.md);
      - setelah itu admin dibuat dengan perintah artisan (2), lalu akun asli diimport (F9).
    - **Selesai kalau:**
      - halaman login terbuka lewat `https://`, dan `http://` dialihkan ke `https://`; cookie sesi bertanda `Secure`;
      - semua butir daftar periksa §10 dicentang;
      - membuka `/.env` dari browser tidak menampilkan isi file;
      - cron terbukti jalan (mis. file log cron bertambah tiap menit);
      - waktu import 50 akun tercatat di langkah ini;
      - halaman error tidak menampilkan detail teknis;
      - tidak ada akun contoh seeder di produksi (login dengan akun contoh gagal), dan admin produksi dibuat lewat perintah artisan (2);
      - perintah artisan (2) yang dijalankan kedua kali ditolak tanpa mengubah apa pun;
      - tidak ada data siswa asli;
      - tes manual di hosting yang dikerjakan penguji lain masuk `docs/tes-manual-tertunda.md`.
11. F6 Rekap mingguan + CSV (Senin–Jumat, tanggal libur dan tanggal sebelum `tanggal_mulai_rotasi` dikecualikan).
    Selesai kalau: siswa terjadwal Rabu, Rabu libur, tanpa submit → terjadwal 0 dan tidak piket 0 untuk minggu itu; minggu berikutnya tugasnya tetap bergeser satu langkah; minggu sebelum `tanggal_mulai_rotasi` → terjadwal 0 dan tidak piket 0.
    Catatan dari Langkah 5: pelindung rumus Excel (nilai yang diawali `=`, `+`, `-`, `@`, tab, atau enter diberi `'` di depan) sekarang ada di `AkunController::csvPassword`. Pindahkan ke tempat bersama (mis. `App\Support`) dan pakai juga untuk ekspor rekap, karena nama siswa dari import bisa diawali `=`. Jangan ditulis dua kali. Saat dipindah, tambahkan juga `\n` (baris baru): versi sekarang hanya menangkap tab dan `\r`, cukup untuk nama hasil import (sel di-trim), tapi belum untuk data lain seperti alasan tolak.
12. F7 Penghapusan foto mingguan (scheduler) + peringatan mulai Jumat sore
13. Uji coba dengan 1 kelas sungguhan selama 1 minggu (Senin–Jumat)
