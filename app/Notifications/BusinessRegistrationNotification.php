<?php

namespace App\Notifications;

use App\Models\Business;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessRegistrationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $business;

    public function __construct(Business $business)
    {
        $this->business = $business;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('New Business Registration - ' . $this->business->business_name)
            ->greeting('Hello!')
            ->line('A new business has registered and is requesting approval.')
            ->line('**Business Details:**')
            ->line('**Business Name:** ' . $this->business->business_name)
            ->line('**Email:** ' . $this->business->email)
            ->line('**Username:** ' . $this->business->username)
            ->line('**Phone:** ' . ($this->business->phone ?? 'N/A'))
            ->line('**Address:** ' . ($this->business->address ?? 'N/A'))
            ->line('**Webhook URL:** ' . ($this->business->webhook_url ?? 'N/A'))
            ->line('**Approval Status:** ' . ucfirst($this->business->approval_status))
            ->line('**Status:** ' . ucfirst($this->business->status))
            ->line('Please review and approve this business registration in the admin panel.')
            ->action('View Business', url('/admin/businesses/' . $this->business->id))
            ->line('Thank you!');
    }
}
