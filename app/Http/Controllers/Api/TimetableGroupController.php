<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimetableGroup;
use Illuminate\Http\Request;

class TimetableGroupController extends Controller
{
    public function index(Request $request)
    {
        // Fall back to the caller's branch. Filtering on a null branch_id matches
        // nothing and returns an empty list with a 200 — a silent failure.
        $branchId = $request->branch_id ?: auth()->user()->branch_id;

        return TimetableGroup::with('classes')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string',
            'class_ids' => 'required|array|min:1',
            'class_ids.*' => 'exists:school_classes,id',
        ]);

        $group = TimetableGroup::create([
            'branch_id' => $data['branch_id'],
            'name' => $data['name'],
            'added_by' => auth()->id(),
        ]);

        $group->classes()->sync($data['class_ids']);

        return $group->load('classes');
    }

    public function show($id)
    {
        return TimetableGroup::with('classes')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $group = TimetableGroup::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string',
            'class_ids' => 'required|array|min:1',
            'class_ids.*' => 'exists:school_classes,id',
        ]);

        $group->update(['name' => $data['name']]);
        $group->classes()->sync($data['class_ids']);

        return $group->load('classes');
    }

    public function destroy($id)
    {
        TimetableGroup::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Time Table Group deleted',
        ]);
    }
}
