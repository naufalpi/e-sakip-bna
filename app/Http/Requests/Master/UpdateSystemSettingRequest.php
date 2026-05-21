<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('settings.manage');
    }

    public function rules(): array
    {
        return [
            'group' => ['required', 'string', 'max:80'],
            'key' => ['required', 'string', 'max:120', Rule::unique('system_settings', 'key')->ignore($this->route('systemSetting'))],
            'label' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(['string', 'text', 'integer', 'boolean', 'json'])],
            'value' => ['nullable', 'string'],
            'is_public' => ['boolean'],
        ];
    }
}
