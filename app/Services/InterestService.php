<?php

namespace App\Services;

use App\Models\Invoice;
use Carbon\Carbon;

class InterestService
{
    const MONTHLY_INTEREST_RATE = 0.035; // 3.5%
    const GRACE_PERIOD_DAYS = 30;

    public function calculateInterest(Invoice $invoice): float
    {
        if ($invoice->status === 'paid' && $invoice->interest_amount > 0) {
            return $invoice->interest_amount;
        }

        $baseInterest = $invoice->principal_amount * self::MONTHLY_INTEREST_RATE;
        
        $additionalInterest = 0;
        if ($invoice->due_date) {
            $now = Carbon::now();
            $dueDate = Carbon::parse($invoice->due_date);
            $gracePeriodEnd = $invoice->grace_period_end_date 
                ? Carbon::parse($invoice->grace_period_end_date)
                : ($dueDate->copy()->addDays(self::GRACE_PERIOD_DAYS));

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

    public function updateInvoiceStatus(Invoice $invoice): void
    {
        if ($invoice->status === 'paid') {
            if ($invoice->interest_amount == 0) {
                $interest = $this->calculateInterest($invoice);
                $invoice->interest_amount = $interest;
                $invoice->total_amount = $invoice->principal_amount + $interest;
                $invoice->save();
            }
            return;
        }

        $interest = $this->calculateInterest($invoice);
        $invoice->interest_amount = $interest;
        $invoice->total_amount = $invoice->principal_amount + $interest;
        $invoice->remaining_balance = $invoice->total_amount - $invoice->paid_amount;

        if (!$invoice->due_date) {
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
                $invoice->status = 'in_grace';
                $invoice->months_overdue = $now->diffInMonths($dueDate);
                if ($invoice->months_overdue < 1) {
                    $invoice->months_overdue = 1;
                }
            } else {
                $invoice->status = 'overdue';
                $invoice->months_overdue = $now->diffInMonths($dueDate);
                if ($invoice->months_overdue < 1) {
                    $invoice->months_overdue = 1;
                }
            }
        }

        $invoice->save();
    }

    public function updateAllInvoices(): void
    {
        $unpaidInvoices = Invoice::where('status', '!=', 'paid')->get();
        
        foreach ($unpaidInvoices as $invoice) {
            $this->updateInvoiceStatus($invoice);
        }
        
        $paidInvoicesWithMissingInterest = Invoice::where('status', 'paid')
            ->where('interest_amount', 0)
            ->where(function ($query) {
                $query->whereNull('credit_repaid_status')
                      ->orWhere('credit_repaid_status', '!=', 'fully_paid');
            })
            ->get();
        
        foreach ($paidInvoicesWithMissingInterest as $invoice) {
            $interest = $this->calculateInterest($invoice);
            if ($interest > 0) {
                $invoice->interest_amount = $interest;
                $invoice->total_amount = $invoice->principal_amount + $interest;
                $invoice->save();
            }
        }
    }
}


