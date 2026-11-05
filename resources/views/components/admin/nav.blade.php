{{-- Menu admin (SPEC F9). Sejak Langkah 7 semua halaman admin sudah ada, jadi tidak ada lagi menu "segera". --}}
{{-- Warna dari token (Langkah 5c sesi 2): dipakai juga di halaman jadwal yang sudah ikut mode gelap. --}}
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
               'inline-flex min-h-11 items-center rounded-xl px-4 py-2 font-medium transition-colors',
               'bg-utama text-pada-utama' => $aktif,
               'border border-garis-isian bg-permukaan text-teks hover:bg-permukaan-lembut' => ! $aktif,
           ])>{{ $teks }}</a>
    @endforeach
</nav>
