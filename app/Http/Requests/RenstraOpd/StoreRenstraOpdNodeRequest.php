<?php

namespace App\Http\Requests\RenstraOpd;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRenstraOpdNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('renstra_opd'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'pagu' => $this->normalizeCurrency($this->input('pagu')),
            'pagu_indikatif' => $this->normalizeCurrency($this->input('pagu_indikatif')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([
                'tujuan',
                'indikator_tujuan',
                'target_tujuan',
                'sasaran',
                'indikator_sasaran',
                'target_sasaran',
                'program',
                'indikator_program',
                'target_program',
                'kegiatan',
                'indikator_kegiatan',
                'target_kegiatan',
                'sub_kegiatan',
                'indikator_sub_kegiatan',
                'target_sub_kegiatan',
                'anggaran_sub_kegiatan',
            ])],
            'parent_id' => ['nullable', 'integer'],
            'periode_tahun_id' => ['nullable', 'integer', 'exists:periode_tahun,id'],
            'satuan_indikator_id' => ['nullable', 'integer', 'exists:satuan_indikator,id'],
            'tujuan_daerah_id' => ['nullable', 'integer', 'exists:tujuan_daerah,id'],
            'indikator_tujuan_daerah_id' => ['nullable', 'integer', 'exists:indikator_tujuan_daerah,id'],
            'sasaran_daerah_id' => ['nullable', 'integer', 'exists:sasaran_daerah,id'],
            'indikator_sasaran_daerah_id' => ['nullable', 'integer', 'exists:indikator_sasaran_daerah,id'],
            'program_rpjmd_id' => ['nullable', 'integer', 'exists:program_rpjmd,id'],
            'indikator_program_rpjmd_id' => ['nullable', 'integer', 'exists:indikator_program_rpjmd,id'],
            'program_pemerintahan_id' => ['nullable', 'integer', 'exists:program_pemerintahan,id'],
            'kegiatan_pemerintahan_id' => ['nullable', 'required_if:type,kegiatan', 'integer', 'exists:kegiatan_pemerintahan,id'],
            'sub_kegiatan_pemerintahan_id' => ['nullable', 'required_if:type,sub_kegiatan', 'integer', 'exists:sub_kegiatan_pemerintahan,id'],
            'opd_unit_id' => ['nullable', 'integer', 'exists:opd_units,id'],
            'kode' => ['nullable', 'string', 'max:80'],
            'uraian' => ['nullable', 'string'],
            'sasaran_level' => ['nullable', 'string'],
            'indikator' => ['nullable', 'string'],
            'tipe_indikator' => ['nullable', Rule::in(['positif', 'negatif'])],
            'definisi_operasional' => ['nullable', 'string'],
            'formula' => ['nullable', 'string'],
            'formulasi_pengukuran' => ['nullable', 'string'],
            'tipe_perhitungan' => ['nullable', Rule::in(['kumulatif', 'non_kumulatif'])],
            'opd_penanggung_jawab_id' => ['nullable', 'integer', 'exists:opds,id'],
            'pd_penanggung_jawab' => ['nullable', 'string', 'max:255'],
            'sumber_data' => ['nullable', 'string', 'max:255'],
            'target' => ['nullable', 'numeric'],
            'target_text' => ['nullable', 'string', 'max:255'],
            'pagu' => ['nullable', 'numeric'],
            'pagu_indikatif' => ['nullable', 'numeric'],
            'urutan' => ['nullable', 'integer', 'min:1', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'kegiatan_pemerintahan_id.required_if' => 'Pilih kegiatan dari master terlebih dahulu.',
            'sub_kegiatan_pemerintahan_id.required_if' => 'Pilih sub kegiatan dari master terlebih dahulu.',
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
