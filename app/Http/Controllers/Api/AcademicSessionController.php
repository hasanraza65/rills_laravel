<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AcademicSessionController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->branch_id ?: auth()->user()->branch_id;

        return AcademicSession::when($branchId, fn ($q, $v) => $q->where('branch_id', $v))
            ->orderByDesc('start_date')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id'  => 'required|exists:branches,id',
            'name'       => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'is_active'  => 'boolean',
        ]);

        $data['added_by'] = auth()->id();

        return DB::transaction(function () use ($data) {
            // Only one active session per branch — activating this one deactivates
            // every other session in the same branch.
            if (!empty($data['is_active'])) {
                AcademicSession::where('branch_id', $data['branch_id'])->update(['is_active' => false]);
            }

            return AcademicSession::create($data);
        });
    }

    public function update(Request $request, $id)
    {
        $session = AcademicSession::findOrFail($id);

        $data = $request->validate([
            'name'       => 'required|string|max:255',
            'start_date' => 'required|date',
            'end_date'   => 'required|date|after:start_date',
            'is_active'  => 'boolean',
        ]);

        return DB::transaction(function () use ($session, $data) {
            if (!empty($data['is_active'])) {
                AcademicSession::where('branch_id', $session->branch_id)
                    ->where('id', '!=', $session->id)
                    ->update(['is_active' => false]);
            }

            $session->update($data);

            return $session->fresh();
        });
    }

    public function destroy($id)
    {
        AcademicSession::findOrFail($id)->delete();

        return response()->json(['success' => true]);
    }
}
