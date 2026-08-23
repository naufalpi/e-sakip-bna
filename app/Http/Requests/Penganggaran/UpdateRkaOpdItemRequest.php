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
            && (($this->user()?->can('update', $rka) ?? false) || ($this->user()?->can('verifyBudget', $rka) ?? false));
    }

    protected function prepareForValidation(): void
    {
        $currencyFields = [
            'alokasi_tahun_sebelumnya',
            'pagu_belanja_operasi_usulan',
            'pagu_belanja_modal_usulan',
            'pagu_belanja_tidak_terduga_usulan',
            'pagu_belanja_transfer_usulan',
            'pagu_belanja_operasi_hasil_verifikasi',
            'pagu_belanja_modal_hasil_verifikasi',
            'pagu_belanja_tidak_terduga_hasil_verifikasi',
            'pagu_belanja_transfer_hasil_verifikasi',
            'alokasi_tahun_berikutnya',
        ];
        $normalized = [];

        foreach ($currencyFields as $field) {
            $normalized[$field] = $this->normalizeCurrency($this->input($field));
        }

        $normalized['pagu_usulan'] = collect([
            $normalized['pagu_belanja_operasi_usulan'],
            $normalized['pagu_belanja_modal_usulan'],
            $normalized['pagu_belanja_tidak_terduga_usulan'],
            $normalized['pagu_belanja_transfer_usulan'],
        ])->sum(fn (mixed $value) => (float) $value);
        $normalized['pagu_hasil_verifikasi'] = collect([
            $normalized['pagu_belanja_operasi_hasil_verifikasi'],
            $normalized['pagu_belanja_modal_hasil_verifikasi'],
            $normalized['pagu_belanja_tidak_terduga_hasil_verifikasi'],
            $normalized['pagu_belanja_transfer_hasil_verifikasi'],
        ])->sum(fn (mixed $value) => (float) $value);

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
            'pagu_usulan' => ['required', 'numeric', 'min:0'],
            'pagu_belanja_operasi_usulan' => ['required', 'numeric', 'min:0'],
            'pagu_belanja_modal_usulan' => ['required', 'numeric', 'min:0'],
            'pagu_belanja_tidak_terduga_usulan' => ['required', 'numeric', 'min:0'],
            'pagu_belanja_transfer_usulan' => ['required', 'numeric', 'min:0'],
            'pagu_hasil_verifikasi' => ['required', 'numeric', 'min:0'],
            'pagu_belanja_operasi_hasil_verifikasi' => ['required', 'numeric', 'min:0'],
            'pagu_belanja_modal_hasil_verifikasi' => ['required', 'numeric', 'min:0'],
            'pagu_belanja_tidak_terduga_hasil_verifikasi' => ['required', 'numeric', 'min:0'],
            'pagu_belanja_transfer_hasil_verifikasi' => ['required', 'numeric', 'min:0'],
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

            $canManage = $this->user()?->can('update', $rka) ?? false;
            $canVerify = $this->user()?->can('verifyBudget', $rka) ?? false;
            $proposal = (float) $this->input('pagu_usulan');
            $proposalChanged = $canManage && abs($proposal - (float) $item->pagu_renja) > 0.001;
            $verificationChanged = $canVerify && collect(['operasi', 'modal', 'tidak_terduga', 'transfer'])
                ->contains(function (string $type) use ($canManage, $item): bool {
                    $verified = (float) $this->input("pagu_belanja_{$type}_hasil_verifikasi");
                    $proposal = $canManage
                        ? (float) $this->input("pagu_belanja_{$type}_usulan")
                        : (float) $item->getAttribute("pagu_belanja_{$type}_usulan");

                    return abs($verified - $proposal) > 0.001;
                });

            if (($proposalChanged || $verificationChanged) && blank($this->input('alasan_penyesuaian'))) {
                $validator->errors()->add('alasan_penyesuaian', 'Alasan penyesuaian wajib diisi ketika pagu berubah dari nilai acuan.');
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
