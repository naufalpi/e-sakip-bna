<?php

namespace App\Http\Requests\Master;

use App\Models\OpdUnit;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', User::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'opd_id' => ['nullable', 'integer', 'exists:opds,id'],
            'opd_unit_id' => ['nullable', 'integer', 'exists:opd_units,id'],
            'username' => ['required', 'string', 'max:100', 'alpha_dash:ascii', 'unique:users,username'],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'jabatan' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'status' => ['required', Rule::in(['active', 'inactive', 'suspended'])],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $opdUnitId = $this->input('opd_unit_id');

            if (blank($opdUnitId)) {
                return;
            }

            if (! is_numeric($opdUnitId)) {
                return;
            }

            $opdId = $this->input('opd_id');

            if (blank($opdId)) {
                $validator->errors()->add('opd_unit_id', 'Pilih OPD terlebih dahulu sebelum memilih unit.');

                return;
            }

            if (! is_numeric($opdId)) {
                return;
            }

            $belongsToOpd = OpdUnit::query()
                ->whereKey($opdUnitId)
                ->where('opd_id', $opdId)
                ->exists();

            if (! $belongsToOpd) {
                $validator->errors()->add('opd_unit_id', 'Unit harus berada pada OPD yang dipilih.');
            }
        });
    }
}
