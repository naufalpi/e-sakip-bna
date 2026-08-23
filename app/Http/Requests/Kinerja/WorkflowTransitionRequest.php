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
            'note' => ['required_if:action,reject,revision,unlock,withdraw,correct', 'nullable', 'string', 'max:5000'],
            'correction_reference' => ['required_if:action,correct', 'nullable', 'string', 'max:1000'],
            'current_reviewer_id' => ['nullable', 'integer', 'exists:users,id'],
            'action' => ['required', Rule::in(['submit', 'withdraw', 'verify', 'approve', 'reject', 'revision', 'lock', 'unlock', 'correct'])],
        ];
    }
}
