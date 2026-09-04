<?php

namespace App\Http\Requests\Perencanaan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CancelDocumentEstablishmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() === true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'alasan_pembatalan' => ['required', 'string', 'min:10', 'max:2000'],
            'konfirmasi' => ['required', Rule::in(['BATALKAN'])],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'alasan_pembatalan' => 'alasan pembatalan',
            'konfirmasi' => 'konfirmasi',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'konfirmasi.in' => 'Ketik BATALKAN untuk mengonfirmasi pembatalan penetapan.',
        ];
    }
}
