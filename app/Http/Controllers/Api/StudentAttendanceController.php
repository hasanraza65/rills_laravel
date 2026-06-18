<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParentProfile;
use App\Models\Section;
use App\Models\Student;
use App\Models\StudentAttendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentAttendanceController extends Controller
{
    /**
     * Returns students in a section along with their attendance for a given date.
     * Used by the teacher's daily marking view.
     */
    public function sectionView(Request $request)
    {
        $request->validate([
            'section_id' => 'required|integer|exists:sections,id',
            'date'       => 'required|date_format:Y-m-d',
        ]);

        $section  = Section::with('schoolClass:id,name')->findOrFail($request->section_id);
        $students = Student::where('section_id', $request->section_id)
            ->select('id', 'name', 'admission_no', 'gender')
            ->get();

        $existingMap = StudentAttendance::where('section_id', $request->section_id)
            ->where('date', $request->date)
            ->get()
            ->keyBy('student_id');

        $studentsWithAttendance = $students->map(function ($student) use ($existingMap) {
            $att = $existingMap->get($student->id);
            return [
                'id'           => $student->id,
                'name'         => $student->name,
                'admission_no' => $student->admission_no,
                'gender'       => $student->gender,
                'attendance'   => $att ? [
                    'id'      => $att->id,
                    'status'  => $att->status,
                    'remarks' => $att->remarks,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'section'        => [
                    'id'         => $section->id,
                    'name'       => $section->name,
                    'class_name' => $section->schoolClass?->name,
                ],
                'date'           => $request->date,
                'already_marked' => $existingMap->isNotEmpty(),
                'students'       => $studentsWithAttendance,
            ],
        ]);
    }

    /**
     * Bulk upsert attendance for a section on a date.
     */
    public function mark(Request $request)
    {
        $request->validate([
            'branch_id'              => 'required|integer|exists:branches,id',
            'section_id'             => 'required|integer|exists:sections,id',
            'date'                   => 'required|date_format:Y-m-d',
            'records'                => 'required|array|min:1',
            'records.*.student_id'   => 'required|integer|exists:students,id',
            'records.*.status'       => 'required|in:P,A,L,H',
            'records.*.remarks'      => 'nullable|string|max:500',
        ]);

        $markedBy = auth()->id();

        DB::beginTransaction();
        try {
            $saved = [];
            foreach ($request->records as $rec) {
                $att = StudentAttendance::updateOrCreate(
                    [
                        'student_id' => $rec['student_id'],
                        'date'       => $request->date,
                    ],
                    [
                        'branch_id'  => $request->branch_id,
                        'section_id' => $request->section_id,
                        'status'     => $rec['status'],
                        'remarks'    => $rec['remarks'] ?? null,
                        'marked_by'  => $markedBy,
                    ]
                );
                $saved[] = ['student_id' => $att->student_id, 'status' => $att->status];
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to save attendance.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Attendance saved successfully.',
            'data'    => $saved,
        ]);
    }

    /**
     * Date-range report for a section (admin/branch-admin view).
     */
    public function report(Request $request)
    {
        $request->validate([
            'branch_id'  => 'required|integer|exists:branches,id',
            'section_id' => 'required|integer|exists:sections,id',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date'   => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'student_id' => 'nullable|integer|exists:students,id',
        ]);

        $studentsQuery = Student::where('section_id', $request->section_id)
            ->select('id', 'name', 'admission_no', 'parent_id');

        if ($request->student_id) {
            $studentsQuery->where('id', $request->student_id);
        }

        $students   = $studentsQuery->with('parent:id,father_name')->get();
        $studentIds = $students->pluck('id');

        $records = StudentAttendance::whereIn('student_id', $studentIds)
            ->whereBetween('date', [$request->start_date, $request->end_date])
            ->get()
            ->groupBy('student_id');

        // Generate ordered date list
        $dates = [];
        $current = new \DateTime($request->start_date);
        $end     = new \DateTime($request->end_date);
        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        $result = $students->map(function ($student) use ($records, $dates) {
            $studentRecords = $records->get($student->id, collect());
            $byDate         = $studentRecords->keyBy(fn($r) => $r->date->format('Y-m-d'));

            $perDate = [];
            foreach ($dates as $date) {
                $rec           = $byDate->get($date);
                $perDate[$date] = $rec ? ['status' => $rec->status, 'remarks' => $rec->remarks] : null;
            }

            $totals = ['P' => 0, 'A' => 0, 'L' => 0, 'H' => 0];
            foreach ($studentRecords as $rec) {
                if (isset($totals[$rec->status])) {
                    $totals[$rec->status]++;
                }
            }

            return [
                'student_id'   => $student->id,
                'student_name' => $student->name,
                'father_name'  => $student->parent?->father_name,
                'admission_no' => $student->admission_no,
                'records'      => $perDate,
                'totals'       => $totals,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $result,
            'dates'   => $dates,
        ]);
    }

    /**
     * Monthly calendar view for a specific student (parent's perspective).
     */
    public function parentView(Request $request)
    {
        $request->validate([
            'student_id' => 'required|integer|exists:students,id',
            'year'       => 'required|integer|min:2020|max:2100',
            'month'      => 'required|integer|min:1|max:12',
        ]);

        $startDate = sprintf('%04d-%02d-01', $request->year, $request->month);
        $endDate   = date('Y-m-t', strtotime($startDate));

        $student = Student::with(['class:id,name', 'section:id,name'])->findOrFail($request->student_id);

        $records = StudentAttendance::where('student_id', $request->student_id)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get();

        $totals = ['P' => 0, 'A' => 0, 'L' => 0, 'H' => 0];
        foreach ($records as $rec) {
            if (isset($totals[$rec->status])) {
                $totals[$rec->status]++;
            }
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'student' => [
                    'id'           => $student->id,
                    'name'         => $student->name,
                    'class_name'   => $student->class?->name,
                    'section_name' => $student->section?->name,
                ],
                'records' => $records->map(fn($r) => [
                    'date'    => $r->date->format('Y-m-d'),
                    'status'  => $r->status,
                    'remarks' => $r->remarks,
                ]),
                'totals'  => $totals,
            ],
        ]);
    }

    /**
     * Parent: get all children of the authenticated user.
     */
    public function myChildren(Request $request)
    {
        $parentProfile = ParentProfile::where('user_id', auth()->id())->first();

        if (!$parentProfile) {
            return response()->json(['success' => false, 'message' => 'Parent profile not found.'], 404);
        }

        $students = Student::where('parent_id', $parentProfile->id)
            ->with(['class:id,name', 'section:id,name'])
            ->select('id', 'name', 'admission_no', 'class_id', 'section_id')
            ->get()
            ->map(fn($s) => [
                'id'           => $s->id,
                'name'         => $s->name,
                'admission_no' => $s->admission_no,
                'class_name'   => $s->class?->name,
                'section_name' => $s->section?->name,
            ]);

        return response()->json(['success' => true, 'data' => $students]);
    }

    /**
     * Branch-level summary stats for the admin overview.
     */
    public function summary(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'date'      => 'nullable|date_format:Y-m-d',
        ]);

        $date = $request->date ?? now()->format('Y-m-d');

        $todayRecords = StudentAttendance::where('branch_id', $request->branch_id)
            ->where('date', $date)
            ->get();

        $totalToday = $todayRecords->count();
        $counts     = ['P' => 0, 'A' => 0, 'L' => 0, 'H' => 0];
        foreach ($todayRecords as $rec) {
            if (isset($counts[$rec->status])) {
                $counts[$rec->status]++;
            }
        }

        // Last 7 days trend
        $trends = [];
        for ($i = 6; $i >= 0; $i--) {
            $d          = now()->subDays($i)->format('Y-m-d');
            $dayRecords = StudentAttendance::where('branch_id', $request->branch_id)
                ->where('date', $d)
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status');

            $dayTotal   = array_sum($dayRecords->toArray());
            $dayPresent = $dayRecords->get('P', 0);

            $trends[] = [
                'date'        => $d,
                'label'       => now()->subDays($i)->format('D'),
                'present_pct' => $dayTotal > 0 ? round(($dayPresent / $dayTotal) * 100, 1) : 0,
                'total'       => $dayTotal,
            ];
        }

        // By section for the date
        $bySection = StudentAttendance::where('branch_id', $request->branch_id)
            ->where('date', $date)
            ->with('section:id,name')
            ->get()
            ->groupBy('section_id')
            ->map(function ($recs, $sectionId) {
                $total   = $recs->count();
                $present = $recs->where('status', 'P')->count();
                $section = $recs->first()?->section;
                return [
                    'section_id'   => $sectionId,
                    'section_name' => $section?->name ?? "Section {$sectionId}",
                    'total'        => $total,
                    'present'      => $present,
                    'present_pct'  => $total > 0 ? round(($present / $total) * 100, 1) : 0,
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data'    => [
                'date'               => $date,
                'total_marked'       => $totalToday,
                'counts'             => $counts,
                'present_percentage' => $totalToday > 0 ? round(($counts['P'] / $totalToday) * 100, 1) : 0,
                'trends'             => $trends,
                'by_section'         => $bySection,
            ],
        ]);
    }
}
