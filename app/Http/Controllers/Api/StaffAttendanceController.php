<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffAttendance;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StaffAttendanceController extends Controller
{
    // Staff roles (excludes PARENT = 5)
    private const STAFF_ROLES = [1, 2, 3, 4, 6, 7];

    /**
     * Returns staff in a branch with their attendance for a given date.
     * Used by the admin's daily marking view.
     */
    public function branchView(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'date'      => 'required|date_format:Y-m-d',
        ]);

        $staff = User::where('branch_id', $request->branch_id)
            ->whereIn('user_role', self::STAFF_ROLES)
            ->select('id', 'name', 'user_role', 'avatar')
            ->get();

        $existingMap = StaffAttendance::where('branch_id', $request->branch_id)
            ->where('date', $request->date)
            ->get()
            ->keyBy('user_id');

        $staffWithAttendance = $staff->map(function ($user) use ($existingMap) {
            $att = $existingMap->get($user->id);
            return [
                'id'         => $user->id,
                'name'       => $user->name,
                'role'       => $user->user_role,
                'avatar'     => $user->avatar,
                'attendance' => $att ? [
                    'id'      => $att->id,
                    'status'  => $att->status,
                    'remarks' => $att->remarks,
                ] : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => [
                'branch_id'      => $request->branch_id,
                'date'           => $request->date,
                'already_marked' => $existingMap->isNotEmpty(),
                'staff'          => $staffWithAttendance,
            ],
        ]);
    }

    /**
     * Bulk upsert staff attendance for a branch on a date.
     */
    public function mark(Request $request)
    {
        $request->validate([
            'branch_id'            => 'required|integer|exists:branches,id',
            'date'                 => 'required|date_format:Y-m-d',
            'records'              => 'required|array|min:1',
            'records.*.user_id'    => 'required|integer|exists:users,id',
            'records.*.status'     => 'required|in:P,A,L,H',
            'records.*.remarks'    => 'nullable|string|max:500',
        ]);

        $markedBy = auth()->id();

        DB::beginTransaction();
        try {
            $saved = [];
            foreach ($request->records as $rec) {
                $att = StaffAttendance::updateOrCreate(
                    [
                        'user_id' => $rec['user_id'],
                        'date'    => $request->date,
                    ],
                    [
                        'branch_id' => $request->branch_id,
                        'status'    => $rec['status'],
                        'remarks'   => $rec['remarks'] ?? null,
                        'marked_by' => $markedBy,
                    ]
                );
                $saved[] = ['user_id' => $att->user_id, 'status' => $att->status];
            }
            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to save attendance.'], 500);
        }

        return response()->json([
            'success' => true,
            'message' => 'Staff attendance saved successfully.',
            'data'    => $saved,
        ]);
    }

    /**
     * Monthly report: staff attendance grid for a branch.
     */
    public function report(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'month'     => 'required|regex:/^\d{4}-\d{2}$/',
        ]);

        [$year, $monthNum] = explode('-', $request->month);
        $startDate = "{$request->month}-01";
        $endDate   = date('Y-m-t', strtotime($startDate));

        $staff = User::where('branch_id', $request->branch_id)
            ->whereIn('user_role', self::STAFF_ROLES)
            ->select('id', 'name', 'user_role')
            ->get();

        $staffIds = $staff->pluck('id');

        $records = StaffAttendance::whereIn('user_id', $staffIds)
            ->whereBetween('date', [$startDate, $endDate])
            ->get()
            ->groupBy('user_id');

        // Generate dates
        $dates = [];
        $current = new \DateTime($startDate);
        $end     = new \DateTime($endDate);
        while ($current <= $end) {
            $dates[] = $current->format('Y-m-d');
            $current->modify('+1 day');
        }

        $roleMap = [1 => 'SUPER_ADMIN', 2 => 'SCHOOL_ADMIN', 3 => 'BRANCH_ADMIN', 4 => 'TEACHER', 6 => 'GATE_KEEPER', 7 => 'LIBRARIAN'];

        $result = $staff->map(function ($user) use ($records, $dates, $roleMap) {
            $userRecords = $records->get($user->id, collect());
            $byDate      = $userRecords->keyBy(fn($r) => $r->date->format('Y-m-d'));

            $perDate = [];
            foreach ($dates as $date) {
                $rec          = $byDate->get($date);
                $perDate[$date] = $rec ? ['status' => $rec->status, 'remarks' => $rec->remarks] : null;
            }

            $totals = ['P' => 0, 'A' => 0, 'L' => 0, 'H' => 0];
            foreach ($userRecords as $rec) {
                if (isset($totals[$rec->status])) {
                    $totals[$rec->status]++;
                }
            }

            return [
                'user_id' => $user->id,
                'name'    => $user->name,
                'role'    => $roleMap[$user->user_role] ?? 'STAFF',
                'records' => $perDate,
                'totals'  => $totals,
            ];
        });

        return response()->json([
            'success' => true,
            'data'    => $result,
            'dates'   => $dates,
        ]);
    }

    /**
     * Branch-level summary stats for the admin overview.
     */
    public function summary(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
            'date'      => 'nullable|date_format:Y-m-d',
        ]);

        $date = $request->date ?? now()->format('Y-m-d');

        $todayRecords = StaffAttendance::where('branch_id', $request->branch_id)
            ->where('date', $date)
            ->get();

        $totalToday = $todayRecords->count();
        $counts     = ['P' => 0, 'A' => 0, 'L' => 0, 'H' => 0];
        foreach ($todayRecords as $rec) {
            if (isset($counts[$rec->status])) {
                $counts[$rec->status]++;
            }
        }

        // Last 7 days trend
        $trends = [];
        for ($i = 6; $i >= 0; $i--) {
            $d          = now()->subDays($i)->format('Y-m-d');
            $dayRecords = StaffAttendance::where('branch_id', $request->branch_id)
                ->where('date', $d)
                ->selectRaw('status, COUNT(*) as cnt')
                ->groupBy('status')
                ->pluck('cnt', 'status');

            $dayTotal   = array_sum($dayRecords->toArray());
            $dayPresent = $dayRecords->get('P', 0);

            $trends[] = [
                'date'        => $d,
                'label'       => now()->subDays($i)->format('D'),
                'present_pct' => $dayTotal > 0 ? round(($dayPresent / $dayTotal) * 100, 1) : 0,
                'total'       => $dayTotal,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'date'               => $date,
                'total_marked'       => $totalToday,
                'counts'             => $counts,
                'present_percentage' => $totalToday > 0 ? round(($counts['P'] / $totalToday) * 100, 1) : 0,
                'trends'             => $trends,
            ],
        ]);
    }
}
