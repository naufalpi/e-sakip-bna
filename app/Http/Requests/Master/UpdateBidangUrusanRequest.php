<?php

namespace App\Http\Requests\Master;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBidangUrusanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('urusan.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'urusan_pemerintahan_id' => ['required', 'integer', 'exists:urusan_pemerintahan,id'],
            'kode' => [
                'required',
                'string',
                'max:80',
                Rule::unique('bidang_urusan', 'kode')
                    ->where(fn ($query) => $query->where('urusan_pemerintahan_id', $this->input('urusan_pemerintahan_id')))
                    ->whereNull('deleted_at')
                    ->ignore($this->route('bidangUrusan')),
            ],
            'nama' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }
}
