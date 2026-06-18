<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Syllabus;
use App\Models\ClassSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SyllabusController extends Controller
{
    // =====================
    // LIST (teacher wise)
    // =====================
    public function index(Request $request)
    {
        $user = Auth::user();

        $subjectIds = ClassSubject::where('teacher_id', $user->id)
            ->where('branch_id',$request->branch_id)
            ->pluck('id');

        $data = Syllabus::with('subject')
            ->whereIn('subject_id', $subjectIds)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    // =====================
    // ALL SYLLABUS (admin view)
    // =====================
    public function indexAll()
    {
        $data = Syllabus::with('subject')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function adminIndex()
    {
        $data = Syllabus::with('subject')->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    // =====================
    // CREATE
    // =====================
    public function store(Request $request)
    {
        $request->validate([
            'subject_id' => 'required',
            'month' => 'required',
            'content' => 'required',
        ]);

        $syllabus = Syllabus::create([
            'subject_id' => $request->subject_id,
            'month' => $request->month,
            'content' => $request->content,
            'status' => 'Pending',
            'campus_id' => auth()->user()->campus_id ?? null,
            'session_id' => auth()->user()->session_id ?? null,
            'branch_id' => $request->branch_id
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Syllabus created',
            'data' => $syllabus
        ]);
    }

    // =====================
    // SHOW
    // =====================
    public function show($id)
    {
        $data = Syllabus::with('subject')->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    // =====================
    // UPDATE
    // =====================
    public function update(Request $request, $id)
    {
        $data = Syllabus::findOrFail($id);

        $data->update($request->all());

        return response()->json([
            'success' => true,
            'message' => 'Updated successfully',
            'data' => $data
        ]);
    }

    // =====================
    // DELETE
    // =====================
    public function destroy($id)
    {
        $data = Syllabus::findOrFail($id);
        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted successfully'
        ]);
    }

    // =====================
    // STATUS UPDATE (single)
    // =====================
    public function updateStatus(Request $request)
    {
        $data = Syllabus::findOrFail($request->id);
        $data->status = $request->status;
        $data->save();

        return response()->json([
            'success' => true,
            'message' => 'Status updated'
        ]);
    }

    // =====================
    // BULK STATUS (section/month)
    // =====================
    public function updateFullStatus(Request $request)
    {
        $month = date('m', strtotime($request->date));

        $subjectIds = ClassSubject::where('section_id', $request->section_id)
            ->pluck('id');

        $syllabuses = Syllabus::whereMonth('month', $month)
            ->whereIn('subject_id', $subjectIds)
            ->get();

        foreach ($syllabuses as $syllabus) {
            $syllabus->status = $request->status;
            $syllabus->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk status updated'
        ]);
    }

    // =====================
    // DELETE SINGLE
    // =====================
    public function singleDelete(Request $request)
    {
        Syllabus::where('id', $request->id)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Deleted'
        ]);
    }

    // =====================
    // VIEW BY SECTION + MONTH
    // =====================
    public function syllabusesView(Request $request)
    {
        $month = date('m', strtotime($request->date));

        $subjectIds = ClassSubject::where('section_id', $request->section_id)
            ->pluck('id');

        $data = Syllabus::with('subject')
            ->whereMonth('month', $month)
            ->whereIn('subject_id', $subjectIds)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }
}