<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;

class StoreJabatanOrganisasiImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('jabatan_organisasi.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'File import harus menggunakan format Excel .xlsx.',
            'file.max' => 'Ukuran file import maksimal 10 MB.',
        ];
    }
}
