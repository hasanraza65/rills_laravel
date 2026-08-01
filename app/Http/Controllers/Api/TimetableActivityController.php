<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimetableActivity;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class TimetableActivityController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->branch_id ?: auth()->user()->branch_id;

        // Global activities (branch_id null) are visible to every campus, in addition
        // to whatever the caller's own branch has defined.
        return TimetableActivity::where(function ($q) use ($branchId) {
                $q->whereNull('branch_id');

                if ($branchId) {
                    $q->orWhere('branch_id', $branchId);
                }
            })
            ->orderBy('name')
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'nullable|exists:branches,id',
            'name' => 'required|string',
        ]);

        $this->assertNameAvailable($data['name'], $data['branch_id'] ?? null);

        return TimetableActivity::create($data);
    }

    public function update(Request $request, $id)
    {
        $activity = TimetableActivity::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string',
        ]);

        $this->assertNameAvailable($data['name'], $activity->branch_id, $activity->id);

        $activity->update($data);

        return $activity;
    }

    public function destroy($id)
    {
        TimetableActivity::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Activity deleted',
        ]);
    }

    private function assertNameAvailable(string $name, ?int $branchId, ?int $ignoreId = null): void
    {
        $exists = TimetableActivity::whereRaw('LOWER(name) = ?', [strtolower($name)])
            ->where(function ($q) use ($branchId) {
                $branchId ? $q->where('branch_id', $branchId) : $q->whereNull('branch_id');
            })
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => 'An activity with this name already exists in this scope.',
            ]);
        }
    }
}
