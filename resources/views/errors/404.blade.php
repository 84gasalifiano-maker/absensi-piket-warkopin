{{-- Halaman 404 (SPEC F9, v0.9): alamat tidak ada, data sekolah lain, atau data yang baru saja dihapus. --}}
<x-layouts.app judul="Halaman tidak ditemukan" mode-gelap>
    <x-kartu class="mt-4 sm:mt-10">
        <h1 class="mb-2 text-2xl font-semibold tracking-tight">Halaman tidak ditemukan</h1>
        <p class="mb-6 text-teks-lembut">
            Halaman atau data yang kamu cari tidak ada. Mungkin sudah dihapus, atau alamatnya salah.
        </p>
        <x-tombol :href="url('/')" class="w-full sm:w-auto">Kembali ke halaman awal</x-tombol>
    </x-kartu>
</x-layouts.app>
