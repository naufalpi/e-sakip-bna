<?php

namespace App\Http\Requests\Kinerja;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRencanaAksiRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->can('update', $this->route('rencana_aksi'))) {
            return false;
        }

        return ! $this->user()->hasRole('admin_opd')
            || ((int) $this->input('opd_id') === (int) $this->user()->opd_id);
    }

    public function rules(): array
    {
        return [
            'opd_id' => ['required', 'integer', 'exists:opds,id'],
            'perjanjian_kinerja_id' => ['nullable', 'integer', 'exists:perjanjian_kinerja,id'],
            'periode_tahun_id' => ['required', 'integer', 'exists:periode_tahun,id'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'judul' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'submitted', 'revision', 'verified', 'approved', 'rejected', 'locked'])],
            'catatan' => ['nullable', 'string'],
        ];
    }
}
