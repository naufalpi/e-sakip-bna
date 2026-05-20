<?php

namespace App\Http\Controllers\Kinerja\Concerns;

use App\Models\IndikatorOpdProgram;
use App\Models\IndikatorSasaranOpd;
use App\Models\Opd;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\PeriodeTahun;
use App\Models\PerjanjianKinerja;
use App\Models\PerjanjianKinerjaItem;
use App\Models\RencanaAksi;
use App\Models\RencanaAksiItem;
use App\Models\RenstraOpd;
use App\Models\SasaranOpd;
use App\Models\SatuanIndikator;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

trait BuildsKinerjaOptions
{
    private function shouldLimitToUserOpd(User $user): bool
    {
        return $user->hasRole('admin_opd')
            && ! $user->hasAnyRole([
                'super_admin',
                'admin_kabupaten_bagian_organisasi',
                'admin_kabupaten_bapperida',
                'admin_kabupaten_inspektorat',
            ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function opdOptions(User $user): array
    {
        return Opd::query()
            ->where('status', 'active')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->whereKey($user->opd_id ?? 0))
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama', 'singkatan'])
            ->map(fn (Opd $opd) => [
                'id' => $opd->id,
                'label' => $opd->singkatan ? "{$opd->singkatan} - {$opd->nama}" : $opd->nama,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function periodeOptions(): array
    {
        return PeriodeTahun::query()
            ->orderBy('tahun')
            ->get(['id', 'tahun', 'nama'])
            ->map(fn (PeriodeTahun $periode) => [
                'id' => $periode->id,
                'label' => "{$periode->tahun} - {$periode->nama}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function renstraOptions(User $user, ?int $opdId = null): array
    {
        return RenstraOpd::query()
            ->with('opd:id,nama,singkatan')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($opdId, fn (Builder $query) => $query->where('opd_id', $opdId))
            ->orderByDesc('tahun_awal')
            ->get(['id', 'opd_id', 'judul', 'tahun_awal', 'tahun_akhir'])
            ->map(fn (RenstraOpd $renstra) => [
                'id' => $renstra->id,
                'opd_id' => $renstra->opd_id,
                'label' => "{$renstra->tahun_awal}-{$renstra->tahun_akhir} - {$renstra->judul}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function perjanjianKinerjaOptions(User $user, ?int $opdId = null): array
    {
        return PerjanjianKinerja::query()
            ->with('opd:id,nama,singkatan')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($opdId, fn (Builder $query) => $query->where('opd_id', $opdId))
            ->orderByDesc('tahun')
            ->get(['id', 'opd_id', 'tahun', 'judul', 'status'])
            ->map(fn (PerjanjianKinerja $pk) => [
                'id' => $pk->id,
                'opd_id' => $pk->opd_id,
                'label' => "{$pk->tahun} - {$pk->judul}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rencanaAksiOptions(User $user, ?int $opdId = null): array
    {
        return RencanaAksi::query()
            ->with('opd:id,nama,singkatan')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($opdId, fn (Builder $query) => $query->where('opd_id', $opdId))
            ->orderByDesc('tahun')
            ->get(['id', 'opd_id', 'tahun', 'judul', 'status'])
            ->map(fn (RencanaAksi $rencanaAksi) => [
                'id' => $rencanaAksi->id,
                'opd_id' => $rencanaAksi->opd_id,
                'label' => "{$rencanaAksi->tahun} - {$rencanaAksi->judul}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function satuanOptions(): array
    {
        return SatuanIndikator::query()
            ->where('status', 'active')
            ->orderBy('nama')
            ->get(['id', 'nama', 'simbol'])
            ->map(fn (SatuanIndikator $satuan) => [
                'id' => $satuan->id,
                'label' => $satuan->simbol ? "{$satuan->nama} ({$satuan->simbol})" : $satuan->nama,
            ])
            ->all();
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function nodeOptionsForOpd(int $opdId): array
    {
        return [
            'sasaran_opd' => SasaranOpd::query()
                ->whereHas('tujuan.renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->orderBy('urutan')
                ->get(['id', 'kode', 'sasaran'])
                ->map(fn (SasaranOpd $item) => ['id' => $item->id, 'label' => $this->optionLabel($item->kode, $item->sasaran)])
                ->values()
                ->all(),
            'indikator_sasaran_opd' => IndikatorSasaranOpd::query()
                ->whereHas('sasaran.tujuan.renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->orderBy('urutan')
                ->get(['id', 'kode', 'indikator'])
                ->map(fn (IndikatorSasaranOpd $item) => ['id' => $item->id, 'label' => $this->optionLabel($item->kode, $item->indikator)])
                ->values()
                ->all(),
            'opd_program' => OpdProgram::query()
                ->whereHas('renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->orderBy('urutan')
                ->get(['id', 'kode', 'nama'])
                ->map(fn (OpdProgram $item) => ['id' => $item->id, 'label' => $this->optionLabel($item->kode, $item->nama)])
                ->values()
                ->all(),
            'opd_kegiatan' => OpdKegiatan::query()
                ->whereHas('program.renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->orderBy('urutan')
                ->get(['id', 'kode', 'nama'])
                ->map(fn (OpdKegiatan $item) => ['id' => $item->id, 'label' => $this->optionLabel($item->kode, $item->nama)])
                ->values()
                ->all(),
            'opd_sub_kegiatan' => OpdSubKegiatan::query()
                ->whereHas('kegiatan.program.renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->orderBy('urutan')
                ->get(['id', 'kode', 'nama'])
                ->map(fn (OpdSubKegiatan $item) => ['id' => $item->id, 'label' => $this->optionLabel($item->kode, $item->nama)])
                ->values()
                ->all(),
            'indikator_opd_program' => IndikatorOpdProgram::query()
                ->whereHas('program.renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->orderBy('urutan')
                ->get(['id', 'kode', 'indikator'])
                ->map(fn (IndikatorOpdProgram $item) => ['id' => $item->id, 'label' => $this->optionLabel($item->kode, $item->indikator)])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function perjanjianKinerjaItemOptions(int $opdId): array
    {
        return PerjanjianKinerjaItem::query()
            ->whereHas('perjanjianKinerja', fn (Builder $query) => $query->where('opd_id', $opdId))
            ->orderBy('urutan')
            ->get(['id', 'kode', 'indikator'])
            ->map(fn (PerjanjianKinerjaItem $item) => [
                'id' => $item->id,
                'label' => $this->optionLabel($item->kode, $item->indikator),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rencanaAksiItemOptions(int $opdId): array
    {
        return RencanaAksiItem::query()
            ->whereHas('rencanaAksi', fn (Builder $query) => $query->where('opd_id', $opdId))
            ->orderBy('urutan')
            ->get(['id', 'aksi'])
            ->map(fn (RencanaAksiItem $item) => [
                'id' => $item->id,
                'label' => str($item->aksi)->limit(100)->toString(),
            ])
            ->values()
            ->all();
    }

    private function optionLabel(?string $kode, ?string $label): string
    {
        return trim(($kode ? "{$kode} - " : '').str($label ?? '')->limit(100)->toString());
    }
}
