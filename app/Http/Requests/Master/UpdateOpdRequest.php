<?php

namespace App\Http\Requests\Master;

use App\Models\Opd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOpdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('opd'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Opd $opd */
        $opd = $this->route('opd');

        return [
            'urusan_pemerintahan_id' => ['nullable', 'integer', 'exists:urusan_pemerintahan,id'],
            'kode' => ['required', 'string', 'max:50', Rule::unique(Opd::class, 'kode')->ignore($opd->id)],
            'nama' => ['required', 'string', 'max:255'],
            'singkatan' => ['nullable', 'string', 'max:100'],
            'jenis' => ['nullable', 'string', 'max:100'],
            'alamat' => ['nullable', 'string'],
            'telepon' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'nama_kepala' => ['nullable', 'string', 'max:255'],
            'nip_kepala' => ['nullable', 'string', 'max:30'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
