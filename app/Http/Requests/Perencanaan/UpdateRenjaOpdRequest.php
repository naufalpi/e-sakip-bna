<?php

namespace App\Http\Requests\Perencanaan;

use App\Models\PeriodeTahun;
use App\Models\RenjaOpd;
use App\Models\Rkpd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var RenjaOpd|null $renja */
            $renja = $this->route('renja_opd');
            if (! $renja) {
                return;
            }

            $duplicate = RenjaOpd::query()
                ->where('id', '!=', $renja->id)
                ->where('opd_id', $this->integer('opd_id'))
                ->where('periode_tahun_id', $this->integer('periode_tahun_id'))
                ->where('tahun', $this->integer('tahun'))
                ->where('jenis_versi', $renja->jenis_versi)
                ->when($this->filled('opd_unit_id'),
                    fn ($query) => $query->where('opd_unit_id', $this->integer('opd_unit_id')),
                    fn ($query) => $query->whereNull('opd_unit_id'))
                ->exists();

            if ($duplicate) {
                $validator->errors()->add('tahun', 'Versi RENJA untuk OPD, unit, dan tahun tersebut sudah tersedia.');
            }

            if ($this->filled('rkpd_id')) {
                $expectedVersion = $renja->jenis_versi === 'perubahan' ? 'perubahan' : 'awal';
                $rkpd = Rkpd::query()->find($this->integer('rkpd_id'));
                if ($rkpd && ($rkpd->jenis_versi !== $expectedVersion || (int) $rkpd->tahun !== $this->integer('tahun'))) {
                    $validator->errors()->add('rkpd_id', "Versi RENJA ini harus menggunakan RKPD {$expectedVersion} pada tahun yang sama.");
                }
            }
        });
    }
}
