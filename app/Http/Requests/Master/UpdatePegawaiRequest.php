<?php

namespace App\Http\Requests\Master;

use Illuminate\Validation\Rule;

class UpdatePegawaiRequest extends StorePegawaiRequest
{
    public function rules(): array
    {
        $rules = parent::rules();
        $pegawai = $this->route('pegawai');

        $rules['user_id'] = ['nullable', 'integer', 'exists:users,id', Rule::unique('pegawai', 'user_id')->ignore($pegawai?->id)];
        $rules['nip'] = ['nullable', 'string', 'max:30', Rule::unique('pegawai', 'nip')->ignore($pegawai?->id)];

        return $rules;
    }
}
