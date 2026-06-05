<?php

namespace App\Services\PublicSite;

use App\Models\Dokumen;
use App\Models\EvaluasiSakip;
use App\Models\Lkjip;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\PerjanjianKinerja;
use App\Models\RealisasiKinerja;
use App\Models\RencanaAksi;
use App\Models\RenstraOpd;
use App\Models\TindakLanjutRekomendasi;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PublicLandingService
{
    private const PUBLIC_DOCUMENT_STATUSES = ['verified', 'approved', 'locked'];

    private const PUBLIC_DOCUMENT_TYPES = [
        'pohon_kinerja',
        'cascading',
        'iku',
        'renstra',
        'renja',
        'perjanjian_kinerja',
        'rencana_aksi',
        'realisasi_kinerja',
        'lkjip',
        'lhe',
        'tindak_lanjut',
    ];

    private const TRIWULAN_LABELS = [
        'tw1' => 'Triwulan I',
        'tw2' => 'Triwulan II',
        'tw3' => 'Triwulan III',
        'tw4' => 'Triwulan IV',
    ];

    private const PUBLIC_SECTIONS = ['perencanaan', 'pengukuran', 'pelaporan', 'evaluasi'];

    /**
     * @return array<string, mixed>
     */
    public function payload(?string $section = null, ?int $requestedYear = null): array
    {
        $activeSection = in_array($section, self::PUBLIC_SECTIONS, true) ? $section : null;
        $periode = $this->periodeForYear($requestedYear);
        $tahun = $requestedYear ?: ($periode?->tahun ?? (int) now()->year);
        $opds = Opd::query()
            ->where('status', 'active')
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama', 'singkatan']);
        $opdIds = $opds->pluck('id')->map(fn ($id) => (int) $id)->all();

        $renstraByOpd = $this->renstraByOpd($opdIds, $tahun);
        $pkByOpd = $this->latestByOpd(PerjanjianKinerja::query()->where('tahun', $tahun), $opdIds);
        $rencanaAksiByOpd = $this->latestByOpd(RencanaAksi::query()->where('tahun', $tahun), $opdIds);
        $lkjipByOpd = $this->latestByOpd(Lkjip::query()->where('tahun', $tahun), $opdIds);
        $realisasiByOpdTriwulan = $this->realisasiByOpdTriwulan($opdIds, $tahun);
        $evaluasiByOpd = $this->evaluasiByOpd($opdIds, $tahun);
        $documents = $this->documentMaps($periode, $opdIds);

        $counts = [
            'tujuan' => $this->countTujuan($opdIds, $tahun),
            'sasaran' => $this->countSasaran($opdIds, $tahun),
            'indikator_sasaran' => $this->countIndikatorSasaran($opdIds, $tahun),
            'program' => $this->countProgram($opdIds, $tahun),
            'kegiatan' => $this->countKegiatan($opdIds, $tahun),
            'sub_kegiatan' => $this->countSubKegiatan($opdIds, $tahun),
        ];

        $perencanaan = $this->perencanaanRows($opds, $renstraByOpd, $pkByOpd, $rencanaAksiByOpd, $documents);
        $pengukuran = $this->pengukuranRows($opds, $counts);
        $pelaporan = $this->pelaporanRows($opds, $lkjipByOpd, $realisasiByOpdTriwulan, $documents);
        $evaluasi = $this->evaluasiRows($opds, $evaluasiByOpd, $documents);

        $nilaiEvaluasi = collect($evaluasiByOpd->values())
            ->filter(fn (EvaluasiSakip $evaluasi) => $evaluasi->nilai_akhir !== null)
            ->map(fn (EvaluasiSakip $evaluasi) => (float) $evaluasi->nilai_akhir);

        $tables = [
            'perencanaan' => [],
            'pengukuran' => [],
            'pelaporan' => [],
            'evaluasi' => [],
        ];

        if ($activeSection) {
            $tables[$activeSection] = [
                'perencanaan' => $perencanaan,
                'pengukuran' => $pengukuran,
                'pelaporan' => $pelaporan,
                'evaluasi' => $evaluasi,
            ][$activeSection];
        }

        return [
            'active_section' => $activeSection,
            'section_urls' => [
                'home' => route('home', ['tahun' => $tahun]),
                'perencanaan' => route('public.section', ['section' => 'perencanaan', 'tahun' => $tahun]),
                'pengukuran' => route('public.section', ['section' => 'pengukuran', 'tahun' => $tahun]),
                'pelaporan' => route('public.section', ['section' => 'pelaporan', 'tahun' => $tahun]),
                'evaluasi' => route('public.section', ['section' => 'evaluasi', 'tahun' => $tahun]),
            ],
            'available_years' => $this->availableYears($tahun),
            'filters' => [
                'tahun' => $tahun,
            ],
            'meta' => [
                'tahun' => $tahun,
                'periode_label' => $periode ? $periode->nama : 'Tahun '.$tahun,
                'generated_at' => now()->toDateTimeString(),
            ],
            'stats' => [
                'opd_count' => $opds->count(),
                'planning_ready_count' => collect($perencanaan)->filter(fn (array $row) => $row['is_ready'])->count(),
                'measurement_ready_count' => collect($pengukuran)->filter(fn (array $row) => $row['is_ready'])->count(),
                'report_ready_count' => collect($pelaporan)->filter(fn (array $row) => $row['is_ready'])->count(),
                'evaluation_count' => $evaluasiByOpd->count(),
                'public_document_count' => $documents['count'],
                'average_sakip' => $nilaiEvaluasi->isNotEmpty() ? round($nilaiEvaluasi->avg(), 2) : null,
            ],
            'tables' => $tables,
        ];
    }

    private function periodeForYear(?int $tahun): ?PeriodeTahun
    {
        if ($tahun) {
            $periode = PeriodeTahun::query()
                ->where('tahun', $tahun)
                ->orderByDesc('id')
                ->first();

            if ($periode) {
                return $periode;
            }
        }

        return $this->currentPeriode();
    }

    /**
     * @return array<int, int>
     */
    private function availableYears(int $selectedYear): array
    {
        return PeriodeTahun::query()
            ->orderByDesc('tahun')
            ->pluck('tahun')
            ->push($selectedYear)
            ->unique()
            ->values()
            ->all();
    }

    private function currentPeriode(): ?PeriodeTahun
    {
        return PeriodeTahun::query()
            ->where('status', 'active')
            ->orderByDesc('tahun')
            ->first()
            ?: PeriodeTahun::query()->orderByDesc('tahun')->first();
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return Collection<int, RenstraOpd>
     */
    private function renstraByOpd(array $opdIds, int $tahun): Collection
    {
        if ($opdIds === []) {
            return collect();
        }

        return RenstraOpd::query()
            ->whereIn('opd_id', $opdIds)
            ->where('tahun_awal', '<=', $tahun)
            ->where('tahun_akhir', '>=', $tahun)
            ->orderByDesc('tahun_awal')
            ->orderByDesc('id')
            ->get()
            ->groupBy('opd_id')
            ->map(fn (Collection $items) => $items->first());
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return Collection<int, Model>
     */
    private function latestByOpd($query, array $opdIds): Collection
    {
        if ($opdIds === []) {
            return collect();
        }

        return $query
            ->whereIn('opd_id', $opdIds)
            ->latest('id')
            ->get()
            ->groupBy('opd_id')
            ->map(fn (Collection $items) => $items->first());
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<int, array<string, RealisasiKinerja>>
     */
    private function realisasiByOpdTriwulan(array $opdIds, int $tahun): array
    {
        if ($opdIds === []) {
            return [];
        }

        $rows = RealisasiKinerja::query()
            ->whereIn('opd_id', $opdIds)
            ->where('tahun', $tahun)
            ->where('periode_realisasi', 'triwulan')
            ->whereIn('triwulan', array_keys(self::TRIWULAN_LABELS))
            ->latest('id')
            ->get();

        $map = [];
        foreach ($rows as $row) {
            $opdId = (int) $row->opd_id;
            $triwulan = (string) $row->triwulan;

            $map[$opdId][$triwulan] ??= $row;
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return Collection<int, EvaluasiSakip>
     */
    private function evaluasiByOpd(array $opdIds, int $tahun): Collection
    {
        if ($opdIds === []) {
            return collect();
        }

        return EvaluasiSakip::query()
            ->with(['lhe', 'predikatEvaluasi', 'rekomendasi.tindakLanjut'])
            ->whereIn('opd_id', $opdIds)
            ->where('tahun', $tahun)
            ->latest('id')
            ->get()
            ->groupBy('opd_id')
            ->map(fn (Collection $items) => $items->first());
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array{by_opd_jenis: array<string, array<string, mixed>>, by_relation: array<string, array<string, mixed>>, count: int}
     */
    private function documentMaps(?PeriodeTahun $periode, array $opdIds): array
    {
        if ($opdIds === []) {
            return ['by_opd_jenis' => [], 'by_relation' => [], 'count' => 0];
        }

        $documents = Dokumen::query()
            ->with('relations')
            ->whereIn('jenis', self::PUBLIC_DOCUMENT_TYPES)
            ->whereIn('status', self::PUBLIC_DOCUMENT_STATUSES)
            ->where(function ($query) use ($periode) {
                $query->whereNull('periode_tahun_id');

                if ($periode) {
                    $query->orWhere('periode_tahun_id', $periode->id);
                }
            })
            ->where(function ($query) use ($opdIds) {
                $query->whereNull('opd_id')->orWhereIn('opd_id', $opdIds);
            })
            ->latest('id')
            ->get();

        $byOpdJenis = [];
        $byRelation = [];

        foreach ($documents as $document) {
            $serialized = $this->serializeDocument($document);

            if ($document->opd_id) {
                $key = $document->opd_id.'|'.$document->jenis;
                $byOpdJenis[$key] ??= $serialized;
            }

            foreach ($document->relations as $relation) {
                $key = $relation->related_type.'|'.$relation->related_id.'|'.$document->jenis;
                $byRelation[$key] ??= $serialized;
            }
        }

        return [
            'by_opd_jenis' => $byOpdJenis,
            'by_relation' => $byRelation,
            'count' => $documents->count(),
        ];
    }

    /**
     * @param  Collection<int, Opd>  $opds
     * @param  Collection<int, RenstraOpd>  $renstraByOpd
     * @param  Collection<int, PerjanjianKinerja>  $pkByOpd
     * @param  Collection<int, RencanaAksi>  $rencanaAksiByOpd
     * @param  array<string, mixed>  $documents
     * @return array<int, array<string, mixed>>
     */
    private function perencanaanRows(Collection $opds, Collection $renstraByOpd, Collection $pkByOpd, Collection $rencanaAksiByOpd, array $documents): array
    {
        return $opds->values()->map(function (Opd $opd, int $index) use ($renstraByOpd, $pkByOpd, $rencanaAksiByOpd, $documents) {
            $opdId = (int) $opd->id;
            $renstra = $renstraByOpd->get($opdId);
            $pk = $pkByOpd->get($opdId);
            $rencanaAksi = $rencanaAksiByOpd->get($opdId);

            $renstraDocument = $this->documentForModel($documents, $renstra, 'renstra')
                ?: $this->documentByOpd($documents, $opdId, 'renstra');
            $pohonKinerjaDocument = $this->documentByOpd($documents, $opdId, 'pohon_kinerja');
            $cascadingDocument = $this->documentForModel($documents, $renstra, 'cascading')
                ?: $this->documentByOpd($documents, $opdId, 'cascading');
            $pkDocument = $this->documentForModel($documents, $pk, 'perjanjian_kinerja')
                ?: $this->documentByOpd($documents, $opdId, 'perjanjian_kinerja');
            $rencanaAksiDocument = $this->documentForModel($documents, $rencanaAksi, 'rencana_aksi')
                ?: $this->documentByOpd($documents, $opdId, 'rencana_aksi');

            return [
                'no' => $index + 1,
                'opd' => $this->serializeOpd($opd),
                'is_ready' => $renstra !== null && $pk !== null && $rencanaAksi !== null,
                'cells' => [
                    'pohon_kinerja' => $this->fileCell($pohonKinerjaDocument),
                    'cascading' => $this->fileCell($cascadingDocument),
                    'iku' => $this->fileCell($this->documentByOpd($documents, $opdId, 'iku')),
                    'renstra' => $this->fileCell($renstraDocument, $renstra !== null, $renstra ? $this->statusLabel($renstra->status) : 'Data tersedia'),
                    'renja_rkt' => $this->fileCell($this->documentByOpd($documents, $opdId, 'renja')),
                    'rencana_aksi' => $this->fileCell($rencanaAksiDocument, $rencanaAksi !== null, $rencanaAksi ? $this->statusLabel($rencanaAksi->status) : 'Data tersedia'),
                    'pk' => $this->fileCell($pkDocument, $pk !== null, $pk ? $this->statusLabel($pk->status) : 'Data tersedia'),
                ],
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Opd>  $opds
     * @param  array<string, Collection<int, int>>  $counts
     * @return array<int, array<string, mixed>>
     */
    private function pengukuranRows(Collection $opds, array $counts): array
    {
        return $opds->values()->map(function (Opd $opd, int $index) use ($counts) {
            $opdId = (int) $opd->id;
            $tujuan = $this->countValue($counts['tujuan'], $opdId);
            $sasaran = $this->countValue($counts['sasaran'], $opdId);
            $program = $this->countValue($counts['program'], $opdId);
            $kegiatan = $this->countValue($counts['kegiatan'], $opdId);
            $subKegiatan = $this->countValue($counts['sub_kegiatan'], $opdId);

            return [
                'no' => $index + 1,
                'opd' => $this->serializeOpd($opd),
                'is_ready' => ($tujuan + $sasaran + $program + $kegiatan + $subKegiatan) > 0,
                'cells' => [
                    'tujuan' => $this->countCell($tujuan, 'tujuan'),
                    'sasaran_strategis' => $this->countCell($sasaran, 'sasaran'),
                    'program' => $this->countCell($program, 'program'),
                    'kegiatan' => $this->countCell($kegiatan, 'kegiatan'),
                    'sub_kegiatan' => $this->countCell($subKegiatan, 'sub kegiatan'),
                ],
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Opd>  $opds
     * @param  Collection<int, Lkjip>  $lkjipByOpd
     * @param  array<int, array<string, RealisasiKinerja>>  $realisasiByOpdTriwulan
     * @param  array<string, mixed>  $documents
     * @return array<int, array<string, mixed>>
     */
    private function pelaporanRows(Collection $opds, Collection $lkjipByOpd, array $realisasiByOpdTriwulan, array $documents): array
    {
        return $opds->values()->map(function (Opd $opd, int $index) use ($lkjipByOpd, $realisasiByOpdTriwulan, $documents) {
            $opdId = (int) $opd->id;
            $lkjip = $lkjipByOpd->get($opdId);
            $cells = [
                'lkjip' => $this->fileCell(
                    $this->documentForModel($documents, $lkjip, 'lkjip') ?: $this->documentByOpd($documents, $opdId, 'lkjip'),
                    $lkjip !== null,
                    $lkjip ? $this->statusLabel($lkjip->status) : 'Data tersedia',
                ),
            ];

            foreach (array_keys(self::TRIWULAN_LABELS) as $triwulan) {
                $realisasi = $realisasiByOpdTriwulan[$opdId][$triwulan] ?? null;
                $cells[$triwulan] = $this->fileCell(
                    $this->documentForModel($documents, $realisasi, 'realisasi_kinerja'),
                    $realisasi !== null,
                    $realisasi ? $this->statusLabel($realisasi->status) : 'Data tersedia',
                );
            }

            return [
                'no' => $index + 1,
                'opd' => $this->serializeOpd($opd),
                'is_ready' => $lkjip !== null,
                'cells' => $cells,
            ];
        })->all();
    }

    /**
     * @param  Collection<int, Opd>  $opds
     * @param  Collection<int, EvaluasiSakip>  $evaluasiByOpd
     * @param  array<string, mixed>  $documents
     * @return array<int, array<string, mixed>>
     */
    private function evaluasiRows(Collection $opds, Collection $evaluasiByOpd, array $documents): array
    {
        return $opds->values()->map(function (Opd $opd, int $index) use ($evaluasiByOpd, $documents) {
            $opdId = (int) $opd->id;
            $evaluasi = $evaluasiByOpd->get($opdId);
            $tindakLanjut = $this->latestTindakLanjut($evaluasi);

            return [
                'no' => $index + 1,
                'opd' => $this->serializeOpd($opd),
                'is_ready' => $evaluasi !== null,
                'cells' => [
                    'nilai_sakip' => $this->scoreCell($evaluasi),
                    'lhe_internal' => $this->fileCell(
                        $this->documentForModel($documents, $evaluasi, 'lhe') ?: $this->documentByOpd($documents, $opdId, 'lhe'),
                        $evaluasi?->lhe !== null,
                        $evaluasi?->lhe ? $this->statusLabel($evaluasi->lhe->status) : 'Data tersedia',
                    ),
                    'tindak_lanjut_lhe' => $this->fileCell(
                        $this->documentForModel($documents, $tindakLanjut, 'tindak_lanjut') ?: $this->documentByOpd($documents, $opdId, 'tindak_lanjut'),
                        $tindakLanjut !== null,
                        $tindakLanjut ? $this->statusLabel($tindakLanjut->status_tindak_lanjut) : 'Data tersedia',
                    ),
                ],
            ];
        })->all();
    }

    private function latestTindakLanjut(?EvaluasiSakip $evaluasi): ?TindakLanjutRekomendasi
    {
        if (! $evaluasi) {
            return null;
        }

        return $evaluasi->rekomendasi
            ->flatMap(fn ($rekomendasi) => $rekomendasi->tindakLanjut)
            ->sortByDesc('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOpd(Opd $opd): array
    {
        return [
            'id' => $opd->id,
            'kode' => $opd->kode,
            'nama' => $opd->nama,
            'singkatan' => $opd->singkatan,
            'label' => $opd->singkatan ?: $opd->nama,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDocument(Dokumen $document): array
    {
        return [
            'id' => $document->id,
            'judul' => $document->judul,
            'filename' => $document->original_filename,
            'mime_type' => $document->mime_type,
            'view_url' => route('public.dokumen.view', $document),
            'download_url' => route('public.dokumen.download', $document),
        ];
    }

    /**
     * @param  array<string, mixed>  $documents
     * @return array<string, mixed>|null
     */
    private function documentForModel(array $documents, ?Model $model, string $jenis): ?array
    {
        if (! $model) {
            return null;
        }

        return $documents['by_relation'][$model::class.'|'.$model->getKey().'|'.$jenis] ?? null;
    }

    /**
     * @param  array<string, mixed>  $documents
     * @return array<string, mixed>|null
     */
    private function documentByOpd(array $documents, int $opdId, string $jenis): ?array
    {
        return $documents['by_opd_jenis'][$opdId.'|'.$jenis] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    private function statusCell(bool $available, string $availableLabel, string $missingLabel): array
    {
        return [
            'kind' => 'status',
            'state' => $available ? 'available' : 'missing',
            'label' => $available ? $availableLabel : $missingLabel,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $document
     * @return array<string, mixed>
     */
    private function fileCell(?array $document, bool $hasData = false, string $dataLabel = 'Data tersedia'): array
    {
        if ($document) {
            return [
                'kind' => 'file',
                'state' => 'available',
                'label' => 'Dokumen tersedia',
                'dokumen' => $document,
            ];
        }

        if ($hasData) {
            return [
                'kind' => 'status',
                'state' => 'data',
                'label' => $dataLabel,
            ];
        }

        return [
            'kind' => 'status',
            'state' => 'missing',
            'label' => 'Belum tersedia',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function countCell(int $count, string $label): array
    {
        return [
            'kind' => 'metric',
            'state' => $count > 0 ? 'available' : 'missing',
            'label' => $count > 0 ? $count.' '.$label : 'Belum tersedia',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function scoreCell(?EvaluasiSakip $evaluasi): array
    {
        if (! $evaluasi) {
            return [
                'kind' => 'score',
                'state' => 'missing',
                'label' => 'Belum tersedia',
            ];
        }

        $nilai = (float) $evaluasi->nilai_akhir;

        return [
            'kind' => 'score',
            'state' => match (true) {
                $nilai > 80 => 'excellent',
                $nilai > 70 => 'available',
                $nilai > 60 => 'data',
                default => 'warning',
            },
            'label' => number_format($nilai, 2, ',', '.'),
            'description' => $evaluasi->predikat ? 'Predikat '.$evaluasi->predikat : null,
        ];
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'draft' => 'Draft',
            'submitted' => 'Diajukan',
            'revision' => 'Perlu revisi',
            'verified' => 'Terverifikasi',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'locked' => 'Terkunci',
            'belum' => 'Belum',
            'proses' => 'Proses',
            'selesai' => 'Selesai',
            'ditolak' => 'Ditolak',
            'perlu_perbaikan' => 'Perlu perbaikan',
            default => 'Data tersedia',
        };
    }

    private function countValue(Collection $counts, int $opdId): int
    {
        return (int) ($counts->get($opdId) ?? $counts->get((string) $opdId) ?? 0);
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return Collection<int, int>
     */
    private function countTujuan(array $opdIds, int $tahun): Collection
    {
        return $this->renstraJoinedCount('tujuan_opd', 'tujuan_opd.renstra_opd_id', $opdIds, $tahun);
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return Collection<int, int>
     */
    private function countSasaran(array $opdIds, int $tahun): Collection
    {
        if ($opdIds === []) {
            return collect();
        }

        return DB::table('sasaran_opd')
            ->join('tujuan_opd', 'tujuan_opd.id', '=', 'sasaran_opd.tujuan_opd_id')
            ->join('renstra_opd', 'renstra_opd.id', '=', 'tujuan_opd.renstra_opd_id')
            ->whereIn('renstra_opd.opd_id', $opdIds)
            ->where('renstra_opd.tahun_awal', '<=', $tahun)
            ->where('renstra_opd.tahun_akhir', '>=', $tahun)
            ->whereNull('sasaran_opd.deleted_at')
            ->whereNull('tujuan_opd.deleted_at')
            ->whereNull('renstra_opd.deleted_at')
            ->select('renstra_opd.opd_id', DB::raw('count(*) as total'))
            ->groupBy('renstra_opd.opd_id')
            ->pluck('total', 'opd_id');
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return Collection<int, int>
     */
    private function countIndikatorSasaran(array $opdIds, int $tahun): Collection
    {
        if ($opdIds === []) {
            return collect();
        }

        return DB::table('indikator_sasaran_opd')
            ->join('sasaran_opd', 'sasaran_opd.id', '=', 'indikator_sasaran_opd.sasaran_opd_id')
            ->join('tujuan_opd', 'tujuan_opd.id', '=', 'sasaran_opd.tujuan_opd_id')
            ->join('renstra_opd', 'renstra_opd.id', '=', 'tujuan_opd.renstra_opd_id')
            ->whereIn('renstra_opd.opd_id', $opdIds)
            ->where('renstra_opd.tahun_awal', '<=', $tahun)
            ->where('renstra_opd.tahun_akhir', '>=', $tahun)
            ->whereNull('indikator_sasaran_opd.deleted_at')
            ->whereNull('sasaran_opd.deleted_at')
            ->whereNull('tujuan_opd.deleted_at')
            ->whereNull('renstra_opd.deleted_at')
            ->select('renstra_opd.opd_id', DB::raw('count(*) as total'))
            ->groupBy('renstra_opd.opd_id')
            ->pluck('total', 'opd_id');
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return Collection<int, int>
     */
    private function countProgram(array $opdIds, int $tahun): Collection
    {
        return $this->renstraJoinedCount('opd_program', 'opd_program.renstra_opd_id', $opdIds, $tahun);
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return Collection<int, int>
     */
    private function countKegiatan(array $opdIds, int $tahun): Collection
    {
        if ($opdIds === []) {
            return collect();
        }

        return DB::table('opd_kegiatan')
            ->join('opd_program', 'opd_program.id', '=', 'opd_kegiatan.opd_program_id')
            ->join('renstra_opd', 'renstra_opd.id', '=', 'opd_program.renstra_opd_id')
            ->whereIn('renstra_opd.opd_id', $opdIds)
            ->where('renstra_opd.tahun_awal', '<=', $tahun)
            ->where('renstra_opd.tahun_akhir', '>=', $tahun)
            ->whereNull('opd_kegiatan.deleted_at')
            ->whereNull('opd_program.deleted_at')
            ->whereNull('renstra_opd.deleted_at')
            ->select('renstra_opd.opd_id', DB::raw('count(*) as total'))
            ->groupBy('renstra_opd.opd_id')
            ->pluck('total', 'opd_id');
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return Collection<int, int>
     */
    private function countSubKegiatan(array $opdIds, int $tahun): Collection
    {
        if ($opdIds === []) {
            return collect();
        }

        return DB::table('opd_sub_kegiatan')
            ->join('opd_kegiatan', 'opd_kegiatan.id', '=', 'opd_sub_kegiatan.opd_kegiatan_id')
            ->join('opd_program', 'opd_program.id', '=', 'opd_kegiatan.opd_program_id')
            ->join('renstra_opd', 'renstra_opd.id', '=', 'opd_program.renstra_opd_id')
            ->whereIn('renstra_opd.opd_id', $opdIds)
            ->where('renstra_opd.tahun_awal', '<=', $tahun)
            ->where('renstra_opd.tahun_akhir', '>=', $tahun)
            ->whereNull('opd_sub_kegiatan.deleted_at')
            ->whereNull('opd_kegiatan.deleted_at')
            ->whereNull('opd_program.deleted_at')
            ->whereNull('renstra_opd.deleted_at')
            ->select('renstra_opd.opd_id', DB::raw('count(*) as total'))
            ->groupBy('renstra_opd.opd_id')
            ->pluck('total', 'opd_id');
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return Collection<int, int>
     */
    private function renstraJoinedCount(string $table, string $renstraForeignKey, array $opdIds, int $tahun): Collection
    {
        if ($opdIds === []) {
            return collect();
        }

        return DB::table($table)
            ->join('renstra_opd', 'renstra_opd.id', '=', $renstraForeignKey)
            ->whereIn('renstra_opd.opd_id', $opdIds)
            ->where('renstra_opd.tahun_awal', '<=', $tahun)
            ->where('renstra_opd.tahun_akhir', '>=', $tahun)
            ->whereNull($table.'.deleted_at')
            ->whereNull('renstra_opd.deleted_at')
            ->select('renstra_opd.opd_id', DB::raw('count(*) as total'))
            ->groupBy('renstra_opd.opd_id')
            ->pluck('total', 'opd_id');
    }
}
