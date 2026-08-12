<?php

namespace App\Support;

use App\Models\RolePermission;
use App\Models\User;
use App\Models\UserPermission;

class PermissionResolver
{
    /** Only these tiers bypass all permission checks (privileged, can never lock themselves out). */
    const PRIVILEGED_ROLES = [1, 2];

    const MODULES = [
        ['slug' => 'dashboard',          'name' => 'Dashboard',           'group' => 'General'],
        ['slug' => 'students',           'name' => 'Students',            'group' => 'Academic'],
        ['slug' => 'families',           'name' => 'Parents & Families',  'group' => 'Academic'],
        ['slug' => 'diaries',            'name' => 'Diaries',             'group' => 'Academic'],
        ['slug' => 'syllabus',           'name' => 'Syllabus',            'group' => 'Academic'],
        ['slug' => 'lesson_plans',       'name' => 'Lesson Plans',        'group' => 'Academic'],
        ['slug' => 'question_bank',      'name' => 'Question Bank',       'group' => 'Academic'],
        ['slug' => 'timetable',          'name' => 'Time Table',          'group' => 'Academic'],
        ['slug' => 'examination',        'name' => 'Examination',         'group' => 'Academic'],
        ['slug' => 'student_attendance', 'name' => 'Student Attendance',  'group' => 'Attendance'],
        ['slug' => 'staff_attendance',   'name' => 'Staff Attendance',    'group' => 'Attendance'],
        ['slug' => 'fees',               'name' => 'Fee & Finance',       'group' => 'Finance'],
        ['slug' => 'staff',              'name' => 'Staff Management',    'group' => 'Administration'],
        ['slug' => 'branches',           'name' => 'Branches',            'group' => 'Administration'],
        ['slug' => 'classes_sections',   'name' => 'Classes & Sections',  'group' => 'Administration'],
        ['slug' => 'visitors',           'name' => 'Visitors / Gate',     'group' => 'Administration'],
        ['slug' => 'library',            'name' => 'Library',             'group' => 'Administration'],
        ['slug' => 'roles',              'name' => 'Roles & Permissions', 'group' => 'Administration'],
        ['slug' => 'academic_sessions',  'name' => 'Academic Sessions',   'group' => 'Administration'],
        ['slug' => 'reports',            'name' => 'Reports',             'group' => 'Reports'],
    ];

    public static function isPrivileged(User $user): bool
    {
        return in_array((int) $user->user_role, self::PRIVILEGED_ROLES, true);
    }

    /**
     * A user's effective permission map: role defaults overlaid with any
     * per-user overrides (non-null override flags win over the role's value).
     *
     * @return array<string, array{view: bool, create: bool, edit: bool, delete: bool}>
     */
    public static function effectivePermissions(User $user): array
    {
        $map = [];

        foreach (RolePermission::where('role_id', (int) $user->user_role)->get() as $row) {
            $map[$row->module_slug] = [
                'view'   => (bool) $row->can_view,
                'create' => (bool) $row->can_create,
                'edit'   => (bool) $row->can_edit,
                'delete' => (bool) $row->can_delete,
            ];
        }

        foreach (UserPermission::where('user_id', $user->id)->get() as $override) {
            $current = $map[$override->module_slug] ?? ['view' => false, 'create' => false, 'edit' => false, 'delete' => false];

            if (!is_null($override->can_view))   $current['view']   = (bool) $override->can_view;
            if (!is_null($override->can_create)) $current['create'] = (bool) $override->can_create;
            if (!is_null($override->can_edit))   $current['edit']   = (bool) $override->can_edit;
            if (!is_null($override->can_delete)) $current['delete'] = (bool) $override->can_delete;

            $map[$override->module_slug] = $current;
        }

        return $map;
    }

    public static function can(User $user, string $moduleSlug, string $action): bool
    {
        if (self::isPrivileged($user)) {
            return true;
        }

        return (bool) (self::effectivePermissions($user)[$moduleSlug][$action] ?? false);
    }
}
