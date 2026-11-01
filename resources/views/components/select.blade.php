{{-- Label + dropdown + pesan error. $options: [nilai => label]. $kosong: teks pilihan kosong (null = tanpa pilihan kosong). --}}
@props(['name', 'label', 'options' => [], 'value' => null, 'kosong' => null])

{{-- Setelah validasi gagal, pakai pilihan terakhir (juga kalau dikosongkan), bukan nilai lama. --}}
@php($terpilih = session()->hasOldInput() ? old($name) : $value)
{{-- Isian lama berbentuk array (kiriman tidak wajar) dianggap kosong, bukan error. --}}
@php($terpilih = is_scalar($terpilih) ? (string) $terpilih : '')

<div>
    <label for="{{ $name }}" class="mb-1 block font-medium">{{ $label }}</label>
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->class('block w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-base focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/30') }}
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    >
        @if ($kosong !== null)
            <option value="">{{ $kosong }}</option>
        @endif
        @foreach ($options as $nilai => $teks)
            <option value="{{ $nilai }}" @selected($terpilih === (string) $nilai)>{{ $teks }}</option>
        @endforeach
    </select>
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
