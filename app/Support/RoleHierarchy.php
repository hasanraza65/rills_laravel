<?php

namespace App\Support;

class RoleHierarchy
{
    /** Role id => role ids that caller may assign to a staff member. */
    private const ASSIGNABLE = [
        1 => [1, 2, 3, 4, 6, 7], // super_admin
        2 => [2, 3, 4, 6, 7],    // admin
        3 => [3, 4, 6, 7],       // branch_admin
        4 => [4, 6, 7],          // teacher
        6 => [4, 6, 7],          // gate_keeper
        7 => [4, 6, 7],          // librarian
    ];

    public static function assignableRoleIds(int $callerRoleId): array
    {
        return self::ASSIGNABLE[$callerRoleId] ?? [];
    }
}
