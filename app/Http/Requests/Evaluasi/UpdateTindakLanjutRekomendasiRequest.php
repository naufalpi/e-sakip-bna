<?php

namespace App\Http\Requests\Evaluasi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTindakLanjutRekomendasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $tindakLanjut = $this->route('tindak_lanjut');

        return $this->user()->hasRole('admin_opd')
            && filled($this->user()->opd_id)
            && (int) $tindakLanjut->opd_id === (int) $this->user()->opd_id
            && ! ((string) $tindakLanjut->status_tindak_lanjut === 'selesai' && filled($tindakLanjut->diverifikasi_oleh));
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
