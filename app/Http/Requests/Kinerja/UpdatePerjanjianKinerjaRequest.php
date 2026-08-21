<?php

namespace App\Http\Requests\Kinerja;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePerjanjianKinerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->can('update', $this->route('perjanjian_kinerja'))) {
            return false;
        }

        return ! $this->user()->hasRole('admin_opd')
            || ((int) $this->input('opd_id') === (int) $this->user()->opd_id);
    }

    public function rules(): array
    {
        return [
            'opd_id' => ['required', 'integer', 'exists:opds,id'],
            'pegawai_id' => ['required', 'integer', 'exists:pegawai,id'],
            'penempatan_pegawai_id' => ['nullable', 'integer', 'exists:riwayat_pejabat_jabatan,id'],
            'atasan_pegawai_id' => ['nullable', 'integer', 'different:pegawai_id', 'exists:pegawai,id'],
            'tipe_pk' => ['required', Rule::in(['cascading', 'individual'])],
            'renstra_opd_id' => ['nullable', 'required_if:tipe_pk,cascading', 'integer', 'exists:renstra_opd,id'],
            'periode_tahun_id' => ['required', 'integer', 'exists:periode_tahun,id'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'judul' => ['required', 'string', 'max:255'],
            'nomor_dokumen' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(['draft', 'submitted', 'revision', 'verified', 'approved', 'rejected', 'locked'])],
            'catatan' => ['nullable', 'string'],
        ];
    }
}
