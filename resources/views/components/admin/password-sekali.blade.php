{{-- Password awal / hasil reset: tampil sekali (flash), lalu hilang dari sesi (SPEC F9). --}}
@if ($info = session('password_sekali'))
    <div role="alert" class="mb-6 rounded-lg border-2 border-amber-400 bg-amber-50 p-4">
        <p class="font-semibold text-amber-900">Catat password ini sekarang — tidak akan ditampilkan lagi.</p>
        <dl class="mt-2 grid grid-cols-[auto_1fr] gap-x-4 gap-y-1">
            <dt class="text-gray-700">Nama</dt>
            <dd class="font-medium">{{ $info['nama'] }}</dd>
            <dt class="text-gray-700">NIS / email</dt>
            <dd class="font-mono">{{ $info['login'] }}</dd>
            <dt class="text-gray-700">Password</dt>
            <dd class="font-mono text-xl tracking-wider" data-password-sekali>{{ $info['password'] }}</dd>
        </dl>
        <p class="mt-2 text-sm text-gray-700">User wajib mengganti password ini saat login.</p>
    </div>
@endif
