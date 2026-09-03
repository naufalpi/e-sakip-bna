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
            && ($this->user()?->can('update', $rka) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'judul' => str($this->input('judul', ''))->trim()->upper()->toString(),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'judul' => ['required', 'string', 'max:255'],
            'catatan' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
