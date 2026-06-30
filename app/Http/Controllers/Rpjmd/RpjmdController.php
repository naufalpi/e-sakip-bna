<?php

namespace App\Http\Controllers\Rpjmd;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rpjmd\StoreRpjmdRequest;
use App\Http\Requests\Rpjmd\UpdateRpjmdRequest;
use App\Models\ImportBatch;
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
                'import' => $request->user()->can('create', Rpjmd::class),
            ],
            'recentImports' => $request->user()->can('create', Rpjmd::class) ? $this->recentImports() : [],
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
        $rpjmd = Rpjmd::create($request->validated());

        return redirect()->route('rpjmd.show', $rpjmd)->with('success', 'RPJMD berhasil ditambahkan.');
    }

    public function show(Request $request, Rpjmd $rpjmd, WorkflowDataService $workflowDataService): Response
    {
        $this->authorize('view', $rpjmd);

        $manage = $request->user()->can('update', $rpjmd);
        $visibleOpdId = $this->shouldLimitToUserOpd($request->user()) ? $request->user()->opd_id : null;

        $rpjmd->load([
            'periodeTahun:id,tahun,nama,status',
            'visi.misi',
            'visi.tujuan.indikator.satuanIndikator:id,nama,simbol',
            'visi.tujuan.indikator.targets.periodeTahun:id,tahun,nama',
            'visi.tujuan.indikator.targetTriwulan.periodeTahun:id,tahun,nama',
            'visi.tujuan.sasaran.indikator.satuanIndikator:id,nama,simbol',
            'visi.tujuan.sasaran.indikator.targets.periodeTahun:id,tahun,nama',
            'visi.tujuan.sasaran.indikator.targetTriwulan.periodeTahun:id,tahun,nama',
            'visi.tujuan.sasaran.strategi.programs.urusanPemerintahan:id,kode,nama',
            'visi.tujuan.sasaran.strategi.programs.indikator.satuanIndikator:id,nama,simbol',
            'visi.tujuan.sasaran.strategi.programs.indikator.targets.periodeTahun:id,tahun,nama',
            'visi.tujuan.sasaran.strategi.programs.indikator.targetTriwulan.periodeTahun:id,tahun,nama',
            'visi.tujuan.sasaran.strategi.programs.opdPenanggungJawab' => fn ($query) => $query->select('opds.id', 'opds.nama', 'opds.singkatan'),
        ]);

        return Inertia::render('Rpjmd/Show', [
            'rpjmd' => $this->serializeRpjmd($rpjmd, $visibleOpdId),
            'nodeOptions' => $manage ? $this->nodeOptions($rpjmd) : [],
            'targetTriwulanOptions' => $manage ? $this->targetTriwulanOptions($rpjmd) : [],
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
        return [
            'visi' => RpjmdVisi::query()->where('rpjmd_id', $rpjmd->id)->orderBy('urutan')->get(['id', 'visi'])->map(fn ($item) => ['id' => $item->id, 'label' => str($item->visi)->limit(90)->toString()])->values()->all(),
            'misi' => RpjmdMisi::query()->where('rpjmd_id', $rpjmd->id)->orderBy('urutan')->get(['id', 'kode', 'misi'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->misi)])->values()->all(),
            'tujuan' => TujuanDaerah::query()->forRpjmd($rpjmd->id)->orderBy('urutan')->get(['id', 'kode', 'tujuan'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->tujuan)])->values()->all(),
            'indikator_tujuan' => IndikatorTujuanDaerah::query()->whereHas('tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))->orderBy('urutan')->get(['id', 'kode', 'indikator'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all(),
            'sasaran' => SasaranDaerah::query()->whereHas('tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))->orderBy('urutan')->get(['id', 'kode', 'sasaran'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->sasaran)])->values()->all(),
            'indikator_sasaran' => IndikatorSasaranDaerah::query()->whereHas('sasaran.tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))->orderBy('urutan')->get(['id', 'kode', 'indikator'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all(),
            'strategi' => StrategiDaerah::query()->whereHas('sasaran.tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))->orderBy('urutan')->get(['id', 'kode', 'strategi'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->strategi)])->values()->all(),
            'program' => ProgramRpjmd::query()->whereHas('strategi.sasaran.tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))->orderBy('urutan')->get(['id', 'kode', 'nama'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->nama)])->values()->all(),
            'indikator_program' => IndikatorProgramRpjmd::query()->whereHas('program.strategi.sasaran.tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))->orderBy('urutan')->get(['id', 'kode', 'indikator'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all(),
        ];
    }

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function targetTriwulanOptions(Rpjmd $rpjmd): array
    {
        return [
            'indikator_tujuan_daerah' => IndikatorTujuanDaerah::query()
                ->whereHas('tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))
                ->orderBy('urutan')
                ->get(['id', 'kode', 'indikator'])
                ->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])
                ->values()
                ->all(),
            'indikator_sasaran_daerah' => IndikatorSasaranDaerah::query()
                ->whereHas('sasaran.tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))
                ->orderBy('urutan')
                ->get(['id', 'kode', 'indikator'])
                ->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])
                ->values()
                ->all(),
            'indikator_program_rpjmd' => IndikatorProgramRpjmd::query()
                ->whereHas('program.strategi.sasaran.tujuan', fn ($query) => $query->forRpjmd($rpjmd->id))
                ->orderBy('urutan')
                ->get(['id', 'kode', 'indikator'])
                ->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])
                ->values()
                ->all(),
        ];
    }

    private function nodeLabel(?string $kode, ?string $label): string
    {
        unset($kode);

        return trim(str($label ?? '')->limit(90)->toString());
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
        $query->whereHas('visi.tujuan.sasaran.strategi.programs.opdPenanggungJawab', function ($query) use ($user) {
            $query->where('opds.id', $user->opd_id ?? 0);
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentImports(): array
    {
        return ImportBatch::query()
            ->with('uploadedBy:id,name')
            ->where('module', 'rpjmd')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (ImportBatch $batch) => [
                'id' => $batch->id,
                'status' => $batch->status,
                'original_filename' => $batch->original_filename,
                'total_rows' => $batch->total_rows,
                'preview_rows' => $batch->preview_rows,
                'created_at' => $batch->created_at?->toISOString(),
                'uploaded_by' => $batch->uploadedBy?->name,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRpjmd(Rpjmd $rpjmd, ?int $visibleOpdId = null): array
    {
        return [
            'id' => $rpjmd->id,
            'judul' => $rpjmd->judul,
            'nomor_perda' => $rpjmd->nomor_perda,
            'tahun_awal' => $rpjmd->tahun_awal,
            'tahun_akhir' => $rpjmd->tahun_akhir,
            'status' => $rpjmd->status,
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
                    'indikator' => $tujuan->indikator->map(fn (IndikatorTujuanDaerah $indikator) => $this->serializeIndikator($indikator)),
                    'sasaran' => $tujuan->sasaran->map(fn (SasaranDaerah $sasaran) => $this->serializeSasaran($sasaran, $visibleOpdId))->filter()->values(),
                ])->filter(fn (array $tujuan) => ! $visibleOpdId || $tujuan['sasaran']->isNotEmpty())->values(),
            ])->filter(fn (array $visi) => ! $visibleOpdId || $visi['tujuan']->isNotEmpty())->values(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeSasaran(SasaranDaerah $sasaran, ?int $visibleOpdId = null): ?array
    {
        $strategi = $sasaran->strategi
            ->map(fn (StrategiDaerah $strategi) => $this->serializeStrategi($strategi, $visibleOpdId))
            ->filter()
            ->values();

        if ($visibleOpdId && $strategi->isEmpty()) {
            return null;
        }

        return [
            'id' => $sasaran->id,
            'kode' => $sasaran->kode,
            'sasaran' => $sasaran->sasaran,
            'urutan' => $sasaran->urutan,
            'indikator' => $sasaran->indikator->map(fn (IndikatorSasaranDaerah $indikator) => $this->serializeIndikator($indikator)),
            'strategi' => $strategi,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function serializeStrategi(StrategiDaerah $strategi, ?int $visibleOpdId = null): ?array
    {
        $programs = $strategi->programs
            ->when($visibleOpdId, fn ($programs) => $programs->filter(
                fn (ProgramRpjmd $program) => $program->opdPenanggungJawab->contains('id', $visibleOpdId)
            ))
            ->values();

        if ($visibleOpdId && $programs->isEmpty()) {
            return null;
        }

        return [
            'id' => $strategi->id,
            'kode' => $strategi->kode,
            'strategi' => $strategi->strategi,
            'arah_kebijakan' => $strategi->arah_kebijakan,
            'urutan' => $strategi->urutan,
            'programs' => $programs->map(fn (ProgramRpjmd $program) => [
                'id' => $program->id,
                'urusan_pemerintahan_id' => $program->urusan_pemerintahan_id,
                'kode' => $program->kode,
                'nama' => $program->nama,
                'pagu_indikatif' => $program->pagu_indikatif,
                'status' => $program->status,
                'urutan' => $program->urutan,
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
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeIndikator(IndikatorTujuanDaerah|IndikatorSasaranDaerah|IndikatorProgramRpjmd $indikator): array
    {
        return [
            'id' => $indikator->id,
            'kode' => $indikator->kode,
            'indikator' => $indikator->indikator,
            'tipe_indikator' => $indikator->tipe_indikator,
            'satuan_indikator_id' => $indikator->satuan_indikator_id,
            'formula' => $indikator->formula,
            'sumber_data' => $indikator->sumber_data,
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
            'target_triwulan' => $indikator->targetTriwulan->map(fn ($target) => [
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
            ]),
        ];
    }
}
