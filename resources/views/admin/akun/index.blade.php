<x-layouts.app judul="Kelola akun" lebar>
    <x-admin.nav />

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Kelola akun</h1>
        <a href="{{ route('admin.akun.create') }}" class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">
            Tambah akun
        </a>
    </div>

    <form method="GET" action="{{ route('admin.akun.index') }}" class="mb-4 flex flex-wrap items-end gap-3 rounded-lg border border-gray-200 bg-white p-4">
        <div class="min-w-48">
            <x-select name="kelas" label="Kelas" :options="$daftarKelas->pluck('nama', 'id')->all()"
                      :value="$filterKelas?->id" kosong="Semua kelas" />
        </div>
        <div class="min-w-48">
            <x-select name="role" label="Role"
                      :options="\App\Enums\Role::opsi()"
                      :value="$filterRole?->value" kosong="Semua role" />
        </div>
        <button type="submit" data-teks-proses="Memuat…" class="rounded-lg border border-gray-300 px-4 py-3 font-medium hover:bg-gray-100">Tampilkan</button>
    </form>

    @if ($daftarAkun->isEmpty())
        <p class="rounded-lg bg-white p-4 text-gray-700">Tidak ada akun yang cocok dengan filter ini.</p>
    @else
        <div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
            <table class="w-full text-left">
                <caption class="sr-only">Daftar akun</caption>
                <thead class="bg-gray-100 text-sm">
                    <tr>
                        <th scope="col" class="px-4 py-3">Nama</th>
                        <th scope="col" class="px-4 py-3">Role</th>
                        <th scope="col" class="px-4 py-3">Kelas</th>
                        <th scope="col" class="px-4 py-3">No. absen</th>
                        <th scope="col" class="px-4 py-3">NIS / email</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">Aksi</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @foreach ($daftarAkun as $akun)
                        <tr>
                            <td class="px-4 py-3 font-medium">{{ $akun->nama }}</td>
                            <td class="px-4 py-3">{{ $akun->role->label() }}</td>
                            <td class="px-4 py-3">{{ $akun->kelas?->nama ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $akun->no_absen ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-sm">{{ $akun->nis ?? $akun->email }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('admin.akun.edit', $akun) }}" class="rounded-lg border border-gray-300 px-3 py-2 hover:bg-gray-100">
                                    Ubah<span class="sr-only"> {{ $akun->nama }}</span>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $daftarAkun->links() }}</div>
    @endif
</x-layouts.app>
