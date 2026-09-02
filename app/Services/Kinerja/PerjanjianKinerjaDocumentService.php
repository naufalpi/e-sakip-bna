<?php

namespace App\Services\Kinerja;

use App\Models\DpaOpd;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\PerjanjianKinerja;
use App\Models\PerjanjianKinerjaItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class PerjanjianKinerjaDocumentService
{
    public function __construct(private readonly KopDokumenService $kopService) {}

    public function build(PerjanjianKinerja $pk): array
    {
        $pk->loadMissing([
            'opd:id,kode,nama,singkatan,alamat,telepon,email',
            'periodeTahun:id,tahun,nama',
            'pegawai:id,nama,nip,pangkat_golongan',
            'atasanPegawai:id,nama,nip,pangkat_golongan',
            'penempatanPegawai.jabatanOrganisasi:id,opd_unit_id,parent_id,nama,level_jabatan',
            'penempatanPegawai.jabatanOrganisasi.opdUnit:id,nama',
            'penempatanPegawai.jabatanOrganisasi.parent:id,nama,level_jabatan',
            'items.satuanIndikator:id,nama,simbol',
            'programs',
        ]);

        $isBupati = $pk->level_pk === 'bupati';
        $isLowerCascading = $pk->level_pk === 'individu' && $pk->tipe_pk === 'cascading';
        $isManualIndividual = $pk->level_pk === 'individu' && $pk->tipe_pk === 'individual';
        $directSupervisorPosition = $pk->penempatanPegawai?->jabatanOrganisasi?->parent;
        $firstParty = [
            'name' => $pk->nama_pegawai_snapshot ?: $pk->pegawai?->nama ?: '(nama pejabat)',
            'nip' => $pk->nip_snapshot ?: $pk->pegawai?->nip,
            'rank' => $pk->pegawai?->pangkat_golongan,
            'position' => $pk->jabatan_snapshot ?: $pk->penempatanPegawai?->jabatanOrganisasi?->nama ?: ($isBupati ? 'Bupati Banjarnegara' : 'Pimpinan Perangkat Daerah'),
        ];
        $secondPosition = $pk->jabatan_atasan_snapshot;
        if ($directSupervisorPosition
            && (blank($secondPosition)
                || (str_contains(mb_strtolower((string) $secondPosition), 'bupati')
                    && $directSupervisorPosition->level_jabatan !== 'kepala_daerah'))) {
            $secondPosition = $directSupervisorPosition->nama;
        }

        $secondParty = $isBupati ? null : [
            'name' => $pk->nama_atasan_snapshot ?: $pk->atasanPegawai?->nama ?: '(nama atasan)',
            'nip' => $pk->nip_atasan_snapshot ?: $pk->atasanPegawai?->nip,
            'rank' => $pk->atasanPegawai?->pangkat_golongan,
            'position' => $secondPosition ?: ($pk->level_pk === 'kepala_opd' ? 'Bupati Banjarnegara' : 'Atasan Langsung'),
        ];

        $date = $pk->tanggal_dokumen ?: $pk->created_at;
        $placeDate = ($pk->tempat_penandatanganan ?: 'Banjarnegara').', '.($date?->translatedFormat('j F Y') ?: '....................');
        $kop = $pk->kop_dokumen_snapshot ?: $this->kopService->snapshotFor($pk);
        $programs = $pk->programs->map(fn ($program) => [
            'id' => $program->id,
            'code' => $program->kode,
            'name' => $program->nama_program,
            'budget' => (float) $program->anggaran,
            'budget_label' => $this->money($program->anggaran),
            'note' => $program->keterangan ?: 'APBD',
            'activities' => [],
        ])->values();

        if ($pk->level_pk === 'struktural' && $programs->isEmpty()) {
            $programs = $this->structuralPrograms($pk);
        }

        return [
            'id' => $pk->id,
            'is_bupati' => $isBupati,
            'is_structural' => $pk->level_pk === 'struktural',
            'is_lower_cascading' => $isLowerCascading,
            'is_manual_individual' => $isManualIndividual,
            'level' => $pk->level_pk,
            'level_label' => $pk->levelLabel(),
            'title' => 'PERJANJIAN KINERJA TAHUN '.$pk->tahun,
            'year' => $pk->tahun,
            'document_number' => $pk->nomor_dokumen,
            'place_date' => $placeDate,
            'agency_name' => $kop['nama_pemerintah'],
            'office_name' => $kop['nama_instansi'],
            'address' => $kop['alamat'],
            'telephone' => $kop['telepon'],
            'fax' => $kop['faksimile'],
            'website' => $kop['website'],
            'email' => $kop['email'],
            'city' => $kop['kota'],
            'postal_code' => $kop['kode_pos'],
            'logo_path' => $kop['logo_path'],
            'logo_data_uri' => $this->logoDataUri($kop['logo_path'] ?? null),
            'letterhead' => $kop,
            'contact' => collect([
                $kop['telepon'] ? 'Telepon '.$kop['telepon'] : null,
                $kop['faksimile'] ? 'Faksimile '.$kop['faksimile'] : null,
                $kop['website'],
                $kop['email'],
            ])->filter()->implode(' · '),
            'first_party' => $firstParty,
            'second_party' => $secondParty,
            'employee_name' => $firstParty['name'],
            'work_unit' => $pk->penempatanPegawai?->jabatanOrganisasi?->opdUnit?->nama ?: $pk->opd?->nama,
            'performance_groups' => $this->performanceGroups($pk->items, $pk->level_pk),
            'programs' => $programs->all(),
            'activity_budget_groups' => $isLowerCascading ? $this->lowerCascadingActivities($pk)->all() : [],
            'total_budget' => (float) $programs->sum('budget'),
            'total_budget_label' => $this->money($programs->sum('budget')),
            'missing_targets_count' => $pk->items
                ->where('jenis_item', '!=', 'program_opd')
                ->filter(fn (PerjanjianKinerjaItem $item) => blank($item->target_text) && $item->target === null)
                ->count(),
            'source_label' => $this->sourceLabel($pk),
        ];
    }

    private function performanceGroups(Collection $items, ?string $level): array
    {
        $groups = [];
        $sequence = 0;

        foreach ($items as $item) {
            if ($level === 'kepala_opd' && $item->jenis_item === 'program_opd') {
                continue;
            }

            $key = implode(':', [$item->jenis_item, $item->kode, $item->sasaran]);

            if (! isset($groups[$key])) {
                $isGoal = in_array($item->jenis_item, ['tujuan', 'tujuan_opd'], true);
                if (! $isGoal) {
                    $sequence++;
                }

                $groups[$key] = [
                    'number' => $isGoal ? null : $sequence,
                    'type' => $item->jenis_item,
                    'type_label' => $this->itemTypeLabel($item->jenis_item),
                    'code' => $item->kode,
                    'performance' => $item->sasaran,
                    'indicators' => [],
                ];
            }

            $groups[$key]['indicators'][] = [
                'id' => $item->id,
                'name' => $item->indikator,
                'target' => filled($item->target_text) ? $item->target_text : $this->number($item->target),
                'unit' => $item->satuan_snapshot ?: $item->satuanIndikator?->simbol ?: $item->satuanIndikator?->nama ?: '-',
            ];
        }

        return array_values($groups);
    }

    private function itemTypeLabel(?string $type): string
    {
        return match ($type) {
            'tujuan' => 'Tujuan Kabupaten',
            'sasaran' => 'Sasaran Kabupaten',
            'tujuan_opd' => 'Tujuan OPD',
            'sasaran_opd' => 'Sasaran OPD',
            'program_opd' => 'Program OPD',
            'kegiatan', 'kegiatan_opd' => 'Kegiatan OPD',
            'sub_kegiatan', 'sub_kegiatan_opd' => 'Sub Kegiatan OPD',
            default => 'Hasil Kerja',
        };
    }

    private function sourceLabel(PerjanjianKinerja $pk): string
    {
        return match ($pk->sumber_data) {
            'rkpd' => 'RKPD resmi Tahun '.$pk->tahun,
            'dpa' => 'Renstra dan DPA/DPPA resmi Tahun '.$pk->tahun,
            'renstra_cascading' => 'Lingkup kinerja Renstra OPD Tahun '.$pk->tahun,
            'penugasan' => 'Penugasan pengampu kinerja',
            default => 'Input manual',
        };
    }

    private function structuralPrograms(PerjanjianKinerja $pk): Collection
    {
        $selection = collect($pk->lingkup_kinerja_snapshot ?? []);
        $idsFor = fn (string $type) => $selection
            ->filter(fn (string $key) => str_starts_with($key, $type.':'))
            ->map(fn (string $key) => (int) str($key)->after(':')->toString())
            ->filter()
            ->unique()
            ->values();

        $programIds = $idsFor('opd_program');
        $activityIds = $idsFor('opd_kegiatan');
        $subActivityIds = $idsFor('opd_sub_kegiatan');

        if ($subActivityIds->isNotEmpty()) {
            $activityIds = $activityIds
                ->merge(OpdSubKegiatan::query()->whereKey($subActivityIds)->pluck('opd_kegiatan_id'))
                ->unique()
                ->values();
        }

        $activities = OpdKegiatan::query()
            ->whereKey($activityIds)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get(['id', 'opd_program_id', 'kegiatan_pemerintahan_id', 'kode', 'nama', 'urutan']);
        $programIds = $programIds->merge($activities->pluck('opd_program_id'))->unique()->values();

        if ($programIds->isEmpty()) {
            return collect();
        }

        $programRows = OpdProgram::query()
            ->whereKey($programIds)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get(['id', 'program_pemerintahan_id', 'kode', 'nama', 'urutan']);
        $dpa = $this->officialDpaFor($pk);
        $dpaItems = $dpa ? $dpa->items()->get() : collect();

        return $programRows
            ->groupBy(fn (OpdProgram $program) => filled($program->program_pemerintahan_id)
                ? 'master:'.$program->program_pemerintahan_id
                : 'code:'.$this->normalizeReference($program->kode ?: $program->nama))
            ->map(function (Collection $equivalentPrograms) use ($activities, $dpaItems): array {
                /** @var OpdProgram $program */
                $program = $equivalentPrograms->first();
                $budgetItems = $dpaItems->filter(fn ($item) => $this->matchesDpaReference(
                    $item->program_pemerintahan_id,
                    $item->kode_program,
                    $program->program_pemerintahan_id,
                    $program->kode,
                ));
                $budget = (float) $budgetItems->sum(fn ($item) => (float) ($item->pagu_dpa ?? 0));
                $funding = $budgetItems
                    ->pluck('sumber_pendanaan')
                    ->flatMap(fn ($value) => preg_split('/[,;]+/', (string) $value) ?: [])
                    ->map(fn ($value) => trim((string) $value))
                    ->filter()
                    ->unique(fn ($value) => mb_strtolower($value))
                    ->values();

                return [
                    'id' => $program->id,
                    'code' => $program->kode,
                    'name' => $program->nama,
                    'budget' => $budget,
                    'budget_label' => $this->money($budget),
                    'note' => $funding->isNotEmpty() ? $funding->implode(', ') : 'APBD',
                    'activities' => $activities
                        ->whereIn('opd_program_id', $equivalentPrograms->pluck('id'))
                        ->unique(fn (OpdKegiatan $activity) => filled($activity->kegiatan_pemerintahan_id)
                            ? 'master:'.$activity->kegiatan_pemerintahan_id
                            : 'code:'.$this->normalizeReference($activity->kode ?: $activity->nama))
                        ->map(fn (OpdKegiatan $activity) => [
                            'id' => $activity->id,
                            'code' => $activity->kode,
                            'name' => $activity->nama,
                        ])->values()->all(),
                ];
            })->values();
    }

    private function lowerCascadingActivities(PerjanjianKinerja $pk): Collection
    {
        $selection = collect($pk->lingkup_kinerja_snapshot ?? []);
        $idsFor = fn (string $type) => $selection
            ->filter(fn (string $key) => str_starts_with($key, $type.':'))
            ->map(fn (string $key) => (int) str($key)->after(':')->toString())
            ->filter()
            ->unique()
            ->values();

        $selectedActivityIds = $idsFor('opd_kegiatan');
        $selectedSubActivityIds = $idsFor('opd_sub_kegiatan');
        $selectedSubActivities = OpdSubKegiatan::query()
            ->whereKey($selectedSubActivityIds)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get(['id', 'opd_kegiatan_id', 'sub_kegiatan_pemerintahan_id', 'kode', 'nama', 'urutan']);
        $activityIds = $selectedActivityIds
            ->merge($selectedSubActivities->pluck('opd_kegiatan_id'))
            ->unique()
            ->values();

        if ($activityIds->isEmpty()) {
            return collect();
        }

        $activities = OpdKegiatan::query()
            ->whereKey($activityIds)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get(['id', 'kegiatan_pemerintahan_id', 'kode', 'nama', 'urutan']);
        $dpa = $this->officialDpaFor($pk);
        $dpaItems = $dpa ? $dpa->items()->get() : collect();

        return $activities->map(function (OpdKegiatan $activity) use ($selectedSubActivities, $dpaItems): array {
            $subActivities = $selectedSubActivities->where('opd_kegiatan_id', $activity->id);
            $activityItems = $dpaItems->filter(fn ($item) => $this->matchesDpaReference(
                $item->kegiatan_pemerintahan_id,
                $item->kode_kegiatan,
                $activity->kegiatan_pemerintahan_id,
                $activity->kode,
            ));
            $selectedMasterSubIds = $subActivities->pluck('sub_kegiatan_pemerintahan_id')->filter();
            $selectedSubCodes = $subActivities->pluck('kode')->filter()->map(fn ($code) => $this->normalizeReference($code));
            $budgetItems = $selectedMasterSubIds->isNotEmpty() || $selectedSubCodes->isNotEmpty()
                ? $activityItems->filter(fn ($item) => $selectedMasterSubIds->contains($item->sub_kegiatan_pemerintahan_id)
                    || $selectedSubCodes->contains($this->normalizeReference($item->kode_sub_kegiatan)))
                : $activityItems;
            $budget = (float) $budgetItems->sum(fn ($item) => (float) ($item->pagu_dpa ?? 0));
            $funding = $budgetItems
                ->pluck('sumber_pendanaan')
                ->flatMap(fn ($value) => preg_split('/[,;]+/', (string) $value) ?: [])
                ->map(fn ($value) => trim((string) $value))
                ->filter()
                ->unique(fn ($value) => mb_strtolower($value))
                ->values();

            return [
                'id' => $activity->id,
                'code' => $activity->kode,
                'name' => $activity->nama,
                'budget' => $budget,
                'budget_label' => $this->money($budget),
                'note' => $funding->isNotEmpty() ? $funding->implode(', ') : 'APBD',
                'sub_activities' => $subActivities
                    ->map(function (OpdSubKegiatan $subActivity) use ($activityItems): array {
                        $budget = (float) $activityItems
                            ->filter(fn ($item) => $this->matchesDpaReference(
                                $item->sub_kegiatan_pemerintahan_id,
                                $item->kode_sub_kegiatan,
                                $subActivity->sub_kegiatan_pemerintahan_id,
                                $subActivity->kode,
                            ))
                            ->sum(fn ($item) => (float) ($item->pagu_dpa ?? 0));

                        return [
                            'id' => $subActivity->id,
                            'code' => $subActivity->kode,
                            'name' => $subActivity->nama,
                            'budget' => $budget,
                            'budget_label' => $this->money($budget),
                        ];
                    })->values()->all(),
            ];
        })->values();
    }

    private function officialDpaFor(PerjanjianKinerja $pk): ?DpaOpd
    {
        return DpaOpd::query()
            ->where('opd_id', $pk->opd_id)
            ->where('periode_tahun_id', $pk->periode_tahun_id)
            ->where('tahun', $pk->tahun)
            ->whereIn('status', ['approved', 'locked'])
            ->when($pk->renstra_opd_id, fn ($query) => $query
                ->whereHas('renjaOpd', fn ($query) => $query->where('renstra_opd_id', $pk->renstra_opd_id)))
            ->orderByRaw("CASE WHEN jenis_anggaran = 'perubahan' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first();
    }

    private function matchesDpaReference(mixed $sourceId, mixed $sourceCode, mixed $expectedId, mixed $expectedCode): bool
    {
        if (filled($sourceId) && filled($expectedId)) {
            return (int) $sourceId === (int) $expectedId;
        }

        return filled($sourceCode)
            && filled($expectedCode)
            && $this->normalizeReference($sourceCode) === $this->normalizeReference($expectedCode);
    }

    private function normalizeReference(mixed $value): string
    {
        return mb_strtolower(preg_replace('/\s+/', '', trim((string) $value)) ?? '');
    }

    private function number(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return rtrim(rtrim(number_format((float) $value, 4, ',', '.'), '0'), ',');
    }

    private function money(mixed $value): string
    {
        return 'Rp '.number_format((float) ($value ?? 0), 0, ',', '.');
    }

    private function logoDataUri(?string $path): ?string
    {
        if (! $path || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        $mime = Storage::disk('public')->mimeType($path) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode(Storage::disk('public')->get($path));
    }
}
