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
        $query = ClassSubject::with([
            'class',
            'section',
            'teacher'
        ]);

        if ($request->section_id) {
            $query->where('section_id', $request->section_id);
        }

        $query->where('branch_id',$request->branch_id);

        $data = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'class_id' => 'required',
            'section_id' => 'required',
            'teacher_id' => 'required',
            'subject_name' => 'required',
        ]);

        $data = ClassSubject::create($request->all());

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