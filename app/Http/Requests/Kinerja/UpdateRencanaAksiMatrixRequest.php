<?php

namespace App\Http\Requests\Kinerja;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRencanaAksiMatrixRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('rencana_aksi'));
    }

    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'distinct', 'exists:rencana_aksi_items,id'],
            'items.*.formula' => ['required', 'string', 'max:5000'],
            'items.*.penanggung_jawab' => ['required', 'string', 'max:255'],
            'items.*.target_triwulan' => ['required', 'array', 'size:4'],
            'items.*.target_triwulan.*.triwulan' => ['required', 'integer', 'between:1,4'],
            'items.*.target_triwulan.*.target_text' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'items.*.formula.required' => 'Formula wajib diisi pada seluruh baris.',
            'items.*.penanggung_jawab.required' => 'Penanggung jawab wajib diisi pada seluruh baris.',
            'items.*.target_triwulan.size' => 'Target pelaksanaan harus tersedia untuk Triwulan I sampai IV.',
            'items.*.target_triwulan.*.target_text.required' => 'Target setiap triwulan wajib diisi.',
        ];
    }
}
