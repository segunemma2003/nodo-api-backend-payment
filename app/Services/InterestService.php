<?php

namespace App\Services;

use App\Models\Invoice;
use Carbon\Carbon;

class InterestService
{
    const MONTHLY_INTEREST_RATE = 0.035; // 3.5%
    const GRACE_PERIOD_MONTHS = 1; // 1 month grace period after due date
    const DAYS_PER_MONTH = 30; // Standard conversion: 30 days = 1 month

    /**
     * Convert days to months (for payment plan duration)
     * Uses 30 days per month for calculation purposes
     */
    public static function daysToMonths(int $days): float
    {
        return round($days / self::DAYS_PER_MONTH, 2);
    }

    /**
     * Calculate interest for an invoice
     * Interest is calculated per product/item, not on the total amount
     * Upfront interest: For each item: (item_price * quantity) * 3.5% * payment_plan_duration months
     * Plus overdue interest: 3.5% per month after grace period (calculated on principal_amount)
     * Note: payment_plan_duration is stored in months in the database
     */
    public function calculateInterest(Invoice $invoice): float
    {
        if (!$invoice->due_date) {
            return 0;
        }

        $paymentPlanDuration = $invoice->payment_plan_duration ?? 6;
        $principalAmount = $invoice->principal_amount;
        
        // Get items from invoice transaction metadata
        $items = $invoice->getItems();
        
        $upfrontInterest = 0;
        
        if (!empty($items) && is_array($items)) {
            // Calculate interest per product/item
            foreach ($items as $item) {
                $itemPrice = floatval($item['price'] ?? 0);
                $quantity = intval($item['quantity'] ?? 1);
                $itemTotal = $itemPrice * $quantity;
                
                // Interest per item: (item_price * quantity) * 3.5% * payment_plan_duration
                $itemInterest = $itemTotal * self::MONTHLY_INTEREST_RATE * $paymentPlanDuration;
                $upfrontInterest += $itemInterest;
            }
        } else {
            // Fallback: If no items found, calculate on total principal amount (backward compatibility)
        $upfrontInterest = $principalAmount * self::MONTHLY_INTEREST_RATE * $paymentPlanDuration;
        }
        
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
            
            // Overdue interest: Calculate per item if items exist, otherwise on total
            if (!empty($items) && is_array($items)) {
                foreach ($items as $item) {
                    $itemPrice = floatval($item['price'] ?? 0);
                    $quantity = intval($item['quantity'] ?? 1);
                    $itemTotal = $itemPrice * $quantity;
                    
                    // Overdue interest per item: (item_price * quantity) * 3.5% * months_overdue
                    $itemOverdueInterest = $itemTotal * self::MONTHLY_INTEREST_RATE * $monthsOverdue;
                    $overdueInterest += $itemOverdueInterest;
                }
            } else {
                // Fallback: Calculate on total principal amount
            $overdueInterest = $principalAmount * self::MONTHLY_INTEREST_RATE * $monthsOverdue;
            }
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








