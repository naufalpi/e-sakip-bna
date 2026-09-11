<?php

namespace App\Http\Requests\Kinerja;

use App\Models\PerjanjianKinerja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePerjanjianKinerjaItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('perjanjian_kinerja'));
    }

    public function rules(): array
    {
        $pk = $this->route('perjanjian_kinerja');
        $isManualIndividual = $pk instanceof PerjanjianKinerja && $pk->tipe_pk === 'individual';

        return [
            'sumber_item' => ['nullable', Rule::in(['cascading', 'manual'])],
            'level_cascading' => ['nullable', Rule::in(['sasaran', 'program', 'kegiatan', 'sub_kegiatan'])],
            'cascading_source_type' => ['nullable', 'string', 'max:40'],
            'cascading_source_id' => ['nullable', 'integer', 'min:1'],
            'sasaran_opd_id' => ['nullable', 'integer', 'exists:sasaran_opd,id'],
            'indikator_sasaran_opd_id' => ['nullable', 'integer', 'exists:indikator_sasaran_opd,id'],
            'opd_program_id' => ['nullable', 'integer', 'exists:opd_program,id'],
            'satuan_indikator_id' => ['nullable', 'integer', 'exists:satuan_indikator,id'],
            'kode' => ['nullable', 'string', 'max:80'],
            'sasaran' => ['required', 'string'],
            'indikator' => ['required', 'string'],
            'target' => ['nullable', 'numeric'],
            'target_text' => [Rule::requiredIf($isManualIndividual), 'nullable', 'string', 'max:255'],
            'urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_text.required' => 'Target wajib diisi beserta satuannya, misalnya 10 Dokumen.',
        ];
    }
}
