<?php

namespace App\Http\Requests\Rpjmd;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRpjmdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('rpjmd'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'periode_tahun_id' => ['nullable', 'integer', 'exists:periode_tahun,id'],
            'judul' => ['required', 'string', 'max:255'],
            'nomor_perda' => ['nullable', 'string', 'max:255'],
            'tahun_awal' => ['required', 'integer', 'min:2000', 'max:2100'],
            'tahun_akhir' => ['required', 'integer', 'min:2000', 'max:2100', 'gte:tahun_awal'],
            'status' => ['required', Rule::in(['draft', 'submitted', 'revision', 'verified', 'approved', 'rejected', 'locked'])],
            'keterangan' => ['nullable', 'string'],
        ];
    }
}
