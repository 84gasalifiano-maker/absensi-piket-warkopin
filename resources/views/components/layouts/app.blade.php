@props(['judul' => null, 'lebar' => false, 'modeGelap' => false])

{{-- lebar: halaman admin lebih lebar (dipakai di laptop, SPEC §8). --}}
@php($wadah = $lebar ? 'max-w-5xl' : 'max-w-md')

{{--
    Mode gelap mengikuti pengaturan HP/OS (SPEC §8, Langkah 5c, v0.16).
    Sementara, selama Langkah 5c belum selesai: hanya halaman yang sudah memakai token warna
    yang mengirim mode-gelap. Halaman lain diberi data-hanya-terang, jadi tetap terang dan
    warna lamanya tidak bercampur dengan token gelap. Prop ini dihapus di sesi terakhir 5c.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @unless ($modeGelap) data-hanya-terang @endunless>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="color-scheme" content="{{ $modeGelap ? 'light dark' : 'light' }}">

        <title>{{ $judul ? $judul.' — ' : '' }}Absensi Piket</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-dasar font-sans text-teks antialiased">
        @auth
            <header class="border-b border-garis bg-permukaan">
                <div class="mx-auto flex {{ $wadah }} items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ auth()->user()->nama }}</p>
                        <a href="{{ route('ganti-password') }}" class="inline-flex min-h-11 items-center text-sm font-medium text-utama underline underline-offset-2 hover:text-utama-hover">Ganti password</a>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <x-tombol varian="sekunder" data-teks-proses="Keluar…">Keluar</x-tombol>
                    </form>
                </div>
            </header>
        @endauth

        <main class="mx-auto {{ $wadah }} px-4 py-8">
            {{-- Pesan sukses dari redirect()->with('status', ...), tampil di semua halaman. --}}
            @if (session('status'))
                <x-pesan jenis="sukses" class="mb-4">{{ session('status') }}</x-pesan>
            @endif

            {{ $slot }}
        </main>
    </body>
</html>
