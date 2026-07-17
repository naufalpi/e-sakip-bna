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
            'program',
            'indikator_program',
            'target_program',
            'program_opd',
        ];

        return [
            'type' => ['required', Rule::in($types)],
            'parent_id' => ['nullable', 'integer'],
            'misi_ids' => ['nullable', 'array'],
            'misi_ids.*' => ['integer', 'exists:rpjmd_misi,id'],
            'indikator_tujuan_ids' => ['nullable', 'array'],
            'indikator_tujuan_ids.*' => ['integer', 'exists:indikator_tujuan_daerah,id'],
            'periode_tahun_id' => ['nullable', 'integer', 'exists:periode_tahun,id'],
            'satuan_indikator_id' => ['nullable', 'integer', 'exists:satuan_indikator,id'],
            'urusan_pemerintahan_id' => ['nullable', 'integer', 'exists:urusan_pemerintahan,id'],
            'strategi_daerah_id' => ['nullable', 'integer', 'exists:strategi_daerah,id'],
            'program_pemerintahan_id' => ['nullable', 'integer', 'exists:program_pemerintahan,id'],
            'program_pemerintahan_ids' => ['nullable', 'array'],
            'program_pemerintahan_ids.*' => ['integer', 'exists:program_pemerintahan,id'],
            'peran' => ['nullable', 'string', 'max:50'],
            'is_utama' => ['nullable', 'boolean'],
            'rows' => ['required', 'array', 'min:1', 'max:100'],
            'rows.*.parent_id' => ['nullable', 'integer'],
            'rows.*.misi_ids' => ['nullable', 'array'],
            'rows.*.misi_ids.*' => ['integer', 'exists:rpjmd_misi,id'],
            'rows.*.indikator_tujuan_ids' => ['nullable', 'array'],
            'rows.*.indikator_tujuan_ids.*' => ['integer', 'exists:indikator_tujuan_daerah,id'],
            'rows.*.periode_tahun_id' => ['nullable', 'integer', 'exists:periode_tahun,id'],
            'rows.*.existing_target_id' => ['nullable', 'integer'],
            'rows.*.satuan_indikator_id' => ['nullable', 'integer', 'exists:satuan_indikator,id'],
            'rows.*.opd_id' => ['nullable', 'integer', 'exists:opds,id'],
            'rows.*.urusan_pemerintahan_id' => ['nullable', 'integer', 'exists:urusan_pemerintahan,id'],
            'rows.*.strategi_daerah_id' => ['nullable', 'integer', 'exists:strategi_daerah,id'],
            'rows.*.program_pemerintahan_id' => ['nullable', 'integer', 'exists:program_pemerintahan,id'],
            'rows.*.program_pemerintahan_ids' => ['nullable', 'array'],
            'rows.*.program_pemerintahan_ids.*' => ['integer', 'exists:program_pemerintahan,id'],
            'rows.*.kode' => ['nullable', 'string', 'max:80'],
            'rows.*.uraian' => ['nullable', 'string'],
            'rows.*.indikator' => ['nullable', 'string'],
            'rows.*.definisi_operasional' => ['nullable', 'string'],
            'rows.*.alasan_pemilihan' => ['nullable', 'string'],
            'rows.*.formulasi_pengukuran' => ['nullable', 'string'],
            'rows.*.tipe_perhitungan' => ['nullable', Rule::in(['kumulatif', 'non_kumulatif'])],
            'rows.*.sumber_data' => ['nullable', 'string', 'max:255'],
            'rows.*.target' => ['nullable', 'string', 'max:255'],
            'rows.*.target_text' => ['nullable', 'string', 'max:255'],
            'rows.*.peran' => ['nullable', 'string', 'max:50'],
            'rows.*.is_utama' => ['nullable', 'boolean'],
            'rows.*.urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }
}
