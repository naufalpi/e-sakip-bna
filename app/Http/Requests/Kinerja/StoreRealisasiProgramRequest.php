<?php

namespace App\Http\Requests\Kinerja;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRealisasiProgramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('realisasi_kinerja'));
    }

    public function rules(): array
    {
        return [
            'perjanjian_kinerja_item_id' => ['nullable', 'integer', 'exists:perjanjian_kinerja_items,id'],
            'rencana_aksi_item_id' => ['nullable', 'integer', 'exists:rencana_aksi_items,id'],
            'opd_program_id' => ['nullable', 'integer', 'exists:opd_program,id'],
            'indikator_opd_program_id' => ['nullable', 'integer', 'exists:indikator_opd_program,id'],
            'tipe_indikator' => ['nullable', Rule::in(['positif', 'negatif'])],
            'indikator' => ['required', 'string'],
            'target' => ['nullable', 'numeric'],
            'target_text' => ['nullable', 'string', 'max:255'],
            'realisasi' => ['nullable', 'numeric'],
            'realisasi_text' => ['nullable', 'string', 'max:255'],
            'capaian_persen' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'status_capaian' => ['nullable', Rule::in(['merah', 'kuning', 'hijau'])],
            'anggaran' => ['nullable', 'numeric'],
            'realisasi_anggaran' => ['nullable', 'numeric'],
            'serapan_anggaran_persen' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'status_efisiensi' => ['nullable', Rule::in(['efisien', 'cukup_efisien', 'tidak_efisien'])],
            'analisis_efisiensi' => ['nullable', 'string'],
            'kendala' => ['nullable', 'string'],
            'tindak_lanjut' => ['nullable', 'string'],
            'urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }
}
