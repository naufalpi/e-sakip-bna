<?php

namespace App\Http\Requests\Kinerja;

use App\Models\RealisasiKinerja;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRealisasiKinerjaRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->user()->can('create', RealisasiKinerja::class)) {
            return false;
        }

        return ! $this->user()->hasRole('admin_opd')
            || ((int) $this->input('opd_id') === (int) $this->user()->opd_id);
    }

    public function rules(): array
    {
        return [
            'opd_id' => ['required', 'integer', 'exists:opds,id'],
            'perjanjian_kinerja_id' => ['nullable', 'integer', 'exists:perjanjian_kinerja,id'],
            'rencana_aksi_id' => ['nullable', 'integer', 'exists:rencana_aksi,id'],
            'periode_tahun_id' => ['required', 'integer', 'exists:periode_tahun,id'],
            'tahun' => ['required', 'integer', 'min:2000', 'max:2100'],
            'periode_realisasi' => ['required', Rule::in(['bulanan', 'triwulan', 'semester', 'tahunan'])],
            'triwulan' => ['nullable', Rule::in(['tw1', 'tw2', 'tw3', 'tw4'])],
            'bulan' => ['nullable', 'integer', 'min:1', 'max:12'],
            'semester' => ['nullable', 'integer', 'min:1', 'max:2'],
            'status' => ['required', Rule::in(['draft', 'submitted', 'revision', 'verified', 'approved', 'rejected', 'locked'])],
            'catatan' => ['nullable', 'string'],
        ];
    }
}
