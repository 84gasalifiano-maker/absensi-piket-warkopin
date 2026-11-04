{{--
    Satu pesan error. Pesan "daftar password belum diunduh" (SPEC F9, v0.11) ditampilkan
    dengan "halaman Akun" sebagai tautan ke /admin/akun; pesan lain apa adanya.
--}}
@props(['pesan'])

@if ($pesan === \App\Http\Controllers\Admin\AkunController::PESAN_DAFTAR_BELUM_DIUNDUH)
    Unduh dulu daftar password sebelumnya di <a href="{{ route('admin.akun.index') }}" class="font-semibold underline">halaman Akun</a>.
@else
    {{ $pesan }}
@endif
