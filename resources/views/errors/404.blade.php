{{-- Halaman 404 (SPEC F9, v0.9): alamat tidak ada, data sekolah lain, atau data yang baru saja dihapus. --}}
<x-layouts.app judul="Halaman tidak ditemukan">
    <h1 class="mb-2 text-2xl font-bold">Halaman tidak ditemukan</h1>
    <p class="mb-6 text-gray-700">
        Halaman atau data yang kamu cari tidak ada. Mungkin sudah dihapus, atau alamatnya salah.
    </p>
    <a href="{{ url('/') }}" class="inline-block rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-700">
        Kembali ke halaman awal
    </a>
</x-layouts.app>
