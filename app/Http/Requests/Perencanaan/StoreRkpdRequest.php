<?php

namespace App\Http\Requests\Perencanaan;

use App\Models\Rkpd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRkpdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Rkpd::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rpjmd_id' => ['nullable', 'integer', 'exists:rpjmd,id'],
            'periode_tahun_id' => [
                'required',
                'integer',
                'exists:periode_tahun,id',
                Rule::unique('rkpd', 'periode_tahun_id')
                    ->where(fn ($query) => $query->where('tahun', $this->input('tahun')))
                    ->whereNull('deleted_at'),
            ],
            'tahun' => ['required', 'integer', 'between:2000,2100'],
            'judul' => ['required', 'string', 'max:255'],
            'nomor_dokumen' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'submitted', 'revision', 'verified', 'approved', 'rejected', 'locked'])],
            'catatan' => ['nullable', 'string'],
        ];
    }
}
