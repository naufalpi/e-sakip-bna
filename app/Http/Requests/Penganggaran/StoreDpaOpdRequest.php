<?php

namespace App\Http\Requests\Penganggaran;

use App\Models\DpaOpd;
use App\Models\RkaOpd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreDpaOpdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', DpaOpd::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge($this->normalizedHeader());
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'rka_opd_id' => ['required', 'integer', 'exists:rka_opd,id'],
            'judul' => ['required', 'string', 'max:255'],
            'nomor_dpa' => ['nullable', 'string', 'max:255'],
            'tanggal_pengesahan' => ['nullable', 'date'],
            'nama_pengguna_anggaran' => ['nullable', 'string', 'max:255'],
            'nip_pengguna_anggaran' => ['nullable', 'string', 'max:50'],
            'pengguna_anggaran_penempatan_id' => ['nullable', 'integer', 'exists:riwayat_pejabat_jabatan,id'],
            'catatan' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rka = RkaOpd::query()->find($this->integer('rka_opd_id'));
            if (! $rka) {
                return;
            }

            if (! in_array($rka->status, ['approved', 'locked'], true)) {
                $validator->errors()->add('rka_opd_id', 'Pilih RKA yang sudah disetujui atau dikunci.');
            }

            if ($this->user()?->hasRole('admin_opd')
                && (! $this->user()->canAccessOpd($rka->opd_id) || ! $this->user()->canAccessOpdUnit($rka->opd_unit_id))) {
                $validator->errors()->add('rka_opd_id', 'RKA tersebut berada di luar lingkup OPD Anda.');
            }

            if (DpaOpd::query()->where('rka_opd_id', $rka->id)->exists()) {
                $validator->errors()->add('rka_opd_id', 'DPA untuk RKA tersebut sudah tersedia.');
            }
        });
    }

    /** @return array<string, mixed> */
    private function normalizedHeader(): array
    {
        $payload = ['judul' => str($this->input('judul', ''))->trim()->upper()->toString()];
        foreach (['nomor_dpa'] as $field) {
            $payload[$field] = filled($this->input($field)) ? str($this->input($field))->trim()->upper()->toString() : null;
        }
        foreach (['nama_pengguna_anggaran', 'nip_pengguna_anggaran'] as $field) {
            $payload[$field] = filled($this->input($field)) ? str($this->input($field))->trim()->toString() : null;
        }

        return $payload;
    }
}
