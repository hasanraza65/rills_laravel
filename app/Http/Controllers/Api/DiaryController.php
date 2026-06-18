<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Diary;
use App\Models\ClassSubject;
use App\Models\ClassSection;
use Auth;

class DiaryController extends Controller
{
    public function index(Request $request)
    {
        $query = Diary::with('classSubject');

        if (!auth()->user()->user_role == 5) {

            $subjectIds = ClassSubject::where('teacher_id', Auth::id())
                ->pluck('id');

            $query->whereIn('class_subject_id', $subjectIds);
        }

        if ($request->class_subject_id) {
            $query->where('class_subject_id', $request->class_subject_id);
        }

        if ($request->topic) {
            $query->where('topic', 'LIKE', '%' . $request->topic . '%');
        }

        $query->where('branch_id',$request->branch_id);

        $data = $query->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function index2(Request $request)
{
    $date = $request->date 
        ? date('Y-m-d', strtotime($request->date))
        : now()->addMonth()->format('Y-m-d');

    $monthFor = date('F', strtotime($date));

    $data = ClassSection::with('class')
        ->where('branch_id', $request->branch_id)
        ->get();

    return response()->json([
        'success' => true,
        'month' => $monthFor,
        'date' => $date,
        'data' => $data
    ]);
}

    public function store(Request $request)
    {
        $request->validate([
            'class_subject_id' => 'required',
            'topic' => 'required',
            'date' => 'required',
            'page_number' => 'nullable|string',
            'resources' => 'nullable|string',
            'link' => 'nullable|string',
            'home_work' => 'nullable|string',
        ]);

        $exists = Diary::where('class_subject_id', $request->class_subject_id)
            ->where('date', $request->date)
            ->first();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Diary already exists'
            ]);
        }

        $diary = Diary::create($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Diary created successfully',
            'data' => $diary
        ]);
    }

    public function show($id)
    {
        $diary = Diary::with('classSubject')->find($id);

        return response()->json([
            'success' => true,
            'data' => $diary
        ]);
    }

    public function update(Request $request, $id)
    {
        $diary = Diary::find($id);

        $diary->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Diary updated successfully'
        ]);
    }

    public function destroy($id)
    {
        Diary::find($id)?->delete();

        return response()->json([
            'success' => true,
            'message' => 'Diary deleted successfully'
        ]);
    }
}