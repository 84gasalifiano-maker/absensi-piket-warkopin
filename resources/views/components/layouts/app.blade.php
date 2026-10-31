@props(['judul' => null])

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
                <div class="mx-auto flex max-w-md items-center justify-between gap-3 px-4 py-3">
                    <div class="min-w-0">
                        <p class="truncate font-semibold">{{ auth()->user()->nama }}</p>
                        <a href="{{ route('ganti-password') }}" class="text-sm text-blue-700 underline">Ganti password</a>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="rounded-lg border border-gray-300 px-4 py-2 font-medium hover:bg-gray-100">
                            Keluar
                        </button>
                    </form>
                </div>
            </header>
        @endauth

        <main class="mx-auto max-w-md px-4 py-8">
            {{ $slot }}
        </main>
    </body>
</html>
