# Tes manual tertunda

Hal yang tidak bisa dicek tes otomatis: file yang dibuka di aplikasi lain, dan tampilan di HP.
Centang butir yang sudah dites. Kalau hasilnya tidak sesuai, catat di bawah butir itu.

---

## [ ] Langkah 5a: daftar password hasil reset massal

**Apa yang dites:** file CSV dari reset massal terbuka rapi di Excel, isinya lengkap, dan passwordnya bisa dipakai login.

**Langkah:**
1. Login sebagai admin. Buka **Kelas → Ubah** di kelas yang punya siswa yang belum pernah mengganti password. Tekan **Reset password {n} akun**, konfirmasi, lalu tekan **Unduh daftar password**.
2. Buka file itu di Excel (klik dua kali).
3. Logout. Login memakai NIS dan password salah satu baris di file itu.

**Hasil yang diharapkan:**
- Excel langsung menampilkan 5 kolom terpisah: `nama`, `kelas`, `no_absen`, `nis`, `password`. Tidak menumpuk di satu kolom, dan huruf seperti "é" atau "ñ" tidak rusak.
- Jumlah baris akun di file sama dengan angka {n} di pesan sukses "Password {n} akun di kelas … direset."
- Baris urut no. absen.
- Login berhasil, lalu diarahkan ke halaman **Ganti password** tanpa kolom password lama.

---

## [ ] Langkah 5, 6, 7: halaman admin di HP

**Apa yang dites:** halaman admin tetap bisa dipakai di layar kecil. Halaman admin memang dibuat untuk laptop, tapi tidak boleh rusak di HP.

**Langkah:**
1. Login sebagai admin di HP.
2. Buka satu per satu: **Akun** (termasuk form import CSV), **Hari libur**, **Pengaturan**.
3. Di tiap halaman, coba satu aksi: filter akun, tambah satu hari libur, simpan jam tanpa perubahan.

**Hasil yang diharapkan:**
- Tidak ada teks atau tombol yang terpotong. Tabel yang lebar bisa digeser ke samping.
- Pemilih tanggal (hari libur) dan pemilih jam (pengaturan) bawaan HP muncul dan bisa dipakai.
- Setiap aksi menampilkan pesan sukses atau pesan error berbahasa Indonesia.

---

## [ ] Langkah 8: halaman jadwal di HP

**Apa yang dites:** PJ bisa mengatur jadwal kelas dari HP: dropdown hari dan tugas, serta tombol Simpan yang menempel di bawah layar.

**Langkah:**
1. Login sebagai PJ kebersihan di HP. Ketuk **Jadwal piket** di beranda.
2. Gulir daftar siswa sampai bawah, melewati daftar "Belum terjadwal".
3. Pilih hari dan tugas untuk satu siswa, lalu tekan **Simpan**.

**Hasil yang diharapkan:**
- Tombol **Simpan** selalu terlihat, menempel di bawah layar, selama daftar digulir.
- Dropdown hari berisi "— Belum terjadwal —" dan Senin sampai Jumat (tanpa Sabtu). Dropdown tugas berisi Menyapu, Merapikan meja & kursi, dan Mengepel. Keduanya mudah diketuk dan teksnya tidak terpotong.
- Judul kolom menyebut minggunya, mis. "Tugas minggu ini (2–8 Nov)".
- Setelah simpan, muncul "Jadwal tersimpan.", dan ringkasan di atas (x/9 per hari, x/3 per tugas) ikut berubah.

---

## [ ] Langkah 5c sesi 1: halaman login di HP, mode terang dan gelap

**Apa yang dites:** halaman login (contoh pertama Fondasi desain) terbaca dan mudah dipakai di HP, dan mengikuti mode gelap dari pengaturan HP.

**Langkah:**
1. Di HP, buka halaman login dengan mode terang (pengaturan tampilan HP: terang).
2. Ganti pengaturan HP ke mode gelap, lalu buka/muat ulang halaman login.
3. Di kedua mode: ketik NIS dan password, ketuk tombol mata, centang "Ingat saya", lalu ketuk **Masuk**.

**Hasil yang diharapkan:**
- Mode halaman ikut pengaturan HP, tanpa tombol mode. Di mode gelap latar gelap dan teks terang; kontrol bawaan browser (kotak centang, isian) ikut bertema gelap.
- Semua teks terbaca jelas di kedua mode, termasuk teks bantuan dan pesan error (coba password salah).
- Kolom isian, tombol mata, kotak "Ingat saya", dan tombol **Masuk** mudah diketuk jari. Layar tidak membesar sendiri saat kolom diketuk.
- Setelah masuk, halaman siswa/PJ/guru (beranda, verifikasi, ganti password, jadwal) ikut mode HP; halaman admin tetap terang (sementara, sampai sesi 3 Langkah 5c).

---

## [ ] Langkah 5c sesi 2: halaman siswa/PJ/guru di HP, mode terang dan gelap

**Apa yang dites:** ganti password, beranda/verifikasi sementara, 404, pilih kelas, dan kelola jadwal terbaca dan mudah dipakai di HP asli. Termasuk pilihan dropdown bawaan HP dan bilah Simpan saat keyboard muncul (keduanya tidak bisa ditangkap screenshot otomatis).

**Langkah:**
1. Di HP dengan mode gelap, login sebagai PJ, buka **Jadwal piket**. Ketuk dropdown hari dan tugas beberapa siswa, termasuk di baris yang berada tepat di atas bilah Simpan. Gulir sampai bawah, lalu tekan **Simpan**.
2. Masih di mode gelap: login dengan akun baru yang wajib ganti password, lalu login sebagai guru (buka **Verifikasi** → **Jadwal piket**), lalu sebagai admin (buka **Kelas** → **Jadwal**, lalu ketuk menu **Dashboard**). Buka juga alamat yang tidak ada (mis. `/abc`) untuk halaman 404.
3. Ganti pengaturan HP ke mode terang, lalu ulangi langkah 1.

**Hasil yang diharapkan:**
- Daftar pilihan dropdown bawaan HP terbaca di kedua mode: pilihan hari tanpa Sabtu, dan tugas Menyapu / Merapikan meja & kursi / Mengepel.
- Bilah **Simpan** selalu terlihat di bawah layar, tidak menutupi dropdown yang sedang diketuk, dan saat digulir sampai bawah tidak menutupi baris terakhir atau daftar "Belum terjadwal".
- Semua teks, tombol, dan kotak pesan terbaca di kedua mode. Tidak ada halaman yang bisa digeser ke samping.
- Ganti password wajib (akun baru): kotak "Kamu wajib mengganti password…" terbaca di mode gelap, tanpa kolom password lama.
- Admin: halaman pilih kelas dan kelola jadwal ikut gelap, termasuk menu admin. Setelah pindah ke **Dashboard**, halaman admin tampil terang dan menunya tetap terbaca (sementara, sampai sesi 3).
