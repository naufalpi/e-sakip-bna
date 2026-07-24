<?php

namespace App\Http\Requests\RenstraOpd;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRenstraOpdNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('renstra_opd'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([
                'tujuan',
                'indikator_tujuan',
                'target_tujuan',
                'sasaran',
                'indikator_sasaran',
                'target_sasaran',
                'program',
                'indikator_program',
                'target_program',
                'kegiatan',
                'indikator_kegiatan',
                'target_kegiatan',
                'sub_kegiatan',
                'indikator_sub_kegiatan',
                'target_sub_kegiatan',
            ])],
            'parent_id' => ['nullable', 'integer'],
            'periode_tahun_id' => ['nullable', 'integer', 'exists:periode_tahun,id'],
            'satuan_indikator_id' => ['nullable', 'integer', 'exists:satuan_indikator,id'],
            'tujuan_daerah_id' => ['nullable', 'integer', 'exists:tujuan_daerah,id'],
            'indikator_tujuan_daerah_id' => ['nullable', 'integer', 'exists:indikator_tujuan_daerah,id'],
            'sasaran_daerah_id' => ['nullable', 'integer', 'exists:sasaran_daerah,id'],
            'indikator_sasaran_daerah_id' => ['nullable', 'integer', 'exists:indikator_sasaran_daerah,id'],
            'program_rpjmd_id' => ['nullable', 'integer', 'exists:program_rpjmd,id'],
            'indikator_program_rpjmd_id' => ['nullable', 'integer', 'exists:indikator_program_rpjmd,id'],
            'program_pemerintahan_id' => ['nullable', 'integer', 'exists:program_pemerintahan,id'],
            'kegiatan_pemerintahan_id' => ['nullable', 'integer', 'exists:kegiatan_pemerintahan,id'],
            'sub_kegiatan_pemerintahan_id' => ['nullable', 'integer', 'exists:sub_kegiatan_pemerintahan,id'],
            'opd_unit_id' => ['nullable', 'integer', 'exists:opd_units,id'],
            'kode' => ['nullable', 'string', 'max:80'],
            'uraian' => ['nullable', 'string'],
            'sasaran_level' => ['nullable', 'string'],
            'indikator' => ['nullable', 'string'],
            'tipe_indikator' => ['nullable', Rule::in(['positif', 'negatif'])],
            'formula' => ['nullable', 'string'],
            'sumber_data' => ['nullable', 'string', 'max:255'],
            'target' => ['nullable', 'numeric'],
            'target_text' => ['nullable', 'string', 'max:255'],
            'pagu' => ['nullable', 'numeric'],
            'pagu_indikatif' => ['nullable', 'numeric'],
            'urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }
}
