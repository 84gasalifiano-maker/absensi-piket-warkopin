{{-- Label + dropdown + pesan error. $options: [nilai => label]. $kosong: teks pilihan kosong (null = tanpa pilihan kosong). --}}
@props(['name', 'label', 'options' => [], 'value' => null, 'kosong' => null])

{{-- Setelah validasi gagal, pakai pilihan terakhir (juga kalau dikosongkan), bukan nilai lama. --}}
@php($terpilih = session()->hasOldInput() ? old($name) : $value)
{{-- Isian lama berbentuk array (kiriman tidak wajar) dianggap kosong, bukan error. --}}
@php($terpilih = is_scalar($terpilih) ? (string) $terpilih : '')

<div>
    <label for="{{ $name }}" class="mb-1 block font-medium text-teks">{{ $label }}</label>
    <select
        id="{{ $name }}"
        name="{{ $name }}"
        {{ $attributes->class([
            'block min-h-11 w-full rounded-xl border bg-permukaan px-4 py-3 text-base text-teks',
            // Bingkai dropdown yang salah ikut warna error (tebal sama, jadi layout tidak bergeser).
            'border-bahaya focus:border-bahaya' => $errors->has($name),
            'border-garis-isian focus:border-utama' => ! $errors->has($name),
        ]) }}
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
        <p id="{{ $name }}-error" class="mt-1 text-sm font-medium text-bahaya">{{ $message }}</p>
    @enderror
</div>
