<?php

namespace App\Notifications;

use App\Models\BusinessCustomer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Middleware\RateLimited;

class BusinessCustomerCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $businessCustomer;
    protected $business;

    public function __construct(BusinessCustomer $businessCustomer, $business)
    {
        $this->businessCustomer = $businessCustomer;
        $this->business = $business;
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
            ->subject('New Business Customer Created - ' . $this->businessCustomer->business_name)
            ->greeting('Hello!')
            ->line('A new business customer has been created.')
            ->line('**Business Customer Details:**')
            ->line('**Business Name:** ' . $this->businessCustomer->business_name)
            ->line('**Contact Name:** ' . ($this->businessCustomer->contact_name ?? 'N/A'))
            ->line('**Contact Email:** ' . ($this->businessCustomer->contact_email ?? 'N/A'))
            ->line('**Contact Phone:** ' . ($this->businessCustomer->contact_phone ?? 'N/A'))
            ->line('**Address:** ' . ($this->businessCustomer->address ?? 'N/A'))
            ->line('**Created By Business:** ' . $this->business->business_name)
            ->line('**Business Email:** ' . $this->business->email)
            ->line('This customer can now receive invoices for credit purchases.')
            ->line('Thank you!');
    }
}
