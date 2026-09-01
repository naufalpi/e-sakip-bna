<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreJabatanOrganisasiRequest;
use App\Http\Requests\Master\UpdateJabatanOrganisasiRequest;
use App\Http\Requests\Master\VerifyJabatanOrganisasiRequest;
use App\Models\JabatanOrganisasi;
use App\Models\Opd;
use App\Models\OpdUnit;
use App\Models\RiwayatPejabatJabatan;
use App\Models\User;
use App\Services\Master\PegawaiOrganizationSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class JabatanOrganisasiController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('jabatan_organisasi.view'), 403);

        $user = $request->user();
        $filters = $request->only(['search', 'opd_id', 'level_jabatan', 'status', 'keterisian', 'verification_status']);
        $today = now()->toDateString();
        $baseQuery = $this->scopedQuery($user);

        $items = (clone $baseQuery)
            ->with([
                'opd:id,kode,nama,singkatan',
                'opdUnit:id,opd_id,kode,nama',
                'parent:id,nama,level_jabatan',
                'riwayatPejabat' => fn ($query) => $query
                    ->whereDate('tanggal_mulai', '<=', $today)
                    ->where(fn ($query) => $query->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $today))
                    ->with('user:id,name,email')
                    ->orderByDesc('tanggal_mulai'),
            ])
            ->withCount('children')
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('nama', 'ilike', "%{$search}%")
                        ->orWhereHas('opd', fn (Builder $query) => $query
                            ->where('nama', 'ilike', "%{$search}%")
                            ->orWhere('singkatan', 'ilike', "%{$search}%"))
                        ->orWhereHas('riwayatPejabat', fn (Builder $query) => $query
                            ->where('nama_pejabat', 'ilike', "%{$search}%")
                            ->orWhere('nip', 'ilike', "%{$search}%"));
                });
            })
            ->when(($filters['opd_id'] ?? null) && ! $this->shouldLimitToUserOpd($user), fn (Builder $query, string $opdId) => $query->where('opd_id', $opdId))
            ->when($filters['level_jabatan'] ?? null, fn (Builder $query, string $level) => $query->where('level_jabatan', $level))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['verification_status'] ?? null, fn (Builder $query, string $status) => $query->where('verification_status', $status))
            ->when($filters['keterisian'] ?? null, function (Builder $query, string $keterisian) use ($today) {
                $method = $keterisian === 'terisi' ? 'whereHas' : 'whereDoesntHave';
                $query->{$method}('riwayatPejabat', fn (Builder $query) => $this->currentPejabatConstraint($query, $today));
            })
            ->orderByRaw('CASE WHEN jabatan_organisasi.opd_id IS NULL THEN 0 ELSE 1 END')
            ->orderBy(
                Opd::query()
                    ->select('nama')
                    ->whereColumn('opds.id', 'jabatan_organisasi.opd_id')
                    ->limit(1)
            )
            ->orderByRaw("CASE
                WHEN level_jabatan = 'kepala_daerah' THEN 1
                WHEN level_jabatan = 'jpt_pratama' THEN 2
                WHEN level_jabatan = 'administrator' AND LOWER(nama) LIKE '%sekretaris%' THEN 3
                WHEN level_jabatan = 'administrator' THEN 4
                WHEN level_jabatan = 'pengawas' THEN 5
                WHEN level_jabatan = 'fungsional' THEN 6
                WHEN level_jabatan = 'pelaksana' THEN 7
                ELSE 8
            END")
            ->orderBy('urutan')
            ->orderBy('nama')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (JabatanOrganisasi $jabatan) => $this->serialize($jabatan, false, $user));

        $activeQuery = (clone $baseQuery)->where('status', 'active');
        $occupied = (clone $activeQuery)
            ->whereHas('riwayatPejabat', fn (Builder $query) => $this->currentPejabatConstraint($query, $today))
            ->count();

        return Inertia::render('Master/JabatanOrganisasi/Index', [
            'items' => $items,
            'filters' => $filters,
            'opdOptions' => $this->opdOptions($user),
            'levelOptions' => JabatanOrganisasi::levelOptions(),
            'stats' => [
                'total' => (clone $baseQuery)->count(),
                'active' => (clone $activeQuery)->count(),
                'occupied' => $occupied,
                'vacant' => max(0, (clone $activeQuery)->count() - $occupied),
                'pending' => (clone $baseQuery)->where('verification_status', 'pending')->count(),
            ],
            'can' => [
                'create' => $this->canCreate($user),
                'import' => $this->isCentralManager($user),
                'verify' => $user->hasPermission('jabatan_organisasi.verify'),
                'manage_people' => $user->hasPermission('pegawai.view'),
                'opd_scoped' => $this->shouldLimitToUserOpd($user),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($this->canCreate($request->user()), 403);

        return Inertia::render('Master/JabatanOrganisasi/Form', $this->formProps($request->user(), null));
    }

    public function store(StoreJabatanOrganisasiRequest $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($this->canCreate($user), 403);
        $data = $this->normalizeScopedData($user, $request->validated());
        $this->assertHierarchyValid($data);

        if ($this->isCentralManager($user)) {
            $data = [...$data, 'verification_status' => 'verified', 'proposed_by' => $user->id, 'verified_by' => $user->id, 'verified_at' => now(), 'verification_note' => null];
        } else {
            $data = [...$data, 'verification_status' => 'pending', 'proposed_by' => $user->id, 'verified_by' => null, 'verified_at' => null, 'verification_note' => null];
        }

        $jabatan = JabatanOrganisasi::create($data);

        return redirect()
            ->route('master.jabatan-organisasi.show', $jabatan)
            ->with('success', $jabatan->isVerified()
                ? 'Jabatan organisasi berhasil ditambahkan.'
                : 'Usulan jabatan berhasil disimpan dan menunggu verifikasi Admin Kabupaten.');
    }

    public function show(Request $request, JabatanOrganisasi $jabatanOrganisasi): Response
    {
        abort_unless($request->user()->hasPermission('jabatan_organisasi.view'), 403);
        $user = $request->user();
        $this->abortUnlessInScope($user, $jabatanOrganisasi);

        $jabatanOrganisasi->load([
            'opd:id,kode,nama,singkatan',
            'opdUnit:id,opd_id,kode,nama',
            'parent:id,nama,level_jabatan',
            'children:id,parent_id,nama,level_jabatan,status',
            'proposedBy:id,name',
            'verifiedBy:id,name',
            'riwayatPejabat.user:id,name,email',
        ]);

        return Inertia::render('Master/JabatanOrganisasi/Show', [
            'item' => $this->serialize($jabatanOrganisasi, true, $user),
            'penugasanOptions' => RiwayatPejabatJabatan::penugasanOptions(),
            'userOptions' => $this->userOptions($jabatanOrganisasi),
            'can' => [
                'manage_structure' => $this->canEdit($user, $jabatanOrganisasi),
                'verify' => $user->hasPermission('jabatan_organisasi.verify') && ! $jabatanOrganisasi->isVerified(),
                'manage_officials' => false,
                'delete_officials' => false,
                'manage_people' => $user->hasPermission('pegawai.view'),
            ],
        ]);
    }

    public function edit(Request $request, JabatanOrganisasi $jabatanOrganisasi): Response
    {
        $this->abortUnlessInScope($request->user(), $jabatanOrganisasi);
        abort_unless($this->canEdit($request->user(), $jabatanOrganisasi), 403);

        return Inertia::render('Master/JabatanOrganisasi/Form', $this->formProps($request->user(), $jabatanOrganisasi));
    }

    public function update(
        UpdateJabatanOrganisasiRequest $request,
        JabatanOrganisasi $jabatanOrganisasi,
        PegawaiOrganizationSyncService $syncService,
    ): RedirectResponse {
        $this->abortUnlessInScope($request->user(), $jabatanOrganisasi);
        abort_unless($this->canEdit($request->user(), $jabatanOrganisasi), 403);
        $data = $this->normalizeScopedData($request->user(), $request->validated());
        $this->assertHierarchyValid($data, $jabatanOrganisasi);

        if (! $this->isCentralManager($request->user())) {
            $data = [...$data, 'verification_status' => 'pending', 'proposed_by' => $request->user()->id, 'verified_by' => null, 'verified_at' => null, 'verification_note' => null];
        }

        $jabatanOrganisasi->update($data);
        $syncService->syncCurrentHolders($jabatanOrganisasi);

        return redirect()
            ->route('master.jabatan-organisasi.show', $jabatanOrganisasi)
            ->with('success', $this->isCentralManager($request->user())
                ? 'Jabatan organisasi berhasil diperbarui.'
                : 'Usulan jabatan berhasil diperbarui dan dikirim kembali untuk diverifikasi.');
    }

    public function destroy(Request $request, JabatanOrganisasi $jabatanOrganisasi): RedirectResponse
    {
        $this->abortUnlessInScope($request->user(), $jabatanOrganisasi);
        abort_unless($this->canEdit($request->user(), $jabatanOrganisasi), 403);

        if ($jabatanOrganisasi->children()->exists()) {
            return back()->with('error', 'Jabatan belum dapat dihapus karena masih menjadi atasan jabatan lain.');
        }

        if ($jabatanOrganisasi->riwayatPejabat()->exists()) {
            return back()->with('error', 'Jabatan belum dapat dihapus karena memiliki riwayat pejabat. Nonaktifkan jabatan agar riwayat tetap utuh.');
        }

        $jabatanOrganisasi->delete();

        return redirect()->route('master.jabatan-organisasi.index')->with('success', 'Jabatan organisasi berhasil dihapus.');
    }

    public function verify(VerifyJabatanOrganisasiRequest $request, JabatanOrganisasi $jabatanOrganisasi): RedirectResponse
    {
        $data = $request->validated();
        $status = $data['verification_status'];

        if ($status === 'verified' && $jabatanOrganisasi->parent_id
            && ! JabatanOrganisasi::query()->whereKey($jabatanOrganisasi->parent_id)->where('verification_status', 'verified')->exists()) {
            throw ValidationException::withMessages([
                'verification_status' => 'Verifikasi dahulu jabatan atasan agar struktur resmi terbentuk dari atas ke bawah.',
            ]);
        }

        $jabatanOrganisasi->update([
            'verification_status' => $status,
            'verification_note' => $status === 'rejected' ? $data['verification_note'] : null,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        return back()->with('success', $status === 'verified'
            ? 'Usulan jabatan telah diverifikasi.'
            : 'Usulan jabatan dikembalikan kepada Admin OPD untuk diperbaiki.');
    }

    private function scopedQuery(User $user): Builder
    {
        return JabatanOrganisasi::query()
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($this->shouldLimitToUserUnit($user), fn (Builder $query) => $query->where('opd_unit_id', $user->opd_unit_id));
    }

    private function shouldLimitToUserOpd(User $user): bool
    {
        return $user->hasRole('admin_opd')
            && ! $user->hasAnyRole([
                'super_admin',
                'admin_kabupaten_bagian_organisasi',
                'admin_kabupaten_bapperida',
                'admin_kabupaten_bpkad',
                'admin_kabupaten_inspektorat',
                'admin_kabupaten_dinkominfo',
            ]);
    }

    private function shouldLimitToUserUnit(User $user): bool
    {
        return $this->shouldLimitToUserOpd($user) && filled($user->opd_unit_id);
    }

    private function isCentralManager(User $user): bool
    {
        return $user->hasPermission('jabatan_organisasi.manage');
    }

    private function canCreate(User $user): bool
    {
        return $this->isCentralManager($user)
            || ($this->shouldLimitToUserOpd($user) && $user->hasPermission('jabatan_organisasi.manage_opd'));
    }

    private function canEdit(User $user, JabatanOrganisasi $jabatan): bool
    {
        if ($this->isCentralManager($user)) {
            return true;
        }

        return $this->shouldLimitToUserOpd($user)
            && $user->hasPermission('jabatan_organisasi.manage_opd')
            && (int) $jabatan->opd_id === (int) $user->opd_id
            && in_array($jabatan->verification_status, ['pending', 'rejected'], true);
    }

    private function normalizeScopedData(User $user, array $data): array
    {
        if (! $this->shouldLimitToUserOpd($user)) {
            return $data;
        }

        if (! $user->opd_id) {
            abort(403, 'Admin OPD belum terhubung dengan perangkat daerah.');
        }

        if (($data['level_jabatan'] ?? null) === 'kepala_daerah') {
            throw ValidationException::withMessages(['level_jabatan' => 'Admin OPD tidak dapat mengusulkan jabatan Kepala Daerah.']);
        }

        $data['opd_id'] = $user->opd_id;
        if ($this->shouldLimitToUserUnit($user)) {
            $data['opd_unit_id'] = $user->opd_unit_id;
        }

        return $data;
    }

    private function abortUnlessInScope(User $user, JabatanOrganisasi $jabatan): void
    {
        if ($this->shouldLimitToUserOpd($user) && (int) $jabatan->opd_id !== (int) $user->opd_id) {
            abort(403);
        }

        if ($this->shouldLimitToUserUnit($user) && (int) $jabatan->opd_unit_id !== (int) $user->opd_unit_id) {
            abort(403);
        }
    }

    private function assertHierarchyValid(array $data, ?JabatanOrganisasi $current = null): void
    {
        $level = $data['level_jabatan'];
        $opdId = $data['opd_id'] ?? null;
        $unitId = $data['opd_unit_id'] ?? null;
        $parentId = $data['parent_id'] ?? null;

        if ($level === 'kepala_daerah') {
            if ($opdId || $unitId || $parentId) {
                throw ValidationException::withMessages([
                    'level_jabatan' => 'Kepala Daerah tidak ditempatkan pada OPD/unit dan tidak memiliki atasan dalam hierarki ini.',
                ]);
            }

            return;
        }

        if (! $opdId) {
            throw ValidationException::withMessages(['opd_id' => 'Perangkat daerah wajib dipilih untuk level jabatan ini.']);
        }

        if ($unitId) {
            $unit = OpdUnit::query()->findOrFail($unitId);
            if ((int) $unit->opd_id !== (int) $opdId) {
                throw ValidationException::withMessages(['opd_unit_id' => 'Unit organisasi harus berada pada perangkat daerah yang dipilih.']);
            }
        }

        if (! $parentId) {
            throw ValidationException::withMessages(['parent_id' => 'Atasan langsung wajib dipilih agar rantai akuntabilitas kinerja terbentuk.']);
        }

        $parent = JabatanOrganisasi::query()
            ->where('status', 'active')
            ->where('verification_status', '!=', 'rejected')
            ->findOrFail($parentId);
        if ($current && (int) $parent->id === (int) $current->id) {
            throw ValidationException::withMessages(['parent_id' => 'Jabatan tidak dapat menjadi atasan untuk dirinya sendiri.']);
        }

        $allowedParents = [
            'jpt_pratama' => ['kepala_daerah'],
            'administrator' => ['jpt_pratama'],
            'pengawas' => ['jpt_pratama', 'administrator'],
            'fungsional' => ['jpt_pratama', 'administrator', 'pengawas', 'fungsional'],
            'pelaksana' => ['jpt_pratama', 'administrator', 'pengawas', 'fungsional'],
        ];

        if (! in_array($parent->level_jabatan, $allowedParents[$level] ?? [], true)) {
            throw ValidationException::withMessages([
                'parent_id' => 'Level atasan langsung tidak sesuai dengan hierarki jabatan yang dipilih.',
            ]);
        }

        if ($parent->level_jabatan !== 'kepala_daerah' && (int) $parent->opd_id !== (int) $opdId) {
            throw ValidationException::withMessages(['parent_id' => 'Atasan langsung harus berada pada perangkat daerah yang sama.']);
        }

        if ($current) {
            $cursor = $parent;
            $visited = [];
            while ($cursor) {
                if ((int) $cursor->id === (int) $current->id) {
                    throw ValidationException::withMessages(['parent_id' => 'Atasan langsung tidak boleh membentuk siklus hierarki.']);
                }

                if (in_array($cursor->id, $visited, true)) {
                    break;
                }

                $visited[] = $cursor->id;
                $cursor = $cursor->parent;
            }
        }
    }

    private function currentPejabatConstraint(Builder $query, string $today): Builder
    {
        return $query
            ->whereDate('tanggal_mulai', '<=', $today)
            ->where(fn (Builder $query) => $query->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $today));
    }

    private function formProps(User $user, ?JabatanOrganisasi $jabatan): array
    {
        if ($jabatan) {
            $this->abortUnlessInScope($user, $jabatan);
            $jabatan->load(['opd:id,kode,nama,singkatan', 'opdUnit:id,kode,nama', 'parent:id,nama,level_jabatan']);
        }

        return [
            'mode' => $jabatan ? 'edit' : 'create',
            'item' => $jabatan ? $this->serialize($jabatan, false, $user) : null,
            'opdOptions' => $this->opdOptions($user),
            'unitOptions' => $this->unitOptions($user),
            'parentOptions' => $this->parentOptions($user, $jabatan),
            'levelOptions' => collect(JabatanOrganisasi::levelOptions())
                ->when($this->shouldLimitToUserOpd($user), fn ($items) => $items->where('value', '!=', 'kepala_daerah'))
                ->values()->all(),
            'eselonOptions' => JabatanOrganisasi::eselonOptions(),
            'scopeLocked' => $this->shouldLimitToUserOpd($user),
            'isOpdProposal' => ! $this->isCentralManager($user),
        ];
    }

    private function opdOptions(User $user): array
    {
        return Opd::query()
            ->where('status', 'active')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->whereKey($user->opd_id ?? 0))
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama', 'singkatan'])
            ->map(fn (Opd $opd) => [
                'id' => $opd->id,
                'kode' => $opd->kode,
                'label' => $opd->singkatan ? "{$opd->singkatan} - {$opd->nama}" : $opd->nama,
            ])->all();
    }

    private function unitOptions(User $user): array
    {
        return OpdUnit::query()
            ->where('status', 'active')
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where('opd_id', $user->opd_id))
            ->when($this->shouldLimitToUserUnit($user), fn (Builder $query) => $query->whereKey($user->opd_unit_id))
            ->orderBy('opd_id')->orderBy('kode')
            ->get(['id', 'opd_id', 'kode', 'nama'])
            ->map(fn (OpdUnit $unit) => [
                'id' => $unit->id,
                'opd_id' => $unit->opd_id,
                'label' => "{$unit->kode} - {$unit->nama}",
            ])->all();
    }

    private function parentOptions(User $user, ?JabatanOrganisasi $current = null): array
    {
        return JabatanOrganisasi::query()
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->where(
                fn (Builder $query) => $query
                    ->where('opd_id', $user->opd_id)
                    ->orWhere('level_jabatan', 'kepala_daerah')
            ))
            ->when($current, fn (Builder $query) => $query->whereKeyNot($current->id))
            ->where('status', 'active')
            ->where('verification_status', '!=', 'rejected')
            ->orderBy('opd_id')->orderBy('urutan')->orderBy('nama')
            ->get(['id', 'opd_id', 'nama', 'level_jabatan'])
            ->map(fn (JabatanOrganisasi $jabatan) => [
                'id' => $jabatan->id,
                'opd_id' => $jabatan->opd_id,
                'level_jabatan' => $jabatan->level_jabatan,
                'label' => $jabatan->nama,
            ])->all();
    }

    private function userOptions(JabatanOrganisasi $jabatan): array
    {
        return User::query()
            ->where('status', 'active')
            ->when($jabatan->opd_id, fn (Builder $query, int $opdId) => $query->where('opd_id', $opdId))
            ->orderBy('name')
            ->get(['id', 'name', 'username', 'email', 'opd_id'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'label' => $user->name.' - '.($user->username ?: $user->email),
            ])->all();
    }

    private function serialize(JabatanOrganisasi $jabatan, bool $withHistory = false, ?User $actor = null): array
    {
        $today = now()->startOfDay();
        $currentPejabatItems = $jabatan->relationLoaded('riwayatPejabat')
            ? $jabatan->riwayatPejabat->filter(fn (RiwayatPejabatJabatan $item) => $item->tanggal_mulai?->startOfDay()->lte($today)
                && (! $item->tanggal_selesai || $item->tanggal_selesai->startOfDay()->gte($today)))
            : collect();
        $currentPejabat = $currentPejabatItems->first();

        return [
            'id' => $jabatan->id,
            'opd_id' => $jabatan->opd_id,
            'opd_unit_id' => $jabatan->opd_unit_id,
            'parent_id' => $jabatan->parent_id,
            'nama' => $jabatan->nama,
            'level_jabatan' => $jabatan->level_jabatan,
            'level_label' => JabatanOrganisasi::levelLabels()[$jabatan->level_jabatan] ?? $jabatan->level_jabatan,
            'eselon' => $jabatan->eselon,
            'urutan' => $jabatan->urutan,
            'status' => $jabatan->status,
            'verification_status' => $jabatan->verification_status,
            'verification_label' => JabatanOrganisasi::verificationLabels()[$jabatan->verification_status] ?? $jabatan->verification_status,
            'verification_note' => $jabatan->verification_note,
            'verified_at' => $jabatan->verified_at?->timezone(config('app.timezone'))->format('d M Y H:i'),
            'proposed_by' => $jabatan->relationLoaded('proposedBy') && $jabatan->proposedBy ? ['id' => $jabatan->proposedBy->id, 'name' => $jabatan->proposedBy->name] : null,
            'verified_by' => $jabatan->relationLoaded('verifiedBy') && $jabatan->verifiedBy ? ['id' => $jabatan->verifiedBy->id, 'name' => $jabatan->verifiedBy->name] : null,
            'can_edit' => $actor ? $this->canEdit($actor, $jabatan) : false,
            'can_delete' => $actor ? $this->canEdit($actor, $jabatan) : false,
            'can_verify' => $actor ? $actor->hasPermission('jabatan_organisasi.verify') && ! $jabatan->isVerified() : false,
            'children_count' => $jabatan->children_count ?? ($jabatan->relationLoaded('children') ? $jabatan->children->count() : null),
            'opd' => $jabatan->opd ? [
                'id' => $jabatan->opd->id,
                'kode' => $jabatan->opd->kode,
                'nama' => $jabatan->opd->nama,
                'singkatan' => $jabatan->opd->singkatan,
            ] : null,
            'opd_unit' => $jabatan->opdUnit ? [
                'id' => $jabatan->opdUnit->id,
                'kode' => $jabatan->opdUnit->kode,
                'nama' => $jabatan->opdUnit->nama,
            ] : null,
            'parent' => $jabatan->parent ? [
                'id' => $jabatan->parent->id,
                'nama' => $jabatan->parent->nama,
                'level_jabatan' => $jabatan->parent->level_jabatan,
            ] : null,
            'current_pejabat' => $currentPejabat ? $this->serializePejabat($currentPejabat) : null,
            'current_pejabat_count' => $currentPejabatItems->count(),
            'children' => $jabatan->relationLoaded('children')
                ? $jabatan->children->map(fn (JabatanOrganisasi $child) => [
                    'id' => $child->id,
                    'nama' => $child->nama,
                    'level_label' => JabatanOrganisasi::levelLabels()[$child->level_jabatan] ?? $child->level_jabatan,
                    'status' => $child->status,
                ])->all()
                : [],
            'riwayat_pejabat' => $withHistory && $jabatan->relationLoaded('riwayatPejabat')
                ? $jabatan->riwayatPejabat->map(fn (RiwayatPejabatJabatan $item) => $this->serializePejabat($item))->all()
                : [],
        ];
    }

    private function serializePejabat(RiwayatPejabatJabatan $item): array
    {
        $labels = collect(RiwayatPejabatJabatan::penugasanOptions())->pluck('label', 'value');

        return [
            'id' => $item->id,
            'pegawai_id' => $item->pegawai_id,
            'user_id' => $item->user_id,
            'nama_pejabat' => $item->nama_pejabat,
            'nip' => $item->nip,
            'pangkat_golongan' => $item->pangkat_golongan,
            'jenis_penugasan' => $item->jenis_penugasan,
            'jenis_penugasan_label' => $labels[$item->jenis_penugasan] ?? $item->jenis_penugasan,
            'nomor_sk' => $item->nomor_sk,
            'tanggal_sk' => $item->tanggal_sk?->format('Y-m-d'),
            'tanggal_mulai' => $item->tanggal_mulai?->format('Y-m-d'),
            'tanggal_selesai' => $item->tanggal_selesai?->format('Y-m-d'),
            'user' => $item->user ? ['id' => $item->user->id, 'name' => $item->user->name, 'email' => $item->user->email] : null,
        ];
    }
}
