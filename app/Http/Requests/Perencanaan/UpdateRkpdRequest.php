<?php

namespace App\Http\Requests\Perencanaan;

use App\Models\PeriodeTahun;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRkpdRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rkpd = $this->route('rkpd');

        return $rkpd && ($this->user()?->can('update', $rkpd) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $tahun = $this->integer('tahun');
        $periodeTahunId = $this->input('periode_tahun_id') ?: ($tahun ? PeriodeTahun::query()->where('tahun', $tahun)->value('id') : null);

        $this->merge([
            'periode_tahun_id' => $periodeTahunId,
            'judul' => $this->uppercaseValue($this->input('judul')),
            'nomor_dokumen' => $this->uppercaseValue($this->input('nomor_dokumen'), true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rkpd = $this->route('rkpd');

        return [
            'rpjmd_id' => ['nullable', 'integer', 'exists:rpjmd,id'],
            'periode_tahun_id' => [
                'required',
                'integer',
                'exists:periode_tahun,id',
                Rule::unique('rkpd', 'periode_tahun_id')
                    ->ignore($rkpd?->id)
                    ->where(fn ($query) => $query->where('tahun', $this->input('tahun')))
                    ->whereNull('deleted_at'),
            ],
            'tahun' => ['required', 'integer', 'between:2000,2100'],
            'judul' => ['required', 'string', 'max:255'],
            'nomor_dokumen' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(['draft', 'submitted', 'revision', 'verified', 'approved', 'rejected', 'locked'])],
            'catatan' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'periode_tahun_id.unique' => 'RKPD untuk tahun tersebut sudah ada. Silakan buka dokumen RKPD yang sudah dibuat atau pilih tahun lain.',
        ];
    }

    private function uppercaseValue(mixed $value, bool $nullable = false): ?string
    {
        $text = trim((string) ($value ?? ''));

        if ($nullable && $text === '') {
            return null;
        }

        return mb_strtoupper($text, 'UTF-8');
    }
}
