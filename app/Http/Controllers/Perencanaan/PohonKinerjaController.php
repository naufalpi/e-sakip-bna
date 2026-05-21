<?php

namespace App\Http\Controllers\Perencanaan;

use App\Http\Controllers\Controller;
use App\Models\RenstraOpd;
use App\Models\Rpjmd;
use App\Models\User;
use App\Services\Perencanaan\PohonKinerjaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class PohonKinerjaController extends Controller
{
    public function index(Request $request, PohonKinerjaService $pohonKinerjaService): InertiaResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user->can('viewAny', Rpjmd::class) || $user->can('viewAny', RenstraOpd::class), 403);

        $mode = $this->selectedMode($request, $user);

        return $this->respond($request, $this->payload($request, $pohonKinerjaService, $mode));
    }

    public function kabupaten(Request $request, Rpjmd $rpjmd, PohonKinerjaService $pohonKinerjaService): InertiaResponse|JsonResponse
    {
        $this->authorize('view', $rpjmd);

        return $this->respond($request, $this->payload($request, $pohonKinerjaService, 'kabupaten', $rpjmd->id));
    }

    public function opd(Request $request, RenstraOpd $renstraOpd, PohonKinerjaService $pohonKinerjaService): InertiaResponse|JsonResponse
    {
        $this->authorize('view', $renstraOpd);

        return $this->respond($request, $this->payload($request, $pohonKinerjaService, 'opd', null, $renstraOpd->id));
    }

    public function cascadingOpd(Request $request, RenstraOpd $renstraOpd, PohonKinerjaService $pohonKinerjaService): InertiaResponse|JsonResponse
    {
        $this->authorize('view', $renstraOpd);

        return $this->respond($request, $this->payload($request, $pohonKinerjaService, 'cascading', null, $renstraOpd->id));
    }

    private function respond(Request $request, array $payload): InertiaResponse|JsonResponse
    {
        if ($request->wantsJson() || $request->query('format') === 'json') {
            return response()->json($payload);
        }

        return Inertia::render('Perencanaan/PohonKinerja', $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Request $request, PohonKinerjaService $pohonKinerjaService, string $mode, ?int $rpjmdId = null, ?int $renstraId = null): array
    {
        $user = $request->user();
        $rpjmdOptions = $this->rpjmdOptions($user);
        $renstraOptions = $this->renstraOptions($user);
        $selectedRpjmdId = $rpjmdId ?: (int) ($request->integer('rpjmd_id') ?: ($rpjmdOptions[0]['id'] ?? 0));
        $selectedRenstraId = $renstraId ?: (int) ($request->integer('renstra_opd_id') ?: ($renstraOptions[0]['id'] ?? 0));

        if ($mode === 'kabupaten') {
            if ($selectedRpjmdId <= 0) {
                return $this->basePayload($mode, $this->emptyTree($mode), $pohonKinerjaService, $rpjmdOptions, $renstraOptions, $selectedRpjmdId, $selectedRenstraId);
            }

            $rpjmd = Rpjmd::query()->findOrFail($selectedRpjmdId);
            $this->authorize('view', $rpjmd);
            $tree = $pohonKinerjaService->rpjmdTree($rpjmd, $this->opdScopeId($user));

            return $this->basePayload($mode, $tree, $pohonKinerjaService, $rpjmdOptions, $renstraOptions, $selectedRpjmdId, $selectedRenstraId);
        }

        if ($selectedRenstraId <= 0) {
            return $this->basePayload($mode, $this->emptyTree($mode), $pohonKinerjaService, $rpjmdOptions, $renstraOptions, $selectedRpjmdId, $selectedRenstraId);
        }

        $renstra = RenstraOpd::query()->findOrFail($selectedRenstraId);
        $this->authorize('view', $renstra);
        $tree = $mode === 'cascading'
            ? $pohonKinerjaService->cascadingOpdToRpjmdTree($renstra)
            : $pohonKinerjaService->renstraTree($renstra);

        return $this->basePayload($mode, $tree, $pohonKinerjaService, $rpjmdOptions, $renstraOptions, $selectedRpjmdId, $selectedRenstraId);
    }

    /**
     * @param  array<string, mixed>  $tree
     * @param  array<int, array<string, mixed>>  $rpjmdOptions
     * @param  array<int, array<string, mixed>>  $renstraOptions
     * @return array<string, mixed>
     */
    private function basePayload(string $mode, array $tree, PohonKinerjaService $pohonKinerjaService, array $rpjmdOptions, array $renstraOptions, int $selectedRpjmdId, int $selectedRenstraId): array
    {
        return [
            'mode' => $mode,
            'title' => $this->title($mode),
            'description' => $this->description($mode),
            'tree' => $tree,
            'stats' => $pohonKinerjaService->stats($tree),
            'filters' => [
                'mode' => $mode,
                'rpjmd_id' => $selectedRpjmdId ?: null,
                'renstra_opd_id' => $selectedRenstraId ?: null,
            ],
            'rpjmdOptions' => $rpjmdOptions,
            'renstraOptions' => $renstraOptions,
        ];
    }

    private function selectedMode(Request $request, User $user): string
    {
        $mode = $request->string('mode')->toString();

        if (in_array($mode, ['kabupaten', 'opd', 'cascading'], true)) {
            return $mode;
        }

        return $this->opdScopeId($user) ? 'opd' : 'kabupaten';
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyTree(string $mode): array
    {
        return [
            'key' => "empty:{$mode}",
            'type' => 'empty',
            'id' => $mode,
            'label' => 'Belum ada data pohon kinerja',
            'meta' => [],
            'linked_to' => null,
            'children' => [],
        ];
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function rpjmdOptions(User $user): array
    {
        return Rpjmd::query()
            ->with('periodeTahun:id,tahun,nama')
            ->orderByDesc('tahun_awal')
            ->orderBy('judul')
            ->get(['id', 'periode_tahun_id', 'judul', 'tahun_awal', 'tahun_akhir', 'status'])
            ->filter(fn (Rpjmd $rpjmd) => $user->can('view', $rpjmd))
            ->map(fn (Rpjmd $rpjmd) => [
                'id' => $rpjmd->id,
                'label' => "{$rpjmd->tahun_awal}-{$rpjmd->tahun_akhir} - {$rpjmd->judul}",
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{id: int, label: string}>
     */
    private function renstraOptions(User $user): array
    {
        return RenstraOpd::query()
            ->with(['opd:id,nama,singkatan', 'rpjmd:id,tahun_awal,tahun_akhir'])
            ->when($this->opdScopeId($user), fn (Builder $query, int $opdId) => $query->where('opd_id', $opdId))
            ->orderByDesc('tahun_awal')
            ->orderBy('judul')
            ->get(['id', 'opd_id', 'rpjmd_id', 'judul', 'tahun_awal', 'tahun_akhir', 'status'])
            ->filter(fn (RenstraOpd $renstra) => $user->can('view', $renstra))
            ->map(fn (RenstraOpd $renstra) => [
                'id' => $renstra->id,
                'label' => ($renstra->opd?->singkatan ?: $renstra->opd?->nama ?: 'OPD')." - {$renstra->tahun_awal}-{$renstra->tahun_akhir} - {$renstra->judul}",
            ])
            ->values()
            ->all();
    }

    private function opdScopeId(User $user): ?int
    {
        return $user->hasRole('admin_opd')
            && ! $user->hasAnyRole([
                'super_admin',
                'admin_kabupaten_bagian_organisasi',
                'admin_kabupaten_bapperida',
                'admin_kabupaten_inspektorat',
                'admin_kabupaten_dinkominfo',
                'pimpinan',
            ])
            ? (int) $user->opd_id
            : null;
    }

    private function title(string $mode): string
    {
        return match ($mode) {
            'opd' => 'Pohon Kinerja OPD',
            'cascading' => 'Cascading OPD ke RPJMD',
            default => 'Pohon Kinerja Kabupaten',
        };
    }

    private function description(string $mode): string
    {
        return match ($mode) {
            'opd' => 'Visualisasi tujuan, sasaran, program, kegiatan, sub kegiatan, indikator, dan target Renstra OPD.',
            'cascading' => 'Visualisasi keterhubungan cascading Renstra OPD dengan tujuan, sasaran, program, dan indikator RPJMD.',
            default => 'Visualisasi visi, misi, tujuan, sasaran, strategi, program, indikator, target, dan OPD penanggung jawab RPJMD.',
        };
    }
}
