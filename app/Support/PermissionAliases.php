<?php

namespace App\Support;

final class PermissionAliases
{
    /**
     * Nama di sisi kiri adalah permission yang dipakai saat ini. Nama di sisi
     * kanan dipertahankan sebagai alias agar instalasi lama tetap kompatibel.
     *
     * @var array<string, array<int, string>>
     */
    private const ALIASES = [
        'users.manage' => ['manage_users'],
        'roles.manage' => ['manage_roles'],
        'opd.manage' => ['manage_opd'],
        'rpjmd.view' => ['view_rpjmd'],
        'rpjmd.manage' => ['manage_rpjmd'],
        'renstra.view' => ['view_renstra_opd'],
        'renstra.manage' => ['manage_renstra_opd'],
        'evaluasi.manage' => ['manage_evaluasi'],
        'dokumen.manage' => ['manage_dokumen'],
    ];

    public static function canonical(string $permission): string
    {
        foreach (self::ALIASES as $canonical => $aliases) {
            if ($permission === $canonical || in_array($permission, $aliases, true)) {
                return $canonical;
            }
        }

        return $permission;
    }

    /**
     * @return array<int, string>
     */
    public static function equivalents(string $permission): array
    {
        $canonical = self::canonical($permission);

        return array_values(array_unique([
            $canonical,
            ...(self::ALIASES[$canonical] ?? []),
        ]));
    }

    /**
     * @param  array<int, string>  $permissions
     * @return array<int, string>
     */
    public static function expand(array $permissions): array
    {
        $expanded = [];

        foreach ($permissions as $permission) {
            array_push($expanded, ...self::equivalents($permission));
        }

        return array_values(array_unique($expanded));
    }

    /**
     * @return array<int, string>
     */
    public static function legacyNames(): array
    {
        return array_values(array_unique(array_merge(...array_values(self::ALIASES))));
    }
}
