<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RoleController extends Controller
{
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

    public function index()
    {
        $roles = Role::withCount('permissions')->get()->map(function ($role) {
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
        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'color'       => 'nullable|string|max:20',
            'copy_from'   => 'nullable|exists:roles,id',
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
        $role = Role::findOrFail($id);

        $data = $request->validate([
            'name'        => 'required|string|max:100',
            'description' => 'nullable|string|max:255',
            'color'       => 'nullable|string|max:20',
        ]);

        $role->update($data);

        return response()->json(['data' => $role->fresh()]);
    }

    public function destroy($id)
    {
        $role = Role::findOrFail($id);

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

    public function modules()
    {
        return response()->json(['data' => self::MODULES]);
    }

    public function permissions($id)
    {
        $role = Role::with('permissions')->findOrFail($id);

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
        $role = Role::findOrFail($id);

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
