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
