<?php

namespace App\Http\Requests\Master;

use App\Models\JabatanOrganisasi;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreJabatanOrganisasiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('jabatan_organisasi.manage')
            || $this->user()->hasPermission('jabatan_organisasi.manage_opd');
    }

    public function rules(): array
    {
        return [
            'opd_id' => ['nullable', 'integer', 'exists:opds,id'],
            'opd_unit_id' => ['nullable', 'integer', 'exists:opd_units,id'],
            'parent_id' => ['nullable', 'integer', 'exists:jabatan_organisasi,id'],
            'referensi_jabatan_id' => ['nullable', 'integer', 'exists:referensi_jabatan,id', 'required_if:level_jabatan,fungsional,pelaksana'],
            'nama' => [Rule::requiredIf(! in_array($this->input('level_jabatan'), ['fungsional', 'pelaksana'], true)), 'nullable', 'string', 'max:255'],
            'level_jabatan' => ['required', Rule::in(array_keys(JabatanOrganisasi::levelLabels()))],
            'eselon' => ['nullable', Rule::in(collect(JabatanOrganisasi::eselonOptions())->pluck('value')->all())],
            'urutan' => ['required', 'integer', 'min:0', 'max:65535'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    public function attributes(): array
    {
        return [
            'opd_id' => 'perangkat daerah',
            'opd_unit_id' => 'unit organisasi',
            'parent_id' => 'atasan langsung',
            'referensi_jabatan_id' => 'referensi jabatan',
            'level_jabatan' => 'level jabatan',
        ];
    }
}
