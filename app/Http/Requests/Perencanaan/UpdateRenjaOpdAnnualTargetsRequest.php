<?php

namespace App\Http\Requests\Perencanaan;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRenjaOpdAnnualTargetsRequest extends FormRequest
{
    public function authorize(): bool
    {
        $renja = $this->route('renja_opd');

        return $renja && $this->user()?->can('update', $renja);
    }

    public function rules(): array
    {
        return [
            'targets' => ['required', 'array', 'min:1', 'max:200'],
            'targets.*.id' => ['required', 'integer', 'distinct'],
            'targets.*.target_text' => ['required', 'string', 'max:1000'],
            'targets.*.alasan_penyesuaian' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function messages(): array
    {
        return [
            'targets.required' => 'Tidak ada target tahunan yang dikirim.',
            'targets.*.target_text.required' => 'Target RENJA wajib diisi. Gunakan NA jika target belum dapat ditetapkan.',
            'targets.*.target_text.max' => 'Target RENJA maksimal 1.000 karakter.',
            'targets.*.alasan_penyesuaian.max' => 'Alasan penyesuaian maksimal 5.000 karakter.',
        ];
    }
}
