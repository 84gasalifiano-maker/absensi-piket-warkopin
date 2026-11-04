<x-layouts.app judul="Ubah kelas" lebar>
    <x-admin.nav />

    <p class="mb-2"><a href="{{ route('admin.kelas.index') }}" class="text-blue-700 underline">← Kembali ke daftar kelas</a></p>
    <h1 class="mb-4 text-2xl font-bold">Ubah kelas {{ $kelas->nama }}</h1>

    <form method="POST" action="{{ route('admin.kelas.update', $kelas) }}" class="max-w-md space-y-4 rounded-lg border border-gray-200 bg-white p-4">
        @csrf
        @method('PUT')
        @include('admin.kelas._form')
        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-700">
            Simpan
        </button>
    </form>

    {{-- Reset massal password (SPEC F9, §8.2, v0.11). --}}
    <section aria-labelledby="judul-reset-massal" class="mt-6 max-w-md space-y-3 rounded-lg border border-gray-200 bg-white p-4">
        <h2 id="judul-reset-massal" class="text-lg font-semibold">Reset password kelas ini</h2>
        <p class="text-gray-700">
            Untuk siswa/PJ yang belum pernah mengganti password, mis. daftar password hilang.
            Akun yang sudah mengganti password tidak ikut.
        </p>

        @error('reset_password')
            <p id="reset-massal-error" role="alert" class="rounded-lg bg-red-50 p-3 text-red-800">
                <x-admin.pesan-error :pesan="$message" />
            </p>
        @enderror

        @if ($jumlahBelumGanti > 0)
            <p class="font-medium">{{ $jumlahBelumGanti }} akun belum mengganti password.</p>
            <form method="POST" action="{{ route('admin.kelas.reset-password', $kelas) }}"
                  data-konfirmasi="{{ \App\Actions\ResetMassalKelas::pesanKonfirmasi($kelas, $jumlahBelumGanti) }}">
                @csrf
                <button type="submit" data-teks-proses="Mereset password… jangan tutup halaman ini."
                        class="w-full rounded-lg bg-red-700 px-4 py-3 font-semibold text-white hover:bg-red-800">
                    Reset password {{ $jumlahBelumGanti }} akun
                </button>
            </form>
        @elseif (! $errors->has('reset_password'))
            {{-- Kosong (n = 0): tombol tidak tampil (F9). Tidak diulang kalau pesan error sudah tampil. --}}
            <p class="text-gray-700">{{ \App\Actions\ResetMassalKelas::pesanKosong($kelas) }}</p>
        @endif
    </section>
</x-layouts.app>
