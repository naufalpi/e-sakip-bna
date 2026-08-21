<?php

namespace App\Http\Requests\Master;

use App\Models\RiwayatPejabatJabatan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePenempatanPegawaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('pegawai.manage');
    }

    public function rules(): array
    {
        return [
            'jabatan_organisasi_id' => ['required', 'integer', 'exists:jabatan_organisasi,id'],
            'jenis_penugasan' => ['required', Rule::in(collect(RiwayatPejabatJabatan::penugasanOptions())->pluck('value')->all())],
            'nomor_sk' => ['nullable', 'string', 'max:150'],
            'tanggal_sk' => ['nullable', 'date'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ];
    }
}
