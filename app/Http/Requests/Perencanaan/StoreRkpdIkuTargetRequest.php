<?php

namespace App\Http\Requests\Perencanaan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRkpdIkuTargetRequest extends FormRequest
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
            'indikator_type' => ['required', 'string', Rule::in(['indikator_tujuan_daerah', 'indikator_sasaran_daerah'])],
            'indikator_id' => ['required', 'integer'],
            'target_rkpd' => ['nullable', 'string', 'max:255'],
        ];
    }
}
