<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOpdUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('opd.manage')
            || $this->user()->hasPermission('opd_units.manage')
            || ($this->user()->hasRole('admin_opd') && filled($this->user()->opd_id));
    }

    public function rules(): array
    {
        return [
            'opd_id' => ['required', 'integer', 'exists:opds,id'],
            'parent_id' => ['nullable', 'integer', 'exists:opd_units,id'],
            'kode' => ['required', 'string', 'max:120', Rule::unique('opd_units', 'kode')->where('opd_id', $this->input('opd_id'))->ignore($this->route('opdUnit'))],
            'nama' => ['required', 'string', 'max:255'],
            'jenis_unit' => ['nullable', 'string', 'max:80'],
            'nama_pimpinan' => ['nullable', 'string', 'max:255'],
            'nip_pimpinan' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
