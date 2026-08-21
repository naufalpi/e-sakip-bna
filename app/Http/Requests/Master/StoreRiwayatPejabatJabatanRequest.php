<?php

namespace App\Http\Requests\Master;

use App\Models\RiwayatPejabatJabatan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRiwayatPejabatJabatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('jabatan_organisasi.manage')
            || $this->user()->hasPermission('pejabat_jabatan.manage');
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'nama_pejabat' => ['required', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'max:30'],
            'pangkat_golongan' => ['nullable', 'string', 'max:120'],
            'jenis_penugasan' => ['required', Rule::in(collect(RiwayatPejabatJabatan::penugasanOptions())->pluck('value')->all())],
            'nomor_sk' => ['nullable', 'string', 'max:150'],
            'tanggal_sk' => ['nullable', 'date'],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ];
    }

    public function attributes(): array
    {
        return [
            'user_id' => 'akun pengguna',
            'nama_pejabat' => 'nama pejabat',
            'pangkat_golongan' => 'pangkat/golongan',
            'jenis_penugasan' => 'jenis penugasan',
            'nomor_sk' => 'nomor SK',
            'tanggal_sk' => 'tanggal SK',
            'tanggal_mulai' => 'TMT Jabatan',
            'tanggal_selesai' => 'tanggal selesai jabatan',
        ];
    }
}
