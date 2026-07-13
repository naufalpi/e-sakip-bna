<?php

namespace App\Http\Controllers\Rpjmd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rpjmd\StoreRpjmdRequest;
use App\Http\Requests\Rpjmd\UpdateRpjmdRequest;
use App\Models\IndikatorProgramRpjmd;
use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorTujuanDaerah;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\ProgramRpjmd;
use App\Models\Rpjmd;
use App\Models\RpjmdMisi;
use App\Models\RpjmdVisi;
use App\Models\SasaranDaerah;
use App\Models\SatuanIndikator;
use App\Models\StrategiDaerah;
use App\Models\TujuanDaerah;
use App\Models\UrusanPemerintahan;
use App\Models\User;
use App\Services\Workflow\WorkflowDataService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RpjmdController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Rpjmd::class);

        $filters = $request->only(['search', 'status']);

        $user = $request->user();

        $rpjmds = Rpjmd::query()
            ->with('periodeTahun:id,tahun,nama,status')
            ->when($this->shouldLimitToUserOpd($user), fn ($query) => $this->limitToUserOpd($query, $user))
            ->when($filters['search'] ?? null, function ($query, string $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('judul', 'ilike', "%{$search}%")
                        ->orWhere('nomor_perda', 'ilike', "%{$search}%");
                });
            })
            ->when($filters['status'] ?? null, fn ($query, string $status) => $query->where('status', $status))
            ->orderByDesc('tahun_awal')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Rpjmd $rpjmd) => [
                'id' => $rpjmd->id,
                'judul' => $rpjmd->judul,
                'nomor_perda' => $rpjmd->nomor_perda,
                'tahun_awal' => $rpjmd->tahun_awal,
                'tahun_akhir' => $rpjmd->tahun_akhir,
                'status' => $rpjmd->status,
                'periode_tahun' => $rpjmd->periodeTahun ? [
                    'id' => $rpjmd->periodeTahun->id,
                    'tahun' => $rpjmd->periodeTahun->tahun,
                    'nama' => $rpjmd->periodeTahun->nama,
                ] : null,
            ]);

        return Inertia::render('Rpjmd/Index', [
            'rpjmds' => $rpjmds,
            'filters' => $filters,
            'can' => [
                'manage' => $request->user()->can('create', Rpjmd::class),
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Rpjmd::class);

        return Inertia::render('Rpjmd/Form', [
            'mode' => 'create',
            'rpjmd' => null,
            'periodeOptions' => $this->periodeOptions(),
        ]);
    }

    public function store(StoreRpjmdRequest $request): RedirectResponse
    {
        $rpjmd = Rpjmd::create([
            ...$request->validated(),
            'status' => 'draft',
        ]);

        return redirect()->route('rpjmd.show', $rpjmd)->with('success', 'RPJMD berhasil ditambahkan.');
    }

    public function show(Request $request, Rpjmd $rpjmd, WorkflowDataService $workflowDataService): Response
    {
        $this->authorize('view', $rpjmd);

        $manage = $request->user()->can('update', $rpjmd);
        $visibleOpdId = $this->shouldLimitToUserOpd($request->user()) ? $request->user()->opd_id : null;
        $withPreview = ! $manage || $request->boolean('with_preview');

        $baseRelations = [
            'periodeTahun:id,tahun,nama,status',
            'visi:id,rpjmd_id,visi,urutan',
            'visi.misi:id,rpjmd_id,rpjmd_visi_id,kode,misi,urutan',
            'visi.tujuan:id,rpjmd_visi_id,rpjmd_misi_id,kode,tujuan,urutan',
            'visi.tujuan.misiTerkait:id,rpjmd_id,rpjmd_visi_id,misi,urutan',
            'visi.tujuan.indikator.satuanIndikator:id,nama,simbol',
            'visi.tujuan.indikator.opd:id,kode,nama,singkatan',
        ];

        $previewRelations = [
            'visi.tujuan.indikator.targets.periodeTahun:id,tahun,nama',
            'visi.tujuan.indikator.targetTriwulan.periodeTahun:id,tahun,nama',
            'visi.tujuan.sasaran.indikatorTujuanTerkait:id,tujuan_daerah_id,indikator,urutan',
            'visi.tujuan.sasaran.indikator.satuanIndikator:id,nama,simbol',
            'visi.tujuan.sasaran.indikator.opd:id,kode,nama,singkatan',
            'visi.tujuan.sasaran.indikator.targets.periodeTahun:id,tahun,nama',
            'visi.tujuan.sasaran.indikator.targetTriwulan.periodeTahun:id,tahun,nama',
            'visi.tujuan.sasaran.indikator.programs.strategi:id,kode,strategi,status',
            'visi.tujuan.sasaran.indikator.programs.urusanPemerintahan:id,kode,nama',
            'visi.tujuan.sasaran.indikator.programs.indikator.satuanIndikator:id,nama,simbol',
            'visi.tujuan.sasaran.indikator.programs.indikator.opd:id,kode,nama,singkatan',
            'visi.tujuan.sasaran.indikator.programs.indikator.targets.periodeTahun:id,tahun,nama',
            'visi.tujuan.sasaran.indikator.programs.indikator.targetTriwulan.periodeTahun:id,tahun,nama',
            'visi.tujuan.sasaran.indikator.programs.opdPenanggungJawab' => fn ($query) => $query->select('opds.id', 'opds.nama', 'opds.singkatan'),
        ];

        $rpjmd->load($withPreview ? array_merge($baseRelations, $previewRelations) : $baseRelations);
        $nodeOptions = $manage ? $this->nodeOptions($rpjmd) : [];

        return Inertia::render('Rpjmd/Show', [
            'rpjmd' => $this->serializeRpjmd($rpjmd, $visibleOpdId, $withPreview),
            'previewLoaded' => $withPreview,
            'nodeOptions' => $nodeOptions,
            'targetTriwulanOptions' => $manage ? $this->targetTriwulanOptions($nodeOptions) : [],
            'periodeOptions' => $manage ? $this->periodeOptions() : [],
            'satuanOptions' => $manage ? $this->satuanOptions() : [],
            'opdOptions' => $manage ? $this->opdOptions() : [],
            'urusanOptions' => $manage ? $this->urusanOptions() : [],
            'can' => [
                'manage' => $manage,
                'review' => $this->canReviewWorkflow($request->user()),
                'lock' => $this->canLockWorkflow($request->user()),
            ],
            'workflow' => $workflowDataService->forModel($rpjmd, 'rpjmd'),
        ]);
    }

    public function edit(Rpjmd $rpjmd): Response
    {
        $this->authorize('update', $rpjmd);

        return Inertia::render('Rpjmd/Form', [
            'mode' => 'edit',
            'rpjmd' => [
                'id' => $rpjmd->id,
                'periode_tahun_id' => $rpjmd->periode_tahun_id,
                'judul' => $rpjmd->judul,
                'nomor_perda' => $rpjmd->nomor_perda,
                'tahun_awal' => $rpjmd->tahun_awal,
                'tahun_akhir' => $rpjmd->tahun_akhir,
                'status' => $rpjmd->status,
                'struktur_tujuan_mode' => $rpjmd->struktur_tujuan_mode,
                'struktur_sasaran_mode' => $rpjmd->struktur_sasaran_mode,
                'keterangan' => $rpjmd->keterangan,
            ],
            'periodeOptions' => $this->periodeOptions(),
        ]);
    }

    public function update(UpdateRpjmdRequest $request, Rpjmd $rpjmd): RedirectResponse
    {
        $rpjmd->update($request->validated());

        return redirect()->route('rpjmd.show', $rpjmd)->with('success', 'RPJMD berhasil diperbarui.');
    }

    public function destroy(Rpjmd $rpjmd): RedirectResponse
    {
        $this->authorize('delete', $rpjmd);

        $rpjmd->delete();

        return redirect()->route('rpjmd.index')->with('success', 'RPJMD berhasil dihapus.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function periodeOptions(): array
    {
        return PeriodeTahun::query()
            ->orderBy('tahun')
            ->get(['id', 'tahun', 'nama', 'status'])
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
     * @return array<int, array<string, mixed>>
     */
    private function opdOptions(): array
    {
        return Opd::query()
            ->where('status', 'active')
            ->orderBy('nama')
            ->get(['id', 'nama', 'singkatan'])
            ->map(fn (Opd $opd) => [
                'id' => $opd->id,
                'label' => $opd->singkatan ? "{$opd->singkatan} - {$opd->nama}" : $opd->nama,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function urusanOptions(): array
    {
        return UrusanPemerintahan::query()
            ->where('status', 'active')
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama'])
            ->map(fn (UrusanPemerintahan $urusan) => [
                'id' => $urusan->id,
                'label' => "{$urusan->kode} - {$urusan->nama}",
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function nodeOptions(Rpjmd $rpjmd): array
    {
        $visi = RpjmdVisi::query()
            ->where('rpjmd_id', $rpjmd->id)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get(['id', 'visi', 'urutan']);
        $misi = RpjmdMisi::query()
            ->where('rpjmd_id', $rpjmd->id)
            ->with('visi:id,visi,urutan')
            ->get(['id', 'rpjmd_visi_id', 'kode', 'misi', 'urutan'])
            ->sortBy(fn ($item) => $this->hierarchySortKey($item->visi?->urutan, $item->urutan, $item->id));
        $tujuan = TujuanDaerah::query()
            ->forRpjmd($rpjmd->id)
            ->with('visi:id,visi,urutan')
            ->get(['id', 'rpjmd_visi_id', 'kode', 'tujuan', 'urutan'])
            ->sortBy(fn ($item) => $this->hierarchySortKey($item->visi?->urutan, $item->urutan, $item->id));
        $indikatorTujuan = IndikatorTujuanDaerah::query()
            ->whereHas('tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))
            ->with('tujuan.visi:id,urutan')
            ->get(['id', 'tujuan_daerah_id', 'kode', 'indikator', 'urutan'])
            ->sortBy(fn ($item) => $this->hierarchySortKey(
                $item->tujuan?->visi?->urutan,
                $item->tujuan?->urutan,
                $item->urutan,
                $item->id,
            ));
        $sasaran = SasaranDaerah::query()
            ->whereHas('tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))
            ->with('tujuan.visi:id,urutan')
            ->get(['id', 'tujuan_daerah_id', 'kode', 'sasaran', 'urutan'])
            ->sortBy(fn ($item) => $this->hierarchySortKey(
                $item->tujuan?->visi?->urutan,
                $item->tujuan?->urutan,
                $item->urutan,
                $item->id,
            ));
        $indikatorSasaran = IndikatorSasaranDaerah::query()
            ->whereHas('sasaran.tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))
            ->with('sasaran.tujuan.visi:id,urutan')
            ->get(['id', 'sasaran_daerah_id', 'kode', 'indikator', 'urutan'])
            ->sortBy(fn ($item) => $this->hierarchySortKey(
                $item->sasaran?->tujuan?->visi?->urutan,
                $item->sasaran?->tujuan?->urutan,
                $item->sasaran?->urutan,
                $item->urutan,
                $item->id,
            ));
        $program = ProgramRpjmd::query()
            ->forRpjmd($rpjmd->id)
            ->with('indikatorSasaran.sasaran.tujuan.visi:id,urutan')
            ->get(['id', 'indikator_sasaran_daerah_id', 'kode', 'nama', 'urutan'])
            ->sortBy(fn ($item) => $this->hierarchySortKey(
                $item->indikatorSasaran?->sasaran?->tujuan?->visi?->urutan,
                $item->indikatorSasaran?->sasaran?->tujuan?->urutan,
                $item->indikatorSasaran?->sasaran?->urutan,
                $item->indikatorSasaran?->urutan,
                $item->urutan,
                $item->id,
            ));
        $indikatorProgram = IndikatorProgramRpjmd::query()
            ->whereHas('program', fn ($query) => $query->forRpjmd($rpjmd->id))
            ->with('program.indikatorSasaran.sasaran.tujuan.visi:id,urutan')
            ->get(['id', 'program_rpjmd_id', 'kode', 'indikator', 'urutan'])
            ->sortBy(fn ($item) => $this->hierarchySortKey(
                $item->program?->indikatorSasaran?->sasaran?->tujuan?->visi?->urutan,
                $item->program?->indikatorSasaran?->sasaran?->tujuan?->urutan,
                $item->program?->indikatorSasaran?->sasaran?->urutan,
                $item->program?->urutan,
                $item->urutan,
                $item->id,
            ));

        return [
            'visi' => $visi
                ->map(fn ($item) => ['id' => $item->id, 'label' => str($item->visi)->limit(90)->toString()])
                ->values()
                ->all(),
            'misi' => $misi
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'label' => $this->nodeLabel($item->kode, $item->misi),
                    'description' => $this->groupLabel('Visi', $item->visi?->visi),
                    'group' => $this->groupLabel('Visi', $item->visi?->visi),
                ])
                ->values()
                ->all(),
            'tujuan' => $tujuan
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'label' => $this->nodeLabel($item->kode, $item->tujuan),
                    'description' => $this->groupLabel('Visi', $item->visi?->visi),
                    'group' => $this->groupLabel('Visi', $item->visi?->visi),
                ])
                ->values()
                ->all(),
            'indikator_tujuan' => $indikatorTujuan
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'label' => $this->nodeLabel($item->kode, $item->indikator),
                    'description' => $this->groupLabel('Tujuan', $item->tujuan?->tujuan),
                    'group' => $this->groupLabel('Tujuan', $item->tujuan?->tujuan),
                ])
                ->values()
                ->all(),
            'sasaran' => $sasaran
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'label' => $this->nodeLabel($item->kode, $item->sasaran),
                    'description' => $this->groupLabel('Tujuan', $item->tujuan?->tujuan),
                    'group' => $this->groupLabel('Tujuan', $item->tujuan?->tujuan),
                ])
                ->values()
                ->all(),
            'indikator_sasaran' => $indikatorSasaran
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'label' => $this->nodeLabel($item->kode, $item->indikator),
                    'description' => $this->groupLabel('Sasaran', $item->sasaran?->sasaran),
                    'group' => $this->groupLabel('Sasaran', $item->sasaran?->sasaran),
                    'sasaran_id' => $item->sasaran_daerah_id,
                ])
                ->values()
                ->all(),
            'strategi' => StrategiDaerah::query()
                ->where('status', 'active')
                ->orderBy('strategi')
                ->get(['id', 'kode', 'strategi'])
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'label' => $this->nodeLabel($item->kode, $item->strategi),
                ])
                ->values()
                ->all(),
            'program' => $program
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'label' => $this->nodeLabel($item->kode, $item->nama),
                    'description' => $this->groupLabel('Sasaran', $item->indikatorSasaran?->sasaran?->sasaran),
                    'group' => $this->groupLabel('Sasaran', $item->indikatorSasaran?->sasaran?->sasaran),
                ])
                ->values()
                ->all(),
            'indikator_program' => $indikatorProgram
                ->map(fn ($item) => [
                    'id' => $item->id,
                    'label' => $this->nodeLabel($item->kode, $item->indikator),
                    'description' => $this->groupLabel('Program', $item->program?->nama),
                    'group' => $this->groupLabel('Program', $item->program?->nama),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function targetTriwulanOptions(array $nodeOptions): array
    {
        return [
            'indikator_tujuan_daerah' => $nodeOptions['indikator_tujuan'] ?? [],
            'indikator_sasaran_daerah' => $nodeOptions['indikator_sasaran'] ?? [],
            'indikator_program_rpjmd' => $nodeOptions['indikator_program'] ?? [],
        ];
    }

    private function nodeLabel(?string $kode, ?string $label): string
    {
        unset($kode);

        return trim(str($label ?? '')->limit(90)->toString());
    }

    private function groupLabel(string $type, ?string $label): ?string
    {
        $label = trim((string) $label);

        return $label === '' ? null : "{$type}: ".str($label)->limit(110)->toString();
    }

    private function hierarchySortKey(?int ...$parts): string
    {
        return collect($parts)
            ->map(fn (?int $part) => str_pad((string) ($part ?? 999999), 6, '0', STR_PAD_LEFT))
            ->implode('.');
    }

    private function shouldLimitToUserOpd(User $user): bool
    {
        return $user->hasRole('admin_opd')
            && ! $user->hasAnyRole([
                'super_admin',
                'admin_kabupaten_bapperida',
                'admin_kabupaten_bagian_organisasi',
                'admin_kabupaten_inspektorat',
                'pimpinan',
            ]);
    }

    private function canReviewWorkflow(User $user): bool
    {
        return $user->hasAnyRole(['super_admin', 'admin_kabupaten_bapperida', 'admin_kabupaten_bagian_organisasi'])
            || $user->hasPermission('lock_period');
    }

    private function canLockWorkflow(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasPermission('lock_period');
    }

    private function limitToUserOpd(Builder $query, User $user): void
    {
        $query->whereHas('visi.tujuan.sasaran.indikator.programs.opdPenanggungJawab', function ($query) use ($user) {
            $query->where('opds.id', $user->opd_id ?? 0);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRpjmd(Rpjmd $rpjmd, ?int $visibleOpdId = null, bool $withPreview = true): array
    {
        return [
            'id' => $rpjmd->id,
            'judul' => $rpjmd->judul,
            'nomor_perda' => $rpjmd->nomor_perda,
            'tahun_awal' => $rpjmd->tahun_awal,
            'tahun_akhir' => $rpjmd->tahun_akhir,
            'status' => $rpjmd->status,
            'struktur_tujuan_mode' => $rpjmd->struktur_tujuan_mode,
            'struktur_sasaran_mode' => $rpjmd->struktur_sasaran_mode,
            'keterangan' => $rpjmd->keterangan,
            'periode_tahun' => $rpjmd->periodeTahun ? [
                'id' => $rpjmd->periodeTahun->id,
                'tahun' => $rpjmd->periodeTahun->tahun,
                'nama' => $rpjmd->periodeTahun->nama,
            ] : null,
            'visi' => $rpjmd->visi->map(fn (RpjmdVisi $visi) => [
                'id' => $visi->id,
                'visi' => $visi->visi,
                'urutan' => $visi->urutan,
                'misi' => $visi->misi->map(fn (RpjmdMisi $misi) => [
                    'id' => $misi->id,
                    'kode' => $misi->kode,
                    'misi' => $misi->misi,
                    'urutan' => $misi->urutan,
                ])->values(),
                'tujuan' => $visi->tujuan->map(fn (TujuanDaerah $tujuan) => [
                    'id' => $tujuan->id,
                    'kode' => $tujuan->kode,
                    'tujuan' => $tujuan->tujuan,
                    'urutan' => $tujuan->urutan,
                    'misi_ids' => $tujuan->misiTerkait->pluck('id')->values(),
                    'misi_terkait' => $tujuan->misiTerkait->map(fn (RpjmdMisi $misi) => [
                        'id' => $misi->id,
                        'misi' => $misi->misi,
                        'urutan' => $misi->urutan,
                    ])->values(),
                    'indikator' => $tujuan->indikator->map(fn (IndikatorTujuanDaerah $indikator) => $this->serializeIndikator($indikator, $withPreview)),
                    'sasaran' => $withPreview
                        ? $tujuan->sasaran->map(fn (SasaranDaerah $sasaran) => $this->serializeSasaran($sasaran, $visibleOpdId))->filter()->values()
                        : collect(),
                ])->filter(fn (array $tujuan) => ! $visibleOpdId || ($tujuan['sasaran']->isNotEmpty() || ! $withPreview))->values(),
            ])->filter(fn (array $visi) => ! $visibleOpdId || $visi['tujuan']->isNotEmpty())->values(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeSasaran(SasaranDaerah $sasaran, ?int $visibleOpdId = null): ?array
    {
        $indikator = $sasaran->indikator
            ->map(fn (IndikatorSasaranDaerah $indikator) => $this->serializeIndikator($indikator, true, $visibleOpdId))
            ->filter(fn (array $indikator) => ! $visibleOpdId || collect($indikator['programs'])->isNotEmpty())
            ->values();

        if ($visibleOpdId && $indikator->isEmpty()) {
            return null;
        }

        return [
            'id' => $sasaran->id,
            'kode' => $sasaran->kode,
            'sasaran' => $sasaran->sasaran,
            'urutan' => $sasaran->urutan,
            'indikator_tujuan_ids' => $sasaran->indikatorTujuanTerkait->pluck('id')->values(),
            'indikator_tujuan_terkait' => $sasaran->indikatorTujuanTerkait->map(fn (IndikatorTujuanDaerah $indikator) => [
                'id' => $indikator->id,
                'indikator' => $indikator->indikator,
                'urutan' => $indikator->urutan,
            ])->values(),
            'indikator' => $indikator,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProgram(ProgramRpjmd $program): array
    {
        return [
            'id' => $program->id,
            'indikator_sasaran_daerah_id' => $program->indikator_sasaran_daerah_id,
            'strategi_daerah_id' => $program->strategi_daerah_id,
            'urusan_pemerintahan_id' => $program->urusan_pemerintahan_id,
            'kode' => $program->kode,
            'nama' => $program->nama,
            'status' => $program->status,
            'urutan' => $program->urutan,
            'strategi' => $program->strategi ? [
                'id' => $program->strategi->id,
                'kode' => $program->strategi->kode,
                'strategi' => $program->strategi->strategi,
            ] : null,
            'urusan_pemerintahan' => $program->urusanPemerintahan ? [
                'kode' => $program->urusanPemerintahan->kode,
                'nama' => $program->urusanPemerintahan->nama,
            ] : null,
            'opd_penanggung_jawab' => $program->opdPenanggungJawab->map(fn (Opd $opd) => [
                'pivot_id' => $opd->pivot->id,
                'id' => $opd->id,
                'nama' => $opd->nama,
                'singkatan' => $opd->singkatan,
                'peran' => $opd->pivot->peran,
                'is_utama' => (bool) $opd->pivot->is_utama,
            ]),
            'indikator' => $program->indikator->map(fn (IndikatorProgramRpjmd $indikator) => $this->serializeIndikator($indikator)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeIndikator(
        IndikatorTujuanDaerah|IndikatorSasaranDaerah|IndikatorProgramRpjmd $indikator,
        bool $withTargets = true,
        ?int $visibleOpdId = null,
    ): array {
        $programs = $indikator instanceof IndikatorSasaranDaerah
            ? $indikator->programs
                ->when($visibleOpdId, fn ($programs) => $programs->filter(
                    fn (ProgramRpjmd $program) => $program->opdPenanggungJawab->contains('id', $visibleOpdId)
                ))
                ->map(fn (ProgramRpjmd $program) => $this->serializeProgram($program))
                ->values()
            : collect();

        return [
            'id' => $indikator->id,
            'kode' => $indikator->kode,
            'indikator' => $indikator->indikator,
            'satuan_indikator_id' => $indikator->satuan_indikator_id,
            'opd_id' => $indikator->opd_id,
            'definisi_operasional' => $indikator->definisi_operasional,
            'alasan_pemilihan' => $indikator->alasan_pemilihan,
            'formulasi_pengukuran' => $indikator->formulasi_pengukuran,
            'tipe_perhitungan' => $indikator->tipe_perhitungan,
            'sumber_data' => $indikator->sumber_data,
            'urutan' => $indikator->urutan,
            'satuan' => $indikator->satuanIndikator ? [
                'nama' => $indikator->satuanIndikator->nama,
                'simbol' => $indikator->satuanIndikator->simbol,
            ] : null,
            'opd' => $indikator->opd ? [
                'id' => $indikator->opd->id,
                'kode' => $indikator->opd->kode,
                'nama' => $indikator->opd->nama,
                'singkatan' => $indikator->opd->singkatan,
            ] : null,
            'targets' => $withTargets ? $indikator->targets->map(fn ($target) => [
                'id' => $target->id,
                'periode_tahun' => [
                    'id' => $target->periodeTahun->id,
                    'tahun' => $target->periodeTahun->tahun,
                    'nama' => $target->periodeTahun->nama,
                ],
                'target' => $target->target,
                'target_text' => $target->target_text,
            ]) : collect(),
            'target_triwulan' => $withTargets ? $indikator->targetTriwulan->map(fn ($target) => [
                'id' => $target->id,
                'periode_tahun' => [
                    'id' => $target->periodeTahun->id,
                    'tahun' => $target->periodeTahun->tahun,
                    'nama' => $target->periodeTahun->nama,
                ],
                'triwulan' => $target->triwulan,
                'target_angka' => $target->target_angka,
                'target_text' => $target->target_text,
            ]) : collect(),
            'programs' => $programs,
        ];
    }
}
