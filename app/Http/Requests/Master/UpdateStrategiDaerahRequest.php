<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStrategiDaerahRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('strategi.manage');
    }

    public function rules(): array
    {
        return [
            'kode' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('strategi_daerah', 'kode')
                    ->ignore($this->route('strategiDaerah'))
                    ->whereNull('deleted_at'),
            ],
            'strategi' => ['required', 'string'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
