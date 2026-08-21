<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreJabatanOrganisasiRequest;
use App\Http\Requests\Master\UpdateJabatanOrganisasiRequest;
use App\Models\JabatanOrganisasi;
use App\Models\Opd;
use App\Models\OpdUnit;
use App\Models\RiwayatPejabatJabatan;
use App\Models\User;
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
        $filters = $request->only(['search', 'opd_id', 'level_jabatan', 'status', 'keterisian']);
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
            ->when($filters['keterisian'] ?? null, function (Builder $query, string $keterisian) use ($today) {
                $method = $keterisian === 'terisi' ? 'whereHas' : 'whereDoesntHave';
                $query->{$method}('riwayatPejabat', fn (Builder $query) => $this->currentPejabatConstraint($query, $today));
            })
            ->orderByRaw("CASE level_jabatan WHEN 'kepala_daerah' THEN 1 WHEN 'jpt_pratama' THEN 2 WHEN 'administrator' THEN 3 WHEN 'pengawas' THEN 4 WHEN 'fungsional' THEN 5 ELSE 6 END")
            ->orderBy('opd_id')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (JabatanOrganisasi $jabatan) => $this->serialize($jabatan));

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
            ],
            'can' => [
                'manage' => $user->hasPermission('jabatan_organisasi.manage'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('jabatan_organisasi.manage'), 403);

        return Inertia::render('Master/JabatanOrganisasi/Form', $this->formProps($request->user(), null));
    }

    public function store(StoreJabatanOrganisasiRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $this->assertHierarchyValid($data);

        $jabatan = JabatanOrganisasi::create($data);

        return redirect()
            ->route('master.jabatan-organisasi.show', $jabatan)
            ->with('success', 'Jabatan organisasi berhasil ditambahkan.');
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
            'riwayatPejabat.user:id,name,email',
        ]);

        return Inertia::render('Master/JabatanOrganisasi/Show', [
            'item' => $this->serialize($jabatanOrganisasi, true),
            'penugasanOptions' => RiwayatPejabatJabatan::penugasanOptions(),
            'userOptions' => $this->userOptions($jabatanOrganisasi),
            'can' => [
                'manage_structure' => $user->hasPermission('jabatan_organisasi.manage'),
                'manage_officials' => false,
                'delete_officials' => false,
                'manage_people' => $user->hasPermission('pegawai.view'),
            ],
        ]);
    }

    public function edit(Request $request, JabatanOrganisasi $jabatanOrganisasi): Response
    {
        abort_unless($request->user()->hasPermission('jabatan_organisasi.manage'), 403);
        $this->abortUnlessInScope($request->user(), $jabatanOrganisasi);

        return Inertia::render('Master/JabatanOrganisasi/Form', $this->formProps($request->user(), $jabatanOrganisasi));
    }

    public function update(UpdateJabatanOrganisasiRequest $request, JabatanOrganisasi $jabatanOrganisasi): RedirectResponse
    {
        $this->abortUnlessInScope($request->user(), $jabatanOrganisasi);
        $data = $request->validated();
        $this->assertHierarchyValid($data, $jabatanOrganisasi);

        $jabatanOrganisasi->update($data);

        return redirect()
            ->route('master.jabatan-organisasi.show', $jabatanOrganisasi)
            ->with('success', 'Jabatan organisasi berhasil diperbarui.');
    }

    public function destroy(Request $request, JabatanOrganisasi $jabatanOrganisasi): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('jabatan_organisasi.manage'), 403);
        $this->abortUnlessInScope($request->user(), $jabatanOrganisasi);

        if ($jabatanOrganisasi->children()->exists()) {
            return back()->with('error', 'Jabatan belum dapat dihapus karena masih menjadi atasan jabatan lain.');
        }

        if ($jabatanOrganisasi->riwayatPejabat()->exists()) {
            return back()->with('error', 'Jabatan belum dapat dihapus karena memiliki riwayat pejabat. Nonaktifkan jabatan agar riwayat tetap utuh.');
        }

        $jabatanOrganisasi->delete();

        return redirect()->route('master.jabatan-organisasi.index')->with('success', 'Jabatan organisasi berhasil dihapus.');
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

        $parent = JabatanOrganisasi::query()->findOrFail($parentId);
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
            'item' => $jabatan ? $this->serialize($jabatan) : null,
            'opdOptions' => $this->opdOptions($user),
            'unitOptions' => $this->unitOptions($user),
            'parentOptions' => $this->parentOptions($user, $jabatan),
            'levelOptions' => JabatanOrganisasi::levelOptions(),
            'eselonOptions' => JabatanOrganisasi::eselonOptions(),
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
        return $this->scopedQuery($user)
            ->when($this->shouldLimitToUserOpd($user), fn (Builder $query) => $query->orWhere('level_jabatan', 'kepala_daerah'))
            ->when($current, fn (Builder $query) => $query->whereKeyNot($current->id))
            ->where('status', 'active')
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

    private function serialize(JabatanOrganisasi $jabatan, bool $withHistory = false): array
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
