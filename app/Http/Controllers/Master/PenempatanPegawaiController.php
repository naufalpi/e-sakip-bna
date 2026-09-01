<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StorePenempatanPegawaiRequest;
use App\Http\Requests\Master\UpdatePenempatanPegawaiRequest;
use App\Models\JabatanOrganisasi;
use App\Models\Pegawai;
use App\Models\RiwayatPejabatJabatan;
use App\Models\User;
use App\Services\Master\PegawaiOrganizationSyncService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PenempatanPegawaiController extends Controller
{
    public function store(
        StorePenempatanPegawaiRequest $request,
        Pegawai $pegawai,
        PegawaiOrganizationSyncService $syncService,
    ): RedirectResponse {
        $this->assertInScope($request->user(), $pegawai);
        $data = $request->validated();
        $jabatan = $this->resolveJabatan($request->user(), $pegawai, (int) $data['jabatan_organisasi_id']);
        $this->assertPeriodAvailable($pegawai, $jabatan, $data);

        $placement = $pegawai->penempatan()->create($this->placementPayload($pegawai, $data));
        $syncService->syncEmployee($pegawai);

        return back()->with('success', "Jabatan {$placement->nama_pejabat} berhasil dicatat.");
    }

    public function update(
        UpdatePenempatanPegawaiRequest $request,
        Pegawai $pegawai,
        RiwayatPejabatJabatan $penempatan,
        PegawaiOrganizationSyncService $syncService,
    ): RedirectResponse {
        $this->assertInScope($request->user(), $pegawai);
        $this->assertBelongsToPegawai($pegawai, $penempatan);
        $data = $request->validated();
        $jabatan = $this->resolveJabatan($request->user(), $pegawai, (int) $data['jabatan_organisasi_id']);
        $this->assertPeriodAvailable($pegawai, $jabatan, $data, $penempatan);

        $penempatan->update($this->placementPayload($pegawai, $data));
        $syncService->syncEmployee($pegawai);

        return back()->with('success', 'Jabatan pegawai berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        Pegawai $pegawai,
        RiwayatPejabatJabatan $penempatan,
        PegawaiOrganizationSyncService $syncService,
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('pegawai.manage') && ! $this->shouldLimitToUserOpd($request->user()), 403);
        $this->assertBelongsToPegawai($pegawai, $penempatan);

        if ($penempatan->penugasanKinerja()->exists() || $penempatan->perjanjianKinerja()->exists()) {
            return back()->with('error', 'Riwayat jabatan tidak dapat dihapus karena sudah dipakai pada penugasan kinerja atau PK. Isi tanggal selesai untuk menutup jabatan.');
        }

        $penempatan->delete();
        $syncService->syncEmployee($pegawai);

        return back()->with('success', 'Riwayat jabatan pegawai berhasil dihapus.');
    }

    private function resolveJabatan(User $user, Pegawai $pegawai, int $jabatanId): JabatanOrganisasi
    {
        $jabatan = JabatanOrganisasi::query()
            ->where('status', 'active')
            ->findOrFail($jabatanId);

        if (! in_array($jabatan->verification_status, ['verified', 'pending'], true)) {
            throw ValidationException::withMessages([
                'jabatan_organisasi_id' => 'Jabatan masih memerlukan perbaikan dan belum dapat dipakai untuk penempatan pegawai.',
            ]);
        }

        if ($this->shouldLimitToUserOpd($user)
            && ((int) $jabatan->opd_id !== (int) $user->opd_id || (int) $pegawai->opd_id !== (int) $user->opd_id)) {
            abort(403);
        }

        if ((int) $jabatan->opd_id !== (int) $pegawai->opd_id && $this->shouldLimitToUserOpd($user)) {
            abort(403);
        }

        return $jabatan;
    }

    private function assertPeriodAvailable(
        Pegawai $pegawai,
        JabatanOrganisasi $jabatan,
        array $data,
        ?RiwayatPejabatJabatan $current = null
    ): void {
        $overlap = RiwayatPejabatJabatan::query()
            ->where('jabatan_organisasi_id', $jabatan->id)
            ->when($jabatan->allowsMultipleHolders(), fn (Builder $query) => $query->where('pegawai_id', $pegawai->id))
            ->when($current, fn (Builder $query) => $query->whereKeyNot($current->id))
            ->when($data['tanggal_selesai'] ?? null, fn (Builder $query, string $end) => $query->whereDate('tanggal_mulai', '<=', $end))
            ->where(fn (Builder $query) => $query->whereNull('tanggal_selesai')->orWhereDate('tanggal_selesai', '>=', $data['tanggal_mulai']))
            ->exists();

        if ($overlap) {
            $message = $jabatan->allowsMultipleHolders()
                ? 'Pegawai ini sudah memiliki riwayat yang bertumpang tindih pada jabatan tersebut.'
                : 'Jabatan struktural ini sudah ditempati pada rentang tanggal tersebut. Akhiri jabatan pejabat sebelumnya terlebih dahulu.';

            throw ValidationException::withMessages(['tanggal_mulai' => $message]);
        }
    }

    private function placementPayload(Pegawai $pegawai, array $data): array
    {
        return [
            ...$data,
            'pegawai_id' => $pegawai->id,
            'user_id' => $pegawai->user_id,
            'nama_pejabat' => $pegawai->nama,
            'nip' => $pegawai->nip,
            'pangkat_golongan' => $pegawai->pangkat_golongan,
        ];
    }

    private function assertBelongsToPegawai(Pegawai $pegawai, RiwayatPejabatJabatan $penempatan): void
    {
        abort_unless((int) $penempatan->pegawai_id === (int) $pegawai->id, 404);
    }

    private function assertInScope(User $user, Pegawai $pegawai): void
    {
        abort_unless($user->hasPermission('pegawai.manage'), 403);
        if ($this->shouldLimitToUserOpd($user) && (int) $pegawai->opd_id !== (int) $user->opd_id) {
            abort(403);
        }
    }

    private function shouldLimitToUserOpd(User $user): bool
    {
        return filled($user->opd_id) && ! $user->hasAnyRole([
            'super_admin',
            'admin_kabupaten_bagian_organisasi',
            'admin_kabupaten_dinkominfo',
        ]);
    }
}
