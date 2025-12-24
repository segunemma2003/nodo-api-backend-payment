<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $password;

    public function __construct($password)
    {
        $this->password = $password;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('Welcome to FSCredit - Your Account Details')
            ->greeting('Hello ' . $notifiable->business_name . '!')
            ->line('Your FSCredit account has been created successfully.')
            ->line('**Account Number:** ' . $notifiable->account_number)
            ->line('**Username:** ' . $notifiable->username)
            ->line('**Email:** ' . $notifiable->email)
            ->line('**Password:** ' . $this->password)
            ->line('**Credit Limit:** ₦' . number_format($notifiable->credit_limit, 2))
            ->line('Please keep your login credentials secure and change your password after first login.')
            ->action('Login to Dashboard', url('/login'))
            ->line('Thank you for choosing FSCredit!');
    }
}

