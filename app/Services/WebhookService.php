<?php

namespace App\Services;

use App\Models\Business;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    public function sendWebhook(Business $business, string $event, array $data): void
    {
        if (!$business->webhook_url) {
            return;
        }

        try {
            $payload = [
                'event' => $event,
                'timestamp' => now()->toIso8601String(),
                'data' => $data,
            ];

            $response = Http::timeout(10)
                ->post($business->webhook_url, $payload);

            if (!$response->successful()) {
                Log::warning('Webhook failed', [
                    'business_id' => $business->id,
                    'event' => $event,
                    'status' => $response->status(),
                    'response' => $response->body(),
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Webhook error', [
                'business_id' => $business->id,
                'event' => $event,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public function sendPaymentUpdate(Business $business, array $paymentData): void
    {
        $this->sendWebhook($business, 'payment.received', $paymentData);
    }

    public function sendStatusUpdate(Business $business, array $statusData): void
    {
        $this->sendWebhook($business, 'status.updated', $statusData);
    }

    public function sendError(Business $business, array $errorData): void
    {
        $this->sendWebhook($business, 'error.occurred', $errorData);
    }
}

