<?php

namespace App\Http\Requests\Master;

use App\Models\RiwayatPejabatJabatan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePegawaiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('pegawai.manage');
    }

    public function rules(): array
    {
        return [
            'opd_id' => ['nullable', 'integer', 'exists:opds,id'],
            'opd_unit_id' => ['nullable', 'integer', 'exists:opd_units,id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', Rule::unique('pegawai', 'user_id')],
            'nama' => ['required', 'string', 'max:255'],
            'nip' => ['nullable', 'string', 'max:30', Rule::unique('pegawai', 'nip')],
            'pangkat_golongan' => ['nullable', 'string', 'max:120'],
            'jenis_pegawai' => ['required', Rule::in(['pejabat_negara', 'pns', 'pppk', 'non_asn'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'jabatan_organisasi_id' => ['nullable', 'integer', 'exists:jabatan_organisasi,id'],
            'jenis_penugasan' => [
                'nullable',
                'required_with:jabatan_organisasi_id',
                Rule::in(collect(RiwayatPejabatJabatan::penugasanOptions())->pluck('value')->all()),
            ],
            'nomor_sk' => ['nullable', 'string', 'max:150'],
            'tanggal_sk' => ['nullable', 'date'],
            'tanggal_mulai' => ['nullable', 'required_with:jabatan_organisasi_id', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ];
    }

    public function attributes(): array
    {
        return [
            'opd_id' => 'perangkat daerah',
            'opd_unit_id' => 'unit organisasi',
            'user_id' => 'akun pengguna',
            'nama' => 'nama pegawai',
            'nip' => 'NIP',
            'pangkat_golongan' => 'pangkat/golongan',
            'jenis_pegawai' => 'jenis pegawai',
            'jabatan_organisasi_id' => 'jabatan',
            'jenis_penugasan' => 'jenis jabatan',
            'tanggal_mulai' => 'TMT jabatan',
        ];
    }
}
