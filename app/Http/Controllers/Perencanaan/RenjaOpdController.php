<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Perencanaan\StoreRenjaOpdRequest;
use App\Http\Requests\Perencanaan\UpdateRenjaOpdRequest;
use App\Models\Opd;
use App\Models\OpdUnit;
use App\Models\PeriodeTahun;
use App\Models\RenjaOpd;
use App\Models\RenjaOpdItem;
use App\Models\RenstraOpd;
use App\Models\Rkpd;
use App\Models\SubKegiatanPemerintahan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RenjaOpdController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RenjaOpd::class);

        $filters = $request->only(['search', 'status', 'opd_id', 'periode_tahun_id', 'tahun']);
        $user = $request->user();

        $items = RenjaOpd::query()
            ->with(['opd:id,kode,nama,singkatan', 'opdUnit:id,kode,nama', 'rkpd:id,judul,tahun,status', 'periodeTahun:id,tahun,nama'])
            ->withCount('items')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($user->hasOpdUnitScope(), fn (Builder $query) => $query->where('opd_unit_id', $user->opd_unit_id))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('judul', 'ilike', "%{$search}%")
                        ->orWhere('nomor_dokumen', 'ilike', "%{$search}%")
                        ->orWhereHas('opd', fn (Builder $query) => $query->where('nama', 'ilike', "%{$search}%")->orWhere('singkatan', 'ilike', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['opd_id'] ?? null, fn (Builder $query, string $opdId) => $query->where('opd_id', $opdId))
            ->when($filters['periode_tahun_id'] ?? null, fn (Builder $query, string $periodeId) => $query->where('periode_tahun_id', $periodeId))
            ->when($filters['tahun'] ?? null, fn (Builder $query, string $tahun) => $query->where('tahun', $tahun))
            ->orderByDesc('tahun')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (RenjaOpd $renja) => [
                'id' => $renja->id,
                'judul' => $renja->judul,
                'nomor_dokumen' => $renja->nomor_dokumen,
                'tahun' => $renja->tahun,
                'status' => $renja->status,
                'items_count' => $renja->items_count,
                'opd' => $renja->opd ? [
                    'id' => $renja->opd->id,
                    'kode' => $renja->opd->kode,
                    'nama' => $renja->opd->nama,
                    'singkatan' => $renja->opd->singkatan,
                ] : null,
                'opd_unit' => $renja->opdUnit ? [
                    'id' => $renja->opdUnit->id,
                    'kode' => $renja->opdUnit->kode,
                    'nama' => $renja->opdUnit->nama,
                ] : null,
                'rkpd' => $renja->rkpd ? [
                    'id' => $renja->rkpd->id,
                    'judul' => $renja->rkpd->judul,
                    'tahun' => $renja->rkpd->tahun,
                ] : null,
                'periode_tahun' => $renja->periodeTahun ? [
                    'id' => $renja->periodeTahun->id,
                    'tahun' => $renja->periodeTahun->tahun,
                    'nama' => $renja->periodeTahun->nama,
                ] : null,
            ]);

        return Inertia::render('RenjaOpd/Index', [
            'items' => $items,
            'filters' => $filters,
            'opdOptions' => $this->opdOptions($user),
            'periodeOptions' => $this->periodeOptions(),
            'can' => [
                'manage' => $user->can('create', RenjaOpd::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', RenjaOpd::class);

        return Inertia::render('RenjaOpd/Form', [
            'mode' => 'create',
            'renja' => null,
            'rkpdOptions' => $this->rkpdOptions(),
            'renstraOptions' => $this->renstraOptions($request->user()),
            'opdOptions' => $this->opdOptions($request->user()),
            'opdUnitOptions' => $this->opdUnitOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
        ]);
    }

    public function store(StoreRenjaOpdRequest $request): RedirectResponse
    {
        $renja = RenjaOpd::create([
            ...$request->validated(),
            'status' => $request->validated('status') ?: 'draft',
        ]);

        return redirect()->route('renja-opd.show', $renja)->with('success', 'Renja OPD berhasil dibuat.');
    }

    public function show(Request $request, RenjaOpd $renjaOpd): Response
    {
        $this->authorize('view', $renjaOpd);

        $filters = $request->only(['search', 'status']);
        $canManage = $request->user()->can('update', $renjaOpd);

        $renjaOpd->load(['opd:id,kode,nama,singkatan', 'opdUnit:id,kode,nama', 'rkpd:id,judul,tahun,status', 'periodeTahun:id,tahun,nama']);

        $items = $renjaOpd->items()
            ->with([
                'programPemerintahan:id,kode,nama',
                'kegiatanPemerintahan:id,kode,nama',
                'subKegiatanPemerintahan:id,kode,nama',
            ])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('kode', 'ilike', "%{$search}%")
                        ->orWhere('nama_sub_kegiatan', 'ilike', "%{$search}%")
                        ->orWhere('indikator', 'ilike', "%{$search}%");
                });
            })
            ->orderBy('urutan')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (RenjaOpdItem $item) => $this->serializeItem($item));

        return Inertia::render('RenjaOpd/Show', [
            'renja' => $this->serializeRenja($renjaOpd),
            'items' => $items,
            'filters' => $filters,
            'subKegiatanOptions' => $canManage ? $this->subKegiatanOptions((int) $renjaOpd->periode_tahun_id) : [],
            'can' => [
                'manage' => $canManage,
            ],
        ]);
    }

    public function edit(Request $request, RenjaOpd $renjaOpd): Response
    {
        $this->authorize('update', $renjaOpd);

        return Inertia::render('RenjaOpd/Form', [
            'mode' => 'edit',
            'renja' => $this->serializeRenja($renjaOpd),
            'rkpdOptions' => $this->rkpdOptions(),
            'renstraOptions' => $this->renstraOptions($request->user()),
            'opdOptions' => $this->opdOptions($request->user()),
            'opdUnitOptions' => $this->opdUnitOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
        ]);
    }

    public function update(UpdateRenjaOpdRequest $request, RenjaOpd $renjaOpd): RedirectResponse
    {
        $renjaOpd->update($request->validated());

        return redirect()->route('renja-opd.show', $renjaOpd)->with('success', 'Renja OPD berhasil diperbarui.');
    }

    public function destroy(RenjaOpd $renjaOpd): RedirectResponse
    {
        $this->authorize('delete', $renjaOpd);

        $renjaOpd->delete();

        return redirect()->route('renja-opd.index')->with('success', 'Renja OPD berhasil dihapus.');
    }

    private function shouldLimitToUserOpd(User $user): bool
    {
        return $user->hasRole('admin_opd')
            && ! $user->hasAnyRole(['super_admin', 'admin_kabupaten_bagian_organisasi', 'admin_kabupaten_bapperida', 'admin_kabupaten_inspektorat']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rkpdOptions(): array
    {
        return Rkpd::query()
            ->orderByDesc('tahun')
            ->get(['id', 'judul', 'tahun', 'status'])
            ->map(fn (Rkpd $rkpd) => [
                'id' => $rkpd->id,
                'tahun' => $rkpd->tahun,
                'label' => "{$rkpd->tahun} - {$rkpd->judul}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function renstraOptions(User $user): array
    {
        return RenstraOpd::query()
            ->with('opd:id,nama,singkatan')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->orderByDesc('tahun_awal')
            ->get(['id', 'opd_id', 'judul', 'tahun_awal', 'tahun_akhir'])
            ->map(fn (RenstraOpd $renstra) => [
                'id' => $renstra->id,
                'label' => "{$renstra->tahun_awal}-{$renstra->tahun_akhir} - ".($renstra->opd?->singkatan ?: $renstra->opd?->nama ?: $renstra->judul),
            ])
            ->all();
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
    private function opdUnitOptions(User $user): array
    {
        return OpdUnit::query()
            ->with('opd:id,nama,singkatan')
            ->where('status', 'active')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($user->hasOpdUnitScope(), fn (Builder $query) => $query->whereKey($user->opd_unit_id))
            ->orderBy('nama')
            ->get(['id', 'opd_id', 'kode', 'nama'])
            ->map(fn (OpdUnit $unit) => [
                'id' => $unit->id,
                'opd_id' => $unit->opd_id,
                'label' => "{$unit->nama} - ".($unit->opd?->singkatan ?: $unit->opd?->nama),
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
                'tahun' => $periode->tahun,
                'label' => "{$periode->tahun} - {$periode->nama}",
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function subKegiatanOptions(int $periodeTahunId): array
    {
        return SubKegiatanPemerintahan::query()
            ->with('kegiatanPemerintahan.programPemerintahan:id,kode,nama')
            ->where('periode_tahun_id', $periodeTahunId)
            ->where('status', 'active')
            ->orderBy('kode')
            ->limit(3000)
            ->get(['id', 'periode_tahun_id', 'kegiatan_pemerintahan_id', 'kode', 'nama'])
            ->map(function (SubKegiatanPemerintahan $subKegiatan) {
                $kegiatan = $subKegiatan->kegiatanPemerintahan;
                $program = $kegiatan?->programPemerintahan;

                return [
                    'id' => $subKegiatan->id,
                    'kode' => $subKegiatan->kode,
                    'nama' => $subKegiatan->nama,
                    'label' => "{$subKegiatan->kode} - {$subKegiatan->nama}",
                    'description' => $this->label($kegiatan?->kode, $kegiatan?->nama),
                    'group' => $this->label($program?->kode, $program?->nama),
                ];
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRenja(RenjaOpd $renja): array
    {
        return [
            'id' => $renja->id,
            'rkpd_id' => $renja->rkpd_id,
            'renstra_opd_id' => $renja->renstra_opd_id,
            'opd_id' => $renja->opd_id,
            'opd_unit_id' => $renja->opd_unit_id,
            'periode_tahun_id' => $renja->periode_tahun_id,
            'tahun' => $renja->tahun,
            'judul' => $renja->judul,
            'nomor_dokumen' => $renja->nomor_dokumen,
            'status' => $renja->status,
            'catatan' => $renja->catatan,
            'opd' => $renja->opd ? [
                'id' => $renja->opd->id,
                'kode' => $renja->opd->kode,
                'nama' => $renja->opd->nama,
                'singkatan' => $renja->opd->singkatan,
            ] : null,
            'opd_unit' => $renja->opdUnit ? [
                'id' => $renja->opdUnit->id,
                'kode' => $renja->opdUnit->kode,
                'nama' => $renja->opdUnit->nama,
            ] : null,
            'rkpd' => $renja->rkpd ? [
                'id' => $renja->rkpd->id,
                'judul' => $renja->rkpd->judul,
                'tahun' => $renja->rkpd->tahun,
            ] : null,
            'periode_tahun' => $renja->periodeTahun ? [
                'id' => $renja->periodeTahun->id,
                'tahun' => $renja->periodeTahun->tahun,
                'nama' => $renja->periodeTahun->nama,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(RenjaOpdItem $item): array
    {
        return [
            'id' => $item->id,
            'sub_kegiatan_pemerintahan_id' => $item->sub_kegiatan_pemerintahan_id,
            'kode' => $item->kode,
            'nama_sub_kegiatan' => $item->nama_sub_kegiatan,
            'indikator' => $item->indikator,
            'target_akhir_renstra' => $item->target_akhir_renstra,
            'realisasi_capaian_renja_tahun_lalu' => $item->realisasi_capaian_renja_tahun_lalu,
            'prakiraan_capaian_target_renja_tahun_berjalan' => $item->prakiraan_capaian_target_renja_tahun_berjalan,
            'target' => $item->target,
            'pagu_indikatif' => $item->pagu_indikatif,
            'lokasi' => $item->lokasi,
            'sumber_dana' => $item->sumber_dana,
            'prioritas_nasional' => $item->prioritas_nasional,
            'prioritas_daerah' => $item->prioritas_daerah,
            'kelompok_sasaran' => $item->kelompok_sasaran,
            'prakiraan_maju_target' => $item->prakiraan_maju_target,
            'prakiraan_maju_pagu_indikatif' => $item->prakiraan_maju_pagu_indikatif,
            'status' => $item->status,
            'urutan' => $item->urutan,
            'program' => $this->label($item->programPemerintahan?->kode, $item->programPemerintahan?->nama),
            'kegiatan' => $this->label($item->kegiatanPemerintahan?->kode, $item->kegiatanPemerintahan?->nama),
            'sub_kegiatan' => $this->label($item->subKegiatanPemerintahan?->kode, $item->subKegiatanPemerintahan?->nama),
        ];
    }

    private function label(?string $kode, ?string $nama): string
    {
        return trim(collect([$kode, $nama])->filter()->implode(' - ')) ?: '-';
    }
}
