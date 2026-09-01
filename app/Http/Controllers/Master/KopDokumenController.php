<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\KopDokumen;
use App\Models\Opd;
use App\Services\Kinerja\KopDokumenService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class KopDokumenController extends Controller
{
    public function index(Request $request, KopDokumenService $kopService): Response
    {
        $this->authorizeView($request);
        $user = $request->user();

        $opds = Opd::query()
            ->where('status', 'active')
            ->when($user->hasRole('admin_opd'), fn (Builder $query) => $query->whereKey($user->opd_id))
            ->orderBy('nama')
            ->get(['id', 'kode', 'nama', 'singkatan', 'alamat', 'telepon', 'email']);

        $profiles = KopDokumen::query()
            ->whereIn('scope_key', [
                ...($user->hasRole('admin_opd') ? [] : ['kabupaten']),
                ...$opds->map(fn (Opd $opd) => 'opd:'.$opd->id)->all(),
            ])
            ->get()
            ->keyBy('scope_key');

        $items = collect();
        if (! $user->hasRole('admin_opd')) {
            $items->push($this->serializeProfile('kabupaten', 'Kop Kabupaten', null, $profiles->get('kabupaten'), $kopService));
        }

        $opds->each(function (Opd $opd) use ($items, $profiles, $kopService): void {
            $scopeKey = 'opd:'.$opd->id;
            $items->push($this->serializeProfile($scopeKey, $opd->singkatan ?: $opd->nama, $opd, $profiles->get($scopeKey), $kopService));
        });

        return Inertia::render('Master/KopDokumen/Index', [
            'items' => $items->values()->all(),
            'can' => ['manage' => $this->canManage($request)],
        ]);
    }

    public function update(Request $request, string $scopeKey): RedirectResponse
    {
        abort_unless($this->canManage($request), 403);
        $opd = $this->resolveScope($request, $scopeKey);

        $data = $request->validate([
            'nama_pemerintah' => ['required', 'string', 'max:255'],
            'nama_instansi' => ['required', 'string', 'max:255'],
            'alamat' => ['nullable', 'string', 'max:1000'],
            'telepon' => ['nullable', 'string', 'max:100'],
            'faksimile' => ['nullable', 'string', 'max:100'],
            'website' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'kota' => ['required', 'string', 'max:100'],
            'kode_pos' => ['nullable', 'string', 'max:20'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
            'hapus_logo' => ['nullable', 'boolean'],
        ]);

        $profile = KopDokumen::query()->firstOrNew(['scope_key' => $scopeKey]);
        $logoPath = $profile->logo_path;

        if ($request->boolean('hapus_logo')) {
            $logoPath = null;
        }
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('kop-dokumen', 'public');
        }

        $profile->fill([
            ...collect($data)->except(['logo', 'hapus_logo'])->all(),
            'opd_id' => $opd?->id,
            'logo_path' => $logoPath,
            'is_active' => true,
        ])->save();

        return back()->with('success', 'Kop dokumen berhasil diperbarui. Dokumen PK baru akan memakai pengaturan ini.');
    }

    private function serializeProfile(string $scopeKey, string $label, ?Opd $opd, ?KopDokumen $profile, KopDokumenService $kopService): array
    {
        $values = $kopService->forScope($opd, $opd ? 'kepala_opd' : 'bupati');

        return [
            'id' => $profile?->id,
            'scope_key' => $scopeKey,
            'label' => $label,
            'opd' => $opd ? ['id' => $opd->id, 'kode' => $opd->kode, 'nama' => $opd->nama, 'singkatan' => $opd->singkatan] : null,
            'is_configured' => (bool) $profile,
            ...$values,
            'logo_url' => $values['logo_path'] ? Storage::disk('public')->url($values['logo_path']) : asset('images/logo-banjarnegara.png'),
        ];
    }

    private function resolveScope(Request $request, string $scopeKey): ?Opd
    {
        if ($scopeKey === 'kabupaten') {
            abort_if($request->user()->hasRole('admin_opd'), 403);

            return null;
        }

        if (! preg_match('/^opd:(\d+)$/', $scopeKey, $matches)) {
            throw ValidationException::withMessages(['scope_key' => 'Lingkup kop dokumen tidak valid.']);
        }

        $opd = Opd::query()->findOrFail((int) $matches[1]);
        abort_if($request->user()->hasRole('admin_opd') && (int) $request->user()->opd_id !== (int) $opd->id, 403);

        return $opd;
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()->isSuperAdmin() || $request->user()->hasAnyPermission(['kop_dokumen.view', 'kop_dokumen.manage']), 403);
    }

    private function canManage(Request $request): bool
    {
        return $request->user()->isSuperAdmin() || $request->user()->hasPermission('kop_dokumen.manage');
    }
}
