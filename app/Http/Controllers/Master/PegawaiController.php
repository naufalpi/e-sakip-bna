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
use App\Models\RiwayatPejabatJabatan;
use App\Models\SasaranOpd;
use App\Models\User;
use App\Services\Master\PegawaiOrganizationSyncService;
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
        $hierarchyRank = RiwayatPejabatJabatan::query()
            ->selectRaw("MIN(CASE
                WHEN jabatan_organisasi.level_jabatan = 'kepala_daerah' THEN 1
                WHEN jabatan_organisasi.level_jabatan = 'jpt_pratama' THEN 2
                WHEN jabatan_organisasi.level_jabatan = 'administrator' AND LOWER(jabatan_organisasi.nama) LIKE '%sekretaris%' THEN 3
                WHEN jabatan_organisasi.level_jabatan = 'administrator' THEN 4
                WHEN jabatan_organisasi.level_jabatan = 'pengawas' THEN 5
                WHEN jabatan_organisasi.level_jabatan = 'fungsional' THEN 6
                WHEN jabatan_organisasi.level_jabatan = 'pelaksana' THEN 7
                ELSE 8
            END)")
            ->join('jabatan_organisasi', 'jabatan_organisasi.id', '=', 'riwayat_pejabat_jabatan.jabatan_organisasi_id')
            ->whereColumn('riwayat_pejabat_jabatan.pegawai_id', 'pegawai.id')
            ->whereNull('riwayat_pejabat_jabatan.deleted_at')
            ->whereNull('jabatan_organisasi.deleted_at')
            ->whereDate('riwayat_pejabat_jabatan.tanggal_mulai', '<=', $today)
            ->where(fn (Builder $query) => $query
                ->whereNull('riwayat_pejabat_jabatan.tanggal_selesai')
                ->orWhereDate('riwayat_pejabat_jabatan.tanggal_selesai', '>=', $today));

        $items = (clone $baseQuery)
            ->select('pegawai.*')
            ->selectSub($hierarchyRank, 'hierarchy_rank')
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
            ->orderByRaw('CASE WHEN pegawai.opd_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy(
                Opd::query()
                    ->select('nama')
                    ->whereColumn('opds.id', 'pegawai.opd_id')
                    ->limit(1)
            )
            ->orderBy('hierarchy_rank')
            ->orderBy('nama')
            ->paginate(50)
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

    public function store(StorePegawaiRequest $request, PegawaiOrganizationSyncService $syncService): RedirectResponse
    {
        $data = $this->normalizeScopedData($request->user(), $request->validated());
        $placementData = Arr::only($data, [
            'jabatan_organisasi_id',
            'jenis_penugasan',
        ]);
        $data = Arr::except($data, array_keys($placementData));

        if (($data['status'] ?? 'active') !== 'active') {
            $placementData = [];
        } elseif (filled($placementData['jabatan_organisasi_id'] ?? null)) {
            $placementData['tanggal_mulai'] = now()->toDateString();
        }

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
        $syncService->syncEmployee($pegawai);

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
        ]);

        $canManage = $request->user()->hasPermission('pegawai.manage');

        return Inertia::render('Master/Pegawai/Show', [
            'item' => $this->serialize($pegawai, true),
            'jabatanOptions' => $this->jabatanOptions($request->user(), $pegawai),
            'penugasanOptions' => RiwayatPejabatJabatan::penugasanOptions(),
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

    public function update(
        UpdatePegawaiRequest $request,
        Pegawai $pegawai,
        PegawaiOrganizationSyncService $syncService,
    ): RedirectResponse {
        $this->abortUnlessInScope($request->user(), $pegawai);
        $wasActive = $pegawai->status === 'active';
        $data = $this->normalizeScopedData($request->user(), $request->validated());
        $placementData = Arr::only($data, ['jabatan_organisasi_id', 'jenis_penugasan']);
        $data = Arr::except($data, array_keys($placementData));
        $currentPlacement = $this->currentPlacement($pegawai);
        $selectedJabatan = null;

        if (($data['status'] ?? 'active') === 'active' && filled($placementData['jabatan_organisasi_id'] ?? null)) {
            $selectedJabatan = $this->resolveInitialJabatan(
                $request->user(),
                $data,
                (int) $placementData['jabatan_organisasi_id'],
            );
            $this->assertInitialPlacementAvailable(
                $selectedJabatan,
                [...$placementData, 'tanggal_mulai' => now()->toDateString()],
                $currentPlacement?->id,
            );
            $data['opd_id'] = $selectedJabatan->opd_id;
            $data['opd_unit_id'] = $selectedJabatan->opd_unit_id;
        } elseif ($currentOrganization = $syncService->currentOrganization($pegawai)) {
            $data = [...$data, ...$currentOrganization];
        }
        $this->assertReferencesMatch($data);
        DB::transaction(function () use ($pegawai, $data, $placementData, $selectedJabatan, $currentPlacement, $wasActive): void {
            $pegawai->update($data);

            if ($wasActive && $pegawai->status === 'inactive') {
                $this->closeCurrentPlacements($pegawai);
                $pegawai->penugasanKinerja()->where('status', 'active')->update(['status' => 'inactive']);
            } elseif ($selectedJabatan) {
                if ($currentPlacement && (int) $currentPlacement->jabatan_organisasi_id === (int) $selectedJabatan->id) {
                    $currentPlacement->update([
                        'jenis_penugasan' => $placementData['jenis_penugasan'],
                        'user_id' => $pegawai->user_id,
                        'nama_pejabat' => $pegawai->nama,
                        'nip' => $pegawai->nip,
                        'pangkat_golongan' => $pegawai->pangkat_golongan,
                    ]);
                } else {
                    $this->closeCurrentPlacements($pegawai);
                    $pegawai->penempatan()->create([
                        'jabatan_organisasi_id' => $selectedJabatan->id,
                        'jenis_penugasan' => $placementData['jenis_penugasan'],
                        'tanggal_mulai' => now()->toDateString(),
                        'user_id' => $pegawai->user_id,
                        'nama_pejabat' => $pegawai->nama,
                        'nip' => $pegawai->nip,
                        'pangkat_golongan' => $pegawai->pangkat_golongan,
                    ]);
                }
            }
        });
        $syncService->syncEmployee($pegawai);

        $message = $wasActive && $pegawai->status === 'inactive'
            ? 'Pegawai dinonaktifkan dan jabatan aktifnya telah ditutup. Akun aplikasi tidak diubah.'
            : 'Data pegawai berhasil diperbarui.';

        return redirect()->route('master.pegawai.show', $pegawai)->with('success', $message);
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
        $currentPlacement = $pegawai ? $this->currentPlacement($pegawai) : null;

        return [
            'mode' => $pegawai ? 'edit' : 'create',
            'item' => $pegawai ? [
                ...$this->serialize($pegawai),
                'jabatan_organisasi_id' => $currentPlacement?->jabatan_organisasi_id,
                'jenis_penugasan' => $currentPlacement?->jenis_penugasan ?? 'definitif',
            ] : null,
            'opdOptions' => $this->opdOptions($user),
            'userOptions' => User::query()
                ->where('status', 'active')
                ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
                ->orderBy('name')->get(['id', 'opd_id', 'name', 'username', 'email'])
                ->map(fn (User $account) => ['id' => $account->id, 'opd_id' => $account->opd_id, 'label' => $account->name.' - '.($account->username ?: $account->email)])->all(),
            'jenisOptions' => Pegawai::jenisOptions(),
            'jabatanOptions' => $this->formJabatanOptions($user),
            'penugasanOptions' => RiwayatPejabatJabatan::penugasanOptions(),
            'scopeLocked' => $this->shouldLimitToUserOpd($user),
            'isKepalaDaerah' => $currentPlacement?->jabatanOrganisasi?->level_jabatan === 'kepala_daerah',
            'canManageJobs' => $user->hasPermission('jabatan_organisasi.manage')
                || $user->hasPermission('jabatan_organisasi.manage_opd'),
        ];
    }

    private function formJabatanOptions(User $user): array
    {
        return JabatanOrganisasi::query()
            ->where('status', 'active')
            ->whereIn('verification_status', ['verified', 'pending'])
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query
                ->where('opd_id', $user->opd_id)
                ->where('level_jabatan', '!=', 'kepala_daerah'))
            ->orderByRaw("CASE level_jabatan WHEN 'kepala_daerah' THEN 0 WHEN 'jpt_pratama' THEN 1 WHEN 'administrator' THEN 2 WHEN 'pengawas' THEN 3 WHEN 'fungsional' THEN 4 ELSE 5 END")
            ->orderBy('opd_id')->orderBy('urutan')->orderBy('nama')
            ->get(['id', 'opd_id', 'opd_unit_id', 'nama', 'level_jabatan', 'verification_status'])
            ->map(fn (JabatanOrganisasi $jabatan) => [
                'id' => $jabatan->id,
                'opd_id' => $jabatan->opd_id,
                'opd_unit_id' => $jabatan->opd_unit_id,
                'label' => $jabatan->nama.($jabatan->isPendingVerification() ? ' · menunggu verifikasi' : ''),
                'level_jabatan' => $jabatan->level_jabatan,
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

    private function assertInitialPlacementAvailable(JabatanOrganisasi $jabatan, array $data, ?int $ignorePlacementId = null): void
    {
        if ($jabatan->allowsMultipleHolders()) {
            return;
        }

        $overlap = RiwayatPejabatJabatan::query()
            ->where('jabatan_organisasi_id', $jabatan->id)
            ->whereHas('pegawai', fn (Builder $query) => $query->where('status', 'active'))
            ->when($ignorePlacementId, fn (Builder $query, int $id) => $query->whereKeyNot($id))
            ->when($data['tanggal_selesai'] ?? null, fn (Builder $query, string $end) => $query->whereDate('tanggal_mulai', '<=', $end))
            ->where(fn (Builder $query) => $query->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $data['tanggal_mulai']))
            ->exists();

        if ($overlap) {
            throw ValidationException::withMessages([
                'jabatan_organisasi_id' => 'Jabatan ini masih ditempati pegawai aktif lain. Akhiri jabatan sebelumnya terlebih dahulu.',
            ]);
        }
    }

    private function closeCurrentPlacements(Pegawai $pegawai): void
    {
        $today = now()->startOfDay();

        $pegawai->penempatan()
            ->whereDate('tanggal_mulai', '<=', $today->toDateString())
            ->where(fn (Builder $query) => $query
                ->whereNull('tanggal_selesai')
                ->orWhereDate('tanggal_selesai', '>=', $today->toDateString()))
            ->get()
            ->each(function (RiwayatPejabatJabatan $placement) use ($today): void {
                $endDate = $placement->tanggal_mulai?->startOfDay()->lt($today)
                    ? $today->copy()->subDay()
                    : $today;

                $placement->update(['tanggal_selesai' => $endDate->toDateString()]);
            });
    }

    private function currentPlacement(Pegawai $pegawai): ?RiwayatPejabatJabatan
    {
        $today = now()->toDateString();

        return $pegawai->penempatan()
            ->with('jabatanOrganisasi:id,opd_id,opd_unit_id,level_jabatan')
            ->whereDate('tanggal_mulai', '<=', $today)
            ->where(fn (Builder $query) => $query
                ->whereNull('tanggal_selesai')
                ->orWhereDate('tanggal_selesai', '>=', $today))
            ->orderByRaw("CASE jenis_penugasan WHEN 'definitif' THEN 1 WHEN 'penjabat' THEN 2 WHEN 'plt' THEN 3 WHEN 'plh' THEN 4 ELSE 5 END")
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->first();
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
        $currentPlacements = $pegawai->status === 'active' && $pegawai->relationLoaded('penempatan')
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
            'tanggal_mulai' => $placement->tanggal_mulai?->format('Y-m-d'),
            'tanggal_selesai' => $placement->tanggal_selesai?->format('Y-m-d'),
        ];
    }
}
