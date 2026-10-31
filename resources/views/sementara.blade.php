{{-- Halaman sementara untuk /beranda, /verifikasi, /admin (Langkah 2). --}}
<x-layouts.app :judul="$judul">
    <h1 class="mb-2 text-2xl font-bold">{{ $judul }}</h1>
    <p class="mb-1">Halo, {{ auth()->user()->nama }}.</p>
    <p class="text-gray-600">Halaman ini masih dalam pembuatan.</p>
</x-layouts.app>
