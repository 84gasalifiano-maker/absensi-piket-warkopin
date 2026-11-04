{{-- Menu admin (SPEC F9). Halaman yang belum dibuat tampil dengan tanda "segera" dan tidak bisa diklik. --}}
@php
    $menu = [
        ['Dashboard', route('admin'), request()->routeIs('admin')],
        ['Kelas', route('admin.kelas.index'), request()->routeIs('admin.kelas.*')],
        ['Akun', route('admin.akun.index'), request()->routeIs('admin.akun.*')],
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

    @foreach (['Pengaturan'] as $teks)
        <span class="cursor-not-allowed rounded-lg border border-dashed border-gray-300 px-4 py-2 text-gray-500">
            {{ $teks }} <span class="text-xs">(segera)</span>
        </span>
    @endforeach
</nav>
