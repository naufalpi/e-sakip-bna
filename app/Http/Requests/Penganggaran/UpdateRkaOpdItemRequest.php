<?php

namespace App\Http\Requests\Penganggaran;

use App\Models\RkaOpd;
use App\Models\RkaOpdItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateRkaOpdItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rka = $this->route('rka_opd');

        return $rka instanceof RkaOpd
            && ($this->user()?->can('update', $rka) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $currencyFields = [
            'alokasi_tahun_sebelumnya',
            'pagu_rka',
            'alokasi_tahun_berikutnya',
        ];
        $normalized = [];

        foreach ($currencyFields as $field) {
            $normalized[$field] = $this->normalizeCurrency($this->input($field));
        }

        $this->merge($normalized);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tolok_ukur_kinerja' => ['nullable', 'string', 'max:5000'],
            'target_kinerja' => ['nullable', 'string', 'max:255'],
            'satuan_kinerja' => ['nullable', 'string', 'max:100'],
            'sumber_pendanaan' => ['nullable', 'string', 'max:2000'],
            'lokasi' => ['nullable', 'string', 'max:2000'],
            'kelompok_sasaran' => ['nullable', 'string', 'max:2000'],
            'bulan_mulai' => ['required', 'integer', 'between:1,12'],
            'bulan_selesai' => ['required', 'integer', 'between:1,12', 'gte:bulan_mulai'],
            'alokasi_tahun_sebelumnya' => ['required', 'numeric', 'min:0'],
            'pagu_rka' => ['required', 'numeric', 'min:0'],
            'alokasi_tahun_berikutnya' => ['required', 'numeric', 'min:0'],
            'alasan_penyesuaian' => ['nullable', 'string', 'max:5000'],
            'catatan' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rka = $this->route('rka_opd');
            $item = $this->route('item');
            if (! $rka instanceof RkaOpd || ! $item instanceof RkaOpdItem) {
                return;
            }

            if (abs((float) $this->input('pagu_rka') - (float) $item->pagu_renja) > 0.001
                && blank($this->input('alasan_penyesuaian'))) {
                $validator->errors()->add('alasan_penyesuaian', 'Catatan perbedaan wajib diisi ketika Pagu RKA berbeda dari Pagu RENJA.');
            }
        });
    }

    private function normalizeCurrency(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_numeric($value)) {
            return $value;
        }

        return str_replace(['.', ',', ' '], '', (string) $value);
    }
}
