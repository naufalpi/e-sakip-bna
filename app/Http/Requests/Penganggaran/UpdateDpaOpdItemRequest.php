<?php

namespace App\Http\Requests\Penganggaran;

use App\Models\DpaOpd;
use App\Models\DpaOpdItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateDpaOpdItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        $dpa = $this->route('dpa_opd');

        return $dpa instanceof DpaOpd
            && (($this->user()?->can('update', $dpa) ?? false) || ($this->user()?->can('verifyBudget', $dpa) ?? false));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['pagu_dpa' => $this->normalizeCurrency($this->input('pagu_dpa'))]);
        $plans = collect($this->input('cash_plan', []))->map(fn (array $row) => [
            'bulan' => $row['bulan'] ?? null,
            'jumlah' => $this->normalizeCurrency($row['jumlah'] ?? null),
        ])->all();
        $this->merge(['cash_plan' => $plans]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pagu_dpa' => ['required', 'numeric', 'min:0'],
            'alasan_penyesuaian' => ['nullable', 'string', 'max:5000'],
            'catatan' => ['nullable', 'string', 'max:5000'],
            'cash_plan' => ['required', 'array', 'size:12'],
            'cash_plan.*.bulan' => ['required', 'integer', 'between:1,12', 'distinct'],
            'cash_plan.*.jumlah' => ['required', 'numeric', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $dpa = $this->route('dpa_opd');
            $item = $this->route('item');
            if (! $dpa instanceof DpaOpd || ! $item instanceof DpaOpdItem) {
                return;
            }

            $pagu = $this->user()?->can('verifyBudget', $dpa)
                ? (float) $this->input('pagu_dpa')
                : (float) $item->pagu_dpa;
            $planTotal = collect($this->input('cash_plan'))->sum(fn (array $row) => (float) $row['jumlah']);

            if (abs($planTotal - $pagu) > 0.01) {
                $validator->errors()->add('cash_plan', 'Jumlah rencana penarikan 12 bulan harus sama dengan pagu DPA.');
            }

            if ($this->user()?->can('verifyBudget', $dpa)
                && abs($pagu - (float) $item->pagu_rka) > 0.01
                && blank($this->input('alasan_penyesuaian'))) {
                $validator->errors()->add('alasan_penyesuaian', 'Alasan penyesuaian wajib diisi ketika pagu DPA berbeda dari RKA yang disetujui.');
            }

            $outsidePeriod = collect($this->input('cash_plan'))->contains(fn (array $row) => ((int) $row['bulan'] < $item->bulan_mulai || (int) $row['bulan'] > $item->bulan_selesai)
                && (float) $row['jumlah'] > 0);
            if ($outsidePeriod) {
                $validator->errors()->add('cash_plan', 'Penarikan dana harus berada dalam waktu pelaksanaan sub kegiatan.');
            }
        });
    }

    private function normalizeCurrency(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return is_numeric($value) ? $value : str_replace(['.', ',', ' '], '', (string) $value);
    }
}
