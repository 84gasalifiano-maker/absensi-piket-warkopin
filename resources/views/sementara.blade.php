{{-- Halaman sementara untuk /beranda dan /verifikasi (Langkah 2), sampai dibuat di Langkah 9/10. --}}
<x-layouts.app :judul="$judul" mode-gelap>
    <h1 class="mb-3 text-2xl font-semibold tracking-tight">{{ $judul }}</h1>

    <x-kartu>
        <p class="mb-1">Halo, {{ auth()->user()->nama }}.</p>
        <p class="text-teks-lembut">Halaman ini masih dalam pembuatan.</p>

        {{-- Tautan ke kelola jadwal sampai beranda/verifikasi dibuat (SPEC §8.1, v0.15):
             PJ di /beranda, guru di /verifikasi. --}}
        @php($role = auth()->user()->role)
        @if ((request()->routeIs('beranda') && $role === \App\Enums\Role::PjKebersihan)
            || (request()->routeIs('verifikasi') && $role === \App\Enums\Role::Guru))
            <x-tombol :href="route('jadwal')" class="mt-5 w-full">Jadwal piket</x-tombol>
        @endif
    </x-kartu>
</x-layouts.app>
