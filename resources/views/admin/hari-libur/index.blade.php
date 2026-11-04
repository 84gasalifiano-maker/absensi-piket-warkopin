{{-- Kelola hari libur (SPEC F8, §8.1, v0.12). Semua teks dari data di-escape lewat {{ }}. --}}
<x-layouts.app judul="Hari Libur" lebar>
    <x-admin.nav />

    <h1 class="mb-4 text-2xl font-bold">Hari Libur</h1>

    {{-- Urutan SPEC §8.1: form tambah dulu, lalu daftar (di HP bertumpuk, di laptop berdampingan). --}}
    <div class="grid gap-8 lg:grid-cols-[1fr_2fr]">
        <section aria-labelledby="judul-tambah" class="rounded-lg border border-gray-200 bg-white p-4 lg:self-start">
            <h2 id="judul-tambah" class="mb-4 text-lg font-semibold">Tambah hari libur</h2>
            <form method="POST" action="{{ route('admin.hari-libur.store') }}" class="space-y-4">
                @csrf
                <x-field name="tanggal" label="Tanggal" type="date" min="2000-01-01" max="2100-12-31" required />
                <x-field name="keterangan" label="Keterangan" maxlength="255" required />
                <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-700">
                    Tambah
                </button>
            </form>
        </section>

        <section aria-labelledby="judul-daftar">
            <h2 id="judul-daftar" class="sr-only">Daftar hari libur</h2>

            @if ($daftarLibur->isEmpty())
                <p class="rounded-lg bg-white p-4 text-gray-700">Belum ada hari libur.</p>
            @else
                <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
                    <table class="w-full text-left">
                        <caption class="sr-only">Daftar hari libur</caption>
                        <thead class="bg-gray-100 text-sm">
                            <tr>
                                <th scope="col" class="px-4 py-3">Tanggal</th>
                                <th scope="col" class="px-4 py-3">Keterangan</th>
                                <th scope="col" class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @foreach ($daftarLibur as $libur)
                                <tr>
                                    <td class="px-4 py-3 font-medium whitespace-nowrap">{{ $libur->labelTanggal() }}</td>
                                    <td class="px-4 py-3">{{ $libur->adaKeterangan() ? $libur->keterangan : '—' }}</td>
                                    <td class="px-4 py-3">
                                        <form method="POST" action="{{ route('admin.hari-libur.destroy', $libur) }}"
                                              data-konfirmasi="{{ $libur->pesanKonfirmasiHapus() }}" class="flex justify-end">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" data-teks-proses="Menghapus…" class="rounded-lg border border-red-300 px-3 py-2 text-red-700 hover:bg-red-50">
                                                Hapus<span class="sr-only"> hari libur {{ $libur->labelTanggal() }}</span>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-layouts.app>
