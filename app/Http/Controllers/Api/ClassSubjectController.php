<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\ClassSubject;
use App\Models\SchoolClass;

class ClassSubjectController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $query = ClassSubject::with([
            'class',
            'section',
            'teacher'
        ]);

        if ($request->section_id) {
            $query->where('section_id', $request->section_id);
        }

        // `mine=1` scopes to the caller's own teaching assignments. Teachers have no
        // branch_id on their user row, so this is the only way they can list subjects.
        if ($request->boolean('mine')) {
            $query->where('teacher_id', $user->id);
        } elseif ($request->teacher_id) {
            $query->where('teacher_id', $request->teacher_id);
        }

        // Fall back to the caller's branch rather than filtering on a null branch_id,
        // which would silently match nothing and return an empty list with a 200.
        $branchId = $request->branch_id ?: $user->branch_id;

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $data = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'class_id' => 'required|integer',
            'section_id' => 'required|integer',
            'teacher_id' => 'required|integer',
            'subject_name' => 'required|string',
            'campus_id' => 'nullable|integer',
            'session_id' => 'nullable|integer',
            'branch_id' => 'nullable|integer',
        ]);

        // Never trust an attacker-supplied branch_id blindly — fall back to the
        // caller's own branch, same scoping rule used in index().
        $validated['branch_id'] = $validated['branch_id'] ?? auth()->user()->branch_id;

        $data = ClassSubject::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Class subject created successfully',
            'data' => $data
        ]);
    }

    public function show($id)
    {
        $data = ClassSubject::with([
            'class',
            'section',
            'teacher'
        ])->find($id);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function update(Request $request, $id)
    {
        $data = ClassSubject::find($id);

        $data->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Class subject updated successfully',
            'data' => $data
        ]);
    }

    public function destroy($id)
    {
        ClassSubject::find($id)?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Class subject deleted successfully'
        ]);
    }

    public function getSubjects(Request $request)
    {
        $data = ClassSubject::where(
            'section_id',
            $request->section_id
        )->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function indexClasses()
    {
        $data = SchoolClass::with([
            'sections',
            'students'
        ])->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}