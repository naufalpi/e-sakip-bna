<?php

namespace App\Http\Requests\Master;

use App\Models\KegiatanPemerintahan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class UpdateProgramPemerintahanReferenceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $periodeTahunId = $this->resolvePeriodeTahunId((string) $this->route('type'));

        if ($periodeTahunId) {
            $this->merge(['periode_tahun_id' => $periodeTahunId]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->hasPermission('urusan.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $type = (string) $this->route('type');
        $uniqueRule = $this->uniqueKodeRule($type);

        return [
            'periode_tahun_id' => [Rule::requiredIf($type !== 'program'), 'nullable', 'integer', 'exists:periode_tahun,id'],
            'tahun_awal' => [Rule::requiredIf($type === 'program'), 'nullable', 'integer', 'min:2000', 'max:2100'],
            'tahun_akhir' => [Rule::requiredIf($type === 'program'), 'nullable', 'integer', 'min:2000', 'max:2100', 'gte:tahun_awal'],
            'bidang_urusan_id' => [
                Rule::requiredIf($type === 'program'),
                'nullable',
                'integer',
                'exists:bidang_urusan,id',
            ],
            'program_pemerintahan_id' => [
                Rule::requiredIf($type === 'kegiatan'),
                'nullable',
                'integer',
                'exists:program_pemerintahan,id',
            ],
            'kegiatan_pemerintahan_id' => [
                Rule::requiredIf($type === 'sub_kegiatan'),
                'nullable',
                'integer',
                'exists:kegiatan_pemerintahan,id',
            ],
            'kode' => [
                'required',
                'string',
                'max:80',
                $uniqueRule,
            ],
            'nama' => ['required', 'string', 'max:255'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ];
    }

    /**
     * @return array{0: string, 1: string, 2: mixed}
     */
    private function uniqueKodeRule(string $type): Unique
    {
        return match ($type) {
            'kegiatan' => Rule::unique('kegiatan_pemerintahan', 'kode')
                ->where(fn ($query) => $query
                    ->where('periode_tahun_id', $this->input('periode_tahun_id'))
                    ->where('program_pemerintahan_id', $this->input('program_pemerintahan_id')))
                ->whereNull('deleted_at')
                ->ignore($this->route('id')),
            'sub_kegiatan' => Rule::unique('sub_kegiatan_pemerintahan', 'kode')
                ->where(fn ($query) => $query
                    ->where('periode_tahun_id', $this->input('periode_tahun_id'))
                    ->where('kegiatan_pemerintahan_id', $this->input('kegiatan_pemerintahan_id')))
                ->whereNull('deleted_at')
                ->ignore($this->route('id')),
            default => Rule::unique('program_pemerintahan', 'kode')
                ->where(fn ($query) => $query
                    ->where('tahun_awal', $this->input('tahun_awal'))
                    ->where('tahun_akhir', $this->input('tahun_akhir'))
                    ->where('bidang_urusan_id', $this->input('bidang_urusan_id')))
                ->whereNull('deleted_at')
                ->ignore($this->route('id')),
        };
    }

    private function resolvePeriodeTahunId(string $type): ?int
    {
        return match ($type) {
            'sub_kegiatan' => KegiatanPemerintahan::query()
                ->whereKey($this->input('kegiatan_pemerintahan_id'))
                ->value('periode_tahun_id'),
            default => null,
        };
    }
}
