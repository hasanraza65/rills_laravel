<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Visitor;
use Illuminate\Support\Facades\Auth;

class VisitorController extends Controller
{
    
    // Get All Visitors
    public function index()
    {
        $visitors = Visitor::with('addedBy')->latest()->get();

        return response()->json([
            'status' => true,
            'data' => $visitors
        ]);
    }

    // Store Visitor
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'cnic' => 'nullable|string|max:20',
            'reason' => 'nullable|string',
            'branch_id' => 'nullable|integer'
        ]);

        $visitor = Visitor::create([
            'added_by' => Auth::id(),
            'branch_id' => $request->branch_id,
            'name' => $request->name,
            'phone' => $request->phone,
            'cnic' => $request->cnic,
            'reason' => $request->reason,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Visitor created successfully',
            'data' => $visitor
        ]);
    }

    // Show Single Visitor
    public function show($id)
    {
        $visitor = Visitor::with('addedBy')->find($id);

        if (!$visitor) {
            return response()->json([
                'status' => false,
                'message' => 'Visitor not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $visitor
        ]);
    }

    // Update Visitor
    public function update(Request $request, $id)
    {
        $visitor = Visitor::find($id);

        if (!$visitor) {
            return response()->json([
                'status' => false,
                'message' => 'Visitor not found'
            ], 404);
        }

        $request->validate([
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'cnic' => 'nullable|string|max:20',
            'reason' => 'nullable|string',
            'branch_id' => 'nullable|integer'
        ]);

        $visitor->update($request->all());

        return response()->json([
            'status' => true,
            'message' => 'Visitor updated successfully',
            'data' => $visitor
        ]);
    }

    // Delete Visitor
    public function destroy($id)
    {
        $visitor = Visitor::find($id);

        if (!$visitor) {
            return response()->json([
                'status' => false,
                'message' => 'Visitor not found'
            ], 404);
        }

        $visitor->delete();

        return response()->json([
            'status' => true,
            'message' => 'Visitor deleted successfully'
        ]);
    }
}