<?php

namespace App\Http\Requests\Evaluasi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRekomendasiEvaluasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('evaluasi_sakip'));
    }

    public function rules(): array
    {
        return [
            'evaluasi_sakip_item_id' => ['nullable', 'integer', 'exists:evaluasi_sakip_items,id'],
            'nomor' => ['nullable', 'string', 'max:80'],
            'rekomendasi' => ['required', 'string'],
            'prioritas' => ['required', Rule::in(['rendah', 'sedang', 'tinggi'])],
            'status_tindak_lanjut' => ['nullable', Rule::in(['belum', 'proses', 'selesai', 'ditolak', 'perlu_perbaikan'])],
            'target_tanggal' => ['nullable', 'date'],
        ];
    }
}
