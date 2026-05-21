<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreSystemSettingRequest;
use App\Http\Requests\Master\UpdateSystemSettingRequest;
use App\Models\SystemSetting;
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

        $items = SystemSetting::query()
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
            ->through(fn (SystemSetting $setting) => $this->serialize($setting));

        return Inertia::render('Master/SystemSetting/Index', [
            'items' => $items,
            'filters' => $filters,
            'groupOptions' => $this->groupOptions(),
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
            'groupOptions' => $this->groupOptions(),
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function store(StoreSystemSettingRequest $request): RedirectResponse
    {
        $data = $this->normalizedData($request->validated());
        SystemSetting::create($data);

        return redirect()->route('master.system-settings.index')->with('success', 'Pengaturan sistem berhasil ditambahkan.');
    }

    public function edit(Request $request, SystemSetting $systemSetting): Response
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        return Inertia::render('Master/SystemSetting/Form', [
            'mode' => 'edit',
            'item' => $this->serialize($systemSetting),
            'groupOptions' => $this->groupOptions(),
            'typeOptions' => $this->typeOptions(),
        ]);
    }

    public function update(UpdateSystemSettingRequest $request, SystemSetting $systemSetting): RedirectResponse
    {
        $systemSetting->update($this->normalizedData($request->validated()));

        return redirect()->route('master.system-settings.index')->with('success', 'Pengaturan sistem berhasil diperbarui.');
    }

    public function destroy(Request $request, SystemSetting $systemSetting): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('settings.manage'), 403);

        $systemSetting->delete();

        return redirect()->route('master.system-settings.index')->with('success', 'Pengaturan sistem berhasil dihapus.');
    }

    private function serialize(SystemSetting $setting): array
    {
        return [
            'id' => $setting->id,
            'group' => $setting->group,
            'key' => $setting->key,
            'label' => $setting->label,
            'type' => $setting->type,
            'value' => $this->valueToString($setting->value),
            'raw_value' => $setting->value,
            'is_public' => $setting->is_public,
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

    private function groupOptions(): array
    {
        return SystemSetting::query()
            ->select('group')
            ->distinct()
            ->orderBy('group')
            ->pluck('group')
            ->filter()
            ->map(fn (string $group) => ['value' => $group, 'label' => str($group)->replace('_', ' ')->title()->toString()])
            ->values()
            ->all();
    }

    private function typeOptions(): array
    {
        return [
            ['value' => 'string', 'label' => 'String'],
            ['value' => 'text', 'label' => 'Teks'],
            ['value' => 'integer', 'label' => 'Integer'],
            ['value' => 'boolean', 'label' => 'Boolean'],
            ['value' => 'json', 'label' => 'JSON'],
        ];
    }
}
