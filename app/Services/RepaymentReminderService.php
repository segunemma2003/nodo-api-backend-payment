<?php

namespace App\Services;

use App\Models\Invoice;
use App\Notifications\RepaymentReminderNotification;
use Carbon\Carbon;

class RepaymentReminderService
{
    public function sendReminders(): void
    {
        $today = Carbon::today();
        $oneWeekFromToday = $today->copy()->addWeek();
        
        // Find invoices with due_date exactly 7 days from today (1 week before due date)
        $invoices = Invoice::where('status', '!=', 'paid')
            ->whereNotNull('due_date')
            ->whereDate('due_date', $oneWeekFromToday->format('Y-m-d'))
            ->whereNotNull('customer_id')
            ->with('customer')
            ->get();

        foreach ($invoices as $invoice) {
            // Only send reminder if customer exists
            if ($invoice->customer) {
                $invoice->customer->notify(new RepaymentReminderNotification($invoice));
            }
        }
    }
}
