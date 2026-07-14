<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProgramPemerintahanReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('urusan.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = (string) $this->route('type');
        [$table, $parentColumn, $parentValue] = $this->referenceScope($type);

        return [
            'bidang_urusan_id' => [
                Rule::requiredIf($type === 'program'),
                'nullable',
                'integer',
                'exists:bidang_urusan,id',
            ],
            'program_pemerintahan_id' => [
                Rule::requiredIf($type === 'kegiatan'),
                'nullable',
                'integer',
                'exists:program_pemerintahan,id',
            ],
            'kegiatan_pemerintahan_id' => [
                Rule::requiredIf($type === 'sub_kegiatan'),
                'nullable',
                'integer',
                'exists:kegiatan_pemerintahan,id',
            ],
            'kode' => [
                'required',
                'string',
                'max:80',
                Rule::unique($table, 'kode')
                    ->where(fn ($query) => $query->where($parentColumn, $parentValue))
                    ->whereNull('deleted_at')
                    ->ignore($this->route('id')),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: mixed}
     */
    private function referenceScope(string $type): array
    {
        return match ($type) {
            'kegiatan' => ['kegiatan_pemerintahan', 'program_pemerintahan_id', $this->input('program_pemerintahan_id')],
            'sub_kegiatan' => ['sub_kegiatan_pemerintahan', 'kegiatan_pemerintahan_id', $this->input('kegiatan_pemerintahan_id')],
            default => ['program_pemerintahan', 'bidang_urusan_id', $this->input('bidang_urusan_id')],
        };
    }
}
