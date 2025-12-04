<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Your Nodopay Business Account Has Been Approved')
            ->greeting('Hello ' . $notifiable->business_name . '!')
            ->line('Congratulations! Your Nodopay business account has been approved.')
            ->line('**API Token:** ' . $notifiable->api_token)
            ->line('You can now integrate Nodopay payment gateway into your platform.')
            ->line('Use your API token to authenticate requests to the Nodopay API.')
            ->action('Login to Dashboard', url('/login'))
            ->line('Thank you for choosing Nodopay!');
    }
}

