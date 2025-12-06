<?php

namespace App\Services;

use App\Models\Invoice;
use Carbon\Carbon;

class InterestService
{
    const MONTHLY_INTEREST_RATE = 0.035; // 3.5%
    const GRACE_PERIOD_DAYS = 30;

    /**
     * Calculate interest for an invoice
     * Initial 3.5% interest is always added when invoice is created (regardless of due_date)
     * Additional 3.5% interest is added if grace period is exceeded (only if due_date exists)
     * Total: 3.5% (no due_date or within grace period) or 7% (after grace period)
     * 
     * Note: For paid invoices, if interest_amount is already set, use it. Otherwise calculate it.
     * This ensures interest is preserved for credit deduction even after invoice is paid.
     */
    public function calculateInterest(Invoice $invoice): float
    {
        // If invoice is paid but already has interest calculated, preserve it
        // This is needed for credit deduction (remaining_balance should include interest)
        if ($invoice->status === 'paid' && $invoice->interest_amount > 0) {
            return $invoice->interest_amount;
        }

        // If invoice is paid but interest is 0, we still need to calculate it for credit deduction
        // (This handles existing invoices that were created before interest calculation was fixed)
        // Base interest: 3.5% is always added (initial interest) - even without due_date
        $baseInterest = $invoice->principal_amount * self::MONTHLY_INTEREST_RATE;
        
        // Additional interest: 3.5% if grace period is exceeded (only if due_date exists)
        $additionalInterest = 0;
        if ($invoice->due_date) {
            $now = Carbon::now();
            $dueDate = Carbon::parse($invoice->due_date);
            $gracePeriodEnd = $invoice->grace_period_end_date 
                ? Carbon::parse($invoice->grace_period_end_date)
                : ($dueDate->copy()->addDays(self::GRACE_PERIOD_DAYS));

            // For paid invoices, check if grace period was exceeded at time of payment
            // For unpaid invoices, check current date
            $checkDate = $invoice->status === 'paid' && $invoice->paid_at 
                ? Carbon::parse($invoice->paid_at) 
                : $now;

            if ($checkDate->gt($gracePeriodEnd)) {
                $additionalInterest = $invoice->principal_amount * self::MONTHLY_INTEREST_RATE;
            }
        }

        $totalInterest = $baseInterest + $additionalInterest;

        return round($totalInterest, 2);
    }

    /**
     * Update invoice status based on dates and calculate interest
     * Interest (3.5% base) is always calculated, even without due_date
     * For paid invoices, we still update interest if it's 0 (for existing invoices)
     */
    public function updateInvoiceStatus(Invoice $invoice): void
    {
        // For paid invoices, only update if interest is 0 (to fix existing invoices)
        // Otherwise, preserve the existing interest
        if ($invoice->status === 'paid' && $invoice->interest_amount > 0) {
            // Ensure remaining_balance includes interest for credit deduction
            $invoice->remaining_balance = $invoice->total_amount - $invoice->paid_amount;
            $invoice->save();
            return;
        }

        // Calculate interest (3.5% always, + 3.5% if grace period exceeded)
        // Interest is calculated even if due_date is null
        $interest = $this->calculateInterest($invoice);
        $invoice->interest_amount = $interest;
        $invoice->total_amount = $invoice->principal_amount + $interest;
        $invoice->remaining_balance = $invoice->total_amount - $invoice->paid_amount;

        // Update status based on due_date (if exists)
        if (!$invoice->due_date) {
            // If no due_date, keep invoice as pending but interest is still calculated
            $invoice->status = 'pending';
        } else {
            $now = Carbon::now();
            $dueDate = Carbon::parse($invoice->due_date);
            $gracePeriodEnd = $invoice->grace_period_end_date 
                ? Carbon::parse($invoice->grace_period_end_date)
                : ($dueDate->copy()->addDays(self::GRACE_PERIOD_DAYS));

            if ($now->lt($dueDate)) {
                $invoice->status = 'pending';
            } elseif ($now->gte($dueDate) && $now->lte($gracePeriodEnd)) {
                // In grace period - base 3.5% interest applies
                $invoice->status = 'in_grace';
                $invoice->months_overdue = $now->diffInMonths($dueDate);
                if ($invoice->months_overdue < 1) {
                    $invoice->months_overdue = 1;
                }
            } else {
                // After grace period - overdue, additional 3.5% interest applies (total 7%)
                $invoice->status = 'overdue';
                $invoice->months_overdue = $now->diffInMonths($dueDate);
                if ($invoice->months_overdue < 1) {
                    $invoice->months_overdue = 1;
                }
            }
        }

        $invoice->save();
    }

    /**
     * Update all invoices status and interest
     * Includes paid invoices with pending credit repayment (to recalculate interest if needed)
     */
    public function updateAllInvoices(): void
    {
        // Get unpaid invoices
        $unpaidInvoices = Invoice::where('status', '!=', 'paid')->get();
        
        // Get paid invoices where credit is not fully repaid (need interest for credit deduction)
        $paidInvoicesWithPendingCredit = Invoice::where('status', 'paid')
            ->where(function ($query) {
                $query->whereNull('credit_repaid_status')
                      ->orWhere('credit_repaid_status', '!=', 'fully_paid');
            })
            ->get();
        
        // Update all invoices
        foreach ($unpaidInvoices as $invoice) {
            $this->updateInvoiceStatus($invoice);
        }
        
        foreach ($paidInvoicesWithPendingCredit as $invoice) {
            $this->updateInvoiceStatus($invoice);
        }
    }
}


