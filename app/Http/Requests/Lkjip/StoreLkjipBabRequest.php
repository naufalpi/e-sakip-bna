<?php

namespace App\Http\Requests\Lkjip;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLkjipBabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('lkjip'));
    }

    public function rules(): array
    {
        return [
            'kode' => ['required', 'string', 'max:50'],
            'judul' => ['required', 'string', 'max:255'],
            'jenis' => ['required', Rule::in(['pendahuluan', 'perencanaan', 'akuntabilitas', 'penutup', 'lampiran', 'bab'])],
            'konten' => ['nullable', 'string'],
            'urutan' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }
}
