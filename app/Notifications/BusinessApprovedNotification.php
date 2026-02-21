<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\Middleware\RateLimited;

class BusinessApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

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
            ->subject('Your FSCredit Business Account Has Been Approved')
            ->greeting('Hello ' . $notifiable->business_name . '!')
            ->line('Congratulations! Your FSCredit business account has been approved.')
            ->line('**API Token:** ' . $notifiable->api_token)
            ->line('You can now integrate FSCredit payment gateway into your platform.')
            ->line('Use your API token to authenticate requests to the FSCredit API.')
            ->action('Login to Dashboard', url('/login'))
            ->line('Thank you for choosing FSCredit!');
    }
}

