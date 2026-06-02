<?php

namespace App\Http\Requests\Perencanaan;

use App\Services\Perencanaan\TargetRevisionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTargetRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'target_table' => ['required', 'string', Rule::in(app(TargetRevisionService::class)->supportedTargetTables())],
            'target_id' => ['required', 'integer', 'min:1'],
            'new_values' => ['required', 'array'],
            'new_values.target' => ['nullable', 'numeric'],
            'new_values.target_text' => ['nullable', 'string', 'max:255'],
            'new_values.pagu' => ['nullable', 'numeric', 'min:0'],
            'new_values.target_angka' => ['nullable', 'numeric'],
            'new_values.target_anggaran' => ['nullable', 'numeric', 'min:0'],
            'reason' => ['required', 'string', 'max:5000'],
            'document_number' => ['nullable', 'string', 'max:120'],
            'document_date' => ['nullable', 'date'],
            'dokumen_id' => ['nullable', 'integer', 'exists:dokumen,id'],
        ];
    }
}
