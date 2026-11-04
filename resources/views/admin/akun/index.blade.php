<x-layouts.app judul="Kelola akun" lebar>
    <x-admin.nav />

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Kelola akun</h1>
        <a href="{{ route('admin.akun.create') }}" class="rounded-lg bg-blue-600 px-4 py-2 font-semibold text-white hover:bg-blue-700">
            Tambah akun
        </a>
    </div>

    @error('unduh')
        <p role="alert" class="mb-4 rounded-lg bg-red-50 p-3 text-red-800">{{ $message }}</p>
    @enderror

    {{-- Daftar password hasil import: hanya di sesi, bisa diunduh sekali (SPEC F9, v0.10). --}}
    @if ($jumlahPasswordImport > 0)
        <section aria-labelledby="judul-unduh" class="mb-6 rounded-lg border-2 border-amber-400 bg-amber-50 p-4">
            <h2 id="judul-unduh" class="font-semibold text-amber-900">Daftar password awal {{ $jumlahPasswordImport }} akun belum diunduh</h2>
            <p class="mt-1 text-gray-800">
                Unduh sekarang dan simpan di tempat aman. Daftar ini hanya bisa diunduh <strong>sekali</strong>,
                lalu tidak bisa dilihat lagi. Kalau terlewat, reset password akun yang bersangkutan.
            </p>
            <form method="POST" action="{{ route('admin.akun.import.password') }}" class="mt-3">
                @csrf
                <button type="submit" data-teks-proses="Sudah diunduh" class="rounded-lg bg-amber-600 px-4 py-3 font-semibold text-white hover:bg-amber-700">
                    Unduh daftar password
                </button>
            </form>
        </section>
    @endif

    <details class="mb-6 rounded-lg border border-gray-200 bg-white p-4" @if ($errors->has('file')) open @endif>
        <summary class="cursor-pointer font-semibold">Import akun siswa/PJ dari CSV</summary>

        <div class="mt-3 space-y-3">
            <p class="text-gray-700">
                Baris pertama berisi judul kolom <code>nama</code>, <code>nis</code>, <code>kelas</code>, <code>no_absen</code>,
                dan boleh <code>role</code>. Maks {{ \App\Actions\ImportAkun::MAKS_BARIS }} akun per file (mis. satu file per kelas), ukuran maks 1 MB.
            </p>
            <pre class="overflow-x-auto rounded bg-gray-100 p-3 text-sm">nama,nis,kelas,no_absen,role
Budi Santoso,12345,XI RPL 1,1,
Siti Aminah,12346,XI RPL 1,2,pj_kebersihan</pre>
            <ul class="list-disc space-y-1 pl-5 text-sm text-gray-700">
                <li><code>kelas</code> ditulis sama seperti nama kelas di halaman Kelas.</li>
                <li><code>role</code>: <code>siswa</code> atau <code>pj_kebersihan</code>; kosong = siswa. Guru dan admin ditambah lewat tombol "Tambah akun".</li>
                <li>Di Excel: format kolom NIS sebagai Teks (supaya angka 0 di depan tidak hilang), lalu Simpan Sebagai "CSV UTF-8".</li>
                <li>Kalau ada satu saja baris yang salah, tidak ada akun yang dibuat.</li>
            </ul>

            @if ($errors->has('file'))
                <div id="file-import-error" role="alert" class="rounded-lg bg-red-50 p-3 text-red-800">
                    <p class="font-semibold">Tidak ada akun yang dibuat. Perbaiki file lalu upload ulang:</p>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($errors->get('file') as $pesan)
                            <li><x-admin.pesan-error :pesan="$pesan" /></li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($jumlahPasswordImport > 0)
                <p class="font-medium text-amber-900">Unduh dulu daftar password di atas sebelum import baru.</p>
            @else
                <form method="POST" action="{{ route('admin.akun.import') }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <div>
                        <label for="file-import" class="mb-1 block font-medium">File CSV</label>
                        <input id="file-import" type="file" name="file" accept=".csv,text/csv" required
                               class="block w-full rounded-lg border border-gray-300 p-2"
                               @error('file') aria-invalid="true" aria-describedby="file-import-error" @enderror>
                    </div>
                    <button type="submit" data-teks-proses="Mengimpor…" class="rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-700">
                        Import
                    </button>
                    <p class="text-sm text-gray-600">Proses bisa memakan waktu sampai 30 detik.</p>
                </form>
            @endif
        </div>
    </details>

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
