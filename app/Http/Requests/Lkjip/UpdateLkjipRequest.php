<?php

namespace App\Http\Requests\Lkjip;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLkjipRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->can('update', $this->route('lkjip'))) {
            return false;
        }

        return ! $this->user()->hasRole('admin_opd')
            || ((int) $this->input('opd_id') === (int) $this->user()->opd_id);
    }

    public function rules(): array
    {
        return [
            'opd_id' => ['required', 'integer', 'exists:opds,id'],
            'periode_tahun_id' => ['required', 'integer', 'exists:periode_tahun,id'],
            'perjanjian_kinerja_id' => ['nullable', 'integer', 'exists:perjanjian_kinerja,id'],
            'realisasi_kinerja_id' => ['nullable', 'integer', 'exists:realisasi_kinerja,id'],
            'evaluasi_sakip_id' => ['nullable', 'integer', 'exists:evaluasi_sakip,id'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'judul' => ['required', 'string', 'max:255'],
            'nomor_dokumen' => ['nullable', 'string', 'max:255'],
            'ringkasan_eksekutif' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['draft', 'submitted', 'revision', 'verified', 'approved', 'rejected', 'locked'])],
            'catatan' => ['nullable', 'string'],
        ];
    }
}
