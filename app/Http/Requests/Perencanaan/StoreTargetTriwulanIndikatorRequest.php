<?php

namespace App\Http\Requests\Perencanaan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTargetTriwulanIndikatorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'related_table' => ['required', Rule::in([
                'indikator_tujuan_daerah',
                'indikator_sasaran_daerah',
                'indikator_program_rpjmd',
                'indikator_tujuan_opd',
                'indikator_sasaran_opd',
                'indikator_opd_program',
                'indikator_sub_kegiatan',
            ])],
            'related_id' => ['required', 'integer'],
            'periode_tahun_id' => ['required', 'integer', 'exists:periode_tahun,id'],
            'triwulan' => ['required', Rule::in(['tw1', 'tw2', 'tw3', 'tw4'])],
            'target_text' => ['nullable', 'string', 'max:255'],
            'target_angka' => ['nullable', 'numeric'],
            'target_anggaran' => ['nullable', 'numeric'],
        ];
    }
}
