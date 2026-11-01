@php($siswa = in_array($akun->role, \App\Enums\Role::loginPakaiNis(), true))

<x-layouts.app judul="Ubah akun" lebar>
    <x-admin.nav />

    <p class="mb-2"><a href="{{ route('admin.akun.index') }}" class="text-blue-700 underline">← Kembali ke daftar akun</a></p>
    <h1 class="mb-4 text-2xl font-bold">Ubah akun {{ $akun->nama }}</h1>

    <x-admin.password-sekali />

    <div class="grid gap-6 lg:grid-cols-[3fr_2fr]">
        <form method="POST" action="{{ route('admin.akun.update', $akun) }}" class="space-y-4 rounded-lg border border-gray-200 bg-white p-4">
            @csrf
            @method('PUT')

            <x-field name="nama" label="Nama" :value="$akun->nama" maxlength="100" required />

            @if ($siswa)
                {{-- Role hanya bisa diubah siswa ↔ PJ (SPEC F9). --}}
                <x-select name="role" label="Role" :value="$akun->role->value" :options="\App\Enums\Role::opsi(\App\Enums\Role::loginPakaiNis())" />

                @include('admin.akun._isian-siswa')

                @if ($punyaJadwal)
                    <div class="rounded-lg border border-amber-300 bg-amber-50 p-3">
                        <p id="konfirmasi-pindah-info" class="text-amber-900">
                            Siswa ini punya jadwal piket di kelas {{ $akun->kelas->nama }}.
                            Jadwal piket siswa ini di kelas lama akan dihapus kalau kelasnya diganti.
                        </p>
                        <label class="mt-2 flex items-center gap-3">
                            <input type="checkbox" name="konfirmasi_pindah_kelas" value="1" class="size-5" @checked(old('konfirmasi_pindah_kelas'))
                                   aria-describedby="konfirmasi-pindah-info @error('konfirmasi_pindah_kelas') konfirmasi_pindah_kelas-error @enderror"
                                   @error('konfirmasi_pindah_kelas') aria-invalid="true" @enderror>
                            <span>Ya, hapus jadwal lama</span>
                        </label>
                        @error('konfirmasi_pindah_kelas')
                            <p id="konfirmasi_pindah_kelas-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
                        @enderror
                    </div>
                @endif
            @else
                <div>
                    <p class="mb-1 font-medium">Role</p>
                    <p>{{ $akun->role->label() }} <span class="text-sm text-gray-600">(tidak bisa diubah)</span></p>
                    @error('role')
                        <p class="mt-1 text-sm text-red-700">{{ $message }}</p>
                    @enderror
                </div>
                <x-field name="email" label="Email" type="email" :value="$akun->email" maxlength="255" required />
            @endif

            <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-700">
                Simpan
            </button>
        </form>

        <section aria-labelledby="judul-reset" class="h-fit rounded-lg border border-gray-200 bg-white p-4">
            <h2 id="judul-reset" class="mb-2 text-lg font-semibold">Reset password</h2>
            @if ($akunSendiri)
                <p class="text-gray-700">
                    Ini akunmu sendiri. Ganti password lewat halaman
                    <a href="{{ route('ganti-password') }}" class="text-blue-700 underline">Ganti password</a>.
                </p>
            @else
                <p class="mb-3 text-gray-700">
                    Password baru dibuat otomatis dan ditampilkan sekali. {{ $akun->nama }} akan keluar dari semua perangkat
                    dan wajib mengganti password saat login berikutnya.
                </p>
                <form method="POST" action="{{ route('admin.akun.reset-password', $akun) }}"
                      data-konfirmasi="Reset password {{ $akun->nama }}?">
                    @csrf
                    <button type="submit" data-teks-proses="Mereset…" class="w-full rounded-lg border border-red-300 px-4 py-3 font-semibold text-red-700 hover:bg-red-50">
                        Reset password
                    </button>
                </form>
            @endif
        </section>

        <section aria-labelledby="judul-hapus" class="h-fit rounded-lg border border-gray-200 bg-white p-4 lg:col-start-2">
            <h2 id="judul-hapus" class="mb-2 text-lg font-semibold">Hapus akun</h2>
            {{-- Pesan penolakan dari server cukup tampil sekali: kalau sama dengan alasan di bawah, alasan itu yang disorot. --}}
            @php($pesanHapus = $errors->first('hapus'))
            @if ($pesanHapus !== '' && $pesanHapus !== $alasanTidakBisaDihapus)
                <p role="alert" class="mb-3 rounded-lg bg-red-50 p-3 text-red-800">{{ $pesanHapus }}</p>
            @endif
            @if ($alasanTidakBisaDihapus)
                {{-- Tombol hapus tidak tampil; server juga menolak (SPEC F9). --}}
                @if ($pesanHapus === $alasanTidakBisaDihapus)
                    <p role="alert" class="rounded-lg bg-red-50 p-3 text-red-800">{{ $alasanTidakBisaDihapus }}</p>
                @else
                    <p class="text-gray-700">{{ $alasanTidakBisaDihapus }}</p>
                @endif
            @else
                <p class="mb-3 text-gray-700">Jadwal piket akun ini ikut terhapus. Tindakan ini tidak bisa dibatalkan.</p>
                <form method="POST" action="{{ route('admin.akun.destroy', $akun) }}"
                      data-konfirmasi="Hapus akun {{ $akun->nama }}? Tindakan ini tidak bisa dibatalkan.">
                    @csrf
                    @method('DELETE')
                    <button type="submit" data-teks-proses="Menghapus…" class="w-full rounded-lg border border-red-400 bg-red-50 px-4 py-3 font-semibold text-red-800 hover:bg-red-100">
                        Hapus akun
                    </button>
                </form>
            @endif
        </section>
    </div>
</x-layouts.app>
