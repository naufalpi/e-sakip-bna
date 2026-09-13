<?php

namespace App\Http\Requests\Master;

use App\Models\ReferensiJabatan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateReferensiJabatanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermission('referensi_jabatan.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'identity_key' => ReferensiJabatan::makeIdentityKey(
                (string) $this->input('jenis_jabatan'),
                (string) $this->input('nama'),
                $this->filled('jenjang') ? (string) $this->input('jenjang') : null,
            ),
        ]);
    }

    public function rules(): array
    {
        $reference = $this->route('referensiJabatan');

        return [
            'identity_key' => ['required', 'string', 'size:64', Rule::unique('referensi_jabatan', 'identity_key')->ignore($reference)],
            'kode' => ['nullable', 'string', 'max:120', Rule::unique('referensi_jabatan', 'kode')->ignore($reference)],
            'nama' => ['required', 'string', 'max:255'],
            'jenis_jabatan' => ['required', Rule::in(array_keys(ReferensiJabatan::jenisLabels()))],
            'jenjang' => ['nullable', 'string', 'max:120'],
            'kelas_jabatan' => ['nullable', 'integer', 'min:1', 'max:17'],
            'kualifikasi' => ['nullable', 'string', 'max:5000'],
            'dasar_hukum' => ['nullable', 'string', 'max:3000'],
            'berlaku_mulai' => ['nullable', 'date'],
            'berlaku_sampai' => ['nullable', 'date', 'after_or_equal:berlaku_mulai'],
            'verification_status' => ['required', Rule::in(['draft', 'verified'])],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
