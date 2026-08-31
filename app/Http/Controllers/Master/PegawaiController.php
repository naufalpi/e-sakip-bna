<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StorePegawaiRequest;
use App\Http\Requests\Master\UpdatePegawaiRequest;
use App\Models\JabatanOrganisasi;
use App\Models\Opd;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\OpdUnit;
use App\Models\Pegawai;
use App\Models\PenugasanPengampuKinerja;
use App\Models\PeriodeTahun;
use App\Models\RiwayatPejabatJabatan;
use App\Models\SasaranOpd;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PegawaiController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('pegawai.view'), 403);

        $user = $request->user();
        $filters = $request->only(['search', 'opd_id', 'jenis_pegawai', 'status']);
        $baseQuery = $this->scopedQuery($user);
        $today = now()->toDateString();

        $items = (clone $baseQuery)
            ->with([
                'opd:id,kode,nama,singkatan',
                'opdUnit:id,opd_id,kode,nama',
                'user:id,name,username,email',
                'penempatan' => fn ($query) => $query
                    ->whereDate('tanggal_mulai', '<=', $today)
                    ->where(fn ($query) => $query->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $today))
                    ->with('jabatanOrganisasi:id,nama,level_jabatan,opd_id,opd_unit_id,verification_status')
                    ->orderByDesc('tanggal_mulai'),
            ])
            ->withCount('penempatan')
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(fn (Builder $query) => $query
                    ->where('nama', 'ilike', "%{$search}%")
                    ->orWhere('nip', 'ilike', "%{$search}%")
                    ->orWhereHas('penempatan.jabatanOrganisasi', fn (Builder $query) => $query->where('nama', 'ilike', "%{$search}%")));
            })
            ->when(($filters['opd_id'] ?? null) && ! $this->shouldLimitToUserOpd($user), fn (Builder $query, string $opdId) => $query->where('opd_id', $opdId))
            ->when($filters['jenis_pegawai'] ?? null, fn (Builder $query, string $jenis) => $query->where('jenis_pegawai', $jenis))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Pegawai $pegawai) => $this->serialize($pegawai));

        return Inertia::render('Master/Pegawai/Index', [
            'items' => $items,
            'filters' => $filters,
            'opdOptions' => $this->opdOptions($user),
            'jenisOptions' => Pegawai::jenisOptions(),
            'stats' => [
                'total' => (clone $baseQuery)->count(),
                'active' => (clone $baseQuery)->where('status', 'active')->count(),
                'withPlacement' => (clone $baseQuery)->whereHas('penempatan', fn (Builder $query) => $this->currentPlacementConstraint($query, $today))->count(),
            ],
            'can' => [
                'manage' => $user->hasPermission('pegawai.manage'),
                'manage_jobs' => $user->hasPermission('jabatan_organisasi.manage')
                    || $user->hasPermission('jabatan_organisasi.manage_opd'),
                'opd_scoped' => $this->shouldLimitToUserOpd($user),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('pegawai.manage'), 403);

        return Inertia::render('Master/Pegawai/Form', $this->formProps($request->user()));
    }

    public function store(StorePegawaiRequest $request): RedirectResponse
    {
        $data = $this->normalizeScopedData($request->user(), $request->validated());
        $placementData = Arr::only($data, [
            'jabatan_organisasi_id',
            'jenis_penugasan',
            'nomor_sk',
            'tanggal_sk',
            'tanggal_mulai',
            'tanggal_selesai',
        ]);
        $data = Arr::except($data, array_keys($placementData));

        $jabatan = null;
        if (filled($placementData['jabatan_organisasi_id'] ?? null)) {
            $jabatan = $this->resolveInitialJabatan($request->user(), $data, (int) $placementData['jabatan_organisasi_id']);
            $this->assertInitialPlacementAvailable($jabatan, $placementData);
            $data['opd_id'] = $jabatan->opd_id;
            $data['opd_unit_id'] = $jabatan->opd_unit_id;
        }

        $this->assertReferencesMatch($data);
        $pegawai = DB::transaction(function () use ($data, $placementData, $jabatan): Pegawai {
            $pegawai = Pegawai::create($data);

            if ($jabatan) {
                $pegawai->penempatan()->create([
                    ...$placementData,
                    'pegawai_id' => $pegawai->id,
                    'user_id' => $pegawai->user_id,
                    'nama_pejabat' => $pegawai->nama,
                    'nip' => $pegawai->nip,
                    'pangkat_golongan' => $pegawai->pangkat_golongan,
                ]);
            }

            return $pegawai;
        });

        return redirect()->route('master.pegawai.show', $pegawai)->with(
            'success',
            $jabatan ? 'Pegawai dan jabatan berhasil disimpan.' : 'Pegawai berhasil ditambahkan.'
        );
    }

    public function show(Request $request, Pegawai $pegawai): Response
    {
        abort_unless($request->user()->hasPermission('pegawai.view'), 403);
        $this->abortUnlessInScope($request->user(), $pegawai);

        $pegawai->load([
            'opd:id,kode,nama,singkatan',
            'opdUnit:id,opd_id,kode,nama',
            'user:id,name,username,email',
            'penempatan.jabatanOrganisasi:id,opd_id,opd_unit_id,parent_id,nama,level_jabatan,eselon,status,verification_status',
            'penugasanKinerja.periodeTahun:id,tahun,nama',
            'penugasanKinerja.penempatan.jabatanOrganisasi:id,nama,level_jabatan',
        ]);

        $canManage = $request->user()->hasPermission('pegawai.manage');

        return Inertia::render('Master/Pegawai/Show', [
            'item' => $this->serialize($pegawai, true),
            'jabatanOptions' => $this->jabatanOptions($request->user(), $pegawai),
            'penugasanOptions' => RiwayatPejabatJabatan::penugasanOptions(),
            'periodeOptions' => PeriodeTahun::query()->orderByDesc('tahun')->get(['id', 'tahun', 'nama'])->map(fn (PeriodeTahun $periode) => [
                'id' => $periode->id,
                'tahun' => $periode->tahun,
                'label' => "{$periode->tahun} - {$periode->nama}",
            ])->all(),
            'sourceTypeOptions' => PenugasanPengampuKinerja::sourceOptions(),
            'cascadingOptions' => $this->cascadingOptions($pegawai),
            'can' => [
                'manage' => $canManage,
                'delete' => $canManage && ! $this->shouldLimitToUserOpd($request->user()),
                'manage_jobs' => $request->user()->hasPermission('jabatan_organisasi.manage')
                    || $request->user()->hasPermission('jabatan_organisasi.manage_opd'),
            ],
        ]);
    }

    public function edit(Request $request, Pegawai $pegawai): Response
    {
        abort_unless($request->user()->hasPermission('pegawai.manage'), 403);
        $this->abortUnlessInScope($request->user(), $pegawai);

        return Inertia::render('Master/Pegawai/Form', $this->formProps($request->user(), $pegawai));
    }

    public function update(UpdatePegawaiRequest $request, Pegawai $pegawai): RedirectResponse
    {
        $this->abortUnlessInScope($request->user(), $pegawai);
        $data = $this->normalizeScopedData($request->user(), $request->validated());
        $this->assertReferencesMatch($data);
        $pegawai->update($data);

        return redirect()->route('master.pegawai.show', $pegawai)->with('success', 'Data pegawai berhasil diperbarui.');
    }

    public function destroy(Request $request, Pegawai $pegawai): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('pegawai.manage') && ! $this->shouldLimitToUserOpd($request->user()), 403);

        if ($pegawai->penempatan()->exists() || $pegawai->penugasanKinerja()->exists() || $pegawai->perjanjianKinerja()->exists()) {
            return back()->with('error', 'Pegawai tidak dapat dihapus karena memiliki riwayat jabatan atau data kinerja. Nonaktifkan pegawai agar histori tetap utuh.');
        }

        $pegawai->delete();

        return redirect()->route('master.pegawai.index')->with('success', 'Pegawai berhasil dihapus.');
    }

    private function scopedQuery(User $user): Builder
    {
        return Pegawai::query()->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id));
    }

    private function shouldLimitToUserOpd(User $user): bool
    {
        return filled($user->opd_id) && ! $user->hasAnyRole([
            'super_admin',
            'admin_kabupaten_bagian_organisasi',
            'admin_kabupaten_dinkominfo',
        ]);
    }

    private function abortUnlessInScope(User $user, Pegawai $pegawai): void
    {
        if ($this->shouldLimitToUserOpd($user) && (int) $pegawai->opd_id !== (int) $user->opd_id) {
            abort(403);
        }
    }

    private function normalizeScopedData(User $user, array $data): array
    {
        if ($this->shouldLimitToUserOpd($user)) {
            $data['opd_id'] = $user->opd_id;
        }

        return $data;
    }

    private function assertReferencesMatch(array $data): void
    {
        if (($data['opd_unit_id'] ?? null) && ! OpdUnit::query()->whereKey($data['opd_unit_id'])->where('opd_id', $data['opd_id'])->exists()) {
            throw ValidationException::withMessages(['opd_unit_id' => 'Unit organisasi harus berada pada perangkat daerah yang dipilih.']);
        }

        if (($data['user_id'] ?? null) && ($data['opd_id'] ?? null)
            && ! User::query()->whereKey($data['user_id'])->where('opd_id', $data['opd_id'])->exists()) {
            throw ValidationException::withMessages(['user_id' => 'Akun pengguna harus berada pada perangkat daerah yang sama.']);
        }
    }

    private function formProps(User $user, ?Pegawai $pegawai = null): array
    {
        return [
            'mode' => $pegawai ? 'edit' : 'create',
            'item' => $pegawai ? $this->serialize($pegawai) : null,
            'opdOptions' => $this->opdOptions($user),
            'unitOptions' => OpdUnit::query()
                ->where('status', 'active')
                ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
                ->orderBy('opd_id')->orderBy('kode')
                ->get(['id', 'opd_id', 'kode', 'nama'])
                ->map(fn (OpdUnit $unit) => ['id' => $unit->id, 'opd_id' => $unit->opd_id, 'label' => "{$unit->kode} - {$unit->nama}"])->all(),
            'userOptions' => User::query()
                ->where('status', 'active')
                ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
                ->orderBy('name')->get(['id', 'opd_id', 'name', 'username', 'email'])
                ->map(fn (User $account) => ['id' => $account->id, 'opd_id' => $account->opd_id, 'label' => $account->name.' - '.($account->username ?: $account->email)])->all(),
            'jenisOptions' => Pegawai::jenisOptions(),
            'jabatanOptions' => $this->formJabatanOptions($user),
            'penugasanOptions' => RiwayatPejabatJabatan::penugasanOptions(),
            'scopeLocked' => $this->shouldLimitToUserOpd($user),
            'canManageJobs' => $user->hasPermission('jabatan_organisasi.manage')
                || $user->hasPermission('jabatan_organisasi.manage_opd'),
        ];
    }

    private function formJabatanOptions(User $user): array
    {
        return JabatanOrganisasi::query()
            ->where('status', 'active')
            ->whereIn('verification_status', ['verified', 'pending'])
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->orderByRaw("CASE level_jabatan WHEN 'kepala_daerah' THEN 0 WHEN 'jpt_pratama' THEN 1 WHEN 'administrator' THEN 2 WHEN 'pengawas' THEN 3 WHEN 'fungsional' THEN 4 ELSE 5 END")
            ->orderBy('opd_id')->orderBy('urutan')->orderBy('nama')
            ->get(['id', 'opd_id', 'opd_unit_id', 'nama', 'level_jabatan', 'verification_status'])
            ->map(fn (JabatanOrganisasi $jabatan) => [
                'id' => $jabatan->id,
                'opd_id' => $jabatan->opd_id,
                'opd_unit_id' => $jabatan->opd_unit_id,
                'label' => $jabatan->nama.($jabatan->isPendingVerification() ? ' · menunggu verifikasi' : ''),
                'level_label' => JabatanOrganisasi::levelLabels()[$jabatan->level_jabatan] ?? $jabatan->level_jabatan,
                'multiple' => $jabatan->allowsMultipleHolders(),
                'verification_status' => $jabatan->verification_status,
            ])->all();
    }

    private function resolveInitialJabatan(User $user, array $data, int $jabatanId): JabatanOrganisasi
    {
        $jabatan = JabatanOrganisasi::query()
            ->where('status', 'active')
            ->findOrFail($jabatanId);

        if (! in_array($jabatan->verification_status, ['verified', 'pending'], true)) {
            throw ValidationException::withMessages([
                'jabatan_organisasi_id' => 'Jabatan masih memerlukan perbaikan dan belum dapat dipakai untuk penempatan pegawai.',
            ]);
        }

        if ($this->shouldLimitToUserOpd($user) && (int) $jabatan->opd_id !== (int) $user->opd_id) {
            abort(403);
        }

        if (filled($data['opd_id'] ?? null) && (int) $jabatan->opd_id !== (int) $data['opd_id']) {
            throw ValidationException::withMessages(['jabatan_organisasi_id' => 'Jabatan harus berada pada perangkat daerah yang dipilih.']);
        }

        return $jabatan;
    }

    private function assertInitialPlacementAvailable(JabatanOrganisasi $jabatan, array $data): void
    {
        if ($jabatan->allowsMultipleHolders()) {
            return;
        }

        $overlap = RiwayatPejabatJabatan::query()
            ->where('jabatan_organisasi_id', $jabatan->id)
            ->when($data['tanggal_selesai'] ?? null, fn (Builder $query, string $end) => $query->whereDate('tanggal_mulai', '<=', $end))
            ->where(fn (Builder $query) => $query->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $data['tanggal_mulai']))
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'tanggal_mulai' => 'Jabatan ini masih ditempati pegawai lain. Akhiri jabatan sebelumnya terlebih dahulu.',
            ]);
        }
    }

    private function opdOptions(User $user): array
    {
        return Opd::query()->where('status', 'active')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->whereKey($user->opd_id ?? 0))
            ->orderBy('nama')->get(['id', 'kode', 'nama', 'singkatan'])
            ->map(fn (Opd $opd) => ['id' => $opd->id, 'label' => $opd->singkatan ? "{$opd->singkatan} - {$opd->nama}" : $opd->nama])->all();
    }

    private function jabatanOptions(User $user, Pegawai $pegawai): array
    {
        return JabatanOrganisasi::query()
            ->with('opd:id,nama,singkatan')
            ->where('status', 'active')
            ->whereIn('verification_status', ['verified', 'pending'])
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $pegawai->opd_id))
            ->orderByRaw("CASE level_jabatan WHEN 'jpt_pratama' THEN 1 WHEN 'administrator' THEN 2 WHEN 'pengawas' THEN 3 WHEN 'fungsional' THEN 4 ELSE 5 END")
            ->orderBy('opd_id')->orderBy('urutan')->orderBy('nama')
            ->get(['id', 'opd_id', 'opd_unit_id', 'nama', 'level_jabatan', 'verification_status'])
            ->map(fn (JabatanOrganisasi $jabatan) => [
                'id' => $jabatan->id,
                'label' => ($jabatan->opd?->singkatan ? "{$jabatan->opd->singkatan} · " : '').$jabatan->nama
                    .($jabatan->isPendingVerification() ? ' · menunggu verifikasi' : ''),
                'level' => $jabatan->level_jabatan,
                'level_label' => JabatanOrganisasi::levelLabels()[$jabatan->level_jabatan] ?? $jabatan->level_jabatan,
                'multiple' => $jabatan->allowsMultipleHolders(),
                'verification_status' => $jabatan->verification_status,
            ])->all();
    }

    private function cascadingOptions(Pegawai $pegawai): array
    {
        if (! $pegawai->opd_id) {
            return ['sasaran' => [], 'program' => [], 'kegiatan' => [], 'sub_kegiatan' => []];
        }

        $opdId = (int) $pegawai->opd_id;

        return [
            'sasaran' => SasaranOpd::query()->whereHas('tujuan.renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->orderBy('urutan')->get(['id', 'kode', 'sasaran'])->map(fn (SasaranOpd $item) => ['id' => $item->id, 'label' => trim(($item->kode ? "{$item->kode} - " : '').$item->sasaran)])->all(),
            'program' => OpdProgram::query()->whereHas('renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->orderBy('urutan')->get(['id', 'kode', 'nama'])->map(fn (OpdProgram $item) => ['id' => $item->id, 'label' => trim(($item->kode ? "{$item->kode} - " : '').$item->nama)])->all(),
            'kegiatan' => OpdKegiatan::query()->whereHas('program.renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->orderBy('urutan')->get(['id', 'kode', 'nama'])->map(fn (OpdKegiatan $item) => ['id' => $item->id, 'label' => trim(($item->kode ? "{$item->kode} - " : '').$item->nama)])->all(),
            'sub_kegiatan' => OpdSubKegiatan::query()->whereHas('kegiatan.program.renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->orderBy('urutan')->get(['id', 'kode', 'nama'])->map(fn (OpdSubKegiatan $item) => ['id' => $item->id, 'label' => trim(($item->kode ? "{$item->kode} - " : '').$item->nama)])->all(),
        ];
    }

    private function currentPlacementConstraint(Builder $query, string $today): Builder
    {
        return $query->whereDate('tanggal_mulai', '<=', $today)
            ->where(fn (Builder $query) => $query->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $today));
    }

    private function serialize(Pegawai $pegawai, bool $detailed = false): array
    {
        $today = now()->startOfDay();
        $currentPlacements = $pegawai->relationLoaded('penempatan')
            ? $pegawai->penempatan->filter(fn (RiwayatPejabatJabatan $placement) => $placement->tanggal_mulai?->startOfDay()->lte($today)
                && (! $placement->tanggal_selesai || $placement->tanggal_selesai->startOfDay()->gte($today)))
            : collect();

        return [
            'id' => $pegawai->id,
            'opd_id' => $pegawai->opd_id,
            'opd_unit_id' => $pegawai->opd_unit_id,
            'user_id' => $pegawai->user_id,
            'nama' => $pegawai->nama,
            'nip' => $pegawai->nip,
            'pangkat_golongan' => $pegawai->pangkat_golongan,
            'jenis_pegawai' => $pegawai->jenis_pegawai,
            'jenis_pegawai_label' => collect(Pegawai::jenisOptions())->pluck('label', 'value')[$pegawai->jenis_pegawai] ?? $pegawai->jenis_pegawai,
            'status' => $pegawai->status,
            'opd' => $pegawai->opd,
            'opd_unit' => $pegawai->opdUnit,
            'user' => $pegawai->user,
            'penempatan_count' => $pegawai->penempatan_count ?? ($pegawai->relationLoaded('penempatan') ? $pegawai->penempatan->count() : 0),
            'current_placements' => $currentPlacements->map(fn (RiwayatPejabatJabatan $placement) => $this->serializePlacement($placement))->values()->all(),
            'penempatan' => $detailed && $pegawai->relationLoaded('penempatan')
                ? $pegawai->penempatan->map(fn (RiwayatPejabatJabatan $placement) => $this->serializePlacement($placement))->all() : [],
            'penugasan_kinerja' => $detailed && $pegawai->relationLoaded('penugasanKinerja')
                ? $pegawai->penugasanKinerja->map(fn (PenugasanPengampuKinerja $assignment) => [
                    'id' => $assignment->id,
                    'periode_tahun_id' => $assignment->periode_tahun_id,
                    'tahun' => $assignment->tahun,
                    'sumber_kinerja_type' => $assignment->sumber_kinerja_type,
                    'sumber_kinerja_label' => $assignment->sumber_kinerja_label,
                    'peran' => $assignment->peran,
                    'status' => $assignment->status,
                    'periode_label' => $assignment->periodeTahun?->nama,
                    'jabatan_label' => $assignment->penempatan?->jabatanOrganisasi?->nama,
                ])->all() : [],
        ];
    }

    private function serializePlacement(RiwayatPejabatJabatan $placement): array
    {
        return [
            'id' => $placement->id,
            'jabatan_organisasi_id' => $placement->jabatan_organisasi_id,
            'jabatan' => $placement->jabatanOrganisasi ? [
                'id' => $placement->jabatanOrganisasi->id,
                'nama' => $placement->jabatanOrganisasi->nama,
                'level_jabatan' => $placement->jabatanOrganisasi->level_jabatan,
                'level_label' => JabatanOrganisasi::levelLabels()[$placement->jabatanOrganisasi->level_jabatan] ?? $placement->jabatanOrganisasi->level_jabatan,
                'multiple' => $placement->jabatanOrganisasi->allowsMultipleHolders(),
                'verification_status' => $placement->jabatanOrganisasi->verification_status,
            ] : null,
            'jenis_penugasan' => $placement->jenis_penugasan,
            'jenis_penugasan_label' => collect(RiwayatPejabatJabatan::penugasanOptions())->pluck('label', 'value')[$placement->jenis_penugasan] ?? $placement->jenis_penugasan,
            'nomor_sk' => $placement->nomor_sk,
            'tanggal_sk' => $placement->tanggal_sk?->format('Y-m-d'),
            'tanggal_mulai' => $placement->tanggal_mulai?->format('Y-m-d'),
            'tanggal_selesai' => $placement->tanggal_selesai?->format('Y-m-d'),
        ];
    }
}
