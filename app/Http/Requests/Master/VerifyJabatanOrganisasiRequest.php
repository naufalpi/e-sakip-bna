<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyJabatanOrganisasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('jabatan_organisasi.verify') ?? false;
    }

    public function rules(): array
    {
        return [
            'verification_status' => ['required', Rule::in(['verified', 'rejected'])],
            'verification_note' => [
                'nullable',
                'string',
                'max:2000',
                Rule::requiredIf(fn () => $this->input('verification_status') === 'rejected'),
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'verification_note' => 'catatan perbaikan',
        ];
    }
}
