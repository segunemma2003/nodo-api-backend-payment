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
     */
    public function calculateInterest(Invoice $invoice): float
    {
        if ($invoice->status === 'paid' || !$invoice->due_date) {
            return 0;
        }

        $now = Carbon::now();
        $gracePeriodEnd = $invoice->grace_period_end_date 
            ? Carbon::parse($invoice->grace_period_end_date)
            : (Carbon::parse($invoice->due_date)->addDays(self::GRACE_PERIOD_DAYS));

        // No interest during grace period
        if ($now->lte($gracePeriodEnd)) {
            return 0;
        }

        // Calculate months overdue after grace period
        $monthsOverdue = $now->diffInMonths($gracePeriodEnd);
        if ($monthsOverdue < 1) {
            $monthsOverdue = 1; // Minimum 1 month
        }

        // Calculate interest on remaining balance
        $interest = $invoice->remaining_balance * self::MONTHLY_INTEREST_RATE * $monthsOverdue;

        return round($interest, 2);
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

        if ($now->lt($dueDate)) {
            $invoice->status = 'pending';
        } elseif ($now->gte($dueDate) && $now->lte($gracePeriodEnd)) {
            $invoice->status = 'in_grace';
        } else {
            $invoice->status = 'overdue';
            $invoice->months_overdue = $now->diffInMonths($gracePeriodEnd);
            
            // Calculate and update interest
            $interest = $this->calculateInterest($invoice);
            $invoice->interest_amount = $interest;
            $invoice->total_amount = $invoice->principal_amount + $interest;
            $invoice->remaining_balance = $invoice->total_amount - $invoice->paid_amount;
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


