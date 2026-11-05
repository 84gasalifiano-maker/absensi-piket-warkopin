{{--
    Tombol bersama (SPEC §11 Langkah 5c). varian: utama | sekunder | bahaya.
    Dengan href → tautan berbentuk tombol; tanpa href → <button> (type default "submit").
    Tinggi minimal 44 px (min-h-11) supaya mudah diketuk di HP.
    Pencegah klik ganda di resources/js/app.js tetap bekerja: teruskan data-teks-proses
    (teks saat diproses, default "Menyimpan…") atau data-tetap-nonaktif lewat atribut.
    Tautan (href) tidak bisa dinonaktifkan; untuk tombol nonaktif pakai tanpa href + disabled.
--}}
@props(['varian' => 'utama', 'href' => null, 'type' => 'submit'])

@php
    $kelas = [
        'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-5 py-3 text-base font-semibold transition-colors',
        'disabled:cursor-not-allowed disabled:opacity-60',
        match ($varian) {
            'sekunder' => 'border border-garis-isian bg-permukaan text-teks hover:bg-permukaan-lembut',
            'bahaya' => 'bg-bahaya text-pada-bahaya hover:bg-bahaya-hover',
            default => 'bg-utama text-pada-utama shadow-sm hover:bg-utama-hover',
        },
    ];
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class($kelas) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->class($kelas) }}>{{ $slot }}</button>
@endif
