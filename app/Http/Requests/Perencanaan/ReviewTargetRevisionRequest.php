<?php

namespace App\Http\Requests\Perencanaan;

use Illuminate\Foundation\Http\FormRequest;

class ReviewTargetRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
