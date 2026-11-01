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
</x-layouts.app>
