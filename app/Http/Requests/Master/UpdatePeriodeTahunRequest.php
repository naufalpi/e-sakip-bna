<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePeriodeTahunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('periode.manage');
    }

    public function rules(): array
    {
        return [
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100', Rule::unique('periode_tahun', 'tahun')->ignore($this->route('periodeTahun'))],
            'nama' => ['required', 'string', 'max:255'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'status' => ['required', Rule::in(['draft', 'active', 'locked', 'archived'])],
        ];
    }
}
