<?php

namespace App\Http\Requests\Rpjmd;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRpjmdNodeBulkRequest extends FormRequest
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
        $types = [
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
        ];

        return [
            'type' => ['required', Rule::in($types)],
            'parent_id' => ['nullable', 'integer'],
            'periode_tahun_id' => ['nullable', 'integer', 'exists:periode_tahun,id'],
            'satuan_indikator_id' => ['nullable', 'integer', 'exists:satuan_indikator,id'],
            'urusan_pemerintahan_id' => ['nullable', 'integer', 'exists:urusan_pemerintahan,id'],
            'peran' => ['nullable', 'string', 'max:50'],
            'is_utama' => ['nullable', 'boolean'],
            'rows' => ['required', 'array', 'min:1', 'max:100'],
            'rows.*.parent_id' => ['nullable', 'integer'],
            'rows.*.periode_tahun_id' => ['nullable', 'integer', 'exists:periode_tahun,id'],
            'rows.*.satuan_indikator_id' => ['nullable', 'integer', 'exists:satuan_indikator,id'],
            'rows.*.opd_id' => ['nullable', 'integer', 'exists:opds,id'],
            'rows.*.urusan_pemerintahan_id' => ['nullable', 'integer', 'exists:urusan_pemerintahan,id'],
            'rows.*.kode' => ['nullable', 'string', 'max:80'],
            'rows.*.uraian' => ['nullable', 'string'],
            'rows.*.indikator' => ['nullable', 'string'],
            'rows.*.tipe_indikator' => ['nullable', Rule::in(['positif', 'negatif'])],
            'rows.*.formula' => ['nullable', 'string'],
            'rows.*.sumber_data' => ['nullable', 'string', 'max:255'],
            'rows.*.arah_kebijakan' => ['nullable', 'string'],
            'rows.*.target' => ['nullable', 'numeric'],
            'rows.*.target_text' => ['nullable', 'string', 'max:255'],
            'rows.*.pagu' => ['nullable', 'numeric'],
            'rows.*.pagu_indikatif' => ['nullable', 'numeric'],
            'rows.*.peran' => ['nullable', 'string', 'max:50'],
            'rows.*.is_utama' => ['nullable', 'boolean'],
            'rows.*.urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }
}
