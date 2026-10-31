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
