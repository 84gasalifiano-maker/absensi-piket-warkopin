<x-layouts.app judul="Admin" lebar>
    <x-admin.nav />

    <h1 class="mb-4 text-2xl font-bold">Ringkasan kelas</h1>

    @if ($daftarKelas->isEmpty())
        <p class="rounded-lg bg-white p-4 text-gray-700">
            Belum ada kelas. <a href="{{ route('admin.kelas.index') }}" class="text-blue-700 underline">Tambah kelas</a>.
        </p>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="w-full text-left">
                <caption class="sr-only">Ringkasan semua kelas</caption>
                <thead class="bg-gray-100 text-sm">
                    <tr>
                        <th scope="col" class="px-4 py-3">Kelas</th>
                        <th scope="col" class="px-4 py-3">Wali kelas</th>
                        <th scope="col" class="px-4 py-3">PJ kebersihan</th>
                        <th scope="col" class="px-4 py-3 text-right">Jumlah siswa</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($daftarKelas as $kelas)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $kelas->nama }}</td>
                            <td class="px-4 py-3">{{ $kelas->waliKelas?->nama ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $kelas->pjKebersihan->pluck('nama')->join(', ') ?: '—' }}</td>
                            <td class="px-4 py-3 text-right">{{ $kelas->anggota_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-layouts.app>
