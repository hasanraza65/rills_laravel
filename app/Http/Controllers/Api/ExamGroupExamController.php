<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamGroupExam;
use Illuminate\Http\Request;

class ExamGroupExamController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->branch_id ?: auth()->user()->branch_id;

        return ExamGroupExam::query()
            ->when($request->exam_group_id, fn ($q, $v) => $q->where('exam_group_id', $v))
            ->when(
                !$request->exam_group_id && $branchId,
                fn ($q) => $q->whereHas('group', fn ($g) => $g->where('branch_id', $branchId))
            )
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'exam_group_id' => 'required|exists:exam_groups,id',
            'name' => 'required|string',
            'publish_exam' => 'boolean',
            'publish_schedule' => 'boolean',
            'publish_result' => 'boolean',
            'description' => 'nullable|string',
        ]);

        return ExamGroupExam::create($data);
    }

    public function update(Request $request, $id)
    {
        $exam = ExamGroupExam::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string',
            'publish_exam' => 'boolean',
            'publish_schedule' => 'boolean',
            'publish_result' => 'boolean',
            'description' => 'nullable|string',
        ]);

        $exam->update($data);

        return $exam;
    }

    public function destroy($id)
    {
        ExamGroupExam::findOrFail($id)->delete();

        return response()->json(['message' => 'Exam deleted']);
    }
}
