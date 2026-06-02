<?php

namespace App\Http\Requests\RenstraOpd;

use App\Models\RenstraOpd;
use Illuminate\Foundation\Http\FormRequest;

class StoreRenstraOpdImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', RenstraOpd::class);
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
