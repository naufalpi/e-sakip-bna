<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSatuanIndikatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('satuan.manage');
    }

    public function rules(): array
    {
        return [
            'nama' => ['required', 'string', 'max:255', Rule::unique('satuan_indikator', 'nama')],
            'simbol' => ['nullable', 'string', 'max:30'],
            'jenis' => ['nullable', 'string', 'max:60'],
            'deskripsi' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
