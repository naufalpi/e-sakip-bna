<?php

namespace App\Http\Requests\Rpjmd;

use App\Models\Rpjmd;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRpjmdRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Rpjmd::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'periode_tahun_id' => ['nullable', 'integer', 'exists:periode_tahun,id'],
            'judul' => ['required', 'string', 'max:255'],
            'nomor_perda' => ['nullable', 'string', 'max:255'],
            'tahun_awal' => ['required', 'integer', 'min:2000', 'max:2100'],
            'tahun_akhir' => ['required', 'integer', 'min:2000', 'max:2100', 'gte:tahun_awal'],
            'struktur_tujuan_mode' => ['nullable', Rule::in(['tujuan_lintas_misi', 'tujuan_per_misi'])],
            'struktur_sasaran_mode' => ['nullable', Rule::in(['sasaran_langsung_tujuan', 'sasaran_melalui_indikator_tujuan', 'campuran'])],
            'keterangan' => ['nullable', 'string'],
        ];
    }
}
