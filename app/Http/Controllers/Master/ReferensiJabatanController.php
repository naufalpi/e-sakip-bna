<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreReferensiJabatanRequest;
use App\Http\Requests\Master\UpdateReferensiJabatanRequest;
use App\Models\ReferensiJabatan;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class ReferensiJabatanController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('referensi_jabatan.view'), 403);

        $filters = $request->only(['search', 'jenis_jabatan', 'verification_status', 'status']);
        $query = ReferensiJabatan::query();

        $items = (clone $query)
            ->withCount('placements')
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $query->where(function (Builder $query) use ($search) {
                    $query->where('nama', 'ilike', "%{$search}%")
                        ->orWhere('kode', 'ilike', "%{$search}%")
                        ->orWhere('jenjang', 'ilike', "%{$search}%")
                        ->orWhere('dasar_hukum', 'ilike', "%{$search}%");
                });
            })
            ->when($filters['jenis_jabatan'] ?? null, fn (Builder $query, string $type) => $query->where('jenis_jabatan', $type))
            ->when($filters['verification_status'] ?? null, fn (Builder $query, string $status) => $query->where('verification_status', $status))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->orderBy('jenis_jabatan')
            ->orderBy('nama')
            ->orderBy('jenjang')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (ReferensiJabatan $reference) => $this->serialize($reference));

        return Inertia::render('Master/ReferensiJabatan/Index', [
            'items' => $items,
            'filters' => $filters,
            'jenisOptions' => ReferensiJabatan::jenisOptions(),
            'stats' => [
                'total' => (clone $query)->count(),
                'verified' => (clone $query)->where('verification_status', 'verified')->count(),
                'draft' => (clone $query)->where('verification_status', 'draft')->count(),
                'active' => (clone $query)->where('status', 'active')->count(),
            ],
            'can' => [
                'manage' => $request->user()->hasPermission('referensi_jabatan.manage'),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        abort_unless($request->user()->hasPermission('referensi_jabatan.manage'), 403);

        return Inertia::render('Master/ReferensiJabatan/Form', $this->formProps(null));
    }

    public function store(StoreReferensiJabatanRequest $request): RedirectResponse
    {
        ReferensiJabatan::create($request->validated());

        return redirect()->route('master.referensi-jabatan.index')->with('success', 'Referensi jabatan berhasil ditambahkan.');
    }

    public function edit(Request $request, ReferensiJabatan $referensiJabatan): Response
    {
        abort_unless($request->user()->hasPermission('referensi_jabatan.manage'), 403);

        return Inertia::render('Master/ReferensiJabatan/Form', $this->formProps($referensiJabatan));
    }

    public function update(UpdateReferensiJabatanRequest $request, ReferensiJabatan $referensiJabatan): RedirectResponse
    {
        $data = $request->validated();

        if ($data['jenis_jabatan'] !== $referensiJabatan->jenis_jabatan && $referensiJabatan->placements()->exists()) {
            throw ValidationException::withMessages([
                'jenis_jabatan' => 'Jenis tidak dapat diubah karena referensi sudah digunakan. Buat referensi baru agar hierarki jabatan tetap akurat.',
            ]);
        }

        $referensiJabatan->update($data);

        if ($referensiJabatan->wasChanged(['nama', 'jenis_jabatan'])) {
            $referensiJabatan->placements()->update([
                'nama' => $referensiJabatan->nama,
                'level_jabatan' => $referensiJabatan->jenis_jabatan,
            ]);
        }

        return redirect()->route('master.referensi-jabatan.index')->with('success', 'Referensi jabatan berhasil diperbarui.');
    }

    public function destroy(Request $request, ReferensiJabatan $referensiJabatan): RedirectResponse
    {
        abort_unless($request->user()->hasPermission('referensi_jabatan.manage'), 403);

        if ($referensiJabatan->placements()->exists()) {
            return back()->with('error', 'Referensi sudah digunakan pada penempatan jabatan. Nonaktifkan referensi agar riwayat tetap utuh.');
        }

        $referensiJabatan->delete();

        return back()->with('success', 'Referensi jabatan berhasil dihapus.');
    }

    private function formProps(?ReferensiJabatan $reference): array
    {
        return [
            'mode' => $reference ? 'edit' : 'create',
            'item' => $reference ? $this->serialize($reference) : null,
            'jenisOptions' => ReferensiJabatan::jenisOptions(),
        ];
    }

    private function serialize(ReferensiJabatan $reference): array
    {
        return [
            'id' => $reference->id,
            'kode' => $reference->kode,
            'nama' => $reference->nama,
            'jenis_jabatan' => $reference->jenis_jabatan,
            'jenis_label' => ReferensiJabatan::jenisLabels()[$reference->jenis_jabatan] ?? $reference->jenis_jabatan,
            'jenjang' => $reference->jenjang,
            'kelas_jabatan' => $reference->kelas_jabatan,
            'kualifikasi' => $reference->kualifikasi,
            'dasar_hukum' => $reference->dasar_hukum,
            'berlaku_mulai' => $reference->berlaku_mulai?->format('Y-m-d'),
            'berlaku_sampai' => $reference->berlaku_sampai?->format('Y-m-d'),
            'verification_status' => $reference->verification_status,
            'verification_label' => ReferensiJabatan::verificationLabels()[$reference->verification_status] ?? $reference->verification_status,
            'status' => $reference->status,
            'placements_count' => $reference->placements_count ?? null,
        ];
    }
}
