<?php

namespace App\Http\Requests\Rpjmd;

use App\Models\Rpjmd;
use Illuminate\Foundation\Http\FormRequest;

class StoreRpjmdImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Rpjmd::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ];
    }
}
