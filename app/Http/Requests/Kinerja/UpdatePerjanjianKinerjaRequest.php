<?php

namespace App\Http\Requests\Kinerja;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePerjanjianKinerjaRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $level = $this->input('level_pk');

        if ($level) {
            $this->merge([
                'tipe_pk' => $level === 'individu' ? 'individual' : 'cascading',
                'tempat_penandatanganan' => $this->input('tempat_penandatanganan', 'Banjarnegara'),
            ]);
        }
    }

    public function authorize(): bool
    {
        if (! $this->user()->can('update', $this->route('perjanjian_kinerja'))) {
            return false;
        }

        if ($this->user()->hasRole('admin_opd') && $this->input('level_pk') === 'bupati') {
            return false;
        }

        return ! $this->user()->hasRole('admin_opd')
            || ((int) $this->input('opd_id') === (int) $this->user()->opd_id);
    }

    public function rules(): array
    {
        return [
            'opd_id' => [Rule::requiredIf(fn () => $this->input('level_pk') !== 'bupati'), 'nullable', 'integer', 'exists:opds,id'],
            'pegawai_id' => ['required', 'integer', 'exists:pegawai,id'],
            'penempatan_pegawai_id' => ['nullable', 'integer', 'exists:riwayat_pejabat_jabatan,id'],
            'atasan_pegawai_id' => ['nullable', 'integer', 'different:pegawai_id', 'exists:pegawai,id'],
            'tipe_pk' => ['required', Rule::in(['cascading', 'individual'])],
            'level_pk' => ['nullable', Rule::in(['bupati', 'kepala_opd', 'struktural', 'individu'])],
            'renstra_opd_id' => [Rule::requiredIf(fn () => in_array($this->input('level_pk'), ['kepala_opd', 'struktural'], true) || (! $this->input('level_pk') && $this->input('tipe_pk') === 'cascading')), 'nullable', 'integer', 'exists:renstra_opd,id'],
            'rkpd_id' => [Rule::requiredIf(fn () => $this->input('level_pk') === 'bupati'), 'nullable', 'integer', 'exists:rkpd,id'],
            'dpa_opd_id' => [Rule::requiredIf(fn () => $this->input('level_pk') === 'kepala_opd'), 'nullable', 'integer', 'exists:dpa_opd,id'],
            'periode_tahun_id' => ['required', 'integer', 'exists:periode_tahun,id'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'judul' => ['required', 'string', 'max:255'],
            'nomor_dokumen' => ['nullable', 'string', 'max:255'],
            'tanggal_dokumen' => ['nullable', 'date'],
            'tempat_penandatanganan' => ['nullable', 'string', 'max:120'],
            'status' => ['required', Rule::in(['draft', 'submitted', 'revision', 'verified', 'approved', 'rejected', 'locked'])],
            'catatan' => ['nullable', 'string'],
        ];
    }
}
