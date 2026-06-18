<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\PaymentItem;
use App\Models\ParentWallet;

class PaymentController extends Controller
{
    public function pay(Request $request)
    {
        $request->validate([
            'invoice_id'              => 'required|exists:invoices,id',
            'payment_method'          => 'required|string|in:cash,bank_transfer,cheque',
            'bank_name'               => 'nullable|string|max:255',
            'reference_no'            => 'nullable|string|max:255',
            'payment_date'            => 'nullable|date',
            'use_wallet'              => 'nullable|boolean',
            'items'                   => 'required|array|min:1',
            'items.*.invoice_item_id' => 'required|exists:invoice_items,id',
            'items.*.amount'          => 'required|numeric|min:0',
        ]);

        return DB::transaction(function () use ($request) {

            // Lock the invoice row to prevent concurrent payment races
            $invoice  = Invoice::with('items')->lockForUpdate()->findOrFail($request->invoice_id);
            $parentId = $invoice->parent_id;

            $wallet = ParentWallet::firstOrCreate(
                ['parent_id' => $parentId],
                ['balance'   => 0]
            );

            // Create payment stub – amount will be updated at end
            $payment = Payment::create([
                'invoice_id'            => $invoice->id,
                'parent_id'             => $parentId,
                'payment_method'        => $request->payment_method,
                'bank_name'             => $request->bank_name,
                'reference_no'          => $request->reference_no,
                'payment_date'          => $request->payment_date ?? now()->toDateString(),
                'amount_paid'           => 0,
                'wallet_used'           => 0,
                'extra_added_to_wallet' => 0,
            ]);

            $totalItemsPaid = 0.0;

            foreach ($request->items as $itemData) {
                $item      = $invoice->items->firstWhere('id', $itemData['invoice_item_id']);
                $payAmount = (float) $itemData['amount'];

                if (!$item) {
                    return response()->json([
                        'success' => false,
                        'message' => "Invoice item #{$itemData['invoice_item_id']} does not belong to this invoice.",
                    ], 422);
                }

                if ($payAmount <= 0) {
                    continue;
                }

                // Remaining = item.amount (already just the unpaid carry portion) minus prior payments on this item.
                // previous_paid is a historical snapshot only – it is NOT subtracted here.
                $alreadyPaid = (float) PaymentItem::where('invoice_item_id', $item->id)->sum('paid_amount');
                $remaining   = max(0.0, (float) $item->amount - $alreadyPaid);

                // Clamp silently to remaining (prevent overpayment per item)
                if ($payAmount > $remaining + 0.01) {
                    return response()->json([
                        'success' => false,
                        'message' => "Overpayment on '{$item->head_name}'. Remaining: Rs. {$remaining}, attempted: Rs. {$payAmount}.",
                    ], 422);
                }

                $payAmount = min($payAmount, $remaining);

                $payment->items()->create([
                    'invoice_item_id' => $item->id,
                    'paid_amount'     => $payAmount,
                ]);

                $totalItemsPaid += $payAmount;
            }

            // Apply wallet balance if requested
            $walletUsed = 0.0;

            if ($request->boolean('use_wallet') && $wallet->balance > 0) {
                // How much of the invoice is still outstanding after items paid this session
                $previousPaymentsTotal = (float) $invoice->payments()
                    ->where('id', '!=', $payment->id)
                    ->sum('amount_paid');

                $invoiceRemaining = max(
                    0.0,
                    (float) $invoice->total_amount - $previousPaymentsTotal - $totalItemsPaid
                );

                if ($invoiceRemaining > 0) {
                    $walletUsed = min((float) $wallet->balance, $invoiceRemaining);
                    $wallet->decrement('balance', $walletUsed);
                }
            }

            $sessionTotal = $totalItemsPaid + $walletUsed;

            // Finalise the payment record
            $payment->update([
                'amount_paid'           => $sessionTotal,
                'wallet_used'           => $walletUsed,
                'extra_added_to_wallet' => 0,
            ]);

            // Update invoice status based on cumulative payments
            $totalPaidOnInvoice = (float) $invoice->payments()->sum('amount_paid');

            if ($totalPaidOnInvoice >= (float) $invoice->total_amount - 0.01) {
                $invoice->update(['status' => 'paid']);
            } elseif ($totalPaidOnInvoice > 0) {
                $invoice->update(['status' => 'partial']);
            }

            // Reconcile original carried-forward invoices
            $this->reconcileCarriedInvoices($invoice);

            $wallet->refresh();

            return response()->json([
                'success'        => true,
                'message'        => 'Payment recorded successfully.',
                'amount_paid'    => $sessionTotal,
                'wallet_used'    => $walletUsed,
                'wallet_balance' => (float) $wallet->balance,
                'invoice_status' => $invoice->fresh()->status,
            ]);
        });
    }

    public function wallet(int $parentId)
    {
        $wallet = ParentWallet::firstOrCreate(
            ['parent_id' => $parentId],
            ['balance'   => 0]
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'parent_id' => $parentId,
                'balance'   => (float) $wallet->balance,
            ],
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * After a payment is recorded, check whether any original invoice that was
     * previously carried forward can now be marked as paid or partial.
     *
     * Approach (no double-counting):
     *   totalForItem = PaymentItems on original item
     *                + PaymentItems on all carry items that reference original item
     */
    private function reconcileCarriedInvoices(Invoice $currentInvoice): void
    {
        $carriedFromIds = $currentInvoice->items()
            ->whereNotNull('carried_from_item_id')
            ->pluck('carried_from_item_id');

        if ($carriedFromIds->isEmpty()) {
            return;
        }

        $originalInvoiceIds = InvoiceItem::whereIn('id', $carriedFromIds)
            ->pluck('invoice_id')
            ->unique();

        foreach ($originalInvoiceIds as $invoiceId) {
            $originalInvoice = Invoice::with('items')->find($invoiceId);

            if (!$originalInvoice || $originalInvoice->status === 'paid') {
                continue;
            }

            $allSettled = true;
            $anyPaid    = false;

            foreach ($originalInvoice->items as $item) {
                // Direct payments to the original item
                $directPaid = (float) PaymentItem::where('invoice_item_id', $item->id)->sum('paid_amount');

                // Payments made via any carry item that references this original item
                $carryIds     = InvoiceItem::where('carried_from_item_id', $item->id)->pluck('id');
                $paidViaCarry = $carryIds->isNotEmpty()
                    ? (float) PaymentItem::whereIn('invoice_item_id', $carryIds)->sum('paid_amount')
                    : 0.0;

                $totalForItem = $directPaid + $paidViaCarry;

                if ($totalForItem < (float) $item->amount - 0.01) {
                    $allSettled = false;
                }

                if ($totalForItem > 0) {
                    $anyPaid = true;
                }
            }

            if ($allSettled) {
                $originalInvoice->update(['status' => 'paid']);
            } elseif ($anyPaid && $originalInvoice->status !== 'partial') {
                $originalInvoice->update(['status' => 'partial']);
            }
        }
    }
}
