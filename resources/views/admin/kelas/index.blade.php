<x-layouts.app judul="Kelola kelas" lebar>
    <x-admin.nav />

    <h1 class="mb-4 text-2xl font-bold">Kelola kelas</h1>

    @error('hapus')
        <p role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-red-800">{{ $message }}</p>
    @enderror

    <div class="grid gap-8 lg:grid-cols-[2fr_1fr]">
        <section aria-labelledby="judul-daftar">
            <h2 id="judul-daftar" class="sr-only">Daftar kelas</h2>

            @if ($daftarKelas->isEmpty())
                <p class="rounded-lg bg-white p-4 text-gray-700">Belum ada kelas. Tambahkan lewat form di samping.</p>
            @else
                <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                    <table class="w-full text-left">
                        <caption class="sr-only">Daftar kelas</caption>
                        <thead class="bg-gray-100 text-sm">
                            <tr>
                                <th scope="col" class="px-4 py-3">Kelas</th>
                                <th scope="col" class="px-4 py-3">Wali kelas</th>
                                <th scope="col" class="px-4 py-3">Mulai rotasi</th>
                                <th scope="col" class="px-4 py-3 text-right">Siswa</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($daftarKelas as $kelas)
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $kelas->nama }}</td>
                                    <td class="px-4 py-3">{{ $kelas->waliKelas?->nama ?? '—' }}</td>
                                    <td class="px-4 py-3">{{ $kelas->tanggal_mulai_rotasi->translatedFormat('j M Y') }}</td>
                                    <td class="px-4 py-3 text-right">{{ $kelas->anggota_count }}</td>
                                    <td class="px-4 py-3">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('jadwal', ['kelas' => $kelas->id]) }}"
                                               class="rounded-lg border border-gray-300 px-3 py-2 hover:bg-gray-100">Jadwal<span class="sr-only"> kelas {{ $kelas->nama }}</span></a>
                                            <a href="{{ route('admin.kelas.edit', $kelas) }}"
                                               class="rounded-lg border border-gray-300 px-3 py-2 hover:bg-gray-100">Ubah</a>
                                            <form method="POST" action="{{ route('admin.kelas.destroy', $kelas) }}"
                                                  data-konfirmasi="Hapus kelas {{ $kelas->nama }}?">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" data-teks-proses="Menghapus…" class="rounded-lg border border-red-300 px-3 py-2 text-red-700 hover:bg-red-50">
                                                    Hapus<span class="sr-only"> kelas {{ $kelas->nama }}</span>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-sm text-gray-600">Kelas hanya bisa dihapus kalau belum punya siswa, jadwal, atau riwayat piket.</p>
            @endif
        </section>

        <section aria-labelledby="judul-tambah" class="rounded-lg border border-gray-200 bg-white p-4">
            <h2 id="judul-tambah" class="mb-4 text-lg font-semibold">Tambah kelas</h2>
            <form method="POST" action="{{ route('admin.kelas.store') }}" class="space-y-4">
                @csrf
                @include('admin.kelas._form')
                <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-700">
                    Tambah kelas
                </button>
            </form>
        </section>
    </div>
</x-layouts.app>
