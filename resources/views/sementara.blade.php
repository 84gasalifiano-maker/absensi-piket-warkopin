{{-- Halaman sementara untuk /beranda, /verifikasi, /admin (Langkah 2). --}}
<x-layouts.app :judul="$judul">
    <h1 class="mb-2 text-2xl font-bold">{{ $judul }}</h1>
    <p class="mb-1">Halo, {{ auth()->user()->nama }}.</p>
    <p class="text-gray-600">Halaman ini masih dalam pembuatan.</p>

    {{-- Tautan ke kelola jadwal sampai beranda/verifikasi dibuat (SPEC §8.1, v0.15):
         PJ di /beranda, guru di /verifikasi. --}}
    @php($role = auth()->user()->role)
    @if ((request()->routeIs('beranda') && $role === \App\Enums\Role::PjKebersihan)
        || (request()->routeIs('verifikasi') && $role === \App\Enums\Role::Guru))
        <a href="{{ route('jadwal') }}"
           class="mt-4 block rounded-lg bg-blue-600 px-4 py-3 text-center font-semibold text-white hover:bg-blue-700">Jadwal piket</a>
    @endif
</x-layouts.app>
