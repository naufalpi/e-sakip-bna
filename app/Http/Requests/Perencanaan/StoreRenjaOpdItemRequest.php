<?php

namespace App\Http\Requests\Perencanaan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRenjaOpdItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $renjaOpd = $this->route('renja_opd');

        return $renjaOpd && ($this->user()?->can('update', $renjaOpd) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sub_kegiatan_pemerintahan_id' => ['required', 'integer', 'exists:sub_kegiatan_pemerintahan,id'],
            'indikator_sub_kegiatan_id' => ['nullable', 'integer', 'exists:indikator_sub_kegiatan,id'],
            'indikator' => ['nullable', 'string'],
            'target_akhir_renstra' => ['nullable', 'string', 'max:255'],
            'realisasi_capaian_renja_tahun_lalu' => ['nullable', 'string', 'max:255'],
            'prakiraan_capaian_target_renja_tahun_berjalan' => ['nullable', 'string', 'max:255'],
            'target' => ['nullable', 'string', 'max:255'],
            'pagu_indikatif' => ['nullable', 'numeric', 'min:0'],
            'lokasi' => ['nullable', 'string'],
            'sumber_dana' => ['nullable', 'string', 'max:255'],
            'prioritas_nasional' => ['nullable', 'string'],
            'prioritas_daerah' => ['nullable', 'string'],
            'kelompok_sasaran' => ['nullable', 'string'],
            'prakiraan_maju_target' => ['nullable', 'string', 'max:255'],
            'prakiraan_maju_pagu_indikatif' => ['nullable', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', Rule::in(['draft', 'submitted', 'revision', 'verified', 'approved', 'rejected', 'locked'])],
            'urutan' => ['nullable', 'integer', 'between:1,9999'],
        ];
    }
}
