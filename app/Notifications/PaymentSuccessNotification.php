<?php

namespace App\Notifications;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentSuccessNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $invoice;
    protected $amount;
    protected $type;

    public function __construct(Invoice $invoice, $amount, $type = 'credit_purchase')
    {
        $this->invoice = $invoice;
        $this->amount = $amount;
        $this->type = $type;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        if ($this->type === 'credit_purchase') {
            return $this->creditPurchaseMail($notifiable);
        } else {
            return $this->repaymentMail($notifiable);
        }
    }

    protected function creditPurchaseMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Payment Successful - Invoice ' . $this->invoice->invoice_id)
            ->greeting('Hello ' . $notifiable->business_name . '!')
            ->line('A payment has been successfully processed through Nodopay.')
            ->line('**Invoice ID:** ' . $this->invoice->invoice_id)
            ->line('**Amount:** ₦' . number_format($this->amount, 2))
            ->line('**Customer:** ' . $this->invoice->customer->business_name)
            ->line('**Due Date:** ' . $this->invoice->due_date->format('F d, Y'))
            ->line('Payment has been automatically transferred to your account.')
            ->action('View Invoice', url('/invoices/' . $this->invoice->invoice_id))
            ->line('Thank you for using Nodopay!');
    }

    protected function repaymentMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Payment Received - Invoice ' . $this->invoice->invoice_id)
            ->greeting('Hello ' . $notifiable->business_name . '!')
            ->line('We have received your payment.')
            ->line('**Invoice ID:** ' . $this->invoice->invoice_id)
            ->line('**Amount Paid:** ₦' . number_format($this->amount, 2))
            ->line('**Remaining Balance:** ₦' . number_format($this->invoice->remaining_balance, 2))
            ->line('**Due Date:** ' . $this->invoice->due_date->format('F d, Y'))
            ->action('View Invoice', url('/invoices/' . $this->invoice->invoice_id))
            ->line('Thank you for your payment!');
    }
}

