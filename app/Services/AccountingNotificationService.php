<?php

namespace App\Services;

use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AccountingNotificationService
{
    protected const ACCOUNTING_EMAILS = [
        'accounting@foodstuff.store',
        'accountings@foodstuff.store',
    ];

    /**
     * Send email to accounting team
     */
    public static function sendToAccounting($subject, $view, $data = [])
    {
        try {
            foreach (self::ACCOUNTING_EMAILS as $email) {
                Mail::send($view, $data, function ($message) use ($email, $subject) {
                    $message->to($email)
                        ->subject($subject);
                });
            }
        } catch (\Exception $e) {
            Log::error('Failed to send accounting notification: ' . $e->getMessage(), [
                'subject' => $subject,
                'emails' => self::ACCOUNTING_EMAILS,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send simple text email to accounting team
     */
    public static function sendTextEmail($subject, $message)
    {
        try {
            foreach (self::ACCOUNTING_EMAILS as $email) {
                Mail::raw($message, function ($mail) use ($email, $subject) {
                    $mail->to($email)
                        ->subject($subject);
                });
            }
        } catch (\Exception $e) {
            Log::error('Failed to send accounting text email: ' . $e->getMessage(), [
                'subject' => $subject,
                'emails' => self::ACCOUNTING_EMAILS,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
