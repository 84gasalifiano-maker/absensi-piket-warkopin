{{--
    Pesan bersama (SPEC §11 Langkah 5c). jenis: sukses | error | peringatan | info.
    Error diumumkan langsung (role="alert"). Sukses/info/peringatan diumumkan sopan (role="status"):
    peringatan bisa tampil di setiap muat halaman (mis. peringatan F7), jadi tidak dibacakan berulang-ulang.
    Garis kiri tebal membuat jenis pesan terlihat tanpa hanya mengandalkan warna latar.
--}}
@props(['jenis' => 'info'])

@php
    [$role, $warna] = match ($jenis) {
        'sukses' => ['status', 'border-sukses bg-sukses-lembut'],
        'error' => ['alert', 'border-bahaya bg-bahaya-lembut'],
        'peringatan' => ['status', 'border-peringatan bg-peringatan-lembut'],
        default => ['status', 'border-info bg-info-lembut'],
    };
@endphp

<div role="{{ $role }}" {{ $attributes->class(['rounded-xl border-l-4 p-3 text-teks', $warna]) }}>
    {{ $slot }}
</div>
