<?php

namespace Database\Seeders;

use App\Models\Opd;
use App\Models\OpdUnit;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class OperationalUserSeeder extends Seeder
{
    public function run(): void
    {
        $passwordHash = Hash::make(env('SEEDED_USER_PASSWORD', 'password'));

        $this->seedKabupatenUsers($passwordHash);
        $this->seedOpdUsers($passwordHash);
        $this->seedOpdUnitUsers($passwordHash);
    }

    private function seedKabupatenUsers(string $passwordHash): void
    {
        $accounts = [
            'admin_kabupaten_bagian_organisasi' => [
                'name' => 'Admin Kabupaten Bagian Organisasi',
                'email' => 'admin.bagian-organisasi@example.test',
                'jabatan' => 'Admin Monitoring dan Validasi SAKIP Kabupaten',
            ],
            'admin_kabupaten_bapperida' => [
                'name' => 'Admin Kabupaten Bapperida',
                'email' => 'admin.bapperida@example.test',
                'jabatan' => 'Admin Perencanaan Kabupaten',
            ],
            'admin_kabupaten_inspektorat' => [
                'name' => 'Admin Kabupaten Inspektorat',
                'email' => 'admin.inspektorat@example.test',
                'jabatan' => 'Admin Evaluasi SAKIP Inspektorat',
            ],
            'admin_kabupaten_dinkominfo' => [
                'name' => 'Admin Kabupaten Dinkominfo',
                'email' => 'admin.dinkominfo@example.test',
                'jabatan' => 'Admin Sistem dan Data Umum',
            ],
            'pimpinan' => [
                'name' => 'Pimpinan Daerah',
                'email' => 'pimpinan@example.test',
                'jabatan' => 'Pimpinan',
            ],
        ];

        foreach ($accounts as $roleName => $account) {
            $user = User::query()->updateOrCreate(
                ['username' => $roleName],
                [
                    'opd_id' => null,
                    'opd_unit_id' => null,
                    'name' => $account['name'],
                    'email' => $account['email'],
                    'phone' => null,
                    'jabatan' => $account['jabatan'],
                    'password' => $passwordHash,
                    'status' => 'active',
                ],
            );

            $this->syncRole($user, $roleName);
        }
    }

    private function seedOpdUsers(string $passwordHash): void
    {
        $role = Role::query()->where('name', 'admin_opd')->firstOrFail();

        Opd::query()
            ->where('status', 'active')
            ->orderBy('kode')
            ->get(['id', 'kode', 'nama', 'singkatan'])
            ->each(function (Opd $opd) use ($passwordHash, $role) {
                $key = $this->accountKey($opd->kode);

                $user = User::query()->updateOrCreate(
                    ['username' => "opd_{$key}"],
                    [
                        'opd_id' => $opd->id,
                        'opd_unit_id' => null,
                        'name' => 'Admin OPD '.($opd->singkatan ?: $opd->nama),
                        'email' => "opd-{$key}@example.test",
                        'phone' => null,
                        'jabatan' => 'Admin OPD',
                        'password' => $passwordHash,
                        'status' => 'active',
                    ],
                );

                $user->roles()->sync([$role->id]);
            });
    }

    private function seedOpdUnitUsers(string $passwordHash): void
    {
        $role = Role::query()->where('name', 'admin_opd')->firstOrFail();

        OpdUnit::query()
            ->with('opd:id,nama,singkatan')
            ->where('status', 'active')
            ->orderBy('opd_id')
            ->orderBy('kode')
            ->get(['id', 'opd_id', 'kode', 'nama', 'jenis_unit'])
            ->each(function (OpdUnit $unit) use ($passwordHash, $role) {
                $key = $this->accountKey($unit->kode);

                $user = User::query()->updateOrCreate(
                    ['username' => "unit_{$key}"],
                    [
                        'opd_id' => $unit->opd_id,
                        'opd_unit_id' => $unit->id,
                        'name' => 'Admin Unit '.$unit->nama,
                        'email' => "unit-{$key}@example.test",
                        'phone' => null,
                        'jabatan' => $this->unitJabatan($unit),
                        'password' => $passwordHash,
                        'status' => 'active',
                    ],
                );

                $user->roles()->sync([$role->id]);
            });
    }

    private function syncRole(User $user, string $roleName): void
    {
        $roleId = Role::query()->where('name', $roleName)->value('id');

        if ($roleId === null) {
            throw new \RuntimeException("Role {$roleName} belum tersedia.");
        }

        $user->roles()->sync([$roleId]);
    }

    private function accountKey(string $value): string
    {
        return Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->limit(80, '')
            ->toString();
    }

    private function unitJabatan(OpdUnit $unit): string
    {
        return match ($unit->jenis_unit) {
            'puskesmas' => 'Admin Puskesmas',
            'sekolah' => 'Admin Sekolah',
            'kelurahan' => 'Admin Kelurahan',
            'uptd', 'labkes', 'rsud' => 'Admin UPTD',
            default => 'Admin Unit OPD',
        };
    }
}
