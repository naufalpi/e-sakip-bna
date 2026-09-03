<?php

namespace App\Http\Requests\Penganggaran;

use App\Models\RenjaOpd;
use App\Models\RkaOpd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreRkaOpdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', RkaOpd::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'judul' => str($this->input('judul', ''))->trim()->upper()->toString(),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'renja_opd_id' => ['required', 'integer', 'exists:renja_opd,id'],
            'judul' => ['required', 'string', 'max:255'],
            'catatan' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $renja = RenjaOpd::query()->find($this->integer('renja_opd_id'));
            if (! $renja) {
                return;
            }

            if (! in_array($renja->jenis_versi, ['ditetapkan', 'perubahan'], true)
                || ! in_array($renja->status, ['approved', 'locked'], true)) {
                $validator->errors()->add('renja_opd_id', 'Pilih RENJA Ditetapkan atau RENJA Perubahan yang sudah disetujui.');
            }

            if ($this->user()?->hasRole('admin_opd')
                && (! $this->user()->canAccessOpd($renja->opd_id) || ! $this->user()->canAccessOpdUnit($renja->opd_unit_id))) {
                $validator->errors()->add('renja_opd_id', 'RENJA tersebut berada di luar lingkup OPD Anda.');
            }

            if (RkaOpd::query()->where('renja_opd_id', $renja->id)->exists()) {
                $validator->errors()->add('renja_opd_id', 'RKA untuk versi RENJA tersebut sudah tersedia.');
            }
        });
    }
}
