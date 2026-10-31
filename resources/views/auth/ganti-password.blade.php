<x-layouts.app judul="Ganti password">
    <h1 class="mb-1 text-2xl font-bold">Ganti password</h1>

    @if ($wajib)
        <p class="mb-6 rounded-lg bg-amber-50 p-3 text-amber-900">
            Kamu wajib mengganti password dulu sebelum bisa lanjut.
        </p>
    @else
        <p class="mb-6 text-gray-600">Password baru minimal 8 karakter dan harus berbeda dari password lama.</p>
    @endif

    <form method="POST" action="{{ route('ganti-password') }}" class="space-y-4">
        @csrf

        <x-field name="password_lama" label="Password lama" type="password" autocomplete="current-password" required />
        <x-field name="password_baru" label="Password baru (minimal 8 karakter)" type="password" autocomplete="new-password" minlength="8" required />
        <x-field name="password_baru_confirmation" label="Ulangi password baru" type="password" autocomplete="new-password" minlength="8" required />

        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-3 text-base font-semibold text-white hover:bg-blue-700">
            Simpan password
        </button>
    </form>
</x-layouts.app>
