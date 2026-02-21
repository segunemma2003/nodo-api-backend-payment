<?php

namespace App\Notifications;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Middleware\RateLimited;

class CustomerRegistrationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $customer;

    public function __construct(Customer $customer)
    {
        $this->customer = $customer;
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware()
    {
        return [new RateLimited('emails')];
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('New Customer Registration - ' . $this->customer->business_name)
            ->greeting('Hello!')
            ->line('A new customer has registered and is requesting credit approval.')
            ->line('**Customer Details:**')
            ->line('**Business Name:** ' . $this->customer->business_name)
            ->line('**Account Number:** ' . $this->customer->account_number)
            ->line('**Email:** ' . $this->customer->email)
            ->line('**Username:** ' . $this->customer->username)
            ->line('**Phone:** ' . ($this->customer->phone ?? 'N/A'))
            ->line('**Address:** ' . ($this->customer->address ?? 'N/A'))
            ->line('**Minimum Purchase Amount:** ₦' . number_format($this->customer->minimum_purchase_amount, 2))
            ->line('**Payment Plan Duration:** ' . $this->customer->payment_plan_duration . ' months')
            ->line('**Approval Status:** ' . ucfirst($this->customer->approval_status))
            ->line('**Status:** ' . ucfirst($this->customer->status))
            ->line('Please review and approve this customer registration in the admin panel.')
            ->action('View Customer', url('/admin/customers/' . $this->customer->id))
            ->line('Thank you!');
    }
}
