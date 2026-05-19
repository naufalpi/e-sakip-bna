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

        $rpjmds = Rpjmd::query()
            ->with('periodeTahun:id,tahun,nama,status')
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
        $rpjmd = Rpjmd::create($request->validated());

        return redirect()->route('rpjmd.show', $rpjmd)->with('success', 'RPJMD berhasil ditambahkan.');
    }

    public function show(Request $request, Rpjmd $rpjmd): Response
    {
        $this->authorize('view', $rpjmd);

        $rpjmd->load([
            'periodeTahun:id,tahun,nama,status',
            'visi.misi.tujuan.indikator.satuanIndikator:id,nama,simbol',
            'visi.misi.tujuan.indikator.targets.periodeTahun:id,tahun,nama',
            'visi.misi.tujuan.sasaran.indikator.satuanIndikator:id,nama,simbol',
            'visi.misi.tujuan.sasaran.indikator.targets.periodeTahun:id,tahun,nama',
            'visi.misi.tujuan.sasaran.strategi.programs.urusanPemerintahan:id,kode,nama',
            'visi.misi.tujuan.sasaran.strategi.programs.indikator.satuanIndikator:id,nama,simbol',
            'visi.misi.tujuan.sasaran.strategi.programs.indikator.targets.periodeTahun:id,tahun,nama',
            'visi.misi.tujuan.sasaran.strategi.programs.opdPenanggungJawab' => fn ($query) => $query->select('opds.id', 'opds.nama', 'opds.singkatan'),
        ]);

        return Inertia::render('Rpjmd/Show', [
            'rpjmd' => $this->serializeRpjmd($rpjmd),
            'nodeOptions' => $this->nodeOptions($rpjmd),
            'periodeOptions' => $this->periodeOptions(),
            'satuanOptions' => $this->satuanOptions(),
            'opdOptions' => $this->opdOptions(),
            'urusanOptions' => $this->urusanOptions(),
            'can' => [
                'manage' => $request->user()->can('update', $rpjmd),
            ],
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

    public function importPlaceholder(Request $request): RedirectResponse
    {
        $this->authorize('create', Rpjmd::class);

        $request->validate([
            'file' => ['nullable', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        return back()->with('success', 'Placeholder import Excel RPJMD sudah tersedia. Implementasi parsing detail akan dikerjakan pada tahap lanjutan.');
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
            'tujuan' => TujuanDaerah::query()->whereHas('misi', fn ($query) => $query->where('rpjmd_id', $rpjmd->id))->orderBy('urutan')->get(['id', 'kode', 'tujuan'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->tujuan)])->values()->all(),
            'indikator_tujuan' => IndikatorTujuanDaerah::query()->whereHas('tujuan.misi', fn ($query) => $query->where('rpjmd_id', $rpjmd->id))->orderBy('urutan')->get(['id', 'kode', 'indikator'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all(),
            'sasaran' => SasaranDaerah::query()->whereHas('tujuan.misi', fn ($query) => $query->where('rpjmd_id', $rpjmd->id))->orderBy('urutan')->get(['id', 'kode', 'sasaran'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->sasaran)])->values()->all(),
            'indikator_sasaran' => IndikatorSasaranDaerah::query()->whereHas('sasaran.tujuan.misi', fn ($query) => $query->where('rpjmd_id', $rpjmd->id))->orderBy('urutan')->get(['id', 'kode', 'indikator'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all(),
            'strategi' => StrategiDaerah::query()->whereHas('sasaran.tujuan.misi', fn ($query) => $query->where('rpjmd_id', $rpjmd->id))->orderBy('urutan')->get(['id', 'kode', 'strategi'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->strategi)])->values()->all(),
            'program' => ProgramRpjmd::query()->whereHas('strategi.sasaran.tujuan.misi', fn ($query) => $query->where('rpjmd_id', $rpjmd->id))->orderBy('urutan')->get(['id', 'kode', 'nama'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->nama)])->values()->all(),
            'indikator_program' => IndikatorProgramRpjmd::query()->whereHas('program.strategi.sasaran.tujuan.misi', fn ($query) => $query->where('rpjmd_id', $rpjmd->id))->orderBy('urutan')->get(['id', 'kode', 'indikator'])->map(fn ($item) => ['id' => $item->id, 'label' => $this->nodeLabel($item->kode, $item->indikator)])->values()->all(),
        ];
    }

    private function nodeLabel(?string $kode, ?string $label): string
    {
        return trim(($kode ? "{$kode} - " : '').str($label ?? '')->limit(90)->toString());
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRpjmd(Rpjmd $rpjmd): array
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
                    'tujuan' => $misi->tujuan->map(fn (TujuanDaerah $tujuan) => [
                        'id' => $tujuan->id,
                        'kode' => $tujuan->kode,
                        'tujuan' => $tujuan->tujuan,
                        'indikator' => $tujuan->indikator->map(fn (IndikatorTujuanDaerah $indikator) => $this->serializeIndikator($indikator)),
                        'sasaran' => $tujuan->sasaran->map(fn (SasaranDaerah $sasaran) => [
                            'id' => $sasaran->id,
                            'kode' => $sasaran->kode,
                            'sasaran' => $sasaran->sasaran,
                            'indikator' => $sasaran->indikator->map(fn (IndikatorSasaranDaerah $indikator) => $this->serializeIndikator($indikator)),
                            'strategi' => $sasaran->strategi->map(fn (StrategiDaerah $strategi) => [
                                'id' => $strategi->id,
                                'kode' => $strategi->kode,
                                'strategi' => $strategi->strategi,
                                'arah_kebijakan' => $strategi->arah_kebijakan,
                                'programs' => $strategi->programs->map(fn (ProgramRpjmd $program) => [
                                    'id' => $program->id,
                                    'kode' => $program->kode,
                                    'nama' => $program->nama,
                                    'pagu_indikatif' => $program->pagu_indikatif,
                                    'status' => $program->status,
                                    'urusan_pemerintahan' => $program->urusanPemerintahan ? [
                                        'kode' => $program->urusanPemerintahan->kode,
                                        'nama' => $program->urusanPemerintahan->nama,
                                    ] : null,
                                    'opd_penanggung_jawab' => $program->opdPenanggungJawab->map(fn (Opd $opd) => [
                                        'id' => $opd->id,
                                        'nama' => $opd->nama,
                                        'singkatan' => $opd->singkatan,
                                        'peran' => $opd->pivot->peran,
                                        'is_utama' => (bool) $opd->pivot->is_utama,
                                    ]),
                                    'indikator' => $program->indikator->map(fn (IndikatorProgramRpjmd $indikator) => $this->serializeIndikator($indikator)),
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
    private function serializeIndikator(IndikatorTujuanDaerah|IndikatorSasaranDaerah|IndikatorProgramRpjmd $indikator): array
    {
        return [
            'id' => $indikator->id,
            'kode' => $indikator->kode,
            'indikator' => $indikator->indikator,
            'formula' => $indikator->formula,
            'sumber_data' => $indikator->sumber_data,
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
