{{-- Menu admin (SPEC F9). Sejak Langkah 7 semua halaman admin sudah ada, jadi tidak ada lagi menu "segera". --}}
@php
    $menu = [
        ['Dashboard', route('admin'), request()->routeIs('admin')],
        ['Kelas', route('admin.kelas.index'), request()->routeIs('admin.kelas.*')],
        ['Akun', route('admin.akun.index'), request()->routeIs('admin.akun.*')],
        // Urutan sesuai SPEC F9: Kelas, Akun, Pengaturan, Hari libur.
        ['Pengaturan', route('admin.pengaturan.edit'), request()->routeIs('admin.pengaturan.*')],
        ['Hari libur', route('admin.hari-libur.index'), request()->routeIs('admin.hari-libur.*')],
    ];
@endphp

<nav aria-label="Menu admin" class="mb-6 flex flex-wrap gap-2">
    @foreach ($menu as [$teks, $url, $aktif])
        <a href="{{ $url }}" @if ($aktif) aria-current="page" @endif
           @class([
               'rounded-lg px-4 py-2 font-medium',
               'bg-blue-600 text-white' => $aktif,
               'border border-gray-300 bg-white hover:bg-gray-100' => ! $aktif,
           ])>{{ $teks }}</a>
    @endforeach
</nav>
