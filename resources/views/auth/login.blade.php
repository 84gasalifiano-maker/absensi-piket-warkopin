{{-- Login (SPEC F1). Contoh pertama Fondasi desain (Langkah 5c sesi 1): hanya token & komponen bersama. --}}
<x-layouts.app judul="Masuk" mode-gelap>
    <div class="pt-4 sm:pt-10">
        <div class="mb-6 flex items-center gap-3">
            {{-- Ikon sapu sederhana; hiasan saja. --}}
            <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-utama text-pada-utama shadow-sm" aria-hidden="true">
                <svg class="size-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M14.5 3.5 10 12" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M7 12h7l2.5 8.5h-12L7 12Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 16.5v4M12.5 16.5v4" />
                </svg>
            </span>
            <h1 class="text-2xl font-semibold tracking-tight">Absensi Piket</h1>
        </div>

        <x-kartu>
            <p class="mb-5 text-teks-lembut">Masuk pakai NIS (siswa) atau email (guru/admin).</p>

            <form method="POST" action="{{ route('login') }}" class="space-y-4">
                @csrf

                <x-field name="login" label="NIS atau email" autocomplete="username" autocapitalize="none" required autofocus />
                <x-field name="password" label="Password" type="password" autocomplete="current-password" required />

                {{-- Default tidak dicentang (SPEC F1). Seluruh baris bisa diketuk (min. 44 px). --}}
                <label class="flex min-h-11 items-center gap-3">
                    <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="kotak-centang">
                    <span>Ingat saya</span>
                </label>

                <x-tombol data-teks-proses="Memproses…" class="w-full">Masuk</x-tombol>
            </form>
        </x-kartu>
    </div>
</x-layouts.app>
