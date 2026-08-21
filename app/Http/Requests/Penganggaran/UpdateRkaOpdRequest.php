<?php

namespace App\Http\Requests\Penganggaran;

use App\Models\RkaOpd;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRkaOpdRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rka = $this->route('rka_opd');

        return $rka instanceof RkaOpd
            && (($this->user()?->can('update', $rka) ?? false) || ($this->user()?->can('verifyBudget', $rka) ?? false));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'judul' => str($this->input('judul', ''))->trim()->upper()->toString(),
            'nomor_dokumen' => $this->uppercaseNullable('nomor_dokumen'),
            'nomor_kua' => $this->uppercaseNullable('nomor_kua'),
            'nomor_ppas' => $this->uppercaseNullable('nomor_ppas'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'max:255'],
            'nomor_dokumen' => ['nullable', 'string', 'max:255'],
            'tanggal_dokumen' => ['nullable', 'date'],
            'nomor_kua' => ['nullable', 'string', 'max:255'],
            'tanggal_kua' => ['nullable', 'date'],
            'nomor_ppas' => ['nullable', 'string', 'max:255'],
            'tanggal_ppas' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:5000'],
            'catatan_verifikasi' => ['nullable', 'string', 'max:5000'],
        ];
    }

    private function uppercaseNullable(string $key): ?string
    {
        return filled($this->input($key))
            ? str($this->input($key))->trim()->upper()->toString()
            : null;
    }
}
