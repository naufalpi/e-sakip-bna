<?php

namespace App\Services\Perencanaan;

use App\Models\RenstraOpd;
use App\Models\Rpjmd;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DocumentRevisionService
{
    /**
     * Create a draft amendment without changing the currently active document.
     * All cascading records are cloned as a snapshot so the original document
     * remains auditable and can still be used until this amendment is approved.
     *
     * @param  array{alasan_perubahan: string, dasar_perubahan?: string|null, tanggal_berlaku?: string|null}  $metadata
     */
    public function createRpjmdRevision(Rpjmd $source, array $metadata, User $actor): Rpjmd
    {
        return DB::transaction(function () use ($source, $metadata, $actor): Rpjmd {
            /** @var Rpjmd $source */
            $source = Rpjmd::query()->lockForUpdate()->findOrFail($source->id);
            $rootId = $this->lockRootVersion($source);
            $this->ensureRevisionCanBeCreated($source, $rootId);

            $revision = new Rpjmd;
            $revision->forceFill($this->rootAttributes($source->getAttributes(), $metadata, $source, $actor, $rootId));
            $revision->save();

            $this->cloneRpjmdTree($source->id, $revision->id);

            return $revision->fresh();
        });
    }

    /**
     * @param  array{alasan_perubahan: string, dasar_perubahan?: string|null, tanggal_berlaku?: string|null}  $metadata
     */
    public function createRenstraRevision(RenstraOpd $source, array $metadata, User $actor): RenstraOpd
    {
        return DB::transaction(function () use ($source, $metadata, $actor): RenstraOpd {
            /** @var RenstraOpd $source */
            $source = RenstraOpd::query()->lockForUpdate()->findOrFail($source->id);
            $rootId = $this->lockRootVersion($source);
            $this->ensureRevisionCanBeCreated($source, $rootId);

            $attributes = $this->rootAttributes($source->getAttributes(), $metadata, $source, $actor, $rootId);

            // A Renstra amendment stays a separate snapshot, but when it is
            // created because its RPJMD has changed, its new draft must point
            // at that approved RPJMD version. The source Renstra remains
            // untouched for audit and historical reporting.
            if ($source->perlu_penyesuaian_rpjmd && $source->rpjmd_perubahan_terbaru_id !== null) {
                $attributes = array_merge($attributes, [
                    'rpjmd_id' => $source->rpjmd_perubahan_terbaru_id,
                    'perlu_penyesuaian_rpjmd' => false,
                    'rpjmd_perubahan_terbaru_id' => null,
                    'rpjmd_penyesuaian_terdeteksi_pada' => null,
                ]);
            }

            $revision = new RenstraOpd;
            $revision->forceFill($attributes);
            $revision->save();

            $this->cloneRenstraTree($source->id, $revision->id);

            return $revision->fresh();
        });
    }

    private function cloneRpjmdTree(int $sourceRpjmdId, int $revisionRpjmdId): void
    {
        $visi = $this->cloneRows('rpjmd_visi', 'rpjmd_id', [$sourceRpjmdId => $revisionRpjmdId]);
        $misi = $this->cloneRows('rpjmd_misi', 'rpjmd_id', [$sourceRpjmdId => $revisionRpjmdId], [
            'rpjmd_visi_id' => $visi,
        ]);
        $tujuan = $this->cloneTujuanDaerah($visi, $misi);
        $this->cloneRows('tujuan_daerah_misi', 'tujuan_daerah_id', $tujuan, [
            'rpjmd_misi_id' => $misi,
        ]);

        $indikatorTujuan = $this->cloneRows('indikator_tujuan_daerah', 'tujuan_daerah_id', $tujuan);
        $this->cloneRows('target_indikator_tujuan_daerah', 'indikator_tujuan_daerah_id', $indikatorTujuan);
        $this->cloneTriwulanTargets('indikator_tujuan_daerah', $indikatorTujuan);

        $sasaran = $this->cloneRows('sasaran_daerah', 'tujuan_daerah_id', $tujuan);
        $this->cloneRows('sasaran_daerah_indikator_tujuan', 'sasaran_daerah_id', $sasaran, [
            'indikator_tujuan_daerah_id' => $indikatorTujuan,
        ]);

        $indikatorSasaran = $this->cloneRows('indikator_sasaran_daerah', 'sasaran_daerah_id', $sasaran);
        $this->cloneRows('target_indikator_sasaran_daerah', 'indikator_sasaran_daerah_id', $indikatorSasaran);
        $this->cloneTriwulanTargets('indikator_sasaran_daerah', $indikatorSasaran);

        $program = $this->clonePrograms($sasaran, $indikatorSasaran);
        $this->cloneRows('program_rpjmd_program_pemerintahan', 'program_rpjmd_id', $program);
        $this->cloneRows('program_rpjmd_opd_penanggung_jawab', 'program_rpjmd_id', $program);
        $this->cloneRows('pagu_program_rpjmd', 'program_rpjmd_id', $program);

        $indikatorProgram = $this->cloneRows('indikator_program_rpjmd', 'program_rpjmd_id', $program);
        $this->cloneRows('target_indikator_program_rpjmd', 'indikator_program_rpjmd_id', $indikatorProgram);
        $this->cloneTriwulanTargets('indikator_program_rpjmd', $indikatorProgram);
        $this->cloneRows('indikator_program_rpjmd_opd_pengampu', 'indikator_program_rpjmd_id', $indikatorProgram);
    }

    private function cloneRenstraTree(int $sourceRenstraId, int $revisionRenstraId): void
    {
        $tujuan = $this->cloneRows('tujuan_opd', 'renstra_opd_id', [$sourceRenstraId => $revisionRenstraId]);
        $indikatorTujuan = $this->cloneRows('indikator_tujuan_opd', 'tujuan_opd_id', $tujuan);
        $this->cloneRows('target_indikator_tujuan_opd', 'indikator_tujuan_opd_id', $indikatorTujuan);
        $this->cloneTriwulanTargets('indikator_tujuan_opd', $indikatorTujuan);

        $sasaran = $this->cloneRows('sasaran_opd', 'tujuan_opd_id', $tujuan);
        $indikatorSasaran = $this->cloneRows('indikator_sasaran_opd', 'sasaran_opd_id', $sasaran);
        $this->cloneRows('target_indikator_sasaran_opd', 'indikator_sasaran_opd_id', $indikatorSasaran);
        $this->cloneTriwulanTargets('indikator_sasaran_opd', $indikatorSasaran);

        $program = $this->cloneRows('opd_program', 'renstra_opd_id', [$sourceRenstraId => $revisionRenstraId], [
            'sasaran_opd_id' => $sasaran,
        ]);
        $indikatorProgram = $this->cloneRows('indikator_opd_program', 'opd_program_id', $program);
        $this->cloneRows('target_indikator_opd_program', 'indikator_opd_program_id', $indikatorProgram);
        $this->cloneTriwulanTargets('indikator_opd_program', $indikatorProgram);

        $kegiatan = $this->cloneRows('opd_kegiatan', 'opd_program_id', $program);
        $indikatorKegiatan = $this->cloneRows('indikator_opd_kegiatan', 'opd_kegiatan_id', $kegiatan);
        $this->cloneRows('target_indikator_opd_kegiatan', 'indikator_opd_kegiatan_id', $indikatorKegiatan);
        $this->cloneTriwulanTargets('indikator_opd_kegiatan', $indikatorKegiatan);

        $subKegiatan = $this->cloneRows('opd_sub_kegiatan', 'opd_kegiatan_id', $kegiatan);
        $indikatorSubKegiatan = $this->cloneRows('indikator_sub_kegiatan', 'opd_sub_kegiatan_id', $subKegiatan);
        $this->cloneRows('target_indikator_sub_kegiatan', 'indikator_sub_kegiatan_id', $indikatorSubKegiatan);
        $this->cloneTriwulanTargets('indikator_sub_kegiatan', $indikatorSubKegiatan);
        $this->cloneRows('anggaran_sub_kegiatan_renstra', 'opd_sub_kegiatan_id', $subKegiatan);
    }

    /**
     * Tujuan daerah supports two RPJMD structures: a purpose can belong directly
     * to a vision (Banjarnegara) or to a mission (the general structure).
     * Clone both references instead of assuming the vision-based structure.
     *
     * @param  array<int, int>  $visiMap
     * @param  array<int, int>  $misiMap
     * @return array<int, int>
     */
    private function cloneTujuanDaerah(array $visiMap, array $misiMap): array
    {
        if ($visiMap === [] && $misiMap === []) {
            return [];
        }

        $query = $this->activeRows('tujuan_daerah');
        $query->where(function (Builder $nested) use ($visiMap, $misiMap): void {
            if ($visiMap !== []) {
                $nested->whereIn('rpjmd_visi_id', array_keys($visiMap));
            }

            if ($misiMap !== []) {
                $method = $visiMap !== [] ? 'orWhereIn' : 'whereIn';
                $nested->{$method}('rpjmd_misi_id', array_keys($misiMap));
            }
        });

        $map = [];
        foreach ($query->orderBy('id')->get() as $row) {
            $map[(int) $row->id] = $this->copyRow('tujuan_daerah', $row, [
                'rpjmd_visi_id' => $this->mappedValue($visiMap, $row->rpjmd_visi_id ?? null),
                'rpjmd_misi_id' => $this->mappedValue($misiMap, $row->rpjmd_misi_id ?? null),
            ]);
        }

        return $map;
    }

    /** @param array<int, int> $sasaranMap @param array<int, int> $indikatorSasaranMap */
    private function clonePrograms(array $sasaranMap, array $indikatorSasaranMap): array
    {
        if ($sasaranMap === [] && $indikatorSasaranMap === []) {
            return [];
        }

        $query = $this->activeRows('program_rpjmd');
        $query->where(function (Builder $nested) use ($sasaranMap, $indikatorSasaranMap): void {
            if ($sasaranMap !== []) {
                $nested->whereIn('sasaran_daerah_id', array_keys($sasaranMap));
            }

            if ($indikatorSasaranMap !== []) {
                $method = $sasaranMap !== [] ? 'orWhereIn' : 'whereIn';
                $nested->{$method}('indikator_sasaran_daerah_id', array_keys($indikatorSasaranMap));
            }
        });

        $map = [];
        foreach ($query->orderBy('id')->get() as $row) {
            $map[(int) $row->id] = $this->copyRow('program_rpjmd', $row, [
                'sasaran_daerah_id' => $this->mappedValue($sasaranMap, $row->sasaran_daerah_id ?? null),
                'indikator_sasaran_daerah_id' => $this->mappedValue($indikatorSasaranMap, $row->indikator_sasaran_daerah_id ?? null),
            ]);
        }

        return $map;
    }

    /**
     * @param  array<int, int>  $parentMap
     * @param  array<string, array<int, int>>  $additionalForeignMaps
     * @return array<int, int>
     */
    private function cloneRows(string $table, string $foreignKey, array $parentMap, array $additionalForeignMaps = []): array
    {
        if ($parentMap === [] || ! Schema::hasTable($table)) {
            return [];
        }

        $map = [];
        foreach ($this->activeRows($table)->whereIn($foreignKey, array_keys($parentMap))->orderBy('id')->get() as $row) {
            $overrides = [$foreignKey => $parentMap[(int) $row->{$foreignKey}]];

            foreach ($additionalForeignMaps as $column => $foreignMap) {
                $overrides[$column] = $this->mappedValue($foreignMap, $row->{$column} ?? null);
            }

            $map[(int) $row->id] = $this->copyRow($table, $row, $overrides);
        }

        return $map;
    }

    /** @param array<int, int> $indicatorMap */
    private function cloneTriwulanTargets(string $relatedTable, array $indicatorMap): void
    {
        if ($indicatorMap === [] || ! Schema::hasTable('target_triwulan_indikator')) {
            return;
        }

        foreach (DB::table('target_triwulan_indikator')
            ->where('related_table', $relatedTable)
            ->whereIn('related_id', array_keys($indicatorMap))
            ->orderBy('id')
            ->get() as $row) {
            $this->copyRow('target_triwulan_indikator', $row, [
                'related_id' => $indicatorMap[(int) $row->related_id],
            ]);
        }
    }

    private function activeRows(string $table): Builder
    {
        $query = DB::table($table);

        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    private function copyRow(string $table, object $row, array $overrides = []): int
    {
        $attributes = (array) $row;
        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at'], $attributes['deleted_at']);

        $attributes = array_merge($attributes, $overrides, [
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return (int) DB::table($table)->insertGetId($attributes);
    }

    /** @param array<int, int> $map */
    private function mappedValue(array $map, mixed $sourceId): ?int
    {
        if ($sourceId === null) {
            return null;
        }

        return $map[(int) $sourceId] ?? null;
    }

    /** @param array<string, mixed> $attributes @param array<string, mixed> $metadata */
    private function rootAttributes(array $attributes, array $metadata, Rpjmd|RenstraOpd $source, User $actor, int $rootId): array
    {
        unset($attributes['id'], $attributes['created_at'], $attributes['updated_at'], $attributes['deleted_at']);

        return array_merge($attributes, [
            'status' => 'draft',
            'jenis_versi' => 'perubahan',
            'nomor_versi' => $this->nextVersionNumber($source::class, $rootId),
            'parent_version_id' => $source->id,
            'root_version_id' => $rootId,
            'is_active_version' => false,
            'alasan_perubahan' => $metadata['alasan_perubahan'],
            'dasar_perubahan' => $metadata['dasar_perubahan'] ?? null,
            'tanggal_berlaku' => $metadata['tanggal_berlaku'] ?? null,
            'disahkan_oleh' => null,
            'disahkan_pada' => null,
        ]);
    }

    private function lockRootVersion(Rpjmd|RenstraOpd $source): int
    {
        $modelClass = $source::class;
        $rootId = $source->root_version_id ?? $source->id;

        while ($parentId = $modelClass::query()->whereKey($rootId)->value('parent_version_id')) {
            $rootId = (int) $parentId;
        }

        $modelClass::query()->lockForUpdate()->findOrFail($rootId);

        return $rootId;
    }

    private function ensureRevisionCanBeCreated(Rpjmd|RenstraOpd $source, int $rootId): void
    {
        if (! $source->is_active_version || ! in_array($source->status, ['approved', 'locked'], true)) {
            throw ValidationException::withMessages([
                'document' => 'Perubahan hanya dapat dibuat dari dokumen aktif yang sudah disetujui atau terkunci.',
            ]);
        }

        $hasOpenRevision = $source::query()
            ->where('root_version_id', $rootId)
            ->where('jenis_versi', 'perubahan')
            ->whereIn('status', ['draft', 'submitted', 'revision', 'verified'])
            ->exists();

        if ($hasOpenRevision) {
            throw ValidationException::withMessages([
                'document' => 'Masih ada dokumen Perubahan yang belum selesai. Selesaikan atau tolak Perubahan tersebut sebelum membuat Perubahan baru.',
            ]);
        }
    }

    /** @param class-string<Rpjmd|RenstraOpd> $modelClass */
    private function nextVersionNumber(string $modelClass, int $rootId): int
    {
        return ((int) $modelClass::query()->where('root_version_id', $rootId)->max('nomor_versi')) + 1;
    }
}
