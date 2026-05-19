<?php

namespace App\Http\Requests\Rpjmd;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRpjmdNodeRequest extends FormRequest
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
            'type' => ['required', Rule::in([
                'visi',
                'misi',
                'tujuan',
                'indikator_tujuan',
                'target_tujuan',
                'sasaran',
                'indikator_sasaran',
                'target_sasaran',
                'strategi',
                'program',
                'indikator_program',
                'target_program',
                'program_opd',
            ])],
            'parent_id' => ['nullable', 'integer'],
            'periode_tahun_id' => ['nullable', 'integer', 'exists:periode_tahun,id'],
            'satuan_indikator_id' => ['nullable', 'integer', 'exists:satuan_indikator,id'],
            'opd_id' => ['nullable', 'integer', 'exists:opds,id'],
            'urusan_pemerintahan_id' => ['nullable', 'integer', 'exists:urusan_pemerintahan,id'],
            'kode' => ['nullable', 'string', 'max:80'],
            'uraian' => ['nullable', 'string'],
            'indikator' => ['nullable', 'string'],
            'formula' => ['nullable', 'string'],
            'sumber_data' => ['nullable', 'string', 'max:255'],
            'arah_kebijakan' => ['nullable', 'string'],
            'target' => ['nullable', 'numeric'],
            'target_text' => ['nullable', 'string', 'max:255'],
            'pagu' => ['nullable', 'numeric'],
            'pagu_indikatif' => ['nullable', 'numeric'],
            'peran' => ['nullable', 'string', 'max:50'],
            'is_utama' => ['nullable', 'boolean'],
            'urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }
}
