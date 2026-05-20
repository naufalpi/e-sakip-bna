<?php

namespace App\Http\Requests\Kinerja;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRencanaAksiItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('rencana_aksi'));
    }

    public function rules(): array
    {
        return [
            'perjanjian_kinerja_item_id' => ['nullable', 'integer', 'exists:perjanjian_kinerja_items,id'],
            'opd_program_id' => ['nullable', 'integer', 'exists:opd_program,id'],
            'opd_kegiatan_id' => ['nullable', 'integer', 'exists:opd_kegiatan,id'],
            'opd_sub_kegiatan_id' => ['nullable', 'integer', 'exists:opd_sub_kegiatan,id'],
            'periode_realisasi' => ['required', Rule::in(['bulanan', 'triwulan', 'semester', 'tahunan'])],
            'triwulan' => ['nullable', Rule::in(['tw1', 'tw2', 'tw3', 'tw4'])],
            'bulan' => ['nullable', 'integer', 'min:1', 'max:12'],
            'aksi' => ['required', 'string'],
            'indikator' => ['nullable', 'string'],
            'target' => ['nullable', 'numeric'],
            'target_text' => ['nullable', 'string', 'max:255'],
            'anggaran' => ['nullable', 'numeric'],
            'penanggung_jawab' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:30'],
            'urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }
}
