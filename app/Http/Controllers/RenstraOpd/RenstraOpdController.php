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
use App\Models\Opd;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\PeriodeTahun;
use App\Models\ProgramRpjmd;
use App\Models\RenstraOpd;
use App\Models\Rpjmd;
use App\Models\SasaranDaerah;
use App\Models\SasaranOpd;
use App\Models\SatuanIndikator;
use App\Models\TujuanDaerah;
use App\Models\TujuanOpd;
use App\Models\User;
use App\Services\Workflow\WorkflowDataService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'tujuan.sasaran.sasaranDaerah:id,kode,sasaran',
            'tujuan.sasaran.indikator.indikatorSasaranDaerah:id,kode,indikator',
            'tujuan.sasaran.indikator.satuanIndikator:id,nama,simbol',
            'tujuan.sasaran.indikator.targets.periodeTahun:id,tahun,nama',
            'tujuan.sasaran.programs.programRpjmd:id,kode,nama',
            'tujuan.sasaran.programs.indikator.indikatorProgramRpjmd:id,kode,indikator',
            'tujuan.sasaran.programs.indikator.satuanIndikator:id,nama,simbol',
            'tujuan.sasaran.programs.indikator.targets.periodeTahun:id,tahun,nama',
            'tujuan.sasaran.programs.kegiatan.subKegiatan.indikator.satuanIndikator:id,nama,simbol',
        ]);

        return Inertia::render('RenstraOpd/Show', [
            'renstra' => $this->serializeRenstra($renstraOpd),
            'nodeOptions' => $manage ? $this->nodeOptions($renstraOpd) : [],
            'rpjmdReferenceOptions' => $manage ? $this->rpjmdReferenceOptions($renstraOpd->rpjmd_id) : [],
            'periodeOptions' => $manage ? $this->periodeOptions() : [],
            'satuanOptions' => $manage ? $this->satuanOptions() : [],
            'can' => [
                'manage' => $manage,
                'review' => $this->canReviewWorkflow($request->user()),
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
    private function rpjmdReferenceOptions(int $rpjmdId): array
    {
        return [
            'tujuan_daerah' => TujuanDaerah::query()->whereHas('misi', fn (Builder $query) => $query->where('rpjmd_id', $rpjmdId))->orderBy('urutan')->get(['id', 'kode', 'tujuan'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->tujuan)])->values()->all(),
            'indikator_tujuan_daerah' => IndikatorTujuanDaerah::query()->whereHas('tujuan.misi', fn (Builder $query) => $query->where('rpjmd_id', $rpjmdId))->orderBy('urutan')->get(['id', 'kode', 'indikator'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all(),
            'sasaran_daerah' => SasaranDaerah::query()->whereHas('tujuan.misi', fn (Builder $query) => $query->where('rpjmd_id', $rpjmdId))->orderBy('urutan')->get(['id', 'kode', 'sasaran'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->sasaran)])->values()->all(),
            'indikator_sasaran_daerah' => IndikatorSasaranDaerah::query()->whereHas('sasaran.tujuan.misi', fn (Builder $query) => $query->where('rpjmd_id', $rpjmdId))->orderBy('urutan')->get(['id', 'kode', 'indikator'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all(),
            'program_rpjmd' => ProgramRpjmd::query()->whereHas('strategi.sasaran.tujuan.misi', fn (Builder $query) => $query->where('rpjmd_id', $rpjmdId))->orderBy('urutan')->get(['id', 'kode', 'nama'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->nama)])->values()->all(),
            'indikator_program_rpjmd' => IndikatorProgramRpjmd::query()->whereHas('program.strategi.sasaran.tujuan.misi', fn (Builder $query) => $query->where('rpjmd_id', $rpjmdId))->orderBy('urutan')->get(['id', 'kode', 'indikator'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all(),
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
                'kode' => $tujuan->kode,
                'tujuan' => $tujuan->tujuan,
                'linked' => filled($tujuan->tujuan_daerah_id),
                'tujuan_daerah' => $tujuan->tujuanDaerah ? [
                    'kode' => $tujuan->tujuanDaerah->kode,
                    'tujuan' => $tujuan->tujuanDaerah->tujuan,
                ] : null,
                'indikator' => $tujuan->indikator->map(fn (IndikatorTujuanOpd $indikator) => $this->serializeIndikator($indikator, 'indikatorTujuanDaerah')),
                'sasaran' => $tujuan->sasaran->map(fn (SasaranOpd $sasaran) => [
                    'id' => $sasaran->id,
                    'kode' => $sasaran->kode,
                    'sasaran' => $sasaran->sasaran,
                    'linked' => filled($sasaran->sasaran_daerah_id),
                    'sasaran_daerah' => $sasaran->sasaranDaerah ? [
                        'kode' => $sasaran->sasaranDaerah->kode,
                        'sasaran' => $sasaran->sasaranDaerah->sasaran,
                    ] : null,
                    'indikator' => $sasaran->indikator->map(fn (IndikatorSasaranOpd $indikator) => $this->serializeIndikator($indikator, 'indikatorSasaranDaerah')),
                    'programs' => $sasaran->programs->map(fn (OpdProgram $program) => [
                        'id' => $program->id,
                        'kode' => $program->kode,
                        'nama' => $program->nama,
                        'pagu_indikatif' => $program->pagu_indikatif,
                        'status' => $program->status,
                        'linked' => filled($program->program_rpjmd_id),
                        'program_rpjmd' => $program->programRpjmd ? [
                            'kode' => $program->programRpjmd->kode,
                            'nama' => $program->programRpjmd->nama,
                        ] : null,
                        'indikator' => $program->indikator->map(fn (IndikatorOpdProgram $indikator) => $this->serializeIndikator($indikator, 'indikatorProgramRpjmd')),
                        'kegiatan' => $program->kegiatan->map(fn (OpdKegiatan $kegiatan) => [
                            'id' => $kegiatan->id,
                            'kode' => $kegiatan->kode,
                            'nama' => $kegiatan->nama,
                            'pagu_indikatif' => $kegiatan->pagu_indikatif,
                            'sub_kegiatan' => $kegiatan->subKegiatan->map(fn (OpdSubKegiatan $subKegiatan) => [
                                'id' => $subKegiatan->id,
                                'kode' => $subKegiatan->kode,
                                'nama' => $subKegiatan->nama,
                                'pagu_indikatif' => $subKegiatan->pagu_indikatif,
                                'indikator' => $subKegiatan->indikator->map(fn (IndikatorSubKegiatan $indikator) => [
                                    'id' => $indikator->id,
                                    'kode' => $indikator->kode,
                                    'indikator' => $indikator->indikator,
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
            'formula' => $indikator->formula,
            'sumber_data' => $indikator->sumber_data,
            'linked' => filled($indikator->{$linkedRelation}?->id),
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
        ];
    }
}
