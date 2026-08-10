<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamStudentSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamStudentSubjectController extends Controller
{
    /**
     * Returns { student_id: [class_subject_id, ...] } for the requested students.
     * POST (not GET) so a large student_ids array never has to survive URL/query-string
     * serialization.
     */
    public function lookup(Request $request)
    {
        $data = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'integer',
        ]);

        return ExamStudentSubject::whereIn('student_id', $data['student_ids'])
            ->get(['student_id', 'class_subject_id'])
            ->groupBy('student_id')
            ->map(fn ($rows) => $rows->pluck('class_subject_id')->values());
    }

    /** Adds `class_subject_ids` to every listed student's existing exam subjects (union — doesn't drop anything). */
    public function bulkAssign(Request $request)
    {
        $data = $request->validate([
            'student_ids' => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
            'class_subject_ids' => 'required|array|min:1',
            'class_subject_ids.*' => 'exists:class_subjects,id',
        ]);

        DB::transaction(function () use ($data) {
            $now = now();
            $rows = [];
            foreach ($data['student_ids'] as $studentId) {
                foreach ($data['class_subject_ids'] as $subjectId) {
                    $rows[] = [
                        'student_id' => $studentId,
                        'class_subject_id' => $subjectId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                }
            }
            ExamStudentSubject::insertOrIgnore($rows);
        });

        return response()->json(['message' => 'Subjects assigned']);
    }

    /** Sets one student's exam subjects to exactly `class_subject_ids` (full replace). */
    public function setForStudent(Request $request, $studentId)
    {
        $data = $request->validate([
            'class_subject_ids' => 'array',
            'class_subject_ids.*' => 'exists:class_subjects,id',
        ]);

        $subjectIds = $data['class_subject_ids'] ?? [];

        DB::transaction(function () use ($studentId, $subjectIds) {
            ExamStudentSubject::where('student_id', $studentId)
                ->whereNotIn('class_subject_id', $subjectIds)
                ->delete();

            $now = now();
            $rows = array_map(fn ($subjectId) => [
                'student_id' => $studentId,
                'class_subject_id' => $subjectId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $subjectIds);

            if ($rows) {
                ExamStudentSubject::insertOrIgnore($rows);
            }
        });

        return response()->json(['message' => 'Subjects updated']);
    }
}
