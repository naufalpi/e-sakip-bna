<?php

namespace App\Http\Requests\Penganggaran;

use App\Models\DpaOpd;
use Illuminate\Foundation\Http\FormRequest;

class UpdateDpaOpdRequest extends FormRequest
{
    public function authorize(): bool
    {
        $dpa = $this->route('dpa_opd');

        return $dpa instanceof DpaOpd
            && (($this->user()?->can('update', $dpa) ?? false) || ($this->user()?->can('verifyBudget', $dpa) ?? false));
    }

    protected function prepareForValidation(): void
    {
        $payload = ['judul' => str($this->input('judul', ''))->trim()->upper()->toString()];
        foreach (['nomor_dpa', 'nomor_perda_apbd', 'nomor_perkada_penjabaran'] as $field) {
            $payload[$field] = filled($this->input($field)) ? str($this->input($field))->trim()->upper()->toString() : null;
        }
        foreach (['nama_pengguna_anggaran', 'nip_pengguna_anggaran', 'nama_ppkd', 'nip_ppkd', 'nama_sekretaris_daerah', 'nip_sekretaris_daerah'] as $field) {
            $payload[$field] = filled($this->input($field)) ? str($this->input($field))->trim()->toString() : null;
        }
        $this->merge($payload);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'max:255'],
            'nomor_dpa' => ['nullable', 'string', 'max:255'],
            'tanggal_pengesahan' => ['nullable', 'date'],
            'nomor_perda_apbd' => ['nullable', 'string', 'max:255'],
            'tanggal_perda_apbd' => ['nullable', 'date'],
            'nomor_perkada_penjabaran' => ['nullable', 'string', 'max:255'],
            'tanggal_perkada_penjabaran' => ['nullable', 'date'],
            'nama_pengguna_anggaran' => ['nullable', 'string', 'max:255'],
            'nip_pengguna_anggaran' => ['nullable', 'string', 'max:50'],
            'pengguna_anggaran_penempatan_id' => ['nullable', 'integer', 'exists:riwayat_pejabat_jabatan,id'],
            'nama_ppkd' => ['nullable', 'string', 'max:255'],
            'nip_ppkd' => ['nullable', 'string', 'max:50'],
            'ppkd_penempatan_id' => ['nullable', 'integer', 'exists:riwayat_pejabat_jabatan,id'],
            'nama_sekretaris_daerah' => ['nullable', 'string', 'max:255'],
            'nip_sekretaris_daerah' => ['nullable', 'string', 'max:50'],
            'sekretaris_daerah_penempatan_id' => ['nullable', 'integer', 'exists:riwayat_pejabat_jabatan,id'],
            'catatan' => ['nullable', 'string', 'max:5000'],
            'catatan_verifikasi' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
