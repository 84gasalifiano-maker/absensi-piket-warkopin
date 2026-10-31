<x-layouts.app judul="Masuk">
    <h1 class="mb-1 text-2xl font-bold">Absensi Piket</h1>
    <p class="mb-6 text-gray-600">Masuk pakai NIS (siswa) atau email (guru/admin).</p>

    <form method="POST" action="{{ route('login') }}" class="space-y-4">
        @csrf

        <x-field name="login" label="NIS atau email" autocomplete="username" autocapitalize="none" required autofocus />
        <x-field name="password" label="Password" type="password" autocomplete="current-password" required />

        {{-- Default tidak dicentang (SPEC F1). --}}
        <label class="flex items-center gap-3 py-1">
            <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-5 rounded border-gray-300">
            <span>Ingat saya</span>
        </label>

        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-3 text-base font-semibold text-white hover:bg-blue-700">
            Masuk
        </button>
    </form>
</x-layouts.app>
