<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class BranchController extends Controller
{
    // ✅ GET /api/branches
    public function index()
    {
        $branches = Branch::latest()->get();

        return response()->json([
            'status' => true,
            'data' => $branches
        ]);
    }

    // ✅ POST /api/branches
    public function store(Request $request)
    {
        $request->validate([
            'branch_name' => 'required|string|max:255',
            'branch_city' => 'required|string|max:255',
            'branch_address' => 'nullable|string',
            'branch_phone' => 'nullable|string|max:20',

            'branch_code' => 'nullable|string|max:3',
            'campus_start_date' => 'nullable|date',
            'campus_phone' => 'nullable|string|max:20',
            'campus_email' => 'nullable|email|max:255',

            // optional for admin creation
            'admin_email' => 'nullable|email|max:255',
            'admin_password' => 'nullable|string|min:6',
            'branch_admin_id' => 'nullable|integer|exists:users,id',
        ]);

        $branchAdminId = $request->branch_admin_id;

        // 1. If no admin provided, create new user
        if (!$branchAdminId && $request->admin_email && $request->admin_password) {

            $user = User::create([
                'name' => $request->branch_name . ' Admin',
                'email' => $request->admin_email,
                'password' => Hash::make($request->admin_password),
                'user_role' => 3
            ]);

            $branchAdminId = $user->id;
        }

        // 2. Create branch
        $branch = Branch::create([
            'added_by' => auth()->id(),
            'branch_name' => $request->branch_name,
            'branch_city' => $request->branch_city,
            'branch_address' => $request->branch_address,
            'branch_phone' => $request->branch_phone,

            'branch_code' => $request->branch_code
                ? strtoupper($request->branch_code)
                : null,

            'campus_start_date' => $request->campus_start_date,
            'campus_phone' => $request->branch_phone,
            'campus_email' => $request->campus_email,

            'branch_admin_id' => $branchAdminId,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Branch created successfully',
            'data' => $branch
        ], 201);
    }

    // ✅ GET /api/branches/{id}
    public function show($id)
    {
        $branch = Branch::find($id);

        if (!$branch) {
            return response()->json([
                'status' => false,
                'message' => 'Branch not found'
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $branch
        ]);
    }

    // ✅ PUT/PATCH /api/branches/{id}
    public function update(Request $request, $id)
    {
        $branch = Branch::find($id);

        if (!$branch) {
            return response()->json([
                'status' => false,
                'message' => 'Branch not found'
            ], 404);
        }

        $request->validate([
            'branch_name' => 'sometimes|required|string|max:255',
            'branch_city' => 'sometimes|required|string|max:255',
            'branch_address' => 'nullable|string',
            'branch_phone' => 'nullable|string|max:20',

            'branch_code' => 'nullable|string|max:3',
            'campus_start_date' => 'nullable|date',
            'campus_email' => 'nullable|email|max:255',
        ]);

        $branch->update([
            'branch_name' => $request->branch_name ?? $branch->branch_name,
            'branch_city' => $request->branch_city ?? $branch->branch_city,
            'branch_address' => $request->branch_address ?? $branch->branch_address,

            // sync both phones
            'branch_phone' => $request->branch_phone ?? $branch->branch_phone,
            'campus_phone' => $request->branch_phone ?? $branch->campus_phone,

            'branch_code' => $request->branch_code
                ? strtoupper($request->branch_code)
                : $branch->branch_code,

            'campus_start_date' => $request->campus_start_date ?? $branch->campus_start_date,

            'campus_email' => $request->campus_email
                ? strtolower($request->campus_email)
                : $branch->campus_email,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Branch updated successfully',
            'data' => $branch
        ]);
    }

    // ✅ DELETE /api/branches/{id}
    public function destroy($id)
    {
        $branch = Branch::find($id);

        if (!$branch) {
            return response()->json([
                'status' => false,
                'message' => 'Branch not found'
            ], 404);
        }

        $branch->delete(); // soft delete

        return response()->json([
            'status' => true,
            'message' => 'Branch deleted successfully'
        ]);
    }
}