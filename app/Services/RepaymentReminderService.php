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
        
        $invoices = Invoice::where('status', '!=', 'paid')
            ->where(function ($query) use ($today) {
                $query->where('due_date', '<=', $today->copy()->addDays(7))
                    ->orWhere('status', 'overdue');
            })
            ->with('customer')
            ->get();

        foreach ($invoices as $invoice) {
            $daysUntilDue = $today->diffInDays($invoice->due_date, false);
            $isOverdue = $invoice->status === 'overdue';
            
            if ($isOverdue || ($daysUntilDue <= 7 && $daysUntilDue >= 0)) {
                $invoice->customer->notify(new RepaymentReminderNotification($invoice));
            }
        }
    }
}

