<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePenugasanPengampuKinerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('pegawai.manage');
    }

    public function rules(): array
    {
        return [
            'penempatan_pegawai_id' => ['nullable', 'integer', 'exists:riwayat_pejabat_jabatan,id'],
            'periode_tahun_id' => ['required', 'integer', 'exists:periode_tahun,id'],
            'sumber_kinerja_type' => ['required', Rule::in(['sasaran', 'program', 'kegiatan', 'sub_kegiatan'])],
            'sumber_kinerja_id' => ['required', 'integer', 'min:1'],
            'peran' => ['required', Rule::in(['penanggung_jawab', 'anggota'])],
        ];
    }
}
