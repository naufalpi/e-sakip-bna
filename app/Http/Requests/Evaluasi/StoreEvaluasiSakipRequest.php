<?php

namespace App\Http\Requests\Evaluasi;

use App\Models\EvaluasiSakip;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEvaluasiSakipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', EvaluasiSakip::class);
    }

    public function rules(): array
    {
        return [
            'opd_id' => ['required', 'integer', 'exists:opds,id'],
            'periode_tahun_id' => ['required', 'integer', 'exists:periode_tahun,id'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'tanggal_evaluasi' => ['nullable', 'date'],
            'status' => ['required', Rule::in(['draft', 'submitted', 'revision', 'verified', 'approved', 'rejected', 'locked'])],
            'catatan_umum' => ['nullable', 'string'],
        ];
    }
}
