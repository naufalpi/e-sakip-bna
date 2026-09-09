<?php

namespace App\Http\Requests\Master;

use App\Models\SystemAnnouncement;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SystemAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
            'message' => trim((string) $this->input('message')),
            'link_label' => filled($this->input('link_label')) ? trim((string) $this->input('link_label')) : null,
            'link_url' => filled($this->input('link_url')) ? trim((string) $this->input('link_url')) : null,
            'target_roles' => collect($this->input('target_roles', []))->filter()->unique()->values()->all(),
            'target_opd_ids' => collect($this->input('target_opd_ids', []))->filter(fn ($id) => filled($id))->map(fn ($id) => (int) $id)->unique()->values()->all(),
            'is_active' => $this->boolean('is_active'),
            'is_dismissible' => $this->boolean('is_dismissible'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'message' => ['required', 'string', 'max:2000'],
            'type' => ['required', Rule::in(SystemAnnouncement::TYPES)],
            'audience' => ['required', Rule::in(SystemAnnouncement::AUDIENCES)],
            'target_roles' => ['nullable', 'array'],
            'target_roles.*' => ['string', 'distinct', 'exists:roles,name'],
            'target_opd_ids' => ['nullable', 'array'],
            'target_opd_ids.*' => ['integer', 'distinct', 'exists:opds,id'],
            'link_label' => ['nullable', 'string', 'max:80'],
            'link_url' => [
                'nullable',
                'string',
                'max:2048',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (blank($value)) {
                        return;
                    }

                    $isInternalPath = str_starts_with($value, '/') && ! str_starts_with($value, '//');
                    $scheme = parse_url((string) $value, PHP_URL_SCHEME);
                    $isExternalUrl = in_array(strtolower((string) $scheme), ['http', 'https'], true)
                        && filter_var($value, FILTER_VALIDATE_URL) !== false;

                    if (! $isInternalPath && ! $isExternalUrl) {
                        $fail('Tautan harus berupa alamat http/https atau path internal yang diawali /.');
                    }
                },
            ],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
            'is_active' => ['required', 'boolean'],
            'is_dismissible' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->input('audience') === 'roles' && empty($this->input('target_roles'))) {
                    $validator->errors()->add('target_roles', 'Pilih minimal satu role tujuan.');
                }

                if ($this->input('audience') === 'opds' && empty($this->input('target_opd_ids'))) {
                    $validator->errors()->add('target_opd_ids', 'Pilih minimal satu OPD tujuan.');
                }

                if (
                    ! $validator->errors()->hasAny(['starts_at', 'ends_at'])
                    && $this->filled('starts_at')
                    && $this->filled('ends_at')
                    && $this->date('ends_at')->lte($this->date('starts_at'))
                ) {
                    $validator->errors()->add('ends_at', 'Waktu selesai harus setelah waktu mulai.');
                }

                if ($this->filled('link_url') && blank($this->input('link_label'))) {
                    $validator->errors()->add('link_label', 'Teks tombol wajib diisi jika tautan digunakan.');
                }
            },
        ];
    }

    /** @return array<string, mixed> */
    public function announcementData(): array
    {
        $data = $this->validated();

        if ($data['audience'] !== 'roles') {
            $data['target_roles'] = null;
        }

        if ($data['audience'] !== 'opds') {
            $data['target_opd_ids'] = null;
        }

        return $data;
    }
}
