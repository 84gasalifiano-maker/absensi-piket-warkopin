{{-- Isian kelas, dipakai form tambah & ubah. $kelas null saat tambah. --}}
@php($kelas ??= null)
@php($tanggalTerkunci ??= false)

<x-field name="nama" label="Nama kelas" :value="$kelas?->nama" placeholder="mis. XI RPL 1" maxlength="50" required />

<x-select
    name="wali_kelas_id"
    label="Wali kelas"
    :options="$daftarGuru->pluck('nama', 'id')->all()"
    :value="$kelas?->wali_kelas_id"
    kosong="— Belum ada wali kelas —"
/>

@if ($tanggalTerkunci)
    <div>
        <label for="tanggal_mulai_rotasi" class="mb-1 block font-medium">Tanggal mulai rotasi</label>
        {{-- Nonaktif: tidak ikut terkirim; server juga menolak perubahan (SPEC F2). --}}
        <input id="tanggal_mulai_rotasi" type="date" value="{{ $kelas->tanggal_mulai_rotasi->toDateString() }}" disabled
               aria-describedby="tanggal_mulai_rotasi-kunci @error('tanggal_mulai_rotasi') tanggal_mulai_rotasi-error @enderror"
               class="block w-full rounded-lg border border-gray-300 bg-gray-100 px-4 py-3 text-base text-gray-600">
        <p id="tanggal_mulai_rotasi-kunci" class="mt-1 text-sm text-gray-700">
            {{ \App\Http\Requests\Admin\SimpanKelasRequest::PESAN_TERKUNCI }}
        </p>
        @error('tanggal_mulai_rotasi')
            <p id="tanggal_mulai_rotasi-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
        @enderror
    </div>
@else
    <x-field name="tanggal_mulai_rotasi" label="Tanggal mulai rotasi (harus hari Senin)" type="date"
             :value="$kelas?->tanggal_mulai_rotasi?->toDateString()" required
             bantuan="Rotasi tugas piket dihitung mulai minggu dari tanggal ini. Tidak bisa diubah lagi setelah kelas punya riwayat piket." />
@endif
