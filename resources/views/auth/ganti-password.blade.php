{{-- Ganti password (SPEC F1, §8.2). Fondasi desain Langkah 5c sesi 2: token & komponen bersama. --}}
<x-layouts.app judul="Ganti password" mode-gelap>
    <h1 class="mb-3 text-2xl font-semibold tracking-tight">Ganti password</h1>

    <x-kartu>
        @if ($wajib)
            <x-pesan jenis="peringatan" class="mb-5">
                Kamu wajib mengganti password dulu sebelum bisa lanjut.
            </x-pesan>
        @else
            <x-pesan jenis="info" class="mb-5">Password baru minimal 8 karakter dan harus berbeda dari password lama.</x-pesan>
        @endif

        <form method="POST" action="{{ route('ganti-password') }}" class="space-y-4">
            @csrf

            {{-- Tidak tampil saat ganti wajib di sesi yang login dengan password (F1, §8.2, v0.11). --}}
            @if ($perluPasswordLama)
                <x-field name="password_lama" label="Password lama" type="password" autocomplete="current-password" required />
            @endif
            <x-field name="password_baru" label="Password baru (minimal 8 karakter)" type="password" autocomplete="new-password" minlength="8" required />
            <x-field name="password_baru_confirmation" label="Ulangi password baru" type="password" autocomplete="new-password" minlength="8" required />

            <x-tombol class="w-full">Simpan password</x-tombol>
        </form>
    </x-kartu>
</x-layouts.app>
