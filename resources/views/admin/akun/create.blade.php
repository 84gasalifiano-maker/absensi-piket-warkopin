<x-layouts.app judul="Tambah akun" lebar>
    <x-admin.nav />

    <p class="mb-2"><a href="{{ route('admin.akun.index') }}" class="text-blue-700 underline">← Kembali ke daftar akun</a></p>
    <h1 class="mb-1 text-2xl font-bold">Tambah akun</h1>
    <p class="mb-4 text-gray-600">Password dibuat otomatis dan ditampilkan sekali setelah akun disimpan.</p>

    <form method="POST" action="{{ route('admin.akun.store') }}" class="max-w-xl space-y-6 rounded-lg border border-gray-200 bg-white p-4">
        @csrf

        <div class="space-y-4">
            <x-field name="nama" label="Nama" maxlength="100" required />
            <x-select name="role" label="Role" kosong="— Pilih role —" data-pilih-isian-role
                      :options="\App\Enums\Role::opsi()" />
        </div>

        <fieldset data-isian-role="siswa pj_kebersihan" class="space-y-4 rounded-lg border border-gray-200 p-4">
            <legend class="px-1 font-semibold">Khusus siswa / PJ kebersihan</legend>
            @include('admin.akun._isian-siswa')
        </fieldset>

        <fieldset data-isian-role="guru admin" class="space-y-4 rounded-lg border border-gray-200 p-4">
            <legend class="px-1 font-semibold">Khusus guru / admin</legend>
            <x-field name="email" label="Email" type="email" maxlength="255" autocomplete="off" />
        </fieldset>

        <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-700">
            Simpan akun
        </button>
    </form>
</x-layouts.app>
