<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\TempAddKey;
use Auth;

class TempAddKeyController extends Controller
{
    // GET ALL
    public function index()
    {
        $data = TempAddKey::latest()->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    // STORE
    public function store(Request $request)
    {
        $request->validate([
            'branch_id' => 'nullable|integer',
            'key' => 'required|string',

            'visitor_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'purpose' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',

            // students should be an array of objects
            'students' => 'nullable|array',
            'students.*.name' => 'nullable|string|max:255',
            'students.*.class' => 'nullable|string|max:255',
        ]);

        $data = TempAddKey::create([
            'added_by' => Auth::id(),
            'branch_id' => $request->branch_id,
            'key' => $request->key,

            'visitor_name' => $request->visitor_name,
            'address' => $request->address,
            'purpose' => $request->purpose,
            'remarks' => $request->remarks,

            // Laravel will auto convert array → JSON if casted in model
            'students' => $request->students,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Created successfully',
            'data' => $data
        ]);
    }

    // SHOW SINGLE
    public function show($id)
    {
        $data = TempAddKey::find($id);

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    // UPDATE
    public function update(Request $request, $id)
    {
        $data = TempAddKey::find($id);

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Not found'
            ], 404);
        }

        $request->validate([
            'branch_id' => 'nullable|integer',
            'key' => 'nullable|string',

            'visitor_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'purpose' => 'nullable|string|max:255',
            'remarks' => 'nullable|string',

            'students' => 'nullable|array',
            'students.*.name' => 'nullable|string|max:255',
            'students.*.class' => 'nullable|string|max:255',
        ]);

        // Handle students (in case it comes as JSON string)
        $students = $request->has('students')
            ? (is_string($request->students)
                ? json_decode($request->students, true)
                : $request->students)
            : $data->students;

        $data->update([
            'branch_id' => $request->branch_id ?? $data->branch_id,
            'key' => $request->key ?? $data->key,

            'visitor_name' => $request->visitor_name ?? $data->visitor_name,
            'address' => $request->address ?? $data->address,
            'purpose' => $request->purpose ?? $data->purpose,
            'remarks' => $request->remarks ?? $data->remarks,

            'students' => $students,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Updated successfully',
            'data' => $data
        ]);
    }
    
    // DELETE
    public function destroy($id)
    {
        $data = TempAddKey::find($id);

        if (!$data) {
            return response()->json([
                'status' => false,
                'message' => 'Not found'
            ], 404);
        }

        $data->delete();

        return response()->json([
            'status' => true,
            'message' => 'Deleted successfully'
        ]);
    }
}