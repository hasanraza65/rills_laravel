<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use App\Support\RoleHierarchy;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /** Only these tiers may manage Roles & Permissions at all, matching the seeded module access. */
    const MANAGER_ROLES = [1, 2];

    const MODULES = [
        ['slug' => 'dashboard',          'name' => 'Dashboard',           'group' => 'General'],
        ['slug' => 'students',           'name' => 'Students',            'group' => 'Academic'],
        ['slug' => 'families',           'name' => 'Parents & Families',  'group' => 'Academic'],
        ['slug' => 'diaries',            'name' => 'Diaries',             'group' => 'Academic'],
        ['slug' => 'syllabus',           'name' => 'Syllabus',            'group' => 'Academic'],
        ['slug' => 'lesson_plans',       'name' => 'Lesson Plans',        'group' => 'Academic'],
        ['slug' => 'question_bank',      'name' => 'Question Bank',       'group' => 'Academic'],
        ['slug' => 'student_attendance', 'name' => 'Student Attendance',  'group' => 'Attendance'],
        ['slug' => 'staff_attendance',   'name' => 'Staff Attendance',    'group' => 'Attendance'],
        ['slug' => 'fees',               'name' => 'Fee & Finance',       'group' => 'Finance'],
        ['slug' => 'staff',              'name' => 'Staff Management',    'group' => 'Administration'],
        ['slug' => 'branches',           'name' => 'Branches',            'group' => 'Administration'],
        ['slug' => 'classes_sections',   'name' => 'Classes & Sections',  'group' => 'Administration'],
        ['slug' => 'visitors',           'name' => 'Visitors / Gate',     'group' => 'Administration'],
        ['slug' => 'library',            'name' => 'Library',             'group' => 'Administration'],
        ['slug' => 'roles',              'name' => 'Roles & Permissions', 'group' => 'Administration'],
        ['slug' => 'reports',            'name' => 'Reports',             'group' => 'Reports'],
    ];

    /** Reject anyone outside Super Admin / Admin before they can touch role data. */
    private function authorizeManager(User $caller): void
    {
        abort_unless(in_array($caller->user_role, self::MANAGER_ROLES, true), 403, 'You are not authorized to manage roles & permissions.');
    }

    /** A system role (e.g. Super Admin) can only be viewed/edited by callers whose tier covers it. */
    private function authorizeRoleAccess(User $caller, Role $role): void
    {
        if ($role->is_system && !in_array($role->id, RoleHierarchy::assignableRoleIds($caller->user_role), true)) {
            abort(403, 'You are not authorized to manage this role.');
        }
    }

    public function index(Request $request)
    {
        $caller = $request->user();
        $this->authorizeManager($caller);
        $assignableIds = RoleHierarchy::assignableRoleIds($caller->user_role);

        $roles = Role::withCount('permissions')->get()
            ->filter(fn ($role) => !$role->is_system || in_array($role->id, $assignableIds, true))
            ->values()
            ->map(function ($role) {
                return [
                    'id'               => $role->id,
                    'name'             => $role->name,
                    'slug'             => $role->slug,
                    'description'      => $role->description,
                    'color'            => $role->color,
                    'is_system'        => $role->is_system,
                    'user_count'       => User::where('user_role', $role->id)->count(),
                    'permissions_count'=> $role->permissions_count,
                    'created_at'       => $role->created_at,
                ];
            });

        return response()->json(['data' => $roles]);
    }

    public function store(Request $request)
    {
        $caller = $request->user();
        $this->authorizeManager($caller);

        // A role may be copied from if it's within the caller's tier, or it's a
        // custom role (custom roles are already bounded by this same rule at creation time).
        $customRoleIds = Role::where('is_system', false)->pluck('id')->all();
        $copyableIds = array_merge(RoleHierarchy::assignableRoleIds($caller->user_role), $customRoleIds);

        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'color'       => 'nullable|string|max:20',
            'copy_from'   => ['nullable', 'exists:roles,id', Rule::in($copyableIds)],
        ]);

        $slug = Str::slug($data['name']);
        $base = $slug;
        $i = 1;
        while (Role::where('slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        $role = Role::create([
            'name'        => $data['name'],
            'slug'        => $slug,
            'description' => $data['description'] ?? null,
            'color'       => $data['color'] ?? '#6366f1',
            'is_system'   => false,
        ]);

        if (!empty($data['copy_from'])) {
            $source = RolePermission::where('role_id', $data['copy_from'])->get();
            foreach ($source as $p) {
                RolePermission::create([
                    'role_id'    => $role->id,
                    'module_slug'=> $p->module_slug,
                    'can_view'   => $p->can_view,
                    'can_create' => $p->can_create,
                    'can_edit'   => $p->can_edit,
                    'can_delete' => $p->can_delete,
                ]);
            }
        }

        return response()->json(['data' => $role->fresh()], 201);
    }

    public function update(Request $request, $id)
    {
        $caller = $request->user();
        $this->authorizeManager($caller);

        $role = Role::findOrFail($id);
        $this->authorizeRoleAccess($caller, $role);

        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'color'       => 'nullable|string|max:20',
        ]);

        $role->update($data);

        return response()->json(['data' => $role->fresh()]);
    }

    public function destroy(Request $request, $id)
    {
        $caller = $request->user();
        $this->authorizeManager($caller);

        $role = Role::findOrFail($id);
        $this->authorizeRoleAccess($caller, $role);

        if ($role->is_system) {
            return response()->json(['message' => 'System roles cannot be deleted.'], 403);
        }

        $count = User::where('user_role', $role->id)->count();
        if ($count > 0) {
            return response()->json([
                'message' => "Cannot delete: {$count} user(s) are assigned to this role.",
            ], 422);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted.']);
    }

    public function modules(Request $request)
    {
        $this->authorizeManager($request->user());

        return response()->json(['data' => self::MODULES]);
    }

    public function permissions(Request $request, $id)
    {
        $caller = $request->user();
        $this->authorizeManager($caller);

        $role = Role::with('permissions')->findOrFail($id);
        $this->authorizeRoleAccess($caller, $role);

        $map = [];
        foreach ($role->permissions as $p) {
            $map[$p->module_slug] = [
                'can_view'   => (bool) $p->can_view,
                'can_create' => (bool) $p->can_create,
                'can_edit'   => (bool) $p->can_edit,
                'can_delete' => (bool) $p->can_delete,
            ];
        }

        $permissions = [];
        foreach (self::MODULES as $module) {
            $existing = $map[$module['slug']] ?? null;
            $permissions[] = [
                'module_slug' => $module['slug'],
                'module_name' => $module['name'],
                'group'       => $module['group'],
                'can_view'    => $existing['can_view']   ?? false,
                'can_create'  => $existing['can_create'] ?? false,
                'can_edit'    => $existing['can_edit']   ?? false,
                'can_delete'  => $existing['can_delete'] ?? false,
            ];
        }

        return response()->json([
            'data' => [
                'role'        => [
                    'id'        => $role->id,
                    'name'      => $role->name,
                    'slug'      => $role->slug,
                    'color'     => $role->color,
                    'is_system' => $role->is_system,
                ],
                'permissions' => $permissions,
            ],
        ]);
    }

    public function updatePermissions(Request $request, $id)
    {
        $caller = $request->user();
        $this->authorizeManager($caller);

        $role = Role::findOrFail($id);
        $this->authorizeRoleAccess($caller, $role);

        $data = $request->validate([
            'permissions'               => 'required|array',
            'permissions.*.module_slug' => 'required|string',
            'permissions.*.can_view'    => 'required|boolean',
            'permissions.*.can_create'  => 'required|boolean',
            'permissions.*.can_edit'    => 'required|boolean',
            'permissions.*.can_delete'  => 'required|boolean',
        ]);

        foreach ($data['permissions'] as $perm) {
            RolePermission::updateOrCreate(
                ['role_id' => $role->id, 'module_slug' => $perm['module_slug']],
                [
                    'can_view'   => $perm['can_view'],
                    'can_create' => $perm['can_create'],
                    'can_edit'   => $perm['can_edit'],
                    'can_delete' => $perm['can_delete'],
                ]
            );
        }

        return response()->json(['message' => 'Permissions saved.']);
    }
}
