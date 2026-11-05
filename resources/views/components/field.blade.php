{{--
    Label + input + pesan error. Ukuran teks 16px supaya HP tidak auto-zoom.
    Untuk type="password", otomatis ada tombol tampilkan/sembunyikan (SPEC F1).
    Perilaku tombol ada di resources/js/app.js.
--}}
@props(['name', 'label', 'type' => 'text', 'value' => null, 'bantuan' => null])

@php($password = $type === 'password')
{{-- Setelah validasi gagal, pakai isian terakhir (juga kalau dikosongkan), bukan nilai lama. --}}
@php($isian = session()->hasOldInput() ? old($name) : $value)
{{-- Isian lama berbentuk array (kiriman tidak wajar) dianggap kosong, bukan error. --}}
@php($isian = is_scalar($isian) ? $isian : null)

<div>
    <label for="{{ $name }}" class="mb-1 block font-medium text-teks">{{ $label }}</label>
    <div class="relative">
        <input
            id="{{ $name }}"
            name="{{ $name }}"
            type="{{ $type }}"
            @unless ($password) value="{{ $isian }}" @endunless
            {{ $attributes->class([
                'block min-h-11 w-full rounded-xl border bg-permukaan px-4 py-3 text-base text-teks placeholder:text-teks-lembut',
                // Bingkai kolom yang salah ikut warna error (tebal sama, jadi layout tidak bergeser).
                'border-bahaya focus:border-bahaya' => $errors->has($name),
                'border-garis-isian focus:border-utama' => ! $errors->has($name),
                'pr-14' => $password,
            ]) }}
            @error($name) aria-invalid="true" @enderror
            @if ($bantuan || $errors->has($name))
                aria-describedby="{{ trim(($bantuan ? $name.'-bantuan ' : '').($errors->has($name) ? $name.'-error' : '')) }}"
            @endif
        >

        @if ($password)
            {{-- type="button" supaya tidak ikut mengirim form. Default: password tersembunyi. --}}
            <button
                type="button"
                data-toggle-password
                aria-controls="{{ $name }}"
                aria-label="Tampilkan password"
                class="absolute top-1/2 right-1 flex size-11 -translate-y-1/2 items-center justify-center rounded-lg text-teks-lembut hover:bg-permukaan-lembut hover:text-teks"
            >
                {{-- Ikon mata: tampil saat password tersembunyi. --}}
                <svg data-ikon="tampilkan" class="size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                </svg>
                {{-- Ikon mata dicoret: tampil saat password terlihat. --}}
                <svg data-ikon="sembunyikan" class="hidden size-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                </svg>
            </button>
        @endif
    </div>
    @if ($bantuan)
        <p id="{{ $name }}-bantuan" class="mt-1 text-sm text-teks-lembut">{{ $bantuan }}</p>
    @endif
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm font-medium text-bahaya">{{ $message }}</p>
    @enderror
</div>
