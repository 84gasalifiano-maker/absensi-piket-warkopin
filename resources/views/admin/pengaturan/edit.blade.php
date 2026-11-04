{{-- Pengaturan jam buka/tutup (SPEC F4, §8.1, v0.13). Jam tampil tanpa detik (06:00). --}}
<x-layouts.app judul="Pengaturan" lebar>
    <x-admin.nav />

    <h1 class="mb-4 text-2xl font-bold">Pengaturan</h1>

    <section aria-labelledby="judul-jam" class="max-w-xl rounded-lg border border-gray-200 bg-white p-4">
        <h2 id="judul-jam" class="sr-only">Jam tepat waktu</h2>
        <div class="mb-4 space-y-1 text-gray-700">
            <p>Jam tepat waktu (WIB), berlaku Senin–Jumat.</p>
            <p>Jam tutup dihitung sampai akhir menit itu (mis. 07:30 berarti sampai 07:30:59).</p>
            <p>Perubahan langsung berlaku, termasuk hari ini. Submit yang sudah masuk tidak berubah.</p>
        </div>

        <form method="POST" action="{{ route('admin.pengaturan.update') }}" class="space-y-6">
            @csrf
            @foreach (['pagi' => 'Pagi', 'siang' => 'Siang'] as $sesi => $judul)
                <fieldset class="grid gap-4 sm:grid-cols-2">
                    <legend class="mb-2 text-lg font-semibold">{{ $judul }}</legend>
                    <x-field :name="'jam_buka_'.$sesi" label="Jam buka" type="time" :value="$sekolah->jamTanpaDetik('jam_buka_'.$sesi)" required />
                    <x-field :name="'jam_tutup_'.$sesi" label="Jam tutup" type="time" :value="$sekolah->jamTanpaDetik('jam_tutup_'.$sesi)" required />
                </fieldset>
            @endforeach

            <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-700">
                Simpan
            </button>
        </form>
    </section>
</x-layouts.app>
