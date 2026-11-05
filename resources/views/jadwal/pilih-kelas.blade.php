{{-- Pilih kelas untuk kelola jadwal: guru > 1 kelas atau admin (SPEC F2, §8.1, v0.15).
     Fondasi desain Langkah 5c sesi 2: token & komponen bersama, ikut mode gelap. --}}
<x-layouts.app judul="Jadwal piket" mode-gelap>
    @if (auth()->user()->role === \App\Enums\Role::Admin)
        <x-admin.nav />
    @endif

    <h1 class="mb-4 text-2xl font-semibold tracking-tight">Jadwal piket</h1>

    @if ($daftarKelas->isEmpty())
        <x-kartu><p class="text-teks-lembut">{{ $guru ? 'Kamu belum menjadi wali kelas mana pun.' : 'Belum ada kelas.' }}</p></x-kartu>
    @else
        <ul class="space-y-2">
            @foreach ($daftarKelas as $kelas)
                <li>
                    <a href="{{ route('jadwal', ['kelas' => $kelas->id]) }}"
                       class="flex min-h-11 items-center justify-between gap-3 rounded-2xl border border-garis bg-permukaan px-4 py-3 font-medium text-teks transition-colors hover:bg-permukaan-lembut">{{ $kelas->nama }}<svg class="size-5 shrink-0 text-teks-lembut" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" /></svg></a>
                </li>
            @endforeach
        </ul>
    @endif
</x-layouts.app>
