<?php

namespace App\Http\Requests\Perencanaan;

use Illuminate\Foundation\Http\FormRequest;

class StoreRkpdItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rkpd = $this->route('rkpd');

        return $rkpd && ($this->user()?->can('update', $rkpd) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pagu_indikatif' => $this->normalizeCurrency($this->input('pagu_indikatif')),
            'prakiraan_maju_pagu_indikatif' => $this->normalizeCurrency($this->input('prakiraan_maju_pagu_indikatif')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'opd_id' => ['required', 'integer', 'exists:opds,id'],
            'opd_unit_id' => ['nullable', 'integer', 'exists:opd_units,id'],
            'sub_kegiatan_pemerintahan_id' => ['required', 'integer', 'exists:sub_kegiatan_pemerintahan,id'],
            'program_rpjmd_id' => ['nullable', 'integer', 'exists:program_rpjmd,id'],
            'indikator' => ['nullable', 'string'],
            'target_akhir_renstra' => ['nullable', 'string'],
            'realisasi_capaian_renja_tahun_lalu' => ['nullable', 'string', 'max:255'],
            'prakiraan_capaian_target_renja_tahun_berjalan' => ['nullable', 'string', 'max:255'],
            'target' => ['nullable', 'string', 'max:255'],
            'pagu_indikatif' => ['nullable', 'numeric', 'min:0'],
            'lokasi' => ['nullable', 'string'],
            'sumber_dana' => ['nullable', 'string', 'max:255'],
            'prioritas_nasional' => ['nullable', 'string'],
            'prioritas_daerah' => ['nullable', 'string'],
            'kelompok_sasaran' => ['nullable', 'string'],
            'prakiraan_maju_target' => ['nullable', 'string', 'max:255'],
            'prakiraan_maju_pagu_indikatif' => ['nullable', 'numeric', 'min:0'],
            'perangkat_daerah_penanggung_jawab' => ['nullable', 'string', 'max:255'],
            'urutan' => ['nullable', 'integer', 'between:1,9999'],
        ];
    }

    private function normalizeCurrency(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (! is_string($value)) {
            return $value;
        }

        $normalized = trim($value);

        if ($normalized === '') {
            return null;
        }

        $normalized = str_replace(' ', '', $normalized);

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);
        } elseif (str_contains($normalized, ',')) {
            $normalized = str_replace(',', '.', $normalized);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $normalized) === 1) {
            $normalized = str_replace('.', '', $normalized);
        }

        return $normalized;
    }
}
