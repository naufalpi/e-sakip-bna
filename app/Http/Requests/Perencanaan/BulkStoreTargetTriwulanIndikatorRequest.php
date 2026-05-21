<?php

namespace App\Http\Requests\Perencanaan;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkStoreTargetTriwulanIndikatorRequest extends FormRequest
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
            'targets' => ['required', 'array', 'min:1', 'max:4'],
            'targets.*.triwulan' => ['required', Rule::in(['tw1', 'tw2', 'tw3', 'tw4'])],
            'targets.*.target_text' => ['nullable', 'string', 'max:255'],
            'targets.*.target_angka' => ['nullable', 'numeric'],
            'targets.*.target_anggaran' => ['nullable', 'numeric'],
        ];
    }
}
