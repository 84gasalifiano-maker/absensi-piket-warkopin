{{-- Kelola jadwal piket satu kelas (SPEC F2, §8.1, v0.15). Dipakai PJ di HP: mobile-first.
     Fondasi desain Langkah 5c sesi 2: token & komponen bersama, ikut mode gelap. --}}
@php
    $daftarHari = \App\Support\RingkasanJadwal::HARI;
    $daftarTugas = \App\Enums\Tugas::cases();
    $maksHari = \App\Support\RingkasanJadwal::MAKS_PER_HARI;
    $maksTugas = \App\Support\RingkasanJadwal::MAKS_PER_TUGAS;
    // Dropdown di tiap baris: sama dengan x-select (tinggi min. 44 px, bingkai error), tapi
    // namanya berbentuk array (jadwal[5][hari]), jadi ditulis langsung di sini.
    $kelasDropdown = 'block min-h-11 w-full rounded-xl border bg-permukaan px-2 py-3 text-base text-teks';
    $sorotan = 'bg-bahaya-lembut font-bold text-bahaya';
@endphp

<x-layouts.app judul="Jadwal piket {{ $kelas->nama }}" mode-gelap>
    @if (auth()->user()->role === \App\Enums\Role::Admin)
        <x-admin.nav />
    @endif

    {{-- 1. Nama kelas + tanggal mulai rotasi (hanya-baca; diubah admin di /admin/kelas). --}}
    <h1 class="text-2xl font-semibold tracking-tight">Jadwal piket {{ $kelas->nama }}</h1>
    <p class="mt-1 text-teks-lembut">Tanggal mulai rotasi: {{ $labelTanggalMulai }}</p>
    @if ($bisaPilihKelasLain)
        <a href="{{ route('jadwal') }}" class="inline-flex min-h-11 items-center font-medium text-utama underline underline-offset-2 hover:text-utama-hover">Pilih kelas lain</a>
    @endif

    {{-- 2. --}}
    <x-pesan jenis="info" class="mt-3">Perubahan langsung berlaku, termasuk minggu ini.</x-pesan>

    {{-- Error validasi: di atas daftar (SPEC §8.1). Pesan per siswa diberi nama siswanya. --}}
    @if ($errors->any())
        <x-pesan jenis="error" class="mt-4">
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
        </x-pesan>
    @endif

    {{-- 3. Ringkasan Senin–Jumat: x/9 per hari, x/3 per tugas. Yang melebihi batas disorot. --}}
    <section aria-labelledby="judul-ringkasan" class="mt-6">
        <h2 id="judul-ringkasan" class="mb-2 text-lg font-semibold">{{ $judulRingkasan }}</h2>
        <x-kartu padding="p-0">
            <ul class="divide-y divide-garis">
                @foreach ($daftarHari as $hari => $namaHari)
                    <li class="px-4 py-3">
                        <p class="flex justify-between font-medium">
                            <span>{{ $namaHari }}</span>
                            <span data-ringkasan-hari="{{ $hari }}" @class(['rounded-md px-1.5', $sorotan => $ringkasan->hariMelebihi($hari)])>{{ $ringkasan->jumlahHari($hari) }}/{{ $maksHari }}</span>
                        </p>
                        <p class="mt-0.5 text-sm text-teks-lembut">
                            @foreach ($daftarTugas as $tugas)
                                <span class="mr-2 inline-block">{{ $tugas->label() }}
                                    <span data-ringkasan-tugas="{{ $hari }}-{{ $tugas->value }}" @class(['rounded-md px-1', $sorotan => $ringkasan->tugasMelebihi($hari, $tugas)])>{{ $ringkasan->jumlahTugas($hari, $tugas) }}/{{ $maksTugas }}</span></span>
                            @endforeach
                        </p>
                    </li>
                @endforeach
            </ul>
        </x-kartu>
    </section>

    {{-- 4. Daftar siswa urut no. absen, 5. Belum terjadwal, 6. tombol Simpan menempel di bawah. --}}
    <section aria-labelledby="judul-siswa" class="mt-6">
        <h2 id="judul-siswa" class="mb-2 text-lg font-semibold">Siswa</h2>

        @if ($anggota->isEmpty())
            <x-kartu><p class="text-teks-lembut">Kelas ini belum punya siswa.</p></x-kartu>
        @else
            @unless ($adaJadwal)
                <x-kartu class="mb-3" padding="p-4"><p class="text-teks-lembut">Pilih hari dan tugas untuk setiap siswa, lalu tekan Simpan.</p></x-kartu>
            @endunless

            <form method="POST" action="{{ route('jadwal.store') }}">
                @csrf
                <input type="hidden" name="kelas" value="{{ $kelas->id }}">
                <input type="hidden" name="minggu_acuan" value="{{ $mingguAcuan->toDateString() }}">

                {{-- Judul kolom sekali saja (label tiap dropdown ada untuk pembaca layar). --}}
                <div class="grid grid-cols-2 gap-2 px-3 pb-1 text-sm font-medium text-teks-lembut" aria-hidden="true">
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
                        <li class="rounded-2xl border border-garis bg-permukaan p-3">
                            <p class="mb-2 font-medium"><span class="text-teks-lembut">{{ $siswa->no_absen }}.</span> {{ $siswa->nama }}</p>
                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label for="hari-{{ $id }}" class="sr-only">Hari {{ $siswa->nama }}</label>
                                    <select id="hari-{{ $id }}" name="jadwal[{{ $id }}][hari]"
                                            @if ($salahHari) aria-invalid="true" @endif
                                            @class([$kelasDropdown, 'border-bahaya focus:border-bahaya' => $salahHari, 'border-garis-isian focus:border-utama' => ! $salahHari])>
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
                                            @class([$kelasDropdown, 'border-bahaya focus:border-bahaya' => $salahTugas, 'border-garis-isian focus:border-utama' => ! $salahTugas])>
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
                        <x-kartu padding="p-4">
                            <ul class="list-inside list-disc" data-belum-terjadwal>
                                @foreach ($belumTerjadwal as $siswa)
                                    <li>{{ $siswa->no_absen }}. {{ $siswa->nama }}</li>
                                @endforeach
                            </ul>
                        </x-kartu>
                    </section>
                @endif

                {{-- 6. Bilah Simpan menempel di bawah layar. Ada di akhir alur halaman, jadi saat
                     digulir sampai bawah ia berada setelah baris terakhir, bukan menutupinya.
                     data-bilah-menempel: app.css memberi jarak gulir bawah, supaya dropdown yang
                     sedang diisi digulir ke atas bilah ini, tidak tertutup. --}}
                <div data-bilah-menempel class="sticky bottom-0 -mx-4 mt-6 border-t border-garis bg-dasar px-4 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
                    <x-tombol class="w-full">Simpan</x-tombol>
                </div>
            </form>
        @endif
    </section>
</x-layouts.app>
