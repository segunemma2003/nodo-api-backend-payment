<?php

namespace App\Services;

use App\Models\Invoice;
use Carbon\Carbon;

class InterestService
{
    const MONTHLY_INTEREST_RATE = 0.035; // 3.5%
    const GRACE_PERIOD_MONTHS = 1; // 1 month grace period after due date

    /**
     * Calculate interest for an invoice
     * Upfront interest: 3.5% * payment_plan_duration months * principal_amount
     * Plus overdue interest: 3.5% per month after grace period
     */
    public function calculateInterest(Invoice $invoice): float
    {
        if (!$invoice->due_date) {
            return 0;
        }

        $paymentPlanDuration = $invoice->payment_plan_duration ?? 6;
        $principalAmount = $invoice->principal_amount;
        
        // Upfront interest: 3.5% * number of months * principal amount
        $upfrontInterest = $principalAmount * self::MONTHLY_INTEREST_RATE * $paymentPlanDuration;
        
        // Check if invoice is overdue (past grace period)
        $now = Carbon::now();
        $dueDate = Carbon::parse($invoice->due_date);
        $gracePeriodEnd = $invoice->grace_period_end_date 
            ? Carbon::parse($invoice->grace_period_end_date)
            : $dueDate->copy()->addMonth(); // 1 month grace period

        $overdueInterest = 0;
        if ($now->gt($gracePeriodEnd)) {
            // Calculate months overdue (after grace period)
            $monthsOverdue = $now->diffInMonths($gracePeriodEnd);
            if ($monthsOverdue < 1) {
                $monthsOverdue = 1;
            }
            
            // Add 3.5% per month for each month overdue
            $overdueInterest = $principalAmount * self::MONTHLY_INTEREST_RATE * $monthsOverdue;
        }

        $totalInterest = $upfrontInterest + $overdueInterest;
        return round($totalInterest, 2);
    }

    public function updateInvoiceStatus(Invoice $invoice): void
    {
        if ($invoice->status === 'paid') {
            return; // Don't update status or interest for paid invoices
        }

        if (!$invoice->due_date) {
            $invoice->status = 'pending';
            $invoice->save();
            return;
        }

        $now = Carbon::now();
        $dueDate = Carbon::parse($invoice->due_date);
        $gracePeriodEnd = $invoice->grace_period_end_date 
            ? Carbon::parse($invoice->grace_period_end_date)
            : $dueDate->copy()->addMonth(); // 1 month grace period

        // Ensure grace_period_end_date is set
        if (!$invoice->grace_period_end_date) {
            $invoice->grace_period_end_date = $gracePeriodEnd;
        }

        // Calculate and update interest
        $interest = $this->calculateInterest($invoice);
        $invoice->interest_amount = $interest;
        $invoice->total_amount = $invoice->principal_amount + $interest;
        $invoice->remaining_balance = $invoice->total_amount - $invoice->paid_amount;

        // Update status based on dates
        if ($now->lt($dueDate)) {
            $invoice->status = 'pending';
            $invoice->months_overdue = 0;
        } elseif ($now->gte($dueDate) && $now->lte($gracePeriodEnd)) {
            $invoice->status = 'in_grace';
            $invoice->months_overdue = 0;
        } else {
            // Past grace period - overdue
            $invoice->status = 'overdue';
            $monthsOverdue = $now->diffInMonths($gracePeriodEnd);
            $invoice->months_overdue = $monthsOverdue < 1 ? 1 : $monthsOverdue;
        }

        $invoice->save();
    }

    public function updateAllInvoices(): void
    {
        $unpaidInvoices = Invoice::where('status', '!=', 'paid')->get();
        
        foreach ($unpaidInvoices as $invoice) {
            $this->updateInvoiceStatus($invoice);
        }
    }
}

