<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StorePenugasanPengampuKinerjaRequest;
use App\Models\OpdKegiatan;
use App\Models\OpdProgram;
use App\Models\OpdSubKegiatan;
use App\Models\Pegawai;
use App\Models\PenugasanPengampuKinerja;
use App\Models\PeriodeTahun;
use App\Models\RiwayatPejabatJabatan;
use App\Models\SasaranOpd;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PenugasanPengampuKinerjaController extends Controller
{
    public function store(StorePenugasanPengampuKinerjaRequest $request, Pegawai $pegawai): RedirectResponse
    {
        $this->assertInScope($request->user(), $pegawai);
        $data = $request->validated();
        $periode = PeriodeTahun::query()->findOrFail($data['periode_tahun_id']);

        if (! $pegawai->opd_id) {
            throw ValidationException::withMessages([
                'sumber_kinerja_id' => 'Pegawai harus ditempatkan pada perangkat daerah sebelum diberi penugasan cascading.',
            ]);
        }

        if ($data['penempatan_pegawai_id'] ?? null) {
            abort_unless(RiwayatPejabatJabatan::query()
                ->whereKey($data['penempatan_pegawai_id'])
                ->where('pegawai_id', $pegawai->id)
                ->exists(), 422);
        }

        $label = $this->resolveSourceLabel($pegawai, $data['sumber_kinerja_type'], (int) $data['sumber_kinerja_id']);

        $existingAssignment = PenugasanPengampuKinerja::withTrashed()
            ->where('pegawai_id', $pegawai->id)
            ->where('periode_tahun_id', $periode->id)
            ->where('sumber_kinerja_type', $data['sumber_kinerja_type'])
            ->where('sumber_kinerja_id', $data['sumber_kinerja_id'])
            ->first();

        if ($existingAssignment && ! $existingAssignment->trashed()) {
            throw ValidationException::withMessages([
                'sumber_kinerja_id' => 'Penugasan cascading ini sudah tercatat untuk pegawai dan periode yang dipilih.',
            ]);
        }

        if ($existingAssignment?->trashed()) {
            $existingAssignment->restore();
            $existingAssignment->update([
                ...$data,
                'opd_id' => $pegawai->opd_id,
                'tahun' => $periode->tahun,
                'sumber_kinerja_label' => $label,
                'status' => 'active',
            ]);

            return back()->with('success', 'Penugasan pengampu kinerja berhasil diaktifkan kembali.');
        }

        $pegawai->penugasanKinerja()->create([
            ...$data,
            'opd_id' => $pegawai->opd_id,
            'tahun' => $periode->tahun,
            'sumber_kinerja_label' => $label,
            'status' => 'active',
        ]);

        return back()->with('success', 'Penugasan pengampu kinerja berhasil ditambahkan. PK Cascading dapat memakai penugasan ini.');
    }

    public function destroy(Request $request, Pegawai $pegawai, PenugasanPengampuKinerja $penugasan): RedirectResponse
    {
        $this->assertInScope($request->user(), $pegawai);
        abort_unless((int) $penugasan->pegawai_id === (int) $pegawai->id, 404);
        $penugasan->delete();

        return back()->with('success', 'Penugasan pengampu kinerja berhasil dihapus.');
    }

    private function resolveSourceLabel(Pegawai $pegawai, string $type, int $id): string
    {
        $opdId = (int) $pegawai->opd_id;
        $item = match ($type) {
            'sasaran' => SasaranOpd::query()
                ->whereKey($id)
                ->whereHas('tujuan.renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->first(['id', 'kode', 'sasaran']),
            'program' => OpdProgram::query()
                ->whereKey($id)
                ->whereHas('renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->first(['id', 'kode', 'nama']),
            'kegiatan' => OpdKegiatan::query()
                ->whereKey($id)
                ->whereHas('program.renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->first(['id', 'kode', 'nama']),
            'sub_kegiatan' => OpdSubKegiatan::query()
                ->whereKey($id)
                ->whereHas('kegiatan.program.renstra', fn (Builder $query) => $query->where('opd_id', $opdId))
                ->first(['id', 'kode', 'nama']),
            default => null,
        };

        if (! $item) {
            throw ValidationException::withMessages(['sumber_kinerja_id' => 'Data cascading tidak ditemukan atau tidak berada pada OPD pegawai.']);
        }

        $name = $type === 'sasaran' ? $item->sasaran : $item->nama;

        return trim(($item->kode ? "{$item->kode} - " : '').$name);
    }

    private function assertInScope(User $user, Pegawai $pegawai): void
    {
        abort_unless($user->hasPermission('pegawai.manage'), 403);
        $isScoped = filled($user->opd_id) && ! $user->hasAnyRole([
            'super_admin',
            'admin_kabupaten_bagian_organisasi',
            'admin_kabupaten_dinkominfo',
        ]);

        if ($isScoped && (int) $pegawai->opd_id !== (int) $user->opd_id) {
            abort(403);
        }
    }
}
