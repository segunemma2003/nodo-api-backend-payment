<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BusinessCreatedNotification extends Notification implements ShouldQueue
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
        $mail = (new MailMessage)
            ->subject('Welcome to FSCredit - Your Business Account Details')
            ->greeting('Hello ' . $notifiable->business_name . '!')
            ->line('Your FSCredit business account has been created successfully.')
            ->line('**Username:** ' . $notifiable->username)
            ->line('**Email:** ' . $notifiable->email);

        if ($this->password) {
            $mail->line('**Password:** ' . $this->password);
        }

        if ($notifiable->approval_status === 'pending') {
            $mail->line('Your account is pending approval. You will be notified once it is approved.');
        } elseif ($notifiable->approval_status === 'approved') {
            $mail->line('**API Token:** ' . $notifiable->api_token)
                ->line('You can now integrate FSCredit payment gateway into your platform.');
        }

        if ($this->password) {
            $mail->line('Please keep your login credentials secure and change your password after first login.');
        }

        $mail->action('Login to Dashboard', url('/login'))
            ->line('Thank you for choosing FSCredit!');

        return $mail;
    }
}

