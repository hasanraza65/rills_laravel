<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamScheduleController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->branch_id ?: auth()->user()->branch_id;

        return ExamSchedule::with(['subject:id,subject_name', 'schoolClass:id,name', 'section:id,name', 'teacher:id,name'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($request->exam_type, fn ($q, $v) => $q->where('exam_type', $v))
            ->when($request->class_id, fn ($q, $v) => $q->where('class_id', $v))
            ->when($request->section_id, fn ($q, $v) => $q->where('section_id', $v))
            ->when($request->exam_group_exam_id, fn ($q, $v) => $q->where('exam_group_exam_id', $v))
            ->orderByDesc('date')
            ->get();
    }

    /**
     * Saves an entire class+section+exam-type schedule sheet in one shot — mirrors the
     * Exam Schedule bulk row-editor: upserts every row with data, and deletes any
     * previously-saved subject for this combo that the caller no longer sent (removed
     * via the row's "X" action, or left blank).
     */
    public function bulkSave(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'exam_type' => 'required|string',
            'exam_group_exam_id' => 'nullable|exists:exam_group_exams,id',
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:sections,id',
            'rows' => 'array',
            'rows.*.subject_id' => 'required|exists:class_subjects,id',
            'rows.*.date' => 'required|date',
            'rows.*.start_time' => 'required|string',
            'rows.*.duration' => 'nullable|string',
            'rows.*.teacher_id' => 'nullable|exists:users,id',
            'rows.*.total_marks' => 'required|integer|min:0',
            'rows.*.min_marks' => 'nullable|integer|min:0',
        ]);

        DB::transaction(function () use ($data) {
            $keptSubjectIds = [];

            foreach ($data['rows'] ?? [] as $row) {
                $keptSubjectIds[] = $row['subject_id'];

                ExamSchedule::updateOrCreate(
                    [
                        'class_id' => $data['class_id'],
                        'section_id' => $data['section_id'],
                        'subject_id' => $row['subject_id'],
                        'exam_type' => $data['exam_type'],
                    ],
                    [
                        'branch_id' => $data['branch_id'],
                        'exam_group_exam_id' => $data['exam_group_exam_id'] ?? null,
                        'date' => $row['date'],
                        'start_time' => $row['start_time'],
                        'duration' => $row['duration'] ?? null,
                        'teacher_id' => $row['teacher_id'] ?? null,
                        'total_marks' => $row['total_marks'],
                        'min_marks' => $row['min_marks'] ?? 0,
                    ]
                );
            }

            ExamSchedule::where('class_id', $data['class_id'])
                ->where('section_id', $data['section_id'])
                ->where('exam_type', $data['exam_type'])
                ->whereNotIn('subject_id', $keptSubjectIds)
                ->delete();
        });

        return ExamSchedule::with(['subject:id,subject_name', 'schoolClass:id,name', 'section:id,name', 'teacher:id,name'])
            ->where('class_id', $data['class_id'])
            ->where('section_id', $data['section_id'])
            ->where('exam_type', $data['exam_type'])
            ->get();
    }

    public function destroy($id)
    {
        ExamSchedule::findOrFail($id)->delete();

        return response()->json(['message' => 'Exam schedule deleted']);
    }
}
