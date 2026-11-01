{{-- Isian khusus siswa/PJ (login pakai NIS). $akun null saat tambah. --}}
@php($akun ??= null)

<x-field name="nis" label="NIS" :value="$akun?->nis" inputmode="numeric" maxlength="30" autocomplete="off" />
<x-select name="class_id" label="Kelas" :options="$daftarKelas->pluck('nama', 'id')->all()"
          :value="$akun?->class_id" kosong="— Pilih kelas —" />
<x-field name="no_absen" label="No. absen" type="number" :value="$akun?->no_absen" min="1" max="255" />
