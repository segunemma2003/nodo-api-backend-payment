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
     * Initial 3.5% interest is always added when invoice is created
     * Additional 3.5% interest is added if grace period is exceeded
     * Total: 3.5% (within grace period) or 7% (after grace period)
     */
    public function calculateInterest(Invoice $invoice): float
    {
        if ($invoice->status === 'paid' || !$invoice->due_date) {
            return 0;
        }

        $now = Carbon::now();
        $dueDate = Carbon::parse($invoice->due_date);
        $gracePeriodEnd = $invoice->grace_period_end_date 
            ? Carbon::parse($invoice->grace_period_end_date)
            : ($dueDate->copy()->addDays(self::GRACE_PERIOD_DAYS));

        // Base interest: 3.5% is always added (initial interest)
        $baseInterest = $invoice->principal_amount * self::MONTHLY_INTEREST_RATE;
        
        // Additional interest: 3.5% if grace period is exceeded
        $additionalInterest = 0;
        if ($now->gt($gracePeriodEnd)) {
            $additionalInterest = $invoice->principal_amount * self::MONTHLY_INTEREST_RATE;
        }

        $totalInterest = $baseInterest + $additionalInterest;

        return round($totalInterest, 2);
    }

    /**
     * Update invoice status based on dates
     */
    public function updateInvoiceStatus(Invoice $invoice): void
    {
        if ($invoice->status === 'paid') {
            return;
        }

        // If no due_date, keep invoice as pending
        if (!$invoice->due_date) {
            $invoice->status = 'pending';
            $invoice->save();
            return;
        }

        $now = Carbon::now();
        $dueDate = Carbon::parse($invoice->due_date);
        $gracePeriodEnd = $invoice->grace_period_end_date 
            ? Carbon::parse($invoice->grace_period_end_date)
            : ($dueDate->copy()->addDays(self::GRACE_PERIOD_DAYS));

        // Calculate interest (3.5% always, + 3.5% if grace period exceeded)
        $interest = $this->calculateInterest($invoice);
        $invoice->interest_amount = $interest;
        $invoice->total_amount = $invoice->principal_amount + $interest;
        $invoice->remaining_balance = $invoice->total_amount - $invoice->paid_amount;

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

        $invoice->save();
    }

    /**
     * Update all invoices status and interest
     */
    public function updateAllInvoices(): void
    {
        $invoices = Invoice::where('status', '!=', 'paid')->get();
        
        foreach ($invoices as $invoice) {
            $this->updateInvoiceStatus($invoice);
        }
    }
}


