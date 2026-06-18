<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\PaymentItem;
use App\Models\Student;
use App\Models\ParentWallet;

class InvoiceController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['branch_id' => 'required|integer']);

        $invoices = Invoice::with(['items.student', 'student', 'parent'])
            ->where('branch_id', $request->branch_id)
            ->latest()
            ->get()
            ->map(function (Invoice $invoice) {
                $totalPaid = $invoice->payments()->sum('amount_paid');
                return array_merge($invoice->toArray(), [
                    'total_paid' => (float) $totalPaid,
                    'remaining'  => (float) max(0, $invoice->total_amount - $totalPaid),
                    'is_overdue' => $this->isOverdue($invoice),
                ]);
            });

        return response()->json(['success' => true, 'data' => $invoices]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'branch_id'     => 'required|integer',
            'issue_date'    => 'required|date',
            'due_date'      => 'nullable|date|after_or_equal:issue_date',
            'student_ids'   => 'required|array|min:1',
            'student_ids.*' => 'exists:students,id',
        ]);

        $students = Student::with('feeHeads')
            ->whereIn('id', $request->student_ids)
            ->get();

        if ($students->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'No students found.'], 404);
        }

        $parentIds = $students->pluck('parent_id')->filter()->unique();

        if ($parentIds->count() > 1) {
            return response()->json([
                'success' => false,
                'message' => 'Selected students belong to different parents. Please select students from the same family.',
            ], 422);
        }

        $parentId  = $parentIds->first();
        $studentId = $students->count() === 1 ? $students->first()->id : null;

        // Collision-safe invoice number: date prefix + microsecond-level unique suffix
        $invoiceNo = 'INV-' . now()->format('ymd') . '-' . strtoupper(substr(uniqid(), -5));

        $invoice = Invoice::create([
            'invoice_no'   => $invoiceNo,
            'branch_id'    => $request->branch_id,
            'parent_id'    => $parentId,
            'student_id'   => $studentId,
            'issue_date'   => $request->issue_date,
            'due_date'     => $request->due_date,
            'status'       => 'unpaid',
            'total_amount' => 0,
        ]);

        $total = 0;

        // Step 1 – add current fee heads for each student
        foreach ($students as $student) {
            foreach ($student->feeHeads as $head) {
                $this->upsertItem(
                    $invoice,
                    $student->id,
                    $head->head_name,
                    (float) $head->head_amount,
                    $total,
                    $head->head_frequency ?? 'Monthly'
                );
            }
        }

        // Step 2 – carry forward unpaid balances from previous invoices
        $previousInvoices = Invoice::where('parent_id', $parentId)
            ->whereIn('status', ['unpaid', 'partial', 'carried_forward'])
            ->where('id', '!=', $invoice->id)
            ->with('items')
            ->get();

        foreach ($previousInvoices as $prevInvoice) {
            foreach ($prevInvoice->items as $item) {
                $frequency = $item->head_frequency ?? 'Monthly';

                // One-time fees never carry forward
                if ($frequency === 'One Time') {
                    continue;
                }

                // Actual payments made directly to this item via PaymentItems
                $directPaid = (float) PaymentItem::where('invoice_item_id', $item->id)->sum('paid_amount');
                $remaining  = max(0, (float) $item->amount - $directPaid);

                if ($remaining <= 0) {
                    continue;
                }

                if ($frequency === 'Annual') {
                    // Annual fees only carry within the same academic year
                    if ($this->getAcademicYear(now()) !== $this->getAcademicYear($prevInvoice->created_at)) {
                        continue;
                    }

                    // If this annual fee already exists in the new invoice (added from current fee heads),
                    // just record how much was previously paid as an audit snapshot
                    $existing = $invoice->items()
                        ->where('student_id', $item->student_id)
                        ->where('head_name', $item->head_name)
                        ->where('head_frequency', $frequency)
                        ->first();

                    if ($existing) {
                        if ($directPaid > 0) {
                            $existing->increment('previous_paid', $directPaid);
                        }
                        continue;
                    }
                }

                // Monthly / Annual (not yet in new invoice) – carry the remaining amount
                $this->upsertItem(
                    $invoice,
                    $item->student_id,
                    $item->head_name,
                    $remaining,
                    $total,
                    $frequency,
                    $directPaid,   // snapshot of what was paid before carry
                    $item->id
                );
            }

            // Mark old invoice as carried forward
            $prevInvoice->update(['status' => 'carried_forward']);
        }

        $invoice->update(['total_amount' => $total]);

        return response()->json([
            'success' => true,
            'message' => 'Invoice generated successfully.',
            'data'    => $invoice->load(['items.student', 'parent']),
        ]);
    }

    public function show(int $id)
    {
        $invoice = Invoice::with(['items.student', 'parent', 'payments'])->findOrFail($id);

        $items = $invoice->items->map(function (InvoiceItem $item) {
            // Direct payments made to this specific item
            $paid      = (float) PaymentItem::where('invoice_item_id', $item->id)->sum('paid_amount');
            $remaining = (float) max(0, $item->amount - $paid);

            $carriedFrom = null;
            if ($item->carried_from_item_id) {
                $origin = InvoiceItem::with('invoice:id,invoice_no')->find($item->carried_from_item_id);
                if ($origin) {
                    $carriedFrom = [
                        'invoice_id'      => $origin->invoice_id,
                        'invoice_no'      => $origin->invoice->invoice_no ?? 'N/A',
                        'original_amount' => (float) ($origin->amount + ($origin->previous_paid ?? 0)),
                    ];
                }
            }

            return [
                'id'             => $item->id,
                'student_id'     => $item->student_id,
                'student'        => $item->student,
                'head_name'      => $item->head_name,
                'head_frequency' => $item->head_frequency,
                'amount'         => (float) $item->amount,
                'paid'           => $paid,
                'previous_paid'  => (float) ($item->previous_paid ?? 0),
                'remaining'      => $remaining,
                'carried_from'   => $carriedFrom,
            ];
        });

        $totalPaid = (float) $invoice->payments->sum('amount_paid');
        $wallet    = ParentWallet::where('parent_id', $invoice->parent_id)->first();

        return response()->json([
            'id'             => $invoice->id,
            'invoice_no'     => $invoice->invoice_no,
            'branch_id'      => $invoice->branch_id,
            'issue_date'     => $invoice->issue_date,
            'due_date'       => $invoice->due_date,
            'is_overdue'     => $this->isOverdue($invoice),
            'parent'         => $invoice->parent,
            'status'         => $invoice->status,
            'total_amount'   => (float) $invoice->total_amount,
            'total_paid'     => $totalPaid,
            'remaining'      => (float) max(0, $invoice->total_amount - $totalPaid),
            'items'          => $items,
            'payments'       => $invoice->payments->map(fn ($p) => [
                'id'             => $p->id,
                'amount_paid'    => (float) $p->amount_paid,
                'wallet_used'    => (float) $p->wallet_used,
                'payment_method' => $p->payment_method,
                'bank_name'      => $p->bank_name,
                'reference_no'   => $p->reference_no,
                'payment_date'   => $p->payment_date,
                'created_at'     => $p->created_at?->toISOString(),
            ]),
            'wallet_balance' => (float) ($wallet?->balance ?? 0),
        ]);
    }

    public function destroy(int $id)
    {
        $invoice = Invoice::findOrFail($id);

        if (in_array($invoice->status, ['paid', 'partial'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a paid or partially paid invoice. Please contact the administrator.',
            ], 422);
        }

        $invoice->delete();

        return response()->json(['success' => true, 'message' => 'Invoice deleted successfully.']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create or merge an invoice line item.
     * Items are keyed by (student_id, head_name, head_frequency).
     */
    private function upsertItem(
        Invoice $invoice,
        ?int    $studentId,
        string  $headName,
        float   $amount,
        float   &$total,
        string  $frequency,
        float   $previousPaid    = 0,
        ?int    $carriedFromId   = null
    ): void {
        $existing = $invoice->items()
            ->where('student_id', $studentId)
            ->where('head_name', $headName)
            ->where('head_frequency', $frequency)
            ->first();

        if ($existing) {
            $existing->increment('amount', $amount);
            if ($previousPaid > 0) {
                $existing->increment('previous_paid', $previousPaid);
            }
        } else {
            $invoice->items()->create([
                'student_id'           => $studentId,
                'head_name'            => $headName,
                'amount'               => $amount,
                'head_frequency'       => $frequency,
                'previous_paid'        => $previousPaid,
                'carried_from_item_id' => $carriedFromId,
            ]);
        }

        $total += $amount;
    }

    /**
     * Academic year string, e.g. "2025-26".
     * Year runs March → February.
     */
    private function getAcademicYear($date): string
    {
        $year  = (int) $date->year;
        $month = (int) $date->month;

        if ($month <= 2) {
            return ($year - 1) . '-' . substr((string) $year, 2);
        }

        return $year . '-' . substr((string) ($year + 1), 2);
    }

    private function isOverdue(Invoice $invoice): bool
    {
        return $invoice->due_date !== null
            && $invoice->due_date < now()->toDateString()
            && $invoice->status !== 'paid';
    }
}
