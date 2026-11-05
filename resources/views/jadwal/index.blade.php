{{-- Kelola jadwal piket satu kelas (SPEC F2, §8.1, v0.15). Dipakai PJ di HP: mobile-first. --}}
@php
    $daftarHari = \App\Support\RingkasanJadwal::HARI;
    $daftarTugas = \App\Enums\Tugas::cases();
    $maksHari = \App\Support\RingkasanJadwal::MAKS_PER_HARI;
    $maksTugas = \App\Support\RingkasanJadwal::MAKS_PER_TUGAS;
@endphp

<x-layouts.app judul="Jadwal piket {{ $kelas->nama }}">
    @if (auth()->user()->role === \App\Enums\Role::Admin)
        <x-admin.nav />
    @endif

    {{-- 1. Nama kelas + tanggal mulai rotasi (hanya-baca; diubah admin di /admin/kelas). --}}
    <h1 class="text-2xl font-bold">Jadwal piket {{ $kelas->nama }}</h1>
    <p class="mt-1 text-gray-700">Tanggal mulai rotasi: {{ $labelTanggalMulai }}</p>
    @if ($bisaPilihKelasLain)
        <a href="{{ route('jadwal') }}" class="mt-1 inline-block text-blue-700 underline">Pilih kelas lain</a>
    @endif

    {{-- 2. --}}
    <p class="mt-3 rounded-lg bg-blue-50 p-3 text-blue-900">Perubahan langsung berlaku, termasuk minggu ini.</p>

    {{-- Error validasi: di atas daftar (SPEC §8.1). Pesan per siswa diberi nama siswanya. --}}
    @if ($errors->any())
        <div role="alert" class="mt-4 rounded-lg bg-red-50 p-3 text-red-800">
            <ul class="list-inside list-disc space-y-1">
                @foreach (['minggu_acuan', 'jadwal'] as $kolom)
                    @foreach ($errors->get($kolom) as $pesan)
                        <li>{{ $pesan }}</li>
                    @endforeach
                @endforeach
                @foreach ($anggota as $siswa)
                    @foreach ([...$errors->get("jadwal.{$siswa->id}.hari"), ...$errors->get("jadwal.{$siswa->id}.tugas")] as $pesan)
                        <li>{{ $siswa->nama }}: {{ $pesan }}</li>
                    @endforeach
                @endforeach
            </ul>
        </div>
    @endif

    {{-- 3. Ringkasan Senin–Jumat: x/9 per hari, x/3 per tugas. Yang melebihi batas disorot. --}}
    <section aria-labelledby="judul-ringkasan" class="mt-6">
        <h2 id="judul-ringkasan" class="mb-2 text-lg font-semibold">{{ $judulRingkasan }}</h2>
        <ul class="divide-y divide-gray-200 rounded-lg border border-gray-200 bg-white">
            @foreach ($daftarHari as $hari => $namaHari)
                <li class="px-3 py-2">
                    <p class="flex justify-between font-medium">
                        <span>{{ $namaHari }}</span>
                        <span data-ringkasan-hari="{{ $hari }}" @class(['rounded px-1', 'bg-red-100 font-bold text-red-800' => $ringkasan->hariMelebihi($hari)])>{{ $ringkasan->jumlahHari($hari) }}/{{ $maksHari }}</span>
                    </p>
                    <p class="text-sm text-gray-700">
                        @foreach ($daftarTugas as $tugas)
                            <span class="mr-2 inline-block">{{ $tugas->label() }}
                                <span data-ringkasan-tugas="{{ $hari }}-{{ $tugas->value }}" @class(['rounded px-1', 'bg-red-100 font-bold text-red-800' => $ringkasan->tugasMelebihi($hari, $tugas)])>{{ $ringkasan->jumlahTugas($hari, $tugas) }}/{{ $maksTugas }}</span></span>
                        @endforeach
                    </p>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- 4. Daftar siswa urut no. absen, 5. Belum terjadwal, 6. tombol Simpan menempel di bawah. --}}
    <section aria-labelledby="judul-siswa" class="mt-6">
        <h2 id="judul-siswa" class="mb-2 text-lg font-semibold">Siswa</h2>

        @if ($anggota->isEmpty())
            <p class="rounded-lg bg-white p-4 text-gray-700">Kelas ini belum punya siswa.</p>
        @else
            @unless ($adaJadwal)
                <p class="mb-3 rounded-lg bg-white p-3 text-gray-700">Pilih hari dan tugas untuk setiap siswa, lalu tekan Simpan.</p>
            @endunless

            <form method="POST" action="{{ route('jadwal.store') }}">
                @csrf
                <input type="hidden" name="kelas" value="{{ $kelas->id }}">
                <input type="hidden" name="minggu_acuan" value="{{ $mingguAcuan->toDateString() }}">

                {{-- Judul kolom sekali saja (label tiap dropdown ada untuk pembaca layar). --}}
                <div class="grid grid-cols-2 gap-2 px-3 pb-1 text-sm font-medium text-gray-700" aria-hidden="true">
                    <span>Hari</span>
                    <span>{{ $labelTugas }}</span>
                </div>

                <ul class="space-y-2">
                    @foreach ($anggota as $siswa)
                        @php
                            $id = $siswa->id;
                            $salahHari = $errors->has("jadwal.{$id}.hari");
                            $salahTugas = $errors->has("jadwal.{$id}.tugas");
                        @endphp
                        <li class="rounded-lg border border-gray-200 bg-white p-3">
                            <p class="mb-2 font-medium">{{ $siswa->no_absen }}. {{ $siswa->nama }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label for="hari-{{ $id }}" class="sr-only">Hari {{ $siswa->nama }}</label>
                                    <select id="hari-{{ $id }}" name="jadwal[{{ $id }}][hari]"
                                            @if ($salahHari) aria-invalid="true" @endif
                                            @class(['block w-full rounded-lg border bg-white px-2 py-3 text-base', 'border-red-600' => $salahHari, 'border-gray-300' => ! $salahHari])>
                                        <option value="">— Belum terjadwal —</option>
                                        @foreach ($daftarHari as $hari => $namaHari)
                                            <option value="{{ $hari }}" @selected($nilai[$id]['hari'] === $hari)>{{ $namaHari }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label for="tugas-{{ $id }}" class="sr-only">{{ $labelTugas }} {{ $siswa->nama }}</label>
                                    <select id="tugas-{{ $id }}" name="jadwal[{{ $id }}][tugas]"
                                            @if ($salahTugas) aria-invalid="true" @endif
                                            @class(['block w-full rounded-lg border bg-white px-2 py-3 text-base', 'border-red-600' => $salahTugas, 'border-gray-300' => ! $salahTugas])>
                                        <option value="">—</option>
                                        @foreach ($daftarTugas as $tugas)
                                            <option value="{{ $tugas->value }}" @selected($nilai[$id]['tugas'] === $tugas)>{{ $tugas->label() }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>

                {{-- 5. Belum terjadwal (dari data tersimpan). Di dalam form, supaya tombol Simpan
                     tetap menempel di bawah layar saat daftar ini digulir. --}}
                @if ($belumTerjadwal->isNotEmpty())
                    <section aria-labelledby="judul-belum" class="mt-6">
                        <h2 id="judul-belum" class="mb-2 text-lg font-semibold">Belum terjadwal</h2>
                        <ul class="list-inside list-disc rounded-lg bg-white p-3 text-gray-800" data-belum-terjadwal>
                            @foreach ($belumTerjadwal as $siswa)
                                <li>{{ $siswa->no_absen }}. {{ $siswa->nama }}</li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                {{-- 6. --}}
                <div class="sticky bottom-0 -mx-4 mt-4 border-t border-gray-200 bg-gray-50 px-4 py-3">
                    <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-4 text-lg font-semibold text-white hover:bg-blue-700">
                        Simpan
                    </button>
                </div>
            </form>
        @endif
    </section>

</x-layouts.app>
