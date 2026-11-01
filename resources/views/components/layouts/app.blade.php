@props(['judul' => null, 'lebar' => false])

{{-- lebar: halaman admin lebih lebar (dipakai di laptop, SPEC §8). --}}
@php($wadah = $lebar ? 'max-w-5xl' : 'max-w-md')

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ $judul ? $judul.' — ' : '' }}Absensi Piket</title>

        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-gray-50 font-sans text-gray-900 antialiased">
        @auth
            <header class="border-b border-gray-200 bg-white">
                <div class="mx-auto flex {{ $wadah }} items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ auth()->user()->nama }}</p>
                        <a href="{{ route('ganti-password') }}" class="text-sm text-blue-700 underline">Ganti password</a>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" data-teks-proses="Keluar…" class="rounded-lg border border-gray-300 px-4 py-2 font-medium hover:bg-gray-100">
                            Keluar
                        </button>
                    </form>
                </div>
            </header>
        @endauth

        <main class="mx-auto {{ $wadah }} px-4 py-8">
            {{-- Pesan sukses dari redirect()->with('status', ...), tampil di semua halaman. --}}
            @if (session('status'))
                <p role="status" class="mb-4 rounded-lg bg-green-50 p-3 text-green-900">{{ session('status') }}</p>
            @endif

            {{ $slot }}
        </main>
    </body>
</html>
