<?php

namespace App\Http\Requests\Kinerja;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePerjanjianKinerjaItemTargetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('perjanjian_kinerja'));
    }

    public function rules(): array
    {
        return [
            'target_text' => ['required_unless:restore_source,true', 'nullable', 'string', 'max:255'],
            'restore_source' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'target_text.required_unless' => 'Target PK wajib diisi.',
        ];
    }
}
