<?php

namespace Tests\Feature\Jadwal;

use App\Enums\Role;
use App\Enums\Tugas;
use App\Models\DutySchedule;
use App\Models\Kelas;
use App\Models\User;
use Carbon\CarbonInterface;
use DOMDocument;
use DOMXPath;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Admin\AdminTestCase;

/**
 * Data dasar tes jadwal (SPEC F2, v0.15): kelas $this->kelas dengan tanggal mulai rotasi
 * Senin 2 Nov 2026 (contoh SPEC §11 Langkah 8), satu PJ, dan "hari ini" Rabu 4 Nov 2026.
 */
abstract class JadwalTestCase extends AdminTestCase
{
    protected const MULAI = '2026-11-02';

    protected User $pj;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-04 10:00:00');
        $this->kelas->update(['tanggal_mulai_rotasi' => self::MULAI]);
        $this->pj = $this->siswa(Role::PjKebersihan);
    }

    protected function jadwalTersimpan(User $siswa, int $hari, Tugas $tugasAwal, ?Kelas $kelas = null): DutySchedule
    {
        return DutySchedule::create([
            'class_id' => ($kelas ?? $this->kelas)->id,
            'user_id' => $siswa->id,
            'hari' => $hari,
            'tugas_awal' => $tugasAwal,
        ]);
    }

    protected function halaman(User $oleh, ?Kelas $kelas = null): TestResponse
    {
        return $this->actingAs($oleh)->get($this->url($kelas));
    }

    /**
     * POST /jadwal seperti dikirim form. $jadwal: [user_id => ['hari' => .., 'tugas' => ..]].
     * Minggu acuan default = Senin minggu "hari ini".
     *
     * @param  array<int|string, mixed>  $jadwal
     */
    protected function simpan(User $oleh, array $jadwal, ?string $mingguAcuan = null, ?Kelas $kelas = null): TestResponse
    {
        $kelas ??= $this->kelas;

        return $this->actingAs($oleh)->from($this->url($kelas))->post(route('jadwal.store'), [
            'kelas' => $kelas->id,
            'minggu_acuan' => $mingguAcuan ?? now()->startOfWeek(CarbonInterface::MONDAY)->toDateString(),
            'jadwal' => $jadwal,
        ]);
    }

    protected function url(?Kelas $kelas = null): string
    {
        return route('jadwal', ['kelas' => ($kelas ?? $this->kelas)->id]);
    }

    /** @return array{hari: int, tugas_awal: Tugas}|null */
    protected function jadwalDi(User $siswa, ?Kelas $kelas = null): ?array
    {
        $jadwal = DutySchedule::where('class_id', ($kelas ?? $this->kelas)->id)->where('user_id', $siswa->id)->first();

        return $jadwal ? ['hari' => $jadwal->hari, 'tugas_awal' => $jadwal->tugas_awal] : null;
    }

    protected function xpath(TestResponse $response): DOMXPath
    {
        $dom = new DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());

        return new DOMXPath($dom);
    }

    /** Nilai option yang terpilih di <select name="$nama">, '' kalau tidak ada yang terpilih. */
    protected function terpilih(TestResponse $response, string $nama): string
    {
        $xpath = $this->xpath($response);
        $this->assertSame(1, $xpath->query("//select[@name='{$nama}']")->length, "Dropdown {$nama} harus ada tepat satu");

        $opsi = $xpath->query("//select[@name='{$nama}']/option[@selected]");

        return $opsi->length ? $opsi->item(0)->getAttribute('value') : '';
    }

    /** @return list<string> */
    protected function nilaiOpsi(TestResponse $response, string $nama): array
    {
        $hasil = [];
        foreach ($this->xpath($response)->query("//select[@name='{$nama}']/option") as $opsi) {
            $hasil[] = $opsi->getAttribute('value');
        }

        return $hasil;
    }

    protected function isiRingkasan(TestResponse $response, string $atribut, string $nilai): string
    {
        $sel = $this->xpath($response)->query("//*[@{$atribut}='{$nilai}']");
        $this->assertSame(1, $sel->length, "Sel ringkasan {$atribut}={$nilai} harus ada tepat satu");

        return trim($sel->item(0)->textContent);
    }

    protected function ringkasanDisorot(TestResponse $response, string $atribut, string $nilai): bool
    {
        $sel = $this->xpath($response)->query("//*[@{$atribut}='{$nilai}']")->item(0);

        return str_contains($sel->getAttribute('class'), 'bg-bahaya-lembut'); // token sorotan (Langkah 5c sesi 2)
    }

    /**
     * Isian form persis seperti yang akan dikirim browser dari halaman ini.
     *
     * @return array{minggu_acuan: string, jadwal: array<int, array{hari: string, tugas: string}>}
     */
    protected function isianForm(TestResponse $response): array
    {
        $xpath = $this->xpath($response);
        $jadwal = [];

        foreach ($xpath->query("//select[starts-with(@name, 'jadwal[')]") as $select) {
            preg_match('/^jadwal\[(\d+)\]\[(hari|tugas)\]$/', $select->getAttribute('name'), $cocok);
            $terpilih = $xpath->query('option[@selected]', $select);
            $jadwal[(int) $cocok[1]][$cocok[2]] = $terpilih->length ? $terpilih->item(0)->getAttribute('value') : '';
        }

        return [
            'minggu_acuan' => $xpath->query("//input[@name='minggu_acuan']")->item(0)->getAttribute('value'),
            'jadwal' => $jadwal,
        ];
    }
}
