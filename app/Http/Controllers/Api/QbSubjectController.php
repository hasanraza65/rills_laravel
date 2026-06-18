<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QbSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class QbSubjectController extends Controller
{
    /**
     * List all subjects — optionally filter by branch or class
     * GET /api/qb-subjects?branch_id=1&class_id=2
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        $query = QbSubject::with(['schoolClass:id,name', 'branch:id,branch_name']);

        // Branch scoping — Super Admin (role 1) sees all, others scoped to their branch
        if ($user->user_role !== 1) {
            $branchId = $request->branch_id ?? $user->branch_id ?? null;
            if ($branchId) {
                $query->where('branch_id', $branchId);
            }
        } elseif ($request->branch_id) {
            $query->where('branch_id', $request->branch_id);
        }

        if ($request->class_id) {
            $query->where('class_id', $request->class_id);
        }

        $data = $query->orderBy('name')->get();

        return response()->json([
            'success' => true,
            'data'    => $data,
        ]);
    }

    /**
     * Create a subject
     * POST /api/qb-subjects
     */
    public function store(Request $request)
    {
        $request->validate([
            'class_id' => 'required|exists:school_classes,id',
            'name'     => 'required|string|max:255',
        ]);

        $user = Auth::user();

        $subject = QbSubject::create([
            'branch_id' => $request->branch_id ?? null,
            'class_id'  => $request->class_id,
            'name'      => $request->name,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Subject created successfully.',
            'data'    => $subject->load('schoolClass:id,name'),
        ], 201);
    }

    /**
     * Show a single subject
     * GET /api/qb-subjects/{id}
     */
    public function show($id)
    {
        $subject = QbSubject::with(['schoolClass:id,name', 'branch:id,branch_name', 'topics'])->find($id);

        if (!$subject) {
            return response()->json(['success' => false, 'message' => 'Subject not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $subject]);
    }

    /**
     * Update a subject
     * PUT /api/qb-subjects/{id}
     */
    public function update(Request $request, $id)
    {
        $subject = QbSubject::find($id);

        if (!$subject) {
            return response()->json(['success' => false, 'message' => 'Subject not found.'], 404);
        }

        $request->validate([
            'class_id' => 'sometimes|exists:school_classes,id',
            'name'     => 'sometimes|string|max:255',
        ]);

        $subject->update($request->only(['class_id', 'name', 'branch_id']));

        return response()->json([
            'success' => true,
            'message' => 'Subject updated successfully.',
            'data'    => $subject->load('schoolClass:id,name'),
        ]);
    }

    /**
     * Delete a subject
     * DELETE /api/qb-subjects/{id}
     */
    public function destroy($id)
    {
        $subject = QbSubject::find($id);

        if (!$subject) {
            return response()->json(['success' => false, 'message' => 'Subject not found.'], 404);
        }

        $subject->delete();

        return response()->json(['success' => true, 'message' => 'Subject deleted successfully.']);
    }
}