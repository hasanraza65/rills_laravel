<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamSubjectGroup;
use Illuminate\Http\Request;

class ExamSubjectGroupController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->branch_id ?: auth()->user()->branch_id;

        return ExamSubjectGroup::with('subjects:id,subject_name')
            ->when($request->section_id, fn ($q, $v) => $q->where('section_id', $v))
            ->when(!$request->section_id && $branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'section_id' => 'required|exists:sections,id',
            'name' => 'required|string',
            'subject_ids' => 'required|array|min:1',
            'subject_ids.*' => 'exists:class_subjects,id',
        ]);

        $group = ExamSubjectGroup::create([
            'branch_id' => $data['branch_id'],
            'section_id' => $data['section_id'],
            'name' => $data['name'],
        ]);

        $group->subjects()->sync($data['subject_ids']);

        return $group->load('subjects:id,subject_name');
    }
}
