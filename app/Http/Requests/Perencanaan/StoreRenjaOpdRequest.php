<?php

namespace App\Http\Requests\Perencanaan;

use App\Models\RenjaOpd;
use App\Models\PeriodeTahun;
use Illuminate\Foundation\Http\FormRequest;

class StoreRenjaOpdRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user?->can('create', RenjaOpd::class)) {
            return false;
        }

        if ($user->hasRole('admin_opd') && ! $user->isSuperAdmin()) {
            return $user->canAccessOpd((int) $this->input('opd_id'))
                && $user->canAccessOpdUnit($this->input('opd_unit_id') ? (int) $this->input('opd_unit_id') : null);
        }

        return true;
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
            'status' => 'draft',
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
            'status' => ['nullable', 'string', 'in:draft'],
            'catatan' => ['nullable', 'string'],
        ];
    }
}
