<?php

namespace App\Http\Controllers\RenstraOpd;

use App\Http\Controllers\Controller;
use App\Http\Requests\RenstraOpd\StoreRenstraOpdRequest;
use App\Http\Requests\RenstraOpd\UpdateRenstraOpdRequest;
use App\Models\IndikatorOpdProgram;
use App\Models\IndikatorProgramRpjmd;
use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorSasaranOpd;
use App\Models\IndikatorSubKegiatan;
use App\Models\IndikatorTujuanDaerah;
use App\Models\IndikatorTujuanOpd;
use App\Models\KegiatanPemerintahan;
use App\Models\Opd;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\OpdUnit;
use App\Models\PeriodeTahun;
use App\Models\ProgramPemerintahan;
use App\Models\ProgramRpjmd;
use App\Models\RenstraOpd;
use App\Models\Rpjmd;
use App\Models\SasaranDaerah;
use App\Models\SasaranOpd;
use App\Models\SatuanIndikator;
use App\Models\SubKegiatanPemerintahan;
use App\Models\TujuanDaerah;
use App\Models\TujuanOpd;
use App\Models\User;
use App\Services\Workflow\WorkflowDataService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class RenstraOpdController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RenstraOpd::class);

        $filters = $request->only(['search', 'status', 'opd_id', 'rpjmd_id', 'periode_tahun_id']);
        $user = $request->user();

        $renstras = RenstraOpd::query()
            ->with(['opd:id,kode,nama,singkatan', 'rpjmd:id,judul,tahun_awal,tahun_akhir,status', 'periodeTahun:id,tahun,nama'])
            ->withCount(['tujuan', 'programs'])
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('judul', 'ilike', "%{$search}%")
                        ->orWhere('nomor_dokumen', 'ilike', "%{$search}%")
                        ->orWhereHas('opd', fn (Builder $query) => $query->where('nama', 'ilike', "%{$search}%")->orWhere('singkatan', 'ilike', "%{$search}%"));
                });
            })
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['opd_id'] ?? null, fn (Builder $query, string $opdId) => $query->where('opd_id', $opdId))
            ->when($filters['rpjmd_id'] ?? null, fn (Builder $query, string $rpjmdId) => $query->where('rpjmd_id', $rpjmdId))
            ->when($filters['periode_tahun_id'] ?? null, fn (Builder $query, string $periodeId) => $query->where('periode_tahun_id', $periodeId))
            ->orderByDesc('tahun_awal')
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (RenstraOpd $renstra) => [
                'id' => $renstra->id,
                'judul' => $renstra->judul,
                'nomor_dokumen' => $renstra->nomor_dokumen,
                'tahun_awal' => $renstra->tahun_awal,
                'tahun_akhir' => $renstra->tahun_akhir,
                'status' => $renstra->status,
                'opd' => $renstra->opd ? [
                    'id' => $renstra->opd->id,
                    'kode' => $renstra->opd->kode,
                    'nama' => $renstra->opd->nama,
                    'singkatan' => $renstra->opd->singkatan,
                ] : null,
                'rpjmd' => $renstra->rpjmd ? [
                    'id' => $renstra->rpjmd->id,
                    'judul' => $renstra->rpjmd->judul,
                    'tahun_awal' => $renstra->rpjmd->tahun_awal,
                    'tahun_akhir' => $renstra->rpjmd->tahun_akhir,
                ] : null,
                'periode_tahun' => $renstra->periodeTahun ? [
                    'id' => $renstra->periodeTahun->id,
                    'tahun' => $renstra->periodeTahun->tahun,
                    'nama' => $renstra->periodeTahun->nama,
                ] : null,
                'progress' => [
                    'tujuan_count' => $renstra->tujuan_count,
                    'program_count' => $renstra->programs_count,
                    'status' => $renstra->tujuan_count > 0 && $renstra->programs_count > 0 ? 'terisi' : 'belum_lengkap',
                ],
            ]);

        return Inertia::render('RenstraOpd/Index', [
            'renstras' => $renstras,
            'filters' => $filters,
            'opdOptions' => $this->opdOptions($user),
            'rpjmdOptions' => $this->rpjmdOptions(),
            'periodeOptions' => $this->periodeOptions(),
            'can' => [
                'manage' => $user->can('create', RenstraOpd::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', RenstraOpd::class);

        return Inertia::render('RenstraOpd/Form', [
            'mode' => 'create',
            'renstra' => null,
            'opdOptions' => $this->opdOptions($request->user()),
            'rpjmdOptions' => $this->rpjmdOptions(),
            'periodeOptions' => $this->periodeOptions(),
        ]);
    }

    public function store(StoreRenstraOpdRequest $request): RedirectResponse
    {
        $renstra = RenstraOpd::create($request->validated());

        return redirect()->route('renstra-opd.show', $renstra)->with('success', 'Renstra OPD berhasil ditambahkan.');
    }

    public function show(Request $request, RenstraOpd $renstraOpd, WorkflowDataService $workflowDataService): Response
    {
        $this->authorize('view', $renstraOpd);

        $manage = $request->user()->can('update', $renstraOpd);

        $renstraOpd->load([
            'opd:id,kode,nama,singkatan',
            'rpjmd:id,judul,tahun_awal,tahun_akhir,status',
            'periodeTahun:id,tahun,nama,status',
            'tujuan.tujuanDaerah:id,kode,tujuan',
            'tujuan.indikator.indikatorTujuanDaerah:id,kode,indikator',
            'tujuan.indikator.satuanIndikator:id,nama,simbol',
            'tujuan.indikator.targets.periodeTahun:id,tahun,nama',
            'tujuan.indikator.targetTriwulan.periodeTahun:id,tahun,nama',
            'tujuan.sasaran.sasaranDaerah:id,kode,sasaran',
            'tujuan.sasaran.indikator.indikatorSasaranDaerah:id,kode,indikator',
            'tujuan.sasaran.indikator.satuanIndikator:id,nama,simbol',
            'tujuan.sasaran.indikator.targets.periodeTahun:id,tahun,nama',
            'tujuan.sasaran.indikator.targetTriwulan.periodeTahun:id,tahun,nama',
            'tujuan.sasaran.programs.programRpjmd:id,kode,nama,program_pemerintahan_id',
            'tujuan.sasaran.programs.programRpjmd.programPemerintahan:id,kode,nama,bidang_urusan_id',
            'tujuan.sasaran.programs.programRpjmd.programPemerintahanReferences:id,kode,nama,bidang_urusan_id',
            'tujuan.sasaran.programs.programPemerintahan:id,kode,nama,bidang_urusan_id',
            'tujuan.sasaran.programs.indikator.indikatorProgramRpjmd:id,kode,indikator',
            'tujuan.sasaran.programs.indikator.satuanIndikator:id,nama,simbol',
            'tujuan.sasaran.programs.indikator.targets.periodeTahun:id,tahun,nama',
            'tujuan.sasaran.programs.indikator.targetTriwulan.periodeTahun:id,tahun,nama',
            'tujuan.sasaran.programs.kegiatan.kegiatanPemerintahan:id,kode,nama,program_pemerintahan_id',
            'tujuan.sasaran.programs.kegiatan.subKegiatan.subKegiatanPemerintahan:id,kode,nama,kegiatan_pemerintahan_id',
            'tujuan.sasaran.programs.kegiatan.subKegiatan.opdUnit:id,kode,nama,jenis_unit',
            'tujuan.sasaran.programs.kegiatan.subKegiatan.indikator.satuanIndikator:id,nama,simbol',
            'tujuan.sasaran.programs.kegiatan.subKegiatan.indikator.targetTriwulan.periodeTahun:id,tahun,nama',
        ]);

        return Inertia::render('RenstraOpd/Show', [
            'renstra' => $this->serializeRenstra($renstraOpd),
            'nodeOptions' => $manage ? $this->nodeOptions($renstraOpd) : [],
            'targetTriwulanOptions' => $manage ? $this->targetTriwulanOptions($renstraOpd) : [],
            'rpjmdReferenceOptions' => $manage ? $this->rpjmdReferenceOptions($renstraOpd->rpjmd_id) : [],
            'masterReferenceOptions' => $manage ? $this->masterReferenceOptions($renstraOpd) : [],
            'periodeOptions' => $manage ? $this->periodeOptions() : [],
            'satuanOptions' => $manage ? $this->satuanOptions() : [],
            'can' => [
                'manage' => $manage,
                'review' => $this->canReviewWorkflow($request->user()),
                'lock' => $this->canLockWorkflow($request->user()),
            ],
            'workflow' => $workflowDataService->forModel($renstraOpd, 'renstra_opd'),
        ]);
    }

    public function edit(Request $request, RenstraOpd $renstraOpd): Response
    {
        $this->authorize('update', $renstraOpd);

        return Inertia::render('RenstraOpd/Form', [
            'mode' => 'edit',
            'renstra' => [
                'id' => $renstraOpd->id,
                'opd_id' => $renstraOpd->opd_id,
                'rpjmd_id' => $renstraOpd->rpjmd_id,
                'periode_tahun_id' => $renstraOpd->periode_tahun_id,
                'judul' => $renstraOpd->judul,
                'nomor_dokumen' => $renstraOpd->nomor_dokumen,
                'tahun_awal' => $renstraOpd->tahun_awal,
                'tahun_akhir' => $renstraOpd->tahun_akhir,
                'status' => $renstraOpd->status,
                'keterangan' => $renstraOpd->keterangan,
            ],
            'opdOptions' => $this->opdOptions($request->user()),
            'rpjmdOptions' => $this->rpjmdOptions(),
            'periodeOptions' => $this->periodeOptions(),
        ]);
    }

    public function update(UpdateRenstraOpdRequest $request, RenstraOpd $renstraOpd): RedirectResponse
    {
        $renstraOpd->update($request->validated());

        return redirect()->route('renstra-opd.show', $renstraOpd)->with('success', 'Renstra OPD berhasil diperbarui.');
    }

    public function destroy(RenstraOpd $renstraOpd): RedirectResponse
    {
        $this->authorize('delete', $renstraOpd);

        $renstraOpd->delete();

        return redirect()->route('renstra-opd.index')->with('success', 'Renstra OPD berhasil dihapus.');
    }

    private function shouldLimitToUserOpd(User $user): bool
    {
        return $user->hasRole('admin_opd')
            && ! $user->hasAnyRole(['super_admin', 'admin_kabupaten_bagian_organisasi', 'admin_kabupaten_bapperida', 'admin_kabupaten_inspektorat']);
    }

    private function canReviewWorkflow(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin_kabupaten_bagian_organisasi'])
            || $user->hasPermission('lock_period');
    }

    private function canLockWorkflow(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('lock_period');
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
    private function rpjmdOptions(): array
    {
        return Rpjmd::query()
            ->orderByDesc('tahun_awal')
            ->get(['id', 'judul', 'tahun_awal', 'tahun_akhir', 'status'])
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
                'label' => "{$periode->tahun} - {$periode->nama}",
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
    private function nodeOptions(RenstraOpd $renstra): array
    {
        return [
            'tujuan' => TujuanOpd::query()->where('renstra_opd_id', $renstra->id)->orderBy('urutan')->get(['id', 'kode', 'tujuan'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->tujuan)])->values()->all(),
            'indikator_tujuan' => IndikatorTujuanOpd::query()->whereHas('tujuan', fn (Builder $query) => $query->where('renstra_opd_id', $renstra->id))->orderBy('urutan')->get(['id', 'kode', 'indikator'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all(),
            'sasaran' => SasaranOpd::query()->whereHas('tujuan', fn (Builder $query) => $query->where('renstra_opd_id', $renstra->id))->orderBy('urutan')->get(['id', 'kode', 'sasaran'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->sasaran)])->values()->all(),
            'indikator_sasaran' => IndikatorSasaranOpd::query()->whereHas('sasaran.tujuan', fn (Builder $query) => $query->where('renstra_opd_id', $renstra->id))->orderBy('urutan')->get(['id', 'kode', 'indikator'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all(),
            'program' => OpdProgram::query()->where('renstra_opd_id', $renstra->id)->orderBy('urutan')->get(['id', 'kode', 'nama'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->nama)])->values()->all(),
            'indikator_program' => IndikatorOpdProgram::query()->whereHas('program', fn (Builder $query) => $query->where('renstra_opd_id', $renstra->id))->orderBy('urutan')->get(['id', 'kode', 'indikator'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all(),
            'kegiatan' => OpdKegiatan::query()->whereHas('program', fn (Builder $query) => $query->where('renstra_opd_id', $renstra->id))->orderBy('urutan')->get(['id', 'kode', 'nama'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->nama)])->values()->all(),
            'sub_kegiatan' => OpdSubKegiatan::query()->whereHas('kegiatan.program', fn (Builder $query) => $query->where('renstra_opd_id', $renstra->id))->orderBy('urutan')->get(['id', 'kode', 'nama'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->nama)])->values()->all(),
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function targetTriwulanOptions(RenstraOpd $renstra): array
    {
        return [
            'indikator_tujuan_opd' => IndikatorTujuanOpd::query()
                ->whereHas('tujuan', fn (Builder $query) => $query->where('renstra_opd_id', $renstra->id))
                ->orderBy('urutan')
                ->get(['id', 'kode', 'indikator'])
                ->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])
                ->values()
                ->all(),
            'indikator_sasaran_opd' => IndikatorSasaranOpd::query()
                ->whereHas('sasaran.tujuan', fn (Builder $query) => $query->where('renstra_opd_id', $renstra->id))
                ->orderBy('urutan')
                ->get(['id', 'kode', 'indikator'])
                ->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])
                ->values()
                ->all(),
            'indikator_opd_program' => IndikatorOpdProgram::query()
                ->whereHas('program', fn (Builder $query) => $query->where('renstra_opd_id', $renstra->id))
                ->orderBy('urutan')
                ->get(['id', 'kode', 'indikator'])
                ->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])
                ->values()
                ->all(),
            'indikator_sub_kegiatan' => IndikatorSubKegiatan::query()
                ->whereHas('subKegiatan.kegiatan.program', fn (Builder $query) => $query->where('renstra_opd_id', $renstra->id))
                ->orderBy('urutan')
                ->get(['id', 'kode', 'indikator'])
                ->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function rpjmdReferenceOptions(int $rpjmdId): array
    {
        return [
            'tujuan_daerah' => TujuanDaerah::query()->forRpjmd($rpjmdId)->orderBy('urutan')->get(['id', 'kode', 'tujuan'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->tujuan)])->values()->all(),
            'indikator_tujuan_daerah' => IndikatorTujuanDaerah::query()->whereHas('tujuan', fn (Builder $query) => $query->forRpjmd($rpjmdId))->orderBy('urutan')->get(['id', 'kode', 'indikator'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all(),
            'sasaran_daerah' => SasaranDaerah::query()->whereHas('tujuan', fn (Builder $query) => $query->forRpjmd($rpjmdId))->orderBy('urutan')->get(['id', 'kode', 'sasaran'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->sasaran)])->values()->all(),
            'indikator_sasaran_daerah' => IndikatorSasaranDaerah::query()->whereHas('sasaran.tujuan', fn (Builder $query) => $query->forRpjmd($rpjmdId))->orderBy('urutan')->get(['id', 'kode', 'indikator'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all(),
            'program_rpjmd' => ProgramRpjmd::query()
                ->forRpjmd($rpjmdId)
                ->with('programPemerintahan:id,kode,nama,bidang_urusan_id')
                ->with('programPemerintahanReferences:id,kode,nama,bidang_urusan_id')
                ->orderBy('urutan')
                ->get(['id', 'program_pemerintahan_id', 'kode', 'nama'])
                ->map(fn (ProgramRpjmd $item) => [
                    'id' => $item->id,
                    'program_pemerintahan_id' => $item->program_pemerintahan_id,
                    'program_pemerintahan_ids' => $item->programPemerintahanReferenceIds(),
                    'label' => $this->nodeLabel($item->kode, $item->nama),
                    'description' => $item->programPemerintahan ? $this->nodeLabel($item->programPemerintahan->kode, $item->programPemerintahan->nama) : null,
                ])
                ->values()
                ->all(),
            'indikator_program_rpjmd' => IndikatorProgramRpjmd::query()->whereHas('program', fn (Builder $query) => $query->forRpjmd($rpjmdId))->orderBy('urutan')->get(['id', 'kode', 'indikator'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all(),
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function masterReferenceOptions(RenstraOpd $renstra): array
    {
        return [
            'program_pemerintahan' => ProgramPemerintahan::query()
                ->with('bidangUrusan.urusanPemerintahan:id,kode,nama')
                ->where('status', 'active')
                ->orderBy('kode')
                ->get(['id', 'bidang_urusan_id', 'kode', 'nama'])
                ->map(fn (ProgramPemerintahan $program) => [
                    'id' => $program->id,
                    'kode' => $program->kode,
                    'nama' => $program->nama,
                    'bidang_urusan_id' => $program->bidang_urusan_id,
                    'label' => $this->nodeLabel($program->kode, $program->nama),
                    'description' => $program->bidangUrusan ? $this->nodeLabel($program->bidangUrusan->kode, $program->bidangUrusan->nama) : null,
                    'group' => $program->bidangUrusan?->urusanPemerintahan ? $this->nodeLabel($program->bidangUrusan->urusanPemerintahan->kode, $program->bidangUrusan->urusanPemerintahan->nama) : null,
                ])
                ->values()
                ->all(),
            'kegiatan_pemerintahan' => KegiatanPemerintahan::query()
                ->with('programPemerintahan:id,kode,nama,bidang_urusan_id')
                ->where('status', 'active')
                ->orderBy('kode')
                ->get(['id', 'program_pemerintahan_id', 'kode', 'nama'])
                ->map(fn (KegiatanPemerintahan $kegiatan) => [
                    'id' => $kegiatan->id,
                    'kode' => $kegiatan->kode,
                    'nama' => $kegiatan->nama,
                    'program_pemerintahan_id' => $kegiatan->program_pemerintahan_id,
                    'label' => $this->nodeLabel($kegiatan->kode, $kegiatan->nama),
                    'description' => $kegiatan->programPemerintahan ? $this->nodeLabel($kegiatan->programPemerintahan->kode, $kegiatan->programPemerintahan->nama) : null,
                    'group' => $kegiatan->programPemerintahan ? $this->nodeLabel($kegiatan->programPemerintahan->kode, $kegiatan->programPemerintahan->nama) : null,
                ])
                ->values()
                ->all(),
            'sub_kegiatan_pemerintahan' => SubKegiatanPemerintahan::query()
                ->with('kegiatanPemerintahan.programPemerintahan:id,kode,nama,bidang_urusan_id')
                ->where('status', 'active')
                ->orderBy('kode')
                ->get(['id', 'kegiatan_pemerintahan_id', 'kode', 'nama'])
                ->map(fn (SubKegiatanPemerintahan $subKegiatan) => [
                    'id' => $subKegiatan->id,
                    'kode' => $subKegiatan->kode,
                    'nama' => $subKegiatan->nama,
                    'kegiatan_pemerintahan_id' => $subKegiatan->kegiatan_pemerintahan_id,
                    'program_pemerintahan_id' => $subKegiatan->kegiatanPemerintahan?->program_pemerintahan_id,
                    'label' => $this->nodeLabel($subKegiatan->kode, $subKegiatan->nama),
                    'description' => $subKegiatan->kegiatanPemerintahan ? $this->nodeLabel($subKegiatan->kegiatanPemerintahan->kode, $subKegiatan->kegiatanPemerintahan->nama) : null,
                    'group' => $subKegiatan->kegiatanPemerintahan?->programPemerintahan ? $this->nodeLabel($subKegiatan->kegiatanPemerintahan->programPemerintahan->kode, $subKegiatan->kegiatanPemerintahan->programPemerintahan->nama) : null,
                ])
                ->values()
                ->all(),
            'opd_units' => OpdUnit::query()
                ->where('opd_id', $renstra->opd_id)
                ->where('status', 'active')
                ->orderBy('kode')
                ->get(['id', 'kode', 'nama', 'jenis_unit'])
                ->map(fn (OpdUnit $unit) => [
                    'id' => $unit->id,
                    'kode' => $unit->kode,
                    'nama' => $unit->nama,
                    'jenis_unit' => $unit->jenis_unit,
                    'label' => $this->nodeLabel($unit->kode, $unit->nama),
                    'description' => $unit->jenis_unit,
                ])
                ->values()
                ->all(),
        ];
    }

    private function nodeLabel(?string $kode, ?string $label): string
    {
        return trim(($kode ? "{$kode} - " : '').str($label ?? '')->limit(90)->toString());
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRenstra(RenstraOpd $renstra): array
    {
        return [
            'id' => $renstra->id,
            'judul' => $renstra->judul,
            'nomor_dokumen' => $renstra->nomor_dokumen,
            'tahun_awal' => $renstra->tahun_awal,
            'tahun_akhir' => $renstra->tahun_akhir,
            'status' => $renstra->status,
            'keterangan' => $renstra->keterangan,
            'opd' => $renstra->opd ? [
                'id' => $renstra->opd->id,
                'kode' => $renstra->opd->kode,
                'nama' => $renstra->opd->nama,
                'singkatan' => $renstra->opd->singkatan,
            ] : null,
            'rpjmd' => $renstra->rpjmd ? [
                'id' => $renstra->rpjmd->id,
                'judul' => $renstra->rpjmd->judul,
                'tahun_awal' => $renstra->rpjmd->tahun_awal,
                'tahun_akhir' => $renstra->rpjmd->tahun_akhir,
            ] : null,
            'periode_tahun' => $renstra->periodeTahun ? [
                'id' => $renstra->periodeTahun->id,
                'tahun' => $renstra->periodeTahun->tahun,
                'nama' => $renstra->periodeTahun->nama,
            ] : null,
            'tujuan' => $renstra->tujuan->map(fn (TujuanOpd $tujuan) => [
                'id' => $tujuan->id,
                'tujuan_daerah_id' => $tujuan->tujuan_daerah_id,
                'kode' => $tujuan->kode,
                'tujuan' => $tujuan->tujuan,
                'linked' => filled($tujuan->tujuan_daerah_id),
                'urutan' => $tujuan->urutan,
                'tujuan_daerah' => $tujuan->tujuanDaerah ? [
                    'kode' => $tujuan->tujuanDaerah->kode,
                    'tujuan' => $tujuan->tujuanDaerah->tujuan,
                ] : null,
                'indikator' => $tujuan->indikator->map(fn (IndikatorTujuanOpd $indikator) => $this->serializeIndikator($indikator, 'indikatorTujuanDaerah')),
                'sasaran' => $tujuan->sasaran->map(fn (SasaranOpd $sasaran) => [
                    'id' => $sasaran->id,
                    'sasaran_daerah_id' => $sasaran->sasaran_daerah_id,
                    'kode' => $sasaran->kode,
                    'sasaran' => $sasaran->sasaran,
                    'linked' => filled($sasaran->sasaran_daerah_id),
                    'urutan' => $sasaran->urutan,
                    'sasaran_daerah' => $sasaran->sasaranDaerah ? [
                        'kode' => $sasaran->sasaranDaerah->kode,
                        'sasaran' => $sasaran->sasaranDaerah->sasaran,
                    ] : null,
                    'indikator' => $sasaran->indikator->map(fn (IndikatorSasaranOpd $indikator) => $this->serializeIndikator($indikator, 'indikatorSasaranDaerah')),
                    'programs' => $sasaran->programs->map(fn (OpdProgram $program) => [
                        'id' => $program->id,
                        'program_rpjmd_id' => $program->program_rpjmd_id,
                        'program_pemerintahan_id' => $program->program_pemerintahan_id,
                        'kode' => $program->kode,
                        'nama' => $program->nama,
                        'pagu_indikatif' => $program->pagu_indikatif,
                        'status' => $program->status,
                        'linked' => filled($program->program_rpjmd_id),
                        'urutan' => $program->urutan,
                        'program_rpjmd' => $program->programRpjmd ? [
                            'kode' => $program->programRpjmd->kode,
                            'nama' => $program->programRpjmd->nama,
                            'program_pemerintahan_id' => $program->programRpjmd->program_pemerintahan_id,
                            'program_pemerintahan_ids' => $program->programRpjmd->programPemerintahanReferenceIds(),
                        ] : null,
                        'program_pemerintahan' => $program->programPemerintahan ? [
                            'kode' => $program->programPemerintahan->kode,
                            'nama' => $program->programPemerintahan->nama,
                        ] : null,
                        'indikator' => $program->indikator->map(fn (IndikatorOpdProgram $indikator) => $this->serializeIndikator($indikator, 'indikatorProgramRpjmd')),
                        'kegiatan' => $program->kegiatan->map(fn (OpdKegiatan $kegiatan) => [
                            'id' => $kegiatan->id,
                            'kegiatan_pemerintahan_id' => $kegiatan->kegiatan_pemerintahan_id,
                            'kode' => $kegiatan->kode,
                            'nama' => $kegiatan->nama,
                            'pagu_indikatif' => $kegiatan->pagu_indikatif,
                            'urutan' => $kegiatan->urutan,
                            'kegiatan_pemerintahan' => $kegiatan->kegiatanPemerintahan ? [
                                'kode' => $kegiatan->kegiatanPemerintahan->kode,
                                'nama' => $kegiatan->kegiatanPemerintahan->nama,
                                'program_pemerintahan_id' => $kegiatan->kegiatanPemerintahan->program_pemerintahan_id,
                            ] : null,
                            'sub_kegiatan' => $kegiatan->subKegiatan->map(fn (OpdSubKegiatan $subKegiatan) => [
                                'id' => $subKegiatan->id,
                                'sub_kegiatan_pemerintahan_id' => $subKegiatan->sub_kegiatan_pemerintahan_id,
                                'opd_unit_id' => $subKegiatan->opd_unit_id,
                                'kode' => $subKegiatan->kode,
                                'nama' => $subKegiatan->nama,
                                'pagu_indikatif' => $subKegiatan->pagu_indikatif,
                                'urutan' => $subKegiatan->urutan,
                                'sub_kegiatan_pemerintahan' => $subKegiatan->subKegiatanPemerintahan ? [
                                    'kode' => $subKegiatan->subKegiatanPemerintahan->kode,
                                    'nama' => $subKegiatan->subKegiatanPemerintahan->nama,
                                    'kegiatan_pemerintahan_id' => $subKegiatan->subKegiatanPemerintahan->kegiatan_pemerintahan_id,
                                ] : null,
                                'opd_unit' => $subKegiatan->opdUnit ? [
                                    'kode' => $subKegiatan->opdUnit->kode,
                                    'nama' => $subKegiatan->opdUnit->nama,
                                    'jenis_unit' => $subKegiatan->opdUnit->jenis_unit,
                                ] : null,
                                'indikator' => $subKegiatan->indikator->map(fn (IndikatorSubKegiatan $indikator) => [
                                    'id' => $indikator->id,
                                    'satuan_indikator_id' => $indikator->satuan_indikator_id,
                                    'kode' => $indikator->kode,
                                    'indikator' => $indikator->indikator,
                                    'tipe_indikator' => $indikator->tipe_indikator,
                                    'formula' => $indikator->formula,
                                    'sumber_data' => $indikator->sumber_data,
                                    'urutan' => $indikator->urutan,
                                    'target_triwulan' => $this->serializeTargetTriwulan($indikator),
                                    'satuan' => $indikator->satuanIndikator ? [
                                        'nama' => $indikator->satuanIndikator->nama,
                                        'simbol' => $indikator->satuanIndikator->simbol,
                                    ] : null,
                                ]),
                            ]),
                        ]),
                    ]),
                ]),
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeIndikator(IndikatorTujuanOpd|IndikatorSasaranOpd|IndikatorOpdProgram $indikator, string $linkedRelation): array
    {
        return [
            'id' => $indikator->id,
            'kode' => $indikator->kode,
            'indikator' => $indikator->indikator,
            $this->linkedColumn($linkedRelation) => $indikator->{$this->linkedColumn($linkedRelation)},
            'satuan_indikator_id' => $indikator->satuan_indikator_id,
            'tipe_indikator' => $indikator->tipe_indikator,
            'formula' => $indikator->formula,
            'sumber_data' => $indikator->sumber_data,
            'linked' => filled($indikator->{$linkedRelation}?->id),
            'urutan' => $indikator->urutan,
            'satuan' => $indikator->satuanIndikator ? [
                'nama' => $indikator->satuanIndikator->nama,
                'simbol' => $indikator->satuanIndikator->simbol,
            ] : null,
            'targets' => $indikator->targets->map(fn ($target) => [
                'id' => $target->id,
                'periode_tahun' => [
                    'id' => $target->periodeTahun->id,
                    'tahun' => $target->periodeTahun->tahun,
                    'nama' => $target->periodeTahun->nama,
                ],
                'target' => $target->target,
                'target_text' => $target->target_text,
                'pagu' => $target->pagu ?? null,
            ]),
            'target_triwulan' => $this->serializeTargetTriwulan($indikator),
        ];
    }

    private function linkedColumn(string $linkedRelation): string
    {
        return match ($linkedRelation) {
            'indikatorTujuanDaerah' => 'indikator_tujuan_daerah_id',
            'indikatorSasaranDaerah' => 'indikator_sasaran_daerah_id',
            'indikatorProgramRpjmd' => 'indikator_program_rpjmd_id',
        };
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function serializeTargetTriwulan(IndikatorTujuanOpd|IndikatorSasaranOpd|IndikatorOpdProgram|IndikatorSubKegiatan $indikator)
    {
        return $indikator->targetTriwulan->map(fn ($target) => [
            'id' => $target->id,
            'periode_tahun' => [
                'id' => $target->periodeTahun->id,
                'tahun' => $target->periodeTahun->tahun,
                'nama' => $target->periodeTahun->nama,
            ],
            'triwulan' => $target->triwulan,
            'target_angka' => $target->target_angka,
            'target_text' => $target->target_text,
            'target_anggaran' => $target->target_anggaran,
        ]);
    }
}
