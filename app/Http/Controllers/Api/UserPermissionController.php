<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserPermission;
use App\Support\PermissionResolver;
use App\Support\RoleHierarchy;
use Illuminate\Http\Request;

class UserPermissionController extends Controller
{
    /**
     * Who may manage per-user permission overrides is deliberately narrower than
     * PermissionResolver::PRIVILEGED_ROLES (which also includes Admin/role 2 for
     * general app access) — only Super Admin has unrestricted reach here, and
     * Branch Admin has scoped reach. Admin cannot manage overrides at all.
     */
    const SUPER_ADMIN_ROLE  = 1;
    const BRANCH_ADMIN_ROLE = 3;

    /**
     * Super Admin may manage anyone. A Branch Admin may manage overrides for
     * staff in their own branch, within the same role tier they're already
     * allowed to assign (see RoleHierarchy) — mirroring how staff creation is
     * scoped. Everyone else (including Admin) is rejected outright.
     */
    private function authorizeTargetAccess(User $caller, User $target): void
    {
        $callerRole = (int) $caller->user_role;

        if ($callerRole === self::SUPER_ADMIN_ROLE) {
            return;
        }

        if ($callerRole === self::BRANCH_ADMIN_ROLE) {
            $sameBranch = $caller->branch_id !== null && $caller->branch_id === $target->branch_id;
            $withinTier = in_array((int) $target->user_role, RoleHierarchy::assignableRoleIds($callerRole), true);

            abort_unless($sameBranch && $withinTier, 403, 'You are not authorized to manage this user\'s permissions.');
            return;
        }

        abort(403, 'You are not authorized to manage user permissions.');
    }

    public function show(Request $request, $id)
    {
        $caller = $request->user();
        $target = User::findOrFail($id);
        $this->authorizeTargetAccess($caller, $target);

        $roleDefaults = [];
        foreach (RolePermission::where('role_id', (int) $target->user_role)->get() as $row) {
            $roleDefaults[$row->module_slug] = [
                'view'   => (bool) $row->can_view,
                'create' => (bool) $row->can_create,
                'edit'   => (bool) $row->can_edit,
                'delete' => (bool) $row->can_delete,
            ];
        }

        $overrides = [];
        foreach (UserPermission::where('user_id', $target->id)->get() as $row) {
            $overrides[$row->module_slug] = [
                'view'   => $row->can_view,
                'create' => $row->can_create,
                'edit'   => $row->can_edit,
                'delete' => $row->can_delete,
            ];
        }

        $effective = PermissionResolver::effectivePermissions($target);

        $permissions = [];
        foreach (PermissionResolver::MODULES as $module) {
            $slug = $module['slug'];
            $permissions[] = [
                'module_slug'  => $slug,
                'module_name'  => $module['name'],
                'group'        => $module['group'],
                'role_default' => $roleDefaults[$slug] ?? ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
                'override'     => $overrides[$slug] ?? null,
                'effective'    => $effective[$slug] ?? ['view' => false, 'create' => false, 'edit' => false, 'delete' => false],
            ];
        }

        return response()->json([
            'data' => [
                'user' => [
                    'id'   => $target->id,
                    'name' => $target->name,
                    'role' => optional($target->role)->name,
                ],
                'is_privileged' => PermissionResolver::isPrivileged($target),
                'permissions'   => $permissions,
            ],
        ]);
    }

    public function update(Request $request, $id)
    {
        $caller = $request->user();
        $target = User::findOrFail($id);
        $this->authorizeTargetAccess($caller, $target);

        abort_if(
            PermissionResolver::isPrivileged($target),
            422,
            'Super Admin / Admin already have full access everywhere — per-user overrides would have no effect.'
        );

        $data = $request->validate([
            'permissions'               => 'required|array',
            'permissions.*.module_slug' => 'required|string',
            'permissions.*.can_view'    => 'nullable|boolean',
            'permissions.*.can_create'  => 'nullable|boolean',
            'permissions.*.can_edit'    => 'nullable|boolean',
            'permissions.*.can_delete'  => 'nullable|boolean',
        ]);

        // Branch Admin (the only non-Super-Admin who can reach this point) can never
        // grant a permission they don't hold themselves — they can only ever narrow
        // (revoke) or defer to the role default. Prevents escalating a teacher above themselves.
        if ((int) $caller->user_role !== self::SUPER_ADMIN_ROLE) {
            $callerEffective = PermissionResolver::effectivePermissions($caller);
            $actionsByField  = ['can_view' => 'view', 'can_create' => 'create', 'can_edit' => 'edit', 'can_delete' => 'delete'];

            foreach ($data['permissions'] as $perm) {
                foreach ($actionsByField as $field => $action) {
                    if (($perm[$field] ?? null) !== true) {
                        continue;
                    }

                    $callerHasIt = $callerEffective[$perm['module_slug']][$action] ?? false;
                    abort_unless($callerHasIt, 403, "You cannot grant '{$action}' on '{$perm['module_slug']}' because you do not have that permission yourself.");
                }
            }
        }

        foreach ($data['permissions'] as $perm) {
            $flags = [
                'can_view'   => $perm['can_view']   ?? null,
                'can_create' => $perm['can_create'] ?? null,
                'can_edit'   => $perm['can_edit']   ?? null,
                'can_delete' => $perm['can_delete'] ?? null,
            ];

            $allNull = collect($flags)->every(fn ($v) => is_null($v));

            if ($allNull) {
                UserPermission::where('user_id', $target->id)
                    ->where('module_slug', $perm['module_slug'])
                    ->delete();
                continue;
            }

            UserPermission::updateOrCreate(
                ['user_id' => $target->id, 'module_slug' => $perm['module_slug']],
                $flags
            );
        }

        return response()->json(['message' => 'Permissions saved.']);
    }
}
