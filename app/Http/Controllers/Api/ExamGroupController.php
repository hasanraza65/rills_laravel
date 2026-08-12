<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamGroup;
use Illuminate\Http\Request;

class ExamGroupController extends Controller
{
    public function index(Request $request)
    {
        // Fall back to the caller's branch. Filtering on a null branch_id matches
        // nothing and returns an empty list with a 200 — a silent failure.
        $branchId = $request->branch_id ?: auth()->user()->branch_id;

        return ExamGroup::with('academicSession:id,name')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
            'name' => 'required|string',
            'exam_type' => 'required|string',
            'description' => 'nullable|string',
        ]);

        return ExamGroup::create([
            ...$data,
            'added_by' => auth()->id(),
        ]);
    }

    public function update(Request $request, $id)
    {
        $group = ExamGroup::findOrFail($id);

        $data = $request->validate([
            'academic_session_id' => 'nullable|exists:academic_sessions,id',
            'name' => 'required|string',
            'exam_type' => 'required|string',
            'description' => 'nullable|string',
        ]);

        $group->update($data);

        return $group;
    }

    public function destroy($id)
    {
        ExamGroup::findOrFail($id)->delete();

        return response()->json(['message' => 'Exam Group deleted']);
    }
}
