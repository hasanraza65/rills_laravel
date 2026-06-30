<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Invoice;
use App\Models\LessonPlanSubject;
use App\Models\ParentProfile;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentAttendance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function overview(Request $request): JsonResponse
    {
        $user = auth()->user();

        return match ((int) $user->user_role) {
            1 => $this->superAdminOverview(),
            2 => $this->schoolAdminOverview($user),
            3 => $this->branchAdminOverview($request),
            4 => $this->teacherOverview($user),
            5 => $this->parentOverview($user),
            default => response()->json([
                'success' => false,
                'message' => 'No dashboard overview available for this role.',
            ], 403),
        };
    }

    // ─── Role 1: Super Admin ──────────────────────────────────────────────────

    private function superAdminOverview(): JsonResponse
    {
        $today = now()->toDateString();

        $totalBranches = Branch::count();
        $totalStudents = Student::where('currently_studying', true)->count();

        [$attendanceMarked, $attendancePresent] = $this->attendanceTotals(null, $today);
        [$totalInvoiced, $totalPaid]            = $this->feeTotals(null);

        return response()->json([
            'success' => true,
            'role'    => 'SUPER_ADMIN',
            'data'    => [
                'total_branches' => $totalBranches,
                'total_students' => $totalStudents,
                'attendance_today' => $this->attendanceStat($attendanceMarked, $attendancePresent),
                'fee_collection'   => $this->feeStat($totalInvoiced, $totalPaid),
            ],
        ]);
    }

    // ─── Role 2: School Admin ─────────────────────────────────────────────────

    private function schoolAdminOverview($user): JsonResponse
    {
        $today     = now()->toDateString();
        $branchIds = Branch::where('branch_admin_id', $user->id)->pluck('id');

        $totalStudents = Student::whereIn('branch_id', $branchIds)
            ->whereNotNull('currently_studying')
            ->count();

        [$attendanceMarked, $attendancePresent] = $this->attendanceTotals($branchIds, $today);
        [$totalInvoiced, $totalPaid]            = $this->feeTotals($branchIds);

        return response()->json([
            'success' => true,
            'role'    => 'SCHOOL_ADMIN',
            'data'    => [
                'total_branches'   => $branchIds->count(),
                'total_students'   => $totalStudents,
                'attendance_today' => $this->attendanceStat($attendanceMarked, $attendancePresent),
                'fee_collection'   => $this->feeStat($totalInvoiced, $totalPaid),
            ],
        ]);
    }

    // ─── Role 3: Branch Admin ─────────────────────────────────────────────────

    private function branchAdminOverview(Request $request): JsonResponse
    {
        $request->validate([
            'branch_id' => 'required|integer|exists:branches,id',
        ]);

        $today    = now()->toDateString();
        $branchId = (int) $request->branch_id;

        $totalStudents = Student::where('branch_id', $branchId)
            ->whereNotNull('currently_studying')
            ->count();

        // Attendance
        $todayRecords      = StudentAttendance::where('branch_id', $branchId)->where('date', $today)->get();
        $attendanceMarked  = $todayRecords->count();
        $attendancePresent = $todayRecords->where('status', 'P')->count();

        // Fee collection
        $invoiceIds  = Invoice::where('branch_id', $branchId)->pluck('id');
        $totalInvoiced = Invoice::where('branch_id', $branchId)->sum('total_amount');
        $totalPaid     = Payment::whereIn('invoice_id', $invoiceIds)->sum('amount_paid');

        // Last 5 admissions
        $recentAdmissions = Student::where('branch_id', $branchId)
            ->whereNotNull('currently_studying')
            ->latest()
            ->take(5)
            ->get(['id', 'name', 'admission_no', 'admission_date'])
            ->map(fn($s) => [
                'id'             => $s->id,
                'name'           => $s->name,
                'admission_no'   => $s->admission_no,
                'admission_date' => $s->admission_date?->format('Y-m-d'),
            ]);

        return response()->json([
            'success' => true,
            'role'    => 'BRANCH_ADMIN',
            'data'    => [
                'total_students'   => $totalStudents,
                'attendance_today' => $this->attendanceStat($attendanceMarked, $attendancePresent),
                'fee_collection'   => $this->feeStat($totalInvoiced, $totalPaid),
                'recent_admissions' => $recentAdmissions,
            ],
        ]);
    }

    // ─── Role 4: Teacher ──────────────────────────────────────────────────────

    private function teacherOverview($user): JsonResponse
    {
        $today    = now()->toDateString();
        $branchId = $user->branch_id;

        $subjects = LessonPlanSubject::where('user_id', $user->id)
            ->with('subject:id,name')
            ->get();

        $todayRecords      = $branchId
            ? StudentAttendance::where('branch_id', $branchId)->where('date', $today)->get()
            : collect();
        $attendanceMarked  = $todayRecords->count();
        $attendancePresent = $todayRecords->where('status', 'P')->count();

        return response()->json([
            'success' => true,
            'role'    => 'TEACHER',
            'data'    => [
                'total_subjects'   => $subjects->count(),
                'subjects'         => $subjects->map(fn($s) => [
                    'id'   => $s->subject_id,
                    'name' => $s->subject?->name,
                ]),
                'attendance_today' => $this->attendanceStat($attendanceMarked, $attendancePresent),
            ],
        ]);
    }

    // ─── Role 5: Parent ───────────────────────────────────────────────────────

    private function parentOverview($user): JsonResponse
    {
        $today         = now()->toDateString();
        $parentProfile = ParentProfile::where('user_id', $user->id)->first();

        if (! $parentProfile) {
            return response()->json([
                'success' => true,
                'role'    => 'PARENT',
                'data'    => ['total_children' => 0, 'children' => []],
            ]);
        }

        $children   = Student::where('parent_id', $parentProfile->id)
            ->whereNotNull('currently_studying')
            ->get(['id', 'name', 'admission_no', 'class_id', 'section_id']);

        $childIds   = $children->pluck('id');
        $invoices   = Invoice::whereIn('student_id', $childIds)->get(['id', 'student_id', 'total_amount']);
        $invoiceIds = $invoices->pluck('id');
        $payments   = Payment::whereIn('invoice_id', $invoiceIds)->get(['invoice_id', 'amount_paid']);

        // Map invoice totals and paid amounts per student
        $invoicedPerStudent = $invoices->groupBy('student_id')
            ->map(fn($group) => $group->sum('total_amount'));
        $paidPerStudent = $invoices->map(function ($inv) use ($payments) {
            return [
                'student_id' => $inv->student_id,
                'paid'       => $payments->where('invoice_id', $inv->id)->sum('amount_paid'),
            ];
        })->groupBy('student_id')->map(fn($g) => $g->sum('paid'));

        // Today's attendance per student (single query)
        $todayAttendance = StudentAttendance::whereIn('student_id', $childIds)
            ->where('date', $today)
            ->pluck('status', 'student_id');

        $childrenData = $children->map(function ($child) use ($invoicedPerStudent, $paidPerStudent, $todayAttendance) {
            $invoiced = (float) ($invoicedPerStudent[$child->id] ?? 0);
            $paid     = (float) ($paidPerStudent[$child->id] ?? 0);

            return [
                'id'               => $child->id,
                'name'             => $child->name,
                'admission_no'     => $child->admission_no,
                'attendance_today' => $todayAttendance[$child->id] ?? null,
                'fee_summary'      => [
                    'total_invoiced' => $invoiced,
                    'total_paid'     => $paid,
                    'pending'        => max(0, $invoiced - $paid),
                ],
            ];
        });

        return response()->json([
            'success' => true,
            'role'    => 'PARENT',
            'data'    => [
                'total_children' => $children->count(),
                'children'       => $childrenData,
            ],
        ]);
    }

    // ─── Shared helpers ───────────────────────────────────────────────────────

    private function attendanceTotals($branchIds, string $date): array
    {
        $q = StudentAttendance::where('date', $date);
        if ($branchIds !== null) {
            is_iterable($branchIds)
                ? $q->whereIn('branch_id', $branchIds)
                : $q->where('branch_id', $branchIds);
        }
        $records = $q->get(['status']);
        return [$records->count(), $records->where('status', 'P')->count()];
    }

    private function feeTotals($branchIds): array
    {
        $invoiceQ = Invoice::query();
        if ($branchIds !== null) {
            is_iterable($branchIds)
                ? $invoiceQ->whereIn('branch_id', $branchIds)
                : $invoiceQ->where('branch_id', $branchIds);
        }
        $invoiceIds = $invoiceQ->pluck('id');
        $totalInvoiced = $invoiceQ->sum('total_amount');
        $totalPaid     = Payment::whereIn('invoice_id', $invoiceIds)->sum('amount_paid');
        return [(float) $totalInvoiced, (float) $totalPaid];
    }

    private function attendanceStat(int $marked, int $present): array
    {
        return [
            'total_marked' => $marked,
            'present'      => $present,
            'percentage'   => $marked > 0 ? round(($present / $marked) * 100, 1) : 0,
        ];
    }

    private function feeStat(float $invoiced, float $paid): array
    {
        return [
            'total_invoiced'   => $invoiced,
            'total_collected'  => $paid,
            'total_pending'    => max(0, $invoiced - $paid),
            'percentage'       => $invoiced > 0 ? round(($paid / $invoiced) * 100, 1) : 0,
        ];
    }
}
