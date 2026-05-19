<?php

namespace App\Http\Requests\Rpjmd;

use App\Models\Rpjmd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRpjmdRequest extends FormRequest
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
