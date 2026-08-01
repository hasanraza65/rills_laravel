<?php

namespace Database\Seeders;

use App\Http\Controllers\Api\TimetableController;
use App\Models\Branch;
use App\Models\ClassSubject;
use App\Models\SchoolClass;
use App\Models\Section;
use App\Models\Timetable;
use App\Models\TimetableActivity;
use App\Models\TimetableGroup;
use App\Models\TimetablePeriodSet;
use App\Models\TimetableSlot;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds a realistic, re-runnable demo for the Time Table module using data that
 * already exists in the branch (real classes, real teachers) rather than inventing
 * new hardcoded names. Safe to run multiple times — every write is firstOrCreate'd
 * or guarded, and re-running generateSlots() preserves any manual edits made since.
 */
class TimetableDemoSeeder extends Seeder
{
    public function run(): void
    {
        $branch = Branch::where('branch_name', 'Main Campus')->first() ?? Branch::first();

        if (!$branch) {
            $this->command?->warn('TimetableDemoSeeder: no branch found, skipping.');
            return;
        }

        $gradeOne = SchoolClass::where('branch_id', $branch->id)->where('name', 'Grade 1')->first();

        $reception = SchoolClass::where('branch_id', $branch->id)->where('name', 'Reception')->whereHas('sections')->first()
            ?? SchoolClass::where('branch_id', $branch->id)->where('name', 'Reception')->first();

        if (!$gradeOne || !$reception) {
            $this->command?->warn('TimetableDemoSeeder: expected classes (Grade 1 / Reception) not found, skipping.');
            return;
        }

        $teachers = User::where('branch_id', $branch->id)->where('user_role', 4)->get();

        if ($teachers->isEmpty()) {
            $this->command?->warn('TimetableDemoSeeder: no teachers found for this branch, skipping.');
            return;
        }

        // Sections — reuse whatever Reception already has; add a couple to Grade 1
        // (currently section-less) so there's a multi-section level to demo.
        $nile = Section::firstOrCreate(['branch_id' => $branch->id, 'school_class_id' => $gradeOne->id, 'name' => 'Nile']);
        $yenisei = Section::firstOrCreate(['branch_id' => $branch->id, 'school_class_id' => $gradeOne->id, 'name' => 'Yenisei']);
        $receptionSection = Section::where('school_class_id', $reception->id)->first();

        $sections = collect([$nile, $yenisei, $receptionSection])->filter()->values();

        // Subject + teacher assignments — English gets every available teacher
        // (mirrors a subject taught by more than one teacher), others rotate.
        $subjectNames = ['English', 'Maths', 'Urdu', 'Science'];

        foreach ($sections as $section) {
            foreach ($subjectNames as $i => $subjectName) {
                $assignedTeachers = $subjectName === 'English'
                    ? $teachers
                    : $teachers->slice($i % $teachers->count(), 1);

                foreach ($assignedTeachers as $teacher) {
                    ClassSubject::firstOrCreate([
                        'branch_id' => $branch->id,
                        'class_id' => $section->school_class_id,
                        'section_id' => $section->id,
                        'teacher_id' => $teacher->id,
                        'subject_name' => $subjectName,
                    ]);
                }
            }
        }

        // Non-teaching activities — global (branch_id null), reusable everywhere.
        $activities = collect(['Assembly', 'Sports', 'Lunch', 'Break', 'Library'])
            ->mapWithKeys(fn ($name) => [$name => TimetableActivity::firstOrCreate(['branch_id' => null, 'name' => $name])]);

        // Group — spans both levels, same shape as "Level Six to Seven" from the spec.
        $group = TimetableGroup::firstOrCreate(['branch_id' => $branch->id, 'name' => 'Grade 1 & Reception Time Table']);
        $group->classes()->syncWithoutDetaching([$gradeOne->id, $reception->id]);

        // Periods — Mon-Fri only: Assembly (15m) then four 40m academic periods.
        $periodSet = TimetablePeriodSet::firstOrCreate([
            'branch_id' => $branch->id,
            'timetable_group_id' => $group->id,
            'title' => 'Standard Day',
        ]);

        if ($periodSet->slots()->count() === 0) {
            foreach (range(1, 5) as $day) {
                $periodSet->slots()->create(['day_of_week' => $day, 'period_number' => 1, 'duration_minutes' => 15]);
                foreach (range(2, 5) as $period) {
                    $periodSet->slots()->create(['day_of_week' => $day, 'period_number' => $period, 'duration_minutes' => 40]);
                }
            }
        }

        $admin = User::whereIn('user_role', [1, 2])->first();

        $timetable = Timetable::firstOrCreate(
            [
                'branch_id' => $branch->id,
                'timetable_group_id' => $group->id,
                'period_set_id' => $periodSet->id,
                'title' => 'Demo Timetable 2026',
            ],
            [
                'date_from' => now()->startOfMonth()->toDateString(),
                'date_to' => now()->addMonths(2)->toDateString(),
                'school_time_from' => '08:30:00',
                'is_active' => true,
                'created_by' => $admin?->id,
                'updated_by' => $admin?->id,
            ]
        );

        // Reuse the exact same algorithm the API uses — computes real start/end times
        // and preserves any assignments already made if this seeder runs again.
        (new TimetableController())->generateSlots($timetable);

        $englishBySection = ClassSubject::where('subject_name', 'English')
            ->whereIn('section_id', $sections->pluck('id'))
            ->get()
            ->groupBy('section_id');

        foreach ($sections as $section) {
            // Assembly on period 1, every weekday.
            TimetableSlot::where('timetable_id', $timetable->id)
                ->where('section_id', $section->id)
                ->where('period_number', 1)
                ->update(['timetable_activity_id' => $activities['Assembly']->id, 'class_subject_id' => null, 'teacher_id' => null]);

            // English on Monday period 2 — leaves other periods blank on purpose so
            // the grid shows a realistic mix of filled and empty cells.
            $englishCs = $englishBySection->get($section->id)?->first();

            if ($englishCs) {
                TimetableSlot::where('timetable_id', $timetable->id)
                    ->where('section_id', $section->id)
                    ->where('day_of_week', 1)
                    ->where('period_number', 2)
                    ->update(['class_subject_id' => $englishCs->id, 'teacher_id' => $englishCs->teacher_id, 'timetable_activity_id' => null]);
            }
        }

        $this->command?->info("TimetableDemoSeeder: seeded demo timetable #{$timetable->id} for branch #{$branch->id}.");
    }
}
