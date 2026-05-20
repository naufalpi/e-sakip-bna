<?php

namespace App\Http\Requests\Evaluasi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLheRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('evaluasi_sakip'));
    }

    public function rules(): array
    {
        return [
            'nomor_lhe' => ['nullable', 'string', 'max:255'],
            'tanggal_lhe' => ['nullable', 'date'],
            'ringkasan' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['draft', 'submitted', 'revision', 'verified', 'approved', 'rejected', 'locked'])],
        ];
    }
}
