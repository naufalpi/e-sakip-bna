<?php

namespace App\Services\Workflow;

use App\Models\EvaluasiSakip;
use App\Models\RealisasiKinerja;
use App\Models\TindakLanjutRekomendasi;
use App\Models\User;
use App\Models\WorkflowSubmission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WorkflowInboxService
{
    private const MODULES = [
        'rpjmd',
        'renstra_opd',
        'perjanjian_kinerja',
        'rencana_aksi',
        'realisasi_kinerja',
        'lkjip',
        'evaluasi_sakip',
        'tindak_lanjut_rekomendasi',
    ];

    private const STATUSES = [
        'draft',
        'submitted',
        'revision',
        'verified',
        'approved',
        'rejected',
        'locked',
    ];

    private const REVIEW_STATUSES = ['submitted', 'verified'];

    private const ACCESS_ROLES = [
        'super_admin',
        'admin_kabupaten_bagian_organisasi',
        'admin_kabupaten_bapperida',
        'admin_kabupaten_inspektorat',
        'admin_opd',
    ];

    private const MONITORING_ROLES = [
        'super_admin',
        'admin_kabupaten_bagian_organisasi',
        'admin_kabupaten_bapperida',
        'admin_kabupaten_inspektorat',
    ];

    private const ACCESS_PERMISSIONS = [
        'kinerja.manage',
        'rpjmd.manage',
        'renstra.manage',
        'evaluasi.manage',
        'lkjip.manage',
        'verify_realisasi',
        'manage_evaluasi',
        'manage_rpjmd',
        'manage_renstra_opd',
    ];

    public function __construct(private readonly WorkflowModuleRegistry $registry) {}

    public function canAccess(User $user): bool
    {
        $user->loadMissing('roles.permissions');

        return $user->isSuperAdmin()
            || $user->hasAnyRole(self::ACCESS_ROLES)
            || $user->hasAnyPermission(self::ACCESS_PERMISSIONS);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function payload(User $user, array $filters): array
    {
        $user->loadMissing('roles.permissions');

        $visibleModules = $this->visibleModules($user);
        $scopeOptions = $this->scopeOptions($user);
        $scope = $this->resolveScope($scopeOptions, (string) ($filters['scope'] ?? ''));
        $module = $this->resolveOption((string) ($filters['module'] ?? ''), $visibleModules->all());
        $status = $this->resolveOption((string) ($filters['status'] ?? ''), self::STATUSES);
        $search = trim((string) ($filters['search'] ?? ''));

        $reviewModules = $this->reviewModules($user);

        $submissions = WorkflowSubmission::query()
            ->with([
                'submittedBy:id,name',
                'currentReviewer:id,name',
            ])
            ->whereIn('module', $visibleModules)
            ->when($module !== '', fn ($query) => $query->where('module', $module))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('updated_at')
            ->latest('id')
            ->get();

        $rows = $this->enrichSubmissions($user, $submissions, $reviewModules)
            ->filter(fn (array $row) => $this->isVisibleForScope($user, $row, $scope))
            ->when($search !== '', fn (Collection $collection) => $this->filterSearch($collection, $search))
            ->values();

        return [
            'items' => $this->paginate($rows)->toArray(),
            'filters' => [
                'search' => $search,
                'module' => $module,
                'status' => $status,
                'scope' => $scope,
            ],
            'moduleOptions' => $this->moduleOptions($visibleModules),
            'statusOptions' => $this->statusOptions(),
            'scopeOptions' => $scopeOptions,
            'summary' => $this->summary($rows),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function moduleOptions(Collection $modules): array
    {
        return $modules
            ->map(fn (string $module) => [
                'value' => $module,
                'label' => $this->registry->label($module),
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        $labels = [
            'draft' => 'Draft',
            'submitted' => 'Diajukan',
            'revision' => 'Revisi',
            'verified' => 'Terverifikasi',
            'approved' => 'Disetujui',
            'rejected' => 'Ditolak',
            'locked' => 'Terkunci',
        ];

        return collect(self::STATUSES)
            ->map(fn (string $status) => [
                'value' => $status,
                'label' => $labels[$status],
            ])
            ->all();
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    private function scopeOptions(User $user): array
    {
        $options = [];

        if ($this->reviewModules($user)->isNotEmpty()) {
            $options[] = [
                'value' => 'review',
                'label' => 'Perlu Review',
                'description' => 'Dokumen yang menunggu verifikasi atau persetujuan.',
            ];
        }

        $options[] = [
            'value' => 'mine',
            'label' => 'Pengajuan Saya',
            'description' => 'Dokumen yang diajukan oleh akun atau OPD sendiri.',
        ];

        if ($this->canMonitorAll($user)) {
            $options[] = [
                'value' => 'all',
                'label' => 'Semua Workflow',
                'description' => 'Monitoring seluruh workflow sesuai kewenangan kabupaten.',
            ];
        }

        return $options;
    }

    /**
     * @param  array<int, array{value: string}>  $scopeOptions
     */
    private function resolveScope(array $scopeOptions, string $requestedScope): string
    {
        $allowed = collect($scopeOptions)->pluck('value');

        if ($requestedScope !== '' && $allowed->contains($requestedScope)) {
            return $requestedScope;
        }

        return (string) ($allowed->first() ?? 'mine');
    }

    /**
     * @param  array<int, string>  $allowed
     */
    private function resolveOption(string $value, array $allowed): string
    {
        return in_array($value, $allowed, true) ? $value : '';
    }

    private function canMonitorAll(User $user): bool
    {
        return $user->isSuperAdmin() || $user->hasAnyRole(self::MONITORING_ROLES);
    }

    private function visibleModules(User $user): Collection
    {
        if ($user->isSuperAdmin() || $user->hasRole('admin_kabupaten_bagian_organisasi') || $user->hasRole('admin_opd')) {
            return collect(self::MODULES);
        }

        $modules = collect();

        if ($user->hasRole('admin_kabupaten_bapperida') || $user->hasAnyPermission(['rpjmd.manage', 'manage_rpjmd'])) {
            $modules = $modules->merge(['rpjmd', 'renstra_opd']);
        }

        if ($user->hasRole('admin_kabupaten_inspektorat') || $user->hasAnyPermission(['evaluasi.manage', 'manage_evaluasi'])) {
            $modules = $modules->merge(self::MODULES);
        }

        if ($user->hasAnyPermission(['kinerja.manage', 'renstra.manage', 'verify_realisasi', 'manage_renstra_opd', 'lkjip.manage'])) {
            $modules = $modules->merge(['renstra_opd', 'perjanjian_kinerja', 'rencana_aksi', 'realisasi_kinerja', 'lkjip']);
        }

        return $modules->intersect(self::MODULES)->unique()->values();
    }

    private function reviewModules(User $user): Collection
    {
        if ($user->isSuperAdmin()) {
            return collect(self::MODULES);
        }

        return collect(self::MODULES)
            ->filter(function (string $module) use ($user) {
                if ($user->hasAnyRole($this->registry->reviewerRoles($module))) {
                    return true;
                }

                if ($module === 'realisasi_kinerja' && $user->hasPermission('verify_realisasi')) {
                    return true;
                }

                if (in_array($module, ['evaluasi_sakip', 'tindak_lanjut_rekomendasi'], true)
                    && $user->hasAnyPermission(['evaluasi.manage', 'manage_evaluasi'])) {
                    return true;
                }

                return false;
            })
            ->values();
    }

    /**
     * @param  Collection<int, WorkflowSubmission>  $submissions
     * @param  Collection<int, string>  $reviewModules
     * @return Collection<int, array<string, mixed>>
     */
    private function enrichSubmissions(User $user, Collection $submissions, Collection $reviewModules): Collection
    {
        $relatedModels = $this->loadRelatedModels($submissions);

        return $submissions->map(function (WorkflowSubmission $submission) use ($user, $relatedModels, $reviewModules) {
            $model = $relatedModels->get($this->relatedKey($submission->module, (int) $submission->related_id));
            $context = $this->contextFor($model, $submission->module);
            $canReview = $reviewModules->contains($submission->module) && in_array($submission->status, self::REVIEW_STATUSES, true);

            return [
                'id' => $submission->id,
                'related_id' => (int) $submission->related_id,
                'module' => $submission->module,
                'module_label' => $this->registry->label($submission->module),
                'status' => $submission->status,
                'note' => $submission->note,
                'submitted_at' => $submission->submitted_at?->toDateTimeString(),
                'reviewed_at' => $submission->reviewed_at?->toDateTimeString(),
                'updated_at' => $submission->updated_at?->toDateTimeString(),
                'submitted_by' => $submission->submittedBy ? [
                    'id' => $submission->submittedBy->id,
                    'name' => $submission->submittedBy->name,
                ] : null,
                'current_reviewer' => $submission->currentReviewer ? [
                    'id' => $submission->currentReviewer->id,
                    'name' => $submission->currentReviewer->name,
                ] : null,
                'context' => $context,
                'can_manage' => $model ? $user->can('update', $model) : false,
                'can_review' => $canReview,
                'can_lock' => ($user->isSuperAdmin() || $user->hasPermission('lock_period')) && $submission->status === 'approved',
                '_submitted_by_id' => $submission->submitted_by,
                '_current_reviewer_id' => $submission->current_reviewer_id,
                '_opd_id' => $context['opd_id'],
            ];
        });
    }

    /**
     * @param  Collection<int, WorkflowSubmission>  $submissions
     * @return Collection<string, Model>
     */
    private function loadRelatedModels(Collection $submissions): Collection
    {
        $models = collect();

        $submissions
            ->groupBy('module')
            ->each(function (Collection $group, string $module) use ($models) {
                $modelClass = $this->registry->modelClass($module);
                $ids = $group->pluck('related_id')->filter()->unique()->values();

                if ($ids->isEmpty()) {
                    return;
                }

                $query = $modelClass::query()->whereKey($ids);
                $relations = $this->relationsFor($module, $modelClass);

                if ($relations !== []) {
                    $query->with($relations);
                }

                $query->get()->each(function (Model $model) use ($models, $module) {
                    $models->put($this->relatedKey($module, (int) $model->getKey()), $model);
                });
            });

        return $models;
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, string>
     */
    private function relationsFor(string $module, string $modelClass): array
    {
        if ($module === 'tindak_lanjut_rekomendasi') {
            return ['opd:id,kode,nama,singkatan', 'rekomendasi.evaluasiSakip:id'];
        }

        return method_exists($modelClass, 'opd') ? ['opd:id,kode,nama,singkatan'] : [];
    }

    private function relatedKey(string $module, int $id): string
    {
        return "{$module}:{$id}";
    }

    /**
     * @return array<string, mixed>
     */
    private function contextFor(?Model $model, string $module): array
    {
        if (! $model) {
            return [
                'title' => 'Data tidak ditemukan',
                'subtitle' => 'Objek workflow sudah tidak tersedia.',
                'opd_id' => null,
                'opd' => null,
                'tahun' => null,
                'status_data' => null,
                'detail_url' => null,
                'missing' => true,
            ];
        }

        $opd = $model->relationLoaded('opd') && $model->getRelation('opd') ? [
            'id' => $model->getRelation('opd')->id,
            'kode' => $model->getRelation('opd')->kode,
            'nama' => $model->getRelation('opd')->nama,
            'singkatan' => $model->getRelation('opd')->singkatan,
        ] : null;

        return [
            'title' => $this->titleFor($model, $module),
            'subtitle' => $this->subtitleFor($model, $module),
            'opd_id' => $opd['id'] ?? ($model->opd_id ?? null),
            'opd' => $opd,
            'tahun' => $this->yearFor($model, $module),
            'status_data' => $model->status ?? $model->status_tindak_lanjut ?? null,
            'detail_url' => $this->detailUrlFor($model, $module),
            'missing' => false,
        ];
    }

    private function titleFor(Model $model, string $module): string
    {
        return match ($module) {
            'rpjmd' => (string) ($model->judul ?? 'RPJMD'),
            'renstra_opd' => (string) ($model->judul ?? 'Renstra OPD'),
            'perjanjian_kinerja' => (string) ($model->judul ?? 'Perjanjian Kinerja'),
            'rencana_aksi' => (string) ($model->judul ?? 'Rencana Aksi'),
            'realisasi_kinerja' => 'Realisasi Kinerja '.$this->periodLabel($model),
            'evaluasi_sakip' => 'Evaluasi SAKIP '.($model->tahun ?? ''),
            'lkjip' => (string) ($model->judul ?? 'LKJIP'),
            'tindak_lanjut_rekomendasi' => Str::limit((string) ($model->uraian_tindak_lanjut ?? 'Tindak Lanjut Rekomendasi'), 90),
            default => $this->registry->label($module),
        };
    }

    private function subtitleFor(Model $model, string $module): ?string
    {
        return match ($module) {
            'rpjmd', 'renstra_opd' => trim(($model->tahun_awal ?? '').'-'.($model->tahun_akhir ?? ''), '-'),
            'realisasi_kinerja' => ucfirst((string) ($model->periode_realisasi ?? '')).' '.$this->periodLabel($model),
            'evaluasi_sakip' => $model instanceof EvaluasiSakip && $model->nilai_akhir !== null
                ? 'Nilai akhir '.$model->nilai_akhir
                : null,
            default => null,
        };
    }

    private function yearFor(Model $model, string $module): ?string
    {
        if (in_array($module, ['rpjmd', 'renstra_opd'], true)) {
            return trim(($model->tahun_awal ?? '').'-'.($model->tahun_akhir ?? ''), '-') ?: null;
        }

        return isset($model->tahun) ? (string) $model->tahun : null;
    }

    private function periodLabel(Model $model): string
    {
        if ($model instanceof RealisasiKinerja) {
            return match ($model->periode_realisasi) {
                'triwulan' => strtoupper((string) $model->triwulan).' '.$model->tahun,
                'bulanan' => 'Bulan '.($model->bulan ?? '-').' '.$model->tahun,
                'semester' => 'Semester '.($model->semester ?? '-').' '.$model->tahun,
                'tahunan' => (string) $model->tahun,
                default => (string) ($model->tahun ?? ''),
            };
        }

        return (string) ($model->tahun ?? '');
    }

    private function detailUrlFor(Model $model, string $module): ?string
    {
        return match ($module) {
            'rpjmd' => route('rpjmd.show', $model),
            'renstra_opd' => route('renstra-opd.show', $model),
            'perjanjian_kinerja' => route('perjanjian-kinerja.show', $model),
            'rencana_aksi' => route('rencana-aksi.show', $model),
            'realisasi_kinerja' => route('realisasi-kinerja.show', $model),
            'evaluasi_sakip' => route('evaluasi-sakip.show', $model),
            'lkjip' => route('lkjip.show', $model),
            'tindak_lanjut_rekomendasi' => $this->tindakLanjutDetailUrl($model),
            default => null,
        };
    }

    private function tindakLanjutDetailUrl(Model $model): ?string
    {
        if (! $model instanceof TindakLanjutRekomendasi || ! $model->relationLoaded('rekomendasi')) {
            return null;
        }

        $evaluasi = $model->rekomendasi?->evaluasiSakip;

        return $evaluasi ? route('evaluasi-sakip.show', $evaluasi) : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function isVisibleForScope(User $user, array $row, string $scope): bool
    {
        if ($scope === 'all') {
            return $this->canMonitorAll($user);
        }

        if ($scope === 'mine') {
            return (int) $row['_submitted_by_id'] === (int) $user->id
                || ($user->opd_id !== null && (int) $row['_opd_id'] === (int) $user->opd_id);
        }

        if (! in_array($row['status'], self::REVIEW_STATUSES, true)) {
            return false;
        }

        return $this->reviewModules($user)->contains($row['module']);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function filterSearch(Collection $rows, string $search): Collection
    {
        $needle = Str::lower($search);

        return $rows->filter(function (array $row) use ($needle) {
            $haystacks = [
                $row['module_label'],
                $row['context']['title'] ?? '',
                $row['context']['subtitle'] ?? '',
                $row['context']['opd']['nama'] ?? '',
                $row['context']['opd']['singkatan'] ?? '',
                $row['submitted_by']['name'] ?? '',
                $row['current_reviewer']['name'] ?? '',
            ];

            return collect($haystacks)
                ->filter()
                ->contains(fn (string $value) => Str::contains(Str::lower($value), $needle));
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function paginate(Collection $rows): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $perPage = 15;
        $items = $rows->slice(($page - 1) * $perPage, $perPage)->values()
            ->map(fn (array $row) => collect($row)->except(['_submitted_by_id', '_current_reviewer_id', '_opd_id'])->all());

        return new LengthAwarePaginator(
            $items,
            $rows->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, int>
     */
    private function summary(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            'need_review' => $rows->whereIn('status', self::REVIEW_STATUSES)->count(),
            'submitted' => $rows->where('status', 'submitted')->count(),
            'verified' => $rows->where('status', 'verified')->count(),
            'revision' => $rows->where('status', 'revision')->count(),
            'approved' => $rows->where('status', 'approved')->count(),
            'rejected' => $rows->where('status', 'rejected')->count(),
            'locked' => $rows->where('status', 'locked')->count(),
        ];
    }
}
