<?php

namespace App\Http\Requests\Perencanaan;

use App\Models\PeriodeTahun;
use App\Models\RenjaOpd;
use App\Models\RenstraOpd;
use App\Models\Rkpd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
        $rkpdId = $this->input('rkpd_id');
        $renstraOpdId = $this->input('renstra_opd_id');

        if (! filled($rkpdId) && $tahun) {
            $rkpdId = Rkpd::query()
                ->where('periode_tahun_id', $periodeTahunId)
                ->where('tahun', $tahun)
                ->where('jenis_versi', 'ditetapkan')
                ->whereIn('status', ['approved', 'locked'])
                ->where('is_active_version', true)
                ->orderByDesc('nomor_versi')
                ->value('id');
        }

        if (! filled($renstraOpdId) && $tahun && $this->filled('opd_id')) {
            $renstraOpdId = RenstraOpd::query()
                ->where('opd_id', $this->integer('opd_id'))
                ->where('is_active_version', true)
                ->where('tahun_awal', '<=', $tahun)
                ->where('tahun_akhir', '>=', $tahun)
                ->orderByDesc('nomor_versi')
                ->orderByDesc('id')
                ->value('id');
        }

        $this->merge([
            'periode_tahun_id' => $periodeTahunId,
            'rkpd_id' => $rkpdId,
            'renstra_opd_id' => $renstraOpdId,
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
            'rkpd_id' => ['required', 'integer', 'exists:rkpd,id'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $duplicate = RenjaOpd::query()
                ->where('opd_id', $this->integer('opd_id'))
                ->where('periode_tahun_id', $this->integer('periode_tahun_id'))
                ->where('tahun', $this->integer('tahun'))
                ->where('jenis_versi', 'awal')
                ->when($this->filled('opd_unit_id'),
                    fn ($query) => $query->where('opd_unit_id', $this->integer('opd_unit_id')),
                    fn ($query) => $query->whereNull('opd_unit_id'))
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('tahun', 'RENJA Awal untuk OPD, unit, dan tahun tersebut sudah tersedia.');
            }

            if ($this->filled('rkpd_id')) {
                $rkpd = Rkpd::query()->find($this->integer('rkpd_id'));
                if ($rkpd && ($rkpd->jenis_versi !== 'ditetapkan'
                    || ! in_array($rkpd->status, ['approved', 'locked'], true)
                    || ! $rkpd->is_active_version
                    || (int) $rkpd->tahun !== $this->integer('tahun'))) {
                    $validator->errors()->add('rkpd_id', 'RENJA Akhir Draft harus menggunakan RKPD Ditetapkan aktif pada tahun yang sama.');
                }
            }

            if ($this->filled('renstra_opd_id')) {
                $renstra = RenstraOpd::query()->find($this->integer('renstra_opd_id'));

                if ($renstra && (int) $renstra->opd_id !== $this->integer('opd_id')) {
                    $validator->errors()->add('renstra_opd_id', 'RENSTRA acuan harus berasal dari OPD yang sama.');
                } elseif ($renstra && (! $renstra->is_active_version
                    || $renstra->tahun_awal > $this->integer('tahun')
                    || $renstra->tahun_akhir < $this->integer('tahun'))) {
                    $validator->errors()->add('renstra_opd_id', 'Pilih RENSTRA aktif yang mencakup tahun RENJA.');
                }
            }
        });
    }
}
