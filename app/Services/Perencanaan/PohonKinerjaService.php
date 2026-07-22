<?php

namespace App\Services\Perencanaan;

use App\Models\IndikatorOpdProgram;
use App\Models\IndikatorSasaranDaerah;
use App\Models\IndikatorSasaranOpd;
use App\Models\IndikatorTujuanOpd;
use App\Models\Opd;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\ProgramRpjmd;
use App\Models\RenstraOpd;
use App\Models\Rpjmd;
use App\Models\RpjmdMisi;
use App\Models\RpjmdVisi;
use App\Models\SasaranDaerah;
use App\Models\SasaranOpd;
use App\Models\TujuanDaerah;
use App\Models\TujuanOpd;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class PohonKinerjaService
{
    /**
     * @return array<string, mixed>
     */
    public function rpjmdTree(Rpjmd $rpjmd, ?int $visibleOpdId = null): array
    {
        $rpjmd->loadMissing([
            'periodeTahun:id,tahun,nama',
            'visi.misi',
            'visi.tujuan.misiTerkait:id,rpjmd_id,rpjmd_visi_id,kode,misi,urutan',
            'visi.tujuan.indikator.satuanIndikator:id,nama,simbol',
            'visi.tujuan.indikator.targets.periodeTahun:id,tahun,nama',
            'visi.tujuan.indikator.targetTriwulan.periodeTahun:id,tahun,nama',
            'visi.tujuan.sasaran.indikator.satuanIndikator:id,nama,simbol',
            'visi.tujuan.sasaran.indikatorTujuanTerkait:id,tujuan_daerah_id,kode,indikator,urutan',
            'visi.tujuan.sasaran.indikator.targets.periodeTahun:id,tahun,nama',
            'visi.tujuan.sasaran.indikator.targetTriwulan.periodeTahun:id,tahun,nama',
            'visi.tujuan.sasaran.programs.strategi:id,kode,strategi',
            'visi.tujuan.sasaran.programs.urusanPemerintahan:id,kode,nama',
            'visi.tujuan.sasaran.programs.opdPenanggungJawab:id,kode,nama,singkatan',
            'visi.tujuan.sasaran.programs.indikator.satuanIndikator:id,nama,simbol',
            'visi.tujuan.sasaran.programs.indikator.targets.periodeTahun:id,tahun,nama',
            'visi.tujuan.sasaran.programs.indikator.targetTriwulan.periodeTahun:id,tahun,nama',
        ]);

        return $this->node(
            type: 'rpjmd',
            id: $rpjmd->id,
            label: "{$rpjmd->tahun_awal}-{$rpjmd->tahun_akhir} - {$rpjmd->judul}",
            meta: [
                'status' => $rpjmd->status,
                'periode' => $rpjmd->periodeTahun?->nama,
                'nomor_perda' => $rpjmd->nomor_perda,
                'struktur_tujuan_mode' => $rpjmd->struktur_tujuan_mode,
                'struktur_sasaran_mode' => $rpjmd->struktur_sasaran_mode,
            ],
            children: $rpjmd->visi
                ->map(fn (RpjmdVisi $visi) => $this->visiNode($visi, $visibleOpdId))
                ->filter()
                ->values()
                ->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function renstraTree(RenstraOpd $renstra): array
    {
        $this->loadRenstraTree($renstra);

        return $this->node(
            type: 'renstra_opd',
            id: $renstra->id,
            label: "{$renstra->tahun_awal}-{$renstra->tahun_akhir} - {$renstra->judul}",
            meta: [
                'status' => $renstra->status,
                'opd' => $renstra->opd?->singkatan ?: $renstra->opd?->nama,
                'periode' => $renstra->periodeTahun?->nama,
                'rpjmd' => $renstra->rpjmd ? "{$renstra->rpjmd->tahun_awal}-{$renstra->rpjmd->tahun_akhir}" : null,
            ],
            children: $renstra->tujuan
                ->map(fn (TujuanOpd $tujuan) => $this->tujuanOpdNode($tujuan))
                ->values()
                ->all(),
            linkedTo: $renstra->rpjmd ? $this->linkedReference('rpjmd', $renstra->rpjmd->id, $renstra->rpjmd->judul) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function cascadingOpdToRpjmdTree(RenstraOpd $renstra): array
    {
        $this->loadRenstraTree($renstra);

        return $this->node(
            type: 'cascading_opd_rpjmd',
            id: $renstra->id,
            label: 'Cascading OPD ke RPJMD',
            meta: [
                'opd' => $renstra->opd?->singkatan ?: $renstra->opd?->nama,
                'renstra' => $renstra->judul,
                'rpjmd' => $renstra->rpjmd ? "{$renstra->rpjmd->tahun_awal}-{$renstra->rpjmd->tahun_akhir} - {$renstra->rpjmd->judul}" : null,
            ],
            children: [
                $this->renstraTree($renstra),
                $renstra->rpjmd ? $this->rpjmdTree($renstra->rpjmd, $renstra->opd_id) : null,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $tree
     * @return array<string, mixed>
     */
    public function stats(array $tree): array
    {
        $flat = collect($this->flatten($tree));

        return [
            'total_nodes' => $flat->count(),
            'indicator_nodes' => $flat->whereIn('type', [
                'indikator_tujuan_daerah',
                'indikator_sasaran_daerah',
                'indikator_program_rpjmd',
                'indikator_tujuan_opd',
                'indikator_sasaran_opd',
                'indikator_opd_program',
                'indikator_sub_kegiatan',
            ])->count(),
            'target_tahunan_nodes' => $flat->where('type', 'target_tahunan')->count(),
            'target_triwulan_nodes' => $flat->where('type', 'target_triwulan')->count(),
            'linked_nodes' => $flat->filter(fn (array $node) => filled($node['linked_to'] ?? null))->count(),
            'incomplete_nodes' => $flat->filter(fn (array $node) => ($node['meta']['kelengkapan_status'] ?? null) === 'perlu_dilengkapi')->count(),
            'opd_penanggung_jawab_nodes' => $flat->where('type', 'opd_penanggung_jawab')->count(),
            'total_pagu' => $flat->sum(fn (array $node) => (float) ($node['meta']['pagu_indikatif'] ?? $node['meta']['pagu'] ?? 0)),
            'total_target_anggaran_triwulan' => $flat->where('type', 'target_triwulan')->sum(fn (array $node) => (float) ($node['meta']['target_anggaran'] ?? 0)),
        ];
    }

    private function loadRenstraTree(RenstraOpd $renstra): void
    {
        $renstra->loadMissing([
            'opd:id,kode,nama,singkatan',
            'rpjmd:id,judul,tahun_awal,tahun_akhir,status',
            'periodeTahun:id,tahun,nama',
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
            'tujuan.sasaran.programs.programRpjmd:id,kode,nama',
            'tujuan.sasaran.programs.indikator.indikatorProgramRpjmd:id,kode,indikator',
            'tujuan.sasaran.programs.indikator.satuanIndikator:id,nama,simbol',
            'tujuan.sasaran.programs.indikator.targets.periodeTahun:id,tahun,nama',
            'tujuan.sasaran.programs.indikator.targetTriwulan.periodeTahun:id,tahun,nama',
            'tujuan.sasaran.programs.kegiatan.subKegiatan.indikator.satuanIndikator:id,nama,simbol',
            'tujuan.sasaran.programs.kegiatan.subKegiatan.indikator.targetTriwulan.periodeTahun:id,tahun,nama',
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function visiNode(RpjmdVisi $visi, ?int $visibleOpdId): ?array
    {
        $misiChildren = $visi->misi
            ->map(fn (RpjmdMisi $misi) => $this->misiNode($misi, $visibleOpdId))
            ->filter()
            ->values()
            ->all();

        $tujuanChildren = $visi->tujuan
            ->map(fn (TujuanDaerah $tujuan) => $this->tujuanDaerahNode($tujuan, $visibleOpdId))
            ->filter()
            ->values()
            ->all();

        $children = [...$misiChildren, ...$tujuanChildren];

        if ($visibleOpdId && count($children) === 0) {
            return null;
        }

        return $this->node('visi', $visi->id, $visi->visi, children: $children);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function misiNode(RpjmdMisi $misi, ?int $visibleOpdId): ?array
    {
        if ($visibleOpdId) {
            return null;
        }

        return $this->node(
            type: 'misi',
            id: $misi->id,
            label: $this->label($misi->kode, $misi->misi),
            meta: ['catatan' => 'Misi RPJMD dicatat sebagai pernyataan arah kebijakan tanpa turunan langsung.'],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function tujuanDaerahNode(TujuanDaerah $tujuan, ?int $visibleOpdId): ?array
    {
        $sasaranChildren = $tujuan->sasaran
            ->map(fn (SasaranDaerah $sasaran) => $this->sasaranDaerahNode($sasaran, $visibleOpdId))
            ->filter()
            ->values();

        if ($visibleOpdId && $sasaranChildren->isEmpty()) {
            return null;
        }

        return $this->node(
            type: 'tujuan_daerah',
            id: $tujuan->id,
            label: $this->label($tujuan->kode, $tujuan->tujuan),
            meta: [
                'misi_ids' => $tujuan->misiTerkait->pluck('id')->values()->all(),
            ],
            children: [
                ...$this->indicatorNodes($tujuan->indikator, 'indikator_tujuan_daerah'),
                ...$sasaranChildren->all(),
            ],
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sasaranDaerahNode(SasaranDaerah $sasaran, ?int $visibleOpdId): ?array
    {
        $indikatorChildren = $sasaran->indikator
            ->map(fn (IndikatorSasaranDaerah $indikator) => $this->indicatorNode($indikator, 'indikator_sasaran_daerah'))
            ->filter()
            ->values();
        $programChildren = $sasaran->programs
            ->when($visibleOpdId, fn (Collection $programs) => $programs->filter(
                fn (ProgramRpjmd $program) => $program->isRelevantForOpd($visibleOpdId)
            ))
            ->map(fn (ProgramRpjmd $program) => $this->programRpjmdNode($program))
            ->values();

        if ($visibleOpdId && $programChildren->isEmpty()) {
            return null;
        }

        return $this->node(
            type: 'sasaran_daerah',
            id: $sasaran->id,
            label: $this->label($sasaran->kode, $sasaran->sasaran),
            meta: [
                'indikator_tujuan_ids' => $sasaran->indikatorTujuanTerkait->pluck('id')->values()->all(),
            ],
            children: [
                ...$indikatorChildren->all(),
                ...$programChildren->all(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function programRpjmdNode(ProgramRpjmd $program): array
    {
        return $this->node(
            type: 'program_rpjmd',
            id: $program->id,
            label: $this->label($program->kode, $program->nama),
            meta: [
                'status' => $program->status,
                'urusan' => $program->urusanPemerintahan ? $this->label($program->urusanPemerintahan->kode, $program->urusanPemerintahan->nama) : null,
                'strategi' => $program->strategi ? $this->label($program->strategi->kode, $program->strategi->strategi) : null,
            ],
            children: [
                ...$this->indicatorNodes($program->indikator, 'indikator_program_rpjmd'),
                ...$program->opdPenanggungJawab
                    ->map(fn (Opd $opd) => $this->opdPenanggungJawabNode($program, $opd))
                    ->values()
                    ->all(),
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function tujuanOpdNode(TujuanOpd $tujuan): array
    {
        return $this->node(
            type: 'tujuan_opd',
            id: $tujuan->id,
            label: $this->label($tujuan->kode, $tujuan->tujuan),
            children: [
                ...$this->indicatorNodes($tujuan->indikator, 'indikator_tujuan_opd', fn (IndikatorTujuanOpd $indikator) => $indikator->indikatorTujuanDaerah ? $this->linkedReference('indikator_tujuan_daerah', $indikator->indikatorTujuanDaerah->id, $indikator->indikatorTujuanDaerah->indikator) : null),
                ...$tujuan->sasaran->map(fn (SasaranOpd $sasaran) => $this->sasaranOpdNode($sasaran))->values()->all(),
            ],
            linkedTo: $tujuan->tujuanDaerah ? $this->linkedReference('tujuan_daerah', $tujuan->tujuanDaerah->id, $tujuan->tujuanDaerah->tujuan) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function sasaranOpdNode(SasaranOpd $sasaran): array
    {
        return $this->node(
            type: 'sasaran_opd',
            id: $sasaran->id,
            label: $this->label($sasaran->kode, $sasaran->sasaran),
            children: [
                ...$this->indicatorNodes($sasaran->indikator, 'indikator_sasaran_opd', fn (IndikatorSasaranOpd $indikator) => $indikator->indikatorSasaranDaerah ? $this->linkedReference('indikator_sasaran_daerah', $indikator->indikatorSasaranDaerah->id, $indikator->indikatorSasaranDaerah->indikator) : null),
                ...$sasaran->programs->map(fn (OpdProgram $program) => $this->opdProgramNode($program))->values()->all(),
            ],
            linkedTo: $sasaran->sasaranDaerah ? $this->linkedReference('sasaran_daerah', $sasaran->sasaranDaerah->id, $sasaran->sasaranDaerah->sasaran) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function opdProgramNode(OpdProgram $program): array
    {
        return $this->node(
            type: 'opd_program',
            id: $program->id,
            label: $this->label($program->kode, $program->nama),
            meta: [
                'status' => $program->status,
                'pagu_indikatif' => $program->pagu_indikatif,
            ],
            children: [
                ...$this->indicatorNodes($program->indikator, 'indikator_opd_program', fn (IndikatorOpdProgram $indikator) => $indikator->indikatorProgramRpjmd ? $this->linkedReference('indikator_program_rpjmd', $indikator->indikatorProgramRpjmd->id, $indikator->indikatorProgramRpjmd->indikator) : null),
                ...$program->kegiatan->map(fn (OpdKegiatan $kegiatan) => $this->opdKegiatanNode($kegiatan))->values()->all(),
            ],
            linkedTo: $program->programRpjmd ? $this->linkedReference('program_rpjmd', $program->programRpjmd->id, $program->programRpjmd->nama) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function opdKegiatanNode(OpdKegiatan $kegiatan): array
    {
        return $this->node(
            type: 'opd_kegiatan',
            id: $kegiatan->id,
            label: $this->label($kegiatan->kode, $kegiatan->nama),
            meta: ['pagu_indikatif' => $kegiatan->pagu_indikatif],
            children: $kegiatan->subKegiatan
                ->map(fn (OpdSubKegiatan $subKegiatan) => $this->opdSubKegiatanNode($subKegiatan))
                ->values()
                ->all(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function opdSubKegiatanNode(OpdSubKegiatan $subKegiatan): array
    {
        return $this->node(
            type: 'opd_sub_kegiatan',
            id: $subKegiatan->id,
            label: $this->label($subKegiatan->kode, $subKegiatan->nama),
            meta: ['pagu_indikatif' => $subKegiatan->pagu_indikatif],
            children: $this->indicatorNodes($subKegiatan->indikator, 'indikator_sub_kegiatan'),
        );
    }

    /**
     * @param  Collection<int, Model>  $indicators
     * @return array<int, array<string, mixed>>
     */
    private function indicatorNodes(Collection $indicators, string $type, ?callable $linkedResolver = null): array
    {
        return $indicators
            ->map(fn (Model $indicator) => $this->indicatorNode($indicator, $type, $linkedResolver ? $linkedResolver($indicator) : null))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function indicatorNode(Model $indicator, string $type, ?array $linkedTo = null, array $extraChildren = []): array
    {
        return $this->node(
            type: $type,
            id: $indicator->getKey(),
            label: $this->label($indicator->getAttribute('kode'), $indicator->getAttribute('indikator')),
            meta: [
                'satuan' => $indicator->satuanIndikator?->simbol ?: $indicator->satuanIndikator?->nama,
                'tipe_indikator' => $indicator->getAttribute('tipe_indikator'),
                'formula' => $indicator->getAttribute('formula'),
                'definisi_operasional' => $indicator->getAttribute('definisi_operasional'),
                'alasan_pemilihan' => $indicator->getAttribute('alasan_pemilihan'),
                'formulasi_pengukuran' => $indicator->getAttribute('formulasi_pengukuran'),
                'tipe_perhitungan' => $indicator->getAttribute('tipe_perhitungan'),
                'sumber_data' => $indicator->getAttribute('sumber_data'),
                'opd' => $indicator->getRelationValue('opd')?->singkatan ?: $indicator->getRelationValue('opd')?->nama,
            ],
            children: [
                ...$this->targetTahunanNodes($indicator),
                ...$this->targetTriwulanNodes($indicator),
                ...$extraChildren,
            ],
            linkedTo: $linkedTo,
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function targetTahunanNodes(Model $indicator): array
    {
        if (! method_exists($indicator, 'targets')) {
            return [];
        }

        return $indicator->targets
            ->map(fn (Model $target) => $this->node(
                type: 'target_tahunan',
                id: $target->getKey(),
                label: 'Target '.$target->periodeTahun?->tahun.': '.$this->targetDisplay($target),
                meta: array_filter([
                    'tahun' => $target->periodeTahun?->tahun,
                    'target' => $target->getAttribute('target'),
                    'target_text' => $target->getAttribute('target_text'),
                    'pagu' => $target->getAttribute('pagu'),
                ], fn ($value) => $value !== null),
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function targetTriwulanNodes(Model $indicator): array
    {
        if (! method_exists($indicator, 'targetTriwulan')) {
            return [];
        }

        return $indicator->targetTriwulan
            ->map(fn (Model $target) => $this->node(
                type: 'target_triwulan',
                id: $target->getKey(),
                label: ($target->periodeTahun?->tahun ?: '-').' '.$this->triwulanLabel($target->getAttribute('triwulan')).': '.$this->targetDisplay($target, 'target_angka'),
                meta: [
                    'tahun' => $target->periodeTahun?->tahun,
                    'triwulan' => $this->triwulanLabel($target->getAttribute('triwulan')),
                    'target_angka' => $target->getAttribute('target_angka'),
                    'target_text' => $target->getAttribute('target_text'),
                    'target_anggaran' => $target->getAttribute('target_anggaran'),
                ],
            ))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function opdPenanggungJawabNode(ProgramRpjmd $program, Opd $opd): array
    {
        return $this->node(
            type: 'opd_penanggung_jawab',
            id: $opd->pivot->id ?? "{$program->id}-{$opd->id}",
            label: $opd->singkatan ? "{$opd->singkatan} - {$opd->nama}" : $opd->nama,
            meta: [
                'kode_opd' => $opd->kode,
                'peran' => $opd->pivot->peran,
                'utama' => (bool) $opd->pivot->is_utama,
            ],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function node(string $type, int|string $id, string $label, array $meta = [], array $children = [], ?array $linkedTo = null): array
    {
        $children = collect($children)
            ->filter()
            ->values()
            ->all();
        $meta = collect($meta)
            ->reject(fn ($value) => $value === null || $value === '')
            ->all();
        $meta = [
            ...$meta,
            ...$this->completionMeta($type, $meta, $children, $linkedTo),
        ];

        return [
            'key' => "{$type}:{$id}",
            'type' => $type,
            'id' => $id,
            'label' => $label,
            'meta' => $meta,
            'linked_to' => $linkedTo,
            'children' => $children,
        ];
    }

    /**
     * @param  array<string, mixed>  $meta
     * @param  array<int, array<string, mixed>>  $children
     * @return array{kelengkapan_status: string, kelengkapan_catatan: string}
     */
    private function completionMeta(string $type, array $meta, array $children, ?array $linkedTo): array
    {
        $childTypes = collect($children)->pluck('type');
        $hasTarget = $childTypes->contains('target_tahunan') || $childTypes->contains('target_triwulan');
        $isIndicator = str_starts_with($type, 'indikator_');

        if ($type === 'target_tahunan' && ! filled($meta['target'] ?? null) && ! filled($meta['target_text'] ?? null)) {
            return $this->incomplete('Target tahunan belum memiliki nilai target.');
        }

        if ($type === 'target_triwulan' && ! filled($meta['target_angka'] ?? null) && ! filled($meta['target_text'] ?? null)) {
            return $this->incomplete('Target triwulan belum memiliki nilai target.');
        }

        if ($isIndicator && ! $hasTarget) {
            return $this->incomplete('Indikator belum memiliki target tahunan atau triwulan.');
        }

        if ($type === 'program_rpjmd' && ! $childTypes->contains('indikator_program_rpjmd')) {
            return $this->incomplete('Program RPJMD belum memiliki indikator program.');
        }

        if ($type === 'program_rpjmd' && ! $childTypes->contains('opd_penanggung_jawab')) {
            return $this->incomplete('Program RPJMD belum memiliki OPD penanggung jawab.');
        }

        if (in_array($type, ['tujuan_opd', 'sasaran_opd', 'opd_program', 'indikator_tujuan_opd', 'indikator_sasaran_opd', 'indikator_opd_program'], true) && ! $linkedTo) {
            return $this->incomplete('Node OPD belum terhubung ke referensi RPJMD.');
        }

        if (in_array($type, ['visi', 'tujuan_daerah', 'sasaran_daerah', 'strategi_daerah', 'tujuan_opd', 'sasaran_opd', 'opd_program', 'opd_kegiatan', 'opd_sub_kegiatan'], true) && $children === []) {
            return $this->incomplete('Node belum memiliki turunan cascading.');
        }

        return [
            'kelengkapan_status' => 'lengkap',
            'kelengkapan_catatan' => 'Struktur minimal tersedia.',
        ];
    }

    /**
     * @return array{kelengkapan_status: string, kelengkapan_catatan: string}
     */
    private function incomplete(string $note): array
    {
        return [
            'kelengkapan_status' => 'perlu_dilengkapi',
            'kelengkapan_catatan' => $note,
        ];
    }

    /**
     * @return array{type: string, id: int|string, label: string}
     */
    private function linkedReference(string $type, int|string $id, string $label): array
    {
        return [
            'type' => $type,
            'id' => $id,
            'label' => $label,
        ];
    }

    private function label(?string $kode, ?string $label): string
    {
        return trim(($kode ? "{$kode} - " : '').($label ?: '-'));
    }

    private function targetDisplay(Model $target, string $numericField = 'target'): string
    {
        return (string) ($target->getAttribute('target_text') ?: $target->getAttribute($numericField) ?: '-');
    }

    private function triwulanLabel(?string $triwulan): string
    {
        return match ($triwulan) {
            'tw1' => 'TW I',
            'tw2' => 'TW II',
            'tw3' => 'TW III',
            'tw4' => 'TW IV',
            default => (string) $triwulan,
        };
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<int, array<string, mixed>>
     */
    private function flatten(array $node): array
    {
        $nodes = [$node];

        foreach ($node['children'] ?? [] as $child) {
            $nodes = [...$nodes, ...$this->flatten($child)];
        }

        return $nodes;
    }
}
