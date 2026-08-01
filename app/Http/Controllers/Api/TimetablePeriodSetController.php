<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimetablePeriodSet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TimetablePeriodSetController extends Controller
{
    public function index(Request $request)
    {
        $branchId = $request->branch_id ?: auth()->user()->branch_id;

        return TimetablePeriodSet::with(['group', 'slots'])
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when($request->timetable_group_id, fn ($q) => $q->where('timetable_group_id', $request->timetable_group_id))
            ->latest()
            ->get();
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'timetable_group_id' => 'required|exists:timetable_groups,id',
            'title' => 'required|string',
            'slots' => 'required|array|min:1',
            'slots.*.day_of_week' => 'required|integer|between:1,7',
            'slots.*.period_number' => 'required|integer|min:1',
            'slots.*.duration_minutes' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($data) {
            $periodSet = TimetablePeriodSet::create([
                'branch_id' => $data['branch_id'],
                'timetable_group_id' => $data['timetable_group_id'],
                'title' => $data['title'],
                'added_by' => auth()->id(),
            ]);

            $periodSet->slots()->createMany($data['slots']);

            return $periodSet->load(['group', 'slots']);
        });
    }

    public function show($id)
    {
        return TimetablePeriodSet::with(['group', 'slots'])->findOrFail($id);
    }

    public function update(Request $request, $id)
    {
        $periodSet = TimetablePeriodSet::findOrFail($id);

        $data = $request->validate([
            'title' => 'required|string',
            'slots' => 'required|array|min:1',
            'slots.*.day_of_week' => 'required|integer|between:1,7',
            'slots.*.period_number' => 'required|integer|min:1',
            'slots.*.duration_minutes' => 'required|integer|min:1',
        ]);

        return DB::transaction(function () use ($periodSet, $data) {
            $periodSet->update(['title' => $data['title']]);

            // Replace-all: simplest correct approach for a small duration matrix,
            // and this table has no other rows referencing it, so nothing to preserve.
            $periodSet->slots()->delete();
            $periodSet->slots()->createMany($data['slots']);

            return $periodSet->load(['group', 'slots']);
        });
    }

    public function destroy($id)
    {
        TimetablePeriodSet::findOrFail($id)->delete();

        return response()->json([
            'message' => 'Periods template deleted',
        ]);
    }
}
