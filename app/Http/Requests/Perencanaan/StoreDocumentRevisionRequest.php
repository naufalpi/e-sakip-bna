<?php

namespace App\Http\Requests\Perencanaan;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRevisionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'alasan_perubahan' => ['required', 'string', 'max:2000'],
            'dasar_perubahan' => ['nullable', 'string', 'max:255'],
            'tanggal_berlaku' => ['nullable', 'date'],
        ];
    }

    public function attributes(): array
    {
        return [
            'alasan_perubahan' => 'alasan perubahan',
            'dasar_perubahan' => 'dasar perubahan',
            'tanggal_berlaku' => 'tanggal berlaku',
        ];
    }
}
