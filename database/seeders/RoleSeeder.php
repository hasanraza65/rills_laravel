<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $roles = [
            [1, 'Super Admin',  'super_admin',  'Full access to all modules and settings',        '#7c3aed', true],
            [2, 'Admin',        'admin',        'School-level administrator with broad access',    '#0ea5e9', true],
            [3, 'Branch Admin', 'branch_admin', 'Manages a single branch and its operations',     '#10b981', true],
            [4, 'Teacher',      'teacher',      'Manages classes, diaries and lesson plans',      '#f59e0b', true],
            [5, 'Parent',       'parent',       'Views student records, fees and attendance',      '#ec4899', true],
            [6, 'Gate Keeper',  'gate_keeper',  'Manages visitor log and entry',                  '#64748b', true],
            [7, 'Librarian',    'librarian',    'Library module access and book management',       '#8b5cf6', true],
        ];

        foreach ($roles as [$id, $name, $slug, $desc, $color, $is_system]) {
            DB::table('roles')->insertOrIgnore([
                'id'          => $id,
                'name'        => $name,
                'slug'        => $slug,
                'description' => $desc,
                'color'       => $color,
                'is_system'   => $is_system,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
        }

        // Shorthand: [module_slug, view, create, edit, delete]
        $rolePerms = [
            // 1 - Super Admin: full access to everything
            1 => $this->fullAccess([
                'dashboard', 'students', 'families', 'diaries', 'syllabus',
                'lesson_plans', 'question_bank', 'student_attendance', 'staff_attendance',
                'fees', 'staff', 'branches', 'classes_sections', 'visitors',
                'library', 'roles', 'reports',
            ]),

            // 2 - Admin: full access (same as super admin, enforced at app layer)
            2 => $this->fullAccess([
                'dashboard', 'students', 'families', 'diaries', 'syllabus',
                'lesson_plans', 'question_bank', 'student_attendance', 'staff_attendance',
                'fees', 'staff', 'branches', 'classes_sections', 'visitors',
                'library', 'roles', 'reports',
            ]),

            // 3 - Branch Admin
            3 => [
                ['dashboard',          true,  false, false, false],
                ['students',           true,  true,  true,  true ],
                ['families',           true,  true,  true,  true ],
                ['fees',               true,  true,  true,  true ],
                ['student_attendance', true,  true,  true,  false],
                ['staff_attendance',   true,  true,  true,  false],
                ['diaries',            true,  true,  true,  true ],
                ['staff',              true,  true,  false, false],
                ['branches',           true,  false, false, false],
                ['classes_sections',   true,  false, false, false],
                ['library',            true,  true,  true,  true ],
                ['visitors',           true,  true,  true,  true ],
                ['syllabus',           true,  false, false, false],
                ['reports',            true,  false, false, false],
            ],

            // 4 - Teacher
            4 => [
                ['dashboard',          true,  false, false, false],
                ['students',           true,  false, false, false],
                ['student_attendance', true,  true,  false, false],
                ['diaries',            true,  true,  true,  true ],
                ['syllabus',           true,  true,  true,  true ],
                ['lesson_plans',       true,  true,  true,  true ],
                ['question_bank',      true,  true,  true,  true ],
            ],

            // 5 - Parent
            5 => [
                ['dashboard',          true,  false, false, false],
                ['students',           true,  false, false, false],
                ['student_attendance', true,  false, false, false],
                ['fees',               true,  false, false, false],
                ['diaries',            true,  false, false, false],
            ],

            // 6 - Gate Keeper
            6 => [
                ['dashboard', true,  false, false, false],
                ['visitors',  true,  true,  true,  true ],
            ],

            // 7 - Librarian
            7 => [
                ['dashboard', true,  false, false, false],
                ['students',  true,  false, false, false],
                ['library',   true,  true,  true,  true ],
            ],
        ];

        foreach ($rolePerms as $roleId => $perms) {
            foreach ($perms as [$slug, $view, $create, $edit, $delete]) {
                DB::table('role_permissions')->insertOrIgnore([
                    'role_id'    => $roleId,
                    'module_slug'=> $slug,
                    'can_view'   => $view,
                    'can_create' => $create,
                    'can_edit'   => $edit,
                    'can_delete' => $delete,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    private function fullAccess(array $modules): array
    {
        return array_map(fn($m) => [$m, true, true, true, true], $modules);
    }
}
