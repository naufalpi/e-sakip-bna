<?php

namespace App\Http\Requests\Dokumen;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDokumenRequest extends FormRequest
{
    public function authorize(): bool
    {
        $dokumen = $this->route('dokumen') ?? $this->route('dokuman');

        if (! $this->user()->can('update', $dokumen)) {
            return false;
        }

        return ! $this->user()->hasRole('admin_opd')
            || blank($this->input('opd_id'))
            || ((int) $this->input('opd_id') === (int) $this->user()->opd_id);
    }

    public function rules(): array
    {
        return [
            'opd_id' => ['nullable', 'integer', 'exists:opds,id'],
            'periode_tahun_id' => ['nullable', 'integer', 'exists:periode_tahun,id'],
            'jenis' => ['required', Rule::in($this->jenisOptions())],
            'judul' => ['required', 'string', 'max:255'],
            'nomor_dokumen' => ['nullable', 'string', 'max:255'],
            'deskripsi' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['draft', 'submitted', 'revision', 'verified', 'approved', 'rejected', 'locked'])],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function jenisOptions(): array
    {
        return [
            'rpjmd',
            'renstra',
            'renja',
            'iku',
            'ikd',
            'perjanjian_kinerja',
            'rencana_aksi',
            'realisasi_kinerja',
            'bukti_dukung',
            'lkjip',
            'lke',
            'lhe',
            'rekomendasi',
            'tindak_lanjut',
            'lainnya',
        ];
    }
}
