{{-- Label + input + pesan error. Ukuran teks 16px supaya HP tidak auto-zoom. --}}
@props(['name', 'label', 'type' => 'text'])

<div>
    <label for="{{ $name }}" class="mb-1 block font-medium">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name) }}" @endif
        {{ $attributes->merge(['class' => 'block w-full rounded-lg border border-gray-300 bg-white px-4 py-3 text-base focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/30']) }}
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
    >
    @error($name)
        <p id="{{ $name }}-error" class="mt-1 text-sm text-red-700">{{ $message }}</p>
    @enderror
</div>
