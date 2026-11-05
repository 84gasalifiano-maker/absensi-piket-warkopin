{{-- Pilih kelas untuk kelola jadwal: guru > 1 kelas atau admin (SPEC F2, §8.1, v0.15). --}}
<x-layouts.app judul="Jadwal piket">
    @if (auth()->user()->role === \App\Enums\Role::Admin)
        <x-admin.nav />
    @endif

    <h1 class="mb-4 text-2xl font-bold">Jadwal piket</h1>

    @if ($daftarKelas->isEmpty())
        <p class="rounded-lg bg-white p-4 text-gray-700">{{ $guru ? 'Kamu belum menjadi wali kelas mana pun.' : 'Belum ada kelas.' }}</p>
    @else
        <ul class="space-y-2">
            @foreach ($daftarKelas as $kelas)
                <li>
                    <a href="{{ route('jadwal', ['kelas' => $kelas->id]) }}"
                       class="block rounded-lg border border-gray-200 bg-white px-4 py-3 font-medium text-blue-700 hover:bg-gray-100">{{ $kelas->nama }}</a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
