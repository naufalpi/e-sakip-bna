<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreRiwayatPejabatJabatanRequest;
use App\Http\Requests\Master\UpdateRiwayatPejabatJabatanRequest;
use App\Models\JabatanOrganisasi;
use App\Models\Pegawai;
use App\Models\RiwayatPejabatJabatan;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class RiwayatPejabatJabatanController extends Controller
{
    public function store(StoreRiwayatPejabatJabatanRequest $request, JabatanOrganisasi $jabatanOrganisasi): RedirectResponse
    {
        $this->assertInScope($request->user(), $jabatanOrganisasi);
        $data = $request->validated();
        $this->assertUserMatchesOpd($data['user_id'] ?? null, $jabatanOrganisasi);
        $pegawai = $this->resolvePegawai($data, $jabatanOrganisasi);
        $data['pegawai_id'] = $pegawai->id;
        $this->assertPeriodAvailable($jabatanOrganisasi, $data);

        $jabatanOrganisasi->riwayatPejabat()->create($data);

        return back()->with('success', 'Riwayat pejabat berhasil ditambahkan.');
    }

    public function update(
        UpdateRiwayatPejabatJabatanRequest $request,
        JabatanOrganisasi $jabatanOrganisasi,
        RiwayatPejabatJabatan $riwayatPejabat
    ): RedirectResponse {
        $this->assertBelongsToJabatan($jabatanOrganisasi, $riwayatPejabat);
        $this->assertInScope($request->user(), $jabatanOrganisasi);
        $data = $request->validated();
        $this->assertUserMatchesOpd($data['user_id'] ?? null, $jabatanOrganisasi);
        $pegawai = $riwayatPejabat->pegawai ?: $this->resolvePegawai($data, $jabatanOrganisasi);
        $pegawai->update([
            'user_id' => $data['user_id'] ?? null,
            'nama' => $data['nama_pejabat'],
            'nip' => $data['nip'] ?? null,
            'pangkat_golongan' => $data['pangkat_golongan'] ?? null,
        ]);
        $data['pegawai_id'] = $pegawai->id;
        $this->assertPeriodAvailable($jabatanOrganisasi, $data, $riwayatPejabat);

        $riwayatPejabat->update($data);

        return back()->with('success', 'Riwayat pejabat berhasil diperbarui.');
    }

    public function destroy(
        Request $request,
        JabatanOrganisasi $jabatanOrganisasi,
        RiwayatPejabatJabatan $riwayatPejabat
    ): RedirectResponse {
        abort_unless($request->user()->hasPermission('jabatan_organisasi.manage'), 403);
        $this->assertBelongsToJabatan($jabatanOrganisasi, $riwayatPejabat);
        $this->assertInScope($request->user(), $jabatanOrganisasi);

        $riwayatPejabat->delete();

        return back()->with('success', 'Riwayat pejabat berhasil dihapus.');
    }

    private function assertPeriodAvailable(
        JabatanOrganisasi $jabatan,
        array $data,
        ?RiwayatPejabatJabatan $current = null
    ): void {
        $overlaps = $jabatan->riwayatPejabat()
            ->when($jabatan->allowsMultipleHolders(), fn (Builder $query) => $query->where('pegawai_id', $data['pegawai_id']))
            ->when($current, fn (Builder $query) => $query->whereKeyNot($current->id))
            ->when($data['tanggal_selesai'] ?? null, fn (Builder $query, string $end) => $query->whereDate('tanggal_mulai', '<=', $end))
            ->where(function (Builder $query) use ($data) {
                $query->whereNull('tanggal_selesai')
                    ->orWhereDate('tanggal_selesai', '>=', $data['tanggal_mulai']);
            })
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages([
                'tanggal_mulai' => 'TMT dan masa tugas bertumpang tindih dengan riwayat pejabat yang sudah ada. Isi tanggal selesai pejabat sebelumnya terlebih dahulu.',
            ]);
        }
    }

    private function assertUserMatchesOpd(mixed $userId, JabatanOrganisasi $jabatan): void
    {
        if (! $userId || ! $jabatan->opd_id) {
            return;
        }

        $user = User::query()->findOrFail($userId);
        if ((int) $user->opd_id !== (int) $jabatan->opd_id) {
            throw ValidationException::withMessages(['user_id' => 'Akun pengguna harus berada pada perangkat daerah yang sama.']);
        }
    }

    private function resolvePegawai(array $data, JabatanOrganisasi $jabatan): Pegawai
    {
        $pegawai = null;

        if ($data['nip'] ?? null) {
            $pegawai = Pegawai::query()->where('nip', $data['nip'])->first();
        }

        if (! $pegawai && ($data['user_id'] ?? null)) {
            $pegawai = Pegawai::query()->where('user_id', $data['user_id'])->first();
        }

        return $pegawai ?: Pegawai::create([
            'opd_id' => $jabatan->opd_id,
            'opd_unit_id' => $jabatan->opd_unit_id,
            'user_id' => $data['user_id'] ?? null,
            'nama' => $data['nama_pejabat'],
            'nip' => $data['nip'] ?? null,
            'pangkat_golongan' => $data['pangkat_golongan'] ?? null,
            'jenis_pegawai' => 'pns',
            'status' => 'active',
        ]);
    }

    private function assertBelongsToJabatan(JabatanOrganisasi $jabatan, RiwayatPejabatJabatan $riwayat): void
    {
        abort_unless((int) $riwayat->jabatan_organisasi_id === (int) $jabatan->id, 404);
    }

    private function assertInScope(User $user, JabatanOrganisasi $jabatan): void
    {
        if ($user->hasPermission('jabatan_organisasi.manage')) {
            return;
        }

        abort_unless(
            $user->hasPermission('pejabat_jabatan.manage')
                && filled($user->opd_id)
                && (int) $user->opd_id === (int) $jabatan->opd_id,
            403,
        );
    }
}
