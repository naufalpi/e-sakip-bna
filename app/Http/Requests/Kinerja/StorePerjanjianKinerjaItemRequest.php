<?php

namespace App\Http\Requests\Kinerja;

use Illuminate\Foundation\Http\FormRequest;

class StorePerjanjianKinerjaItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('perjanjian_kinerja'));
    }

    public function rules(): array
    {
        return [
            'sasaran_opd_id' => ['nullable', 'integer', 'exists:sasaran_opd,id'],
            'indikator_sasaran_opd_id' => ['nullable', 'integer', 'exists:indikator_sasaran_opd,id'],
            'opd_program_id' => ['nullable', 'integer', 'exists:opd_program,id'],
            'satuan_indikator_id' => ['nullable', 'integer', 'exists:satuan_indikator,id'],
            'kode' => ['nullable', 'string', 'max:80'],
            'sasaran' => ['required', 'string'],
            'indikator' => ['required', 'string'],
            'target' => ['nullable', 'numeric'],
            'target_text' => ['nullable', 'string', 'max:255'],
            'urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }
}
