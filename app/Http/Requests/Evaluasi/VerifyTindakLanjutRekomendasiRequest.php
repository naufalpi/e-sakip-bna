<?php

namespace App\Http\Requests\Evaluasi;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VerifyTindakLanjutRekomendasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['super_admin', 'admin_kabupaten_inspektorat'])
            && $this->user()->hasAnyPermission(['evaluasi.manage', 'manage_evaluasi']);
    }

    public function rules(): array
    {
        return [
            'status_tindak_lanjut' => ['required', Rule::in(['proses', 'selesai', 'ditolak', 'perlu_perbaikan'])],
            'catatan_verifikator' => ['required_if:status_tindak_lanjut,ditolak,perlu_perbaikan', 'nullable', 'string'],
        ];
    }
}
