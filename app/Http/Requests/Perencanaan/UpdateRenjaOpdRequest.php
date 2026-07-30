<?php

namespace App\Http\Requests\Perencanaan;

use App\Models\PeriodeTahun;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRenjaOpdRequest extends FormRequest
{
    public function authorize(): bool
    {
        $renjaOpd = $this->route('renja_opd');

        return $renjaOpd && ($this->user()?->can('update', $renjaOpd) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $tahun = (int) $this->input('tahun');
        $periodeTahunId = $this->input('periode_tahun_id') ?: PeriodeTahun::query()
            ->where('tahun', $tahun)
            ->value('id');

        $this->merge([
            'periode_tahun_id' => $periodeTahunId,
            'judul' => str($this->input('judul', ''))->trim()->upper()->toString(),
            'nomor_dokumen' => filled($this->input('nomor_dokumen'))
                ? str($this->input('nomor_dokumen'))->trim()->upper()->toString()
                : null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'rkpd_id' => ['nullable', 'integer', 'exists:rkpd,id'],
            'renstra_opd_id' => ['nullable', 'integer', 'exists:renstra_opd,id'],
            'opd_id' => ['required', 'integer', 'exists:opds,id'],
            'opd_unit_id' => ['nullable', 'integer', 'exists:opd_units,id'],
            'periode_tahun_id' => ['required', 'integer', 'exists:periode_tahun,id'],
            'tahun' => ['required', 'integer', 'between:2000,2100'],
            'judul' => ['required', 'string', 'max:255'],
            'nomor_dokumen' => ['nullable', 'string', 'max:255'],
            'catatan' => ['nullable', 'string'],
        ];
    }
}
