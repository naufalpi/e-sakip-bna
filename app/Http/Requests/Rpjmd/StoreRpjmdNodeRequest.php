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
                'program',
                'indikator_program',
                'target_program',
                'program_opd',
            ])],
            'parent_id' => ['nullable', 'integer'],
            'misi_ids' => ['nullable', 'array'],
            'misi_ids.*' => ['integer', 'exists:rpjmd_misi,id'],
            'indikator_tujuan_ids' => ['nullable', 'array'],
            'indikator_tujuan_ids.*' => ['integer', 'exists:indikator_tujuan_daerah,id'],
            'periode_tahun_id' => ['nullable', 'integer', 'exists:periode_tahun,id'],
            'satuan_indikator_id' => ['nullable', 'integer', 'exists:satuan_indikator,id'],
            'opd_id' => ['nullable', 'integer', 'exists:opds,id'],
            'urusan_pemerintahan_id' => ['nullable', 'integer', 'exists:urusan_pemerintahan,id'],
            'strategi_daerah_id' => ['nullable', 'integer', 'exists:strategi_daerah,id'],
            'program_pemerintahan_id' => ['nullable', 'integer', 'exists:program_pemerintahan,id'],
            'kode' => ['nullable', 'string', 'max:80'],
            'uraian' => ['nullable', 'string'],
            'indikator' => ['nullable', 'string'],
            'definisi_operasional' => ['nullable', 'string'],
            'alasan_pemilihan' => ['nullable', 'string'],
            'formulasi_pengukuran' => ['nullable', 'string'],
            'tipe_perhitungan' => ['nullable', Rule::in(['kumulatif', 'non_kumulatif'])],
            'sumber_data' => ['nullable', 'string', 'max:255'],
            'target' => ['nullable', 'string', 'max:255'],
            'target_text' => ['nullable', 'string', 'max:255'],
            'peran' => ['nullable', 'string', 'max:50'],
            'is_utama' => ['nullable', 'boolean'],
            'urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }
}
