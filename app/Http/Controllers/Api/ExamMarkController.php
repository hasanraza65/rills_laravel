<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamMark;
use App\Models\ExamSchedule;
use App\Models\Student;
use Illuminate\Http\Request;

class ExamMarkController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'exam_schedule_id' => 'required|exists:exam_schedules,id',
        ]);

        return ExamMark::where('exam_schedule_id', $data['exam_schedule_id'])
            ->get(['student_id', 'obtained_marks', 'is_absent', 'note']);
    }

    /**
     * Class-wide result sheet — every student in a class/section, every subject scheduled
     * for that exam type, and whatever marks have been entered so far. Grading itself is
     * computed client-side (see `calculateGrade` in types/api/exam.ts) so this just returns
     * the raw joined data.
     */
    public function marksheet(Request $request)
    {
        $data = $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'section_id' => 'required|exists:sections,id',
            'exam_type' => 'required|string',
        ]);

        $schedules = ExamSchedule::where('class_id', $data['class_id'])
            ->where('section_id', $data['section_id'])
            ->where('exam_type', $data['exam_type'])
            ->with('subject:id,subject_name')
            ->orderBy('id')
            ->get();

        $marksBySchedule = ExamMark::whereIn('exam_schedule_id', $schedules->pluck('id'))
            ->get()
            ->groupBy('exam_schedule_id');

        $students = Student::where('class_id', $data['class_id'])
            ->where('section_id', $data['section_id'])
            ->orderBy('name')
            ->get(['id', 'name', 'admission_no']);

        return [
            'subjects' => $schedules->map(fn ($s) => [
                'schedule_id' => $s->id,
                'subject_name' => $s->subject->subject_name ?? 'Subject',
                'total_marks' => $s->total_marks,
            ])->values(),
            'students' => $students->map(fn ($student) => [
                'student_id' => $student->id,
                'student_name' => $student->name,
                'admission_no' => $student->admission_no,
                'marks' => $schedules->mapWithKeys(function ($schedule) use ($student, $marksBySchedule) {
                    $mark = ($marksBySchedule[$schedule->id] ?? collect())->firstWhere('student_id', $student->id);
                    return [
                        $schedule->id => [
                            'obtained_marks' => $mark->obtained_marks ?? null,
                            'is_absent' => (bool) ($mark->is_absent ?? false),
                        ],
                    ];
                }),
            ])->values(),
        ];
    }

    /**
     * Every mark ever recorded for one student, with the exam type, subject name and
     * marks scale nested in — everything the Report Card needs in a single fetch.
     */
    public function forStudent($studentId)
    {
        return ExamMark::where('student_id', $studentId)
            ->whereHas('schedule')
            ->with(['schedule:id,exam_type,subject_id,total_marks,min_marks', 'schedule.subject:id,subject_name'])
            ->get(['id', 'exam_schedule_id', 'student_id', 'obtained_marks', 'is_absent', 'note']);
    }

    /** Saves every student's mark for one exam schedule in a single write (Marks Entry's Save button). */
    public function bulkSave(Request $request)
    {
        $data = $request->validate([
            'exam_schedule_id' => 'required|exists:exam_schedules,id',
            'marks' => 'array',
            'marks.*.student_id' => 'required|exists:students,id',
            'marks.*.obtained_marks' => 'nullable|integer|min:0',
            'marks.*.is_absent' => 'boolean',
            'marks.*.note' => 'nullable|string|max:255',
        ]);

        foreach ($data['marks'] ?? [] as $mark) {
            $isAbsent = (bool) ($mark['is_absent'] ?? false);
            ExamMark::updateOrCreate(
                [
                    'exam_schedule_id' => $data['exam_schedule_id'],
                    'student_id' => $mark['student_id'],
                ],
                [
                    // Absent students carry 0 marks regardless of what the input held before being marked absent.
                    'obtained_marks' => $isAbsent ? 0 : ($mark['obtained_marks'] ?? 0),
                    'is_absent' => $isAbsent,
                    'note' => $mark['note'] ?? null,
                ]
            );
        }

        return ExamMark::where('exam_schedule_id', $data['exam_schedule_id'])
            ->get(['student_id', 'obtained_marks', 'is_absent', 'note']);
    }

    /** Saves every subject's mark for one student in a single write ("By Student" Marks Entry mode). */
    public function bulkSaveForStudent(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|exists:students,id',
            'marks' => 'array',
            'marks.*.exam_schedule_id' => 'required|exists:exam_schedules,id',
            'marks.*.obtained_marks' => 'nullable|integer|min:0',
            'marks.*.is_absent' => 'boolean',
            'marks.*.note' => 'nullable|string|max:255',
        ]);

        foreach ($data['marks'] ?? [] as $mark) {
            $isAbsent = (bool) ($mark['is_absent'] ?? false);
            ExamMark::updateOrCreate(
                [
                    'exam_schedule_id' => $mark['exam_schedule_id'],
                    'student_id' => $data['student_id'],
                ],
                [
                    'obtained_marks' => $isAbsent ? 0 : ($mark['obtained_marks'] ?? 0),
                    'is_absent' => $isAbsent,
                    'note' => $mark['note'] ?? null,
                ]
            );
        }

        return ExamMark::where('student_id', $data['student_id'])
            ->whereIn('exam_schedule_id', collect($data['marks'] ?? [])->pluck('exam_schedule_id'))
            ->get(['exam_schedule_id', 'obtained_marks', 'is_absent', 'note']);
    }
}
