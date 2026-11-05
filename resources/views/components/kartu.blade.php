{{--
    Kartu bersama (SPEC §11 Langkah 5c): permukaan dengan bingkai tipis.
    padding: ganti lewat prop ini, bukan lewat class (dua kelas padding di satu elemen tidak
    bisa diandalkan di Tailwind: yang menang ditentukan urutan CSS, bukan urutan di atribut).
--}}
@props(['padding' => 'p-5 sm:p-6'])

<div {{ $attributes->class(['rounded-2xl border border-garis bg-permukaan shadow-sm', $padding]) }}>
    {{ $slot }}
</div>
