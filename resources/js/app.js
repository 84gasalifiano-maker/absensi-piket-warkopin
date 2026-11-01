// Tombol tampilkan/sembunyikan password (komponen resources/views/components/field.blade.php).
// Satu listener untuk semua tombol; setiap tombol mengatur kolom di aria-controls-nya sendiri.
document.addEventListener('click', (event) => {
    const tombol = event.target.closest('[data-toggle-password]');
    if (!tombol) return;

    const input = document.getElementById(tombol.getAttribute('aria-controls'));
    if (!input) return;

    const tampilkan = input.type === 'password';
    input.type = tampilkan ? 'text' : 'password';

    tombol.setAttribute('aria-label', tampilkan ? 'Sembunyikan password' : 'Tampilkan password');
    tombol.querySelector('[data-ikon="tampilkan"]').classList.toggle('hidden', tampilkan);
    tombol.querySelector('[data-ikon="sembunyikan"]').classList.toggle('hidden', !tampilkan);
});

// Saat form dikirim:
// 1. Form berbahaya minta konfirmasi dulu: <form data-konfirmasi="Hapus kelas X?">.
// 2. Cegah klik ganda (SPEC §8.1): tombol submit dinonaktifkan dan teksnya diganti
//    "Menyimpan…" (atau data-teks-proses di tombol). Klik ganda bisa, misalnya,
//    membuat password yang tampil sekali hilang tertimpa request kedua.
// Server tetap mengecek semua aturannya; ini hanya kenyamanan di browser.
document.addEventListener('submit', (event) => {
    const form = event.target;

    if (form.dataset.sedangDikirim) {
        event.preventDefault();
        return;
    }

    if (form.dataset.konfirmasi && !window.confirm(form.dataset.konfirmasi)) {
        event.preventDefault();
        return;
    }
    form.dataset.sedangDikirim = '1';

    form.querySelectorAll('button[type="submit"]').forEach((tombol) => {
        tombol.dataset.teksAsli = tombol.innerHTML;
        tombol.disabled = true;
        tombol.textContent = tombol.dataset.teksProses ?? 'Menyimpan…';
    });
});

// Kembali lewat tombol Back / halaman dipulihkan browser: aktifkan lagi tombolnya.
// Dijalankan di setiap pageshow (bukan hanya dari bfcache), karena sebagian browser
// memulihkan status disabled tombol saat halaman dimuat ulang.
// Tombol yang SENGAJA nonaktif dari server (mis. submit piket sebelum jam buka, SPEC F4)
// wajib diberi data-tetap-nonaktif, supaya tidak ikut diaktifkan di sini.
window.addEventListener('pageshow', () => {
    document.querySelectorAll('form').forEach((form) => {
        delete form.dataset.sedangDikirim;
        form.querySelectorAll('button[type="submit"]:not([data-tetap-nonaktif])').forEach((tombol) => {
            if (tombol.dataset.teksAsli) tombol.innerHTML = tombol.dataset.teksAsli;
            tombol.disabled = false;
        });
    });
});

// Form tambah akun: tampilkan hanya isian untuk role yang dipilih (SPEC §8 "isian mengikuti role").
// Tanpa JavaScript semua isian tetap tampil; server mengabaikan isian milik role lain.
function sesuaikanIsianRole(pilihan) {
    const form = pilihan.closest('form');
    form.querySelectorAll('[data-isian-role]').forEach((bagian) => {
        const cocok = pilihan.value === '' || bagian.dataset.isianRole.split(' ').includes(pilihan.value);
        bagian.hidden = !cocok;
        bagian.disabled = !cocok; // isian yang tersembunyi tidak ikut dikirim
    });
}

document.querySelectorAll('select[data-pilih-isian-role]').forEach((pilihan) => {
    sesuaikanIsianRole(pilihan);
    pilihan.addEventListener('change', () => sesuaikanIsianRole(pilihan));
});
