<?php

namespace App\Http\Requests\RenstraOpd;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRenstraOpdRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->can('update', $this->route('renstra_opd'))) {
            return false;
        }

        if ($this->user()->hasRole('admin_opd')) {
            return filled($this->user()->opd_id)
                && (int) $this->input('opd_id') === (int) $this->user()->opd_id;
        }

        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'opd_id' => ['required', 'integer', 'exists:opds,id'],
            'rpjmd_id' => ['required', 'integer', 'exists:rpjmd,id'],
            'periode_tahun_id' => ['nullable', 'integer', 'exists:periode_tahun,id'],
            'judul' => ['required', 'string', 'max:255'],
            'nomor_dokumen' => ['nullable', 'string', 'max:255'],
            'tahun_awal' => ['required', 'integer', 'min:2000', 'max:2100'],
            'tahun_akhir' => ['required', 'integer', 'min:2000', 'max:2100', 'gte:tahun_awal'],
            'status' => ['required', Rule::in(['draft', 'submitted', 'revision', 'verified', 'approved', 'rejected', 'locked'])],
            'keterangan' => ['nullable', 'string'],
        ];
    }
}
