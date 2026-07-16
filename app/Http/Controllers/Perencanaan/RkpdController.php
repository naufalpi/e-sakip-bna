<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Http\Requests\Perencanaan\StoreRkpdRequest;
use App\Http\Requests\Perencanaan\UpdateRkpdRequest;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\ProgramRpjmd;
use App\Models\RenjaOpd;
use App\Models\Rkpd;
use App\Models\RkpdItem;
use App\Models\Rpjmd;
use App\Models\SubKegiatanPemerintahan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RkpdController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Rkpd::class);

        $filters = $request->only(['search', 'status', 'tahun', 'rpjmd_id']);

        $rkpd = Rkpd::query()
            ->with(['rpjmd:id,judul,tahun_awal,tahun_akhir,status', 'periodeTahun:id,tahun,nama,status'])
            ->withCount(['items', 'renjaOpd'])
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('judul', 'ilike', "%{$search}%")
                        ->orWhere('nomor_dokumen', 'ilike', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['tahun'] ?? null, fn (Builder $query, string $tahun) => $query->where('tahun', $tahun))
            ->when($filters['rpjmd_id'] ?? null, fn (Builder $query, string $rpjmdId) => $query->where('rpjmd_id', $rpjmdId))
            ->orderByDesc('tahun')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Rkpd $rkpd) => [
                'id' => $rkpd->id,
                'judul' => $rkpd->judul,
                'nomor_dokumen' => $rkpd->nomor_dokumen,
                'tahun' => $rkpd->tahun,
                'status' => $rkpd->status,
                'items_count' => $rkpd->items_count,
                'renja_opd_count' => $rkpd->renja_opd_count,
                'rpjmd' => $rkpd->rpjmd ? [
                    'id' => $rkpd->rpjmd->id,
                    'judul' => $rkpd->rpjmd->judul,
                    'tahun_awal' => $rkpd->rpjmd->tahun_awal,
                    'tahun_akhir' => $rkpd->rpjmd->tahun_akhir,
                ] : null,
                'periode_tahun' => $rkpd->periodeTahun ? [
                    'id' => $rkpd->periodeTahun->id,
                    'tahun' => $rkpd->periodeTahun->tahun,
                    'nama' => $rkpd->periodeTahun->nama,
                ] : null,
            ]);

        return Inertia::render('Rkpd/Index', [
            'items' => $rkpd,
            'filters' => $filters,
            'rpjmdOptions' => $this->rpjmdOptions(),
            'periodeOptions' => $this->periodeOptions(),
            'can' => [
                'manage' => $request->user()->can('create', Rkpd::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Rkpd::class);

        return Inertia::render('Rkpd/Form', [
            'mode' => 'create',
            'rkpd' => null,
            'rpjmdOptions' => $this->rpjmdOptions(),
            'periodeOptions' => $this->periodeOptions(),
        ]);
    }

    public function store(StoreRkpdRequest $request): RedirectResponse
    {
        $rkpd = Rkpd::create([
            ...$request->validated(),
            'status' => $request->validated('status') ?: 'draft',
        ]);

        return redirect()->route('rkpd.show', $rkpd)->with('success', 'RKPD berhasil dibuat.');
    }

    public function show(Request $request, Rkpd $rkpd): Response
    {
        $this->authorize('view', $rkpd);

        $filters = $request->only(['search', 'status', 'opd_id']);
        $user = $request->user();
        $canManage = $user->can('update', $rkpd);

        $rkpd->load(['rpjmd:id,judul,tahun_awal,tahun_akhir,status', 'periodeTahun:id,tahun,nama,status']);

        $itemsQuery = $rkpd->items()
            ->with([
                'opd:id,kode,nama,singkatan',
                'opdUnit:id,opd_id,kode,nama',
                'urusanPemerintahan:id,kode,nama',
                'bidangUrusan:id,kode,nama',
                'programPemerintahan:id,kode,nama',
                'kegiatanPemerintahan:id,kode,nama',
                'subKegiatanPemerintahan:id,kode,nama',
            ])
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($user->hasOpdUnitScope(), fn (Builder $query) => $query->where('opd_unit_id', $user->opd_unit_id))
            ->when($filters['opd_id'] ?? null, fn (Builder $query, string $opdId) => $query->where('opd_id', $opdId))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('kode', 'ilike', "%{$search}%")
                        ->orWhere('nama_urusan_bidang_program_kegiatan_sub', 'ilike', "%{$search}%")
                        ->orWhere('indikator', 'ilike', "%{$search}%")
                        ->orWhereHas('opd', fn (Builder $query) => $query->where('nama', 'ilike', "%{$search}%")->orWhere('singkatan', 'ilike', "%{$search}%"));
                });
            });

        $items = $itemsQuery
            ->orderBy('urutan')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (RkpdItem $item) => $this->serializeItem($item));

        return Inertia::render('Rkpd/Show', [
            'rkpd' => $this->serializeRkpd($rkpd),
            'items' => $items,
            'filters' => $filters,
            'summary' => $this->summary($rkpd, $user),
            'opdOptions' => $this->opdOptions($user),
            'subKegiatanOptions' => $canManage ? $this->subKegiatanOptions() : [],
            'programRpjmdOptions' => $canManage ? $this->programRpjmdOptions($rkpd->rpjmd_id) : [],
            'can' => [
                'manage' => $canManage,
                'pullRenja' => $canManage,
            ],
        ]);
    }

    public function edit(Rkpd $rkpd): Response
    {
        $this->authorize('update', $rkpd);

        return Inertia::render('Rkpd/Form', [
            'mode' => 'edit',
            'rkpd' => $this->serializeRkpd($rkpd),
            'rpjmdOptions' => $this->rpjmdOptions(),
            'periodeOptions' => $this->periodeOptions(),
        ]);
    }

    public function update(UpdateRkpdRequest $request, Rkpd $rkpd): RedirectResponse
    {
        $rkpd->update($request->validated());

        return redirect()->route('rkpd.show', $rkpd)->with('success', 'RKPD berhasil diperbarui.');
    }

    public function destroy(Rkpd $rkpd): RedirectResponse
    {
        $this->authorize('delete', $rkpd);

        $rkpd->delete();

        return redirect()->route('rkpd.index')->with('success', 'RKPD berhasil dihapus.');
    }

    private function shouldLimitToUserOpd(User $user): bool
    {
        return $user->hasRole('admin_opd')
            && ! $user->hasAnyRole(['super_admin', 'admin_kabupaten_bagian_organisasi', 'admin_kabupaten_bapperida', 'admin_kabupaten_inspektorat']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function rpjmdOptions(): array
    {
        return Rpjmd::query()
            ->orderByDesc('tahun_awal')
            ->get(['id', 'judul', 'tahun_awal', 'tahun_akhir'])
            ->map(fn (Rpjmd $rpjmd) => [
                'id' => $rpjmd->id,
                'label' => "{$rpjmd->tahun_awal}-{$rpjmd->tahun_akhir} - {$rpjmd->judul}",
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
    private function subKegiatanOptions(): array
    {
        return SubKegiatanPemerintahan::query()
            ->with('kegiatanPemerintahan.programPemerintahan.bidangUrusan.urusanPemerintahan:id,kode,nama')
            ->where('status', 'active')
            ->orderBy('kode')
            ->limit(3000)
            ->get(['id', 'kegiatan_pemerintahan_id', 'kode', 'nama'])
            ->map(function (SubKegiatanPemerintahan $subKegiatan) {
                $kegiatan = $subKegiatan->kegiatanPemerintahan;
                $program = $kegiatan?->programPemerintahan;
                $bidang = $program?->bidangUrusan;
                $urusan = $bidang?->urusanPemerintahan;

                return [
                    'id' => $subKegiatan->id,
                    'kode' => $subKegiatan->kode,
                    'nama' => $subKegiatan->nama,
                    'label' => "{$subKegiatan->kode} - {$subKegiatan->nama}",
                    'description' => $this->label($kegiatan?->kode, $kegiatan?->nama),
                    'group' => $this->label($program?->kode, $program?->nama),
                    'program_id' => $program?->id,
                    'kegiatan_id' => $kegiatan?->id,
                    'bidang_id' => $bidang?->id,
                    'urusan_id' => $urusan?->id,
                    'bidang_label' => $this->label($bidang?->kode, $bidang?->nama),
                    'urusan_label' => $this->label($urusan?->kode, $urusan?->nama),
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function programRpjmdOptions(?int $rpjmdId): array
    {
        return ProgramRpjmd::query()
            ->when($rpjmdId, fn (Builder $query) => $query->forRpjmd($rpjmdId))
            ->orderBy('urutan')
            ->get(['id', 'program_pemerintahan_id', 'kode', 'nama'])
            ->map(fn (ProgramRpjmd $program) => [
                'id' => $program->id,
                'label' => $this->label($program->kode, $program->nama),
                'program_pemerintahan_id' => $program->program_pemerintahan_id,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRkpd(Rkpd $rkpd): array
    {
        return [
            'id' => $rkpd->id,
            'rpjmd_id' => $rkpd->rpjmd_id,
            'periode_tahun_id' => $rkpd->periode_tahun_id,
            'tahun' => $rkpd->tahun,
            'judul' => $rkpd->judul,
            'nomor_dokumen' => $rkpd->nomor_dokumen,
            'status' => $rkpd->status,
            'catatan' => $rkpd->catatan,
            'rpjmd' => $rkpd->rpjmd ? [
                'id' => $rkpd->rpjmd->id,
                'judul' => $rkpd->rpjmd->judul,
                'tahun_awal' => $rkpd->rpjmd->tahun_awal,
                'tahun_akhir' => $rkpd->rpjmd->tahun_akhir,
            ] : null,
            'periode_tahun' => $rkpd->periodeTahun ? [
                'id' => $rkpd->periodeTahun->id,
                'tahun' => $rkpd->periodeTahun->tahun,
                'nama' => $rkpd->periodeTahun->nama,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeItem(RkpdItem $item): array
    {
        return [
            'id' => $item->id,
            'opd_id' => $item->opd_id,
            'opd_unit_id' => $item->opd_unit_id,
            'sub_kegiatan_pemerintahan_id' => $item->sub_kegiatan_pemerintahan_id,
            'program_rpjmd_id' => $item->program_rpjmd_id,
            'kode' => $item->kode,
            'nama_urusan_bidang_program_kegiatan_sub' => $item->nama_urusan_bidang_program_kegiatan_sub,
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
            'perangkat_daerah_penanggung_jawab' => $item->perangkat_daerah_penanggung_jawab,
            'status' => $item->status,
            'urutan' => $item->urutan,
            'opd' => $item->opd ? [
                'id' => $item->opd->id,
                'kode' => $item->opd->kode,
                'nama' => $item->opd->nama,
                'singkatan' => $item->opd->singkatan,
            ] : null,
            'opd_unit' => $item->opdUnit ? [
                'id' => $item->opdUnit->id,
                'kode' => $item->opdUnit->kode,
                'nama' => $item->opdUnit->nama,
            ] : null,
            'urusan' => $this->label($item->urusanPemerintahan?->kode, $item->urusanPemerintahan?->nama),
            'bidang' => $this->label($item->bidangUrusan?->kode, $item->bidangUrusan?->nama),
            'program' => $this->label($item->programPemerintahan?->kode, $item->programPemerintahan?->nama),
            'kegiatan' => $this->label($item->kegiatanPemerintahan?->kode, $item->kegiatanPemerintahan?->nama),
            'sub_kegiatan' => $this->label($item->subKegiatanPemerintahan?->kode, $item->subKegiatanPemerintahan?->nama),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Rkpd $rkpd, User $user): array
    {
        $query = $rkpd->items()
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id));

        return [
            'items_count' => (clone $query)->count(),
            'opd_count' => (clone $query)->distinct('opd_id')->count('opd_id'),
            'renja_count' => RenjaOpd::query()
                ->where(function ($query) use ($rkpd) {
                    $query->where('rkpd_id', $rkpd->id)
                        ->orWhere(fn ($query) => $query->where('periode_tahun_id', $rkpd->periode_tahun_id)->where('tahun', $rkpd->tahun));
                })
                ->count(),
            'total_pagu' => (float) (clone $query)->sum('pagu_indikatif'),
            'total_prakiraan_maju' => (float) (clone $query)->sum('prakiraan_maju_pagu_indikatif'),
        ];
    }

    private function label(?string $kode, ?string $nama): string
    {
        return trim(collect([$kode, $nama])->filter()->implode(' - ')) ?: '-';
    }
}
