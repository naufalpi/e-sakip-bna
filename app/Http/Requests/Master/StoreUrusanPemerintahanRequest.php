<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUrusanPemerintahanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('urusan.manage');
    }

    public function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'max:80', Rule::unique('urusan_pemerintahan', 'kode')],
            'nama' => ['required', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
