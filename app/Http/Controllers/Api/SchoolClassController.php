<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\SchoolClass;

class SchoolClassController extends Controller
{
    public function index(Request $request)
    {
        // Fall back to the caller's branch. Filtering on a null branch_id matches
        // nothing and returns an empty list with a 200 — a silent failure.
        $branchId = $request->branch_id ?: auth()->user()->branch_id;

        return SchoolClass::with('sections')
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required',
            'branch_id' => 'required'
        ]);

        $data['added_by'] = auth()->id();

        return SchoolClass::create($data);
    }

    public function show($id)
    {
        return SchoolClass::with('sections')->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $class = SchoolClass::findOrFail($id);

        $data = $request->validate([
            'name' => 'required'
        ]);

        $class->update($data);

        return $class;
    }

    public function destroy($id)
    {
        SchoolClass::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Class deleted'
        ]);
    }
}