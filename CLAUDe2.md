# CLAUDe2.md — Aturan Proyek Absensi Piket

## Konteks
Web app absensi piket kelas untuk murid. Pengguna utama: siswa di HP. Spesifikasi lengkap ada di `SPEc2.md` — baca sebelum mengerjakan fitur apa pun.

## Aturan utama
1. **SPEc2.md adalah sumber kebenaran.** Jangan menambah fitur, endpoint, kolom, atau tabel yang tidak ada di SPEc2.md. Kalau perlu, usulkan perubahan SPEC dulu dan tunggu persetujuan.
2. **Bagian [KEPUTUSAN TERBUKA] jangan diputuskan sendiri.** Tanyakan ke saya.
3. **Satu fitur per sesi.** Kerjakan sesuai urutan di SPEc2.md bagian 11. Jangan lompat.
4. **Kontrak API tidak boleh berubah diam-diam.** Nama endpoint, field, dan kode error harus persis seperti di SPEc2.md bagian 7.
5. **Jangan buat tabel/kolom duplikat.** Cek skema yang ada sebelum migrasi baru.

## Gaya kerja
- Sebelum menulis kode: jelaskan rencana singkat (file mana yang diubah, kenapa).
- Setelah selesai: sebutkan cara mengetes fitur itu secara manual.
- Saya masih belajar — jelaskan keputusan teknis dengan bahasa sederhana, bahasa Indonesia.
- Perubahan kecil dan bertahap lebih baik daripada satu perubahan besar.

## Stack
Laravel 13 (PHP ≥ 8.3) + Blade + MySQL. Lihat SPEc2.md bagian 10.

## Lingkungan lokal
- OS: Windows. Server lokal: Laragon (Apache + MySQL), PHP 8.3+.
- Folder proyek: `D:\laragon\www\absensi-piket`
- URL lokal: `http://absensi-piket.test`
- Database: MySQL, nama `absensi_piket`, user `root`, tanpa password (khusus lokal).
- Jalankan perintah di terminal VS Code (PowerShell). Gunakan `php artisan ...`, `composer ...`, `npm ...`.
- Jangan menjalankan `php artisan migrate:fresh` atau perintah yang menghapus data tanpa bertanya dulu.

## Standar kode
- Ikuti konvensi Laravel: migration untuk semua perubahan tabel (jangan ubah tabel manual lewat phpMyAdmin), Eloquent model, Form Request untuk validasi, Policy/Gate atau middleware untuk hak akses.
- Jangan tulis query SQL mentah dengan input user. Pakai Eloquent / query builder.
- Semua pengecekan role & kelas dilakukan di server, bukan hanya di tampilan.
- Timezone aplikasi `Asia/Jakarta`. Validasi jendela waktu pakai waktu server (`now()`), bukan dari browser.
- Teks UI dan pesan error dalam bahasa Indonesia.
- Mobile-first untuk halaman siswa & verifikator.
- Tugas terjadwal (penghapusan foto mingguan) lewat Laravel Scheduler di `routes/console.php`.

## Keamanan (tidak boleh dilanggar)
- Jangan commit `.env`, password, atau API key. Gunakan `.env.example` berisi placeholder.
- Upload: cek tipe & ukuran file di server, nama file acak, storage privat.
- Foto & data siswa hanya bisa diakses sesuai aturan SPEc2.md bagian 9.

## Selesai berarti
- Fitur sesuai SPEc2.md
- Tidak ada error di console/terminal
- Sudah dites manual dengan langkah yang dijelaskan
- Sudah di-review oleh agent `reviewer`
