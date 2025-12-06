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
     * Interest (3.5% monthly) starts immediately after due date
     * Grace period is for additional penalties, not to avoid interest
     */
    public function calculateInterest(Invoice $invoice): float
    {
        if ($invoice->status === 'paid' || !$invoice->due_date) {
            return 0;
        }

        $now = Carbon::now();
        $dueDate = Carbon::parse($invoice->due_date);

        // No interest before due date
        if ($now->lte($dueDate)) {
            return 0;
        }

        // Calculate months overdue from due date (not grace period end)
        $monthsOverdue = $now->diffInMonths($dueDate);
        if ($monthsOverdue < 1) {
            $monthsOverdue = 1; // Minimum 1 month if overdue by any amount
        }

        // Calculate interest on remaining balance
        // Interest starts immediately after due date at 3.5% per month
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
            // In grace period - interest starts immediately after due date
            $invoice->status = 'in_grace';
            $invoice->months_overdue = $now->diffInMonths($dueDate);
            if ($invoice->months_overdue < 1) {
                $invoice->months_overdue = 1;
            }
            
            // Calculate and update interest (starts immediately after due date)
            $interest = $this->calculateInterest($invoice);
            $invoice->interest_amount = $interest;
            $invoice->total_amount = $invoice->principal_amount + $interest;
            $invoice->remaining_balance = $invoice->total_amount - $invoice->paid_amount;
        } else {
            // After grace period - overdue with interest + additional penalties can apply
            $invoice->status = 'overdue';
            $invoice->months_overdue = $now->diffInMonths($dueDate);
            if ($invoice->months_overdue < 1) {
                $invoice->months_overdue = 1;
            }
            
            // Calculate and update interest (continues from due date)
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


