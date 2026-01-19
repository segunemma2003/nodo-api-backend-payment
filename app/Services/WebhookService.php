<?php

namespace App\Services;

use App\Models\Business;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    /**
     * Send webhook to business callback URL
     * Creates a delivery record and tracks status
     * If business responds with 200, marks as delivered
     * If not, marks as failed and schedules retry
     */
    public function sendWebhook(Business $business, string $event, array $data): void
    {
        if (!$business->webhook_url) {
            return;
        }

        $payload = [
            'event' => $event,
            'timestamp' => now()->toIso8601String(),
            'data' => $data,
        ];

        // Generate webhook signature using business API token
        $payloadString = json_encode($payload);
        $signature = hash_hmac('sha256', $payloadString, $business->api_token);

        // Create webhook delivery record
        $delivery = WebhookDelivery::create([
            'business_id' => $business->id,
            'event' => $event,
            'url' => $business->webhook_url,
            'payload' => $payload,
            'status' => 'pending',
            'attempts' => 1,
        ]);

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'X-FSCredit-Signature' => $signature,
                    'X-FSCredit-Event' => $event,
                ])
                ->post($business->webhook_url, $payload);

            $httpStatus = $response->status();
            $responseBody = $response->body();

            // If business responds with 200, mark as delivered and stop retrying
            if ($httpStatus === 200) {
                $delivery->markAsDelivered($httpStatus, $responseBody);
                Log::info('Webhook delivered successfully', [
                    'business_id' => $business->id,
                    'event' => $event,
                    'delivery_id' => $delivery->id,
                ]);
            } else {
                // Not 200 response - mark as failed and schedule retry
                $delivery->markAsFailed(
                    "Business returned HTTP {$httpStatus}",
                    $httpStatus,
                    $responseBody
                );
                Log::warning('Webhook failed - non-200 response', [
                    'business_id' => $business->id,
                    'event' => $event,
                    'status' => $httpStatus,
                    'delivery_id' => $delivery->id,
                ]);
            }
        } catch (\Exception $e) {
            // Network error or timeout - mark as failed and schedule retry
            $delivery->markAsFailed($e->getMessage());
            Log::error('Webhook error', [
                'business_id' => $business->id,
                'event' => $event,
                'error' => $e->getMessage(),
                'delivery_id' => $delivery->id,
            ]);
        }
    }

    /**
     * Retry failed webhook deliveries
     * Called by scheduled job every hour
     */
    public function retryFailedWebhooks(): void
    {
        $failedDeliveries = WebhookDelivery::where('status', 'failed')
            ->where('next_retry_at', '<=', now())
            ->with('business')
            ->get();

        foreach ($failedDeliveries as $delivery) {
            // Skip if business no longer has webhook URL
            if (!$delivery->business->webhook_url) {
                $delivery->update(['status' => 'failed', 'error_message' => 'Business webhook URL removed']);
                continue;
            }

            // Update URL in case it changed
            $delivery->update(['url' => $delivery->business->webhook_url]);

            try {
                // Get business to access API token for signature
                $business = $delivery->business;
                $payloadString = json_encode($delivery->payload);
                $signature = hash_hmac('sha256', $payloadString, $business->api_token);

                $response = Http::timeout(10)
                    ->withHeaders([
                        'X-FSCredit-Signature' => $signature,
                        'X-FSCredit-Event' => $delivery->event,
                    ])
                    ->post($delivery->url, $delivery->payload);

                $httpStatus = $response->status();
                $responseBody = $response->body();

                // If business responds with 200, mark as delivered and stop retrying
                if ($httpStatus === 200) {
                    $delivery->markAsDelivered($httpStatus, $responseBody);
                    Log::info('Webhook retry successful', [
                        'business_id' => $delivery->business_id,
                        'event' => $delivery->event,
                        'delivery_id' => $delivery->id,
                        'attempts' => $delivery->attempts,
                    ]);
                } else {
                    // Still not 200 - mark as failed and schedule next retry
                    $delivery->markAsFailed(
                        "Business returned HTTP {$httpStatus}",
                        $httpStatus,
                        $responseBody
                    );
                    Log::warning('Webhook retry failed - non-200 response', [
                        'business_id' => $delivery->business_id,
                        'event' => $delivery->event,
                        'status' => $httpStatus,
                        'delivery_id' => $delivery->id,
                        'attempts' => $delivery->attempts,
                    ]);
                }
            } catch (\Exception $e) {
                // Network error - mark as failed and schedule next retry
                $delivery->markAsFailed($e->getMessage());
                Log::error('Webhook retry error', [
                    'business_id' => $delivery->business_id,
                    'event' => $delivery->event,
                    'error' => $e->getMessage(),
                    'delivery_id' => $delivery->id,
                    'attempts' => $delivery->attempts,
                ]);
            }
        }
    }

    public function sendPaymentUpdate(Business $business, array $paymentData): void
    {
        $this->sendWebhook($business, 'payment.status_changed', $paymentData);
    }

    public function sendStatusUpdate(Business $business, array $statusData): void
    {
        $this->sendWebhook($business, 'invoice.status_changed', $statusData);
    }

    public function sendError(Business $business, array $errorData): void
    {
        $this->sendWebhook($business, 'error.occurred', $errorData);
    }
}

