<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreSystemSettingRequest;
use App\Http\Requests\Master\UpdateSystemSettingRequest;
use App\Models\SystemSetting;
use App\Support\SystemSettingCatalog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use JsonException;

class SystemSettingController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('settings.view'), 403);

        $filters = $request->only(['search', 'group', 'type', 'is_public']);

        $items = $this->visibleSettingsQuery($request)
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('key', 'ilike', "%{$search}%")
                        ->orWhere('label', 'ilike', "%{$search}%")
                        ->orWhere('group', 'ilike', "%{$search}%");
                });
            })
            ->when($filters['group'] ?? null, fn (Builder $query, string $group) => $query->where('group', $group))
            ->when($filters['type'] ?? null, fn (Builder $query, string $type) => $query->where('type', $type))
            ->when(($filters['is_public'] ?? '') !== '', fn (Builder $query) => $query->where('is_public', (bool) (int) $filters['is_public']))
            ->orderBy('group')
            ->orderBy('key')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (SystemSetting $setting) => $this->serialize($setting, $request));

        return Inertia::render('Master/SystemSetting/Index', [
            'items' => $items,
            'filters' => $filters,
            'groupSummaries' => $this->groupSummaries($request),
            'groupOptions' => $this->groupOptions($request),
            'typeOptions' => $this->typeOptions(),
            'can' => [
                'manage' => $request->user()->hasPermission('settings.manage'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        return Inertia::render('Master/SystemSetting/Form', [
            'mode' => 'create',
            'item' => null,
            'groupOptions' => $this->groupOptions($request),
            'typeOptions' => $this->typeOptions(),
            'settingCatalog' => $this->settingCatalog($request),
        ]);
    }

    public function store(StoreSystemSettingRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        abort_unless($this->canManageSettingKey($request, $validated['key']), 403);
        $this->validateCatalogOption($validated['key'], $validated['value'] ?? null);

        $data = $this->normalizedData($validated);
        SystemSetting::create($data);

        return redirect()->route('master.system-settings.index')->with('success', 'Pengaturan sistem berhasil ditambahkan.');
    }

    public function edit(Request $request, SystemSetting $systemSetting): Response
    {
        abort_unless($this->canManageSettingKey($request, $systemSetting->key), 403);

        return Inertia::render('Master/SystemSetting/Form', [
            'mode' => 'edit',
            'item' => $this->serialize($systemSetting, $request),
            'groupOptions' => $this->groupOptions($request),
            'typeOptions' => $this->typeOptions(),
            'settingCatalog' => $this->settingCatalog($request),
        ]);
    }

    public function update(UpdateSystemSettingRequest $request, SystemSetting $systemSetting): RedirectResponse
    {
        $validated = $request->validated();
        abort_unless($this->canManageSettingKey($request, $systemSetting->key), 403);
        abort_unless($this->canManageSettingKey($request, $validated['key']), 403);
        $this->validateCatalogOption($validated['key'], $validated['value'] ?? null);

        $systemSetting->update($this->normalizedData($validated));

        return redirect()->route('master.system-settings.index')->with('success', 'Pengaturan sistem berhasil diperbarui.');
    }

    public function destroy(Request $request, SystemSetting $systemSetting): RedirectResponse
    {
        abort_unless($this->canManageSettingKey($request, $systemSetting->key), 403);

        $systemSetting->delete();

        return redirect()->route('master.system-settings.index')->with('success', 'Pengaturan sistem berhasil dihapus.');
    }

    private function serialize(SystemSetting $setting, Request $request): array
    {
        $group = SystemSettingCatalog::group($setting->group);
        $catalog = SystemSettingCatalog::setting($setting->key);

        return [
            'id' => $setting->id,
            'group' => $setting->group,
            'group_label' => $group['label'] ?? str($setting->group)->replace('_', ' ')->title()->toString(),
            'group_description' => $group['description'] ?? null,
            'key' => $setting->key,
            'label' => $setting->label,
            'type' => $setting->type,
            'value' => $this->valueToString($setting->value),
            'raw_value' => $setting->value,
            'is_public' => $setting->is_public,
            'description' => $catalog['description'] ?? null,
            'placeholder' => $catalog['placeholder'] ?? null,
            'can_update' => $this->canManageSettingKey($request, $setting->key),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizedData(array $data): array
    {
        return [
            ...$data,
            'value' => $this->normalizeValue($data['value'] ?? null, (string) $data['type']),
            'is_public' => (bool) ($data['is_public'] ?? false),
        ];
    }

    private function normalizeValue(?string $value, string $type): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        return match ($type) {
            'integer' => (int) $value,
            'boolean' => in_array($value, ['1', 'true', 'yes', 'on'], true),
            'json' => $this->decodeJson($value),
            default => $value,
        };
    }

    private function decodeJson(string $value): mixed
    {
        try {
            return json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ValidationException::withMessages(['value' => 'Nilai harus berupa JSON valid.']);
        }
    }

    private function valueToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '';
        }

        return (string) $value;
    }

    private function groupOptions(Request $request): array
    {
        $catalogSettings = collect($this->visibleCatalogSettings($request));
        $catalogGroupKeys = $catalogSettings->pluck('group')->unique();
        $catalogOptions = collect(SystemSettingCatalog::groupOptions())
            ->filter(fn (array $group) => $catalogGroupKeys->contains($group['value']));

        $databaseOptions = $this->visibleSettingsQuery($request)
            ->select('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group')
            ->filter()
            ->map(fn (string $group) => ['value' => $group, 'label' => str($group)->replace('_', ' ')->title()->toString()])
            ->values();

        return $catalogOptions
            ->merge($databaseOptions)
            ->unique('value')
            ->values()
            ->all();
    }

    private function typeOptions(): array
    {
        return SystemSettingCatalog::typeOptions();
    }

    private function groupSummaries(Request $request): array
    {
        $counts = $this->visibleSettingsQuery($request)
            ->select('group')
            ->selectRaw('count(*) as aggregate')
            ->groupBy('group')
            ->pluck('aggregate', 'group');
        $visibleCatalogGroups = collect($this->visibleCatalogSettings($request))
            ->pluck('group')
            ->unique();

        $catalogGroups = collect(SystemSettingCatalog::groups())
            ->filter(fn (array $group, string $key) => $visibleCatalogGroups->contains($key))
            ->map(fn (array $group, string $key) => [
                'key' => $key,
                'label' => $group['label'],
                'description' => $group['description'],
                'count' => (int) ($counts[$key] ?? 0),
            ]);

        $customGroups = $counts
            ->keys()
            ->reject(fn (string $group) => SystemSettingCatalog::group($group) !== null)
            ->map(fn (string $group) => [
                'key' => $group,
                'label' => str($group)->replace('_', ' ')->title()->toString(),
                'description' => 'Grup pengaturan tambahan.',
                'count' => (int) $counts[$group],
            ]);

        return $catalogGroups
            ->merge($customGroups)
            ->values()
            ->all();
    }

    private function settingCatalog(Request $request): array
    {
        return [
            'groups' => SystemSettingCatalog::groups(),
            'settings' => $this->visibleCatalogSettings($request),
        ];
    }

    private function visibleSettingsQuery(Request $request): Builder
    {
        return SystemSetting::query()
            ->when(
                ! $request->user()->isSuperAdmin(),
                fn (Builder $query) => $query->whereNotIn('key', SystemSettingCatalog::superAdminOnlyKeys()),
            );
    }

    private function visibleCatalogSettings(Request $request): array
    {
        if ($request->user()->isSuperAdmin()) {
            return SystemSettingCatalog::settings();
        }

        return collect(SystemSettingCatalog::settings())
            ->reject(fn (array $setting) => (bool) ($setting['super_admin_only'] ?? false))
            ->all();
    }

    private function canManageSettingKey(Request $request, string $key): bool
    {
        if (! $request->user()->hasPermission('settings.manage')) {
            return false;
        }

        if (in_array($key, SystemSettingCatalog::superAdminOnlyKeys(), true)) {
            return $request->user()->isSuperAdmin();
        }

        return true;
    }

    private function validateCatalogOption(string $key, ?string $value): void
    {
        $catalog = SystemSettingCatalog::setting($key);
        $options = $catalog['options'] ?? [];

        if ($options === [] || $value === null || $value === '') {
            return;
        }

        $allowed = collect($options)->pluck('value')->all();

        if (! in_array($value, $allowed, true)) {
            throw ValidationException::withMessages(['value' => 'Nilai tidak tersedia dalam opsi pengaturan ini.']);
        }
    }
}
