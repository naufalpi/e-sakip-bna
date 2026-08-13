<?php

namespace App\Http\Requests\Perencanaan;

use Illuminate\Foundation\Http\FormRequest;

class StoreRkpdItemImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rkpd = $this->route('rkpd');

        return $rkpd && ($this->user()?->can('update', $rkpd) ?? false);
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
