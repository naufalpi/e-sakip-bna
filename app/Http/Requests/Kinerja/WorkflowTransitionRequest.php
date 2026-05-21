<?php

namespace App\Http\Requests\Kinerja;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WorkflowTransitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user();
    }

    public function rules(): array
    {
        return [
            'note' => ['required_if:action,reject,revision', 'nullable', 'string'],
            'current_reviewer_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['required', Rule::in(['submit', 'verify', 'approve', 'reject', 'revision', 'lock'])],
        ];
    }
}
