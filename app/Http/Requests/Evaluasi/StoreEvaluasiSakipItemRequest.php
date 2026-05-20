<?php

namespace App\Http\Requests\Evaluasi;

use Illuminate\Foundation\Http\FormRequest;

class StoreEvaluasiSakipItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('evaluasi_sakip'));
    }

    public function rules(): array
    {
        return [
            'kriteria_evaluasi_id' => ['required', 'integer', 'exists:kriteria_evaluasi,id'],
            'nilai' => ['required', 'numeric', 'min:0', 'max:100'],
            'catatan' => ['nullable', 'string'],
            'rekomendasi_text' => ['nullable', 'string'],
        ];
    }
}
