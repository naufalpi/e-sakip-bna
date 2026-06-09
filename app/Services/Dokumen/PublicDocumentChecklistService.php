<?php

namespace App\Services\Dokumen;

use App\Models\Dokumen;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\User;
use App\Services\PublicSite\PublicLandingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PublicDocumentChecklistService
{
    private const PUBLIC_STATUSES = ['verified', 'approved', 'locked'];

    private const PUBLIC_TYPES = [
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

    private const SECTIONS = [
        'perencanaan' => [
            'label' => 'Perencanaan Kinerja',
            'description' => 'Dokumen yang muncul pada menu publik Perencanaan.',
            'items' => [
                ['key' => 'pohon_kinerja', 'label' => 'Pohon Kinerja', 'type' => 'document', 'jenis' => 'pohon_kinerja'],
                ['key' => 'cascading', 'label' => 'Cascading', 'type' => 'document', 'jenis' => 'cascading'],
                ['key' => 'iku', 'label' => 'IKU', 'type' => 'document', 'jenis' => 'iku'],
                ['key' => 'renstra', 'label' => 'Renstra', 'type' => 'document', 'jenis' => 'renstra'],
                ['key' => 'renja_rkt', 'label' => 'Renja/RKT', 'type' => 'document', 'jenis' => 'renja'],
                ['key' => 'rencana_aksi', 'label' => 'Rencana Aksi', 'type' => 'document', 'jenis' => 'rencana_aksi'],
                ['key' => 'pk', 'label' => 'Perjanjian Kinerja', 'type' => 'document', 'jenis' => 'perjanjian_kinerja'],
            ],
        ],
        'pengukuran' => [
            'label' => 'Pengukuran Kinerja',
            'description' => 'Kelengkapan data struktur kinerja yang dibuka pada menu publik Pengukuran.',
            'items' => [
                ['key' => 'tujuan', 'label' => 'Tujuan', 'type' => 'data', 'jenis' => null],
                ['key' => 'sasaran_strategis', 'label' => 'Sasaran Strategis', 'type' => 'data', 'jenis' => null],
                ['key' => 'program', 'label' => 'Program', 'type' => 'data', 'jenis' => null],
                ['key' => 'kegiatan', 'label' => 'Kegiatan', 'type' => 'data', 'jenis' => null],
                ['key' => 'sub_kegiatan', 'label' => 'Sub Kegiatan', 'type' => 'data', 'jenis' => null],
            ],
        ],
        'pelaporan' => [
            'label' => 'Pelaporan Kinerja',
            'description' => 'Dokumen LKJIP dan laporan realisasi triwulan yang tampil ke masyarakat.',
            'items' => [
                ['key' => 'lkjip', 'label' => 'LKJIP', 'type' => 'document', 'jenis' => 'lkjip'],
                ['key' => 'tw1', 'label' => 'Laporan TW I', 'type' => 'document', 'jenis' => 'realisasi_kinerja'],
                ['key' => 'tw2', 'label' => 'Laporan TW II', 'type' => 'document', 'jenis' => 'realisasi_kinerja'],
                ['key' => 'tw3', 'label' => 'Laporan TW III', 'type' => 'document', 'jenis' => 'realisasi_kinerja'],
                ['key' => 'tw4', 'label' => 'Laporan TW IV', 'type' => 'document', 'jenis' => 'realisasi_kinerja'],
            ],
        ],
        'evaluasi' => [
            'label' => 'Evaluasi Kinerja',
            'description' => 'Nilai SAKIP, LHE internal, dan tindak lanjut yang dipublikasikan.',
            'items' => [
                ['key' => 'nilai_sakip', 'label' => 'Nilai SAKIP', 'type' => 'score', 'jenis' => null],
                ['key' => 'lhe_internal', 'label' => 'LHE Internal', 'type' => 'document', 'jenis' => 'lhe'],
                ['key' => 'tindak_lanjut_lhe', 'label' => 'Tindak Lanjut LHE', 'type' => 'document', 'jenis' => 'tindak_lanjut'],
            ],
        ],
    ];

    public function __construct(private readonly PublicLandingService $publicLandingService) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function payload(User $user, array $filters): array
    {
        $publicPayload = $this->publicLandingService->fullPayload($this->requestedYear($filters['tahun'] ?? null));
        $tahun = (int) ($publicPayload['filters']['tahun'] ?? now()->year);
        $periode = PeriodeTahun::query()->where('tahun', $tahun)->orderByDesc('id')->first();
        $canViewAll = $this->canViewAll($user);
        $canUpload = $user->can('create', Dokumen::class);
        $selectedOpdId = $canViewAll ? $this->requestedOpdId($filters['opd_id'] ?? null) : $this->ownedOpdId($user);

        $tables = $this->filterTablesByOpd($publicPayload['tables'] ?? [], $selectedOpdId);
        $opdIds = $this->opdIdsFromTables($tables);
        $internalDocuments = $this->internalDocuments($periode?->id, $opdIds);
        $sections = $this->sections($tables, $internalDocuments, $periode?->id, $tahun, $canUpload);

        return [
            'filters' => [
                'tahun' => $tahun,
                'opd_id' => $selectedOpdId,
            ],
            'availableYears' => $publicPayload['available_years'] ?? [$tahun],
            'opdOptions' => $this->opdOptions($user, $canViewAll),
            'canSelectOpd' => $canViewAll,
            'selectedOpd' => $selectedOpdId ? $this->selectedOpd($selectedOpdId) : null,
            'sections' => $sections,
            'summary' => $this->summary($sections),
            'isAggregate' => $selectedOpdId === null,
            'publicUrl' => route('public.section', ['section' => 'perencanaan', 'tahun' => $tahun]),
        ];
    }

    private function requestedYear(mixed $tahun): ?int
    {
        return filled($tahun) && (int) $tahun > 0 ? (int) $tahun : null;
    }

    private function requestedOpdId(mixed $opdId): ?int
    {
        return filled($opdId) && (int) $opdId > 0 ? (int) $opdId : null;
    }

    private function ownedOpdId(User $user): ?int
    {
        return filled($user->opd_id) ? (int) $user->opd_id : null;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function filterTablesByOpd(array $tables, ?int $opdId): array
    {
        if (! $opdId) {
            return $tables;
        }

        return collect($tables)
            ->map(fn (array $rows) => collect($rows)
                ->filter(fn (array $row) => (int) ($row['opd']['id'] ?? 0) === $opdId)
                ->values()
                ->all())
            ->all();
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @return array<int, int>
     */
    private function opdIdsFromTables(array $tables): array
    {
        return collect($tables)
            ->flatMap(fn (array $rows) => collect($rows)->pluck('opd.id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<int, int>  $opdIds
     * @return array<string, array<string, mixed>>
     */
    private function internalDocuments(?int $periodeId, array $opdIds): array
    {
        if ($opdIds === []) {
            return [];
        }

        return Dokumen::query()
            ->whereIn('opd_id', $opdIds)
            ->whereIn('jenis', self::PUBLIC_TYPES)
            ->where(function (Builder $query) use ($periodeId) {
                $query->whereNull('periode_tahun_id');

                if ($periodeId) {
                    $query->orWhere('periode_tahun_id', $periodeId);
                }
            })
            ->latest('id')
            ->get()
            ->groupBy(fn (Dokumen $dokumen) => $dokumen->opd_id.'|'.$dokumen->jenis)
            ->map(fn (Collection $items) => $this->serializeInternalDocument($items->first()))
            ->all();
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $tables
     * @param  array<string, array<string, mixed>>  $internalDocuments
     * @return array<int, array<string, mixed>>
     */
    private function sections(array $tables, array $internalDocuments, ?int $periodeId, int $tahun, bool $canUpload): array
    {
        return collect(self::SECTIONS)
            ->map(fn (array $definition, string $key) => $this->section($key, $definition, $tables[$key] ?? [], $internalDocuments, $periodeId, $tahun, $canUpload))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, mixed>>  $internalDocuments
     * @return array<string, mixed>
     */
    private function section(string $key, array $definition, array $rows, array $internalDocuments, ?int $periodeId, int $tahun, bool $canUpload): array
    {
        $items = collect($definition['items'])
            ->map(fn (array $item) => $this->item($item, $rows, $internalDocuments, $periodeId, $tahun, $canUpload))
            ->values()
            ->all();

        return [
            'key' => $key,
            'label' => $definition['label'],
            'description' => $definition['description'],
            'row_count' => count($rows),
            'items' => $items,
            'summary' => $this->summaryFromItems($items),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, array<string, mixed>>  $internalDocuments
     * @return array<string, mixed>
     */
    private function item(array $definition, array $rows, array $internalDocuments, ?int $periodeId, int $tahun, bool $canUpload): array
    {
        $details = collect($rows)
            ->map(function (array $row) use ($definition, $internalDocuments, $periodeId, $tahun, $canUpload) {
                $opd = $row['opd'] ?? null;
                $opdId = (int) ($opd['id'] ?? 0);
                $cell = $row['cells'][$definition['key']] ?? [
                    'kind' => 'status',
                    'state' => 'missing',
                    'label' => 'Belum tersedia',
                ];
                $jenis = $definition['jenis'] ?? null;
                $internalDocument = $jenis ? ($internalDocuments[$opdId.'|'.$jenis] ?? null) : null;

                return [
                    'opd' => $opd,
                    ...$this->resolveState($definition, $cell, $internalDocument),
                    'source_label' => $cell['label'] ?? null,
                    'public_document' => $cell['dokumen'] ?? null,
                    'internal_document' => $internalDocument,
                    'upload_url' => $canUpload && $jenis && $opdId > 0
                        ? route('dokumen.create', [
                            'jenis' => $jenis,
                            'opd_id' => $opdId,
                            'periode_tahun_id' => $periodeId,
                            'judul' => $definition['label'].' '.$tahun,
                        ])
                        : null,
                ];
            })
            ->values()
            ->all();

        $summary = $this->summaryFromDetails($details);

        return [
            'key' => $definition['key'],
            'label' => $definition['label'],
            'type' => $definition['type'],
            'jenis' => $definition['jenis'] ?? null,
            'details' => $details,
            'summary' => $summary,
            'state' => $this->stateFromSummary($summary),
        ];
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, mixed>  $cell
     * @param  array<string, mixed>|null  $internalDocument
     * @return array<string, mixed>
     */
    private function resolveState(array $definition, array $cell, ?array $internalDocument): array
    {
        if ($definition['type'] === 'document') {
            if (($cell['kind'] ?? null) === 'file' && filled($cell['dokumen'] ?? null)) {
                return [
                    'state' => 'complete',
                    'label' => 'Tampil publik',
                    'description' => 'Dokumen sudah lolos publikasi.',
                ];
            }

            if ($internalDocument) {
                return $this->internalDocumentState($internalDocument);
            }

            if (($cell['state'] ?? null) === 'data') {
                return [
                    'state' => 'needs_upload',
                    'label' => 'Dokumen belum diunggah',
                    'description' => 'Data aplikasi sudah ada, tetapi file publik belum tersedia.',
                ];
            }

            return [
                'state' => 'missing',
                'label' => 'Belum ada',
                'description' => 'Data atau dokumen belum tersedia untuk tahun ini.',
            ];
        }

        if (($cell['state'] ?? null) === 'missing') {
            return [
                'state' => 'missing',
                'label' => 'Belum ada',
                'description' => 'Data belum tersedia.',
            ];
        }

        return [
            'state' => 'complete',
            'label' => (string) ($cell['label'] ?? 'Data tersedia'),
            'description' => 'Data sudah terbaca untuk tampilan publik.',
        ];
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function internalDocumentState(array $document): array
    {
        return match ($document['status']) {
            'submitted' => [
                'state' => 'pending',
                'label' => 'Menunggu verifikasi',
                'description' => 'Dokumen sudah diajukan, tetapi belum tampil ke publik.',
            ],
            'revision' => [
                'state' => 'needs_upload',
                'label' => 'Perlu perbaikan',
                'description' => 'Dokumen perlu diperbaiki sebelum bisa dipublikasikan.',
            ],
            'rejected' => [
                'state' => 'needs_upload',
                'label' => 'Ditolak',
                'description' => 'Dokumen ditolak dan perlu diunggah atau diajukan ulang.',
            ],
            'verified', 'approved', 'locked' => [
                'state' => 'complete',
                'label' => 'Tampil publik',
                'description' => 'Dokumen sudah berstatus publik.',
            ],
            default => [
                'state' => 'draft',
                'label' => 'Draft belum publik',
                'description' => 'Dokumen sudah diunggah, tetapi belum diajukan/diverifikasi.',
            ],
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, int|float>
     */
    private function summary(array $sections): array
    {
        $summary = collect($sections)
            ->pluck('summary')
            ->reduce(fn (array $carry, array $item) => [
                'total' => $carry['total'] + $item['total'],
                'complete' => $carry['complete'] + $item['complete'],
                'pending' => $carry['pending'] + $item['pending'],
                'needs_upload' => $carry['needs_upload'] + $item['needs_upload'],
                'missing' => $carry['missing'] + $item['missing'],
            ], ['total' => 0, 'complete' => 0, 'pending' => 0, 'needs_upload' => 0, 'missing' => 0]);

        return [
            ...$summary,
            'percent' => $summary['total'] > 0 ? round(($summary['complete'] / $summary['total']) * 100, 1) : 0,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<string, int|float>
     */
    private function summaryFromItems(array $items): array
    {
        $summary = collect($items)
            ->pluck('summary')
            ->reduce(fn (array $carry, array $item) => [
                'total' => $carry['total'] + $item['total'],
                'complete' => $carry['complete'] + $item['complete'],
                'pending' => $carry['pending'] + $item['pending'],
                'needs_upload' => $carry['needs_upload'] + $item['needs_upload'],
                'missing' => $carry['missing'] + $item['missing'],
            ], ['total' => 0, 'complete' => 0, 'pending' => 0, 'needs_upload' => 0, 'missing' => 0]);

        return [
            ...$summary,
            'percent' => $summary['total'] > 0 ? round(($summary['complete'] / $summary['total']) * 100, 1) : 0,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $details
     * @return array<string, int|float>
     */
    private function summaryFromDetails(array $details): array
    {
        $summary = collect($details)
            ->reduce(function (array $carry, array $detail) {
                $state = $detail['state'];

                $carry['total']++;

                if ($state === 'complete') {
                    $carry['complete']++;
                } elseif ($state === 'pending' || $state === 'draft') {
                    $carry['pending']++;
                } elseif ($state === 'needs_upload') {
                    $carry['needs_upload']++;
                } else {
                    $carry['missing']++;
                }

                return $carry;
            }, ['total' => 0, 'complete' => 0, 'pending' => 0, 'needs_upload' => 0, 'missing' => 0]);

        return [
            ...$summary,
            'percent' => $summary['total'] > 0 ? round(($summary['complete'] / $summary['total']) * 100, 1) : 0,
        ];
    }

    /**
     * @param  array<string, int|float>  $summary
     */
    private function stateFromSummary(array $summary): string
    {
        if ($summary['total'] === 0) {
            return 'missing';
        }

        if ($summary['complete'] === $summary['total']) {
            return 'complete';
        }

        if ($summary['pending'] > 0) {
            return 'pending';
        }

        if ($summary['needs_upload'] > 0) {
            return 'needs_upload';
        }

        return 'missing';
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInternalDocument(Dokumen $dokumen): array
    {
        return [
            'id' => $dokumen->id,
            'judul' => $dokumen->judul,
            'jenis' => $dokumen->jenis,
            'status' => $dokumen->status,
            'status_label' => $this->statusLabel($dokumen->status),
            'filename' => $dokumen->original_filename,
            'show_url' => route('dokumen.show', $dokumen),
            'download_url' => route('dokumen.download', $dokumen),
            'is_public' => in_array($dokumen->status, self::PUBLIC_STATUSES, true),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function opdOptions(User $user, bool $canViewAll): array
    {
        return Opd::query()
            ->where('status', 'active')
            ->when(! $canViewAll, fn (Builder $query) => $query->whereKey($user->opd_id))
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama', 'singkatan'])
            ->map(fn (Opd $opd) => [
                'id' => $opd->id,
                'label' => $opd->singkatan ? "{$opd->singkatan} - {$opd->nama}" : $opd->nama,
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function selectedOpd(int $opdId): ?array
    {
        $opd = Opd::query()->find($opdId, ['id', 'kode', 'nama', 'singkatan']);

        if (! $opd) {
            return null;
        }

        return [
            'id' => $opd->id,
            'kode' => $opd->kode,
            'nama' => $opd->nama,
            'singkatan' => $opd->singkatan,
            'label' => $opd->singkatan ? "{$opd->singkatan} - {$opd->nama}" : $opd->nama,
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
            default => 'Tidak diketahui',
        };
    }

    private function canViewAll(User $user): bool
    {
        return $user->hasAnyRole([
            'super_admin',
            'admin_kabupaten_bagian_organisasi',
            'admin_kabupaten_bapperida',
            'admin_kabupaten_inspektorat',
            'admin_kabupaten_dinkominfo',
        ]);
    }
}
