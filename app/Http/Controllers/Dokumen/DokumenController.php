<?php

namespace App\Http\Controllers\Dokumen;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dokumen\StoreDokumenRequest;
use App\Http\Requests\Dokumen\UpdateDokumenRequest;
use App\Models\Dokumen;
use App\Models\DokumenRelation;
use App\Models\Opd;
use App\Models\PeriodeTahun;
use App\Models\PerjanjianKinerja;
use App\Models\RealisasiKinerja;
use App\Models\RencanaAksi;
use App\Models\RenstraOpd;
use App\Models\Rpjmd;
use App\Models\User;
use App\Services\Dokumen\DokumenStorageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DokumenController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Dokumen::class);

        $filters = $request->only(['search', 'jenis', 'status', 'opd_id', 'periode_tahun_id']);
        $user = $request->user();

        $dokumen = Dokumen::query()
            ->with(['opd:id,kode,nama,singkatan', 'periodeTahun:id,tahun,nama', 'uploadedBy:id,name', 'relations'])
            ->when(! $this->canViewAll($user), function (Builder $query) use ($user) {
                $query->where(function (Builder $query) use ($user) {
                    $query->where('uploaded_by', $user->id)
                        ->when($user->hasRole('admin_opd') && filled($user->opd_id), fn (Builder $query) => $query->orWhere('opd_id', $user->opd_id));
                });
            })
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('judul', 'like', "%{$search}%")
                        ->orWhere('nomor_dokumen', 'like', "%{$search}%")
                        ->orWhere('original_filename', 'like', "%{$search}%");
                });
            })
            ->when($filters['jenis'] ?? null, fn (Builder $query, string $jenis) => $query->where('jenis', $jenis))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['opd_id'] ?? null, fn (Builder $query, string $opdId) => $query->where('opd_id', $opdId))
            ->when($filters['periode_tahun_id'] ?? null, fn (Builder $query, string $periodeId) => $query->where('periode_tahun_id', $periodeId))
            ->latest('id')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Dokumen $dokumen) => $this->serializeDokumenRow($dokumen));

        return Inertia::render('Dokumen/Index', [
            'dokumen' => $dokumen,
            'filters' => $filters,
            'jenisOptions' => $this->jenisOptions(),
            'statusOptions' => $this->statusOptions(),
            'opdOptions' => $this->opdOptions($user),
            'periodeOptions' => $this->periodeOptions(),
            'can' => [
                'manage' => $user->can('create', Dokumen::class),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Dokumen::class);

        return Inertia::render('Dokumen/Form', [
            'mode' => 'create',
            'dokumen' => null,
            'jenisOptions' => $this->jenisOptions(),
            'statusOptions' => $this->statusOptions(),
            'opdOptions' => $this->opdOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
            'relationOptions' => $this->relationOptions($request->user()),
        ]);
    }

    public function store(StoreDokumenRequest $request, DokumenStorageService $storageService): RedirectResponse
    {
        $data = $request->validated();
        $relation = $this->resolveRelation($request->user(), $data['related_type'] ?? null, $data['related_id'] ?? null);

        if ($request->user()->hasRole('admin_opd') && blank($data['opd_id'] ?? null)) {
            $data['opd_id'] = $request->user()->opd_id;
        }

        $this->assertRelationMatchesOpd($relation['model'], $data['opd_id'] ?? null);

        $dokumen = $storageService->store($data, $request->file('file'), $request->user(), [
            'type' => $relation['type'],
            'id' => $relation['id'],
            'label' => $relation['label'],
        ]);

        return redirect()->route('dokumen.show', $dokumen)->with('success', 'Dokumen berhasil diunggah.');
    }

    public function show(Request $request, Dokumen $dokumen): Response
    {
        $this->authorize('view', $dokumen);

        $dokumen->load(['opd:id,kode,nama,singkatan', 'periodeTahun:id,tahun,nama', 'uploadedBy:id,name', 'relations.related']);

        return Inertia::render('Dokumen/Show', [
            'dokumen' => $this->serializeDokumenDetail($dokumen),
            'can' => [
                'manage' => $request->user()->can('update', $dokumen),
                'download' => $request->user()->can('download', $dokumen),
            ],
        ]);
    }

    public function edit(Request $request, Dokumen $dokumen): Response
    {
        $this->authorize('update', $dokumen);

        return Inertia::render('Dokumen/Form', [
            'mode' => 'edit',
            'dokumen' => [
                'id' => $dokumen->id,
                'opd_id' => $dokumen->opd_id,
                'periode_tahun_id' => $dokumen->periode_tahun_id,
                'jenis' => $dokumen->jenis,
                'judul' => $dokumen->judul,
                'nomor_dokumen' => $dokumen->nomor_dokumen,
                'deskripsi' => $dokumen->deskripsi,
                'status' => $dokumen->status,
            ],
            'jenisOptions' => $this->jenisOptions(),
            'statusOptions' => $this->statusOptions(),
            'opdOptions' => $this->opdOptions($request->user()),
            'periodeOptions' => $this->periodeOptions(),
            'relationOptions' => [],
        ]);
    }

    public function update(UpdateDokumenRequest $request, Dokumen $dokumen): RedirectResponse
    {
        $dokumen->update($request->validated());

        return redirect()->route('dokumen.show', $dokumen)->with('success', 'Metadata dokumen berhasil diperbarui.');
    }

    public function destroy(Dokumen $dokumen): RedirectResponse
    {
        $this->authorize('delete', $dokumen);

        $dokumen->delete();

        return redirect()->route('dokumen.index')->with('success', 'Dokumen berhasil dihapus.');
    }

    public function download(Dokumen $dokumen): StreamedResponse
    {
        $this->authorize('download', $dokumen);

        abort_unless(Storage::disk($dokumen->storage_disk)->exists($dokumen->storage_path), 404);

        return Storage::disk($dokumen->storage_disk)->download($dokumen->storage_path, $dokumen->original_filename, [
            'Content-Type' => $dokumen->mime_type ?: 'application/octet-stream',
        ]);
    }

    /**
     * @return array{type: class-string<Model>|null, id: int|null, label: string|null, model: Model|null}
     */
    private function resolveRelation(User $user, ?string $type, mixed $id): array
    {
        if (blank($type)) {
            return ['type' => null, 'id' => null, 'label' => null, 'model' => null];
        }

        $modelClass = $this->relationMap()[$type] ?? null;
        abort_unless($modelClass, 404);

        /** @var Model $model */
        $model = $modelClass::query()->findOrFail($id);

        if ($user->can('view', $model) === false) {
            throw ValidationException::withMessages(['related_id' => 'Anda tidak berwenang mengaitkan dokumen ke data tersebut.']);
        }

        return [
            'type' => $modelClass,
            'id' => (int) $model->getKey(),
            'label' => $this->relatedLabel($model),
            'model' => $model,
        ];
    }

    private function assertRelationMatchesOpd(?Model $model, mixed $opdId): void
    {
        if (! $model || blank($opdId) || ! isset($model->opd_id)) {
            return;
        }

        if ((int) $model->opd_id !== (int) $opdId) {
            throw ValidationException::withMessages(['opd_id' => 'OPD dokumen tidak sesuai dengan data yang dikaitkan.']);
        }
    }

    /**
     * @return array<string, class-string<Model>>
     */
    private function relationMap(): array
    {
        return [
            'rpjmd' => Rpjmd::class,
            'renstra_opd' => RenstraOpd::class,
            'perjanjian_kinerja' => PerjanjianKinerja::class,
            'rencana_aksi' => RencanaAksi::class,
            'realisasi_kinerja' => RealisasiKinerja::class,
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function jenisOptions(): array
    {
        return [
            ['value' => 'rpjmd', 'label' => 'RPJMD'],
            ['value' => 'renstra', 'label' => 'Renstra'],
            ['value' => 'renja', 'label' => 'Renja'],
            ['value' => 'iku', 'label' => 'IKU'],
            ['value' => 'ikd', 'label' => 'IKD'],
            ['value' => 'perjanjian_kinerja', 'label' => 'Perjanjian Kinerja'],
            ['value' => 'rencana_aksi', 'label' => 'Rencana Aksi'],
            ['value' => 'realisasi_kinerja', 'label' => 'Realisasi Kinerja'],
            ['value' => 'bukti_dukung', 'label' => 'Bukti Dukung'],
            ['value' => 'lkjip', 'label' => 'LKJIP'],
            ['value' => 'lke', 'label' => 'LKE'],
            ['value' => 'lhe', 'label' => 'LHE'],
            ['value' => 'rekomendasi', 'label' => 'Rekomendasi'],
            ['value' => 'tindak_lanjut', 'label' => 'Tindak Lanjut'],
            ['value' => 'lainnya', 'label' => 'Lainnya'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return [
            ['value' => 'draft', 'label' => 'Draft'],
            ['value' => 'submitted', 'label' => 'Diajukan'],
            ['value' => 'revision', 'label' => 'Revisi'],
            ['value' => 'verified', 'label' => 'Terverifikasi'],
            ['value' => 'approved', 'label' => 'Disetujui'],
            ['value' => 'rejected', 'label' => 'Ditolak'],
            ['value' => 'locked', 'label' => 'Terkunci'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function opdOptions(User $user): array
    {
        return Opd::query()
            ->where('status', 'active')
            ->when($user->hasRole('admin_opd') && filled($user->opd_id), fn (Builder $query) => $query->whereKey($user->opd_id))
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
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function relationOptions(User $user): array
    {
        return [
            'rpjmd' => Rpjmd::query()
                ->orderByDesc('tahun_awal')
                ->get(['id', 'judul', 'tahun_awal', 'tahun_akhir', 'status'])
                ->filter(fn (Rpjmd $rpjmd) => $user->can('view', $rpjmd))
                ->map(fn (Rpjmd $rpjmd) => ['id' => $rpjmd->id, 'label' => $this->relatedLabel($rpjmd)])
                ->values()
                ->all(),
            'renstra_opd' => RenstraOpd::query()
                ->with('opd:id,nama,singkatan')
                ->when($user->hasRole('admin_opd') && filled($user->opd_id), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
                ->orderByDesc('tahun_awal')
                ->get(['id', 'opd_id', 'judul', 'tahun_awal', 'tahun_akhir'])
                ->filter(fn (RenstraOpd $renstra) => $user->can('view', $renstra))
                ->map(fn (RenstraOpd $renstra) => ['id' => $renstra->id, 'label' => $this->relatedLabel($renstra)])
                ->values()
                ->all(),
            'perjanjian_kinerja' => PerjanjianKinerja::query()
                ->with('opd:id,nama,singkatan')
                ->when($user->hasRole('admin_opd') && filled($user->opd_id), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
                ->orderByDesc('tahun')
                ->get(['id', 'opd_id', 'judul', 'tahun'])
                ->filter(fn (PerjanjianKinerja $pk) => $user->can('view', $pk))
                ->map(fn (PerjanjianKinerja $pk) => ['id' => $pk->id, 'label' => $this->relatedLabel($pk)])
                ->values()
                ->all(),
            'rencana_aksi' => RencanaAksi::query()
                ->with('opd:id,nama,singkatan')
                ->when($user->hasRole('admin_opd') && filled($user->opd_id), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
                ->orderByDesc('tahun')
                ->get(['id', 'opd_id', 'judul', 'tahun'])
                ->filter(fn (RencanaAksi $rencanaAksi) => $user->can('view', $rencanaAksi))
                ->map(fn (RencanaAksi $rencanaAksi) => ['id' => $rencanaAksi->id, 'label' => $this->relatedLabel($rencanaAksi)])
                ->values()
                ->all(),
            'realisasi_kinerja' => RealisasiKinerja::query()
                ->with('opd:id,nama,singkatan')
                ->when($user->hasRole('admin_opd') && filled($user->opd_id), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
                ->orderByDesc('tahun')
                ->get(['id', 'opd_id', 'tahun', 'periode_realisasi', 'triwulan', 'bulan', 'semester'])
                ->filter(fn (RealisasiKinerja $realisasi) => $user->can('view', $realisasi))
                ->map(fn (RealisasiKinerja $realisasi) => ['id' => $realisasi->id, 'label' => $this->relatedLabel($realisasi)])
                ->values()
                ->all(),
        ];
    }

    private function serializeDokumenRow(Dokumen $dokumen): array
    {
        return [
            'id' => $dokumen->id,
            'jenis' => $dokumen->jenis,
            'judul' => $dokumen->judul,
            'nomor_dokumen' => $dokumen->nomor_dokumen,
            'status' => $dokumen->status,
            'original_filename' => $dokumen->original_filename,
            'mime_type' => $dokumen->mime_type,
            'file_size' => $dokumen->file_size,
            'file_hash' => $dokumen->file_hash,
            'created_at' => $dokumen->created_at?->toDateTimeString(),
            'opd' => $dokumen->opd,
            'periode_tahun' => $dokumen->periodeTahun,
            'uploaded_by' => $dokumen->uploadedBy,
            'relations_count' => $dokumen->relations->count(),
        ];
    }

    private function serializeDokumenDetail(Dokumen $dokumen): array
    {
        return [
            ...$this->serializeDokumenRow($dokumen),
            'deskripsi' => $dokumen->deskripsi,
            'storage_disk' => $dokumen->storage_disk,
            'metadata' => $dokumen->metadata,
            'download_url' => route('dokumen.download', $dokumen),
            'relations' => $dokumen->relations->map(fn (DokumenRelation $relation) => [
                'id' => $relation->id,
                'related_type' => $relation->related_type,
                'related_type_label' => $this->relationTypeLabel($relation->related_type),
                'related_id' => $relation->related_id,
                'label' => $relation->label ?: ($relation->related ? $this->relatedLabel($relation->related) : null),
            ]),
        ];
    }

    private function relatedLabel(Model $model): string
    {
        return match ($model::class) {
            Rpjmd::class => "{$model->tahun_awal}-{$model->tahun_akhir} - {$model->judul}",
            RenstraOpd::class => ($model->opd?->singkatan ? "{$model->opd->singkatan} - " : '')."{$model->tahun_awal}-{$model->tahun_akhir} - {$model->judul}",
            PerjanjianKinerja::class => ($model->opd?->singkatan ? "{$model->opd->singkatan} - " : '')."{$model->tahun} - {$model->judul}",
            RencanaAksi::class => ($model->opd?->singkatan ? "{$model->opd->singkatan} - " : '')."{$model->tahun} - {$model->judul}",
            RealisasiKinerja::class => ($model->opd?->singkatan ? "{$model->opd->singkatan} - " : '')."{$model->tahun} - {$model->periode_realisasi} ".($model->triwulan ?: $model->bulan ?: $model->semester ?: ''),
            default => (string) $model->getKey(),
        };
    }

    private function relationTypeLabel(string $class): string
    {
        return match ($class) {
            Rpjmd::class => 'RPJMD',
            RenstraOpd::class => 'Renstra OPD',
            PerjanjianKinerja::class => 'Perjanjian Kinerja',
            RencanaAksi::class => 'Rencana Aksi',
            RealisasiKinerja::class => 'Realisasi Kinerja',
            default => class_basename($class),
        };
    }

    private function canViewAll(User $user): bool
    {
        return $user->hasAnyRole([
            'super_admin',
            'admin_kabupaten_bagian_organisasi',
            'admin_kabupaten_bapperida',
            'admin_kabupaten_inspektorat',
        ]);
    }
}
