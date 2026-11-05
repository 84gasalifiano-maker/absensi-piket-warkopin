{{-- Kartu bersama (SPEC §11 Langkah 5c): permukaan dengan bingkai tipis. --}}
<div {{ $attributes->class('rounded-2xl border border-garis bg-permukaan p-5 shadow-sm sm:p-6') }}>
    {{ $slot }}
</div>
