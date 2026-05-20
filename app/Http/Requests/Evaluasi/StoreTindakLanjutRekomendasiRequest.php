<?php

namespace App\Http\Requests\Evaluasi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTindakLanjutRekomendasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rekomendasi = $this->route('rekomendasi');

        return $this->user()->hasRole('admin_opd')
            && filled($this->user()->opd_id)
            && (int) $rekomendasi->opd_id === (int) $this->user()->opd_id;
    }

    public function rules(): array
    {
        return [
            'uraian_tindak_lanjut' => ['required', 'string'],
            'status_tindak_lanjut' => ['required', Rule::in(['belum', 'proses', 'selesai', 'ditolak', 'perlu_perbaikan'])],
            'tanggal_tindak_lanjut' => ['nullable', 'date'],
            'catatan_opd' => ['nullable', 'string'],
        ];
    }
}
