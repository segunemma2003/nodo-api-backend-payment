<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RepaymentReminderNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        $daysUntilDue = now()->diffInDays($this->invoice->due_date, false);
        $isOverdue = $this->invoice->status === 'overdue';

        $mail = (new MailMessage)
            ->subject($isOverdue ? 'Overdue Payment Reminder - Invoice ' . $this->invoice->invoice_id : 'Payment Reminder - Invoice ' . $this->invoice->invoice_id)
            ->greeting('Hello ' . $notifiable->business_name . '!');

        if ($isOverdue) {
            $mail->line('Your invoice payment is overdue.')
                ->line('**Invoice ID:** ' . $this->invoice->invoice_id)
                ->line('**Amount Due:** ₦' . number_format($this->invoice->remaining_balance, 2))
                ->line('**Due Date:** ' . $this->invoice->due_date->format('F d, Y'))
                ->line('**Interest Accrued:** ₦' . number_format($this->invoice->interest_amount, 2))
                ->line('**Total Amount:** ₦' . number_format($this->invoice->total_amount, 2))
                ->line('Please make payment as soon as possible to avoid additional interest charges.');
        } else {
            $mail->line('This is a reminder that you have an upcoming payment.')
                ->line('**Invoice ID:** ' . $this->invoice->invoice_id)
                ->line('**Amount Due:** ₦' . number_format($this->invoice->remaining_balance, 2))
                ->line('**Due Date:** ' . $this->invoice->due_date->format('F d, Y'))
                ->line('**Days Until Due:** ' . abs($daysUntilDue) . ' days')
                ->line('Please ensure payment is made before the due date.');
        }

        $mail->line('**Virtual Account Number:** ' . $notifiable->virtual_account_number)
            ->line('**Bank:** ' . $notifiable->virtual_account_bank)
            ->action('View Invoice', url('/invoices/' . $this->invoice->invoice_id))
            ->line('Thank you for your attention to this matter.');

        return $mail;
    }
}

